<?php

namespace App\Filament\Resources\Invoices\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Placeholder;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('restaurant_id')
                ->relationship('restaurant', 'name')
                ->searchable()
                ->required(),
            Select::make('type')
                ->options(['commission' => 'Commission', 'sponsorship' => 'Sponsorship'])
                ->required(),
            TextInput::make('invoice_number')->required()->readOnly(),
            DatePicker::make('period_start')->required(),
            DatePicker::make('period_end')->required(),
            TextInput::make('amount')->numeric()->required()->prefix('₹'),
            TextInput::make('gst_rate')->numeric()->required()->suffix('%'),
            TextInput::make('gst_amount')->numeric()->required()->prefix('₹'),
            TextInput::make('total_amount')->numeric()->required()->prefix('₹'),
            TextInput::make('commission_rate')->numeric()->suffix('%')->nullable(),
            Select::make('status')
                ->options(['pending' => 'Pending', 'approved' => 'Approved', 'paid' => 'Paid', 'cancelled' => 'Cancelled'])
                ->required(),
            Textarea::make('admin_notes'),
        ]);
    }
}
