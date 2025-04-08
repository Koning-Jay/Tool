<?php

namespace App\Filament\Resources\CustomchecksResource\Pages;

use App\Filament\Resources\CustomchecksResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomchecks extends ListRecords
{
    protected static string $resource = CustomchecksResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
