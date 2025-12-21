<?php

namespace App\Core\Traits;

trait EncryptsCredentials
{
    public function setCredentialsAttribute($value): void
    {
        if (empty($value)) {
            $this->attributes['credentials'] = null;
            return;
        }
        
        $this->attributes['credentials'] = encrypt(json_encode($value));
    }

    public function getCredentialsAttribute($value): array
    {
        if (empty($value)) {
            return [];
        }
        
        try {
            return json_decode(decrypt($value), true) ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }
}

