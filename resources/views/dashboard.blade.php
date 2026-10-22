<x-layouts::app :title="__('Dashboard')">
    <livewire:peck-users-dashboard :section="$dashboardSection ?? 'members'" />
</x-layouts::app>
