<?php

namespace App\Services\Wallet;

use App\Core\Models\LoyaltyWallet;
use App\Core\Models\WalletPassRegistration;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use ZipArchive;

/**
 * Apple Wallet (PassKit) signer + APNs pusher.
 *
 * Produces a signed `.pkpass` bundle for a customer's loyalty wallet
 * and fires APNs notifications to wake installed copies when state
 * changes. Both code paths fail soft when `wallet.apple.enabled` is
 * false so the scaffolding ships before certs arrive — the front-end
 * gates the UI off `enabled` so users never see a broken button.
 */
class AppleWalletService
{
    public function isReady(): bool
    {
        $cfg = config('wallet.apple');
        if (!($cfg['enabled'] ?? false)) {
            return false;
        }
        return !empty($cfg['pass_type_id'])
            && !empty($cfg['team_id'])
            && !empty($cfg['pass_cert_path']) && is_readable($cfg['pass_cert_path'])
            && !empty($cfg['wwdr_cert_path']) && is_readable($cfg['wwdr_cert_path']);
    }

    public function isApnsReady(): bool
    {
        if (!$this->isReady()) {
            return false;
        }
        $cfg = config('wallet.apple');
        return !empty($cfg['apns_key_path']) && is_readable($cfg['apns_key_path'])
            && !empty($cfg['apns_key_id']);
    }

    /**
     * Build a signed .pkpass for this wallet and return the raw bytes.
     * Caller streams as `application/vnd.apple.pkpass`.
     */
    public function buildPass(LoyaltyWallet $wallet): string
    {
        if (!$this->isReady()) {
            throw new RuntimeException('WALLET_NOT_CONFIGURED');
        }

        $cfg = config('wallet.apple');
        $serial = $wallet->wallet_pass_serial;
        if (empty($serial)) {
            throw new RuntimeException('WALLET_HAS_NO_SERIAL');
        }

        $passJson = $this->composePassJson($wallet, $serial, $cfg);

        $assetsDir = $cfg['assets_dir'];
        if (!is_dir($assetsDir)) {
            throw new RuntimeException("Apple Wallet assets dir missing: {$assetsDir}");
        }

        // Assemble the pass dir in a tmp working directory; sign;
        // zip; return bytes; clean up.
        $work = sys_get_temp_dir() . '/pkpass_' . bin2hex(random_bytes(8));
        mkdir($work, 0700, true);

        try {
            file_put_contents($work . '/pass.json', json_encode($passJson, JSON_UNESCAPED_SLASHES));
            $this->copyAssets($assetsDir, $work);

            $manifest = $this->buildManifest($work);
            file_put_contents($work . '/manifest.json', json_encode($manifest, JSON_UNESCAPED_SLASHES));

            $this->signManifest($work . '/manifest.json', $work . '/signature', $cfg);

            $bundlePath = $work . '/pass.pkpass';
            $this->zipBundle($work, $bundlePath);

            return file_get_contents($bundlePath);
        } finally {
            $this->rrmdir($work);
        }
    }

    public function pushUpdate(WalletPassRegistration $registration): void
    {
        if (!$this->isApnsReady()) {
            Log::info('APNs not configured, skipping wallet push', [
                'serial' => $registration->serial_number,
            ]);
            return;
        }

        $cfg = config('wallet.apple');
        $jwt = $this->buildApnsJwt($cfg);
        $passTypeId = $registration->pass_type_id ?? $cfg['pass_type_id'];

        // PassKit pushes carry an EMPTY payload by spec — the device
        // wakes up and fetches the pass via the web service.
        $response = Http::withOptions(['version' => 2.0])
            ->withHeaders([
                'authorization' => "bearer {$jwt}",
                'apns-topic' => $passTypeId,
                'apns-push-type' => 'background',
                'apns-priority' => '5',
            ])
            ->post('https://api.push.apple.com/3/device/' . $registration->push_token, '{}');

        if ($response->status() === 410 /* Unregistered */) {
            // Apple says the device dropped the registration; clean up
            // so we don't keep pinging dead tokens.
            $registration->delete();
            return;
        }

        if (!$response->successful()) {
            throw new RuntimeException("APNs returned {$response->status()}: {$response->body()}");
        }
    }

