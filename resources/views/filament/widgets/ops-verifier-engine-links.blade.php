<x-filament::section heading="Verifier Engine Links">
    <div class="flex flex-wrap items-center gap-3">
        <x-filament::button tag="a" href="{{ $serversUrl }}">
            Engine Servers
        </x-filament::button>

        <x-filament::button tag="a" href="{{ $createUrl }}" color="gray">
            Add Engine Server
        </x-filament::button>
    </div>
</x-filament::section>
