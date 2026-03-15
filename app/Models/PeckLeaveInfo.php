<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeckLeaveInfo extends Model
{
    public const TYPE_LEFT = 'Left';

    public const TYPE_LEFT_SERVER = 'LeftServer';

    public const TYPE_LEFT_SQUADRON = 'LeftSquadron';

    protected $table = 'peck_leave_info';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'int';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
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
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(PeckUser::class, 'user_id', 'gaijin_id');
    }
}
