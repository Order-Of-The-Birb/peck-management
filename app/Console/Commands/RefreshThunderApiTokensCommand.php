<?php

namespace App\Console\Commands;

use App\Actions\ServerThunderApi;
use App\Actions\ThunderApi;
use App\Models\ThunderApiToken;
use Illuminate\Console\Command;
use Throwable;

class RefreshThunderApiTokensCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'thunderapi:refresh-tokens';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh due ThunderAPI tokens without exceeding rate limits';

    /**
     * Execute the console command.
     */
    public function handle(ThunderApi $thunderApi, ServerThunderApi $serverThunderApi): int
    {
        $this->refreshServerToken($serverThunderApi);

        $batchSize = max(1, (int) config('peck.thunderapi_refresh.batch_size'));
        $refreshAfterHours = max(1, (int) config('peck.thunderapi_refresh.refresh_after_hours'));

        $tokens = ThunderApiToken::query()
            ->where(function ($query) use ($refreshAfterHours): void {
                $query
                    ->whereNull('refreshed_at')
                    ->orWhere('refreshed_at', '<=', now()->subHours($refreshAfterHours));
            })
            ->orderBy('expires_at')
            ->limit($batchSize)
            ->get();

        $refreshed = 0;
        $invalid = 0;

        foreach ($tokens as $token) {
            try {
                $expires = $thunderApi->refreshToken($token->token);
            } catch (Throwable $throwable) {
                $this->error($throwable->getMessage());

                break;
            }

            if ($expires === null) {
                $token->forceFill(['refreshed_at' => now()])->save();
                $invalid++;

                continue;
            }

            $token->forceFill([
                'expires_at' => $expires,
                'refreshed_at' => now(),
            ])->save();

            $refreshed++;
        }

        $this->info(sprintf(
            'ThunderAPI tokens: %d refreshed, %d invalid, %d processed.',
            $refreshed,
            $invalid,
            $tokens->count(),
        ));

        return self::SUCCESS;
    }

    protected function refreshServerToken(ServerThunderApi $serverThunderApi): void
    {
        try {
            $serverThunderApi->refresh();
        } catch (Throwable $throwable) {
            $this->warn('Server ThunderAPI token: '.$throwable->getMessage());
        }
    }
}
