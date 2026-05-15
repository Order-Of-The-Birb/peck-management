<?php

namespace App\Models;

use App\Jobs\NotifyDiscordBotCacheInvalidation;
use Carbon\CarbonInterface;
use Database\Factories\PeckUserContextFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeckUserContext extends Model
{
    /** @use HasFactory<PeckUserContextFactory> */
    use HasFactory;

    public const TYPE_ONCE_ABSENCE = 'onceAbsence';

    public const TYPE_RECURRING_ABSENCE = 'recurringAbsence';

    public const TYPE_MISC = 'misc';

    public const TYPES = [
        self::TYPE_ONCE_ABSENCE,
        self::TYPE_RECURRING_ABSENCE,
        self::TYPE_MISC,
    ];

    protected $table = 'peck_user_contexts';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'context_id',
        'type',
        'from_date',
        'to_date',
        'weekdays',
        'month_day',
        'comment',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'context_id' => 'integer',
            'from_date' => 'date:Y-m-d',
            'to_date' => 'date:Y-m-d',
            'weekdays' => 'array',
            'month_day' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::created(static function (): void {
            self::dispatchCacheInvalidation();
        });

        static::updated(static function (): void {
            self::dispatchCacheInvalidation();
        });

        static::deleted(static function (): void {
            self::dispatchCacheInvalidation();
        });
    }

    protected static function newFactory(): PeckUserContextFactory
    {
        return PeckUserContextFactory::new();
    }

    public static function lowestAvailableContextId(int $userId): int
    {
        $assignedContextIds = self::query()
            ->where('user_id', $userId)
            ->orderBy('context_id')
            ->pluck('context_id')
            ->map(fn (mixed $contextId): int => (int) $contextId)
            ->values();

        $nextContextId = 0;

        foreach ($assignedContextIds as $assignedContextId) {
            if ($assignedContextId > $nextContextId) {
                break;
            }

            if ($assignedContextId === $nextContextId) {
                $nextContextId++;
            }
        }

        return $nextContextId;
    }

    public function isExpiredOnceAbsence(?CarbonInterface $today = null): bool
    {
        if ($this->type !== self::TYPE_ONCE_ABSENCE || $this->to_date === null) {
            return false;
        }

        return $this->to_date->isBefore(($today ?? today())->startOfDay());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(PeckUser::class, 'user_id', 'gaijin_id');
    }

    private static function dispatchCacheInvalidation(): void
    {
        NotifyDiscordBotCacheInvalidation::dispatch()->onConnection('database');
    }
}
