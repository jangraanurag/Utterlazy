<?php

namespace App\Filament\Resources\Invoices\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')->searchable(),
                TextColumn::make('restaurant.name')->searchable(),
                TextColumn::make('type')->badge()->color(fn($state) => $state === 'commission' ? 'warning' : 'info'),
                TextColumn::make('period_start')->date()->sortable(),
                TextColumn::make('period_end')->date()->sortable(),
                TextColumn::make('total_amount')->money('INR')->sortable(),
                BadgeColumn::make('status')
                    ->colors(['pending' => 'warning', 'approved' => 'success', 'paid' => 'primary', 'cancelled' => 'danger']),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
