<?php

use App\Actions\RefreshPeckDB;
use App\Actions\ResolveUsernames;
use App\Actions\ThunderApi;
use App\Actions\ThunderApiException;
use App\Actions\ThunderApiUnauthorizedException;
use App\Actions\UpdateEnvironmentFile;
use App\Models\ApiKey;
use App\Models\PeckUser;
use App\Models\ThunderApiToken;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

use function Illuminate\Support\defer;

new #[Title('Administration settings')] class extends Component
{
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

    public bool $showVerificationRequiredModal = false;

    public ?string $generatedApiToken = null;

    public bool $showGeneratedApiKeyModal = false;

    public bool $showConfirmApiKeyResetModal = false;

    public bool $showApiKeyGenerationError = false;

    public string $peckUserDeletionSearch = '';

    public bool $showDeletePeckUserModal = false;

    public ?int $pendingDeletePeckUserGaijinId = null;

    /** @var array{gaijin_id:int,username:?string,status:string,discord_id:?int}|null */
    public ?array $pendingDeletePeckUserDetails = null;

    public bool $showDeletePeckUserError = false;

    public string $deletePeckUserErrorMessage = '';

    public string $squadronLookupSearch = '';

    public bool $squadronLookupLoading = false;

    public bool $squadronLookupFailed = false;

    public bool $squadronLookupSearched = false;

    public string $squadronLookupErrorMessage = '';

    /** @var list<array{_id:string,tag:string,name:string}> */
    public array $squadronLookupResults = [];

    public bool $showSquadronSelectionModal = false;

    /** @var array{_id:string,tag:string,name:string}|null */
    public ?array $pendingSquadronSelection = null;

    public ?string $forceRefreshError = null;

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

        $this->squadronLookupSearch = (string) config('peck.squadron_name');

        if ($this->canManageUserLevels() && ! $this->squadronIdConfigured && $this->thunderLoggedIn) {
            $this->performSquadronSearch();
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

    protected function ensureVerifiedForWrite(): bool
    {
        $user = Auth::user();

        if ($user instanceof User && ! $user->hasVerifiedEmail()) {
            $this->showVerificationRequiredModal = true;

            return false;
        }

        return true;
    }

    public function dismissVerificationRequiredModal(): void
    {
        $this->showVerificationRequiredModal = false;
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

        return User::query()->find((int) $this->selectedManagedUserId, ['id', 'name', 'email', 'level', 'email_verified_at']);
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

        if (! $this->ensureVerifiedForWrite()) {
            return;
        }

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

        if (! $this->ensureVerifiedForWrite()) {
            return;
        }

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

        if (! $this->ensureVerifiedForWrite()) {
            return;
        }

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
        } catch (Throwable $throwable) {
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

    protected function attachUsernames(Collection $users): void
    {
        $gaijinIds = $users
            ->pluck('gaijin_id')
            ->map(static fn (mixed $gaijinId): int => (int) $gaijinId)
            ->values()
            ->all();

        $usernames = app(ResolveUsernames::class)->resolve($gaijinIds);

        foreach ($users as $user) {
            $user->setAttribute('username', $usernames[$user->gaijin_id] ?? null);
        }
    }

    #region War Thunder user deletion
    #[Computed]
    public function filteredPeckUsersForDeletion(): Collection
    {
        if (! $this->canManageUserLevels()) {
            return collect();
        }

        $searchTerm = trim($this->peckUserDeletionSearch);

        $users = PeckUser::query()
            ->when($searchTerm !== '', function ($query) use ($searchTerm): void {
                $query->where('gaijin_id', 'like', '%'.$searchTerm.'%');
            })
            ->orderBy('gaijin_id')
            ->get(['gaijin_id']);

        $this->attachUsernames($users);

        return $users;
    }

    public function openDeletePeckUserModal(int $gaijinId): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        if (! $this->ensureVerifiedForWrite()) {
            return;
        }

        $peckUser = PeckUser::query()->find($gaijinId, ['gaijin_id', 'status', 'discord_id']);

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
            'username' => app(ResolveUsernames::class)->resolve([$peckUser->gaijin_id])[$peckUser->gaijin_id] ?? null,
            'status' => $peckUser->status,
            'discord_id' => $peckUser->discord_id,
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

        if (! $this->ensureVerifiedForWrite()) {
            return;
        }

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
        } catch (Throwable $throwable) {
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

        if (! $this->ensureVerifiedForWrite()) {
            return;
        }

        if ($this->hasApiKey) {
            $this->showConfirmApiKeyResetModal = true;

            return;
        }

        $this->generateApiKey();
    }

    public function confirmApiKeyReset(): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        if (! $this->ensureVerifiedForWrite()) {
            return;
        }

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
        } catch (Throwable $throwable) {
            report($throwable);

            $this->generatedApiToken = null;
            $this->showGeneratedApiKeyModal = false;
            $this->showApiKeyGenerationError = true;
        }
    }
    #endregion

    #region Squadron ID lookup
    #[Computed]
    public function squadronIdConfigured(): bool
    {
        return filled((string) config('peck.squadron_id'));
    }

    #[Computed]
    public function thunderLoggedIn(): bool
    {
        $token = ThunderApiToken::query()->find(Auth::id());

        return $token instanceof ThunderApiToken && ! $token->isExpired();
    }

    #[Computed]
    public function squadronLookupBlocked(): bool
    {
        return ! $this->thunderLoggedIn || $this->squadronLookupFailed;
    }

    #[Computed]
    public function squadronLookupBlockedMessage(): string
    {
        if (! $this->thunderLoggedIn) {
            return __('This card is only available after connecting your ThunderAPI account on your profile.');
        }

        return $this->squadronLookupErrorMessage !== ''
            ? $this->squadronLookupErrorMessage
            : __('ThunderAPI could not be reached.');
    }

    public function updatedSquadronLookupSearch(): void
    {
        $this->performSquadronSearch();
    }

    public function performSquadronSearch(): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        if (! $this->thunderLoggedIn) {
            $this->squadronLookupResults = [];
            $this->squadronLookupSearched = false;
            $this->squadronLookupFailed = false;
            $this->squadronLookupErrorMessage = '';

            return;
        }

        $query = trim($this->squadronLookupSearch);

        if ($query === '') {
            $this->squadronLookupResults = [];
            $this->squadronLookupSearched = false;
            $this->squadronLookupFailed = false;
            $this->squadronLookupErrorMessage = '';

            return;
        }

        $token = ThunderApiToken::query()->find(Auth::id());

        if (! $token instanceof ThunderApiToken || $token->isExpired()) {
            $this->squadronLookupResults = [];
            $this->squadronLookupSearched = false;
            $this->squadronLookupFailed = false;
            $this->squadronLookupErrorMessage = '';

            return;
        }

        $this->squadronLookupLoading = true;
        $this->squadronLookupFailed = false;
        $this->squadronLookupErrorMessage = '';

        try {
            $results = app(ThunderApi::class)->searchClans($token->token, $query);
        } catch (ThunderApiUnauthorizedException $exception) {
            $token->forceFill(['expires_at' => now()->subSecond()->timestamp])->save();

            $this->squadronLookupResults = [];
            $this->squadronLookupSearched = false;
            $this->squadronLookupFailed = true;
            $this->squadronLookupErrorMessage = $exception->getMessage();
            $this->squadronLookupLoading = false;

            return;
        } catch (ThunderApiException $exception) {
            $this->squadronLookupResults = [];
            $this->squadronLookupSearched = false;
            $this->squadronLookupFailed = true;
            $this->squadronLookupErrorMessage = $exception->getMessage();
            $this->squadronLookupLoading = false;

            return;
        } catch (Throwable $throwable) {
            report($throwable);

            $this->squadronLookupResults = [];
            $this->squadronLookupSearched = false;
            $this->squadronLookupFailed = true;
            $this->squadronLookupErrorMessage = __('ThunderAPI could not be reached.');
            $this->squadronLookupLoading = false;

            return;
        }

        $this->squadronLookupResults = $this->normalizeSquadronLookupResults($results);
        $this->squadronLookupSearched = true;
        $this->squadronLookupLoading = false;
    }

    public function requestSquadronSelection(string $id): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        $selection = collect($this->squadronLookupResults)->firstWhere('_id', $id);

        if (! is_array($selection)) {
            return;
        }

        $this->pendingSquadronSelection = $selection;
        $this->showSquadronSelectionModal = true;
    }

    public function cancelSquadronSelection(): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        $this->showSquadronSelectionModal = false;
        $this->pendingSquadronSelection = null;
    }

    public function confirmSquadronSelection(): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        if (! $this->ensureVerifiedForWrite()) {
            return;
        }

        if (! is_array($this->pendingSquadronSelection)) {
            return;
        }

        $id = trim((string) ($this->pendingSquadronSelection['_id'] ?? ''));

        if ($id === '') {
            $this->cancelSquadronSelection();

            return;
        }

        app(UpdateEnvironmentFile::class)->set('SQUADRON_ID', $id);

        config()->set('peck.squadron_id', $id);

        $this->cancelSquadronSelection();
        $this->squadronLookupResults = [];
        $this->squadronLookupSearched = false;
        $this->squadronLookupFailed = false;
        $this->squadronLookupErrorMessage = '';
        $this->dispatch('squadron-id-configured');
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @return list<array{_id:string,tag:string,name:string}>
     */
    protected function normalizeSquadronLookupResults(array $results): array
    {
        $normalized = [];

        foreach ($results as $clan) {
            if (! is_array($clan)) {
                continue;
            }

            $id = trim((string) ($clan['_id'] ?? ''));

            if ($id === '') {
                continue;
            }

            $normalized[] = [
                '_id' => $id,
                'tag' => trim((string) ($clan['tag'] ?? '')),
                'name' => trim((string) ($clan['name'] ?? '')),
            ];
        }

        return $normalized;
    }
    #endregion

    #region Force refresh
    public function forceRefreshCooldownMinutes(): int
    {
        return max(1, (int) config('peck.force_refresh.cooldown_minutes', 10));
    }

    #[Computed]
    public function forceRefreshCooldownSeconds(): int
    {
        $startedAt = Cache::get((string) config('peck.force_refresh.lock_key'));

        if (! is_numeric($startedAt)) {
            return 0;
        }

        return max(0, ($this->forceRefreshCooldownMinutes() * 60) - (now()->timestamp - (int) $startedAt));
    }

    #[Computed]
    public function forceRefreshCooldownLabel(): string
    {
        $seconds = $this->forceRefreshCooldownSeconds();

        if ($seconds <= 0) {
            return '';
        }

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    /**
     * @return array{status:string,message:string}|null
     */
    #[Computed]
    public function forceRefreshResult(): ?array
    {
        $result = Cache::get((string) config('peck.force_refresh.result_key'));

        return is_array($result) ? $result : null;
    }

    public function requestForceRefresh(): void
    {
        abort_unless($this->canManageUserLevels(), 403);

        if (! $this->ensureVerifiedForWrite()) {
            return;
        }

        $this->forceRefreshError = null;

        $lockKey = (string) config('peck.force_refresh.lock_key');
        $resultKey = (string) config('peck.force_refresh.result_key');
        $cooldownMinutes = $this->forceRefreshCooldownMinutes();
        $now = now();

        if (! Cache::add($lockKey, $now->timestamp, $now->copy()->addMinutes($cooldownMinutes))) {
            $this->forceRefreshError = __('A refresh was started recently. Please try again in :time.', [
                'time' => $this->forceRefreshCooldownLabel(),
            ]);

            return;
        }

        Cache::forget($resultKey);

        $this->dispatch('force-refresh-started');

        defer(function () use ($resultKey, $cooldownMinutes): void {
            try {
                $stats = app(RefreshPeckDB::class)->handle();

                Cache::put($resultKey, [
                    'status' => 'success',
                    'message' => __('Refresh completed. :created users created, :members members received.', [
                        'created' => $stats['users_created'],
                        'members' => $stats['members_received'],
                    ]),
                ], now()->addMinutes($cooldownMinutes));

                Log::info('Manual PECK database refresh completed.', $stats);
            } catch (Throwable $throwable) {
                Cache::put($resultKey, [
                    'status' => 'error',
                    'message' => $throwable->getMessage(),
                ], now()->addMinutes($cooldownMinutes));

                Log::error('Manual PECK database refresh failed.', [
                    'message' => $throwable->getMessage(),
                ]);
            }
        }, 'peck-force-refresh')->always();
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
                            <flux:text class="break-all text-xs">
                                {{ $this->selectedManagedUser->email }}
                                @if (! $this->selectedManagedUser->hasVerifiedEmail())
                                    <span class="font-medium text-amber-600 dark:text-amber-400">{{ __('(Unverified)') }}</span>
                                @endif
                            </flux:text>
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
                <flux:heading size="lg">{{ __('Delete user') }}</flux:heading>
            </div>

            <div class="space-y-3">
                <flux:input wire:model.live.debounce.300ms="peckUserDeletionSearch" :label="__('Search by Gaijin ID')" />

                <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
                    <div class="max-h-56 overflow-y-auto divide-y divide-neutral-100 dark:divide-neutral-800">
                        @forelse ($this->filteredPeckUsersForDeletion as $filteredPeckUser)
                            <div wire:key="delete-peck-user-row-{{ $filteredPeckUser->gaijin_id }}" class="flex items-center justify-between gap-3 px-3 py-2">
                                <flux:text>{{ $filteredPeckUser->username ?? $filteredPeckUser->gaijin_id }}</flux:text>

                                <button
                                    type="button"
                                    wire:click="openDeletePeckUserModal({{ $filteredPeckUser->gaijin_id }})"
                                    class="rounded-md p-2 text-neutral-500 transition hover:bg-red-50 hover:text-red-600 dark:text-neutral-400 dark:hover:bg-red-900/20 dark:hover:text-red-300"
                                    aria-label="{{ __('Delete :username', ['username' => $filteredPeckUser->username ?? $filteredPeckUser->gaijin_id]) }}"
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

        <div class="mb-6 break-inside-avoid space-y-4 rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900/30">
            <div class="flex items-center gap-2">
                <flux:heading size="lg">{{ __('Force Refresh') }}</flux:heading>
            </div>

            <flux:text>{{ __('Re-sync the PECK database from ThunderAPI. Can only be triggered once every :minutes minutes.', ['minutes' => $this->forceRefreshCooldownMinutes()]) }}</flux:text>

            <flux:button type="button" variant="primary" wire:click="requestForceRefresh" wire:loading.attr="disabled" wire:target="requestForceRefresh">
                {{ __('Force refresh') }}
            </flux:button>

            @if ($this->forceRefreshCooldownSeconds > 0)
                <flux:text class="text-sm text-neutral-500 dark:text-neutral-400">
                    {{ __('Refresh available in :time.', ['time' => $this->forceRefreshCooldownLabel]) }}
                </flux:text>
            @endif

            @if ($forceRefreshError)
                <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/40 dark:text-red-100">
                    {{ $forceRefreshError }}
                </div>
            @endif

            @if ($this->forceRefreshResult)
                @if ($this->forceRefreshResult['status'] === 'success')
                    <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/40 dark:text-green-100">
                        {{ $this->forceRefreshResult['message'] }}
                    </div>
                @else
                    <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/40 dark:text-red-100">
                        {{ $this->forceRefreshResult['message'] }}
                    </div>
                @endif
            @endif

            <x-action-message class="me-3" on="force-refresh-started">
                {{ __('Refresh started.') }}
            </x-action-message>
        </div>

        @if (! $this->squadronIdConfigured)
        <div class="mb-6 break-inside-avoid space-y-4 rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900/30">
            <div class="relative">
                <div class="flex items-center gap-2">
                    <flux:heading size="lg">{{ __('Squadron ID Lookup') }}</flux:heading>
                </div>

                <flux:subheading class="mt-1">
                    {{ __('Initialize Squadron ID value. This will be used for future lookup requests across the site. Only has to be set up once.') }}
                </flux:subheading>

                <div class="mt-4 space-y-3">
                    <flux:input wire:model.live.debounce.1000ms="squadronLookupSearch" :label="__('Search squadrons')" />

                    @if ($squadronLookupLoading)
                        <div class="px-3 py-4 text-center text-sm text-neutral-500 dark:text-neutral-400">
                            {{ __('Searching…') }}
                        </div>
                    @elseif ($squadronLookupSearched && $squadronLookupResults === [])
                        <div class="rounded-lg border border-neutral-200 px-3 py-4 text-center text-sm text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">
                            {{ __('No squadron could be found with this search. Try refining your search query.') }}
                        </div>
                    @else
                        <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
                            <div class="max-h-56 overflow-y-auto divide-y divide-neutral-100 dark:divide-neutral-800">
                                @forelse ($squadronLookupResults as $squadronLookupResult)
                                    <div wire:key="squadron-lookup-result-{{ $squadronLookupResult['_id'] }}" class="flex items-center justify-between gap-3 px-3 py-2">
                                        <flux:text>{{ $squadronLookupResult['_id'] }} <span class="wt-glyphs">{{ $squadronLookupResult['tag'] }}</span> {{ $squadronLookupResult['name'] }}</flux:text>

                                        <flux:button type="button" variant="primary" size="sm" wire:click="requestSquadronSelection('{{ $squadronLookupResult['_id'] }}')">
                                            {{ __('Select') }}
                                        </flux:button>
                                    </div>
                                @empty
                                    <div class="px-3 py-4 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                        {{ __('No results.') }}
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endif
                </div>

                @if ($this->squadronLookupBlocked)
                    <div class="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/60 p-4 backdrop-blur-sm dark:bg-neutral-900/60">
                        <flux:text class="max-w-sm text-center font-medium">
                            {{ $this->squadronLookupBlockedMessage }}
                        </flux:text>
                    </div>
                @endif
            </div>
        </div>
        @endif
        </div>

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

        <flux:modal wire:model="showSquadronSelectionModal" class="max-w-xl">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Confirm squadron selection') }}</flux:heading>
                    <flux:subheading>
                        {{ __('Are you sure this is the right squadron?') }}
                    </flux:subheading>
                </div>

                <div class="space-y-1 rounded-lg border border-neutral-200 bg-neutral-50 p-4 text-sm dark:border-neutral-700 dark:bg-neutral-900/40">
                    <flux:text>{{ __('Squadron ID: :id', ['id' => $pendingSquadronSelection['_id'] ?? '—']) }}</flux:text>
                    <flux:text>{{ __('Tag:') }} <span class="wt-glyphs">{{ $pendingSquadronSelection['tag'] ?? '—' }}</span></flux:text>
                    <flux:text>{{ __('Name: :name', ['name' => $pendingSquadronSelection['name'] ?? '—']) }}</flux:text>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" wire:click="cancelSquadronSelection">
                            {{ __('Cancel') }}
                        </flux:button>
                    </flux:modal.close>

                    <flux:button type="button" variant="primary" wire:click="confirmSquadronSelection" wire:loading.attr="disabled" wire:target="confirmSquadronSelection">
                        {{ __('Confirm') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        <flux:modal wire:model="showVerificationRequiredModal" class="max-w-md">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Email verification required') }}</flux:heading>
                    <flux:text class="mt-2">
                        {{ __('You must verify your email address before you can make changes.') }}
                    </flux:text>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" wire:click="dismissVerificationRequiredModal">
                            {{ __('Close') }}
                        </flux:button>
                    </flux:modal.close>

                    <flux:button type="button" variant="primary" :href="route('profile.edit')" wire:navigate>
                        {{ __('Verify email') }}
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
