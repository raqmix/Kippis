<?php

namespace App\Filament\Resources;

use App\Core\Models\Referral;
use App\Filament\Resources\ReferralResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReferralResource extends Resource
{
    protected static ?string $model = Referral::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-user-plus';
    }

    public static function getNavigationLabel(): string
    {
        return 'Referrals';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Marketing';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('inviter.name')->label('Inviter')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('invitee.name')->label('Invitee')->searchable()->default('—'),
                Tables\Columns\TextColumn::make('referral_code')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'converted'  => 'success',
                        'registered' => 'info',
                        default      => 'gray',
                    }),
                Tables\Columns\TextColumn::make('inviter_points')->sortable(),
                Tables\Columns\TextColumn::make('invitee_points')->sortable(),
                Tables\Columns\TextColumn::make('converted_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending'    => 'Pending',
                        'registered' => 'Registered',
                        'converted'  => 'Converted',
                    ]),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReferrals::route('/'),
        ];
    }
}
