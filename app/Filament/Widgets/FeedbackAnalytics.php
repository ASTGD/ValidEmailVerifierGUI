<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\FeedbackImports\FeedbackImportResource;
use App\Filament\Resources\VerificationJobs\VerificationJobResource;
use App\Models\EmailVerificationOutcome;
use App\Models\EmailVerificationOutcomeIngestion;
use App\Models\VerificationJob;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class FeedbackAnalytics extends Widget
{
    protected string $view = 'filament.widgets.feedback-analytics';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $now = now();
        $sevenDays = $now->copy()->subDays(7);
        $thirtyDays = $now->copy()->subDays(30);

        $baseJobs = VerificationJob::query()->whereNotNull('prepared_at');

        $seven = (clone $baseJobs)
            ->where('prepared_at', '>=', $sevenDays)
            ->selectRaw('COALESCE(SUM(total_emails), 0) as total')
            ->selectRaw('COALESCE(SUM(cached_count), 0) as cached')
            ->first();

        $thirty = (clone $baseJobs)
            ->where('prepared_at', '>=', $thirtyDays)
            ->selectRaw('COALESCE(SUM(total_emails), 0) as total')
            ->selectRaw('COALESCE(SUM(cached_count), 0) as cached')
            ->first();

        $topReasons = EmailVerificationOutcome::query()
            ->whereNotNull('reason_code')
            ->where('observed_at', '>=', $thirtyDays)
            ->select('reason_code', DB::raw('COUNT(*) as total'))
            ->groupBy('reason_code')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $ingestionSummary = EmailVerificationOutcomeIngestion::query()
            ->where('created_at', '>=', $thirtyDays)
            ->selectRaw('COUNT(*) as ingestions')
            ->selectRaw('COALESCE(SUM(item_count), 0) as items')
            ->selectRaw('COALESCE(SUM(imported_count), 0) as imported')
            ->first();

        $ingestionByType = EmailVerificationOutcomeIngestion::query()
            ->where('created_at', '>=', $thirtyDays)
            ->select('type', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(item_count), 0) as items'))
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        return [
            'cache' => [
                'seven' => $this->formatCacheMetrics($seven?->total ?? 0, $seven?->cached ?? 0),
                'thirty' => $this->formatCacheMetrics($thirty?->total ?? 0, $thirty?->cached ?? 0),
                'jobs_url' => VerificationJobResource::getUrl('index'),
            ],
            'topReasons' => $topReasons,
            'ingestion' => [
                'summary' => $ingestionSummary,
                'byType' => $ingestionByType,
                'imports_url' => FeedbackImportResource::getUrl('index'),
            ],
        ];
    }

    private function formatCacheMetrics(int $total, int $cached): array
    {
        $rate = $total > 0 ? round(($cached / $total) * 100, 1) : 0.0;

        return [
            'total' => $total,
            'cached' => $cached,
            'rate' => $rate,
        ];
    }
}
