<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Models\LoyaltyWallet;
use App\Core\Models\WalletPassRegistration;
use App\Http\Controllers\Controller;
use App\Services\Wallet\AppleWalletService;
use App\Services\Wallet\GoogleWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Mobile-app and Apple-web-service endpoints for the loyalty wallet
 * pass. Two halves:
 *
 * 1. Customer-authenticated routes (`/wallet/apple/pass`,
 *    `/wallet/google/save-url`, `/wallet/state`) — the mobile app
 *    calls these to render the "Add to Wallet" buttons and fetch the
 *    actual pass bytes / save URL.
 *
 * 2. Apple PassKit web service routes (`/v1/devices/...`,
 *    `/v1/passes/...`, `/v1/log`) — Apple's servers call these on
 *    behalf of installed devices. Auth is Apple-spec: a header of the
 *    form `Authorization: ApplePass {auth_token}` where the token is
 *    the wallet's `qr_token` (same role as a session token, scoped to
 *    this single pass).
 */
class WalletPassController extends Controller
{
    public function __construct(
        private readonly AppleWalletService $apple,
        private readonly GoogleWalletService $google,
    ) {
    }

    /**
     * GET /api/v1/wallet/state
     *
     * Returns which providers are configured + the wallet's serial.
     * Mobile app reads this to decide whether to render the Add-to-
     * Wallet buttons.
     */
    public function state(Request $request): JsonResponse
    {
        $customer = $request->user();
        $wallet = LoyaltyWallet::firstWhere('customer_id', $customer->id);

        return apiSuccess([
            'apple_enabled' => $this->apple->isReady(),
            'google_enabled' => $this->google->isReady(),
            'serial' => $wallet?->wallet_pass_serial,
            'has_wallet' => $wallet !== null,
        ]);
    }

    /**
     * GET /api/v1/wallet/apple/pass
     *
     * Returns the signed `.pkpass` as `application/vnd.apple.pkpass`.
     * Mobile app downloads this and opens it via `url_launcher` /
     * `OpenFile` so the system Wallet app picks it up.
     */
    public function applePass(Request $request)
    {
        if (!$this->apple->isReady()) {
            return apiError('WALLET_NOT_CONFIGURED', 'Apple Wallet is not configured.', 503);
        }

        $customer = $request->user();
        $wallet = LoyaltyWallet::firstWhere('customer_id', $customer->id);
        if (!$wallet) {
            return apiError('NO_WALLET', 'No loyalty wallet for this customer.', 404);
        }

        try {
            $bytes = $this->apple->buildPass($wallet);
        } catch (\Throwable $e) {
            Log::error('Apple pass build failed', [
                'wallet_id' => $wallet->id,
                'error' => $e->getMessage(),
            ]);
            return apiError('WALLET_BUILD_FAILED', $e->getMessage(), 500);
        }

        return response($bytes)
            ->header('Content-Type', 'application/vnd.apple.pkpass')
            ->header('Content-Disposition', 'attachment; filename="kippis-loyalty.pkpass"');
    }

    /**
     * GET /api/v1/wallet/google/save-url
     *
     * Returns the URL the mobile app should open to add this wallet
     * to Google Wallet. The URL embeds a signed JWT that Google's
     * wallet app validates.
     */
    public function googleSaveUrl(Request $request): JsonResponse
    {
        if (!$this->google->isReady()) {
            return apiError('WALLET_NOT_CONFIGURED', 'Google Wallet is not configured.', 503);
        }

        $customer = $request->user();
        $wallet = LoyaltyWallet::firstWhere('customer_id', $customer->id);
        if (!$wallet) {
            return apiError('NO_WALLET', 'No loyalty wallet for this customer.', 404);
        }

        try {
            $url = $this->google->buildSaveUrl($wallet);
        } catch (\Throwable $e) {
            Log::error('Google save URL build failed', [
                'wallet_id' => $wallet->id,
                'error' => $e->getMessage(),
            ]);
            return apiError('WALLET_BUILD_FAILED', $e->getMessage(), 500);
        }

        return apiSuccess(['save_url' => $url]);
    }

    // -----------------------------------------------------------------
    // Apple PassKit web service spec endpoints. Path shape is fixed by
    // Apple — DO NOT rename. Each is unauthenticated except for the
    // `ApplePass` header check inside the methods.
    // -----------------------------------------------------------------

