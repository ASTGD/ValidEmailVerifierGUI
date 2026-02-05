@if ($showAlert)
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 shadow-sm dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
        <div class="font-semibold">Redis offline — falling back to safe defaults</div>
        <div class="mt-1">
            Queue driver: <span class="font-medium">{{ $runtimeQueue }}</span> · Cache store: <span class="font-medium">{{ $runtimeCache }}</span>
        </div>
        <div class="mt-1 text-xs text-amber-800/80 dark:text-amber-200/70">
            Start Redis to restore the configured Queue Engine settings.
        </div>
    </div>
@endif
