<?php

use App\Models\ApiKey;
use App\Models\Officer;
use App\Models\PeckLeaveInfo;
use App\Models\PeckUser;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Administration settings')] class extends Component {
    use WithPagination;

    public ?string $selectedManagedUserId = null;

    public string $selectedManagedUserLevel = '0';

    public ?string $apiKeyPrefix = null;

    public bool $hasApiKey = false;

    public bool $showConfirmManagedUserDeletionModal = false;

    public ?int $pendingManagedUserDeletionId = null;

    /** @var array{id:int,name:string,email:string,level:int}|null */
    public ?array $pendingManagedUserDeletionDetails = null;

    public bool $showManagedUserDeletionError = false;

    public string $managedUserDeletionErrorMessage = '';

    public ?string $generatedApiToken = null;

    public bool $showGeneratedApiKeyModal = false;

    public bool $showConfirmApiKeyResetModal = false;

    public bool $showApiKeyGenerationError = false;

    public string $officerSearch = '';

    public bool $showAddOfficerModal = false;

    /** @var array{gaijin_id:?string,rank:?string} */
    public array $newOfficerForm = [
        'gaijin_id' => null,
        'rank' => 'officer',
    ];

    public bool $showRankSwitchModal = false;

    public ?int $pendingRankTargetGaijinId = null;

    public ?string $pendingRankTargetUsername = null;

    public ?string $pendingRankTargetCurrentRank = null;

    public ?string $pendingRankTargetRequestedRank = null;

    public ?int $pendingRankConflictingGaijinId = null;

    public ?string $pendingRankConflictingUsername = null;

    public bool $pendingRankIsCreate = false;

    public string $pendingRankReplacementLabel = '';

    public string $peckUserDeletionSearch = '';

    public bool $showDeletePeckUserModal = false;

    public ?int $pendingDeletePeckUserGaijinId = null;

    /** @var array{gaijin_id:int,username:string,status:string,discord_id:?int,joindate:?string}|null */
    public ?array $pendingDeletePeckUserDetails = null;

    public bool $showDeletePeckUserError = false;

    public string $deletePeckUserErrorMessage = '';

    #region Mounting
    public function mount(): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        $this->hydrateApiKeyState();

        if ($this->canManageUserLevels()) {
            $this->initializeSelectedManagedUser();
        }
    }

    #[Computed]
    public function canManageUserLevels(): bool
    {
        return (int) Auth::user()->level === 2;
    }

    #[Computed]
    public function adminAccess(): bool
    {
        return $this->canManageUserLevels();
    }
    #endregion

    #region User management
    #[Computed]
    public function manageableUsers(): Collection
    {
        if (! $this->canManageUserLevels()) {
            return collect();
        }

        return User::query()
            ->orderByDesc('level')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'level']);
    }

    #[Computed]
    public function selectedManagedUser(): ?User
    {
        if (! $this->canManageUserLevels() || ! filled($this->selectedManagedUserId)) {
            return null;
        }

        return User::query()->find((int) $this->selectedManagedUserId, ['id', 'name', 'email', 'level']);
    }

    protected function initializeSelectedManagedUser(): void
    {
        $firstUser = User::query()
            ->orderByDesc('level')
            ->orderBy('name')
            ->first(['id', 'level']);

        if ($firstUser === null) {
            $this->selectedManagedUserId = null;
            $this->selectedManagedUserLevel = '0';

            return;
        }

        $this->selectedManagedUserId = (string) $firstUser->id;
        $this->selectedManagedUserLevel = (string) $firstUser->level;
    }

    public function updatedSelectedManagedUserId(?string $selectedManagedUserId): void
    {
        if (! $this->canManageUserLevels() || ! filled($selectedManagedUserId)) {
            $this->selectedManagedUserLevel = '0';

            return;
        }

        $selectedManagedUserLevel = User::query()
            ->whereKey((int) $selectedManagedUserId)
            ->value('level');

        $this->selectedManagedUserLevel = (string) ($selectedManagedUserLevel ?? 0);
    }

    public function updateSelectedUserLevel(): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        $validated = $this->validate([
            'selectedManagedUserId' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'selectedManagedUserLevel' => [
                'required',
                'integer',
                Rule::in([0, 1, 2]),
            ],
        ]);

        $user = User::query()->findOrFail((int) $validated['selectedManagedUserId']);
        $newLevel = (int) $validated['selectedManagedUserLevel'];

        $user->forceFill([
            'level' => $newLevel,
        ]);
        $user->save();

        if ($user->is(Auth::user())) {
            Auth::setUser($user);
        }

        $this->selectedManagedUserLevel = (string) $newLevel;
        $this->dispatch('user-level-updated');
    }

    public function requestManagedUserDeletion(): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        if (! filled($this->selectedManagedUserId)) {
            $this->showManagedUserDeletionError = true;
            $this->managedUserDeletionErrorMessage = __('Select a user before deleting.');

            return;
        }

        $targetUser = User::query()->find((int) $this->selectedManagedUserId, ['id', 'name', 'email', 'level']);

        if (! $targetUser instanceof User) {
            $this->showManagedUserDeletionError = true;
            $this->managedUserDeletionErrorMessage = __('The selected user no longer exists.');

            return;
        }

        if ($targetUser->is(Auth::user())) {
            $this->showManagedUserDeletionError = true;
            $this->managedUserDeletionErrorMessage = __('You cannot delete your own account from this screen.');

            return;
        }

        $this->pendingManagedUserDeletionId = $targetUser->id;
        $this->pendingManagedUserDeletionDetails = [
            'id' => $targetUser->id,
            'name' => $targetUser->name,
            'email' => $targetUser->email,
            'level' => (int) $targetUser->level,
        ];
        $this->showConfirmManagedUserDeletionModal = true;
        $this->dismissManagedUserDeletionError();
    }

    public function cancelManagedUserDeletion(): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        $this->showConfirmManagedUserDeletionModal = false;
        $this->pendingManagedUserDeletionId = null;
        $this->pendingManagedUserDeletionDetails = null;
    }

    public function confirmManagedUserDeletion(): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        if (! is_int($this->pendingManagedUserDeletionId)) {
            return;
        }

        try {
            $targetUser = User::query()->find($this->pendingManagedUserDeletionId);

            if (! $targetUser instanceof User) {
                $this->cancelManagedUserDeletion();
                $this->showManagedUserDeletionError = true;
                $this->managedUserDeletionErrorMessage = __('The selected user no longer exists.');
                $this->initializeSelectedManagedUser();

                return;
            }

            if ($targetUser->is(Auth::user())) {
                $this->cancelManagedUserDeletion();
                $this->showManagedUserDeletionError = true;
                $this->managedUserDeletionErrorMessage = __('You cannot delete your own account from this screen.');

                return;
            }

            $targetUser->delete();
        } catch (\Throwable $throwable) {
            report($throwable);

            $this->showManagedUserDeletionError = true;
            $this->managedUserDeletionErrorMessage = __('Failed to delete the selected user. Please try again.');

            return;
        }

        $this->cancelManagedUserDeletion();
        $this->initializeSelectedManagedUser();
        $this->dispatch('managed-user-deleted');
    }

    public function dismissManagedUserDeletionError(): void
    {
        $this->showManagedUserDeletionError = false;
        $this->managedUserDeletionErrorMessage = '';
    }
    #endregion

    #region Officer management
    public function updatingOfficerSearch(): void
    {
        $this->resetPage('officers-page');
    }

    public function updatedOfficerSearch(string $officerSearch): void
    {
        if (! $this->showAddOfficerModal) {
            return;
        }

        $matchingPeckUser = $this->findPeckUserBySearch($officerSearch);

        if ($matchingPeckUser !== null) {
            $this->newOfficerForm['gaijin_id'] = (string) $matchingPeckUser->gaijin_id;
        }
    }

    /**
     * @return list<string>
     */
    public function officerRanks(): array
    {
        return ['', ...Officer::RANKS];
    }

    public function officerRankLabel(?string $rank): string
    {
        return match ($rank) {
            Officer::RANK_OFFICER => 'Officer',
            Officer::RANK_DEPUTY => 'Deputy',
            Officer::RANK_COMMANDER => 'Commander',
            default => 'Retired',
        };
    }

    #[Computed]
    public function officerRecords(): LengthAwarePaginator
    {
        if (! $this->canManageUserLevels()) {
            return Officer::query()->whereRaw('1 = 0')->paginate(15, ['*'], 'officers-page');
        }

        $searchTerm = trim($this->officerSearch);

        return Officer::query()
            ->select('officers.gaijin_id', 'officers.rank')
            ->join('peck_users', 'peck_users.gaijin_id', '=', 'officers.gaijin_id')
            ->with('peckUser')
            ->when($searchTerm !== '', function ($query) use ($searchTerm): void {
                $query->where(function ($innerQuery) use ($searchTerm): void {
                    $innerQuery
                        ->where('peck_users.username', 'like', '%'.$searchTerm.'%')
                        ->orWhere('officers.gaijin_id', 'like', '%'.$searchTerm.'%');
                });
            })
            ->orderBy('peck_users.username')
            ->orderBy('officers.gaijin_id')
            ->paginate(15, ['officers.*'], 'officers-page');
    }

    #[Computed]
    public function officerSelectableUsers(): Collection
    {
        if (! $this->canManageUserLevels()) {
            return collect();
        }

        return PeckUser::query()
            ->orderBy('username')
            ->orderBy('gaijin_id')
            ->get(['gaijin_id', 'username']);
    }

    public function openAddOfficerModal(): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        $this->newOfficerForm = [
            'gaijin_id' => null,
            'rank' => 'officer',
        ];

        $matchingPeckUser = $this->findPeckUserBySearch($this->officerSearch);

        if ($matchingPeckUser !== null) {
            $this->newOfficerForm['gaijin_id'] = (string) $matchingPeckUser->gaijin_id;
        }

        $this->showAddOfficerModal = true;
        $this->resetValidation();
    }

    public function closeAddOfficerModal(): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        $this->showAddOfficerModal = false;
        $this->newOfficerForm = [
            'gaijin_id' => null,
            'rank' => 'officer',
        ];
        $this->resetValidation();
    }

    public function createOfficer(): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        $validated = $this->validate([
            'newOfficerForm.gaijin_id' => [
                'required',
                'integer',
                Rule::exists('peck_users', 'gaijin_id'),
                Rule::unique('officers', 'gaijin_id'),
            ],
            'newOfficerForm.rank' => [
                'nullable',
                'string',
                Rule::in($this->officerRanks()),
            ],
        ]);

        $gaijinId = (int) $validated['newOfficerForm']['gaijin_id'];
        $requestedRank = $this->normalizeOfficerRank($validated['newOfficerForm']['rank'] ?? null);
        $conflictingOfficer = $this->findConflictingOfficer($gaijinId, $requestedRank);

        if ($conflictingOfficer !== null && is_string($requestedRank)) {
            $this->queueOfficerRankSwitch(
                targetGaijinId: $gaijinId,
                targetCurrentRank: null,
                targetRequestedRank: $requestedRank,
                conflictingOfficer: $conflictingOfficer,
                isCreate: true,
            );

            return;
        }

        Officer::query()->create([
            'gaijin_id' => $gaijinId,
            'rank' => $requestedRank,
        ]);

        $this->closeAddOfficerModal();
        $this->resetPage('officers-page');
        $this->dispatch('officer-rank-updated');
    }

    public function attemptOfficerRankUpdate(int $gaijinId, ?string $requestedRankValue): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        $normalizedRankValue = $requestedRankValue ?? '';

        if (! in_array($normalizedRankValue, $this->officerRanks(), true)) {
            $this->addError('officerSearch', __('The selected rank is invalid.'));

            return;
        }

        $officer = Officer::query()->findOrFail($gaijinId);
        $requestedRank = $this->normalizeOfficerRank($normalizedRankValue);

        if ($officer->rank === $requestedRank) {
            return;
        }

        $conflictingOfficer = $this->findConflictingOfficer($officer->gaijin_id, $requestedRank);

        if ($conflictingOfficer !== null && is_string($requestedRank)) {
            $this->queueOfficerRankSwitch(
                targetGaijinId: $officer->gaijin_id,
                targetCurrentRank: $officer->rank,
                targetRequestedRank: $requestedRank,
                conflictingOfficer: $conflictingOfficer,
                isCreate: false,
            );

            return;
        }

        $officer->rank = $requestedRank;
        $officer->save();

        $this->dispatch('officer-rank-updated');
    }

    public function cancelOfficerRankSwitch(): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        $this->clearOfficerRankSwitchState();
    }

    public function confirmOfficerRankSwitch(): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        if (! is_int($this->pendingRankTargetGaijinId) || ! is_string($this->pendingRankTargetRequestedRank)) {
            return;
        }

        $targetGaijinId = $this->pendingRankTargetGaijinId;
        $targetRequestedRank = $this->pendingRankTargetRequestedRank;
        $targetCurrentRank = $this->pendingRankTargetCurrentRank;
        $isCreate = $this->pendingRankIsCreate;

        DB::transaction(function () use ($targetGaijinId, $targetRequestedRank, $targetCurrentRank, $isCreate): void {
            $targetOfficer = Officer::query()->lockForUpdate()->find($targetGaijinId);

            if ($targetOfficer === null && $isCreate) {
                $targetOfficer = Officer::query()->create([
                    'gaijin_id' => $targetGaijinId,
                    'rank' => null,
                ]);
            }

            if (! $targetOfficer instanceof Officer) {
                return;
            }

            $conflictingOfficer = Officer::query()
                ->lockForUpdate()
                ->where('rank', $targetRequestedRank)
                ->where('gaijin_id', '!=', $targetGaijinId)
                ->first();

            $targetOfficer->rank = $targetRequestedRank;
            $targetOfficer->save();

            if ($conflictingOfficer instanceof Officer) {
                $conflictingOfficer->rank = $this->determineSwitchedRankForConflictingOfficer(
                    incomingRank: $targetCurrentRank,
                    conflictingGaijinId: $conflictingOfficer->gaijin_id,
                );
                $conflictingOfficer->save();
            }
        });

        if ($isCreate) {
            $this->closeAddOfficerModal();
        }

        $this->clearOfficerRankSwitchState();
        $this->dispatch('officer-rank-updated');
    }

    protected function queueOfficerRankSwitch(
        int $targetGaijinId,
        ?string $targetCurrentRank,
        string $targetRequestedRank,
        Officer $conflictingOfficer,
        bool $isCreate,
    ): void {
        $targetPeckUser = PeckUser::query()->find($targetGaijinId, ['gaijin_id', 'username']);
        $conflictingPeckUser = PeckUser::query()->find($conflictingOfficer->gaijin_id, ['gaijin_id', 'username']);

        $this->pendingRankTargetGaijinId = $targetGaijinId;
        $this->pendingRankTargetUsername = $targetPeckUser?->username;
        $this->pendingRankTargetCurrentRank = $targetCurrentRank;
        $this->pendingRankTargetRequestedRank = $targetRequestedRank;
        $this->pendingRankConflictingGaijinId = $conflictingOfficer->gaijin_id;
        $this->pendingRankConflictingUsername = $conflictingPeckUser?->username;
        $this->pendingRankIsCreate = $isCreate;
        $this->pendingRankReplacementLabel = $this->officerRankLabel(
            $this->determineSwitchedRankForConflictingOfficer(
                incomingRank: $targetCurrentRank,
                conflictingGaijinId: $conflictingOfficer->gaijin_id,
            ),
        );
        $this->showRankSwitchModal = true;
    }

    protected function clearOfficerRankSwitchState(): void
    {
        $this->showRankSwitchModal = false;
        $this->pendingRankTargetGaijinId = null;
        $this->pendingRankTargetUsername = null;
        $this->pendingRankTargetCurrentRank = null;
        $this->pendingRankTargetRequestedRank = null;
        $this->pendingRankConflictingGaijinId = null;
        $this->pendingRankConflictingUsername = null;
        $this->pendingRankIsCreate = false;
        $this->pendingRankReplacementLabel = '';
    }

    protected function findPeckUserBySearch(string $search): ?PeckUser
    {
        $searchTerm = trim($search);

        if ($searchTerm === '') {
            return null;
        }

        if (ctype_digit($searchTerm)) {
            return PeckUser::query()->find((int) $searchTerm, ['gaijin_id', 'username']);
        }

        return PeckUser::query()
            ->where('username', 'like', '%'.$searchTerm.'%')
            ->orderBy('username')
            ->first(['gaijin_id', 'username']);
    }

    protected function normalizeOfficerRank(?string $rank): ?string
    {
        if ($rank === null || $rank === '') {
            return null;
        }

        return $rank;
    }

    protected function findConflictingOfficer(int $targetGaijinId, ?string $requestedRank): ?Officer
    {
        if (! in_array($requestedRank, [Officer::RANK_DEPUTY, Officer::RANK_COMMANDER], true)) {
            return null;
        }

        return Officer::query()
            ->where('rank', $requestedRank)
            ->where('gaijin_id', '!=', $targetGaijinId)
            ->first();
    }

    protected function determineSwitchedRankForConflictingOfficer(?string $incomingRank, int $conflictingGaijinId): ?string
    {
        if ($incomingRank !== null) {
            return $incomingRank;
        }

        $hasLeaveInfo = PeckLeaveInfo::query()
            ->where('user_id', $conflictingGaijinId)
            ->exists();

        if ($hasLeaveInfo) {
            return null;
        }

        return Officer::RANK_OFFICER;
    }
    #endregion

    #region War Thunder user deletion
    #[Computed]
    public function filteredPeckUsersForDeletion(): Collection
    {
        if (! $this->canManageUserLevels()) {
            return collect();
        }

        $searchTerm = trim($this->peckUserDeletionSearch);

        return PeckUser::query()
            ->when($searchTerm !== '', function ($query) use ($searchTerm): void {
                $query->where('username', 'like', '%'.$searchTerm.'%');
            })
            ->orderBy('username')
            ->orderBy('gaijin_id')
            ->get(['gaijin_id', 'username']);
    }

    public function openDeletePeckUserModal(int $gaijinId): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        $peckUser = PeckUser::query()->find($gaijinId, ['gaijin_id', 'username', 'status', 'discord_id', 'joindate']);

        if (! $peckUser instanceof PeckUser) {
            $this->showDeletePeckUserError = true;
            $this->deletePeckUserErrorMessage = __('The selected user no longer exists.');
            $this->showDeletePeckUserModal = false;
            $this->pendingDeletePeckUserGaijinId = null;
            $this->pendingDeletePeckUserDetails = null;

            return;
        }

        $this->pendingDeletePeckUserGaijinId = $peckUser->gaijin_id;
        $this->pendingDeletePeckUserDetails = [
            'gaijin_id' => $peckUser->gaijin_id,
            'username' => $peckUser->username,
            'status' => $peckUser->status,
            'discord_id' => $peckUser->discord_id,
            'joindate' => $peckUser->joindate?->toDateTimeString(),
        ];
        $this->showDeletePeckUserModal = true;
        $this->dismissDeletePeckUserError();
    }

    public function cancelDeletePeckUser(): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        $this->showDeletePeckUserModal = false;
        $this->pendingDeletePeckUserGaijinId = null;
        $this->pendingDeletePeckUserDetails = null;
    }

    public function deletePeckUser(): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        if (! is_int($this->pendingDeletePeckUserGaijinId)) {
            return;
        }

        try {
            $peckUser = PeckUser::query()->find($this->pendingDeletePeckUserGaijinId);

            if (! $peckUser instanceof PeckUser) {
                $this->cancelDeletePeckUser();
                $this->showDeletePeckUserError = true;
                $this->deletePeckUserErrorMessage = __('The selected user no longer exists.');

                return;
            }

            $peckUser->delete();
        } catch (\Throwable $throwable) {
            report($throwable);

            $this->showDeletePeckUserError = true;
            $this->deletePeckUserErrorMessage = __('Failed to delete the selected user. Please try again.');

            return;
        }

        $this->cancelDeletePeckUser();
        $this->dispatch('peck-user-deleted');
    }

    public function dismissDeletePeckUserError(): void
    {
        $this->showDeletePeckUserError = false;
        $this->deletePeckUserErrorMessage = '';
    }
    #endregion

    #region REST API Token
    public function requestApiKeyGeneration(): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        if ($this->hasApiKey) {
            $this->showConfirmApiKeyResetModal = true;

            return;
        }

        $this->generateApiKey();
    }

    public function confirmApiKeyReset(): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        $this->showConfirmApiKeyResetModal = false;
        $this->generateApiKey();
    }

    public function cancelApiKeyReset(): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        $this->showConfirmApiKeyResetModal = false;
    }

    public function closeGeneratedApiKeyModal(): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        $this->showGeneratedApiKeyModal = false;
        $this->generatedApiToken = null;
    }

    public function copyGeneratedApiKey(): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        if (! is_string($this->generatedApiToken) || $this->generatedApiToken === '') {
            return;
        }

        $this->dispatch('copy-to-clipboard', text: $this->generatedApiToken);
    }

    public function dismissApiKeyGenerationError(): void
    {
        $this->showApiKeyGenerationError = false;
    }

    protected function hydrateApiKeyState(): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            $this->apiKeyPrefix = null;
            $this->hasApiKey = false;

            return;
        }

        $apiKey = ApiKey::query()
            ->where('owner', $user->id)
            ->first(['owner', 'key_prefix']);

        $this->apiKeyPrefix = $apiKey?->key_prefix;
        $this->hasApiKey = $apiKey instanceof ApiKey;
    }

    protected function generateApiKey(): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        try {
            $this->generatedApiToken = ApiKey::issueForOwner($user->id);
            $this->showGeneratedApiKeyModal = true;
            $this->showApiKeyGenerationError = false;
            $this->hydrateApiKeyState();
        } catch (\Throwable $throwable) {
            report($throwable);

            $this->generatedApiToken = null;
            $this->showGeneratedApiKeyModal = false;
            $this->showApiKeyGenerationError = true;
        }
    }
    #endregion
};
?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Administration') }}</flux:heading>

    <x-pages::settings.layout :admin-access="$this->adminAccess" content-width-class="max-w-none">
        <div class="columns-1 gap-6 md:columns-2">
            <div class="mb-6 break-inside-avoid space-y-4 rounded-xl border border-neutral-200 bg-white p-2 shadow-sm dark:border-neutral-700 dark:bg-neutral-900/30 sm:p-3">
                <div class="flex items-center gap-2">
                    <flux:heading size="lg">{{ __('User Access Levels') }}</flux:heading>
                </div>

                <div class="space-y-3">
                    <flux:select wire:model.live="selectedManagedUserId" :label="__('User')">
                        @foreach ($this->manageableUsers as $managedUser)
                            <option value="{{ $managedUser->id }}">
                                {{ $managedUser->name }} ({{ $managedUser->email }})
                            </option>
                        @endforeach
                    </flux:select>

                    @if ($this->selectedManagedUser)
                        <div class="rounded-lg border border-neutral-200 p-3 dark:border-neutral-700">
                            <flux:heading size="sm">{{ $this->selectedManagedUser->name }}</flux:heading>
                            <flux:text class="break-all text-xs">{{ $this->selectedManagedUser->email }}</flux:text>
                        </div>
                    @endif

                    <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto_auto] md:items-end">
                        <flux:select wire:model="selectedManagedUserLevel" :label="__('Access level')">
                            <option value="0">{{ __('0 - Read (default)') }}</option>
                            <option value="1">{{ __('1 - Read+Write') }}</option>
                            <option value="2">{{ __('2 - Supervisor') }}</option>
                        </flux:select>

                        <flux:button type="button" variant="primary" wire:click="updateSelectedUserLevel" :disabled="! filled($selectedManagedUserId)" class="h-10 px-3 text-sm">
                            {{ __('Save Level') }}
                        </flux:button>

                        <flux:button type="button" variant="danger" wire:click="requestManagedUserDeletion" :disabled="! filled($selectedManagedUserId)" class="h-10 px-3 text-sm">
                            {{ __('Delete user') }}
                        </flux:button>
                    </div>
                </div>

                <x-action-message class="me-3" on="user-level-updated">
                    {{ __('Saved.') }}
                </x-action-message>

                <x-action-message class="me-3" on="managed-user-deleted">
                    {{ __('User deleted.') }}
                </x-action-message>

                @if ($showManagedUserDeletionError)
                    <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/40 dark:text-red-100">
                        {{ $managedUserDeletionErrorMessage }}
                    </div>
                @endif
            </div>

        <div class="mb-6 break-inside-avoid space-y-4 rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900/30">
            <div class="flex items-center gap-2">
                <flux:heading size="lg">{{ __('Officer Ranks') }}</flux:heading>
            </div>

            <div class="space-y-4">
                <flux:input wire:model.live.debounce.300ms="officerSearch" :label="__('Search by username or Gaijin ID')" />

                <div class="overflow-x-auto">
                    <table class="w-full min-w-full table-fixed divide-y divide-neutral-200 text-left text-sm dark:divide-neutral-700">
                        <thead class="bg-neutral-100/70 text-xs uppercase tracking-wide text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                            <tr>
                                <th class="px-3 py-2">{{ __('Gaijin ID') }}</th>
                                <th class="px-3 py-2">{{ __('Username') }}</th>
                                <th class="px-3 py-2">{{ __('Rank') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            @forelse ($this->officerRecords as $officerRecord)
                                <tr wire:key="officer-row-{{ $officerRecord->gaijin_id }}">
                                    <td class="px-3 py-2 font-medium">{{ $officerRecord->gaijin_id }}</td>
                                    <td class="px-3 py-2">{{ $officerRecord->peckUser?->username ?? '—' }}</td>
                                    <td class="px-3 py-2">
                                        <select
                                            wire:change="attemptOfficerRankUpdate({{ $officerRecord->gaijin_id }}, $event.target.value)"
                                            class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100"
                                        >
                                            <option value="" @selected($officerRecord->rank === null)>{{ __('Retired') }}</option>
                                            <option value="officer" @selected($officerRecord->rank === 'officer')>{{ __('Officer') }}</option>
                                            <option value="deputy" @selected($officerRecord->rank === 'deputy')>{{ __('Deputy') }}</option>
                                            <option value="commander" @selected($officerRecord->rank === 'commander')>{{ __('Commander') }}</option>
                                        </select>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-3 py-4 text-center text-neutral-500 dark:text-neutral-400">
                                        {{ __('No officers found.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div>
                    {{ $this->officerRecords->links() }}
                </div>

                <flux:button type="button" variant="ghost" wire:click="openAddOfficerModal" class="w-full justify-center rounded-lg border border-dashed border-neutral-300 px-4 py-2 text-sm font-medium dark:border-neutral-700">
                    {{ __('+ Add') }}
                </flux:button>

                <x-action-message class="me-3" on="officer-rank-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </div>

        <div class="mb-6 break-inside-avoid space-y-4 rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900/30">
            <div class="flex items-center gap-2">
                <flux:heading size="lg">{{ __('Delete user') }}</flux:heading>
            </div>

            <div class="space-y-3">
                <flux:input wire:model.live.debounce.300ms="peckUserDeletionSearch" :label="__('Search by username')" />

                <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
                    <div class="max-h-56 overflow-y-auto divide-y divide-neutral-100 dark:divide-neutral-800">
                        @forelse ($this->filteredPeckUsersForDeletion as $filteredPeckUser)
                            <div wire:key="delete-peck-user-row-{{ $filteredPeckUser->gaijin_id }}" class="flex items-center justify-between gap-3 px-3 py-2">
                                <flux:text>{{ $filteredPeckUser->username }}</flux:text>

                                <button
                                    type="button"
                                    wire:click="openDeletePeckUserModal({{ $filteredPeckUser->gaijin_id }})"
                                    class="rounded-md p-2 text-neutral-500 transition hover:bg-red-50 hover:text-red-600 dark:text-neutral-400 dark:hover:bg-red-900/20 dark:hover:text-red-300"
                                    aria-label="{{ __('Delete :username', ['username' => $filteredPeckUser->username]) }}"
                                >
                                    <flux:icon.trash class="size-4" />
                                </button>
                            </div>
                        @empty
                            <div class="px-3 py-4 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                {{ __('No users match your search.') }}
                            </div>
                        @endforelse
                    </div>
                </div>

                <x-action-message class="me-3" on="peck-user-deleted">
                    {{ __('User deleted.') }}
                </x-action-message>

                @if ($showDeletePeckUserError)
                    <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/40 dark:text-red-100">
                        {{ $deletePeckUserErrorMessage }}
                    </div>
                @endif
            </div>
        </div>

        <div class="mb-6 break-inside-avoid space-y-4 rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900/30">
            <div class="flex items-center gap-2">
                <flux:heading size="lg">{{ __('API Key') }}</flux:heading>
            </div>
            <div class="space-y-3">
                <div class="rounded-lg border border-neutral-200 p-3 dark:border-neutral-700">
                    @if ($hasApiKey)
                        <flux:text>
                            {{ __('Current key identifier: :prefix', ['prefix' => $apiKeyPrefix ?? __('legacy')]) }}
                        </flux:text>
                    @else
                        <flux:text>{{ __('No API key has been generated yet.') }}</flux:text>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <flux:button type="button" variant="primary" wire:click="requestApiKeyGeneration" wire:loading.attr="disabled" wire:target="requestApiKeyGeneration,confirmApiKeyReset">
                        {{ __('Generate new key') }}
                    </flux:button>
                </div>
            </div>
        </div>
        </div>

        <flux:modal wire:model="showAddOfficerModal" class="max-w-2xl">
            <form wire:submit="createOfficer" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Add Officer Entry') }}</flux:heading>
                    <flux:subheading>
                        {{ __('Create a new officer record from an existing user.') }}
                    </flux:subheading>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:select wire:model="newOfficerForm.gaijin_id" :label="__('User')" required>
                        <option value="">{{ __('Select a user') }}</option>
                        @foreach ($this->officerSelectableUsers as $officerSelectableUser)
                            <option value="{{ $officerSelectableUser->gaijin_id }}">
                                {{ $officerSelectableUser->username }} ({{ $officerSelectableUser->gaijin_id }})
                            </option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="newOfficerForm.rank" :label="__('Rank')">
                        <option value="">{{ __('Retired') }}</option>
                        <option value="officer">{{ __('Officer') }}</option>
                        <option value="deputy">{{ __('Deputy') }}</option>
                        <option value="commander">{{ __('Commander') }}</option>
                    </flux:select>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" wire:click="closeAddOfficerModal">
                            {{ __('Cancel') }}
                        </flux:button>
                    </flux:modal.close>

                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createOfficer">
                        {{ __('Save') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal wire:model="showRankSwitchModal" class="max-w-xl">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Switch rank assignment?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('Only one deputy and one commander are allowed at a time.') }}
                    </flux:subheading>
                </div>

                <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 text-sm dark:border-neutral-700 dark:bg-neutral-900/40">
                    <flux:text>
                        {{ __('Assign :requestedRank to :targetUser (:targetGaijinId).', [
                            'requestedRank' => $this->officerRankLabel($pendingRankTargetRequestedRank),
                            'targetUser' => $pendingRankTargetUsername ?? __('Unknown user'),
                            'targetGaijinId' => $pendingRankTargetGaijinId ?? '—',
                        ]) }}
                    </flux:text>
                    <flux:text class="mt-2">
                        {{ __('Then :conflictingUser (:conflictingGaijinId) will become :replacementRank.', [
                            'conflictingUser' => $pendingRankConflictingUsername ?? __('Unknown user'),
                            'conflictingGaijinId' => $pendingRankConflictingGaijinId ?? '—',
                            'replacementRank' => $pendingRankReplacementLabel,
                        ]) }}
                    </flux:text>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" wire:click="cancelOfficerRankSwitch">
                            {{ __('Cancel') }}
                        </flux:button>
                    </flux:modal.close>

                    <flux:button type="button" variant="primary" wire:click="confirmOfficerRankSwitch" wire:loading.attr="disabled" wire:target="confirmOfficerRankSwitch">
                        {{ __('Switch Rank') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        <flux:modal wire:model="showConfirmManagedUserDeletionModal" class="max-w-xl">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Are you sure you want to delete this user?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('This action permanently deletes the selected authentication user account.') }}
                    </flux:subheading>
                </div>

                <div class="space-y-1 rounded-lg border border-neutral-200 bg-neutral-50 p-4 text-sm dark:border-neutral-700 dark:bg-neutral-900/40">
                    <flux:text>{{ __('Name: :name', ['name' => $pendingManagedUserDeletionDetails['name'] ?? '—']) }}</flux:text>
                    <flux:text>{{ __('Email: :email', ['email' => $pendingManagedUserDeletionDetails['email'] ?? '—']) }}</flux:text>
                    <flux:text>{{ __('User ID: :id', ['id' => $pendingManagedUserDeletionDetails['id'] ?? '—']) }}</flux:text>
                    <flux:text>{{ __('Access level: :level', ['level' => $pendingManagedUserDeletionDetails['level'] ?? '—']) }}</flux:text>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" wire:click="cancelManagedUserDeletion">
                            {{ __('Cancel') }}
                        </flux:button>
                    </flux:modal.close>

                    <flux:button type="button" variant="danger" wire:click="confirmManagedUserDeletion" wire:loading.attr="disabled" wire:target="confirmManagedUserDeletion">
                        {{ __('Delete user') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        <flux:modal wire:model="showDeletePeckUserModal" class="max-w-xl">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Are you sure you want to delete this user?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('This action permanently deletes the selected War Thunder user entry.') }}
                    </flux:subheading>
                </div>

                <div class="space-y-1 rounded-lg border border-neutral-200 bg-neutral-50 p-4 text-sm dark:border-neutral-700 dark:bg-neutral-900/40">
                    <flux:text>{{ __('Username: :username', ['username' => $pendingDeletePeckUserDetails['username'] ?? '—']) }}</flux:text>
                    <flux:text>{{ __('Gaijin ID: :gaijinId', ['gaijinId' => $pendingDeletePeckUserDetails['gaijin_id'] ?? '—']) }}</flux:text>
                    <flux:text>{{ __('Status: :status', ['status' => $pendingDeletePeckUserDetails['status'] ?? '—']) }}</flux:text>
                    <flux:text>{{ __('Discord ID: :discordId', ['discordId' => $pendingDeletePeckUserDetails['discord_id'] ?? '—']) }}</flux:text>
                    <flux:text>{{ __('Join date: :joinDate', ['joinDate' => $pendingDeletePeckUserDetails['joindate'] ?? '—']) }}</flux:text>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" wire:click="cancelDeletePeckUser">
                            {{ __('Cancel') }}
                        </flux:button>
                    </flux:modal.close>

                    <flux:button type="button" variant="danger" wire:click="deletePeckUser" wire:loading.attr="disabled" wire:target="deletePeckUser">
                        {{ __('Delete user') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        <flux:modal wire:model="showConfirmApiKeyResetModal" class="max-w-xl">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Reset API key?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('Are you absolutely sure you want to generate a new API key?') }}
                    </flux:subheading>
                </div>

                <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-100">
                    {{ __('Any application using the previous API key will stop working until it is updated with the new key.') }}
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" wire:click="cancelApiKeyReset">
                            {{ __('Cancel') }}
                        </flux:button>
                    </flux:modal.close>

                    <flux:button type="button" variant="primary" wire:click="confirmApiKeyReset" wire:loading.attr="disabled" wire:target="confirmApiKeyReset">
                        {{ __('Generate and reset') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        <flux:modal wire:model="showGeneratedApiKeyModal" class="max-w-xl">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('New API key generated') }}</flux:heading>
                    <flux:subheading>
                        {{ __('This key is shown only once. Copy it now and store it securely.') }}
                    </flux:subheading>
                </div>

                <input
                    type="text"
                    readonly
                    value="{{ $generatedApiToken ?? '' }}"
                    class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 font-mono text-sm text-neutral-900 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100"
                >

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" wire:click="closeGeneratedApiKeyModal">
                            {{ __('Close') }}
                        </flux:button>
                    </flux:modal.close>

                    <flux:button type="button" variant="primary" wire:click="copyGeneratedApiKey" :disabled="! filled($generatedApiToken)">
                        {{ __('Copy key') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    </x-pages::settings.layout>

    @if ($showApiKeyGenerationError)
        <div
            x-data
            x-init="setTimeout(() => $wire.dismissApiKeyGenerationError(), 3500)"
            class="fixed bottom-4 right-4 z-50 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 shadow-lg dark:border-red-800 dark:bg-red-900/40 dark:text-red-100"
            role="status"
        >
            {{ __('Generating API key failed.') }}
        </div>
    @endif

    <script>
        window.addEventListener('copy-to-clipboard', e => {
            navigator.clipboard.writeText(e.detail.text);
        });
    </script>
</section>
