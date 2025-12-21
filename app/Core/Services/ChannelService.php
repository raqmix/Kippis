<?php

namespace App\Core\Services;

use App\Core\Models\Channel;

class ChannelService
{
    public function sync(Channel $channel): bool
    {
        // Placeholder for channel sync logic
        $channel->update(['last_sync_at' => now()]);
        return true;
    }
}
