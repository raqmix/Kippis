<?php

namespace App\Services\Notifications;

/**
 * Builds the bilingual push strings from lang/{en,ar}/pushes.php. Customers
 * have no stored language, so every push carries both languages (the same
 * pattern the order status pushes always used).
 */
class PushText
{
    public const LOCALES = ['en', 'ar'];

    /**
     * @param array<string, string|int|array<string,string>> $replace A value
     *        may be per-locale (['en' => 'Latte', 'ar' => '...']).
     */
    public static function compose(string $key, array $replace = [], string $separator = ' '): string
    {
        $parts = [];
        foreach (self::LOCALES as $locale) {
            $parts[] = trans($key, self::forLocale($replace, $locale), $locale);
        }

        return implode($separator, $parts);
    }

    public static function title(string $key, array $replace = []): string
    {
        return self::compose($key, $replace, ' • ');
    }

    public static function body(string $key, array $replace = []): string
    {
        return self::compose($key, $replace, ' ');
    }

    private static function forLocale(array $replace, string $locale): array
    {
        $out = [];
        foreach ($replace as $name => $value) {
            $out[$name] = is_array($value) ? (string) ($value[$locale] ?? reset($value)) : (string) $value;
        }

        return $out;
    }
}
