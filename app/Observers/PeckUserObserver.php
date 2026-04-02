<?php

namespace App\Observers;

use App\Jobs\NotifyDiscordBotCacheInvalidation;
use App\Models\PeckUser;

class PeckUserObserver
{
    public function created(PeckUser $peckUser): void
    {
        $this->dispatchCacheInvalidation();
    }

    public function updated(PeckUser $peckUser): void
    {
        $this->dispatchCacheInvalidation();
    }

    public function deleted(PeckUser $peckUser): void
    {
        $this->dispatchCacheInvalidation();
    }

    protected function dispatchCacheInvalidation(): void
    {
        NotifyDiscordBotCacheInvalidation::dispatch()->onConnection('database');
    }
}
