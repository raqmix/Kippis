<?php

namespace App\Services\AppOffer;

use App\Core\Models\Setting;
use App\Support\ClientPlatform;

/**
 * The app-only discount ("30% off every order in the app").
 *
 * Settings (admin: Filament > App Rewards):
 *   app_offer.enabled    bool,  default true
 *   app_offer.percent    0-100, default 30
 *   app_offer.platforms  array, default ['mobile']
 *   app_offer.label_en / app_offer.label_ar  optional overrides of the
 *                        lang('app_offer.label') text
 *
 * The discount applies to the whole order (add-ons included) minus any
 * reward/gift value, and never combines with promotions: Cart::recalculate()
 * keeps whichever of the two is larger (ties go to the app discount).
 */
class AppOffer
{
    public function enabled(): bool
    {
        return (bool) Setting::get('app_offer.enabled', true) && $this->percent() > 0;
    }

    public function percent(): float
    {
        $percent = (float) Setting::get('app_offer.percent', 30);

        return max(0.0, min(100.0, $percent));
    }

    /** @return array<int, string> */
    public function platforms(): array
    {
        $platforms = Setting::get('app_offer.platforms', [ClientPlatform::MOBILE]);
        if (is_string($platforms)) {
            $platforms = array_map('trim', explode(',', $platforms));
        }

        return array_values(array_filter(array_map('strval', (array) $platforms)));
    }

    /** Is $platform an "app" platform (also used by streak / double points / Big Win)? */
    public function isAppPlatform(?string $platform): bool
    {
        return $platform !== null && in_array($platform, $this->platforms(), true);
    }

    /** Should a cart / order on $platform get the app discount right now? */
    public function appliesTo(?string $platform): bool
    {
        return $this->enabled() && $this->isAppPlatform($platform);
    }

    /** EGP off for a discountable base, rounded to piasters. */
    public function discountFor(float $base): float
    {
        if ($base <= 0) {
            return 0.0;
        }

        return round(min($base, $base * $this->percent() / 100), 2);
    }

    public function label(string $locale): string
    {
        $override = (string) Setting::get('app_offer.label_' . $locale, '');
        if ($override !== '') {
            return str_replace(':percent', $this->percentLabel(), $override);
        }

        return __('app_offer.label', ['percent' => $this->percentLabel()], $locale);
    }

    public function percentLabel(): string
    {
        $p = $this->percent();

        return (string) (floor($p) == $p ? (int) $p : $p);
    }

    /** Shape used by GET /v1/app-config. */
    public function toArray(): array
    {
        return [
            'enabled'   => $this->enabled(),
            'percent'   => (float) $this->percent(),
            'platforms' => $this->platforms(),
            'label_en'  => $this->label('en'),
            'label_ar'  => $this->label('ar'),
        ];
    }
}
