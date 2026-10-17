<?php

namespace App\Models;

use Database\Factories\ThunderApiServerTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThunderApiServerToken extends Model
{
    /** @use HasFactory<ThunderApiServerTokenFactory> */
    use HasFactory;

    public const TOKEN_TTL_SECONDS = 86_400;

    protected $table = 'thunderapi_server_tokens';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'token',
        'gaijin_id',
        'expires_at',
        'refreshed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gaijin_id' => 'integer',
            'expires_at' => 'integer',
            'refreshed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ThunderApiServerTokenFactory
    {
        return ThunderApiServerTokenFactory::new();
    }

    public function isExpired(): bool
    {
        return $this->expires_at <= now()->timestamp;
    }

    public function isRefreshDue(int $refreshAfterHours): bool
    {
        return $this->refreshed_at === null || $this->refreshed_at->lte(now()->subHours(max(1, $refreshAfterHours)));
    }

    public static function store(string $token, ?int $gaijinId = null): self
    {
        $serverToken = self::query()->first();

        if ($serverToken === null) {
            return self::query()->create([
                'token' => $token,
                'gaijin_id' => $gaijinId,
                'expires_at' => now()->addDay()->timestamp,
                'refreshed_at' => now(),
            ]);
        }

        $serverToken->forceFill([
            'token' => $token,
            'gaijin_id' => $gaijinId,
            'expires_at' => now()->addDay()->timestamp,
            'refreshed_at' => now(),
        ])->save();

        return $serverToken;
    }
}
