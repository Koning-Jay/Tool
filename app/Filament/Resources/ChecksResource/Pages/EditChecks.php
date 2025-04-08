<?php

namespace App\Filament\Resources\ChecksResource\Pages;

use App\Filament\Resources\ChecksResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChecks extends EditRecord
{
    protected static string $resource = ChecksResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
