<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateBotTokenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:token
        {user : User ID or email}
        {--revoke : Revoke the current token for this user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate or revoke a bot API token for a user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userIdentifier = (string) $this->argument('user');
        $user = $this->resolveUser($userIdentifier);

        if ($user === null) {
            $this->error('User not found. Pass a valid numeric ID or email.');

            return self::FAILURE;
        }

        if ((bool) $this->option('revoke')) {
            $user->forceFill([
                'api_token' => null,
                'api_token_plain' => null,
            ]);
            $user->save();

            $this->info(sprintf('Revoked bot API token for user #%d (%s).', $user->id, $user->email));

            return self::SUCCESS;
        }

        $plainToken = sprintf('peck_%s', Str::random(64));
        $hashedToken = hash('sha256', $plainToken);

        $user->forceFill([
            'api_token' => $hashedToken,
            'api_token_plain' => $plainToken,
        ]);
        $user->save();

        $this->line(sprintf('User: #%d <%s>', $user->id, $user->email));
        $this->warn('Store this token safely. You can also copy it later from settings.');
        $this->line($plainToken);

        return self::SUCCESS;
    }

    protected function resolveUser(string $userIdentifier): ?User
    {
        if (is_numeric($userIdentifier)) {
            return User::query()->find((int) $userIdentifier);
        }

        return User::query()
            ->where('email', $userIdentifier)
            ->first();
    }
}
