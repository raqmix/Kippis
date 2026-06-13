<?php

namespace App\Services\Wallet;

use App\Core\Models\LoyaltyWallet;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Google Wallet (Wallet Objects API) integration.
 *
 * Build flow:
 * 1. One-time `loyaltyClass.insert` (idempotent — we treat 409 as ok).
 * 2. Per-customer `loyaltyObject` containing the points + barcode.
 * 3. Sign a "save to wallet" JWT that the customer's phone opens via
 *    https://pay.google.com/gp/v/save/{jwt}.
 *
 * On state change: PATCH the LoyaltyObject — Google fans out the new
 * state to every device that saved it (sub-second propagation usually,
 * comfortably inside the 60s SLA).
 */
class GoogleWalletService
{
    public function isReady(): bool
    {
        $cfg = config('wallet.google');
        if (!($cfg['enabled'] ?? false)) {
            return false;
        }
        return !empty($cfg['issuer_id'])
            && !empty($cfg['service_account_path'])
            && is_readable($cfg['service_account_path']);
    }

    /**
     * Return the URL the mobile app opens to add this wallet to
     * Google Wallet. Triggers the LoyaltyClass create-or-ensure on
     * first call.
     */
    public function buildSaveUrl(LoyaltyWallet $wallet): string
    {
        if (!$this->isReady()) {
            throw new RuntimeException('WALLET_NOT_CONFIGURED');
        }

        $this->ensureClassExists();

        $cfg = config('wallet.google');
        $issuerId = $cfg['issuer_id'];
        $classId = "{$issuerId}.{$cfg['class_suffix']}";
        $objectId = $this->objectId($wallet);

        $loyaltyObject = $this->composeLoyaltyObject($wallet, $classId, $objectId, $cfg);
        $serviceAccount = $this->loadServiceAccount($cfg);

        $payload = [
            'iss' => $serviceAccount['client_email'],
            'aud' => 'google',
            'origins' => ['https://kippis-eg.com'],
            'typ' => 'savetowallet',
            'iat' => time(),
            'payload' => [
                'loyaltyObjects' => [$loyaltyObject],
            ],
        ];

        $jwt = JWT::encode($payload, $serviceAccount['private_key'], 'RS256');
        return "https://pay.google.com/gp/v/save/{$jwt}";
    }

    /**
     * Called from `PushWalletUpdate` when points change. Google
     * propagates to every device that saved the object within
     * seconds.
     */
    public function patchLoyaltyObject(LoyaltyWallet $wallet): void
    {
        if (!$this->isReady()) {
            Log::info('Google Wallet not configured, skipping patch', [
                'wallet_id' => $wallet->id,
            ]);
            return;
        }

        $token = $this->accessToken();
        $objectId = $this->objectId($wallet);

        $body = [
            'loyaltyPoints' => [
                'label' => 'Points',
                'balance' => ['int' => (int) $wallet->points],
            ],
        ];

        $response = Http::withToken($token)
            ->patch("https://walletobjects.googleapis.com/walletobjects/v1/loyaltyObject/{$objectId}", $body);

        // 404 = object never existed (customer hasn't tapped Save yet);
        // treat as a no-op rather than retry forever.
        if ($response->status() === 404) {
            return;
        }
        if (!$response->successful()) {
            throw new RuntimeException("Google Wallet patch failed ({$response->status()}): {$response->body()}");
        }
    }

    private function ensureClassExists(): void
    {
        $cfg = config('wallet.google');
        $issuerId = $cfg['issuer_id'];
        $classId = "{$issuerId}.{$cfg['class_suffix']}";

        // Cache the "yes, class exists" check so we don't fire a GET
        // before every save-URL request.
        if (Cache::get("google_wallet_class:{$classId}")) {
            return;
        }

        $token = $this->accessToken();

        // 200 = exists, 404 = create it.
        $check = Http::withToken($token)
            ->get("https://walletobjects.googleapis.com/walletobjects/v1/loyaltyClass/{$classId}");
        if ($check->successful()) {
            Cache::put("google_wallet_class:{$classId}", true, now()->addDay());
            return;
        }
        if ($check->status() !== 404) {
            throw new RuntimeException("Google Wallet class lookup failed: {$check->status()} {$check->body()}");
        }

        $design = $cfg['design'];
        $insert = Http::withToken($token)
            ->post('https://walletobjects.googleapis.com/walletobjects/v1/loyaltyClass', [
                'id' => $classId,
                'issuerName' => $design['program_name'],
                'programName' => $design['program_name'],
                'programLogo' => [
                    'sourceUri' => ['uri' => $design['program_logo_url']],
                ],
                'hexBackgroundColor' => $design['background_hex'],
                'reviewStatus' => 'UNDER_REVIEW',
            ]);
        if (!$insert->successful() && $insert->status() !== 409 /* already exists */) {
            throw new RuntimeException("Google Wallet class create failed: {$insert->status()} {$insert->body()}");
        }
        Cache::put("google_wallet_class:{$classId}", true, now()->addDay());
    }

    private function composeLoyaltyObject(LoyaltyWallet $wallet, string $classId, string $objectId, array $cfg): array
    {
        $customer = $wallet->customer;
        return [
            'id' => $objectId,
            'classId' => $classId,
            'state' => 'ACTIVE',
            'accountId' => (string) $wallet->id,
            'accountName' => $customer?->name ?? 'Kippis Member',
            'loyaltyPoints' => [
                'label' => 'Points',
                'balance' => ['int' => (int) $wallet->points],
            ],
            'barcode' => [
                'type' => 'QR_CODE',
                'value' => $wallet->qr_token,
                'alternateText' => substr($wallet->qr_token, 0, 12) . '…',
            ],
        ];
    }

    private function objectId(LoyaltyWallet $wallet): string
    {
        $cfg = config('wallet.google');
        // Object IDs are namespaced under the issuer; we key per
        // wallet so the same id is reusable across save / patch.
        $serial = $wallet->wallet_pass_serial ?? $wallet->id;
        return "{$cfg['issuer_id']}.{$serial}";
    }

    private function loadServiceAccount(array $cfg): array
    {
        $raw = file_get_contents($cfg['service_account_path']);
        $json = json_decode($raw, true);
        if (!is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            throw new RuntimeException('Invalid Google Wallet service account JSON.');
        }
        return $json;
    }

    private function accessToken(): string
    {
        // 1h cache matches Google's token lifetime; we let `iat`-based
        // retries handle clock skew rather than refreshing eagerly.
        return Cache::remember('google_wallet_access_token', now()->addMinutes(50), function () {
            $cfg = config('wallet.google');
            $sa = $this->loadServiceAccount($cfg);

            $now = time();
            $jwt = JWT::encode([
                'iss' => $sa['client_email'],
                'scope' => 'https://www.googleapis.com/auth/wallet_object.issuer',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], $sa['private_key'], 'RS256');

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);
            if (!$response->successful()) {
                throw new RuntimeException("Google Wallet token exchange failed: {$response->status()} {$response->body()}");
            }
            return $response->json('access_token');
        });
    }
}
