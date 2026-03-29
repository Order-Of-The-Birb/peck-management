<?php

namespace App\Models;

use Database\Factories\ApiKeyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    /** @use HasFactory<ApiKeyFactory> */
    use HasFactory;

    protected $table = 'api_keys';

    protected $primaryKey = 'owner';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'int';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner',
        'key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owner' => 'integer',
            'key' => 'string',
        ];
    }

    protected static function newFactory(): ApiKeyFactory
    {
        return ApiKeyFactory::new();
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner');
    }
}
