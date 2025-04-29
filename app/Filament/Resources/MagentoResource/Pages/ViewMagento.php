<?php

namespace App\Filament\Resources\MagentoResource\Pages;

use App\Filament\Resources\MagentoResource;
use App\Filament\Resources\CustomchecksResource;
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
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\Actions as InfolistActions;
use Filament\Infolists\Components\Actions\Action as InfolistAction;

class ViewMagento extends ViewRecord
{
    protected static string $resource = MagentoResource::class;
    
    // Add a property to store the selected number of checks to display
    public int $checksLimit = 10;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Edit')
                ->icon('heroicon-o-pencil')
                ->color('primary'),
            Actions\DeleteAction::make()
                ->label('Delete')
                ->icon('heroicon-o-trash')
                ->color('danger'),

            Actions\Action::make('check_now')
                ->label('Check Now')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $record = $this->getRecord();
                    
                    $primaryResult = MagentoResource::checkWebsiteStatus($record->url, 'primary');
                    Check::create([
                        'magento_id' => $record->id,
                        'url_type' => 'primary',
                        'status' => $primaryResult['status'],
                        'checked_at' => now(),
                    ]);
                    
                    if (!empty($record->secondary_url)) {
                        $secondaryResult = MagentoResource::checkWebsiteStatus($record->secondary_url, 'secondary');
                        Check::create([
                            'magento_id' => $record->id,
                            'url_type' => 'secondary',
                            'status' => $secondaryResult['status'],
                            'checked_at' => now(),
                        ]);
                    }
                    
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
                            ->label('Name'),
                        
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
                                    ->label('Last Check')
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
                                    ->label('Last Check')
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
                                    ->label('Last Check')
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
                        
                            Section::make('System Usage')
                            ->schema([
                                TextEntry::make('ram_usage')
                                    ->label('RAM Usage')
                                    ->state(function () {
                                        $data = MagentoResource::getSystemTestData();
    
                                        if (!isset($data['ram']) || !isset($data['ram']['usage_percent'])) {
                                            return 'No RAM Data';
                                        }
    
                                        return $data['ram']['usage_percent'] . '% (' . $data['ram']['used_mb'] . ' MB used of ' . $data['ram']['total_mb'] . ' MB)';
                                    }),
    
                                TextEntry::make('disk_usage')
                                    ->label('Disk Usage')
                                    ->state(function () {
                                        $data = MagentoResource::getSystemTestData();
    
                                        if (!isset($data['disk']) || !isset($data['disk']['usage_percent'])) {
                                            return 'No Disk Data';
                                        }
    
                                        return $data['disk']['usage_percent'] . '% (' . $data['disk']['used_gb'] . ' GB used of ' . $data['disk']['total_gb'] . ' GB)';
                                    }),
                            ]),

                            
                        TextEntry::make('created_at')
                            ->label('Made on')
                            ->dateTime(),
                        
