<x-filament-panels::page>
    <div class="grid gap-6 xl:grid-cols-3">
        <x-filament::section heading="Inputs" class="xl:col-span-2">
            <p class="text-sm text-gray-500">
                Adjust these inputs to estimate throughput, queue pressure, and monthly costs. Outputs update instantly.
            </p>

            <div class="mt-4">
                {{ $this->form }}
            </div>
        </x-filament::section>

        <div class="space-y-6 xl:col-span-1">
            <x-filament::section heading="Capacity Results">
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($this->results as $label => $value)
                        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">
                                {{ $label }}
                            </div>
                            <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                {{ $value }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            <x-filament::section heading="Poll Load Estimator">
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($this->pollResults as $label => $value)
                        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">
                                {{ $label }}
                            </div>
                            <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                {{ $value }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            <x-filament::section heading="Queue Pressure Estimator">
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($this->queueResults as $label => $value)
                        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">
                                {{ $label }}
                            </div>
                            <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                {{ $value }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            <x-filament::section heading="Cost Estimator">
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($this->costResults as $label => $value)
                        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">
                                {{ $label }}
                            </div>
                            <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                {{ $value }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>

        <x-filament::section heading="Environment Snapshot" class="xl:col-span-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-500">
                    Quick read-only view of runtime settings and recent metrics samples.
                </p>
                <x-filament::button size="sm" wire:click="refreshSnapshotAction">
                    Refresh snapshot
                </x-filament::button>
            </div>

            <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->snapshot as $label => $value)
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            {{ $label }}
                        </dt>
                        <dd class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ $value }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </x-filament::section>

        <x-filament::section heading="Recent Jobs Snapshot" class="xl:col-span-3">
            <p class="text-sm text-gray-500">
                Last 10 jobs with duration and counts. Refresh the page to update.
            </p>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-3 py-2 text-left">Job ID</th>
                            <th class="px-3 py-2 text-left">Status</th>
                            <th class="px-3 py-2 text-left">Created</th>
                            <th class="px-3 py-2 text-left">Started</th>
                            <th class="px-3 py-2 text-left">Finished</th>
                            <th class="px-3 py-2 text-left">Duration</th>
                            <th class="px-3 py-2 text-right">Total</th>
                            <th class="px-3 py-2 text-right">Cached</th>
                            <th class="px-3 py-2 text-right">Valid</th>
                            <th class="px-3 py-2 text-right">Invalid</th>
                            <th class="px-3 py-2 text-right">Risky</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($this->recentJobs as $job)
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs text-gray-700 dark:text-gray-200">{{ $job['id'] }}</td>
                                <td class="px-3 py-2">{{ $job['status'] }}</td>
                                <td class="px-3 py-2">{{ $job['created'] }}</td>
                                <td class="px-3 py-2">{{ $job['started'] }}</td>
                                <td class="px-3 py-2">{{ $job['finished'] }}</td>
                                <td class="px-3 py-2">{{ $job['duration'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $job['total'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $job['cached'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $job['valid'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $job['invalid'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $job['risky'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-3 py-3 text-center text-gray-500" colspan="11">No jobs found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section heading="Notes" class="xl:col-span-3">
            <p class="text-sm text-gray-500">
                This page is disabled by default. Enable with <code class="text-xs">DEVTOOLS_ENABLED=true</code> and
                <code class="text-xs">DEVTOOLS_ENVIRONMENTS=local,staging</code> in your environment file.
            </p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
