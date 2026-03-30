<div>
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
                                    <td colspan="{{ $this->canEdit() ? 8 : 7 }}" class="px-3 py-4 text-center text-neutral-500 dark:text-neutral-400">
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
    </div>
</div>
