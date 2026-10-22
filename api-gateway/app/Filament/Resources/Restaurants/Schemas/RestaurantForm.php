<?php

namespace App\Filament\Resources\Restaurants\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RestaurantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('logo_url')
                    ->url(),
                Toggle::make('is_active')
                    ->required(),
                \Filament\Forms\Components\KeyValue::make('operating_hours')
                    ->columnSpanFull(),
            ]);
    }
}
