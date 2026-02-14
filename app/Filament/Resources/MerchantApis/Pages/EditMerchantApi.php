<?php

namespace App\Filament\Resources\MerchantApis\Pages;

use App\Filament\Resources\MerchantApis\MerchantApiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMerchantApi extends EditRecord
{
    protected static string $resource = MerchantApiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
