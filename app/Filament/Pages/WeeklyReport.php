<?php

namespace App\Filament\Pages;

use App\Services\Reports\WeeklyStoreReport;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Weekly report: per-store sign-ups, first orders, app orders and all
 * orders for one Monday-Sunday week (Africa/Cairo), with CSV export.
 */
class WeeklyReport extends Page
{
    protected string $view = 'filament.pages.weekly-report';

    protected static ?string $slug = 'weekly-report';

    protected static ?int $navigationSort = 11;

    /** Any date inside the chosen week (Y-m-d); snapped to its Monday. */
    public string $week = '';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-calendar-days';
    }

    public static function getNavigationLabel(): string
    {
        return 'Weekly report';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Finance';
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Weekly report';
    }

    public static function canAccess(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_orders');
    }

    public function mount(): void
    {
        $this->week = WeeklyStoreReport::weekStart()->toDateString();
    }

    public function previousWeek(): void
    {
        $this->week = WeeklyStoreReport::weekStart($this->week)->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->week = WeeklyStoreReport::weekStart($this->week)->addWeek()->toDateString();
    }

    public function report(): array
    {
        return app(WeeklyStoreReport::class)->build($this->week ?: null);
    }

    public function exportCsv(): StreamedResponse
    {
        $service = app(WeeklyStoreReport::class);
        $report = $service->build($this->week ?: null);

        return Response::streamDownload(
            fn () => print($service->toCsv($report)),
            'weekly_report_' . $report['week_start'] . '.csv',
            ['Content-Type' => 'text/csv'],
        );
    }
}
