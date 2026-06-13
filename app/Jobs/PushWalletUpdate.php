<?php

namespace App\Jobs;

use App\Core\Models\LoyaltyWallet;
use App\Core\Models\WalletPassRegistration;
use App\Services\Wallet\AppleWalletService;
use App\Services\Wallet\GoogleWalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Pushes a wallet-state-change to every installed pass for this
 * wallet. Fan-out is per-provider:
 *
 * Apple — APNs notification (empty payload) to every registered
 *   (device_library_id, push_token) row in `wallet_pass_registrations`.
 *   Devices wake up and GET /v1/passes/{type}/{serial} to pull the new
 *   pass.
 *
 * Google — single PATCH against the LoyaltyObject. Google fans out to
 *   every device that saved that object id. No per-device bookkeeping.
 *
 * Each provider is independently feature-flagged. Until creds land,
 * the calls short-circuit at the service layer.
 */
class PushWalletUpdate implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;
    public array $backoff = [10, 30, 60, 180, 600];

    public function __construct(
        public readonly int $walletId,
        public readonly string $reason,
    ) {
    }

    public function handle(
        AppleWalletService $apple,
        GoogleWalletService $google,
    ): void {
        $wallet = LoyaltyWallet::find($this->walletId);
        if ($wallet === null) {
            Log::warning('PushWalletUpdate: wallet vanished', ['wallet_id' => $this->walletId]);
            return;
        }

        $serial = $wallet->wallet_pass_serial;
        if (empty($serial)) {
            // Customer hasn't added the pass to any wallet yet; nothing
            // to push.
            return;
        }

        if (config('wallet.apple.enabled')) {
            try {
                $appleRegistrations = WalletPassRegistration::query()
                    ->where('provider', WalletPassRegistration::PROVIDER_APPLE)
                    ->where('serial_number', $serial)
                    ->whereNotNull('push_token')
                    ->get();
                foreach ($appleRegistrations as $registration) {
                    $apple->pushUpdate($registration);
                }
            } catch (\Throwable $e) {
                Log::error('Apple wallet push failed', [
                    'serial' => $serial,
                    'error' => $e->getMessage(),
                ]);
                throw $e; // Let the queue retry per the backoff schedule.
            }
        }

        if (config('wallet.google.enabled')) {
            try {
                $google->patchLoyaltyObject($wallet);
            } catch (\Throwable $e) {
                Log::error('Google wallet patch failed', [
                    'serial' => $serial,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }
    }
}
