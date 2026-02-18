<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} · {{ $pageTitle }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <div class="w-full px-6 py-6">
        <header class="mb-6 flex flex-wrap items-center justify-between gap-4 border-b border-slate-300 pb-4">
            <div>
                <p class="text-xs uppercase tracking-[0.2em] text-slate-600">Operations Docs</p>
                <h1 class="text-2xl font-semibold text-slate-900">{{ $title }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ $pageTitle }}</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ url('/admin') }}" class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm text-slate-700 hover:bg-slate-100">Laravel Admin</a>
                <a href="{{ url('/'.trim((string) config('horizon.path', 'horizon'), '/')) }}" target="_blank" class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm text-slate-700 hover:bg-slate-100">Horizon</a>
                @if (filled(config('services.go_control_plane.base_url')))
                    <a href="{{ rtrim((string) config('services.go_control_plane.base_url'), '/').'/verifier-engine-room/overview' }}" target="_blank" class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm text-slate-700 hover:bg-slate-100">Go Panel</a>
                @endif
            </div>
        </header>

        <div class="grid gap-6 lg:grid-cols-4">
            <aside class="rounded-2xl border border-slate-300 bg-white p-4 shadow-sm lg:col-span-1">
                <p class="mb-3 text-xs uppercase tracking-[0.2em] text-slate-600">Sections</p>
                <nav class="space-y-4">
                    @foreach ($sections as $sectionKey => $section)
                        <div>
                            <p class="mb-1 text-xs font-semibold uppercase tracking-[0.15em] text-slate-600">{{ $section['label'] ?? $sectionKey }}</p>
                            <ul class="space-y-1">
                                @foreach (($section['pages'] ?? []) as $pageKey => $page)
                                    <li>
                                        <a
                                            href="{{ route('internal.docs.page', ['section' => $sectionKey, 'page' => $pageKey]) }}"
                                            class="@if($activeSection === $sectionKey && $activePage === $pageKey) bg-amber-100 text-amber-900 border-amber-300 @else text-slate-700 border-transparent hover:bg-slate-100 @endif block rounded-lg border px-3 py-2 text-sm"
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

            <main class="rounded-2xl border border-slate-300 bg-white p-6 shadow-sm lg:col-span-3">
                <article class="docs-markdown">
                    {!! $contentHtml !!}
                </article>
            </main>
        </div>
    </div>
</body>
</html>
