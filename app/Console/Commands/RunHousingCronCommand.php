<?php

namespace App\Console\Commands;

use App\Services\HousingCronService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunHousingCronCommand extends Command
{
    protected $signature = 'housing:cron';

    protected $description = 'Run housing allotment cron jobs (Drupal cronjobs parity)';

    public function handle(HousingCronService $service): int
    {
        $lines = $service->run();
        foreach ($lines as $line) {
            $this->line($line);
        }
        Log::info('housing:cron completed', ['lines' => $lines]);

        return self::SUCCESS;
    }
}
