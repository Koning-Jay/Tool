<?php

namespace App\Filament\Resources\MagentoResource\Pages;

use App\Filament\Resources\MagentoResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;
use App\Models\Check;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Notifications\Notification;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;

class ViewMagento extends ViewRecord
{
    protected static string $resource = MagentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Bewerken')
                ->icon('heroicon-o-pencil')
                ->color('primary'),
            Actions\DeleteAction::make()
                ->label('Verwijderen')
                ->icon('heroicon-o-trash')
                ->color('danger'),

            Actions\Action::make('check_now')
                ->label('Nu controleren')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $record = $this->getRecord();
                    
                    // Check primary URL
                    $primaryResult = MagentoResource::checkWebsiteStatus($record->url, 'primary');
                    Check::create([
                        'magento_id' => $record->id,
                        'url_type' => 'primary',
                        'status' => $primaryResult['status'],
                        'checked_at' => now(),
                    ]);
                    
                    // Check secondary URL if present
                    if (!empty($record->secondary_url)) {
                        $secondaryResult = MagentoResource::checkWebsiteStatus($record->secondary_url, 'secondary');
                        Check::create([
                            'magento_id' => $record->id,
                            'url_type' => 'secondary',
                            'status' => $secondaryResult['status'],
                            'checked_at' => now(),
                        ]);
                    }
                    
                    // Check tertiary URL if present
                    if (!empty($record->tertiary_url)) {
                        $tertiaryResult = MagentoResource::checkWebsiteStatus($record->tertiary_url, 'tertiary');
                        Check::create([
                            'magento_id' => $record->id,
                            'url_type' => 'tertiary',
                            'status' => $tertiaryResult['status'],
                            'checked_at' => now(),
                        ]);
                    }

                    Notification::make()
                        ->title('Website Checked')
                        ->body("All URLs for {$record->name} have been checked.")
                        ->success()
                        ->send();
                        
