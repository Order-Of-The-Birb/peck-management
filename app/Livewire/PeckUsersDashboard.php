<?php

namespace App\Livewire;

use App\Models\Officer;
use App\Models\PeckAlt;
use App\Models\PeckLeaveInfo;
use App\Models\PeckUser;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class PeckUsersDashboard extends Component
{
    use WithPagination;

    public string $search = '';

    public string $sortBy = 'gaijin_id';

    public string $sortDirection = 'asc';

    public bool $showEditModal = false;

    public bool $showCreateUserModal = false;

    public ?int $selectedGaijinId = null;

    public bool $showFilterModal = false;

    public string $section = 'users';

    public string $altSearch = '';

    public bool $showMasterEditModal = false;

    public bool $showAddSlaveModal = false;

    public ?int $editingMasterGaijinId = null;

    public ?string $altFormMasterGaijinId = null;

    /**
     * @var list<int>
     */
    public array $altFormSlaveGaijinIds = [];

    public ?string $newSlaveGaijinId = null;

    public bool $showAltSaveError = false;

    public string $altSaveErrorMessage = '';

    public bool $showLeaveInfoModal = false;

    public ?int $selectedLeaveInfoGaijinId = null;

    public ?string $selectedLeaveInfoUsername = null;

    public bool $leaveInfoModalFromStatusChange = false;

    /**
     * @var array{gaijin_id:string,username:string,status:string,discord_id:string,joindate:string,current_leave_info:string}
     */
    public array $selectedLeaveInfoUserDetails = [
        'gaijin_id' => '',
        'username' => '',
        'status' => '',
        'discord_id' => '',
        'joindate' => '',
        'current_leave_info' => '',
    ];

    /**
     * @var array{type:string}
     */
    public array $leaveInfoForm = [
        'type' => PeckLeaveInfo::TYPE_LEFT,
    ];

    /**
     * @var array{status:?string,tz:?int,joined_after:?string,joined_before:?string}
     */
    public array $filters = [
        'status' => null,
        'tz' => null,
        'joined_after' => null,
        'joined_before' => null,
    ];

    /**
     * @var array{status:?string,tz:?int,joined_after:?string,joined_before:?string}
     */
    public array $filterForm = [
        'status' => null,
        'tz' => null,
        'joined_after' => null,
        'joined_before' => null,
    ];

    /**
     * @var array{gaijin_id:?string,username:string,discord_id:?string,tz:?string,status:string,joindate:?string,initiator:?string}
     */
    public array $form = [
        'gaijin_id' => null,
        'username' => '',
        'discord_id' => null,
        'tz' => '0',
        'status' => 'unverified',
        'joindate' => null,
        'initiator' => null,
    ];

    /**
     * @var array{gaijin_id:?string,username:string,discord_id:?string,tz:?string,status:string,joindate:?string,initiator:?string}
     */
    public array $newUserForm = [
        'gaijin_id' => null,
        'username' => '',
        'discord_id' => null,
        'tz' => '0',
        'status' => 'unverified',
        'joindate' => null,
        'initiator' => null,
    ];

    /**
     * @var array<string, array<string, bool|string>>
     */
    protected array $queryString = [
        'search' => ['except' => ''],
        'sortBy' => ['except' => 'gaijin_id'],
        'sortDirection' => ['except' => 'asc'],
        'altSearch' => ['except' => ''],
    ];

    public function mount(string $section = 'users'): void
    {
        if (in_array($section, ['users', 'leave_info', 'alts'], true)) {
            $this->section = $section;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingAltSearch(): void
    {
        $this->resetPage('alt-masters-page');
    }

    /**
     * @return array{status:?string,tz:?int,joined_after:?string,joined_before:?string}
     */
    protected function blankFilterForm(): array
    {
        return [
            'status' => null,
            'tz' => null,
            'joined_before' => null,
            'joined_after' => null,
        ];
    }

    public function openFilterModal(): void
    {
        $this->filterForm = $this->filters;
        $this->showFilterModal = true;
        $this->resetValidation();
    }

    public function closeFilterModal(): void
    {
        $this->filterForm = $this->filters;
        $this->showFilterModal = false;
        $this->resetValidation();
    }

    public function applyFilters(): void
    {
        $validated = $this->validate($this->filterRules());
        $validatedFilters = $validated['filterForm'];
        $validatedFilters['tz'] = $this->nullableInteger($validatedFilters['tz']);

        $this->filters = $validatedFilters;
        $this->showFilterModal = false;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $blankFilters = $this->blankFilterForm();

        $this->filters = $blankFilters;
        $this->filterForm = $blankFilters;
        $this->showFilterModal = false;
        $this->resetValidation();
        $this->resetPage();
    }

    public function activeFilterCount(): int
    {
        return collect($this->filters)
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->count();
    }

    /**
     * @return list<string>
     */
    public function filterableStatuses(): array
    {
        return PeckUser::STATUSES;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function filterRules(): array
    {
        return [
            'filterForm.status' => [
                'nullable',
                'string',
                Rule::in($this->filterableStatuses()),
            ],
            'filterForm.tz' => [
                'nullable',
                'integer',
                'between:-11,12',
            ],
            'filterForm.joined_after' => [
                'nullable',
                'date_format:Y-m-d',
                'before_or_equal:filterForm.joined_before',
            ],
            'filterForm.joined_before' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:filterForm.joined_after',
            ],
        ];
    }

    public function sort(string $column): void
    {
        if (! $this->isSortableColumn($column)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function isSortedBy(string $column): bool
    {
        return $this->sortBy === $column;
    }

    /**
     * @return list<string>
     */
    public function sortableColumns(): array
    {
        return [
            'gaijin_id',
            'username',
            'status',
            'discord_id',
            'tz',
            'joindate',
            'initiator',
        ];
    }

    protected function isSortableColumn(string $column): bool
    {
        return in_array($column, $this->sortableColumns(), true);
    }

    public function openCreateUserModal(): void
    {
        $this->ensureCanEdit();

        $this->newUserForm = $this->blankUserForm();
        $this->showCreateUserModal = true;
        $this->resetValidation();
    }

    public function closeCreateUserModal(): void
    {
        $this->ensureCanEdit();

        $this->showCreateUserModal = false;
        $this->newUserForm = $this->blankUserForm();
        $this->resetValidation();
    }

    public function createUser(): void
    {
        $this->ensureCanEdit();

        $validated = $this->validate($this->createUserRules());

        PeckUser::query()->create([
            'gaijin_id' => (int) $validated['newUserForm']['gaijin_id'],
            'username' => $validated['newUserForm']['username'],
            'discord_id' => $this->nullableInteger($validated['newUserForm']['discord_id']),
            'tz' => $this->nullableInteger($validated['newUserForm']['tz']),
            'status' => $validated['newUserForm']['status'],
            'joindate' => $validated['newUserForm']['joindate'],
            'initiator' => $this->nullableInteger($validated['newUserForm']['initiator']),
        ]);

        $this->dispatch('peck-user-created');
        $this->closeCreateUserModal();
        $this->clearSelection();
        $this->resetPage();
    }

    protected function ensureCanEdit(): void
    {
        abort_unless($this->canEdit(), 403);
    }

    /**
     * @return list<string>
     */
    public function editableStatuses(): array
    {
        return PeckUser::STATUSES;
    }

    /**
     * @return list<string>
     */
    public function leaveInfoTypes(): array
    {
        return PeckLeaveInfo::TYPES;
    }

    public function canEdit(): bool
    {
        return auth()->check() && (int) auth()->user()->level >= 1;
    }

    public function isUsersSection(): bool
    {
        return $this->section === 'users';
    }

    public function isLeaveInfoSection(): bool
    {
        return $this->section === 'leave_info';
    }

    public function isAltsSection(): bool
    {
        return $this->section === 'alts';
    }

    public function availableMasterUsers(): Collection
    {
        $selectedMasterGaijinId = $this->nullableInteger($this->altFormMasterGaijinId);
        $assignedSlaveGaijinIds = PeckAlt::query()
            ->pluck('alt_id')
            ->map(fn (mixed $altId): int => (int) $altId)
            ->values()
            ->all();

        return PeckUser::query()
            ->when($assignedSlaveGaijinIds !== [], function (Builder $query) use ($assignedSlaveGaijinIds, $selectedMasterGaijinId): void {
                $query->where(function (Builder $innerQuery) use ($assignedSlaveGaijinIds, $selectedMasterGaijinId): void {
                    $innerQuery->whereNotIn('gaijin_id', $assignedSlaveGaijinIds);

                    if ($selectedMasterGaijinId !== null) {
                        $innerQuery->orWhere('gaijin_id', $selectedMasterGaijinId);
                    }
                });
            })
            ->orderBy('username')
            ->orderBy('gaijin_id')
            ->get(['gaijin_id', 'username']);
    }

    public function availableSlaveUsers(): Collection
    {
        $selectedMasterGaijinId = $this->nullableInteger($this->altFormMasterGaijinId);
        $currentSlaveGaijinIds = collect($this->altFormSlaveGaijinIds)
            ->map(fn (mixed $slaveGaijinId): int => (int) $slaveGaijinId)
            ->values()
            ->all();

        $assignedSlaveQuery = PeckAlt::query()->select('alt_id');
        $existingMasterQuery = PeckAlt::query()->select('owner_id')->distinct();

        if ($this->editingMasterGaijinId !== null) {
            $assignedSlaveQuery->where('owner_id', '!=', $this->editingMasterGaijinId);
            $existingMasterQuery->where('owner_id', '!=', $this->editingMasterGaijinId);
        }

        $assignedSlaveGaijinIds = $assignedSlaveQuery
            ->pluck('alt_id')
            ->map(fn (mixed $altId): int => (int) $altId)
            ->values()
            ->all();

        $existingMasterGaijinIds = $existingMasterQuery
            ->pluck('owner_id')
            ->map(fn (mixed $ownerId): int => (int) $ownerId)
            ->values()
            ->all();

        $excludedGaijinIds = collect([
            $selectedMasterGaijinId,
            ...$currentSlaveGaijinIds,
            ...$assignedSlaveGaijinIds,
            ...$existingMasterGaijinIds,
        ])
            ->filter(fn (mixed $gaijinId): bool => $gaijinId !== null)
            ->map(fn (mixed $gaijinId): int => (int) $gaijinId)
            ->unique()
            ->values()
            ->all();

        return PeckUser::query()
            ->when($excludedGaijinIds !== [], function (Builder $query) use ($excludedGaijinIds): void {
                $query->whereNotIn('gaijin_id', $excludedGaijinIds);
            })
            ->orderBy('username')
            ->orderBy('gaijin_id')
            ->get(['gaijin_id', 'username']);
    }

    public function openCreateMasterModal(): void
    {
        $this->ensureCanEdit();

        $this->editingMasterGaijinId = null;
        $this->altFormMasterGaijinId = null;
        $this->altFormSlaveGaijinIds = [];
        $this->newSlaveGaijinId = null;
        $this->showAddSlaveModal = false;
        $this->showMasterEditModal = true;
        $this->dismissAltSaveError();
        $this->resetValidation();
    }

    public function openEditMasterModal(int $masterGaijinId): void
    {
        $this->ensureCanEdit();

        $this->editingMasterGaijinId = $masterGaijinId;
        $this->altFormMasterGaijinId = (string) $masterGaijinId;
        $this->altFormSlaveGaijinIds = PeckAlt::query()
            ->where('owner_id', $masterGaijinId)
            ->orderBy('alt_id')
            ->pluck('alt_id')
            ->map(fn (mixed $altId): int => (int) $altId)
            ->values()
            ->all();
        $this->newSlaveGaijinId = null;
        $this->showAddSlaveModal = false;
        $this->showMasterEditModal = true;
        $this->dismissAltSaveError();
        $this->resetValidation();
    }

    public function closeMasterEditModal(): void
    {
        $this->ensureCanEdit();

        $this->showMasterEditModal = false;
        $this->showAddSlaveModal = false;
        $this->editingMasterGaijinId = null;
        $this->altFormMasterGaijinId = null;
        $this->altFormSlaveGaijinIds = [];
        $this->newSlaveGaijinId = null;
        $this->dismissAltSaveError();
        $this->resetValidation();
    }

    public function updatedAltFormMasterGaijinId(?string $altFormMasterGaijinId): void
    {
        $selectedMasterGaijinId = $this->nullableInteger($altFormMasterGaijinId);

        if ($selectedMasterGaijinId === null) {
            $this->editingMasterGaijinId = null;
            $this->altFormSlaveGaijinIds = [];

            return;
        }

        $existingSlaveGaijinIds = PeckAlt::query()
            ->where('owner_id', $selectedMasterGaijinId)
            ->orderBy('alt_id')
            ->pluck('alt_id')
            ->map(fn (mixed $altId): int => (int) $altId)
            ->values()
            ->all();

        if ($existingSlaveGaijinIds !== []) {
            $this->editingMasterGaijinId = $selectedMasterGaijinId;
            $this->altFormSlaveGaijinIds = $existingSlaveGaijinIds;

            return;
        }

        $this->editingMasterGaijinId = null;
        $this->altFormSlaveGaijinIds = [];
    }

    public function openAddSlaveModal(): void
    {
        $this->ensureCanEdit();

        if (! filled($this->altFormMasterGaijinId)) {
            $this->addError('altFormMasterGaijinId', __('Select a master account first.'));

            return;
        }

        $this->newSlaveGaijinId = null;
        $this->showAddSlaveModal = true;
        $this->resetValidation(['newSlaveGaijinId']);
    }

    public function closeAddSlaveModal(): void
    {
        $this->ensureCanEdit();

        $this->newSlaveGaijinId = null;
        $this->showAddSlaveModal = false;
        $this->resetValidation(['newSlaveGaijinId']);
    }

    public function addSlaveToMaster(): void
    {
        $this->ensureCanEdit();

        $validated = $this->validate([
            'newSlaveGaijinId' => [
                'required',
                'integer',
                Rule::exists('peck_users', 'gaijin_id'),
            ],
        ]);

        $newSlaveGaijinId = (int) $validated['newSlaveGaijinId'];

        if (! $this->availableSlaveUsers()->contains('gaijin_id', $newSlaveGaijinId)) {
            $this->addError('newSlaveGaijinId', __('The selected slave account is not available.'));

            return;
        }

        if (in_array($newSlaveGaijinId, $this->altFormSlaveGaijinIds, true)) {
            $this->closeAddSlaveModal();

            return;
        }

        $this->altFormSlaveGaijinIds[] = $newSlaveGaijinId;
        $this->altFormSlaveGaijinIds = collect($this->altFormSlaveGaijinIds)
            ->map(fn (mixed $slaveGaijinId): int => (int) $slaveGaijinId)
            ->unique()
            ->values()
            ->all();

        $this->closeAddSlaveModal();
    }

    public function removeSlaveFromMaster(int $slaveGaijinId): void
    {
        $this->ensureCanEdit();

        $this->altFormSlaveGaijinIds = collect($this->altFormSlaveGaijinIds)
            ->map(fn (mixed $candidateGaijinId): int => (int) $candidateGaijinId)
            ->reject(fn (int $candidateGaijinId): bool => $candidateGaijinId === $slaveGaijinId)
            ->values()
            ->all();
    }

    public function setMasterFromSlave(int $slaveGaijinId): void
    {
        $this->ensureCanEdit();

        if (! in_array($slaveGaijinId, $this->altFormSlaveGaijinIds, true)) {
            return;
        }

        $currentMasterGaijinId = $this->nullableInteger($this->altFormMasterGaijinId);

        if ($currentMasterGaijinId === null) {
            $this->addError('altFormMasterGaijinId', __('Select a master account first.'));

            return;
        }

        $remainingSlaveGaijinIds = collect($this->altFormSlaveGaijinIds)
            ->map(fn (mixed $candidateGaijinId): int => (int) $candidateGaijinId)
            ->reject(fn (int $candidateGaijinId): bool => $candidateGaijinId === $slaveGaijinId)
            ->values()
            ->all();

        $this->altFormMasterGaijinId = (string) $slaveGaijinId;
        $this->altFormSlaveGaijinIds = collect([
            ...$remainingSlaveGaijinIds,
            $currentMasterGaijinId,
        ])
            ->unique()
            ->values()
            ->all();
    }

    public function saveMasterAssignment(): void
    {
        $this->ensureCanEdit();
        $this->dismissAltSaveError();

        $validated = $this->validate([
            'altFormMasterGaijinId' => [
                'required',
                'integer',
                Rule::exists('peck_users', 'gaijin_id'),
            ],
            'altFormSlaveGaijinIds' => [
                'required',
                'array',
                'min:1',
            ],
            'altFormSlaveGaijinIds.*' => [
                'required',
                'integer',
                Rule::exists('peck_users', 'gaijin_id'),
                'distinct',
            ],
        ]);

        $masterGaijinId = (int) $validated['altFormMasterGaijinId'];
        $slaveGaijinIds = collect($validated['altFormSlaveGaijinIds'])
            ->map(fn (mixed $slaveGaijinId): int => (int) $slaveGaijinId)
            ->unique()
            ->values()
            ->all();

        if (in_array($masterGaijinId, $slaveGaijinIds, true)) {
            $this->addError('altFormSlaveGaijinIds', __('A master account cannot also be a slave account.'));
            $this->showAltSaveErrorToast(__('A master account cannot also be a slave account.'));

            return;
        }

        if ($this->editingMasterGaijinId === null && PeckAlt::query()->where('owner_id', $masterGaijinId)->exists()) {
            $this->addError('altFormMasterGaijinId', __('The selected master account is already declared.'));
            $this->showAltSaveErrorToast(__('The selected master account is already declared.'));

            return;
        }

        $conflictingSlaveAssignments = PeckAlt::query()
            ->whereIn('alt_id', $slaveGaijinIds);

        if ($this->editingMasterGaijinId !== null) {
            $conflictingSlaveAssignments->where('owner_id', '!=', $this->editingMasterGaijinId);
        }

        if ($conflictingSlaveAssignments->exists()) {
            $this->addError('altFormSlaveGaijinIds', __('At least one selected slave account already has a different master.'));
            $this->showAltSaveErrorToast(__('At least one selected slave account already has a different master.'));

            return;
        }

        $slaveAccountsThatAreMasters = PeckAlt::query()
            ->whereIn('owner_id', $slaveGaijinIds);

        if ($this->editingMasterGaijinId !== null) {
            $slaveAccountsThatAreMasters->where('owner_id', '!=', $this->editingMasterGaijinId);
        }

        if ($slaveAccountsThatAreMasters->exists()) {
            $this->addError('altFormSlaveGaijinIds', __('A slave account cannot be declared as a master account.'));
            $this->showAltSaveErrorToast(__('A slave account cannot be declared as a master account.'));

            return;
        }

        $masterAssignedAsSlave = PeckAlt::query()
            ->where('alt_id', $masterGaijinId);

        if ($this->editingMasterGaijinId !== null) {
            $masterAssignedAsSlave->where('owner_id', '!=', $this->editingMasterGaijinId);
        }

        if ($masterAssignedAsSlave->exists()) {
            $this->addError('altFormMasterGaijinId', __('The selected master account is currently assigned as a slave account.'));
            $this->showAltSaveErrorToast(__('The selected master account is currently assigned as a slave account.'));

            return;
        }

        try {
            DB::transaction(function () use ($masterGaijinId, $slaveGaijinIds): void {
                if ($this->editingMasterGaijinId !== null) {
                    PeckAlt::query()
                        ->where('owner_id', $this->editingMasterGaijinId)
                        ->delete();
                }

                PeckAlt::query()
                    ->whereIn('alt_id', $slaveGaijinIds)
                    ->delete();

                foreach ($slaveGaijinIds as $slaveGaijinId) {
                    PeckAlt::query()->create([
                        'alt_id' => $slaveGaijinId,
                        'owner_id' => $masterGaijinId,
                    ]);
                }
            });
        } catch (Throwable $throwable) {
            report($throwable);
            $this->showAltSaveErrorToast(__('Saving master assignments failed.'));

            return;
        }

        $this->dispatch('peck-alt-saved');
        $this->closeMasterEditModal();
        $this->resetPage('alt-masters-page');
    }

    public function dismissAltSaveError(): void
    {
        $this->showAltSaveError = false;
        $this->altSaveErrorMessage = '';
    }

    public function selectUser(int $gaijinId): void
    {
        $this->ensureCanEdit();

        $peckUser = PeckUser::query()->findOrFail($gaijinId);

        $this->selectedGaijinId = $peckUser->gaijin_id;
        $this->form = [
            'gaijin_id' => $this->nullableString($peckUser->gaijin_id),
            'username' => $peckUser->username,
            'discord_id' => $this->nullableString($peckUser->discord_id),
            'tz' => $this->nullableString($peckUser->tz),
            'status' => $peckUser->status,
            'joindate' => $peckUser->joindate?->format('Y-m-d'),
            'initiator' => $this->nullableString($peckUser->initiator),
        ];

        $this->resetValidation();
        $this->showEditModal = true;
    }

    public function clearSelection(): void
    {
        $this->ensureCanEdit();

        $this->selectedGaijinId = null;
        $this->form = $this->blankUserForm();
        $this->resetValidation();
        $this->showEditModal = false;
    }

    public function openLeaveInfoModal(int $gaijinId, bool $fromStatusChange = false): void
    {
        $this->ensureCanEdit();

        $peckUser = PeckUser::query()
            ->with('leaveInfo')
            ->findOrFail($gaijinId);

        if ($peckUser->status !== 'ex_member') {
            $this->addError('selectedLeaveInfoGaijinId', __('Leave info can only be edited for ex-member users.'));

            return;
        }

        $this->selectedLeaveInfoGaijinId = $peckUser->gaijin_id;
        $this->selectedLeaveInfoUsername = $peckUser->username;
        $this->selectedLeaveInfoUserDetails = [
            'gaijin_id' => (string) $peckUser->gaijin_id,
            'username' => $peckUser->username,
            'status' => $peckUser->status,
            'discord_id' => $this->nullableString($peckUser->discord_id) ?? '—',
            'joindate' => $peckUser->joindate?->format('Y-m-d') ?? '—',
            'current_leave_info' => $peckUser->leaveInfo?->type ?? '—',
        ];
        $this->leaveInfoForm = [
            'type' => $peckUser->leaveInfo?->type ?? PeckLeaveInfo::TYPE_LEFT,
        ];
        $this->leaveInfoModalFromStatusChange = $fromStatusChange;
        $this->showLeaveInfoModal = true;
        $this->resetValidation();
    }

    public function closeLeaveInfoModal(): void
    {
        $this->ensureCanEdit();

        $this->showLeaveInfoModal = false;
        $this->selectedLeaveInfoGaijinId = null;
        $this->selectedLeaveInfoUsername = null;
        $this->leaveInfoForm = [
            'type' => PeckLeaveInfo::TYPE_LEFT,
        ];
        $this->selectedLeaveInfoUserDetails = [
            'gaijin_id' => '',
            'username' => '',
            'status' => '',
            'discord_id' => '',
            'joindate' => '',
            'current_leave_info' => '',
        ];
        $this->leaveInfoModalFromStatusChange = false;
        $this->resetValidation();
    }

    public function saveLeaveInfo(): void
    {
        $this->ensureCanEdit();

        if ($this->selectedLeaveInfoGaijinId === null) {
            $this->addError('selectedLeaveInfoGaijinId', __('Select an ex-member before saving leave info.'));

            return;
        }

        $validated = $this->validate($this->leaveInfoRules());
        $peckUser = PeckUser::query()->find($this->selectedLeaveInfoGaijinId);

        if ($peckUser === null) {
            $this->addError('selectedLeaveInfoGaijinId', __('The selected peck user no longer exists.'));
            $this->closeLeaveInfoModal();

            return;
        }

        if ($peckUser->status !== 'ex_member') {
            $this->addError('leaveInfoForm.type', __('Leave info can only be set for ex-member users.'));

            return;
        }

        PeckLeaveInfo::query()->updateOrCreate(
            ['user_id' => $peckUser->gaijin_id],
            ['type' => $validated['leaveInfoForm']['type']],
        );

        $this->dispatch('peck-leave-info-saved');
        $this->closeLeaveInfoModal();
    }

    public function save(): void
    {
        $this->ensureCanEdit();

        if ($this->selectedGaijinId === null) {
            $this->addError('selectedGaijinId', __('Select a peck user before saving.'));

            return;
        }

        $validated = $this->validate($this->rules());
        $updatedGaijinId = (int) $validated['form']['gaijin_id'];
        $updatedStatus = $validated['form']['status'];

        $previousGaijinId = $this->selectedGaijinId;
        $peckUser = PeckUser::query()->find($previousGaijinId);

        if ($peckUser === null) {
            $this->addError('selectedGaijinId', __('The selected peck user no longer exists.'));
            $this->clearSelection();

            return;
        }

        $previousStatus = $peckUser->status;

        $peckUser->fill([
            'gaijin_id' => $updatedGaijinId,
            'username' => $validated['form']['username'],
            'discord_id' => $this->nullableInteger($validated['form']['discord_id']),
            'tz' => $this->nullableInteger($validated['form']['tz']),
            'status' => $updatedStatus,
            'joindate' => $validated['form']['joindate'],
            'initiator' => $this->nullableInteger($validated['form']['initiator']),
        ]);
        $peckUser->save();

        if ($previousStatus === 'ex_member' && $updatedStatus !== 'ex_member') {
            PeckLeaveInfo::query()
                ->where('user_id', $previousGaijinId)
                ->delete();
        }

        $shouldOpenLeaveInfoModal = $previousStatus !== 'ex_member'
            && $updatedStatus === 'ex_member'
            && ! PeckLeaveInfo::query()->where('user_id', $updatedGaijinId)->exists();

        $this->dispatch('peck-user-saved');

        if ($shouldOpenLeaveInfoModal) {
            $this->showEditModal = false;
            $this->openLeaveInfoModal($updatedGaijinId, true);

            return;
        }

        $this->selectUser($updatedGaijinId);
    }

    /**
     * @return array{gaijin_id:?string,username:string,discord_id:?string,tz:?string,status:string,joindate:?string,initiator:?string}
     */
    protected function blankUserForm(): array
    {
        return [
            'gaijin_id' => null,
            'username' => '',
            'discord_id' => null,
            'tz' => '0',
            'status' => 'unverified',
            'joindate' => null,
            'initiator' => null,
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        $selectedGaijinId = $this->selectedGaijinId;

        return [
            'form.gaijin_id' => [
                'required',
                'integer',
                Rule::unique('peck_users', 'gaijin_id')->ignore($selectedGaijinId, 'gaijin_id'),
            ],
            'form.username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('peck_users', 'username')->ignore($selectedGaijinId, 'gaijin_id'),
            ],
            'form.discord_id' => [
                'nullable',
                'integer',
            ],
            'form.tz' => [
                'nullable',
                'integer',
                'between:-11,12',
            ],
            'form.status' => [
                'required',
                Rule::in($this->editableStatuses()),
            ],
            'form.joindate' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'form.initiator' => [
                'nullable',
                'integer',
                Rule::exists('officers', 'gaijin_id'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $gaijinId = $this->nullableInteger($this->form['gaijin_id'] ?? null);

                    if ($value !== null && $gaijinId !== null && (int) $value === $gaijinId) {
                        $fail(__('The initiator cannot be the same as the selected peck user.'));
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function leaveInfoRules(): array
    {
        return [
            'leaveInfoForm.type' => [
                'required',
                'string',
                Rule::in($this->leaveInfoTypes()),
            ],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function createUserRules(): array
    {
        return [
            'newUserForm.gaijin_id' => [
                'required',
                'integer',
                Rule::unique('peck_users', 'gaijin_id'),
            ],
            'newUserForm.username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('peck_users', 'username'),
            ],
            'newUserForm.discord_id' => [
                'nullable',
                'integer',
            ],
            'newUserForm.tz' => [
                'nullable',
                'integer',
                'between:-11,12',
            ],
            'newUserForm.status' => [
                'required',
                Rule::in($this->editableStatuses()),
            ],
            'newUserForm.joindate' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'newUserForm.initiator' => [
                'nullable',
                'integer',
                Rule::exists('officers', 'gaijin_id'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $gaijinId = $this->nullableInteger($this->newUserForm['gaijin_id'] ?? null);

                    if ($value !== null && $gaijinId !== null && (int) $value === $gaijinId) {
                        $fail(__('The initiator cannot be the same as the selected peck user.'));
                    }
                },
            ],
        ];
    }

    protected function showAltSaveErrorToast(string $message): void
    {
        $this->altSaveErrorMessage = $message;
        $this->showAltSaveError = true;
    }

    public function render(): View
    {
        $sortBy = $this->isSortableColumn($this->sortBy) ? $this->sortBy : 'gaijin_id';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        $initiatorOptions = Officer::query()
            ->select('officers.gaijin_id', 'officers.rank')
            ->join('peck_users', 'peck_users.gaijin_id', '=', 'officers.gaijin_id')
            ->with('peckUser')
            ->orderBy('peck_users.username')
            ->orderBy('officers.gaijin_id')
            ->get();

        $shownUsers = null;
        $leaveInfoUsers = null;
        $altMasterCards = null;
        $editingMasterSlaveUsers = collect();

        if ($this->isUsersSection()) {
            $shownUsers = PeckUser::query()
                ->with('initiatorUser')
                ->when($this->search !== '', function (Builder $query): void {
                    $searchTerm = '%'.$this->search.'%';

                    $query->where(function (Builder $innerQuery) use ($searchTerm): void {
                        $innerQuery
                            ->where('gaijin_id', 'like', $searchTerm)
                            ->orWhere('username', 'like', $searchTerm)
                            ->orWhere('discord_id', 'like', $searchTerm);
                    });
                })
                ->when($this->filters['status'] !== null, function (Builder $query): void {
                    $query->where('status', $this->filters['status']);
                })
                ->when($this->filters['tz'] !== null, function (Builder $query): void {
                    $query->where('tz', $this->filters['tz']);
                })
                ->when($this->filters['joined_after'] !== null, function (Builder $query): void {
                    $query->whereDate('joindate', '>=', $this->filters['joined_after']);
                })
                ->when($this->filters['joined_before'] !== null, function (Builder $query): void {
                    $query->whereDate('joindate', '<=', $this->filters['joined_before']);
                })
                ->orderBy($sortBy, $sortDirection)
                ->orderBy('gaijin_id')
                ->paginate(15);
        }

        if ($this->isLeaveInfoSection()) {
            $leaveInfoUsers = PeckUser::query()
                ->with('leaveInfo')
                ->where('status', 'ex_member')
                ->when($this->search !== '', function (Builder $query): void {
                    $searchTerm = '%'.$this->search.'%';

                    $query->where(function (Builder $innerQuery) use ($searchTerm): void {
                        $innerQuery
                            ->where('gaijin_id', 'like', $searchTerm)
                            ->orWhere('username', 'like', $searchTerm)
                            ->orWhere('discord_id', 'like', $searchTerm);
                    });
                })
                ->orderBy('username')
                ->orderBy('gaijin_id')
                ->paginate(15);
        }

        if ($this->isAltsSection()) {
            $trimmedAltSearch = trim($this->altSearch);

            $altMasterCards = PeckAlt::query()
                ->selectRaw('peck_alts.owner_id, peck_users.username as owner_username, count(*) as slave_count')
                ->join('peck_users', 'peck_users.gaijin_id', '=', 'peck_alts.owner_id')
                ->when($trimmedAltSearch !== '', function (Builder $query) use ($trimmedAltSearch): void {
                    $query->where('peck_users.username', 'like', '%'.$trimmedAltSearch.'%');
                })
                ->groupBy('peck_alts.owner_id', 'peck_users.username')
                ->orderBy('peck_users.username')
                ->orderBy('peck_alts.owner_id')
                ->paginate(12, ['*'], 'alt-masters-page');

            $slaveGaijinIds = collect($this->altFormSlaveGaijinIds)
                ->map(fn (mixed $slaveGaijinId): int => (int) $slaveGaijinId)
                ->unique()
                ->values()
                ->all();

            if ($slaveGaijinIds !== []) {
                $slaveUsers = PeckUser::query()
                    ->whereIn('gaijin_id', $slaveGaijinIds)
                    ->get(['gaijin_id', 'username'])
                    ->keyBy('gaijin_id');

                $editingMasterSlaveUsers = collect($slaveGaijinIds)
                    ->map(fn (int $slaveGaijinId): ?PeckUser => $slaveUsers->get($slaveGaijinId))
                    ->filter(fn (?PeckUser $peckUser): bool => $peckUser instanceof PeckUser)
                    ->values();
            }
        }

        return view('livewire.peck-users-dashboard', [
            'shownUsers' => $shownUsers,
            'leaveInfoUsers' => $leaveInfoUsers,
            'altMasterCards' => $altMasterCards,
            'editingMasterSlaveUsers' => $editingMasterSlaveUsers,
            'editableStatuses' => $this->editableStatuses(),
            'filterableStatuses' => $this->filterableStatuses(),
            'activeFilterCount' => $this->activeFilterCount(),
            'initiatorOptions' => $initiatorOptions,
            'leaveInfoTypes' => $this->leaveInfoTypes(),
        ]);
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    protected function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
