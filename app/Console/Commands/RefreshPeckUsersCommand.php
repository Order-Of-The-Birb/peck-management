<?php

namespace App\Console\Commands;

use App\Actions\RefreshPeckUsersFromThunderInsights;
use Illuminate\Console\Command;

class RefreshPeckUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'peck:refresh-users
        {--squadron= : Squadron name override}
        {--no-leave-sync : Do not mark absent members as ex_member}
        {--dry-run : Fetch and parse only, without writing changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh peck_users from ThunderInsights squadron data';

    /**
     * Execute the console command.
     */
    public function handle(RefreshPeckUsersFromThunderInsights $refreshAction): int
    {
        $squadronName = $this->option('squadron');
        $synchronizeLeaveStates = ! (bool) $this->option('no-leave-sync');
        $dryRun = (bool) $this->option('dry-run');

        try {
            $stats = $refreshAction->handle(
                squadronName: is_string($squadronName) ? $squadronName : null,
                synchronizeLeaveStates: $synchronizeLeaveStates,
                dryRun: $dryRun,
            );
        } catch (\Throwable $throwable) {
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Members received', $stats['members_received']],
                ['Users created', $stats['users_created']],
                ['Users updated', $stats['users_updated']],
                ['Initiators updated', $stats['initiators_updated']],
                ['Marked ex_member', $stats['marked_ex_members']],
                ['Reactivated members', $stats['reactivated_members']],
                ['Leave records removed', $stats['leave_records_removed']],
                ['Dry run', $dryRun ? 'yes' : 'no'],
                ['Leave sync enabled', $synchronizeLeaveStates ? 'yes' : 'no'],
            ],
        );

        return self::SUCCESS;
    }
}