                        TextEntry::make('updated_at')
                            ->label('Last updated')
                            ->dateTime(),
                    ]),
                
                Tabs::make('magento_information_tabs')
                ->columnSpanFull()
                    ->tabs([
                        Tab::make('Website Status')
                            ->schema([
                                Section::make('Recent Checks')
                                    ->headerActions([
                                        InfolistAction::make('change_checks_limit')
                                            ->label(function() {
                                                return 'Show Checks: ' . $this->checksLimit;
                                            })
                                            ->icon('heroicon-o-adjustments-horizontal')
                                            ->form([
                                                Select::make('checksLimit')
                                                    ->label('Number of checks to display')
                                                    ->options([
                                                        5 => '5 checks',
                                                        10 => '10 checks',
                                                        25 => '25 checks',
                                                        50 => '50 checks',
                                                        100 => '100 checks',
                                                    ])
                                                    ->default(function($livewire) {
                                                        return $livewire->checksLimit;
                                                    })
                                                    ->required(),
                                            ])
                                            ->action(function (array $data): void {
                                                $this->checksLimit = $data['checksLimit'];
                                                
                                                Notification::make()
                                                    ->title('Display Updated')
                                                    ->body("Now showing {$this->checksLimit} recent checks.")
                                                    ->success()
                                                    ->send();
                                            })
                                            ->color('gray'),
                                    ])
                                    ->schema([
                                        Tabs::make('checks_tabs')
                                            ->tabs([
                                                Tab::make('All URLs')
                                                    ->schema([
                                                        TextEntry::make('all_recent_checks')
                                                            ->label(function($livewire) {
                                                                return "Showing {$livewire->checksLimit} Recent Checks";
                                                            })
                                                            ->html()
                                                            ->state(function ($record) {
                                                                $checks = $record->checks()->latest('checked_at')->take($this->checksLimit)->get();
                                                                
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
                                                            ->label(function($livewire) {
                                                                return "Showing {$livewire->checksLimit} Primary URL Checks";
                                                            })
                                                            ->html()
                                                            ->state(function ($record) {
                                                                $checks = $record->checks()
                                                                    ->where('url_type', 'primary')
                                                                    ->latest('checked_at')
                                                                    ->take($this->checksLimit)
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
                                                            ->label(function($livewire) {
                                                                return "Showing {$livewire->checksLimit} Secondary URL Checks";
                                                            })
                                                            ->html()
                                                            ->state(function ($record) {
                                                                if (empty($record->secondary_url)) {
                                                                    return '<em>Geen Secondary URL ingesteld</em>';
                                                                }
                                                                
                                                                $checks = Check::where('magento_id', $record->id)
                                                                    ->where('url_type', 'secondary')
                                                                    ->latest('checked_at')
                                                                    ->take($this->checksLimit)
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
                                                            ->label(function($livewire) {
                                                                return "Showing {$livewire->checksLimit} Tertiary URL Checks";
                                                            })
                                                            ->html()
                                                            ->state(function ($record) {
                                                                if (empty($record->tertiary_url)) {
                                                                    return '<em>Geen Tertiary URL ingesteld</em>';
                                                                }
                                                                
                                                                $checks = Check::where('magento_id', $record->id)
                                                                    ->where('url_type', 'tertiary')
                                                                    ->latest('checked_at')
                                                                    ->take($this->checksLimit)
                                                                    ->get();
                                                                
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
                                    ])
                            ]),
                            
                            Tab::make('Custom Checks')
                                ->schema([
                                    Section::make('Manage Custom Checks')
                                        ->description('View and manage custom checks assigned to this Magento page')
                                        ->headerActions([
                                            InfolistAction::make('add_custom_check')
                                                ->label('Add Custom Check')
                                                ->icon('heroicon-o-plus')
                                                ->url(function ($record) {
                                                    return CustomchecksResource::getUrl('create', [
                                                        'preselect_magento' => $record->id,
                                                    ]);
                                                })
                                                ->color('primary'),
                                                
                                            InfolistAction::make('view_all_custom_checks')
                                                ->label('View All Custom Checks')
                                                ->icon('heroicon-o-clipboard-document-list')
                                                ->url(CustomchecksResource::getUrl('index'))
                                                ->color('gray'),
                                        ])
                                        ->columnSpanFull() // Ensure the section spans the full width
                                        ->schema([
                                            TextEntry::make('assigned_custom_checks')
                                                ->label('Assigned Custom Checks')
                                                ->columnSpanFull() // Ensure the content spans the full width
                                                ->html()
                                                ->state(function ($record) {
                                                    $checks = $record->customchecks;
                            
                                                    if ($checks->isEmpty()) {
                                                        return '<div class="text-gray-500 italic p-6 text-center text-lg">No custom checks assigned to this Magento page</div>';
                                                    }
                            
                                                    // Adjust grid layout to 4 columns
                                                    $html = '<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 w-full">';
                                                    foreach ($checks as $check) {
                                                        $typeColor = match($check->check_type) {
                                                            'cpu' => 'bg-blue-100 text-blue-800',
                                                            'ram' => 'bg-red-100 text-red-800',
                                                            'sales' => 'bg-green-100 text-green-800',
                                                            default => 'bg-gray-100 text-gray-800'
                                                        };
                                                    
                                                        $typeName = match($check->check_type) {
                                                            'cpu' => 'CPU Usage',
                                                            'ram' => 'Memory Usage',
                                                            'sales' => 'Sales Performance',
                                                            default => $check->check_type
                                                        };
                                                    
                                                        $suffix = match ($check->check_type) {
                                                            'cpu', 'ram' => '%',
                                                            'sales' => ' Sales',
                                                            default => '',
                                                        };
                                                    
                                                        $status = $check->is_active 
                                                            ? '<span class="px-3 py-1 text-sm font-medium rounded-full bg-green-100 text-green-800">Active</span>' 
                                                            : '<span class="px-3 py-1 text-sm font-medium rounded-full bg-gray-100 text-gray-800">Inactive</span>';
                                                    
                                                        $html .= '<div class="border rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow duration-200">';
                                                        $html .= '<div class="flex items-center justify-between mb-4">';
                                                        $html .= '<div class="text-2xl font-medium">' . $check->name . '</div>';
                                                        $html .= $status;
                                                        $html .= '</div>';
                                                    
                                                        $html .= '<div class="space-y-4 mb-4">';
                                                    
                                                        $html .= '<div class="flex flex-col space-y-1">';
                                                        $html .= '<span class="text-sm text-gray-500">Type</span>';
                                                        $html .= '<span class="px-3 py-2 text-base font-medium rounded-md inline-block ' . $typeColor . '">' . $typeName . '</span>';
                                                        $html .= '</div>';
                                                    
                                                        $html .= '<div class="flex flex-col space-y-1">';
                                                        $html .= '<span class="text-sm text-gray-500">Operator</span>';
                                                        $html .= '<span class="text-base font-medium">' . $check->comparison_operator . '</span>';
                                                        $html .= '</div>';
                                                    
                                                        $html .= '<div class="flex flex-col space-y-1">';
                                                        $html .= '<span class="text-sm text-gray-500">Threshold</span>';
                                                        $html .= '<span class="text-base font-medium">' . $check->threshold_value . $suffix . '</span>';
                                                        $html .= '</div>';
                                                    
                                                        $html .= '</div>';
                                                    
                                                        $html .= '<div class="mt-6">';
                                                        $html .= '<a href="' . CustomchecksResource::getUrl('edit', ['record' => $check]) . '" 
                                                            class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm 
                                                            text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 
                                                            focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full justify-center">';
                                                        $html .= '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">';
                                                        $html .= '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>';
                                                        $html .= '</svg>';
                                                        $html .= 'Edit Check';
                                                        $html .= '</a>';
                                                        $html .= '</div>';
                                                    
                                                        $html .= '</div>';
                                                    }
                                                    $html .= '</div>';
                            
                                                    return $html;
                                                }),
                                        ]),
                                ])
                                ->columnSpanFull() // Ensure the tab spans the full width
                    ]),
            ]);
    }
}