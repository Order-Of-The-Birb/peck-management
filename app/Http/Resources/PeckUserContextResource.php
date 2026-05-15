<?php

namespace App\Http\Resources;

use App\Models\PeckUserContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PeckUserContextResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PeckUserContext $context */
        $context = $this->resource;

        $data = [
            'id' => $context->context_id,
            'type' => $context->type,
        ];

        if ($context->type === PeckUserContext::TYPE_ONCE_ABSENCE) {
            $data['from'] = $context->from_date?->format('Y-m-d');
            $data['to'] = $context->to_date?->format('Y-m-d');
        }

        if ($context->type === PeckUserContext::TYPE_RECURRING_ABSENCE && is_array($context->weekdays)) {
            $data['weekdays'] = collect($context->weekdays)
                ->map(fn (mixed $weekday): int => (int) $weekday)
                ->values()
                ->all();
        }

        if ($context->type === PeckUserContext::TYPE_RECURRING_ABSENCE && $context->month_day !== null) {
            $data['monthDay'] = $context->month_day;
        }

        if ($context->type === PeckUserContext::TYPE_MISC) {
            $data['comment'] = $context->comment;
        }

        return $data;
    }
}
