<?php

namespace App\Models;

use Database\Factories\PeckAltFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeckAlt extends Model
{
    /** @use HasFactory<PeckAltFactory> */
    use HasFactory;

    protected $table = 'peck_alts';

    protected $primaryKey = 'alt_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'int';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'alt_id',
        'owner_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'alt_id' => 'integer',
            'owner_id' => 'integer',
        ];
    }

    protected static function newFactory(): PeckAltFactory
    {
        return PeckAltFactory::new();
    }

    public function altUser(): BelongsTo
    {
        return $this->belongsTo(PeckUser::class, 'alt_id', 'gaijin_id');
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(PeckUser::class, 'owner_id', 'gaijin_id');
    }
}
