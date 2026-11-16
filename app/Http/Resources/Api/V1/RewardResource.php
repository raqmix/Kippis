<?php

namespace App\Http\Resources\Api\V1;

use App\Core\Models\CustomerRedeemWallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomerRedeemWallet */
class RewardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->displayStatus();
        if ($status === CustomerRedeemWallet::STATUS_APPLIED) {
            $status = 'used';
        }

        return [
            'id'            => $this->id,
            'title'         => $this->getTitle(app()->getLocale()),
            'title_en'      => $this->getTitle('en'),
            'title_ar'      => $this->getTitle('ar'),
            'source'        => $this->source,
            'reward_type'   => $this->reward_type,
            'reward_config' => $this->reward_config ?? (object) [],
            'code'          => $this->code,
            'qr_payload'    => $this->qrPayload(),
            'expires_at'    => optional($this->expires_at)->toIso8601String(),
            'status'        => $status,
            'usable_in_app' => $this->isUsableInApp(),
            'used_at'       => optional($this->used_at)->toIso8601String(),
            'created_at'    => optional($this->created_at)->toIso8601String(),
        ];
    }
}
