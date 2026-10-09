<?php

use App\Actions\ThunderApi;
use App\Actions\ThunderApiException;
use App\Actions\ThunderApiTwoFactorRequiredException;
use App\Concerns\ProfileValidationRules;
use App\Models\ThunderApiToken;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';

    public string $thunderEmail = '';
    public string $thunderPassword = '';
    public string $thunderCode = '';
    public bool $twoFactorRequired = false;
    public ?string $thunderError = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
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

        if ($user->wasChanged('email')) {
            $user->sendEmailVerificationNotification();
        }

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

    #region ThunderAPI
    #[Computed]
    public function thunderToken(): ?ThunderApiToken
    {
        return ThunderApiToken::query()->find(Auth::id());
    }

    #[Computed]
    public function thunderStatus(): string
    {
        $token = $this->thunderToken;

        if ($token === null) {
            return 'missing';
        }

        return $token->isExpired() ? 'expired' : 'valid';
    }

    #[Computed]
    public function thunderStatusLabel(): string
    {
        return match ($this->thunderStatus) {
            'valid' => __('Connected'),
            'expired' => __('Expired'),
            default => __('Not connected'),
        };
    }

    public function submitThunderLogin(): void
    {
        $this->thunderError = null;

        $validated = $this->validate([
            'thunderEmail' => ['required', 'string', 'email', 'max:255'],
            'thunderPassword' => ['required', 'string', 'min:6', 'max:64'],
        ]);

        try {
            $result = app(ThunderApi::class)->login($validated['thunderEmail'], $validated['thunderPassword']);
        } catch (ThunderApiTwoFactorRequiredException) {
            $this->twoFactorRequired = true;
            $this->thunderCode = '';

            return;
        } catch (ThunderApiException $exception) {
            $this->thunderError = $exception->getMessage();

            return;
        }

        $this->storeThunderToken($result['token'], $result['user_id']);
    }

    public function submitThunderTwoFactor(): void
    {
        $this->thunderError = null;

        $validated = $this->validate([
            'thunderEmail' => ['required', 'string', 'email', 'max:255'],
            'thunderPassword' => ['required', 'string', 'min:6', 'max:64'],
            'thunderCode' => ['required', 'numeric', 'digits_between:4,8'],
        ]);

        $thunderApi = app(ThunderApi::class);

        try {
            $thunderApi->answerTwoFactor($validated['thunderEmail'], $validated['thunderPassword'], (string) $validated['thunderCode']);
        } catch (ThunderApiException $exception) {
            $this->thunderError = $exception->getMessage();

            return;
        }

        try {
            $result = $thunderApi->login($validated['thunderEmail'], $validated['thunderPassword']);
        } catch (ThunderApiTwoFactorRequiredException) {
            $this->thunderError = __('Two-factor authentication failed. Please check your code and try again.');

            return;
        } catch (ThunderApiException $exception) {
            $this->thunderError = $exception->getMessage();

            return;
        }

        $this->storeThunderToken($result['token'], $result['user_id']);
    }

    public function cancelTwoFactor(): void
    {
        $this->twoFactorRequired = false;
        $this->thunderCode = '';
        $this->thunderError = null;
    }

    public function disconnectThunder(): void
    {
        $this->thunderToken?->delete();

        $this->reset('thunderEmail', 'thunderPassword', 'thunderCode');
        $this->twoFactorRequired = false;
        $this->thunderError = null;
    }

    protected function storeThunderToken(string $token, int $gaijinId): void
    {
        $user = Auth::user();

        ThunderApiToken::storeForUser($user, $token, $gaijinId);

        $this->reset('thunderPassword', 'thunderCode');
        $this->twoFactorRequired = false;
        $this->thunderError = null;

        $this->dispatch('thunderapi-linked');
    }
    #endregion

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

        <flux:separator class="my-6" />

        <section class="w-full space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('ThunderAPI') }}</flux:heading>
                    <flux:subheading>{{ __('Link your War Thunder account to use ThunderAPI features.') }}</flux:subheading>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <span @class([
                        'h-2.5 w-2.5 rounded-full',
                        'bg-red-500' => $this->thunderStatus === 'missing',
                        'bg-amber-500' => $this->thunderStatus === 'expired',
                        'bg-green-500' => $this->thunderStatus === 'valid',
                    ])></span>
                    <flux:text>{{ $this->thunderStatusLabel }}</flux:text>
                </div>
            </div>

            @if ($this->thunderStatus === 'valid')
                <flux:callout variant="success" icon="check-circle" :heading="__('Connected to ThunderAPI')">
                    <flux:text>{{ __('Your War Thunder account is linked and its token is kept refreshed automatically.') }}</flux:text>
                </flux:callout>

                <flux:button variant="ghost" wire:click="disconnectThunder">{{ __('Disconnect') }}</flux:button>
            @else
                @if ($this->thunderStatus === 'expired')
                    <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('Your ThunderAPI token has expired')">
                        <flux:text>{{ __('Your linked token is no longer valid. Log in again to reconnect.') }}</flux:text>
                    </flux:callout>
                @endif

                <form wire:submit="{{ $twoFactorRequired ? 'submitThunderTwoFactor' : 'submitThunderLogin' }}" class="space-y-4">
                    <flux:field>
                        <flux:label>{{ __('Email') }}</flux:label>
                        <flux:input wire:model="thunderEmail" type="email" required autocomplete="off" />
                        <flux:error name="thunderEmail" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Password') }}</flux:label>
                        <flux:input wire:model="thunderPassword" type="password" required autocomplete="off" viewable />
                        <flux:error name="thunderPassword" />
                    </flux:field>

                    @if ($twoFactorRequired)
                        <flux:field>
                            <flux:label>{{ __('Two-factor code') }}</flux:label>
                            <flux:input wire:model="thunderCode" type="text" inputmode="numeric" required autocomplete="one-time-code" />
                            <flux:description>{{ __('Enter the code from your authenticator app or email.') }}</flux:description>
                            <flux:error name="thunderCode" />
                        </flux:field>
                    @endif

                    @if ($thunderError)
                        <flux:callout variant="danger" icon="exclamation-circle" :heading="$thunderError" />
                    @endif

                    <div class="flex items-center gap-4">
                        <flux:button variant="primary" type="submit">
                            {{ $twoFactorRequired ? __('Verify code') : __('Connect') }}
                        </flux:button>

                        @if ($twoFactorRequired)
                            <flux:button type="button" wire:click="cancelTwoFactor">{{ __('Cancel') }}</flux:button>
                        @endif
                    </div>
                </form>
            @endif
        </section>

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
