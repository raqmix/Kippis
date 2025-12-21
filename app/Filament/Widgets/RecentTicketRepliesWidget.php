<?php

namespace App\Filament\Widgets;

use App\Core\Models\SupportReply;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentTicketRepliesWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SupportReply::query()
                    ->with(['ticket', 'admin'])
                    ->where('is_internal', false)
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('ticket.ticket_number')
                    ->label(__('system.ticket_number'))
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => \App\Filament\Resources\SupportTicketResource::getUrl('view', ['record' => $record->ticket_id]))
                    ->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('ticket.subject')
                    ->label(__('system.subject'))
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('message')
                    ->label(__('system.message'))
                    ->limit(50)
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('admin.name')
                    ->label(__('system.replied_by'))
                    ->default(__('system.system'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('system.date'))
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->heading(__('system.recent_ticket_responses'))
            ->description(__('system.recent_ticket_responses_description'))
            ->paginated([5, 10, 15])
            ->defaultPaginationPageOption(5);
    }
}

