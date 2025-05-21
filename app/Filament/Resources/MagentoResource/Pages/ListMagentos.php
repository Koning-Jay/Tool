<?php

namespace App\Filament\Resources\MagentoResource\Pages;

use App\Filament\Resources\MagentoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Support\Facades\Auth;

header("refresh: 1800;");

class ListMagentos extends ListRecords
{
    protected static string $resource = MagentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                            ->visible(fn () => Auth::user()?->role === 'admin'),
            Actions\Action::make('check_all_websites')
                ->label('Alle websites checken')
                ->icon('heroicon-o-globe-alt')
                ->action(function () {
                    \Illuminate\Support\Facades\Artisan::call('magento:check-status');

                    Notification::make()
                        ->title('Checken is begonnen')
                        ->body('Alle magento websites worden gecontroleerd. Dit kan even duren.')
                        ->success()
                        ->send();
                })
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Alle websites controleren?')
                ->modalDescription('Weet je zeker dat je alle websites wilt controleren? Dit kan even duren.'),
        ];
    }
    
}