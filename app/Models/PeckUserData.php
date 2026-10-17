<?php

namespace App\Models;

use Database\Factories\PeckUserDataFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeckUserData extends Model
{
    /** @use HasFactory<PeckUserDataFactory> */
    use HasFactory;

    protected $table = 'peck_user_data';

    protected $primaryKey = 'discord_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'int';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'discord_id',
        'sqb_part',
        'timezone',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discord_id' => 'integer',
            'sqb_part' => 'boolean',
            'timezone' => 'integer',
        ];

    }

    /**
     * @return HasMany<PeckUser, $this>
     */
    public function peckUsers(): HasMany
    {
        return $this->hasMany(PeckUser::class, 'discord_id', 'discord_id');
    }
}
