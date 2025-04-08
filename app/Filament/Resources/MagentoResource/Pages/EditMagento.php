<?php

namespace App\Filament\Resources\MagentoResource\Pages;

use App\Filament\Resources\MagentoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMagento extends EditRecord
{
    protected static string $resource = MagentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
