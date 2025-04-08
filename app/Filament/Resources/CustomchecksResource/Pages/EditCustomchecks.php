<?php

namespace App\Filament\Resources\CustomchecksResource\Pages;

use App\Filament\Resources\CustomchecksResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomchecks extends EditRecord
{
    protected static string $resource = CustomchecksResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
