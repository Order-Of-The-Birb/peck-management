<div>
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <section class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-end">
                <div>
                    <flux:heading size="xl">{{ __('Users') }}</flux:heading>
                    <flux:text>{{ __('View and edit records') }}</flux:text>
                </div>

                <div class="ml-auto flex flex-row flex-wrap items-end justify-end gap-3">
                    @if ($this->canEdit())
                        <flux:button type="button" variant="primary" wire:click="openCreateUserModal" class="w-auto shrink-0">
                            {{ __('Add User') }}
                        </flux:button>
                    @endif

                    <div class="w-[42rem] max-w-full shrink-0">
                        <flux:input
                            wire:model.live.debounce.300ms="search"
                            :label="__('Search')"
                            :placeholder="__('Gaijin ID, username, status, or Discord ID')"
                        />
                    </div>
                </div>
            </div>

            @if ($unverifiedOnly)
                <div class="mt-4">
                    <flux:text>{{ __('Showing unverified users only.') }}</flux:text>
                </div>
            @endif

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
                            <th class="px-3 py-2">
                                <button type="button" wire:click="sort('discord_id')" class="inline-flex items-center gap-1 hover:text-neutral-800 dark:hover:text-neutral-100">
                                    {{ __('Discord ID') }}
                                    @if ($this->isSortedBy('discord_id'))
                                        <span class="text-[10px]">{{ strtoupper($sortDirection) }}</span>
                                    @endif
                                </button>
                            </th>
                            <th class="px-3 py-2">
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
                        @forelse ($peckUsers as $peckUser)
                            <tr wire:key="peck-user-{{ $peckUser->gaijin_id }}" @class([
                                'bg-blue-50/70 dark:bg-blue-900/20' => $selectedGaijinId === $peckUser->gaijin_id,
                            ])>
                                <td class="px-3 py-2 font-medium">{{ $peckUser->gaijin_id }}</td>
                                <td class="px-3 py-2">{{ $peckUser->username }}</td>
                                <td class="px-3 py-2">{{ $peckUser->status }}</td>
                                <td class="px-3 py-2">{{ $peckUser->discord_id ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $peckUser->tz ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $peckUser->joindate?->format('Y-m-d H:i') ?? '—' }}</td>
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
                {{ $peckUsers->links() }}
            </div>
        </section>

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
                                type="datetime-local"
                            />

                            <flux:select wire:model="form.initiator" :label="__('Initiator')">
                                <option value="">{{ __('No initiator') }}</option>
                                @foreach ($initiatorOptions as $initiatorOption)
                                    <option value="{{ $initiatorOption->gaijin_id }}">
                                        {{ $initiatorOption->username }} ({{ $initiatorOption->gaijin_id }})
                                    </option>
                                @endforeach
                            </flux:select>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-3">
                            <flux:modal.close>
                                <flux:button type="button" variant="ghost" wire:click="clearSelection">
                                    {{ __('Cancel') }}
                                </flux:button>
                            </flux:modal.close>

                            <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save">
                                {{ __('Save Changes') }}
                            </flux:button>

                            <x-action-message on="peck-user-saved">
                                {{ __('Saved.') }}
                            </x-action-message>
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
                            type="datetime-local"
                        />

                        <flux:select wire:model="newUserForm.initiator" :label="__('Initiator')">
                            <option value="">{{ __('No initiator') }}</option>
                            @foreach ($initiatorOptions as $initiatorOption)
                                <option value="{{ $initiatorOption->gaijin_id }}">
                                    {{ $initiatorOption->username }} ({{ $initiatorOption->gaijin_id }})
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
    </div>
</div>
