@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="PECK management" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md">
            <img src="{{ asset('images/peck_icon.png') }}" alt="{{ __('PECK management') }}" class="size-full object-cover" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="PECK management" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md">
            <img src="{{ asset('images/peck_icon.png') }}" alt="{{ __('PECK management') }}" class="size-full object-cover" />
        </x-slot>
    </flux:brand>
@endif
