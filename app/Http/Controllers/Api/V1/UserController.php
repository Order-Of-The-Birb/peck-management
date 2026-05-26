<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApiUserContextRequest;
use App\Http\Requests\StoreApiUserRequest;
use App\Http\Requests\UpdateApiUserContextRequest;
use App\Http\Requests\UpdateApiUserRequest;
use App\Http\Requests\UpsertApiUserLeaveInfoRequest;
use App\Http\Resources\PeckUserContextResource;
use App\Http\Resources\PeckUserResource;
use App\Models\PeckLeaveInfo;
use App\Models\PeckUser;
use App\Models\PeckUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(PeckUser::STATUSES)],
            'tz' => ['nullable', 'integer', 'between:-11,12'],
            'sort_by' => ['nullable', 'string', Rule::in([
                'gaijin_id',
                'username',
                'status',
                'discord_id',
                'tz',
                'joindate',
                'initiator',
                'sqb_part',
            ])],
            'sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $searchTerm = $validated['search'] ?? null;
        $sortBy = $validated['sort_by'] ?? 'gaijin_id';
        $sortDirection = $validated['sort_direction'] ?? 'asc';
        $perPage = $validated['per_page'] ?? 15;
        $page = $validated['page'] ?? 1;

        $users = PeckUser::query()
            ->with(['initiatorUser', 'initiatorOfficer'])
            ->when($searchTerm !== null && $searchTerm !== '', function ($query) use ($searchTerm): void {
                $likeSearchTerm = '%'.$searchTerm.'%';

                $query->where(function ($innerQuery) use ($likeSearchTerm): void {
                    $innerQuery
                        ->where('gaijin_id', 'like', $likeSearchTerm)
                        ->orWhere('username', 'like', $likeSearchTerm)
                        ->orWhere('discord_id', 'like', $likeSearchTerm);
                });
            })
            ->when(array_key_exists('status', $validated) && $validated['status'] !== null, function ($query) use ($validated): void {
                $query->where('status', $validated['status']);
            })
            ->when(array_key_exists('tz', $validated) && $validated['tz'] !== null, function ($query) use ($validated): void {
                $query->where('tz', $validated['tz']);
            })
            ->orderBy($sortBy, $sortDirection)
            ->orderBy('gaijin_id')
            ->forPage($page, $perPage)
            ->get();

        return PeckUserResource::collection($users);
    }

    public function store(StoreApiUserRequest $request): JsonResponse
    {
        $peckUser = PeckUser::query()->create($request->validated());

        $peckUser->load(['initiatorUser', 'initiatorOfficer']);

        return (new PeckUserResource($peckUser))
            ->response()
            ->setStatusCode(201);
    }

    public function show(PeckUser $peckUser): PeckUserResource
    {
        $peckUser->load(['initiatorUser', 'initiatorOfficer']);

        return new PeckUserResource($peckUser);
    }

    public function update(UpdateApiUserRequest $request, PeckUser $peckUser): PeckUserResource
    {
        $validated = $request->validated();
        $previousStatus = $peckUser->status;
        $previousGaijinId = $peckUser->gaijin_id;

        $peckUser->fill($validated);
        $peckUser->save();

        if (array_key_exists('status', $validated) && $previousStatus === 'ex_member' && $peckUser->status !== 'ex_member') {
            PeckLeaveInfo::query()
                ->where('user_id', $previousGaijinId)
                ->delete();
        }

        $peckUser->load(['initiatorUser', 'initiatorOfficer']);

        return new PeckUserResource($peckUser);
    }

    public function showLeaveInfo(PeckUser $peckUser): JsonResponse
    {
        $leaveInfoType = $peckUser->leaveInfo()->value('type');

        return response()->json([
            'status' => 'success',
            'data' => $leaveInfoType,
        ]);
    }

    public function upsertLeaveInfo(UpsertApiUserLeaveInfoRequest $request, PeckUser $peckUser): JsonResponse
    {
        $validated = $request->validated();

        PeckLeaveInfo::query()->updateOrCreate(
            ['user_id' => $peckUser->gaijin_id],
            ['type' => $validated['type']],
        );

        return response()->json([
            'status' => 'success',
            'data' => $validated['type'],
        ]);
    }

    public function showContexts(PeckUser $peckUser): JsonResponse
    {
        $contexts = $peckUser->contexts()
            ->orderBy('context_id')
            ->get();

        return response()->json($this->contextCollectionData($contexts));
    }

    public function storeContext(StoreApiUserContextRequest $request, PeckUser $peckUser): JsonResponse
    {
        $createdContexts = DB::transaction(function () use ($request, $peckUser): Collection {
            return collect($this->storeContextPayloads($request->validated()))
                ->map(function (array $payload) use ($peckUser): PeckUserContext {
                    return PeckUserContext::query()->create([
                        'user_id' => $peckUser->gaijin_id,
                        'context_id' => PeckUserContext::lowestAvailableContextId($peckUser->gaijin_id),
                        ...$payload,
                    ]);
                })
                ->values();
        });

        return response()->json($this->contextCollectionData($createdContexts), 201);
    }

    public function updateContext(UpdateApiUserContextRequest $request, PeckUser $peckUser, int $contextId): JsonResponse
    {
        $context = $this->findUserContextOrFail($peckUser, $contextId);

        $context->fill($this->updateContextPayload($context, $request->validated()));
        $context->save();

        return response()->json((new PeckUserContextResource($context))->resolve());
    }

    public function destroyContext(PeckUser $peckUser, int $contextId): JsonResponse
    {
        abort_unless(request()->user()?->level >= 1, 403);

        $this->findUserContextOrFail($peckUser, $contextId)->delete();

        return response()->json(null, 204);
    }

    /**
     * @param  Collection<int, PeckUserContext>  $contexts
     * @return list<array<string, mixed>>
     */
    private function contextCollectionData(Collection $contexts): array
    {
        return $contexts
            ->map(fn (PeckUserContext $context): array => (new PeckUserContextResource($context))->resolve())
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<array<string, mixed>>
     */
    private function storeContextPayloads(array $validated): array
    {
        if ($validated['type'] === PeckUserContext::TYPE_ONCE_ABSENCE) {
            return [[
                'type' => PeckUserContext::TYPE_ONCE_ABSENCE,
                'from_date' => $validated['from'],
                'to_date' => $validated['to'],
                'weekdays' => null,
                'month_day' => null,
                'comment' => null,
            ]];
        }

        if ($validated['type'] === PeckUserContext::TYPE_MISC) {
            return [[
                'type' => PeckUserContext::TYPE_MISC,
                'from_date' => null,
                'to_date' => null,
                'weekdays' => null,
                'month_day' => null,
                'comment' => $validated['comment'],
            ]];
        }

        $payloads = [];

        if (isset($validated['weekdays']) && is_array($validated['weekdays']) && $validated['weekdays'] !== []) {
            $payloads[] = [
                'type' => PeckUserContext::TYPE_RECURRING_ABSENCE,
                'from_date' => null,
                'to_date' => null,
                'weekdays' => $this->normalizedWeekdays($validated['weekdays']),
                'month_day' => null,
                'comment' => null,
            ];
        }

        if (isset($validated['monthDay'])) {
            $payloads[] = [
                'type' => PeckUserContext::TYPE_RECURRING_ABSENCE,
                'from_date' => null,
                'to_date' => null,
                'weekdays' => null,
                'month_day' => (int) $validated['monthDay'],
                'comment' => null,
            ];
        }

        return $payloads;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function updateContextPayload(PeckUserContext $context, array $validated): array
    {
        $type = $validated['type'] ?? $context->type;

        if ($type === PeckUserContext::TYPE_ONCE_ABSENCE) {
            return [
                'type' => PeckUserContext::TYPE_ONCE_ABSENCE,
                'from_date' => $validated['from'] ?? $context->from_date?->format('Y-m-d'),
                'to_date' => $validated['to'] ?? $context->to_date?->format('Y-m-d'),
                'weekdays' => null,
                'month_day' => null,
                'comment' => null,
            ];
        }

        if ($type === PeckUserContext::TYPE_MISC) {
            return [
                'type' => PeckUserContext::TYPE_MISC,
                'from_date' => null,
                'to_date' => null,
                'weekdays' => null,
                'month_day' => null,
                'comment' => $validated['comment'] ?? $context->comment,
            ];
        }

        $weekdays = $context->weekdays;
        $monthDay = $context->month_day;

        if (array_key_exists('weekdays', $validated)) {
            $weekdays = $this->normalizedWeekdays($validated['weekdays']);
            $monthDay = null;
        }

        if (array_key_exists('monthDay', $validated)) {
            $weekdays = null;
            $monthDay = (int) $validated['monthDay'];
        }

        return [
            'type' => PeckUserContext::TYPE_RECURRING_ABSENCE,
            'from_date' => null,
            'to_date' => null,
            'weekdays' => $weekdays,
            'month_day' => $monthDay,
            'comment' => null,
        ];
    }

    /**
     * @param  array<int, mixed>  $weekdays
     * @return list<int>
     */
    private function normalizedWeekdays(array $weekdays): array
    {
        return collect($weekdays)
            ->map(fn (mixed $weekday): int => (int) $weekday)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function findUserContextOrFail(PeckUser $peckUser, int $contextId): PeckUserContext
    {
        return PeckUserContext::query()
            ->where('user_id', $peckUser->gaijin_id)
            ->where('context_id', $contextId)
            ->firstOrFail();
    }
}
