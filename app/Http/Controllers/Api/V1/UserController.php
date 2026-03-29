<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApiUserRequest;
use App\Http\Requests\UpdateApiUserRequest;
use App\Http\Resources\PeckUserResource;
use App\Models\PeckUser;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
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

    public function store(StoreApiUserRequest $request)
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
        $peckUser->fill($request->validated());
        $peckUser->save();
        $peckUser->load(['initiatorUser', 'initiatorOfficer']);

        return new PeckUserResource($peckUser);
    }
}
