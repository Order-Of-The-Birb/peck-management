@php
    $dashboardTitle = __('All users');

    if (($unverifiedOnly ?? false) === true) {
        $dashboardTitle = __('Unverified users');
    } elseif (($membersWithoutDiscordOnly ?? false) === true) {
        $dashboardTitle = __('Missing Discord IDs');
    }
@endphp

<x-layouts::app :title="$dashboardTitle">
    <livewire:peck-users-dashboard
        :members-without-discord-only="$membersWithoutDiscordOnly ?? false"
        :unverified-only="$unverifiedOnly ?? false"
    />
</x-layouts::app>