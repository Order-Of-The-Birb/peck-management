<x-layouts::app :title="__('Dashboard')">
    <livewire:peck-users-dashboard :section="$dashboardSection ?? 'users'" />
</x-layouts::app>
