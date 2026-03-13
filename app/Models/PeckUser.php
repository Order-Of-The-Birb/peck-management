<?php

namespace App\Models;

use Database\Factories\PeckUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PeckUser extends Model
{
    /** @use HasFactory<PeckUserFactory> */
    use HasFactory;

    public const STATUSES = [
        'applicant',
        'unverified',
        'member',
        'ex_member',
        'alt',
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
            'tz' => 'integer',
            'joindate' => 'datetime',
            'initiator' => 'integer',
        ];
    }

    protected static function newFactory(): PeckUserFactory
    {
        return PeckUserFactory::new();
    }

    public function initiatorUser(): BelongsTo
    {
        return $this->belongsTo(self::class, 'initiator', 'gaijin_id');
    }

    public function leaveInfo(): HasOne
    {
        return $this->hasOne(PeckLeaveInfo::class, 'user_id', 'gaijin_id');
    }
}
