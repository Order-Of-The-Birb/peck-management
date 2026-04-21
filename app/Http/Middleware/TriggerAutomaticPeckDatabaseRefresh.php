<?php

namespace App\Http\Middleware;

use App\Actions\RefreshPeckDB;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function Illuminate\Support\defer;

class TriggerAutomaticPeckDatabaseRefresh
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->dispatchRefreshIfDue($request);

        return $next($request);
    }

    protected function dispatchRefreshIfDue(Request $request): void
    {
        if (! config('peck.auto_refresh_enabled', false)) {
            return;
        }

        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return;
        }

        if ($request->is('api/*')) {
            return;
        }

        $schedule = (string) config('peck.refresh_schedule', '0:00');

        if (! preg_match('/^(?<hour>\d{1,2}):(?<minute>\d{2})$/', $schedule, $matches)) {
            Log::warning('Skipping automatic PECK database refresh because the refresh schedule is invalid.', [
                'refresh_schedule' => $schedule,
            ]);

            return;
        }

        $hour = (int) $matches['hour'];
        $minute = (int) $matches['minute'];

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            Log::warning('Skipping automatic PECK database refresh because the refresh schedule is out of range.', [
                'refresh_schedule' => $schedule,
            ]);

            return;
        }

        $now = now('UTC');

        $scheduledAt = $now->copy()->setTime($hour, $minute, 0);

        if ($now->lt($scheduledAt)) {
            return;
        }

        $today = $now->toDateString();
        $lastAttemptedDateKey = (string) config('peck.auto_refresh.last_attempted_date_key');
        $lockKey = (string) config('peck.auto_refresh.lock_key');
        $lockMinutes = max(1, (int) config('peck.auto_refresh.lock_minutes', 10));

        if (Cache::get($lastAttemptedDateKey) === $today) {
            return;
        }

        if (! Cache::add($lockKey, true, $now->copy()->addMinutes($lockMinutes))) {
            return;
        }

        Cache::put($lastAttemptedDateKey, $today, $now->copy()->addDays(14));

        defer(function () use ($lockKey): void {
            try {
                $stats = app(RefreshPeckDB::class)->handle();
                $refreshNow = now('UTC');

                Cache::put(
                    (string) config('peck.auto_refresh.last_successful_date_key'),
                    $refreshNow->toDateString(),
                    $refreshNow->copy()->addDays(30),
                );

                Log::info('Automatic PECK database refresh completed.', $stats);
            } catch (Throwable $throwable) {
                Log::error('Automatic PECK database refresh failed.', [
                    'message' => $throwable->getMessage(),
                ]);
            } finally {
                Cache::forget($lockKey);
            }
        }, 'peck-auto-refresh')->always();
    }
}