    private function composePassJson(LoyaltyWallet $wallet, string $serial, array $cfg): array
    {
        $customer = $wallet->customer;
        $design = $cfg['design'];

        return [
            'formatVersion' => 1,
            'passTypeIdentifier' => $cfg['pass_type_id'],
            'serialNumber' => $serial,
            'teamIdentifier' => $cfg['team_id'],
            'organizationName' => $design['organization_name'],
            'description' => $design['description'],
            'logoText' => $design['logo_text'],
            'foregroundColor' => $design['foreground_color'],
            'backgroundColor' => $design['background_color'],
            'labelColor' => $design['label_color'],

            // Always-on web service so devices pull updates within 60s.
            'webServiceURL' => rtrim($cfg['web_service_url'], '/') . '/',
            'authenticationToken' => $wallet->qr_token,

            // Treat the loyalty pass as a "storeCard" — the right
            // PassKit template for a points wallet (no event date,
            // shows a strip image + balance prominently).
            'storeCard' => [
                'primaryFields' => [[
                    'key' => 'balance',
                    'label' => 'Points',
                    'value' => (int) $wallet->points,
                ]],
                'secondaryFields' => [[
                    'key' => 'member',
                    'label' => 'Member',
                    'value' => $customer?->name ?? 'Kippis Member',
                ]],
                'auxiliaryFields' => [[
                    'key' => 'since',
                    'label' => 'Since',
                    'value' => optional($wallet->created_at)->format('M Y') ?? '',
                ]],
                'backFields' => [
                    [
                        'key' => 'terms',
                        'label' => 'Terms',
                        'value' => 'Points are awarded per purchase per the Kippis Rewards terms. Visit kippis-eg.com for the latest rules.',
                    ],
                    [
                        'key' => 'support',
                        'label' => 'Support',
                        'value' => 'support@kippis-eg.com',
                    ],
                ],
            ],

            // Same QR token as the in-app wallet QR so a single kiosk
            // scanner reads both surfaces.
            'barcodes' => [[
                'format' => 'PKBarcodeFormatQR',
                'message' => $wallet->qr_token,
                'messageEncoding' => 'iso-8859-1',
                'altText' => substr($wallet->qr_token, 0, 12) . '…',
            ]],
        ];
    }

    private function copyAssets(string $assetsDir, string $work): void
    {
        // Required + optional Apple asset files. Anything missing
        // silently drops (Apple validates icon/logo at install time).
        $files = [
            'icon.png', 'icon@2x.png', 'icon@3x.png',
            'logo.png', 'logo@2x.png', 'logo@3x.png',
            'strip.png', 'strip@2x.png', 'strip@3x.png',
        ];
        foreach ($files as $f) {
            $src = $assetsDir . '/' . $f;
            if (is_file($src)) {
                copy($src, $work . '/' . $f);
            }
        }
    }

    private function buildManifest(string $work): array
    {
        $manifest = [];
        foreach (scandir($work) as $f) {
            if ($f === '.' || $f === '..' || $f === 'manifest.json' || $f === 'signature') {
                continue;
            }
            $manifest[$f] = sha1_file($work . '/' . $f);
        }
        return $manifest;
    }

    private function signManifest(string $manifestPath, string $signaturePath, array $cfg): void
    {
        $certData = file_get_contents($cfg['pass_cert_path']);
        $certs = [];
        if (!openssl_pkcs12_read($certData, $certs, $cfg['pass_cert_password'] ?? '')) {
            throw new RuntimeException('Could not read pass cert .p12 — check password.');
        }

        $cert = $certs['cert'];
        $privateKey = $certs['pkey'];
        $extraCerts = $cfg['wwdr_cert_path'];

        $tmpSig = $signaturePath . '.tmp';
        $ok = openssl_pkcs7_sign(
            $manifestPath,
            $tmpSig,
            $cert,
            $privateKey,
            [],
            PKCS7_BINARY | PKCS7_DETACHED,
            $extraCerts,
        );
        if (!$ok) {
            throw new RuntimeException('PKCS#7 sign failed: ' . openssl_error_string());
        }

        // openssl_pkcs7_sign returns S/MIME (with email headers + base64
        // body). Strip headers + decode to raw DER, which is what
        // Apple wants for the `signature` file inside the pass.
        $smime = file_get_contents($tmpSig);
        unlink($tmpSig);
        if (preg_match('/Content-Disposition.*?\\n\\n(.*?)\\n\\n/s', $smime, $m)) {
            $b64 = preg_replace('/[\\r\\n]+/', '', $m[1]);
            file_put_contents($signaturePath, base64_decode($b64));
        } else {
            throw new RuntimeException('Could not parse PKCS#7 signature output.');
        }
    }

    private function zipBundle(string $work, string $output): void
    {
        $zip = new ZipArchive();
        if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Could not create pkpass zip at {$output}");
        }
        foreach (scandir($work) as $f) {
            if ($f === '.' || $f === '..' || $f === 'pass.pkpass') {
                continue;
            }
            $zip->addFile($work . '/' . $f, $f);
        }
        $zip->close();
    }

    private function buildApnsJwt(array $cfg): string
    {
        // APNs uses an ES256-signed JWT, valid for ~1h. We don't cache
        // — the wallet push job rate is well under the renewal cost.
        $now = time();
        return JWT::encode(
            ['iss' => $cfg['team_id'], 'iat' => $now],
            file_get_contents($cfg['apns_key_path']),
            'ES256',
            $cfg['apns_key_id'],
        );
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $p = $dir . '/' . $f;
            is_dir($p) ? $this->rrmdir($p) : unlink($p);
        }
        rmdir($dir);
    }
}
