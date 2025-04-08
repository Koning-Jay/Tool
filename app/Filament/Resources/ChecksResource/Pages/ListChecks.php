<?php

namespace App\Filament\Resources\ChecksResource\Pages;

use App\Filament\Resources\ChecksResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListChecks extends ListRecords
{
    protected static string $resource = ChecksResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
