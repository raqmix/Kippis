<?php

namespace App\Filament\Pages;

use App\Services\Loyalty\MonthlyDrawService;
use Carbon\CarbonImmutable;
use Filament\Actions;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Monthly Big Win: pick a month, export the entries (one per completed
 * app order) as CSV, optionally draw one random entry.
 */
class MonthlyDraw extends Page
{
    protected string $view = 'filament.pages.monthly-draw';

    protected static ?string $slug = 'monthly-draw';

    protected static ?int $navigationSort = 53;

    public string $month = '';

    /** @var array<string, mixed>|null */
    public ?array $winner = null;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-trophy';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Marketing';
    }

    public static function getNavigationLabel(): string
    {
        return __('loyalty_rules.draw.title');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('loyalty_rules.draw.title');
    }

    public static function canAccess(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_loyalty');
    }

    public function mount(): void
    {
        $this->month = CarbonImmutable::now(config('core.business_timezone', 'Africa/Cairo'))->format('Y-m');
    }

    public function updatedMonth(): void
    {
        $this->winner = null;
    }

    /** @return array<string, string> last 18 months, newest first */
    public function monthOptions(): array
    {
        $now = CarbonImmutable::now(config('core.business_timezone', 'Africa/Cairo'))->startOfMonth();
        $options = [];
        for ($i = 0; $i < 18; $i++) {
            $m = $now->subMonths($i);
            $options[$m->format('Y-m')] = $m->translatedFormat('F Y');
        }

        return $options;
    }

    public function entryCount(): int
    {
        return $this->validMonth() ? app(MonthlyDrawService::class)->entries($this->month)->count() : 0;
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless($this->validMonth(), 422);
        $csv = app(MonthlyDrawService::class)->csv($this->month);

        return response()->streamDownload(fn () => print($csv), "monthly-draw-{$this->month}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function pickWinner(): void
    {
        $this->winner = null;
        if (! $this->validMonth()) {
            return;
        }

        $order = app(MonthlyDrawService::class)->pickWinner($this->month);
        if ($order) {
            $this->winner = [
                'order_id'    => $order->id,
                'date'        => $order->created_at->copy()->setTimezone(config('core.business_timezone', 'Africa/Cairo'))->format('Y-m-d H:i'),
                'customer_id' => $order->customer_id,
                'name'        => $order->customer?->name,
                'phone'       => trim(($order->customer?->country_code ?? '') . ' ' . ($order->customer?->phone ?? '')),
                'store'       => $order->store?->name,
            ];
        }
    }

    private function validMonth(): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month);
    }
}
