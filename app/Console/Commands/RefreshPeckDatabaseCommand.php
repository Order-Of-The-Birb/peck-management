<?php

namespace App\Console\Commands;

use App\Actions\RefreshPeckDB;
use Illuminate\Console\Command;
use Throwable;

class RefreshPeckDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'peck:refresh-db
                            {--squadron= : Override the configured squadron name}
                            {--no-leave-sync : Skip synchronizing ex-member leave states}
                            {--dry-run : Fetch and report without writing database changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh PECK users from ThunderInsights';

    /**
     * Execute the console command.
     */
    public function handle(RefreshPeckDB $refreshPeckDB): int
    {
        try {
            $stats = $refreshPeckDB->handle(
                squadronName: $this->option('squadron') ?: null,
                synchronizeLeaveStates: ! (bool) $this->option('no-leave-sync'),
                dryRun: (bool) $this->option('dry-run'),
            );
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Metric', 'Value'],
            collect($stats)
                ->map(fn (int $value, string $metric): array => [$metric, (string) $value])
                ->values()
                ->all(),
        );

        if ((bool) $this->option('dry-run')) {
            $this->comment('Dry run completed without database writes.');
        }

        $this->info('PECK database refresh completed.');

        return self::SUCCESS;
    }
}
