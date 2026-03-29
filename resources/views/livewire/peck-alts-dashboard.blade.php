<div>
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <section class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <flux:heading size="xl">{{ __('Alt Accounts') }}</flux:heading>
                    <flux:text>{{ __('View and edit records') }}</flux:text>
                </div>

                <div class="flex w-full flex-col gap-3 md:w-auto md:flex-row md:items-end">
                    @if ($this->canEdit())
                        <flux:button type="button" variant="primary" wire:click="openCreateModal">
                            {{ __('Add Alt Mapping') }}
                        </flux:button>
                    @endif

                    <div class="w-full md:w-80">
                        <flux:input
                            wire:model.live.debounce.300ms="search"
                            :label="__('Search')"
                            :placeholder="__('Alt username, owner username, or gaijin ID')"
                        />
                    </div>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-left text-sm dark:divide-neutral-700">
                    <thead class="bg-neutral-100/70 text-xs uppercase tracking-wide text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                        <tr>
                            <th class="px-3 py-2">{{ __('Alt Username') }}</th>
                            <th class="px-3 py-2">{{ __('Alt ID') }}</th>
                            <th class="px-3 py-2">{{ __('Owner Username') }}</th>
                            <th class="px-3 py-2">{{ __('Owner ID') }}</th>
                            @if ($this->canEdit())
                                <th class="px-3 py-2 text-right">{{ __('Actions') }}</th>
                            @endif
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        @forelse ($peckAlts as $peckAlt)
                            <tr wire:key="peck-alt-{{ $peckAlt->alt_id }}" @class([
                                'bg-blue-50/70 dark:bg-blue-900/20' => $selectedAltId === $peckAlt->alt_id,
                            ])>
                                <td class="px-3 py-2">{{ $peckAlt->altUser?->username ?? '—' }}</td>
                                <td class="px-3 py-2 font-medium">{{ $peckAlt->alt_id }}</td>
                                <td class="px-3 py-2">{{ $peckAlt->ownerUser?->username ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $peckAlt->owner_id }}</td>
                                @if ($this->canEdit())
                                    <td class="px-3 py-2 text-right">
                                        <flux:button
                                            :variant="$selectedAltId === $peckAlt->alt_id ? 'primary' : 'ghost'"
                                            wire:click="selectAlt({{ $peckAlt->alt_id }})"
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
                                    {{ __('No alt mappings found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $peckAlts->links() }}
            </div>
        </section>

        @if ($this->canEdit())
            <flux:modal wire:model="showEditModal" class="max-w-2xl">
                @if ($selectedAltId !== null)
                    <form wire:submit="save" class="space-y-6">
                        <div>
                            <flux:heading size="lg">{{ __('Edit Alt Mapping') }}</flux:heading>
                            <flux:subheading>{{ __('Update which user is an alternate account of which owner.') }}</flux:subheading>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <flux:select wire:model="form.alt_id" :label="__('Alt User')" required>
                                <option value="">{{ __('Select alt user') }}</option>
                                @foreach ($userOptions as $userOption)
                                    <option value="{{ $userOption->gaijin_id }}">
                                        {{ $userOption->username }} ({{ $userOption->gaijin_id }})
                                    </option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="form.owner_id" :label="__('Owner User')" required>
                                <option value="">{{ __('Select owner user') }}</option>
                                @foreach ($userOptions as $userOption)
                                    <option value="{{ $userOption->gaijin_id }}">
                                        {{ $userOption->username }} ({{ $userOption->gaijin_id }})
                                    </option>
                                @endforeach
                            </flux:select>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-3">
                            <flux:button variant="danger" type="button" wire:click="deleteSelectedAlt" wire:loading.attr="disabled" wire:target="deleteSelectedAlt">
                                {{ __('Delete Mapping') }}
                            </flux:button>

                            <flux:modal.close>
                                <flux:button type="button" variant="ghost" wire:click="clearSelection">
                                    {{ __('Cancel') }}
                                </flux:button>
                            </flux:modal.close>

                            <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save">
                                {{ __('Save Changes') }}
                            </flux:button>

                            <x-action-message on="peck-alt-saved">
                                {{ __('Saved.') }}
                            </x-action-message>

                            <x-action-message on="peck-alt-deleted">
                                {{ __('Deleted.') }}
                            </x-action-message>
                        </div>
                    </form>
                @endif
            </flux:modal>

            <flux:modal wire:model="showCreateModal" class="max-w-2xl">
                <form wire:submit="createAlt" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Add Alt Mapping') }}</flux:heading>
                        <flux:subheading>{{ __('Create a new peck_alts record.') }}</flux:subheading>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:select wire:model="newAltForm.alt_id" :label="__('Alt User')" required>
                            <option value="">{{ __('Select alt user') }}</option>
                            @foreach ($userOptions as $userOption)
                                <option value="{{ $userOption->gaijin_id }}">
                                    {{ $userOption->username }} ({{ $userOption->gaijin_id }})
                                </option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="newAltForm.owner_id" :label="__('Owner User')" required>
                            <option value="">{{ __('Select owner user') }}</option>
                            @foreach ($userOptions as $userOption)
                                <option value="{{ $userOption->gaijin_id }}">
                                    {{ $userOption->username }} ({{ $userOption->gaijin_id }})
                                </option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <flux:modal.close>
                            <flux:button type="button" variant="ghost" wire:click="closeCreateModal">
                                {{ __('Cancel') }}
                            </flux:button>
                        </flux:modal.close>

                        <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createAlt">
                            {{ __('Create Mapping') }}
                        </flux:button>

                        <x-action-message on="peck-alt-created">
                            {{ __('Created.') }}
                        </x-action-message>
                    </div>
                </form>
            </flux:modal>
        @endif
    </div>
</div>
