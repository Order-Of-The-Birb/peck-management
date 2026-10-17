<?php

namespace App\Http\Resources;

use App\Actions\ResolveSquadronRoster;
use App\Models\PeckUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PeckUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PeckUser $peckUser */
        $peckUser = $this->resource;

        $resolver = app(ResolveSquadronRoster::class);
        $roster = $resolver->resolve();

        $data = [
            'gaijin_id' => $peckUser->gaijin_id,
            'discord_id' => $peckUser->discord_id,
            'tz' => $peckUser->tz,
            'status' => $resolver->statusFor((int) $peckUser->gaijin_id, $roster),
            'sqb_part' => $peckUser->sqb_part,
            'joined_at' => $resolver->joinDateFor((int) $peckUser->gaijin_id, $roster),
        ];

        if (array_key_exists('username', $peckUser->getAttributes())) {
            $data['username'] = $peckUser->username;
        }

        return $data;
    }
}
