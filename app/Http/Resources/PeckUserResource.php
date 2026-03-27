<?php

namespace App\Http\Resources;

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

        return [
            'gaijin_id' => $peckUser->gaijin_id,
            'username' => $peckUser->username,
            'discord_id' => $peckUser->discord_id,
            'tz' => $peckUser->tz,
            'status' => $peckUser->status,
            'joindate' => $peckUser->joindate?->format('Y-m-d'),
            'initiator' => $peckUser->initiator,
            'initiator_username' => $peckUser->initiatorUser?->username,
            'initiator_rank' => $peckUser->initiatorOfficer?->rank,
        ];
    }
}
