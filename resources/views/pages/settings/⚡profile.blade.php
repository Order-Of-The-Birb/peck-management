<?php

use App\Models\User;
use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';
    public ?string $apiToken = null;
    public ?string $selectedManagedUserId = null;
    public string $selectedManagedUserLevel = '0';

    /**
     * Mount the component.
     */
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

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    public function resetApiToken(): void
    {
        $user = Auth::user();

        $plainToken = sprintf('peck_%s', Str::random(64));

        $user->forceFill([
            'api_token' => hash('sha256', $plainToken),
            'api_token_plain' => $plainToken,
        ]);
        $user->save();

        $this->apiToken = $plainToken;
        $this->dispatch('api-token-reset');
    }

    public function updatedSelectedManagedUserId(?string $userId): void
    {
        if (! $this->canManageUserLevels()) {
            return;
        }

        if (! filled($userId)) {
            $this->selectedManagedUserLevel = '0';

            return;
        }

        $selectedUser = User::query()->find((int) $userId);

        if ($selectedUser !== null) {
            $this->selectedManagedUserLevel = (string) $selectedUser->level;
        }
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

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && !Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return !Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
    #[Computed]
    public function adminAccess(): bool
    {
        return (int) Auth::user()->level === 2;
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')" :admin-access="$this->adminAccess">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        <div class="my-6 space-y-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700" x-data="{ copied: false }">
            <div>
                <flux:heading size="lg">{{ __('Bot API Token') }}</flux:heading>
                <flux:subheading>{{ __('Use this token in your bot requests. Resetting it will immediately invalidate the previous token.') }}</flux:subheading>
            </div>

            <flux:input
                wire:model="apiToken"
                :label="__('Current Token')"
                type="text"
                readonly
                autocomplete="off"
                :placeholder="__('No token generated yet.')"
            />

            <div class="flex flex-wrap items-center gap-3">
                <flux:button
                    type="button"
                    variant="ghost"
                    x-on:click="if ($wire.apiToken) { navigator.clipboard.writeText($wire.apiToken).then(() => { copied = true; setTimeout(() => copied = false, 2000); }); }"
                    x-bind:disabled="! $wire.apiToken"
                >
                    {{ __('Copy Token') }}
                </flux:button>

                <flux:button type="button" variant="primary" wire:click="resetApiToken">
                    {{ $this->hasApiToken ? __('Reset Token') : __('Generate Token') }}
                </flux:button>

                <x-action-message class="me-3" on="api-token-reset">
                    {{ __('Token reset.') }}
                </x-action-message>

                <flux:text x-cloak x-show="copied" class="!text-green-600 !dark:text-green-400">
                    {{ __('Copied to clipboard.') }}
                </flux:text>
            </div>
        </div>

        @if ($this->canManageUserLevels)
            <div class="my-6 space-y-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <div class="flex items-center gap-2">
                    <flux:heading size="lg">{{ __('User Access Levels') }}</flux:heading>

                    <flux:tooltip :content="__('0 = Viewer, 1 = Database access, 2 = Supervisor')" position="top">
                        <span class="inline-flex size-5 items-center justify-center rounded-full border border-neutral-300 text-xs font-semibold text-neutral-600 dark:border-neutral-600 dark:text-neutral-300">
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

                        <flux:button type="button" variant="primary" wire:click="updateSelectedUserLevel" :disabled="! filled($selectedManagedUserId)">
                            {{ __('Save Level') }}
                        </flux:button>
                    </div>
                </div>

                <x-action-message class="me-3" on="user-level-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        @endif

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
