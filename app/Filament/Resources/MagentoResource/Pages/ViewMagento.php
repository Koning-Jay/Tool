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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ViewMagento extends ViewRecord
{
    protected static string $resource = MagentoResource::class;
    
    // Add a property to store the selected number of checks to display
    public int $checksLimit = 10;
    protected string $notificationEmail = 'Jay@wedigify.nl';


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

                        
                        
                        $this->sendMailchimpNotification(
                            "Test Alert: Manual Check Triggered",
                            "This is a test alert triggered manually for {$record->name}.\n\n" .
                            "This alert was generated on " . now()->format('Y-m-d H:i:s')
                        );
                
                        Notification::make()
                            ->title('Website Checked')
                            ->body("All URLs for {$record->name} have been checked.")
                            ->success()
                            ->send();
                
                        return redirect($this->getResource()::getUrl('view', ['record' => $record]));
                    })
                ->color('primary'),
                
            Actions\Action::make('refresh_metrics')
                ->label('Refresh Metrics')
                ->icon('heroicon-o-chart-bar')
                ->action(function () {
                    Notification::make()
                        ->title('System Metrics Refreshed')
                        ->body("The system metrics have been refreshed.")
                        ->success()
                        ->send();

                        
                        
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->getRecord()]));
                })
                ->color('success'),
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
                        
                            Section::make('Current System Usage')
                            ->schema([
                                TextEntry::make('ram_usage')
                                    ->label('RAM Usage')
                                    ->state(function () {
                                        $data = MagentoResource::getSystemTestData();
                        
                                        if (!isset($data['ram'])) {
                                            return 'No RAM Data';
                                        }
                                        
                                        // Calculate RAM usage percentage
                                        $used = $data['ram']['used_gb'] ?? 0;
                                        $total = $data['ram']['total_gb'] ?? 1; // Avoid division by zero
                                        $usagePercent = round(($used / $total) * 100, 2);
                        
                                        return $usagePercent . '% (' . $used . ' MB used of ' . $total . ' MB)';
                                    }),
                        
                                TextEntry::make('disk_usage')
                                    ->label('Disk Usage')
                                    ->state(function () {
                                        $data = MagentoResource::getSystemTestData();
                        
                                        if (!isset($data['disk'])) {
                                            return 'No Disk Data';
                                        }
                                        
                                        // Calculate Disk usage percentage
                                        $used = $data['disk']['used_gb'] ?? 0;
                                        $total = $data['disk']['total_gb'] ?? 1; // Avoid division by zero
                                        $usagePercent = round(($used / $total) * 100, 2);
                        
                                        return $usagePercent . '% (' . $used . ' GB used of ' . $total . ' GB)';
                                    }),
                                    
                                TextEntry::make('cpu_usage')
                                    ->label('CPU Usage')
                                    ->state(function () {
                                        $data = MagentoResource::getSystemTestData();
                        
                                        if (!isset($data['cpu'])) {
                                            return 'No CPU Data';
                                        }
                                        
                                        // Calculate CPU usage percentage using the same method as in the command
                                        $used = $data['cpu']['used_gb'] ?? 0;
                                        $total = $data['cpu']['total_gb'] ?? 1; // Avoid division by zero
                                        $usagePercent = round(($used / $total) * 100, 2);
                        
                                        return $usagePercent . '% (' . $used . ' GB used of ' . $total . ' GB)';
                                    })
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
                                        ->columnSpanFull()
                                        ->schema([
                                            TextEntry::make('assigned_custom_checks')
                                                ->label('Assigned Custom Checks')
                                                ->columnSpanFull()
                                                ->html()
                                                ->state(function ($record) {
                                                    $checks = $record->customchecks;
                            
                                                    if ($checks->isEmpty()) {
                                                        return '<div class="text-gray-500 italic p-6 text-center text-lg">No custom checks assigned to this Magento page</div>';
                                                    }
                                                    
                                                    // Get current system metrics for comparison
                                                    $systemData = MagentoResource::getSystemTestData();
                                                    
                                                    // Adjust grid layout to 4 columns
                                                    $html = '<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 w-full">';
                                                    foreach ($checks as $check) {
                                                        // Skip CPU load check type as it's being removed
                                                        if ($check->check_type === 'cpu_load') {
                                                            continue;
                                                        }
                                                        
                                                        // Type color
                                                        $typeColor = match($check->check_type) {
                                                            'cpu' => 'bg-blue-100 text-blue-800',
                                                            'ram' => 'bg-red-100 text-red-800',
                                                            'disk' => 'bg-yellow-100 text-yellow-800',
                                                            default => 'bg-gray-100 text-gray-800'
                                                        };
                                                    
                                                        // Type name
                                                        $typeName = match($check->check_type) {
                                                            'cpu' => 'CPU Usage',
                                                            'ram' => 'Ram Usage',
                                                            'disk' => 'Disk Space',
                                                            default => $check->check_type
                                                        };
                                                    
                                                        $suffix = match ($check->check_type) {
                                                            'cpu', 'ram', 'disk' => '%',
                                                            default => '',
                                                        };
                                                    
                                                        $status = $check->is_active 
                                                            ? '<span class="px-3 py-1 text-sm font-medium rounded-full bg-green-100 text-green-800">Active</span>' 
                                                            : '<span class="px-3 py-1 text-sm font-medium rounded-full bg-gray-100 text-gray-800">Inactive</span>';
                                                        
                                                        // Get current metric value based on check type
                                                        $currentValue = null;
                                                        $isTriggered = false;
                                                        
                                                        if (in_array($check->check_type, ['cpu', 'ram', 'disk'])) {
                                                            $used = $systemData[$check->check_type]['used_gb'] ?? 0;
                                                            $total = $systemData[$check->check_type]['total_gb'] ?? 1;
                                                            $usagePercent = round(($used / $total) * 100, 2);
                                                        
                                                            $currentValue = $usagePercent;
                                                        
                                                            $isTriggered = match ($check->comparison_operator) {
                                                                'Greater than' => $currentValue > $check->threshold_value,
                                                                'Less than' => $currentValue < $check->threshold_value,
                                                                'Equal to' => $currentValue == $check->threshold_value,
                                                                default => false,
                                                            };
                                                        
                                                            if ($isTriggered && $check->is_active) {
                                                                $magento = $record->name;
                                                                $subject = "ALERT: {$check->name} check triggered for {$magento}";
                                                                $message = "The {$check->name} check has been triggered for {$magento}.\n\n" .
                                                                           "Current {$typeName}: {$currentValue}{$suffix} ({$used} GB of {$total} GB)\n" .
                                                                           "Threshold: {$check->comparison_operator} {$check->threshold_value}{$suffix}\n\n" .
                                                                           "This alert was generated on " . now()->format('Y-m-d H:i:s');
                                                        
                                                                $notificationSent = $this->sendMailchimpNotification($subject, $message);
                                                        
                                                                if ($notificationSent) {
                                                                    Notification::make()
                                                                        ->title('Alert Email Sent')
                                                                        ->body("An alert email for {$check->name} would be sent to {$this->notificationEmail}")
                                                                        ->warning()
                                                                        ->send();
                                                                }
                                                            }
                                                        
                                                            $valueHtml = "<strong>{$currentValue}{$suffix}</strong><br><small>{$used} GB of {$total} GB</small>";
                                                        } else {
                                                            $valueHtml = "<strong>N/A</strong>";
                                                        }
                                                        
                                                        
                                                        $html .= '<div class="border rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow duration-200 ' . ($isTriggered ? 'border-red-300 bg-red-50' : '') . '">';
                                                        
                                                        // Header with name and active status
                                                        $html .= '<div class="flex items-center justify-between mb-4">';
                                                        $html .= '<div class="text-2xl font-medium">' . $check->name . '</div>';
                                                        $html .= $status;
                                                        $html .= '</div>';
                                                    
                                                        $html .= '<div class="space-y-4 mb-4">';
                                                    
                                                        // Check type
                                                        $html .= '<div class="flex flex-col space-y-1">';
                                                        $html .= '<span class="text-sm text-gray-500">Type</span>';
                                                        $html .= '<span class="px-3 py-2 text-base font-medium rounded-md inline-block ' . $typeColor . '">' . $typeName . '</span>';
                                                        $html .= '</div>';
                                                    
                                                        // Check parameters
                                                        $html .= '<div class="flex flex-col space-y-1">';
                                                        $html .= '<span class="text-sm text-gray-500">Operator</span>';
                                                        $html .= '<span class="text-base font-medium">' . $check->comparison_operator . '</span>';
                                                        $html .= '</div>';
                                                    
                                                        $html .= '<div class="flex flex-col space-y-1">';
                                                        $html .= '<span class="text-sm text-gray-500">Threshold</span>';
                                                        $html .= '<span class="text-base font-medium">' . $check->threshold_value . $suffix . '</span>';
                                                        $html .= '</div>';
                                                        
                                                        // Current value and status
                                                        if ($currentValue !== null) {
                                                            $valueColor = $isTriggered ? 'text-red-600 font-bold' : 'text-green-600';
                                                            
                                                            // Added current metric value display
                                                            $html .= '<div class="flex flex-col space-y-1">';
                                                            $html .= '<span class="text-sm text-gray-500">Current Value</span>';
                                                            $html .= '<span class="text-base font-medium ' . $valueColor . '">' . $currentValue . $suffix . '</span>';
                                                            $html .= '</div>';
                                                            
                                                            $html .= '<div class="flex flex-col space-y-1 mt-2">';
                                                            $html .= '<span class="text-sm text-gray-500">Status</span>';
                                                            $html .= $isTriggered 
                                                                ? '<span class="px-3 py-1 text-sm font-medium rounded-full bg-red-100 text-red-800">Triggered</span>'
                                                                : '<span class="px-3 py-1 text-sm font-medium rounded-full bg-green-100 text-green-800">Normal</span>';
                                                            $html .= '</div>';
                                                        } else {
                                                            $html .= '<div class="flex flex-col space-y-1 mt-2">';
                                                            $html .= '<span class="text-sm text-gray-500">Current Value</span>';
                                                            $html .= '<span class="text-base font-medium text-gray-500">Not available</span>';
                                                            $html .= '</div>';
                                                        }
                                                    
                                                        $html .= '</div>';
                                                    
                                                        // Action buttons
                                                        $html .= '<div class="mt-6 flex space-x-2">';
                                                        
                                                        // Edit button
                                                        $html .= '<a href="' . CustomchecksResource::getUrl('edit', ['record' => $check]) . '" 
                                                            class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm 
                                                            text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 
                                                            focus:outline-none focus:ring-offset-2 focus:ring-indigo-500 w-full justify-center">';
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
                                ->columnSpanFull() 
                    ]),
            ]);
            
    }

    protected function sendMailchimpNotification($subject, $message)
    {
        try {
            // Log the notification for testing
            Log::info('ALERT NOTIFICATION WOULD BE SENT', [
                'subject' => $subject,
                'message' => $message,
                'to' => $this->notificationEmail
            ]);
            
            // Return true to simulate successful sending
            return true;
            
            /* Comment out actual Mailchimp sending for testing
            $mailchimpApiKey = config('services.mailchimp.api_key');
            $mailchimpServerPrefix = config('services.mailchimp.server_prefix');
            $fromEmail = config('services.mailchimp.from_email', 'notifications@wedigify.nl');
            $fromName = config('services.mailchimp.from_name', 'Wedigify Monitoring');
    
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $mailchimpApiKey,
                'Content-Type' => 'application/json',
            ])->post("https://{$mailchimpServerPrefix}.api.mailchimp.com/3.0/messages/send-template", [
                'template_name' => 'alert-notification',
                'template_content' => [],
                'message' => [
                    'subject' => $subject,
                    'from_email' => $fromEmail,
                    'from_name' => $fromName,
                    'to' => [
                        [
                            'email' => $this->notificationEmail,
                            'type' => 'to'
                        ]
                    ],
                    'global_merge_vars' => [
                        [
                            'name' => 'ALERT_MESSAGE',
                            'content' => $message
                        ]
                    ]
                ]
            ]);
    
            return $response->successful();
            */
        } catch (\Exception $e) {
            Log::error('Failed to send notification: ' . $e->getMessage());
            return false;
        }
    }
}