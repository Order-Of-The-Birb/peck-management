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
        @if ($this->isMembersSection())
            <section class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">
                <div class="flex w-full flex-row items-end gap-5">
                    <div>
                        <flux:heading size="xl">{{ __('Members') }}</flux:heading>
                        <flux:text>{{ __('View and manage member records') }}</flux:text>
                    </div>

                    <div class="ml-auto w-xl">
                        <flux:input
                            wire:model.live.debounce.300ms="search"
                            :placeholder="__('Search by Gaijin ID or Discord ID')"
                        />
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
                                <th class="px-3 py-2">{{ __('Username') }}</th>
                                <th class="px-3 py-2">
                                    <button type="button" wire:click="sort('discord_id')" class="inline-flex items-center gap-1 hover:text-neutral-800 dark:hover:text-neutral-100">
                                        {{ __('Discord ID') }}
                                        @if ($this->isSortedBy('discord_id'))
                                            <span class="text-[10px]">{{ strtoupper($sortDirection) }}</span>
                                        @endif
                                    </button>
                                </th>
                                <th class="px-3 py-2">{{ __('Status') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            @forelse ($shownUsers as $peckUser)
                                <tr wire:key="member-{{ $peckUser->gaijin_id }}">
                                    <td class="px-3 py-2 font-medium">{{ $peckUser->gaijin_id }}</td>
                                    <td class="px-3 py-2">{{ $usernames[$peckUser->gaijin_id] ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $peckUser->discord_id ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $memberStatuses[$peckUser->gaijin_id] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <flux:button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            wire:click="openMemberModal({{ $peckUser->gaijin_id }})"
                                            class="px-3! py-1! text-xs"
                                        >
                                            {{ __('View') }}
                                        </flux:button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-4 text-center text-neutral-500 dark:text-neutral-400">
                                        {{ __('No members found.') }}
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
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 p-6 backdrop-blur-sm" wire:click.self="dismissThunderPrompt">
                    <div class="relative w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-6 text-center shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="absolute top-3 right-3">
                            <flux:button
                                type="button"
                                variant="ghost"
                                icon="x-mark"
                                wire:click="dismissThunderPrompt"
                                :aria-label="__('Dismiss')"
                            />
                        </div>
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

        @if ($this->isMembersSection())
            <flux:modal wire:model="showMemberModal" class="w-[70rem] max-w-[calc(100vw-2rem)]">
                @if ($selectedMember !== null)
                    <div class="space-y-6">
                        <div>
                            <flux:heading size="lg">{{ $memberUsername ?? $selectedMember->gaijin_id }}</flux:heading>
                            <flux:subheading>#{{ $selectedMember->gaijin_id }}</flux:subheading>
                        </div>

                        <div class="flex items-center gap-1 border-b border-neutral-200 dark:border-neutral-700">
                            @foreach ([
                                'member' => __('Member'),
                                'context' => __('Context'),
                                'manage' => __('Manage'),
                            ] as $tabKey => $tabLabel)
                                @if ($tabKey !== 'manage' || $this->canEdit())
                                    <button
                                        type="button"
                                        wire:click="selectMemberTab('{{ $tabKey }}')"
                                        @class([
                                            'px-4 py-2 text-sm font-medium transition border-b-2 -mb-px',
                                            'border-blue-500 text-blue-600 dark:border-blue-400 dark:text-blue-300' => $memberTab === $tabKey,
                                            'border-transparent text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-100' => $memberTab !== $tabKey,
                                        ])
                                    >
                                        {{ $tabLabel }}
                                    </button>
                                @endif
                            @endforeach
                        </div>

                        @if ($memberTab === 'member')
                            <div class="space-y-4">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <flux:input
                                        :label="__('Gaijin ID')"
                                        :value="$selectedMember->gaijin_id"
                                        readonly
                                    />

                                    <flux:input
                                        :label="__('Username')"
                                        :value="$memberUsername ?? '—'"
                                        readonly
                                    />

                                    <flux:input
                                        :label="__('Status')"
                                        :value="$memberStatuses[$selectedMember->gaijin_id] ?? '—'"
                                        readonly
                                    />

                                    <flux:input
                                        wire:model="memberForm.discord_id"
                                        :label="__('Discord ID')"
                                        type="number"
                                        inputmode="numeric"
                                        :readonly="! $memberEditMode"
                                    />

                                    <flux:input
                                        wire:model="memberForm.tz"
                                        :label="__('Timezone UTC Offset (hours)')"
                                        type="number"
                                        inputmode="numeric"
                                        :readonly="! $memberEditMode"
                                    />

                                    <div class="flex w-full items-center">
                                        <label class="mt-1 flex flex-1 items-center gap-2">
                                            <flux:checkbox wire:model="memberForm.sqb_part" :disabled="! $memberEditMode" />
                                            <span class="text-sm">{{ __('Expected SQB Participation') }}</span>
                                        </label>
                                    </div>
                                </div>

                                @if ($memberEditMode)
                                    <div class="space-y-2">
                                        <flux:input
                                            wire:model.live="ownerSearch"
                                            :label="__('Search owner by username or Gaijin ID')"
                                            :placeholder="__('Type to search…')"
                                        />

                                        <flux:select wire:model="memberForm.owner" :label="__('Owner')">
                                            <option value="">{{ __('—') }}</option>
                                            @foreach ($ownerOptions as $ownerOption)
                                                <option value="{{ $ownerOption['gaijin_id'] }}">
                                                    {{ $ownerOption['username'] ?? $ownerOption['gaijin_id'] }} ({{ $ownerOption['gaijin_id'] }})
                                                </option>
                                            @endforeach
                                        </flux:select>
                                    </div>
                                @else
                                    <flux:input
                                        :label="__('Owner')"
                                        :value="$memberOwnerGaijinId !== null ? (($usernames[$memberOwnerGaijinId] ?? $memberOwnerGaijinId) . ' (' . $memberOwnerGaijinId . ')') : '—'"
                                        readonly
                                    />
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-3 border-t border-neutral-200 pt-4 dark:border-neutral-700">
                                @if ($memberEditMode)
                                    <div class="ml-auto flex items-center gap-3">
                                        <flux:button type="button" variant="outline" wire:click="cancelMemberEdit">
                                            {{ __('Cancel') }}
                                        </flux:button>

                                        <flux:button type="button" variant="filled" wire:click="saveMember" wire:loading.attr="disabled" wire:target="saveMember">
                                            {{ __('Save') }}
                                        </flux:button>
                                    </div>
                                @elseif ($this->canEdit())
                                    <div class="ml-auto flex items-center gap-3">
                                        <flux:button type="button" variant="primary" wire:click="enterMemberEditMode">
                                            {{ __('Edit') }}
                                        </flux:button>
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if ($memberTab === 'context')
                            <div class="space-y-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                                <div class="space-y-2">
                                    @forelse ($selectedMemberContexts as $contextEntry)
                                        <div wire:key="member-context-entry-{{ $contextEntry->context_id }}" class="group flex items-center gap-3 rounded-lg border border-neutral-200 px-3 py-2 dark:border-neutral-700">
                                            @if ($this->canEdit())
                                                <button
                                                    type="button"
                                                    wire:click="removeContext({{ $contextEntry->context_id }})"
                                                    class="rounded-md p-2 text-neutral-400 opacity-0 transition hover:bg-red-50 hover:text-red-600 focus-visible:opacity-100 group-hover:opacity-100 dark:hover:bg-red-900/20 dark:hover:text-red-300"
                                                    aria-label="{{ __('Remove context') }}"
                                                >
                                                    <flux:icon.trash class="size-4" />
                                                </button>
                                            @endif

                                            <flux:text class="min-w-0 flex-1 truncate">
                                                <span class="font-medium">{{ $this->contextEntryTypeLabel($contextEntry) }}</span>
                                                <span class="mx-1 text-neutral-400">|</span>
                                                <span>{{ $this->contextEntrySummary($contextEntry) }}</span>
                                            </flux:text>

                                            @if ($contextEntry->type === \App\Models\PeckUserContext::TYPE_MISC)
                                                <flux:button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    wire:click="openContextEntryModal({{ $contextEntry->context_id }})"
                                                    class="shrink-0"
                                                >
                                                    {{ __('View') }}
                                                </flux:button>
                                            @endif
                                        </div>
                                    @empty
                                        <div class="rounded-lg border border-dashed border-neutral-300 px-3 py-4 text-center text-sm text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">
                                            {{ __('No contexts.') }}
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
                        @endif

                        @if ($memberTab === 'manage' && $this->canEdit())
                            <div class="space-y-6">
                                @if ($manageActionError !== '')
                                    <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/40 dark:text-red-100" role="alert">
                                        {{ $manageActionError }}
                                    </div>
                                @endif

                                <div class="flex flex-wrap items-center gap-3">
                                    <flux:button type="button" variant="danger" wire:click="openKickConfirmModal">
                                        {{ __('Kick user') }}
                                    </flux:button>

                                    @if ($this->canChangeRoles())
                                        <div class="flex items-center gap-2">
                                            <flux:select wire:model="manageRole" :label="__('Change role')" class="min-w-40">
                                                <option value="">{{ __('Select role') }}</option>
                                                @foreach ($assignableRoles as $assignableRole)
                                                    <option value="{{ $assignableRole }}">{{ $assignableRole }}</option>
                                                @endforeach
                                            </flux:select>

                                            <flux:button
                                                type="button"
                                                variant="primary"
                                                wire:click="changeMemberRole"
                                                wire:loading.attr="disabled"
                                                wire:target="changeMemberRole"
                                                :disabled="! $manageRole"
                                            >
                                                {{ __('Apply') }}
                                            </flux:button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </flux:modal>

            <flux:modal wire:model="showKickConfirmModal" class="max-w-md">
                @if ($selectedMember !== null)
                    <div class="space-y-6">
                        <div>
                            <flux:heading size="lg">{{ __('Kick user') }}</flux:heading>
                            <flux:subheading>
                                {{ __('Are you sure you want to kick #:gaijinId?', ['gaijinId' => $selectedMember->gaijin_id]) }}
                            </flux:subheading>
                        </div>

                        <flux:textarea
                            wire:model="kickReason"
                            :label="__('Reason (optional)')"
                            :placeholder="__('Reason for kicking')"
                        />

                        @if ($manageActionError !== '')
                            <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/40 dark:text-red-100" role="alert">
                                {{ $manageActionError }}
                            </div>
                        @endif

                        <div class="flex flex-wrap items-center justify-end gap-3">
                            <flux:modal.close>
                                <flux:button type="button" variant="ghost" wire:click="cancelKick">
                                    {{ __('Cancel') }}
                                </flux:button>
                            </flux:modal.close>

                            <flux:button type="button" variant="danger" wire:click="kickMember" wire:loading.attr="disabled" wire:target="kickMember">
                                {{ __('Kick') }}
                            </flux:button>
                        </div>
                    </div>
                @endif
            </flux:modal>

            <flux:modal wire:model="showContextEntryModal" class="max-w-2xl">
                @if ($selectedContextEntry !== null)
                    <div class="space-y-6">
                        <div>
                            <flux:heading size="lg">{{ __('Context') }}</flux:heading>
                            <flux:subheading>{{ $this->contextEntryTypeLabel($selectedContextEntry) }}</flux:subheading>
                        </div>

                        @if ($contextEntryEditMode)
                            <flux:textarea wire:model="contextEntryComment" :label="__('Comment')" rows="6" />

                            <div class="flex items-center justify-end gap-3">
                                <flux:button type="button" variant="outline" wire:click="cancelContextEntryEdit">
                                    {{ __('Cancel') }}
                                </flux:button>

                                <flux:button type="button" variant="filled" wire:click="saveContextEntry" wire:loading.attr="disabled" wire:target="saveContextEntry">
                                    {{ __('Save') }}
                                </flux:button>
                            </div>
                        @else
                            <flux:textarea :value="$selectedContextEntry->comment" rows="6" readonly />

                            <div class="flex items-center justify-end gap-3">
                                <flux:modal.close>
                                    <flux:button type="button" variant="ghost" wire:click="closeContextEntryModal">
                                        {{ __('Close') }}
                                    </flux:button>
                                </flux:modal.close>

                                @if ($this->canEdit())
                                    <flux:button type="button" variant="primary" wire:click="enterContextEntryEditMode">
                                        {{ __('Edit') }}
                                    </flux:button>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif
            </flux:modal>
        @endif

        @if ($thunderApiError)
            <div
                class="fixed bottom-4 left-1/2 z-50 flex w-full max-w-lg -translate-x-1/2 items-start justify-between gap-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-lg dark:border-red-800 dark:bg-red-900/40 dark:text-red-100"
                role="alert"
            >
                <div class="min-w-0">
                    <flux:heading size="sm">{{ __('ThunderAPI error') }}</flux:heading>
                    <flux:text class="mt-1 text-sm">
                        {{ __('An error occurred with ThunderAPI. Please contact an administrator.') }}
                    </flux:text>
                </div>

                <flux:button
                    type="button"
                    variant="ghost"
                    icon="x-mark"
                    size="sm"
                    wire:click="dismissThunderApiError"
                    :aria-label="__('Dismiss')"
                    class="shrink-0"
                />
            </div>
        @endif
    </div>
</div>
