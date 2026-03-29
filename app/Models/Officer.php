<?php

namespace App\Models;

use Database\Factories\OfficerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Officer extends Model
{
    /** @use HasFactory<OfficerFactory> */
    use HasFactory;

    protected $table = 'officers';

    protected $primaryKey = 'gaijin_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'int';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'gaijin_id',
        'rank',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gaijin_id' => 'integer',
        ];
    }

    protected static function newFactory(): OfficerFactory
    {
        return OfficerFactory::new();
    }

    public function peckUser(): BelongsTo
    {
        return $this->belongsTo(PeckUser::class, 'gaijin_id', 'gaijin_id');
    }

    public function initiatedUsers(): HasMany
    {
        return $this->hasMany(PeckUser::class, 'initiator', 'gaijin_id');
    }
}
