<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} · {{ $pageTitle }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <div class="mx-auto max-w-7xl px-6 py-6">
        <header class="mb-6 flex flex-wrap items-center justify-between gap-4 border-b border-slate-800 pb-4">
            <div>
                <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Operations Docs</p>
                <h1 class="text-2xl font-semibold">{{ $title }}</h1>
                <p class="mt-1 text-sm text-slate-400">{{ $pageTitle }}</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ url('/admin') }}" class="rounded-full border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-900">Laravel Admin</a>
                <a href="{{ url('/'.trim((string) config('horizon.path', 'horizon'), '/')) }}" target="_blank" class="rounded-full border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-900">Horizon</a>
                @if (filled(config('services.go_control_plane.base_url')))
                    <a href="{{ rtrim((string) config('services.go_control_plane.base_url'), '/').'/verifier-engine-room/overview' }}" target="_blank" class="rounded-full border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-900">Go Panel</a>
                @endif
            </div>
        </header>

        <div class="grid gap-6 lg:grid-cols-[18rem,1fr]">
            <aside class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
                <p class="mb-3 text-xs uppercase tracking-[0.2em] text-slate-400">Sections</p>
                <nav class="space-y-4">
                    @foreach ($sections as $sectionKey => $section)
                        <div>
                            <p class="mb-1 text-xs font-semibold uppercase tracking-[0.15em] text-slate-400">{{ $section['label'] ?? $sectionKey }}</p>
                            <ul class="space-y-1">
                                @foreach (($section['pages'] ?? []) as $pageKey => $page)
                                    <li>
                                        <a
                                            href="{{ route('internal.docs.page', ['section' => $sectionKey, 'page' => $pageKey]) }}"
                                            class="@if($activeSection === $sectionKey && $activePage === $pageKey) bg-amber-500/20 text-amber-200 border-amber-400/40 @else text-slate-200 border-transparent hover:bg-slate-800/80 @endif block rounded-lg border px-3 py-2 text-sm"
                                        >
                                            {{ $page['title'] ?? $pageKey }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </nav>
            </aside>

            <main class="prose prose-invert max-w-none rounded-2xl border border-slate-800 bg-slate-900/60 p-6 prose-headings:scroll-mt-24">
                {!! $contentHtml !!}
            </main>
        </div>
    </div>
</body>
</html>
