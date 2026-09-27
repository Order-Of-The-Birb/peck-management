<?php

namespace App\Models;

use Database\Factories\ThunderApiTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThunderApiToken extends Model
{
    /** @use HasFactory<ThunderApiTokenFactory> */
    use HasFactory;

    public const TOKEN_TTL_SECONDS = 86_400;

    protected $table = 'thunderapi_tokens';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'int';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
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
            'user_id' => 'integer',
            'gaijin_id' => 'integer',
            'expires_at' => 'integer',
            'refreshed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ThunderApiTokenFactory
    {
        return ThunderApiTokenFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at <= now()->timestamp;
    }

    public function isRefreshDue(int $refreshAfterHours): bool
    {
        return $this->refreshed_at === null || $this->refreshed_at->lte(now()->subHours(max(1, $refreshAfterHours)));
    }

    public static function storeForUser(User $user, string $token, ?int $gaijinId = null): self
    {
        return self::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'token' => $token,
                'gaijin_id' => $gaijinId,
                'expires_at' => now()->addDay()->timestamp,
                'refreshed_at' => now(),
            ],
        );
    }

    public function touchUsage(): void
    {
        $this->forceFill([
            'expires_at' => now()->addDay()->timestamp,
            'refreshed_at' => now(),
        ])->save();
    }
}
