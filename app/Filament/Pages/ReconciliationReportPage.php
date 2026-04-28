<?php

namespace App\Filament\Pages;

use App\Core\Models\PaymentTransaction;
use App\Services\ReconciliationService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response;

class ReconciliationReportPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'filament.pages.reconciliation-report';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-banknotes';
    }

    public static function getNavigationLabel(): string
    {
        return 'Reconciliation Report';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Finance';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public function getTitle(): string
    {
        return 'Payment Reconciliation';
    }

    // ---------------------------------------------------------------------------
    // Filter state
    // ---------------------------------------------------------------------------

    public ?string $from    = null;
    public ?string $to      = null;
    public ?string $gateway = null;

    /** @var array<string, mixed> */
    public array $report = [];

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to   = now()->toDateString();
        $this->loadReport();

        $this->form->fill([
            'from'    => $this->from,
            'to'      => $this->to,
            'gateway' => $this->gateway,
        ]);
    }

    // ---------------------------------------------------------------------------
    // Filter form
    // ---------------------------------------------------------------------------

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('from')
                    ->label('From')
                    ->required()
                    ->default(now()->startOfMonth()),
                DatePicker::make('to')
                    ->label('To')
                    ->required()
                    ->default(now()),
                Select::make('gateway')
                    ->label('Gateway')
                    ->options([
                        ''           => 'All gateways',
                        'mastercard' => 'Mastercard',
                        'apple_pay'  => 'Apple Pay',
                        'cash'       => 'Cash',
                    ])
                    ->placeholder('All gateways'),
            ])
            ->columns(3);
    }

    public function applyFilters(): void
    {
        $data = $this->form->getState();

        $this->from    = $data['from'];
        $this->to      = $data['to'];
        $this->gateway = $data['gateway'] ?: null;

        $this->resetTable();
        $this->loadReport();
    }

    private function loadReport(): void
    {
        $service = app(ReconciliationService::class);

        $this->report = $service->generateReport(
            Carbon::parse($this->from),
            Carbon::parse($this->to),
            $this->gateway ?: null,
        );
    }

    // ---------------------------------------------------------------------------
    // Table
    // ---------------------------------------------------------------------------

    public function table(Table $table): Table
    {
        return $table
            ->query($this->tableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('order_id')
                    ->label('Order')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'success' => 'capture',
                        'danger'  => fn ($state) => in_array($state, ['refund', 'void']),
                    ]),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount (pt)')
                    ->formatStateUsing(fn ($state) => number_format($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('gateway')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('gateway_reference')
                    ->label('Reference')
                    ->searchable()
                    ->limit(24),
                Tables\Columns\TextColumn::make('gateway_status')
                    ->label('Status'),
                Tables\Columns\IconColumn::make('reconciled')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'capture' => 'Capture',
                        'refund'  => 'Refund',
                        'void'    => 'Void',
                    ]),
                Tables\Filters\TernaryFilter::make('reconciled')
                    ->label('Reconciled'),
            ])
            ->bulkActions([
                BulkAction::make('markReconciled')
                    ->label('Mark Reconciled')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (\Illuminate\Support\Collection $records): void {
                        app(ReconciliationService::class)
                            ->markReconciled($records->pluck('id')->all());
                        Notification::make()
                            ->title('Marked as reconciled')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private function tableQuery(): Builder
    {
        $query = PaymentTransaction::query()
            ->whereBetween('created_at', [
                Carbon::parse($this->from)->startOfDay(),
                Carbon::parse($this->to)->endOfDay(),
            ]);

        if ($this->gateway) {
            $query->where('gateway', $this->gateway);
        }

        return $query;
    }

    // ---------------------------------------------------------------------------
    // Header actions
    // ---------------------------------------------------------------------------

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => $this->exportCsv()),
        ];
    }

    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $transactions = PaymentTransaction::query()
            ->whereBetween('created_at', [
                Carbon::parse($this->from)->startOfDay(),
                Carbon::parse($this->to)->endOfDay(),
            ])
            ->when($this->gateway, fn ($q) => $q->where('gateway', $this->gateway))
            ->orderByDesc('created_at')
            ->get();

        $filename = 'reconciliation_' . $this->from . '_' . $this->to . '.csv';

        return Response::streamDownload(function () use ($transactions): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Order', 'Type', 'Amount (pt)', 'Gateway', 'Reference', 'Status', 'Reconciled', 'Date']);
            foreach ($transactions as $tx) {
                fputcsv($handle, [
                    $tx->id,
                    $tx->order_id,
                    $tx->type,
                    $tx->amount,
                    $tx->gateway,
                    $tx->gateway_reference,
                    $tx->gateway_status,
                    $tx->reconciled ? 'Yes' : 'No',
                    $tx->created_at->toDateTimeString(),
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
