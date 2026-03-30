<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApiUserRequest;
use App\Http\Requests\UpdateApiUserRequest;
use App\Http\Requests\UpsertApiUserLeaveInfoRequest;
use App\Http\Resources\PeckUserResource;
use App\Models\PeckLeaveInfo;
use App\Models\PeckUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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
}
