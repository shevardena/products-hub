<?php

namespace App\Filament\Resources\MerchantApis\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MerchantApiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Select::make('merchant_id')
                    ->relationship('merchant', 'name')
                    ->required()
                    ->searchable(),

                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('base_url')
                    ->url()
                    ->required()
                    ->columnSpanFull(),

                Select::make('entity_type')
                    ->options([
                        'products' => 'Products',
                        'categories' => 'Categories',
                    ])
                    ->required()
                    ->default('products'),

                TextInput::make('response_data_path')
                    ->label('Response Data Path')
                    ->placeholder('data.items')
                    ->helperText('Path to array in API response'),

                Toggle::make('contains_category_data')
                    ->label('Product API Contains Category Data')
                    ->visible(fn ($get) => $get('entity_type') === 'products'),

                TextInput::make('category_data_path')
                    ->label('Category Data Path')
                    ->placeholder('categories')
                    ->visible(fn ($get) =>
                        $get('entity_type') === 'products' &&
                        $get('contains_category_data')
                    ),

                TextInput::make('image_data_path')
                    ->label('Image Data Path')
                    ->placeholder('images')
                    ->visible(fn ($get) => $get('entity_type') === 'products'),

                Repeater::make('field_mappings')
                    ->schema([
                        TextInput::make('database_column')
                            ->label('Database Column')
                            ->required(),

                        TextInput::make('response_field')
                            ->label('Response Field')
                            ->required(),
                    ])
                    ->columns(2)
                    ->default([])
                    ->columnSpanFull(),


                Select::make('auth_type')
                    ->options([
                        'none' => 'None',
                        'token' => 'Token',
                        'basic' => 'Basic',
                        'bearer' => 'Bearer',
                    ])
                    ->default('none')
                    ->required(),

                KeyValue::make('auth_payload')
                    ->label('Auth Payload')
                    ->visible(fn ($get) => $get('auth_type') !== 'none')
                    ->columnSpanFull(),

                TextInput::make('sync_interval_minutes')
                    ->numeric()
                    ->required()
                    ->default(60),

                DateTimePicker::make('last_synced_at'),

                Toggle::make('is_active')
                    ->required()
                    ->default(true),

            ]);
    }
}
