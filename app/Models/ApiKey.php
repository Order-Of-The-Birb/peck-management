<?php

namespace App\Models;

use Database\Factories\ApiKeyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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
        'key_prefix',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owner' => 'integer',
            'key' => 'string',
            'key_prefix' => 'string',
        ];
    }

    protected static function newFactory(): ApiKeyFactory
    {
        return ApiKeyFactory::new();
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public static function generatePlainToken(): string
    {
        return 'pmk_'.Str::random(60);
    }

    public static function prefixFromToken(string $plainToken): string
    {
        return substr($plainToken, 0, 12);
    }

    public static function issueForOwner(int $ownerId): string
    {
        $plainToken = self::generatePlainToken();

        self::query()->updateOrCreate(
            ['owner' => $ownerId],
            [
                'key' => self::hashToken($plainToken),
                'key_prefix' => self::prefixFromToken($plainToken),
            ],
        );

        return $plainToken;
    }

    public static function findByPlainToken(string $plainToken): ?self
    {
        return self::query()
            ->with('ownerUser')
            ->where('key', self::hashToken($plainToken))
            ->first();
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner');
    }
}
