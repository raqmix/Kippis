<?php

namespace App\Filament\Resources;

use App\Core\Models\Category;
use App\Core\Models\Customer;
use App\Core\Models\Product;
use App\Core\Models\PromoCode;
use App\Core\Models\ScheduledPush;
use App\Filament\Resources\ScheduledPushResource\Pages;
use App\Services\ScheduledPushService;
use Carbon\CarbonImmutable;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class ScheduledPushResource extends Resource
{
    protected static ?string $model = ScheduledPush::class;

    protected static ?string $navigationLabel = 'Scheduled Pushes';

    protected static ?string $modelLabel = 'Scheduled Push';

    public static function canViewAny(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_pushes');
    }

    public static function canCreate(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_pushes');
    }

    public static function canEdit($record): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_pushes');
    }

    public static function canDelete($record): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_pushes');
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-bell-alert';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Marketing';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('Identity')
                    ->description('Internal name — the customer never sees this. Ops uses it to find the row.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(120)
                            ->placeholder('Thursday Breakfast Reminder'),
                    ]),

                Components\Section::make('Copy')
                    ->description('Shown to the customer as the notification title + body. Sent bilingually — both EN and AR appear separated by " • ".')
                    ->schema([
                        Components\Tabs::make('copy_tabs')
                            ->tabs([
                                Components\Tabs\Tab::make('English')->schema([
                                    Forms\Components\TextInput::make('title_en')
                                        ->label('Title (EN)')
                                        ->required()
                                        ->maxLength(60)
                                        ->placeholder('Good morning ☀️'),
                                    Forms\Components\Textarea::make('body_en')
                                        ->label('Body (EN)')
                                        ->required()
                                        ->rows(3)
                                        ->maxLength(200)
                                        ->placeholder("Today's breakfast menu is live — order before 11am."),
                                ]),
                                Components\Tabs\Tab::make('Arabic')->schema([
                                    Forms\Components\TextInput::make('title_ar')
                                        ->label('Title (AR)')
                                        ->required()
                                        ->maxLength(60)
                                        ->placeholder('صباح الخير ☀️'),
                                    Forms\Components\Textarea::make('body_ar')
                                        ->label('Body (AR)')
                                        ->required()
                                        ->rows(3)
                                        ->maxLength(200)
                                        ->placeholder('قائمة الإفطار متاحة الآن — اطلب قبل ١١ صباحاً.'),
                                ]),
                            ]),
                    ]),

                Components\Section::make('Schedule')
                    ->description('When to fire. If the server was down at the scheduled minute, still send within the grace window.')
                    ->schema([
                        Components\Grid::make(3)->schema([
                            Forms\Components\Select::make('day_of_week')
                                ->label('Day')
                                ->options([
                                    '' => 'Every day',
                                    0 => 'Sunday',
                                    1 => 'Monday',
                                    2 => 'Tuesday',
                                    3 => 'Wednesday',
                                    4 => 'Thursday',
                                    5 => 'Friday',
                                    6 => 'Saturday',
                                ])
                                ->placeholder('Every day')
                                ->dehydrateStateUsing(fn ($state) => $state === '' ? null : $state),
                            Forms\Components\TimePicker::make('time_of_day')
                                ->label('Time')
                                ->required()
                                ->seconds(false)
                                ->default('08:00'),
                            Forms\Components\Select::make('timezone')
                                ->label('Timezone')
                                ->options(collect(\DateTimeZone::listIdentifiers())
                                    ->mapWithKeys(fn ($tz) => [$tz => $tz])
                                    ->all())
                                ->searchable()
                                ->default('Africa/Cairo')
                                ->required()
                                ->helperText('Usually keep as Africa/Cairo.'),
                        ]),
                        Forms\Components\TextInput::make('grace_minutes')
                            ->label('Grace window (minutes)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(120)
                            ->default(30)
                            ->helperText('If the cron missed the scheduled time, still send within this many minutes. Beyond that, skip the day.'),
                    ]),

                Components\Section::make('Destination')
                    ->description('Where the customer lands when they tap the notification.')
                    ->schema([
                        Forms\Components\Select::make('deeplink_type')
                            ->label('Deep link')
                            ->options([
                                'home' => 'Home screen',
                                'offers' => 'Offers screen',
                                'category' => 'Category (pick one)',
                                'product' => 'Product (pick one)',
                                'offer_id' => 'Specific offer (pick one)',
                            ])
                            ->default('home')
                            ->required()
                            ->live(),
                        Forms\Components\Select::make('deeplink_id')
                            ->label('Category')
                            ->options(fn () => Category::query()->orderBy('id')->get()
                                ->mapWithKeys(fn ($c) => [$c->id => $c->name_json['en'] ?? $c->name_json['ar'] ?? ('Category #' . $c->id)])
                                ->all())
                            ->searchable()
                            ->required(fn ($get) => $get('deeplink_type') === 'category')
                            ->visible(fn ($get) => $get('deeplink_type') === 'category'),
                        Forms\Components\Select::make('deeplink_id')
                            ->label('Product')
                            ->options(fn () => Product::query()->orderBy('id')->limit(500)->get()
                                ->mapWithKeys(fn ($p) => [$p->id => method_exists($p, 'getName') ? $p->getName() : ($p->name_json['en'] ?? ('Product #' . $p->id))])
                                ->all())
                            ->searchable()
                            ->required(fn ($get) => $get('deeplink_type') === 'product')
                            ->visible(fn ($get) => $get('deeplink_type') === 'product'),
                        Forms\Components\Select::make('deeplink_id')
                            ->label('Offer')
                            ->options(fn () => PromoCode::query()->orderBy('id')->limit(500)->get()
                                ->mapWithKeys(fn ($p) => [$p->id => $p->name_json['en'] ?? $p->code ?? ('Promo #' . $p->id)])
                                ->all())
                            ->searchable()
                            ->required(fn ($get) => $get('deeplink_type') === 'offer_id')
                            ->visible(fn ($get) => $get('deeplink_type') === 'offer_id'),
                    ]),

                Components\Section::make('Audience & activation')
                    ->schema([
                        Forms\Components\Select::make('audience')
                            ->label('Audience')
                            ->options([
                                'opted_in_only' => 'Opted-in customers only (recommended)',
                                'has_ordered' => 'Customers who have ordered (opted-in)',
                                'inactive_30d' => 'Inactive 30+ days (opted-in)',
                                'all' => 'ALL customers — bypasses consent',
                            ])
                            ->default('opted_in_only')
                            ->required()
                            ->helperText('"ALL" ignores customers\' marketing opt-in — reserve for critical announcements only.'),
                        Forms\Components\Toggle::make('active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('When off, the row is preserved but skipped by the cron.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (ScheduledPush $r) => $r->title_en),
                Tables\Columns\TextColumn::make('schedule_label')
                    ->label('Schedule')
                    ->getStateUsing(fn (ScheduledPush $r) => $r->scheduleLabel()),
                Tables\Columns\TextColumn::make('audience')
                    ->badge()
                    ->colors([
                        'gray' => 'opted_in_only',
                        'success' => 'has_ordered',
                        'warning' => 'inactive_30d',
                        'danger' => 'all',
                    ]),
                Tables\Columns\TextColumn::make('last_sent_at')
                    ->label('Last sent')
                    ->since()
                    ->placeholder('Never'),
                Tables\Columns\TextColumn::make('last_sent_count')
                    ->label('Last count')
                    ->numeric()
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('active')->boolean()->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('active')->label('Active'),
            ])
            ->actions([
                Actions\Action::make('fireNow')
                    ->label('Fire now')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(fn (ScheduledPush $r) => 'Send this push immediately to the current audience. This bypasses the schedule and dedup window.')
                    ->action(function (ScheduledPush $r) {
                        $svc = app(ScheduledPushService::class);
                        $count = $svc->fireOne($r, CarbonImmutable::now('UTC'));
                        Notification::make()
                            ->title($count === null
                                ? 'Skipped — this window has already been fired.'
                                : sprintf('Fired to %d recipient(s).', $count))
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('testSend')
                    ->label('Test send')
                    ->icon('heroicon-o-beaker')
                    ->color('info')
                    ->form([
                        Forms\Components\TextInput::make('customer_email_or_phone')
                            ->label('Customer email or phone')
                            ->required()
                            ->helperText('Sends only to this one customer. Ignores audience filter + opt-in.'),
                    ])
                    ->action(function (array $data, ScheduledPush $r) {
                        $needle = trim($data['customer_email_or_phone']);
                        $customer = Customer::query()
                            ->where('email', $needle)
                            ->orWhere('phone', $needle)
                            ->first();
                        if (! $customer) {
                            Notification::make()->title('No customer found for that email/phone.')->danger()->send();
                            return;
                        }
                        try {
                            app(ScheduledPushService::class)->testSendToCustomer($r, $customer);
                            Notification::make()->title('Test push sent to ' . ($customer->email ?? $customer->phone))->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Send failed: ' . $e->getMessage())->danger()->send();
                        }
                    }),
                Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListScheduledPushes::route('/'),
            'create' => Pages\CreateScheduledPush::route('/create'),
            'edit' => Pages\EditScheduledPush::route('/{record}/edit'),
        ];
    }
}
