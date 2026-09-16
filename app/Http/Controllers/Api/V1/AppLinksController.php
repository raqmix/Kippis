<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Models\Setting;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Serves the mobile-app download links used by the public website and
 * anywhere else that surfaces a "Get the app" CTA.
 *
 * All fields are edited from Filament → Marketing → App download links
 * (AppLinksSettings). The site fetches this once per session and
 * respects the *_enabled flags — a disabled platform's badge is hidden
 * (or shown as "coming soon", depending on the component). No app
 * store URL is ever hard-coded on the client.
 *
 * @group App
 */
class AppLinksController extends Controller
{
    /**
     * Get app store links + section copy.
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "ios": {"enabled": true, "url": "https://apps.apple.com/..."},
     *     "android": {"enabled": false, "url": null},
     *     "section": {
     *       "headline_en": "Get the Kippis app",
     *       "headline_ar": "حمّل تطبيق كيبيس",
     *       "body_en": "Order ahead, ...",
     *       "body_ar": "..."
     *     }
     *   }
     * }
     */
    public function index(): JsonResponse
    {
        $iosEnabled = (bool) Setting::get('app.ios_enabled', false);
        $androidEnabled = (bool) Setting::get('app.android_enabled', false);
        $iosUrl = (string) Setting::get('app.ios_url', '');
        $androidUrl = (string) Setting::get('app.android_url', '');

        return apiSuccess([
            'ios' => [
                'enabled' => $iosEnabled,
                // Never leak a URL when disabled — the flag is the source
                // of truth. Prevents a website bug that ignores `enabled`
                // from silently linking to a stale/wrong store listing.
                'url' => $iosEnabled ? ($iosUrl ?: null) : null,
            ],
            'android' => [
                'enabled' => $androidEnabled,
                'url' => $androidEnabled ? ($androidUrl ?: null) : null,
            ],
            'section' => [
                'headline_en' => (string) Setting::get('app.section_headline_en', 'Get the Kippis app'),
                'headline_ar' => (string) Setting::get('app.section_headline_ar', 'حمّل تطبيق كيبيس'),
                'body_en' => (string) Setting::get('app.section_body_en', 'Order ahead, track rewards, and unlock offers only in the app.'),
                'body_ar' => (string) Setting::get('app.section_body_ar', 'اطلب مسبقًا، تابع نقاطك، واحصل على عروض حصرية للتطبيق فقط.'),
            ],
        ]);
    }
}
