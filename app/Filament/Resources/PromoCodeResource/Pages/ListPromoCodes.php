<?php

namespace App\Filament\Resources\PromoCodeResource\Pages;

use App\Core\Models\PromoCode;
use App\Filament\Resources\PromoCodeResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;

class ListPromoCodes extends ListRecords
{
    protected static string $resource = PromoCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('bulk_create_coupons')
                ->label('Bulk Create')
                ->icon('heroicon-o-document-duplicate')
                ->color('info')
                ->modalHeading('Bulk Create Coupons')
                ->modalDescription('Generates N unique codes sharing the same discount + validity. Each code is "<prefix>-<random>" — the random suffix uses unambiguous letters and digits.')
                ->modalSubmitActionLabel('Create')
                ->form([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('count')
                                ->label('How many coupons')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(2000)
                                ->required()
                                ->default(10),
                            Forms\Components\TextInput::make('prefix')
                                ->label('Code prefix')
                                ->helperText('e.g. RAMADAN, LAUNCH, NEWUSER. Uppercased automatically.')
                                ->maxLength(20)
                                ->required(),
                            Forms\Components\TextInput::make('suffix_length')
                                ->label('Random suffix length')
                                ->numeric()
                                ->minValue(4)
                                ->maxValue(12)
                                ->required()
                                ->default(6),
                            Forms\Components\Select::make('discount_type')
                                ->label('Coupon type')
                                ->options([
                                    'percentage' => 'Percentage',
                                    'fixed' => 'Fixed amount (EGP)',
                                ])
                                ->required()
                                ->default('percentage'),
                            Forms\Components\TextInput::make('discount_value')
                                ->label('Discount')
                                ->numeric()
                                ->minValue(0)
                                ->required()
                                ->helperText('% for percentage, EGP for fixed.'),
                            Forms\Components\TextInput::make('usage_limit')
                                ->label('Maximum uses per coupon')
                                ->numeric()
                                ->minValue(1)
                                ->required()
                                ->default(1),
                            Forms\Components\TextInput::make('minimum_order_amount')
                                ->label('Minimum order amount (EGP)')
                                ->numeric()
                                ->minValue(0)
                                ->default(0),
                            Forms\Components\DateTimePicker::make('valid_from')
                                ->label('Start date')
                                ->required(),
                            Forms\Components\DateTimePicker::make('valid_to')
                                ->label('End date')
                                ->required()
                                ->after('valid_from'),
                            Forms\Components\Toggle::make('active')
                                ->label('Active')
                                ->default(true),
                        ]),
                ])
                ->action(function (array $data) {
                    $result = $this->generateBulkCoupons($data);

                    $notification = Notification::make()
                        ->title('Bulk create completed')
                        ->body("{$result['created']} coupons created."
                            . ($result['skipped'] > 0 ? " {$result['skipped']} skipped (duplicate-collision retries exhausted)." : ''));

                    if ($result['skipped'] === 0) {
                        $notification->success();
                    } elseif ($result['created'] > 0) {
                        $notification->warning();
                    } else {
                        $notification->danger();
                    }
                    $notification->send();
                }),
        ];
    }

    /**
     * Generate `count` unique promo codes sharing the same discount + window.
     * Codes are `<UPPERPREFIX>-<random>` where the suffix uses an
     * unambiguous alphabet (no 0/O/1/I/L) so it's safe to read aloud at the
     * counter. Unique-collisions are retried per code up to 6 attempts;
     * if a code can't be made unique it's skipped and reported.
     */
    private function generateBulkCoupons(array $data): array
    {
        $count = (int) $data['count'];
        $prefix = strtoupper(trim($data['prefix']));
        $suffixLength = (int) $data['suffix_length'];
        $created = 0;
        $skipped = 0;

        for ($i = 0; $i < $count; $i++) {
            $code = null;
            for ($attempt = 0; $attempt < 6; $attempt++) {
                $candidate = $prefix . '-' . $this->randomCodeSuffix($suffixLength);
                if (! PromoCode::where('code', $candidate)->exists()) {
                    $code = $candidate;
                    break;
                }
            }
            if ($code === null) {
                $skipped++;
                continue;
            }
            PromoCode::create([
                'code' => $code,
                'discount_type' => $data['discount_type'],
                'discount_value' => $data['discount_value'],
                'usage_limit' => $data['usage_limit'],
                'minimum_order_amount' => $data['minimum_order_amount'] ?? 0,
                'valid_from' => $data['valid_from'],
                'valid_to' => $data['valid_to'],
                'active' => (bool) ($data['active'] ?? true),
                'used_count' => 0,
            ]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    private function randomCodeSuffix(int $length): string
    {
        // Drop 0/O/1/I/L — operator can't misread the printed code.
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $out = '';
        $alphabetLength = strlen($alphabet);
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $alphabetLength - 1)];
        }
        return $out;
    }
}