                    // Force a reload of the page to refresh the data
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $record]));
                })
                ->color('primary'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Website Info')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Naam'),
                        
                        Section::make('Primary URL')
                            ->schema([
                                TextEntry::make('url')
                                    ->label('Primary URL')
                                    ->url(fn ($record) => $record->url)
                                    ->openUrlInNewTab(),
                                
                                TextEntry::make('primary_status')
                                    ->label('Status')
                                    ->state(function ($record) {
                                        $latestCheck = $record->checks()
                                            ->where('url_type', 'primary')
                                            ->latest('checked_at')
                                            ->first();
                                        
                                        return $latestCheck 
                                            ? $latestCheck->status 
                                            : MagentoResource::checkWebsiteStatus($record->url, 'primary')['status'];
                                    })
                                    ->badge()
                                    ->color(fn (string $state): string => $state === 'Live' ? 'success' : 'danger'),
                                
                                TextEntry::make('primary_last_checked')
                                    ->label('Laatste controle')
                                    ->state(function ($record) {
                                        $latestCheck = $record->checks()
                                            ->where('url_type', 'primary')
                                            ->latest('checked_at')
                                            ->first();
                                        
                                        return $latestCheck ? $latestCheck->checked_at->diffForHumans() : 'Nooit';
                                    }),
                            ]),
                        
                        Section::make('Secondary URL')
                            ->hidden(fn ($record) => empty($record->secondary_url))
                            ->schema([
                                TextEntry::make('secondary_url')
                                    ->label('Secondary URL')
                                    ->url(fn ($record) => $record->secondary_url)
                                    ->openUrlInNewTab(),
                                
                                TextEntry::make('secondary_status')
                                    ->label('Status')
                                    ->state(function ($record) {
                                        if (empty($record->secondary_url)) {
                                            return 'N/A';
                                        }
                                        
                                        $latestCheck = $record->checks()
                                            ->where('url_type', 'secondary')
                                            ->latest('checked_at')
                                            ->first();
                                        
                                        return $latestCheck 
                                            ? $latestCheck->status 
                                            : MagentoResource::checkWebsiteStatus($record->secondary_url, 'secondary')['status'];
                                    })
                                    ->badge()
                                    ->color(function (string $state): string {
                                        if ($state === 'N/A') return 'gray';
                                        return $state === 'Live' ? 'success' : 'danger';
                                    }),
                                
                                TextEntry::make('secondary_last_checked')
                                    ->label('Laatste controle')
                                    ->state(function ($record) {
                                        if (empty($record->secondary_url)) {
                                            return 'N/A';
                                        }
                                        
                                        $latestCheck = $record->checks()
                                            ->where('url_type', 'secondary')
                                            ->latest('checked_at')
                                            ->first();
                                        
                                        return $latestCheck ? $latestCheck->checked_at->diffForHumans() : 'Nooit';
                                    }),
                            ]),
                        
                        Section::make('Tertiary URL')
                            ->hidden(fn ($record) => empty($record->tertiary_url))
                            ->schema([
                                TextEntry::make('tertiary_url')
                                    ->label('Tertiary URL')
                                    ->url(fn ($record) => $record->tertiary_url)
                                    ->openUrlInNewTab(),
                                
                                TextEntry::make('tertiary_status')
                                    ->label('Status')
                                    ->state(function ($record) {
                                        if (empty($record->tertiary_url)) {
                                            return 'N/A';
                                        }
                                        
                                        $latestCheck = $record->checks()
                                            ->where('url_type', 'tertiary')
                                            ->latest('checked_at')
                                            ->first();
                                        
                                        return $latestCheck 
                                            ? $latestCheck->status 
                                            : MagentoResource::checkWebsiteStatus($record->tertiary_url, 'tertiary')['status'];
                                    })
                                    ->badge()
                                    ->color(function (string $state): string {
                                        if ($state === 'N/A') return 'gray';
                                        return $state === 'Live' ? 'success' : 'danger';
                                    }),
                                
                                TextEntry::make('tertiary_last_checked')
                                    ->label('Laatste controle')
                                    ->state(function ($record) {
                                        if (empty($record->tertiary_url)) {
                                            return 'N/A';
                                        }
                                        
                                        $latestCheck = $record->checks()
                                            ->where('url_type', 'tertiary')
                                            ->latest('checked_at')
                                            ->first();
                                        
                                        return $latestCheck ? $latestCheck->checked_at->diffForHumans() : 'Nooit';
                                    }),
                            ]),
                        
                        TextEntry::make('api_key')
                            ->label('API Key')
                            ->visible(fn ($record) => filled($record->api_key)),
                        
                        Section::make('Assigned Custom Checks')
                            ->schema([
                                TextEntry::make('customchecks')
                                    ->label('')
                                    ->listWithLineBreaks()
                                    ->formatStateUsing(function ($record) {
                                        $checks = $record->customchecks;
                                        
                                        if ($checks->isEmpty()) {
                                            return 'No custom checks assigned';
                                        }
                                        
                                        return $checks->map(function ($check) {
                                            $severity = match($check->alert_severity) {
                                                'low' => 'Low Severity -',
                                                'medium' => 'Medium Severity -',
                                                'high' => 'High Severity -',
                                                'critical' => 'Critical Severity -',
                                                default => 'Unknown Severity -'
                                          
                                            };
                                            return "{$severity} " . $check->name;
                                        });
                                    }),
                            ]),
                        
                        TextEntry::make('created_at')
                            ->label('Aangemaakt op')
                            ->dateTime(),
                        
                        TextEntry::make('updated_at')
                            ->label('Laatste update')
                            ->dateTime(),
                    ]),
                
                // Fixed Tabs component with improved debugging
                Tabs::make('Recent Checks')
                    ->tabs([
                        Tab::make('All URLs')
                            ->schema([
                                TextEntry::make('all_recent_checks')
                                    ->label('All Recent Checks')
                                    ->html()
                                    ->state(function ($record) {
                                        // Fetch more checks for debugging
                                        $checks = $record->checks()->latest('checked_at')->take(10)->get();
                                        
                                        if ($checks->isEmpty()) {
                                            return '<em>Geen recente controles</em>';
                                        }
                                        
                                        $html = '<div class="space-y-2">';
                                        foreach ($checks as $check) {
                                            $statusColor = $check->status === 'Live' ? 'text-green-600' : 'text-red-600';
                                            $urlType = ucfirst($check->url_type);
                                            $html .= '<div class="flex justify-between">';
                                            $html .= '<span>' . $check->checked_at->format('d-m-Y H:i:s') . '</span>';
                                            $html .= '<span class="mx-4 text-gray-600">' . $urlType . ' URL</span>';
                                            $html .= '<span class="' . $statusColor . ' font-medium">' . $check->status . '</span>';
                                            $html .= '</div>';
                                        }
                                        $html .= '</div>';
                                        
                                        return $html;
                                    })
                            ]),
                        
                        Tab::make('URL .1')
                            ->schema([
                                TextEntry::make('primary_recent_checks')
                                    ->label('Primary URL Checks')
                                    ->html()
                                    ->state(function ($record) {
                                        $checks = $record->checks()
                                            ->where('url_type', 'primary')
                                            ->latest('checked_at')
                                            ->take(10)
                                            ->get();
                                        
                                        if ($checks->isEmpty()) {
                                            return '<em>Geen recente controles voor Primary URL</em>';
                                        }
                                        
                                        $html = '<div class="space-y-2">';
                                        foreach ($checks as $check) {
                                            $statusColor = $check->status === 'Live' ? 'text-green-600' : 'text-red-600';
                                            $html .= '<div class="flex justify-between">';
                                            $html .= '<span>' . $check->checked_at->format('d-m-Y H:i:s') . '</span>';
                                            $html .= '<span class="' . $statusColor . ' font-medium">' . $check->status . '</span>';
                                            $html .= '</div>';
                                        }
                                        $html .= '</div>';
                                        
                                        return $html;
                                    })
                            ]),
                        
                        Tab::make('URL .2')
                            ->schema([
                                TextEntry::make('secondary_recent_checks')
                                    ->label('Secondary URL Checks')
                                    ->html()
                                    ->state(function ($record) {
                                        if (empty($record->secondary_url)) {
                                            return '<em>Geen Secondary URL ingesteld</em>';
                                        }
                                        
                                        $checks = Check::where('magento_id', $record->id)
                                            ->where('url_type', 'secondary')
                                            ->latest('checked_at')
                                            ->take(10)
                                            ->get();
                                        
                                        $checkCount = $checks->count();
                                        
                                        if ($checkCount === 0) {
                                            return '<em>Geen recente controles voor Secondary URL</em>';
                                        }
                                        
                                        $html = '<div class="space-y-2">';
                                        foreach ($checks as $check) {
                                            $statusColor = $check->status === 'Live' ? 'text-green-600' : 'text-red-600';
                                            $html .= '<div class="flex justify-between">';
                                            $html .= '<span>' . $check->checked_at->format('d-m-Y H:i:s') . '</span>';
                                            $html .= '<span class="' . $statusColor . ' font-medium">' . $check->status . '</span>';
                                            $html .= '</div>';
                                        }
                                        $html .= '</div>';
                                        
                                        return $html;
                                    })
                            ]),
                        
                        Tab::make('URL .3')
                            ->schema([
                                TextEntry::make('tertiary_recent_checks')
                                    ->label('Tertiary URL Checks')
                                    ->html()
                                    ->state(function ($record) {
                                        if (empty($record->tertiary_url)) {
                                            return '<em>Geen Tertiary URL ingesteld</em>';
                                        }
                                        
                                        // Get all tertiary checks to debug the issue
                                        $checks = Check::where('magento_id', $record->id)
                                            ->where('url_type', 'tertiary')
                                            ->latest('checked_at')
                                            ->take(10)
                                            ->get();
                                        
                                        // Output check count for debugging
                                        $checkCount = $checks->count();
                                        
                                        if ($checkCount === 0) {
                                            return '<em>Geen recente controles voor Tertiary URL</em>';
                                        }
                                        
                                        $html = '<div class="space-y-2">';
                                        foreach ($checks as $check) {
                                            $statusColor = $check->status === 'Live' ? 'text-green-600' : 'text-red-600';
                                            $html .= '<div class="flex justify-between">';
                                            $html .= '<span>' . $check->checked_at->format('d-m-Y H:i:s') . '</span>';
                                            $html .= '<span class="' . $statusColor . ' font-medium">' . $check->status . '</span>';
                                            $html .= '</div>';
                                        }
                                        $html .= '</div>';
                                        
                                        return $html;
                                    })
                            ]),
                    ])
            ]);
    }
}