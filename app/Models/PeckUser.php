<?php

namespace App\Models;

use Database\Factories\PeckUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PeckUser extends Model
{
    /** @use HasFactory<PeckUserFactory> */
    use HasFactory;

    public const STATUSES = [
        'applicant',
        'unverified',
        'ex_member',
        'member',
    ];

    public const DASHBOARD_EDITABLE_STATUSES = [
        'member',
        'ex_member',
    ];

    protected $table = 'peck_users';

    protected $primaryKey = 'gaijin_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'int';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'gaijin_id',
        'username',
        'discord_id',
        'tz',
        'status',
        'joindate',
        'initiator',
        'sqb_part',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gaijin_id' => 'integer',
            'discord_id' => 'integer',
            'joindate' => 'datetime',
            'initiator' => 'integer',
        ];
    }

    /**
     * The relationships that should always be loaded.
     *
     * @var list<string>
     */
    protected $with = ['userData'];

    /**
     * Pending user data values to persist after save.
     *
     * @var array<string, mixed>
     */
    private array $pendingUserData = [];

    protected static function booted(): void
    {
        static::saving(function (self $peckUser): void {
            $peckUser->status = self::resolvePersistedStatus(
                status: (string) $peckUser->status,
                discordId: $peckUser->discord_id,
            );

            if ($peckUser->isDirty('discord_id') && $peckUser->discord_id !== null) {
                PeckUserData::firstOrCreate(
                    ['discord_id' => $peckUser->discord_id],
                    [
                        'sqb_part' => $peckUser->pendingUserData['sqb_part'] ?? null,
                        'timezone' => $peckUser->pendingUserData['timezone'] ?? null,
                    ],
                );
            }
        });

        static::creating(function (self $peckUser): void {
            if ($peckUser->discord_id !== null) {
                PeckUserData::firstOrCreate(
                    ['discord_id' => $peckUser->discord_id],
                    [
                        'sqb_part' => $peckUser->pendingUserData['sqb_part'] ?? null,
                        'timezone' => $peckUser->pendingUserData['timezone'] ?? null,
                    ],
                );
            }
        });

        static::saved(function (self $peckUser): void {
            if ($peckUser->discord_id !== null && $peckUser->pendingUserData !== []) {
                PeckUserData::where('discord_id', $peckUser->discord_id)
                    ->update($peckUser->pendingUserData);
                $peckUser->pendingUserData = [];
                $peckUser->unsetRelation('userData');
            }
        });
    }

    protected static function newFactory(): PeckUserFactory
    {
        return PeckUserFactory::new();
    }

    public function userData(): BelongsTo
    {
        return $this->belongsTo(PeckUserData::class, 'discord_id', 'discord_id');
    }

    public function getTzAttribute(): ?int
    {
        return $this->userData?->timezone;
    }

    public function setTzAttribute(?int $value): void
    {
        $this->pendingUserData['timezone'] = $value;
    }

    public function getSqbPartAttribute(): ?bool
    {
        return $this->userData?->sqb_part;
    }

    public function setSqbPartAttribute(?bool $value): void
    {
        $this->pendingUserData['sqb_part'] = $value;
    }

    public function initiatorUser(): BelongsTo
    {
        return $this->belongsTo(self::class, 'initiator', 'gaijin_id');
    }

    public function initiatorOfficer(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'initiator', 'gaijin_id');
    }

    public function officer(): HasOne
    {
        return $this->hasOne(Officer::class, 'gaijin_id', 'gaijin_id');
    }

    public function leaveInfo(): HasOne
    {
        return $this->hasOne(PeckLeaveInfo::class, 'user_id', 'gaijin_id');
    }

    public function contexts(): HasMany
    {
        return $this->hasMany(PeckUserContext::class, 'user_id', 'gaijin_id');
    }

    public static function resolvePersistedStatus(string $status, mixed $discordId): string
    {
        if ($status === 'member' && $discordId === null) {
            return 'unverified';
        }

        if ($status === 'unverified' && $discordId !== null) {
            return 'member';
        }

        return $status;
    }
}
