<?php
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use App\Models\User;
use Illuminate\Validation\Rule;

new #[Title('Profile settings')] class extends Component {
	public ?string $selectedManagedUserId = null;
	public string $selectedManagedUserLevel = '0';
	public ?string $apiToken = null;

	#region Mounting
	public function mount(): void
	{
		$user = Auth::user();

		$this->name = $user->name;
		$this->email = $user->email;
		$this->apiToken = $user->api_token_plain;

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
	public function manageableUsers(): \Illuminate\Support\Collection
	{
		if (!$this->canManageUserLevels()) {
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
		if (!$this->canManageUserLevels() || !filled($this->selectedManagedUserId)) {
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
		if (!$this->canManageUserLevels() || !filled($selectedManagedUserId)) {
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
	#endregion
	#region REST API Token
	public function hasApiToken(): bool
	{
		return filled($this->apiToken);
	}
	#endregion
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Administration') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Manage user level')" :subheading="__('Manage the access level of registered users')" :admin-access="$this->adminAccess">
		<div class="my-6 space-y-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
			<div class="flex items-center gap-2">
				<flux:heading size="lg">{{ __('User Access Levels') }}</flux:heading>
		
				<flux:tooltip :content="__('0 = Viewer, 1 = Database access, 2 = Supervisor')" position="top">
					<span
						class="inline-flex size-5 items-center justify-center rounded-full border border-neutral-300 text-xs font-semibold text-neutral-600 dark:border-neutral-600 dark:text-neutral-300">
						?
					</span>
				</flux:tooltip>
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
		
				<div class="grid gap-3 md:grid-cols-[220px_auto] md:items-end">
					<flux:select wire:model="selectedManagedUserLevel" :label="__('Access level')">
						<option value="0">{{ __('0 - Viewer') }}</option>
						<option value="1">{{ __('1 - Database access') }}</option>
						<option value="2">{{ __('2 - Supervisor') }}</option>
					</flux:select>
		
					<flux:button type="button" variant="primary" wire:click="updateSelectedUserLevel"
						:disabled="! filled($selectedManagedUserId)">
						{{ __('Save Level') }}
					</flux:button>
				</div>
			</div>
		
			<x-action-message class="me-3" on="user-level-updated">
				{{ __('Saved.') }}
			</x-action-message>
		</div>
	</x-pages::settings.layout>
</section>
