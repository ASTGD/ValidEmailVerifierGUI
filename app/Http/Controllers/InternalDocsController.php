<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InternalDocsController extends Controller
{
    public function index(): RedirectResponse
    {
        $sections = config('internal_docs.sections', []);
        $firstSectionKey = array_key_first($sections);
        if (! is_string($firstSectionKey)) {
            abort(404);
        }

        $firstPages = Arr::get($sections, $firstSectionKey.'.pages', []);
        $firstPageKey = array_key_first($firstPages);
        if (! is_string($firstPageKey)) {
            abort(404);
        }

        return redirect()->route('internal.docs.page', [
            'section' => $firstSectionKey,
            'page' => $firstPageKey,
        ]);
    }

    public function show(string $section, string $page): View
    {
        $sections = config('internal_docs.sections', []);
        $sectionConfig = Arr::get($sections, $section);
        $pageConfig = Arr::get($sectionConfig, 'pages.'.$page);

        if (! is_array($sectionConfig) || ! is_array($pageConfig)) {
            abort(404);
        }

        $relativePath = (string) ($pageConfig['path'] ?? '');
        if ($relativePath === '') {
            abort(404);
        }

        $docsRoot = realpath(base_path('docs'));
        $targetPath = realpath(base_path($relativePath));
        if (! is_string($docsRoot) || ! is_string($targetPath)) {
            abort(404);
        }

        $normalizedDocsRoot = rtrim($docsRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (! str_starts_with($targetPath, $normalizedDocsRoot)) {
            abort(404);
        }

        if (! File::isFile($targetPath)) {
            abort(404);
        }

        $markdown = File::get($targetPath);
        $contentHtml = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return view('internal-docs.index', [
            'title' => (string) config('internal_docs.title', 'Operations Documentation'),
            'sections' => $sections,
            'activeSection' => $section,
            'activePage' => $page,
            'pageTitle' => (string) ($pageConfig['title'] ?? $page),
            'contentHtml' => $contentHtml,
        ]);
    }
}
