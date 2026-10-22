<?php

namespace App\Filament\Resources\VendorPayments\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;

class VendorPaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('restaurant.name')->searchable(),
                TextColumn::make('payment_period')->sortable(),
                TextColumn::make('week_start')->date()->sortable(),
                TextColumn::make('week_end')->date()->sortable(),
                TextColumn::make('net_payable')->money('INR')->sortable(),
                BadgeColumn::make('status')
                    ->colors(['pending_approval' => 'warning', 'approved' => 'success', 'completed' => 'primary']),
                TextColumn::make('utr_number')->searchable(),
                TextColumn::make('paid_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
