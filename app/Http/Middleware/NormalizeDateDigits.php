<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Apps running in an Arabic locale format dates with Arabic-Indic digits
 * (e.g. "٢٠٢٦-١١-١٥"). Convert them to ASCII digits on date-like fields so
 * validation and Carbon::parse() see "2026-11-15".
 */
class NormalizeDateDigits
{
    public const FIELDS = ['date', 'birthdate', 'event_date', 'event_time', 'from', 'to'];

    private const MAP = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    public static function digits(string $value): string
    {
        return strtr($value, self::MAP);
    }

    public function handle(Request $request, Closure $next): Response
    {
        foreach ([$request->query, $request->request] as $bag) {
            foreach (self::FIELDS as $key) {
                if (is_string($v = $bag->get($key))) {
                    $bag->set($key, self::digits($v));
                }
            }
        }
        if ($request->isJson()) {
            $json = $request->json();
            foreach (self::FIELDS as $key) {
                if (is_string($v = $json->get($key))) {
                    $json->set($key, self::digits($v));
                }
            }
        }

        return $next($request);
    }
}
