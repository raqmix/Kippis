<?php

namespace App\Console\Commands;

use App\Services\CreatorDropService;
use Illuminate\Console\Command;

class DropLifecycleCommand extends Command
{
    protected $signature   = 'drops:lifecycle';
    protected $description = 'Activate scheduled drops and end expired drops';

    public function handle(CreatorDropService $service): int
    {
        $service->runLifecycle();
        $this->info('Drop lifecycle processed.');
        return self::SUCCESS;
    }
}
