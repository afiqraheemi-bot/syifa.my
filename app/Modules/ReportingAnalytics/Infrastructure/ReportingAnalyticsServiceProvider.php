<?php

declare(strict_types=1);

namespace App\Modules\ReportingAnalytics\Infrastructure;

use App\Modules\ReportingAnalytics\Application\MetricDefinitionCatalogue;
use App\Modules\ReportingAnalytics\Contracts\ReportReadInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class ReportingAnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MetricDefinitionCatalogue::class);
        $this->app->singleton(
            ReportReadInterface::class,
            static fn (Application $app): PostgresReportReadAdapter => new PostgresReportReadAdapter(
                $app->make('db')->connection(),
                $app->make(MetricDefinitionCatalogue::class),
            ),
        );
    }
}
