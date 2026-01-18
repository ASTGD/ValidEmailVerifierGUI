<?php

namespace Tests\Feature;

use App\Filament\Widgets\FeedbackAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class FeedbackAnalyticsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_feedback_analytics_widget_builds_view_data(): void
    {
        $widget = app(FeedbackAnalytics::class);
        $method = new ReflectionMethod($widget, 'getViewData');
        $method->setAccessible(true);

        $data = $method->invoke($widget);

        $this->assertArrayHasKey('cache', $data);
        $this->assertArrayHasKey('topReasons', $data);
        $this->assertArrayHasKey('ingestion', $data);
    }
}
