<?php

namespace App\Helpers;

use Carbon\Carbon;

class ArabicTimeHelper
{
    /**
     * Get human-readable Arabic relative time
     * 
     * @param Carbon|string $datetime
     * @return string
     */
    public static function diffForHumans($datetime): string
    {
        if (is_string($datetime)) {
            $datetime = Carbon::parse($datetime);
        }

        $now = Carbon::now();
        $diffInSeconds = $now->diffInSeconds($datetime);
        $diffInMinutes = $now->diffInMinutes($datetime);
        $diffInHours = $now->diffInHours($datetime);
        $diffInDays = $now->diffInDays($datetime);
        $diffInWeeks = $now->diffInWeeks($datetime);
        $diffInMonths = $now->diffInMonths($datetime);
        $diffInYears = $now->diffInYears($datetime);

        if ($diffInSeconds < 60) {
            return 'منذ لحظات';
        }

        if ($diffInMinutes < 60) {
            if ($diffInMinutes == 1) {
                return 'منذ دقيقة';
            }
            return "منذ {$diffInMinutes} دقائق";
        }

        if ($diffInHours < 24) {
            if ($diffInHours == 1) {
                return 'منذ ساعة';
            }
            return "منذ {$diffInHours} ساعات";
        }

        if ($diffInDays < 7) {
            if ($diffInDays == 1) {
                return 'منذ يوم';
            }
            return "منذ {$diffInDays} أيام";
        }

        if ($diffInWeeks < 4) {
            if ($diffInWeeks == 1) {
                return 'منذ أسبوع';
            }
            return "منذ {$diffInWeeks} أسابيع";
        }

        if ($diffInMonths < 12) {
            if ($diffInMonths == 1) {
                return 'منذ شهر';
            }
            return "منذ {$diffInMonths} أشهر";
        }

        if ($diffInYears == 1) {
            return 'منذ سنة';
        }

        return "منذ {$diffInYears} سنوات";
    }
}

