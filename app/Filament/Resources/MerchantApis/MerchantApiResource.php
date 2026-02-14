<?php

namespace App\Filament\Resources\MerchantApis;

use App\Filament\Resources\MerchantApis\Pages\CreateMerchantApi;
use App\Filament\Resources\MerchantApis\Pages\EditMerchantApi;
use App\Filament\Resources\MerchantApis\Pages\ListMerchantApis;
use App\Filament\Resources\MerchantApis\Schemas\MerchantApiForm;
use App\Filament\Resources\MerchantApis\Tables\MerchantApisTable;
use App\Models\MerchantApi;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MerchantApiResource extends Resource
{
    protected static ?string $model = MerchantApi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Merchant Api';

    public static function form(Schema $schema): Schema
    {
        return MerchantApiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MerchantApisTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMerchantApis::route('/'),
            'create' => CreateMerchantApi::route('/create'),
            'edit' => EditMerchantApi::route('/{record}/edit'),
        ];
    }
}
