<?php

namespace App\Console\Commands;

use App\Services\StorageService;
use Illuminate\Console\Command;

class RecalculateStorage extends Command
{
    protected $signature = 'drive:recalculate-storage';
    protected $description = 'Recalculate storage_used for all users based on actual files';

    public function handle(StorageService $storageService)
    {
        $storageService->recalculateAllStorage();
        $this->info('Storage recalculation complete for all users.');
        return Command::SUCCESS;
    }
}
