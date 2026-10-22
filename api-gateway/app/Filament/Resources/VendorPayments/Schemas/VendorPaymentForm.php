<?php

namespace App\Filament\Resources\VendorPayments\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;

class VendorPaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('restaurant_id')
                ->relationship('restaurant', 'name')
                ->searchable()
                ->required(),
            TextInput::make('payment_period')->required(),
            DatePicker::make('week_start')->required(),
            DatePicker::make('week_end')->required(),
            TextInput::make('total_order_value')->numeric()->prefix('₹'),
            TextInput::make('total_discounts')->numeric()->prefix('₹'),
            TextInput::make('total_commission')->numeric()->prefix('₹'),
            TextInput::make('commission_gst')->numeric()->prefix('₹'),
            TextInput::make('total_sponsorship')->numeric()->prefix('₹'),
            TextInput::make('sponsorship_gst')->numeric()->prefix('₹'),
            TextInput::make('net_payable')->numeric()->prefix('₹'),
            Select::make('status')
                ->options(['pending_approval' => 'Pending Approval', 'approved' => 'Approved', 'completed' => 'Completed'])
                ->required(),
            TextInput::make('utr_number'),
            Textarea::make('admin_notes'),
        ]);
    }
}
