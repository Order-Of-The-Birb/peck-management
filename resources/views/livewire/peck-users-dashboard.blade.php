<div>
    <style>
        input[type=number]::-webkit-outer-spin-button,
        input[type=number]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type=number] {
            -moz-appearance: textfield;
        }
    </style>
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        @if ($this->isUsersSection())
            <section class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">
                <div class="flex w-full flex-row items-end gap-5">
                    <div>
                        <flux:heading size="xl">{{ __('Users') }}</flux:heading>
                        <flux:text>{{ __('View and edit records') }}</flux:text>
                    </div>

                    <div class="ml-auto flex items-end gap-3">
                        <flux:button type="button" variant="primary" wire:click="openFilterModal" class="w-auto shrink-0">
                            {{ __('Filter') }}
                            @if ($activeFilterCount > 0)
                                <span class="ml-2 rounded-full bg-white/20 px-2 py-0.5 text-xs font-medium">
                                    {{ $activeFilterCount }}
                                </span>
                            @endif
                        </flux:button>

                        @if ($this->canEdit())
                            <flux:button type="button" variant="primary" wire:click="openCreateUserModal" class="w-auto shrink-0">
                                {{ __('Add User') }}
                            </flux:button>
                        @endif

                        <div class="w-xl">
                            <flux:input
                                wire:model.live.debounce.300ms="search"
                                :placeholder="__('Search by Gaijin ID, username, or Discord ID')"
                            />
                        </div>
                    </div>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table class="w-full min-w-full table-fixed divide-y divide-neutral-200 text-left text-sm dark:divide-neutral-700">
                        <thead class="bg-neutral-100/70 text-xs uppercase tracking-wide text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                            <tr>
                                <th class="px-3 py-2">
                                    <button type="button" wire:click="sort('gaijin_id')" class="inline-flex items-center gap-1 hover:text-neutral-800 dark:hover:text-neutral-100">
                                        {{ __('Gaijin ID') }}
                                        @if ($this->isSortedBy('gaijin_id'))
                                            <span class="text-[10px]">{{ strtoupper($sortDirection) }}</span>
                                        @endif
                                    </button>
                                </th>
                                <th class="px-3 py-2">
                                    <button type="button" wire:click="sort('username')" class="inline-flex items-center gap-1 hover:text-neutral-800 dark:hover:text-neutral-100">
                                        {{ __('Username') }}
                                        @if ($this->isSortedBy('username'))
                                            <span class="text-[10px]">{{ strtoupper($sortDirection) }}</span>
                                        @endif
                                    </button>
                                </th>
                                <th class="px-3 py-2">
                                    <button type="button" wire:click="sort('status')" class="inline-flex items-center gap-1 hover:text-neutral-800 dark:hover:text-neutral-100">
                                        {{ __('Status') }}
                                        @if ($this->isSortedBy('status'))
                                            <span class="text-[10px]">{{ strtoupper($sortDirection) }}</span>
                                        @endif
                                    </button>
                                </th>
                                <th class="w-44 px-3 py-2">
                                    <button type="button" wire:click="sort('discord_id')" class="inline-flex items-center gap-1 hover:text-neutral-800 dark:hover:text-neutral-100">
                                        {{ __('Discord ID') }}
                                        @if ($this->isSortedBy('discord_id'))
                                            <span class="text-[10px]">{{ strtoupper($sortDirection) }}</span>
                                        @endif
                                    </button>
                                </th>
                                <th class="w-20 px-3 py-2">
                                    <button type="button" wire:click="sort('tz')" class="inline-flex items-center gap-1 hover:text-neutral-800 dark:hover:text-neutral-100">
                                        {{ __('TZ') }}
                                        @if ($this->isSortedBy('tz'))
                                            <span class="text-[10px]">{{ strtoupper($sortDirection) }}</span>
                                        @endif
                                    </button>
                                </th>
                                <th class="px-3 py-2">
                                    <button type="button" wire:click="sort('joindate')" class="inline-flex items-center gap-1 hover:text-neutral-800 dark:hover:text-neutral-100">
                                        {{ __('Join Date') }}
                                        @if ($this->isSortedBy('joindate'))
                                            <span class="text-[10px]">{{ strtoupper($sortDirection) }}</span>
                                        @endif
                                    </button>
                                </th>
                                <th class="px-3 py-2">
                                    <button type="button" wire:click="sort('initiator')" class="inline-flex items-center gap-1 hover:text-neutral-800 dark:hover:text-neutral-100">
                                        {{ __('Initiator') }}
                                        @if ($this->isSortedBy('initiator'))
                                            <span class="text-[10px]">{{ strtoupper($sortDirection) }}</span>
                                        @endif
                                    </button>
                                </th>
                                <th class="px-3 py-2">
                                    <button type="button" wire:click="sort('sqb_part')" class="inline-flex items-center gap-1 hover:text-neutral-800 dark:hover:text-neutral-100">
                                        {{ __('SQB Part') }}
                                        @if ($this->isSortedBy('sqb_part'))
                                            <span class="text-[10px]">{{ strtoupper($sortDirection) }}</span>
                                        @endif
                                    </button>
                                </th>
                                @if ($this->canEdit())
                                    <th class="px-3 py-2 text-right">{{ __('Actions') }}</th>
                                @endif
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            @forelse ($shownUsers as $peckUser)
                                <tr wire:key="peck-user-{{ $peckUser->gaijin_id }}" @class([
                                    'bg-blue-50/70 dark:bg-blue-900/20' => $selectedGaijinId === $peckUser->gaijin_id,
                                ])>
                                    <td class="px-3 py-2 font-medium">{{ $peckUser->gaijin_id }}</td>
                                    <td class="px-3 py-2">{{ $peckUser->username }}</td>
                                    <td class="px-3 py-2">{{ $peckUser->status }}</td>
                                    <td class="px-3 py-2">{{ $peckUser->discord_id ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $peckUser->tz ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $peckUser->joindate?->format('Y-m-d') ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $peckUser->initiatorUser?->username ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ is_null($peckUser->sqb_part) ? __('Unknown') : ($peckUser->sqb_part ? __('Yes') : __('No')) }}</td>
                                    @if ($this->canEdit())
                                        <td class="px-3 py-2 text-right">
                                            <flux:button
                                                :variant="$selectedGaijinId === $peckUser->gaijin_id ? 'primary' : 'ghost'"
                                                wire:click="selectUser({{ $peckUser->gaijin_id }})"
                                                class="!px-3 !py-1 text-xs"
                                            >
                                                {{ __('Edit') }}
                                            </flux:button>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $this->canEdit() ? 9 : 8 }}" class="px-3 py-4 text-center text-neutral-500 dark:text-neutral-400">
                                        {{ __('No users found.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $shownUsers?->links() }}
                </div>
            </section>
        @endif

        @if ($this->isLeaveInfoSection())
            <section class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">
                <div class="flex w-full flex-row items-end gap-5">
                    <div>
                        <flux:heading size="xl">{{ __('Leave info') }}</flux:heading>
                        <flux:text>{{ __('Edit leave info for ex-member users') }}</flux:text>
                    </div>

                    <div class="ml-auto w-xl">
                        <flux:input
                            wire:model.live.debounce.300ms="search"
                            :placeholder="__('Search by Gaijin ID, username, or Discord ID')"
                        />
                    </div>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table class="w-full min-w-full table-fixed divide-y divide-neutral-200 text-left text-sm dark:divide-neutral-700">
                        <thead class="bg-neutral-100/70 text-xs uppercase tracking-wide text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                            <tr>
                                <th class="px-3 py-2">{{ __('Gaijin ID') }}</th>
                                <th class="px-3 py-2">{{ __('Username') }}</th>
                                <th class="px-3 py-2">{{ __('Discord ID') }}</th>
                                <th class="px-3 py-2">{{ __('Leave Type') }}</th>
                                @if ($this->canEdit())
                                    <th class="px-3 py-2 text-right">{{ __('Actions') }}</th>
                                @endif
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            @forelse ($leaveInfoUsers as $peckUser)
                                <tr wire:key="leave-info-user-{{ $peckUser->gaijin_id }}" @class([
                                    'bg-blue-50/70 dark:bg-blue-900/20' => $selectedLeaveInfoGaijinId === $peckUser->gaijin_id,
                                ])>
                                    <td class="px-3 py-2 font-medium">{{ $peckUser->gaijin_id }}</td>
                                    <td class="px-3 py-2">{{ $peckUser->username }}</td>
                                    <td class="px-3 py-2">{{ $peckUser->discord_id ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $peckUser->leaveInfo?->type ?? '—' }}</td>
                                    @if ($this->canEdit())
                                        <td class="px-3 py-2 text-right">
                                            <flux:button
                                                variant="primary"
                                                wire:click="openLeaveInfoModal({{ $peckUser->gaijin_id }})"
                                                class="!px-3 !py-1 text-xs"
                                            >
                                                {{ __('Edit') }}
                                            </flux:button>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $this->canEdit() ? 5 : 4 }}" class="px-3 py-4 text-center text-neutral-500 dark:text-neutral-400">
                                        {{ __('No ex-member users found.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $leaveInfoUsers?->links() }}
                </div>
            </section>
        @endif

        @if ($this->isAltsSection())
            <section class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">
                <div class="flex w-full flex-row items-end gap-5">
                    <div>
                        <flux:heading size="xl">{{ __('Alts') }}</flux:heading>
                        <flux:text>{{ __('Manage master and slave account assignments') }}</flux:text>
                    </div>

                    <div class="ml-auto flex items-end gap-3">
                        @if ($this->canEdit())
                            <flux:button type="button" variant="primary" wire:click="openCreateMasterModal" class="w-auto shrink-0">
                                {{ __('Add') }}
                            </flux:button>
                        @endif

                        <div class="w-xl">
                            <flux:input
                                wire:model.live.debounce.300ms="altSearch"
                                :placeholder="__('Search by master account name')"
                            />
                        </div>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @forelse ($altMasterCards as $altMasterCard)
                        <article wire:key="alt-master-card-{{ $altMasterCard->owner_id }}" class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-900/40">
                            <div>
                                <flux:heading size="md">{{ $altMasterCard->owner_username }}</flux:heading>
                                <flux:text class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ __('ID: :id', ['id' => $altMasterCard->owner_id]) }}
                                </flux:text>
                                <flux:text class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ __('Slave accounts: :count', ['count' => $altMasterCard->slave_count]) }}
                                </flux:text>
                            </div>

                            @if ($this->canEdit())
                                <div class="mt-4">
                                    <flux:button type="button" variant="ghost" wire:click="openEditMasterModal({{ $altMasterCard->owner_id }})" class="w-full justify-center">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </div>
                            @endif
                        </article>
                    @empty
                        <div class="col-span-full rounded-xl border border-dashed border-neutral-300 p-6 text-center text-sm text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">
                            {{ __('No master accounts found.') }}
                        </div>
                    @endforelse
                </div>

                <div class="mt-4">
                    {{ $altMasterCards?->links() }}
                </div>
            </section>
        @endif

        @if ($this->isContextSection())
            <section class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">
                <div class="flex w-full flex-row items-end gap-5">
                    <div>
                        <flux:heading size="xl">{{ __('Context') }}</flux:heading>
                    </div>

                    <div class="ml-auto w-xl">
                        <flux:input
                            wire:model.live.debounce.300ms="contextSearch"
                            :placeholder="__('Search by username, Gaijin ID, or Discord ID')"
                        />
                    </div>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @forelse ($contextUserCards as $contextUserCard)
                        <article wire:key="context-user-card-{{ $contextUserCard->gaijin_id }}" class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-900/40">
                            <flux:heading size="md">{{ $contextUserCard->username }}</flux:heading>

                            <div class="mt-4">
                                <flux:button type="button" variant="ghost" wire:click="openContextModal({{ $contextUserCard->gaijin_id }})" class="w-full justify-center">
                                    {{ __('Edit') }}
                                </flux:button>
                            </div>
                        </article>
                    @empty
                        <div class="col-span-full rounded-xl border border-dashed border-neutral-300 p-6 text-center text-sm text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">
                            {{ __('No users found.') }}
                        </div>
                    @endforelse
                </div>

                <div class="mt-4">
                    {{ $contextUserCards?->links() }}
                </div>
            </section>
        @endif

        @if ($this->isSquadronSection())
            @php($squadronBlockReason = $this->squadronBlockReason())

            @if ($squadronBlockReason === 'squadron_id')
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 p-6 backdrop-blur-sm">
                    <div class="w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-6 text-center shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:heading size="lg">{{ __('Squadron not set up') }}</flux:heading>
                        <flux:text class="mt-2">{{ __('The Squadron ID is not yet set up. Please contact an administrator.') }}</flux:text>
                    </div>
                </div>
            @elseif ($squadronBlockReason === 'thunder')
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 p-6 backdrop-blur-sm">
                    <div class="w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-6 text-center shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:heading size="lg">{{ __('ThunderAPI connection required') }}</flux:heading>
                        <flux:text class="mt-2">{{ __('Connect your ThunderAPI account to access the Squadron pages.') }}</flux:text>
                        <div class="mt-6 flex justify-center">
                            <flux:button variant="primary" :href="route('profile.edit')" wire:navigate>
                                {{ __('Open Settings') }}
                            </flux:button>
                        </div>
                    </div>
                </div>
            @elseif ($squadronBlockReason === 'clearance')
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 p-6 backdrop-blur-sm">
                    <div class="w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-6 text-center shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:heading size="lg">{{ __('Insufficient clearance') }}</flux:heading>
                        <flux:text class="mt-2">{{ __('You do not have a high enough clearance to access this page.') }}</flux:text>
                    </div>
                </div>
            @else
                @if ($this->isSquadronLogsSection())
                    <section class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">
                        <div>
                            <flux:heading size="xl">{{ __('Logs') }}</flux:heading>
                            <flux:text>{{ __('Recent squadron activity') }}</flux:text>
                        </div>

                        @if ($squadronLogsFailed)
                            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/40 dark:text-red-100" role="alert">
                                {{ $squadronLogsErrorMessage }}
                            </div>
                        @endif

                        <div class="mt-6 space-y-4">
                            @forelse ($squadronLogs as $squadronLog)
                                <article wire:key="squadron-log-{{ $loop->index }}" class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-900/40">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <flux:heading size="sm">{{ $squadronLog['action_label'] }}</flux:heading>
                                            @if ($squadronLog['actor'] !== null)
                                                <flux:text class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                                                    {{ $squadronLog['actor'] }}
                                                </flux:text>
                                            @endif
                                        </div>

                                        @if ($squadronLog['datetime'] !== null)
                                            <time datetime="{{ $squadronLog['datetime'] }}" class="shrink-0 text-sm text-neutral-500 dark:text-neutral-400">
                                                {{ $squadronLog['datetime'] }}
                                            </time>
                                        @endif
                                    </div>

                                    @foreach ($squadronLog['details'] as $squadronLogDetail)
                                        <flux:text class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">@if ($squadronLogDetail['label'] !== null){{ $squadronLogDetail['label'] }}: @endif@if ($squadronLogDetail['glyphs'])<span class="wt-glyphs">{{ $squadronLogDetail['value'] }}</span>@else{{ $squadronLogDetail['value'] }}@endif</flux:text>
                                    @endforeach
                                </article>
                            @empty
                                <div class="rounded-xl border border-dashed border-neutral-300 p-6 text-center text-sm text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">
                                    {{ __('No logs found.') }}
                                </div>
                            @endforelse
                        </div>

                        @if ($squadronLogsHasMore)
                            <div class="mt-6">
                                <flux:button type="button" variant="ghost" wire:click="loadMoreSquadronLogs" wire:loading.attr="disabled" wire:target="loadMoreSquadronLogs" class="w-full">
                                    {{ __('Show more') }}
                                </flux:button>
                            </div>
                        @endif
                    </section>
                @endif

                @if ($this->isSquadronApplicationsSection())
                    <section class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">
                        <div>
                            <flux:heading size="xl">{{ __('Applications') }}</flux:heading>
                            <flux:text>{{ __('Review and respond to squadron applications.') }}</flux:text>
                        </div>

                        @if ($squadronApplicantsFailed)
                            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/40 dark:text-red-100" role="alert">
                                {{ $squadronApplicantsErrorMessage }}
                            </div>
                        @elseif ($squadronApplicants === [])
                            <div class="mt-6 rounded-xl border border-dashed border-neutral-300 p-10 text-center text-sm text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">
                                {{ __('There are no applicants currently.') }}
                            </div>
                        @else
                            <div class="mt-6 grid gap-4 md:grid-cols-3">
                                @foreach ($squadronApplicants as $applicant)
                                    <button
                                        type="button"
                                        wire:key="applicant-{{ $applicant['uid'] }}"
                                        wire:click="openApplicantModal('{{ $applicant['uid'] }}')"
                                        class="flex cursor-pointer flex-col rounded-xl border border-neutral-200 bg-white p-4 text-left transition hover:border-neutral-300 hover:shadow-sm dark:border-neutral-700 dark:bg-neutral-900/40 dark:hover:border-neutral-600"
                                    >
                                        <div class="flex items-center justify-between gap-4">
                                            <div class="min-w-0 flex-1 rounded-lg bg-neutral-100 px-6 py-2 dark:bg-neutral-800">
                                                <flux:heading size="sm" class="truncate">{{ $applicant['nickname'] }}</flux:heading>
                                                <flux:text class="text-sm text-neutral-500 dark:text-neutral-400">#{{ $applicant['uid'] }}</flux:text>
                                            </div>

                                            @if ($applicant['timestamp'] !== null)
                                                <time datetime="{{ $applicant['timestamp'] }}" class="shrink-0 text-xs text-neutral-500 dark:text-neutral-400">
                                                    {{ $applicant['timestamp'] }}
                                                </time>
                                            @endif
                                        </div>

                                        @if ($applicant['country'] !== null || $applicant['timezone'] !== null)
                                            <div class="mt-auto pt-4 text-sm text-neutral-500 dark:text-neutral-400">
                                                {{ collect([$applicant['country'], $applicant['timezone']])->filter()->implode(' · ') }}
                                            </div>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    <flux:modal wire:model="showApplicantModal" class="w-[48rem] max-w-[calc(100vw-2rem)]">
                        @if (($applicant = $this->selectedApplicant()) !== null)
                            <div class="space-y-6">
                                <div>
                                    <flux:heading size="lg">{{ $applicant['nickname'] }}</flux:heading>
                                    <flux:subheading>#{{ $applicant['uid'] }}</flux:subheading>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <flux:text class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Country') }}</flux:text>
                                        <flux:text class="mt-1">{{ $applicant['country'] ?? '—' }}</flux:text>
                                    </div>

                                    <div>
                                        <flux:text class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Timezone') }}</flux:text>
                                        <flux:text class="mt-1">{{ $applicant['timezone'] ?? '—' }}</flux:text>
                                    </div>
                                </div>

                                @if ($applicant['timestamp'] !== null)
                                    <div>
                                        <flux:text class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Applied') }}</flux:text>
                                        <flux:text class="mt-1">{{ $applicant['timestamp'] }}</flux:text>
                                    </div>
                                @endif

                                <flux:textarea :label="__('Comment')" rows="5" readonly>{{ $applicant['comment'] }}</flux:textarea>

                                @if ($applicantActionError !== '')
                                    <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/40 dark:text-red-100" role="alert">
                                        {{ $applicantActionError }}
                                    </div>
                                @endif

                                <div class="flex flex-wrap items-center justify-end gap-3">
                                    <flux:modal.close>
                                        <flux:button type="button" variant="ghost" wire:click="closeApplicantModal">
                                            {{ __('Cancel') }}
                                        </flux:button>
                                    </flux:modal.close>

                                    <flux:button type="button" variant="danger" wire:click="openRejectApplicantModal">
                                        {{ __('Reject') }}
                                    </flux:button>

                                    <flux:button type="button" variant="primary" wire:click="acceptApplicant" wire:loading.attr="disabled" wire:target="acceptApplicant">
                                        {{ __('Accept') }}
                                    </flux:button>
                                </div>
                            </div>
                        @endif
                    </flux:modal>

                    <flux:modal wire:model="showRejectApplicantModal" class="max-w-md">
                        <form wire:submit="confirmRejectApplicant" class="space-y-6">
                            <div>
                                <flux:heading size="lg">{{ __('Reject Application') }}</flux:heading>
                                <flux:subheading>
                                    {{ __('Provide a reason for rejecting this application.') }}
                                </flux:subheading>
                            </div>

                            <flux:textarea
                                wire:model="rejectApplicantReason"
                                :label="__('Reason')"
                                :placeholder="__('Reason for rejection')"
                            />

                            @if ($applicantActionError !== '')
                                <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/40 dark:text-red-100" role="alert">
                                    {{ $applicantActionError }}
                                </div>
                            @endif

                            <div class="flex flex-wrap items-center justify-end gap-3">
                                <flux:modal.close>
                                    <flux:button type="button" variant="ghost" wire:click="cancelRejectApplicant">
                                        {{ __('Cancel') }}
                                    </flux:button>
                                </flux:modal.close>

                                <flux:button type="submit" variant="danger" wire:loading.attr="disabled" wire:target="confirmRejectApplicant">
                                    {{ __('Reject') }}
                                </flux:button>
                            </div>
                        </form>
                    </flux:modal>
                @endif

                @if ($this->isSquadronManagementSection())
                    <section class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">
                        <div>
                            <flux:heading size="xl">{{ __('Management') }}</flux:heading>
                            <flux:text>{{ __('Squadron management will appear here.') }}</flux:text>
                        </div>
                    </section>
                @endif
            @endif
        @endif

        @if ($this->isUsersSection())
            <flux:modal wire:model="showFilterModal" class="max-w-2xl">
                <form wire:submit="applyFilters" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Filter Users') }}</flux:heading>
                        <flux:subheading>
                            {{ __('Narrow results by status, timezone, and join date.') }}
                        </flux:subheading>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:select wire:model="filterForm.status" :label="__('Status')">
                            <option value="">{{ __('Any status') }}</option>
                            @foreach ($filterableStatuses as $status)
                                <option value="{{ $status }}">{{ $status }}</option>
                            @endforeach
                        </flux:select>

                        <flux:input
                            wire:model="filterForm.tz"
                            :label="__('Timezone UTC Offset (hours)')"
                            type="number"
                            inputmode="numeric"
                            min="-11"
                            max="12"
                            :placeholder="__('Any timezone')"
                        />
                    </div>

                    <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/40">
                        <flux:text class="font-medium text-neutral-800 dark:text-neutral-100">
                            {{ __('Join Date Range') }}
                        </flux:text>

                        <div class="mt-3 grid gap-4 md:grid-cols-2">
                            <flux:input
                                wire:model="filterForm.joined_after"
                                :label="__('Joined On/After')"
                                type="date"
                            />

                            <flux:input
                                wire:model="filterForm.joined_before"
                                :label="__('Joined On/Before')"
                                type="date"
                            />
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <flux:text class="text-sm text-neutral-500 dark:text-neutral-400">
                            @if ($activeFilterCount === 0)
                                {{ __('No active filters') }}
                            @elseif ($activeFilterCount === 1)
                                {{ __('1 active filter') }}
                            @else
                                {{ __(':count active filters', ['count' => $activeFilterCount]) }}
                            @endif
                        </flux:text>

                        <div class="flex flex-wrap items-center justify-end gap-3">
                            <flux:button type="button" variant="ghost" wire:click="resetFilters">
                                {{ __('Clear All') }}
                            </flux:button>

                            <flux:modal.close>
                                <flux:button type="button" variant="ghost" wire:click="closeFilterModal">
                                    {{ __('Cancel') }}
                                </flux:button>
                            </flux:modal.close>

                            <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="applyFilters">
                                {{ __('Apply Filters') }}
                            </flux:button>
                        </div>
                    </div>
                </form>
            </flux:modal>

            @if ($this->canEdit())
                <flux:modal wire:model="showEditModal" class="max-w-3xl">
                    @if ($selectedGaijinId !== null)
                        <form wire:submit="save" class="space-y-6">
                            <div>
                                <flux:heading size="lg">{{ __('Edit User') }}</flux:heading>
                                <flux:subheading>
                                    {{ __('Update the selected peck_users record.') }}
                                </flux:subheading>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <flux:input
                                    wire:model="form.gaijin_id"
                                    :label="__('Gaijin ID')"
                                    type="number"
                                    inputmode="numeric"
                                    required
                                    readonly
                                />

                                <flux:input
                                    wire:model="form.username"
                                    :label="__('Username')"
                                    type="text"
                                    required
                                />

                                <flux:select wire:model="form.status" :label="__('Status')" required>
                                    @foreach ($editableStatuses as $status)
                                        <option value="{{ $status }}">{{ $status }}</option>
                                    @endforeach
                                </flux:select>

                                <flux:input
                                    wire:model="form.discord_id"
                                    :label="__('Discord ID')"
                                    type="number"
                                    inputmode="numeric"
                                />

                                <flux:input
                                    wire:model="form.tz"
                                    :label="__('Timezone UTC Offset (hours)')"
                                    type="number"
                                    inputmode="numeric"
                                />

                                <flux:input
                                    wire:model="form.joindate"
                                    :label="__('Join Date')"
                                    type="date"
                                />

                                <flux:select wire:model="form.initiator" :label="__('Initiator Officer')">
                                    <option value="">{{ __('No initiator officer') }}</option>
                                    @foreach ($initiatorOptions as $initiatorOption)
                                        <option value="{{ $initiatorOption->gaijin_id }}">
                                            {{ $initiatorOption->peckUser?->username ?? $initiatorOption->gaijin_id }}
                                            @if ($initiatorOption->rank)
                                                ({{ $initiatorOption->rank }})
                                            @endif
                                        </option>
                                    @endforeach
                                </flux:select>

                                <div class="flex w-full">
                                    <label class="mt-1 flex flex-1 items-center justify-center gap-2">
                                        <flux:checkbox wire:model="form.sqb_part" />
                                        <span class="text-sm">{{ __('Expected SQB Participation') }}</span>
                                    </label>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <x-action-message on="peck-user-saved" class="text-sm text-green-600 dark:text-green-400">
                                    {{ __('Saved.') }}
                                </x-action-message>

                                <div class="ml-auto flex flex-wrap items-center gap-3">
                                    <flux:modal.close>
                                        <flux:button type="button" variant="ghost" wire:click="clearSelection">
                                            {{ __('Cancel') }}
                                        </flux:button>
                                    </flux:modal.close>

                                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save">
                                        {{ __('Save Changes') }}
                                    </flux:button>
                                </div>
                            </div>
                        </form>
                    @endif
                </flux:modal>

                <flux:modal wire:model="showCreateUserModal" class="max-w-3xl">
                    <form wire:submit="createUser" class="space-y-6">
                        <div>
                            <flux:heading size="lg">{{ __('Add User') }}</flux:heading>
                            <flux:subheading>
                                {{ __('Create a new peck_users record.') }}
                            </flux:subheading>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <flux:input
                                wire:model="newUserForm.gaijin_id"
                                :label="__('Gaijin ID')"
                                type="number"
                                inputmode="numeric"
                                required
                            />

                            <flux:input
                                wire:model="newUserForm.username"
                                :label="__('Username')"
                                type="text"
                                required
                            />

                            <flux:select wire:model="newUserForm.status" :label="__('Status')" required>
                                @foreach ($editableStatuses as $status)
                                    <option value="{{ $status }}">{{ $status }}</option>
                                @endforeach
                            </flux:select>

                            <flux:input
                                wire:model="newUserForm.discord_id"
                                :label="__('Discord ID')"
                                type="number"
                                inputmode="numeric"
                            />

                            <flux:input
                                wire:model="newUserForm.tz"
                                :label="__('Timezone UTC Offset (hours)')"
                                type="number"
                                inputmode="numeric"
                            />

                            <flux:input
                                wire:model="newUserForm.joindate"
                                :label="__('Join Date')"
                                type="date"
                            />

                            <flux:select wire:model="newUserForm.initiator" :label="__('Initiator Officer')">
                                <option value="">{{ __('No initiator officer') }}</option>
                                @foreach ($initiatorOptions as $initiatorOption)
                                    <option value="{{ $initiatorOption->gaijin_id }}">
                                        {{ $initiatorOption->peckUser?->username ?? $initiatorOption->gaijin_id }}
                                        @if ($initiatorOption->rank)
                                            ({{ $initiatorOption->rank }})
                                        @endif
                                    </option>
                                @endforeach
                            </flux:select>

                            <div class="w-full">
                                <flux:label>{{ __('SQB Participation') }}</flux:label>
                                <label class="mt-1 flex items-center gap-2">
                                    <flux:checkbox wire:model="newUserForm.sqb_part" />
                                    <span class="text-sm">{{ __('Expected Participation') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-3">
                            <flux:modal.close>
                                <flux:button type="button" variant="ghost" wire:click="closeCreateUserModal">
                                    {{ __('Cancel') }}
                                </flux:button>
                            </flux:modal.close>

                            <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createUser">
                                {{ __('Create User') }}
                            </flux:button>

                            <x-action-message on="peck-user-created">
                                {{ __('Created.') }}
                            </x-action-message>
                        </div>
                    </form>
                </flux:modal>
            @endif
        @endif

        @if ($this->canEdit() && $this->isAltsSection())
            <flux:modal wire:model="showMasterEditModal" class="max-w-3xl">
                <form wire:submit="saveMasterAssignment" class="space-y-6">
                    <div>
                        <flux:heading size="lg">
                            {{ $editingMasterGaijinId === null ? __('Add Master Account') : __('Edit Master Account') }}
                        </flux:heading>
                        <flux:subheading>
                            {{ __('Assign slave accounts and save the relationship set.') }}
                        </flux:subheading>
                    </div>

                    <flux:select wire:model.live="altFormMasterGaijinId" :label="__('Master account')" required>
                        <option value="">{{ __('Select a master account') }}</option>
                        @foreach ($this->availableMasterUsers() as $availableMasterUser)
                            <option value="{{ $availableMasterUser->gaijin_id }}">
                                {{ $availableMasterUser->username }} ({{ $availableMasterUser->gaijin_id }})
                            </option>
                        @endforeach
                    </flux:select>

                    <div class="space-y-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                        <flux:heading size="sm">{{ __('Slave Accounts') }}</flux:heading>

                        <div class="space-y-2">
                            @forelse ($editingMasterSlaveUsers as $editingMasterSlaveUser)
                                <div wire:key="editing-master-slave-{{ $editingMasterSlaveUser->gaijin_id }}" class="group flex items-center justify-between rounded-lg border border-neutral-200 px-3 py-2 dark:border-neutral-700">
                                    <div class="flex items-center gap-4">
                                        <button
                                            type="button"
                                            wire:click="removeSlaveFromMaster({{ $editingMasterSlaveUser->gaijin_id }})"
                                            class="rounded-md p-2 text-neutral-400 opacity-0 transition hover:bg-red-50 hover:text-red-600 focus-visible:opacity-100 group-hover:opacity-100 dark:hover:bg-red-900/20 dark:hover:text-red-300"
                                            aria-label="{{ __('Remove slave account') }}"
                                        >
                                            <flux:icon.trash class="size-4" />
                                        </button>

                                        <div>
                                            <flux:text class="font-medium">{{ $editingMasterSlaveUser->username }}</flux:text>
                                            <flux:text class="text-xs text-neutral-500 dark:text-neutral-400">{{ $editingMasterSlaveUser->gaijin_id }}</flux:text>
                                        </div>
                                    </div>

                                    <flux:button
                                        type="button"
                                        variant="ghost"
                                        wire:click="setMasterFromSlave({{ $editingMasterSlaveUser->gaijin_id }})"
                                        class="text-xs"
                                    >
                                        {{ __('Set Master') }}
                                    </flux:button>
                                </div>
                            @empty
                                <div class="rounded-lg border border-dashed border-neutral-300 px-3 py-4 text-center text-sm text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">
                                    {{ __('No slave accounts selected.') }}
                                </div>
                            @endforelse
                        </div>

                        <flux:button type="button" variant="ghost" wire:click="openAddSlaveModal" class="w-full justify-center rounded-lg border border-dashed border-neutral-300 dark:border-neutral-700">
                            {{ __('+ Add') }}
                        </flux:button>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <flux:modal.close>
                            <flux:button type="button" variant="ghost" wire:click="closeMasterEditModal">
                                {{ __('Cancel') }}
                            </flux:button>
                        </flux:modal.close>

                        <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="saveMasterAssignment">
                            {{ __('Save') }}
                        </flux:button>
                    </div>
                </form>
            </flux:modal>

            <flux:modal wire:model="showAddSlaveModal" class="max-w-xl">
                <form wire:submit="addSlaveToMaster" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Add Slave Account') }}</flux:heading>
                        <flux:subheading>
                            {{ __('Select a user to assign under the selected master account.') }}
                        </flux:subheading>
                    </div>

                    <flux:select wire:model="newSlaveGaijinId" :label="__('Slave account')" required>
                        <option value="">{{ __('Select a slave account') }}</option>
                        @foreach ($this->availableSlaveUsers() as $availableSlaveUser)
                            <option value="{{ $availableSlaveUser->gaijin_id }}">
                                {{ $availableSlaveUser->username }} ({{ $availableSlaveUser->gaijin_id }})
                            </option>
                        @endforeach
                    </flux:select>

                    <div class="flex items-center justify-end gap-3">
                        <flux:modal.close>
                            <flux:button type="button" variant="ghost" wire:click="closeAddSlaveModal">
                                {{ __('Cancel') }}
                            </flux:button>
                        </flux:modal.close>

                        <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="addSlaveToMaster">
                            {{ __('Add') }}
                        </flux:button>
                    </div>
                </form>
            </flux:modal>
        @endif

        @if ($this->isContextSection())
            <flux:modal wire:model="showContextModal" class="max-w-3xl">
                @if ($selectedContextGaijinId !== null)
                    <div class="space-y-6">
                        <div>
                            <flux:heading size="lg">{{ __('Edit user contexts') }}</flux:heading>
                            <flux:subheading>
                                {{ $selectedContextUsername }} ({{ $selectedContextGaijinId }})
                            </flux:subheading>
                        </div>

                        <flux:field variant="inline">
                            <flux:checkbox wire:model.live="contextShowExpiredAbsences" />
                            <flux:label>{{ __('Show old absence entries') }}</flux:label>
                        </flux:field>

                        <div class="space-y-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                            <div class="space-y-2">
                                @forelse ($selectedContextEntries as $selectedContextEntry)
                                    <div wire:key="selected-context-entry-{{ $selectedContextEntry->context_id }}" class="group flex items-center gap-3 rounded-lg border border-neutral-200 px-3 py-2 dark:border-neutral-700">
                                        @if ($this->canEdit())
                                            <button
                                                type="button"
                                                wire:click="removeContext({{ $selectedContextEntry->context_id }})"
                                                class="rounded-md p-2 text-neutral-400 opacity-0 transition hover:bg-red-50 hover:text-red-600 focus-visible:opacity-100 group-hover:opacity-100 dark:hover:bg-red-900/20 dark:hover:text-red-300"
                                                aria-label="{{ __('Remove context') }}"
                                            >
                                                <flux:icon.trash class="size-4" />
                                            </button>
                                        @endif

                                        <flux:text class="min-w-0 flex-1 truncate">
                                            {{ $this->contextDisplayText($selectedContextEntry) }}
                                        </flux:text>
                                    </div>
                                @empty
                                    <div class="rounded-lg border border-dashed border-neutral-300 px-3 py-4 text-center text-sm text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">
                                        {{ __('No contexts shown.') }}
                                    </div>
                                @endforelse
                            </div>

                            @if ($this->canEdit() && ! $showAddContextForm)
                                <flux:button type="button" variant="ghost" wire:click="openAddContextForm" class="w-full justify-center rounded-lg border border-dashed border-neutral-300 dark:border-neutral-700">
                                    {{ __('+ Add') }}
                                </flux:button>
                            @endif
                        </div>

                        @if ($this->canEdit() && $showAddContextForm)
                            <form wire:submit="addContext" class="space-y-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                                <flux:heading size="sm">{{ __('Add Context') }}</flux:heading>

                                <flux:select wire:model.live="contextForm.type" :label="__('Type')" required>
                                    <option value="{{ \App\Models\PeckUserContext::TYPE_MISC }}">{{ __('Miscellaneous') }}</option>
                                    <option value="{{ \App\Models\PeckUserContext::TYPE_ONCE_ABSENCE }}">{{ __('One-time absence') }}</option>
                                    <option value="{{ \App\Models\PeckUserContext::TYPE_RECURRING_ABSENCE }}">{{ __('Recurring absence') }}</option>
                                </flux:select>

                                @if ($contextForm['type'] === \App\Models\PeckUserContext::TYPE_MISC)
                                    <flux:textarea
                                        wire:model="contextForm.comment"
                                        :label="__('Comment')"
                                        required
                                    />
                                @endif

                                @if ($contextForm['type'] === \App\Models\PeckUserContext::TYPE_ONCE_ABSENCE)
                                    <div class="grid gap-4 md:grid-cols-2">
                                        <flux:input
                                            wire:model="contextForm.from"
                                            :label="__('From')"
                                            type="date"
                                            required
                                        />

                                        <flux:input
                                            wire:model="contextForm.to"
                                            :label="__('To')"
                                            type="date"
                                            required
                                        />
                                    </div>
                                @endif

                                @if ($contextForm['type'] === \App\Models\PeckUserContext::TYPE_RECURRING_ABSENCE)
                                    <div class="space-y-4">
                                        <div>
                                            <flux:label>{{ __('Weekdays') }}</flux:label>
                                            <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                                @foreach ([0 => 'Mon', 1 => 'Tue', 2 => 'Wed', 3 => 'Thu', 4 => 'Fri', 5 => 'Sat', 6 => 'Sun'] as $weekdayValue => $weekdayLabel)
                                                    <label class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-sm dark:border-neutral-700">
                                                        <input type="checkbox" wire:model="contextForm.weekdays" value="{{ $weekdayValue }}" class="rounded border-neutral-300 text-blue-600 focus:ring-blue-500 dark:border-neutral-600 dark:bg-neutral-900" />
                                                        <span>{{ $weekdayLabel }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                            <flux:error name="contextForm.weekdays" />
                                        </div>

                                        <flux:input
                                            wire:model="contextForm.monthDay"
                                            :label="__('Month day')"
                                            type="number"
                                            inputmode="numeric"
                                            min="1"
                                            max="31"
                                        />
                                    </div>
                                @endif

                                <div class="flex flex-wrap items-center gap-3">
                                    <x-action-message on="peck-context-added" class="text-sm text-green-600 dark:text-green-400">
                                        {{ __('Added.') }}
                                    </x-action-message>

                                    <div class="ml-auto flex items-center gap-3">
                                        <flux:button type="button" variant="ghost" wire:click="closeAddContextForm">
                                            {{ __('Cancel') }}
                                        </flux:button>

                                        <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="addContext">
                                            {{ __('Add') }}
                                        </flux:button>
                                    </div>
                                </div>
                            </form>
                        @endif

                        <div class="flex items-center justify-end gap-3">
                            <flux:modal.close>
                                <flux:button type="button" variant="ghost" wire:click="closeContextModal">
                                    {{ __('Close') }}
                                </flux:button>
                            </flux:modal.close>
                        </div>
                    </div>
                @endif
            </flux:modal>
        @endif

        @if ($this->canEdit())
            <flux:modal wire:model="showLeaveInfoModal" class="max-w-xl">
                @if ($selectedLeaveInfoGaijinId !== null)
                    <form wire:submit="saveLeaveInfo" class="space-y-6">
                        <div>
                            <flux:heading size="lg">
                                {{ $leaveInfoModalFromStatusChange ? __('Set Leave Info') : __('Edit Leave Info') }}
                            </flux:heading>
                            <flux:subheading>
                                {{ __('Gaijin ID: :gaijinId, User: :username', ['gaijinId' => $selectedLeaveInfoGaijinId, 'username' => $selectedLeaveInfoUsername ?? '—']) }}
                            </flux:subheading>
                        </div>

                        <div class="rounded-xl border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-800 dark:bg-blue-950/20">
                            <flux:heading size="sm">{{ __('Selected User Details') }}</flux:heading>
                            <flux:subheading>{{ __('Read-only snapshot') }}</flux:subheading>

                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                <flux:input
                                    :label="__('Gaijin ID')"
                                    :value="$selectedLeaveInfoUserDetails['gaijin_id']"
                                    readonly
                                />
                                <flux:input
                                    :label="__('Username')"
                                    :value="$selectedLeaveInfoUserDetails['username']"
                                    readonly
                                />
                                <flux:input
                                    :label="__('Status')"
                                    :value="$selectedLeaveInfoUserDetails['status']"
                                    readonly
                                />
                                <flux:input
                                    :label="__('Discord ID')"
                                    :value="$selectedLeaveInfoUserDetails['discord_id']"
                                    readonly
                                />
                                <flux:input
                                    :label="__('Join Date')"
                                    :value="$selectedLeaveInfoUserDetails['joindate']"
                                    readonly
                                />
                                <flux:input
                                    :label="__('Current Leave Info')"
                                    :value="$selectedLeaveInfoUserDetails['current_leave_info']"
                                    readonly
                                />
                            </div>
                        </div>

                        <flux:select wire:model="leaveInfoForm.type" :label="__('Leave Type')" required>
                            @foreach ($leaveInfoTypes as $leaveInfoType)
                                <option value="{{ $leaveInfoType }}">{{ $leaveInfoType }}</option>
                            @endforeach
                        </flux:select>

                        <div class="flex flex-wrap items-center gap-3">
                            <x-action-message on="peck-leave-info-saved" class="text-sm text-green-600 dark:text-green-400">
                                {{ __('Saved.') }}
                            </x-action-message>

                            <div class="ml-auto flex flex-wrap items-center gap-3">
                                <flux:modal.close>
                                    <flux:button type="button" variant="ghost" wire:click="closeLeaveInfoModal">
                                        {{ __('Cancel') }}
                                    </flux:button>
                                </flux:modal.close>

                                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="saveLeaveInfo">
                                    {{ __('Save Leave Info') }}
                                </flux:button>
                            </div>
                        </div>
                    </form>
                @endif
            </flux:modal>
        @endif

        @if ($showAltSaveError)
            <div
                x-data
                x-init="setTimeout(() => $wire.dismissAltSaveError(), 3500)"
                class="fixed bottom-4 left-1/2 z-50 -translate-x-1/2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 shadow-lg dark:border-red-800 dark:bg-red-900/40 dark:text-red-100"
                role="status"
            >
                {{ $altSaveErrorMessage }}
            </div>
        @endif
    </div>
</div>
