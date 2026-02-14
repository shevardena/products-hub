<?php

namespace App\Filament\Resources\MerchantApis\Pages;

use App\Filament\Resources\MerchantApis\MerchantApiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMerchantApis extends ListRecords
{
    protected static string $resource = MerchantApiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
