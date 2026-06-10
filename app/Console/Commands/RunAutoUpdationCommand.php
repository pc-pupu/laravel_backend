<?php

namespace App\Console\Commands;

use App\Services\AutoUpdationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunAutoUpdationCommand extends Command
{
    protected $signature = 'housing:auto-updation {task=all : offer|offer-ext|license|license-ext|transfer|all}';

    protected $description = 'Run housing auto-updation tasks (Drupal auto_updation parity)';

    public function handle(AutoUpdationService $service): int
    {
        $task = $this->argument('task');
        $lines = match ($task) {
            'offer' => $service->runOfferCancellation(),
            'offer-ext' => $service->runOfferAfterExtension(),
            'license' => $service->runLicenseCancellation(),
            'license-ext' => $service->runLicenseAfterExtension(),
            'transfer' => $service->runTransferChecking(),
            default => $service->runAll(),
        };

        foreach ($lines as $line) {
            $this->line($line);
        }
        Log::info('housing:auto-updation completed', ['task' => $task, 'lines' => $lines]);

        return self::SUCCESS;
    }
}
