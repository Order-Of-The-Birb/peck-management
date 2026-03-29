<?php

namespace App\Livewire;

use App\Models\Officer;
use App\Models\PeckUser;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

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
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
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
        return [
            'member',
            'ex_member',
            'unverified',
        ];
    }

    public function canEdit(): bool
    {
        return auth()->check() && (int) auth()->user()->level >= 1;
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

    public function save(): void
    {
        $this->ensureCanEdit();

        if ($this->selectedGaijinId === null) {
            $this->addError('selectedGaijinId', __('Select a peck user before saving.'));

            return;
        }

        $validated = $this->validate($this->rules());

        $previousGaijinId = $this->selectedGaijinId;
        $peckUser = PeckUser::query()->find($previousGaijinId);

        if ($peckUser === null) {
            $this->addError('selectedGaijinId', __('The selected peck user no longer exists.'));
            $this->clearSelection();

            return;
        }

        $peckUser->fill([
            'username' => $validated['form']['username'],
            'discord_id' => $this->nullableInteger($validated['form']['discord_id']),
            'tz' => $this->nullableInteger($validated['form']['tz']),
            'status' => $validated['form']['status'],
            'joindate' => $validated['form']['joindate'],
            'initiator' => $this->nullableInteger($validated['form']['initiator']),
        ]);
        $peckUser->save();

        $this->selectUser($updatedGaijinId);
        $this->dispatch('peck-user-saved');
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

        return view('livewire.peck-users-dashboard', [
            'shownUsers' => $shownUsers,
            'editableStatuses' => $this->editableStatuses(),
            'filterableStatuses' => $this->filterableStatuses(),
            'activeFilterCount' => $this->activeFilterCount(),
            'initiatorOptions' => $initiatorOptions,
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
