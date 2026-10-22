<?php

namespace App\Filament\Resources\MenuItems\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MenuItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('restaurant_id')
                    ->label('Restaurant')
                    ->options(\App\Models\Restaurant::pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                \Filament\Forms\Components\Select::make('category_id')
                    ->label('Category')
                    ->options(\App\Models\Category::pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),
                TextInput::make('name')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                FileUpload::make('image_url')
                    ->image(),
                Toggle::make('is_available')
                    ->required(),
                \Filament\Forms\Components\KeyValue::make('modifiers')
                    ->columnSpanFull(),
            ]);
    }
}
