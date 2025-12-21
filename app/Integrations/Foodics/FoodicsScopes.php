<?php

namespace App\Integrations\Foodics;

class FoodicsScopes
{
    /**
     * Get required scopes from config.
     *
     * @return array
     */
    public static function required(): array
    {
        $scopes = config('foodics.scopes', '');

        if (empty($scopes)) {
            return [];
        }

        return array_filter(array_map('trim', explode(',', $scopes)));
    }

    /**
     * Check if a specific scope is required.
     *
     * @param string $scope
     * @return bool
     */
    public static function has(string $scope): bool
    {
        return in_array($scope, self::required());
    }
}