    /**
     * POST /v1/devices/{deviceLib}/registrations/{passType}/{serial}
     *
     * Apple Wallet calls this when the customer installs the pass on
     * a device. Body carries the APNs push token.
     */
    public function registerDevice(
        Request $request,
        string $deviceLib,
        string $passType,
        string $serial,
    ) {
        $wallet = $this->authorisePass($request, $serial);
        if ($wallet === null) {
            return response('', Response::HTTP_UNAUTHORIZED);
        }

        $request->validate(['pushToken' => 'required|string|max:512']);

        WalletPassRegistration::updateOrCreate([
            'provider' => WalletPassRegistration::PROVIDER_APPLE,
            'device_library_id' => $deviceLib,
            'serial_number' => $serial,
        ], [
            'customer_id' => $wallet->customer_id,
            'pass_type_id' => $passType,
            'push_token' => $request->input('pushToken'),
            'last_updated_at' => now(),
        ]);

        return response('', Response::HTTP_CREATED);
    }

    /**
     * DELETE /v1/devices/{deviceLib}/registrations/{passType}/{serial}
     */
    public function unregisterDevice(
        Request $request,
        string $deviceLib,
        string $passType,
        string $serial,
    ) {
        $wallet = $this->authorisePass($request, $serial);
        if ($wallet === null) {
            return response('', Response::HTTP_UNAUTHORIZED);
        }

        WalletPassRegistration::where([
            'provider' => WalletPassRegistration::PROVIDER_APPLE,
            'device_library_id' => $deviceLib,
            'serial_number' => $serial,
        ])->delete();

        return response('', Response::HTTP_OK);
    }

    /**
     * GET /v1/devices/{deviceLib}/registrations/{passType}?passesUpdatedSince=
     *
     * Apple polls this to find passes needing refresh. We return the
     * serials updated since the supplied tag (we use the unix
     * timestamp of last_updated_at as the tag).
     */
    public function listUpdates(
        Request $request,
        string $deviceLib,
        string $passType,
    ): JsonResponse {
        $since = (int) $request->query('passesUpdatedSince', '0');

        $registrations = WalletPassRegistration::query()
            ->where('provider', WalletPassRegistration::PROVIDER_APPLE)
            ->where('device_library_id', $deviceLib)
            ->where('pass_type_id', $passType)
            ->get();

        $serials = [];
        $maxTimestamp = $since;
        foreach ($registrations as $registration) {
            $wallet = LoyaltyWallet::firstWhere('wallet_pass_serial', $registration->serial_number);
            if ($wallet === null) {
                continue;
            }
            $tag = $wallet->updated_at?->timestamp ?? 0;
            if ($tag > $since) {
                $serials[] = $registration->serial_number;
                if ($tag > $maxTimestamp) {
                    $maxTimestamp = $tag;
                }
            }
        }

        if (empty($serials)) {
            return response()->json([], Response::HTTP_NO_CONTENT);
        }

        return response()->json([
            'lastUpdated' => (string) $maxTimestamp,
            'serialNumbers' => $serials,
        ]);
    }

    /**
     * GET /v1/passes/{passType}/{serial}
     *
     * Apple downloads the latest pass after a push. Auth via the
     * `Authorization: ApplePass {qr_token}` header.
     */
    public function getPass(Request $request, string $passType, string $serial)
    {
        $wallet = $this->authorisePass($request, $serial);
        if ($wallet === null) {
            return response('', Response::HTTP_UNAUTHORIZED);
        }
        if (!$this->apple->isReady()) {
            return response('', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        try {
            $bytes = $this->apple->buildPass($wallet);
        } catch (\Throwable $e) {
            Log::error('Apple web service rebuild failed', [
                'serial' => $serial,
                'error' => $e->getMessage(),
            ]);
            return response('', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response($bytes)
            ->header('Content-Type', 'application/vnd.apple.pkpass')
            ->header('Last-Modified', gmdate('D, d M Y H:i:s', $wallet->updated_at?->timestamp ?? time()) . ' GMT');
    }

    /**
     * POST /v1/log
     *
     * Apple posts errors here. Just stash to the application log.
     */
    public function log(Request $request): JsonResponse
    {
        Log::info('Apple Wallet web service log', $request->all());
        return response()->json(['ok' => true]);
    }

    /**
     * Validates the `Authorization: ApplePass <token>` header. The
     * token is the wallet's qr_token. Returns the wallet on success.
     */
    private function authorisePass(Request $request, string $serial): ?LoyaltyWallet
    {
        $auth = $request->header('Authorization', '');
        if (!preg_match('/^ApplePass\s+(.+)$/', $auth, $m)) {
            return null;
        }
        $token = trim($m[1]);
        $wallet = LoyaltyWallet::firstWhere('wallet_pass_serial', $serial);
        if (!$wallet || $wallet->qr_token !== $token) {
            return null;
        }
        return $wallet;
    }
}
