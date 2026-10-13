@if (filled($locationName))
    <x-filament::badge color="gray" icon="heroicon-o-map-pin">
        {{ $locationName }}
    </x-filament::badge>
@endif
