<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Magento;
use App\Models\Customchecks;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget\Card;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Widgets\StatsOverviewWidget;

class Dashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?string $title = 'Dashboard';
    protected static string $view = 'filament.pages.dashboard';
    protected static ?int $navigationSort = 1;

    public function getStats(): array
    {
        $totalMagento = Magento::count();
        $activeMagento = Magento::whereHas('customchecks', function ($query) {
            $query->where('is_active', true);
        })->count();

        $totalChecks = Customchecks::count();
        $activeChecks = Customchecks::where('is_active', true)->count();

        $criticalAlerts = Customchecks::where('alert_severity', 'critical')
            ->where('is_active', true)
            ->count();

        return [
            Stat::make('Total Magento Sites', $totalMagento)
                ->description('Total monitored sites')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('success'),

            Stat::make('Active Sites', $activeMagento)
                ->description('Sites with active checks')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('info'),

            Stat::make('Total Checks', $totalChecks)
                ->description('Custom checks configured')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('warning'),
         
        ];
    }

    protected function getViewData(): array
    {
        return [
            'stats' => $this->getStats(),
            'recentMagentos' => Magento::latest()->take(3)->get(),
            'recentChecks' => Customchecks::latest()->take(3)->get(),
        ];
    }
}

echo "Dashboard page loaded successfully.";


