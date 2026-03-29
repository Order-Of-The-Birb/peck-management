<?php

namespace App\Livewire;

use App\Models\PeckAlt;
use App\Models\PeckUser;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class PeckAltsDashboard extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showEditModal = false;

    public bool $showCreateModal = false;

    public ?int $selectedAltId = null;

    /**
     * @var array{alt_id:?string,owner_id:?string}
     */
    public array $form = [
        'alt_id' => null,
        'owner_id' => null,
    ];

    /**
     * @var array{alt_id:?string,owner_id:?string}
     */
    public array $newAltForm = [
        'alt_id' => null,
        'owner_id' => null,
    ];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $queryString = [
        'search' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->ensureCanEdit();

        $this->newAltForm = $this->blankAltForm();
        $this->showCreateModal = true;
        $this->resetValidation();
    }

    public function closeCreateModal(): void
    {
        $this->ensureCanEdit();

        $this->newAltForm = $this->blankAltForm();
        $this->showCreateModal = false;
        $this->resetValidation();
    }

    public function selectAlt(int $altId): void
    {
        $this->ensureCanEdit();

        $peckAlt = PeckAlt::query()->findOrFail($altId);

        $this->selectedAltId = $peckAlt->alt_id;
        $this->form = [
            'alt_id' => (string) $peckAlt->alt_id,
            'owner_id' => (string) $peckAlt->owner_id,
        ];

        $this->showEditModal = true;
        $this->resetValidation();
    }

    public function clearSelection(): void
    {
        $this->ensureCanEdit();

        $this->selectedAltId = null;
        $this->form = $this->blankAltForm();
        $this->showEditModal = false;
        $this->resetValidation();
    }

    public function createAlt(): void
    {
        $this->ensureCanEdit();

        $validated = $this->validate($this->createRules());

        $altId = (int) $validated['newAltForm']['alt_id'];
        $ownerId = (int) $validated['newAltForm']['owner_id'];

        PeckAlt::query()->create([
            'alt_id' => $altId,
            'owner_id' => $ownerId,
        ]);
        $this->syncAltUserWithOwner($altId, $ownerId);

        $this->dispatch('peck-alt-created');
        $this->closeCreateModal();
        $this->clearSelection();
        $this->resetPage();
    }

    public function save(): void
    {
        $this->ensureCanEdit();

        if ($this->selectedAltId === null) {
            $this->addError('selectedAltId', __('Select an alt account mapping before saving.'));

            return;
        }

        $validated = $this->validate($this->rules());

        $peckAlt = PeckAlt::query()->find($this->selectedAltId);

        if ($peckAlt === null) {
            $this->addError('selectedAltId', __('The selected alt mapping no longer exists.'));
            $this->clearSelection();

            return;
        }

        $updatedAltId = (int) $validated['form']['alt_id'];
        $updatedOwnerId = (int) $validated['form']['owner_id'];

        $peckAlt->fill([
            'alt_id' => $updatedAltId,
            'owner_id' => $updatedOwnerId,
        ]);
        $peckAlt->save();
        $this->syncAltUserWithOwner($updatedAltId, $updatedOwnerId);

        $this->dispatch('peck-alt-saved');
        $this->clearSelection();
    }

    public function deleteSelectedAlt(): void
    {
        $this->ensureCanEdit();

        if ($this->selectedAltId === null) {
            $this->addError('selectedAltId', __('Select an alt account mapping before deleting.'));

            return;
        }

        $peckAlt = PeckAlt::query()->find($this->selectedAltId);

        if ($peckAlt === null) {
            $this->addError('selectedAltId', __('The selected alt mapping no longer exists.'));
            $this->clearSelection();

            return;
        }

        $peckAlt->delete();

        $this->dispatch('peck-alt-deleted');
        $this->clearSelection();
        $this->resetPage();
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        $selectedAltId = $this->selectedAltId;

        return [
            'form.alt_id' => [
                'required',
                'integer',
                Rule::exists('peck_users', 'gaijin_id'),
                Rule::unique('peck_alts', 'alt_id')->ignore($selectedAltId, 'alt_id'),
            ],
            'form.owner_id' => [
                'required',
                'integer',
                Rule::exists('peck_users', 'gaijin_id'),
                'different:form.alt_id',
            ],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function createRules(): array
    {
        return [
            'newAltForm.alt_id' => [
                'required',
                'integer',
                Rule::exists('peck_users', 'gaijin_id'),
                Rule::unique('peck_alts', 'alt_id'),
            ],
            'newAltForm.owner_id' => [
                'required',
                'integer',
                Rule::exists('peck_users', 'gaijin_id'),
                'different:newAltForm.alt_id',
            ],
        ];
    }

    /**
     * @return array{alt_id:?string,owner_id:?string}
     */
    protected function blankAltForm(): array
    {
        return [
            'alt_id' => null,
            'owner_id' => null,
        ];
    }

    protected function syncAltUserWithOwner(int $altId, int $ownerId): void
    {
        $ownerUser = PeckUser::query()->findOrFail($ownerId);
        $altUser = PeckUser::query()->findOrFail($altId);

        $altUser->fill([
            'discord_id' => $ownerUser->discord_id,
            'tz' => $ownerUser->tz,
            'status' => 'alt',
            'joindate' => $ownerUser->joindate,
            'initiator' => $ownerUser->initiator,
        ]);
        $altUser->save();
    }

    public function canEdit(): bool
    {
        return auth()->check() && (int) auth()->user()->level >= 1;
    }

    protected function ensureCanEdit(): void
    {
        abort_unless($this->canEdit(), 403);
    }

    public function render(): View
    {
        $userOptions = PeckUser::query()
            ->orderBy('username')
            ->orderBy('gaijin_id')
            ->get(['gaijin_id', 'username']);

        $peckAlts = PeckAlt::query()
            ->with(['altUser', 'ownerUser'])
            ->when($this->search !== '', function (Builder $query): void {
                $searchTerm = '%'.$this->search.'%';

                $query->where(function (Builder $innerQuery) use ($searchTerm): void {
                    $innerQuery
                        ->where('alt_id', 'like', $searchTerm)
                        ->orWhere('owner_id', 'like', $searchTerm)
                        ->orWhereHas('altUser', function (Builder $altQuery) use ($searchTerm): void {
                            $altQuery->where('username', 'like', $searchTerm);
                        })
                        ->orWhereHas('ownerUser', function (Builder $ownerQuery) use ($searchTerm): void {
                            $ownerQuery->where('username', 'like', $searchTerm);
                        });
                });
            })
            ->orderBy('alt_id')
            ->paginate(15);

        return view('livewire.peck-alts-dashboard', [
            'peckAlts' => $peckAlts,
            'userOptions' => $userOptions,
        ]);
    }
}
