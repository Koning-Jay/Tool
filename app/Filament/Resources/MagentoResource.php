<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MagentoResource\Pages;
use App\Models\Magento;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;
use Filament\Tables\Columns\TextColumn;
use App\Models\Check;
use Filament\Notifications\Notification;
use App\Notifications\WebsiteDownNotification;
use App\Notifications\CustomCheckNotification;
use Illuminate\Support\Facades\Notification as FacadesNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;



class MagentoResource extends Resource
{
    protected static ?string $model = Magento::class;
    protected static ?string $navigationIcon = 'heroicon-s-computer-desktop';
    protected static ?string $navigationGroup = 'Monitoring';
    protected static ?string $label = 'Domains';
    protected static ?string $navigationLabel = 'Domains';
    protected static ?string $title = 'Domains';
    protected static string $notificationEmail = 'Jay@wedigify.nl';

    // Add canCreate method to restrict domain creation to admin users only
    public static function canCreate(): bool
    {
        return Auth::user()?->role === 'admin';
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        $healthCheckFiles = self::getAvailableHealthCheckFiles();

        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        
                        Forms\Components\TextInput::make('url')
                            ->label('Primary URL')
                            ->required()
                            ->url()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('secondary_url')
                            ->label('Secondary URL (Optional)')
                            ->url()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('tertiary_url')
                            ->label('Tertiary URL (Optional)')
                            ->url()
                            ->maxLength(255),
                        
                        Forms\Components\Select::make('health_check_file')
                            ->label('Health Check File')
                            ->options($healthCheckFiles)
                            ->default('healthcheck.php')
                            ->helperText('Select which health check file to use for metrics')
                            ->required(),
                     
                        Forms\Components\TagsInput::make('notification_emails')
                            ->label('Notification Emails')
                            ->placeholder('Add email addresses for downtime alerts')
                            ->helperText('jay@wedigify.nl will always be included')
                            ->default(['jay@wedigify.nl'])
                            ->formatStateUsing(function ($state) {
                                return array_unique(array_merge(['jay@wedigify.nl'], $state ?? []));
                            })
                            ->disabled(fn ($state) => in_array('jay@wedigify.nl', $state ?? []))
                            ->separator(',')
                            ->columnSpan(2),
                    ])
                    ->columns(2)
            ]);
    }

    /**
     * @return array
     */
    protected static function getAvailableHealthCheckFiles(): array
    {
        try {
            // List the files we see in the screenshot
            $healthCheckFiles = [
                'healthcheck.php' => 'healthcheck.php',
                'Krale_healthcheck.php' => 'Krale_healthcheck.php',
                'shuz_healthcheck.php' => 'shuz_healthcheck.php',
                // system_testdata.json is not included as it's not a PHP file
            ];
            
            Log::info('Using health check files from storage/app directory: ' . json_encode(array_keys($healthCheckFiles)));
            
            // Also try to find any additional PHP files dynamically
            $directory = '';
            
            if (Storage::exists($directory)) {
                $files = Storage::files($directory);
                
                Log::info('Found ' . count($files) . ' files in storage/app directory');
                
                foreach ($files as $file) {
                    $fileName = basename($file);
                    
                    // Only add PHP files that aren't already in our list
                    if (pathinfo($fileName, PATHINFO_EXTENSION) === 'php' && 
                        !isset($healthCheckFiles[$fileName])) {
                        $healthCheckFiles[$fileName] = $fileName;
                        Log::info('Added additional PHP file: ' . $fileName);
                    }
                }
            } else {
                Log::warning("Storage app directory not found, using only predefined files");
            }
            
            Log::info('Final available health check files: ' . json_encode($healthCheckFiles));
            
            return $healthCheckFiles;
        } catch (\Exception $e) {
            Log::error('Error getting health check files: ' . $e->getMessage());
            
            return [
                'healthcheck.php' => 'healthcheck.php',
                'Krale_healthcheck.php' => 'Krale_healthcheck.php',
                'shuz_healthcheck.php' => 'shuz_healthcheck.php',
            ];
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),
       
                Tables\Columns\TextColumn::make('customchecks.name')
                    ->label('Assigned Custom Checks')
                    ->formatStateUsing(function ($record) {
                        $checks = $record->customchecks;
                
                        if ($checks->isEmpty()) {
                            return 'No custom checks assigned';
                        }
                
                        return $checks->pluck('name')->unique()->join(', ');
                    }),
    
                TextColumn::make('status')
                    ->label('Status')
                    ->state(function (Magento $record) {

                        $statuses = [];
                        
                        $primaryCheck = $record->checks()
                            ->where('url_type', 'primary')
                            ->latest('checked_at')
                            ->first();
                        $primaryStatus = $primaryCheck ? $primaryCheck->status : self::checkWebsiteStatus($record->url)['status'];
                        $statuses[] = $primaryStatus;
                        
                        if (!empty($record->secondary_url)) {
                            $secondaryCheck = $record->checks()
                                ->where('url_type', 'secondary')
                                ->latest('checked_at')
                                ->first();
                            $secondaryStatus = $secondaryCheck ? $secondaryCheck->status : self::checkWebsiteStatus($record->secondary_url, 'secondary')['status'];
                            $statuses[] = $secondaryStatus;
                        }
                        
                        if (!empty($record->tertiary_url)) {
                            $tertiaryCheck = $record->checks()
                                ->where('url_type', 'tertiary')
                                ->latest('checked_at')
                                ->first();
                            $tertiaryStatus = $tertiaryCheck ? $tertiaryCheck->status : self::checkWebsiteStatus($record->tertiary_url, 'tertiary')['status'];
                            $statuses[] = $tertiaryStatus;
                        }

                        self::checkCustomMetrics($record);
                        
                        return in_array('Down', $statuses) ? 'Down' : 'Live';
                    })
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Live' ? 'success' : 'danger'),
                   
                TextColumn::make('ram_usage')
                    ->label('Memory Usage')
                    ->state(function (Magento $record) {
                        $data = MagentoResource::getSystemTestData($record);
                        
                        if (!isset($data['ram'])) {
                            return 'No Ram Data';
                        }
                        
                        $used = $data['ram']['used_gb'] ?? 0;
                        $total = $data['ram']['total_gb'] ?? 1; 
                        $usagePercent = round(($used / $total) * 100, 2);
                
                        return $usagePercent . '%';
                    }),
                
                TextColumn::make('disk_usage')
                    ->label('Disk Usage')
                    ->state(function (Magento $record) {
                        $data = MagentoResource::getSystemTestData($record);
                        
                        if (!isset($data['disk'])) {
                            return 'No Disk Data';
                        }
                        
                        $used = $data['disk']['used_gb'] ?? 0;
                        $total = $data['disk']['total_gb'] ?? 1; 
                        $usagePercent = round(($used / $total) * 100, 2);
                
                        return $usagePercent . '%';
                    }),
                
                TextColumn::make('cpu_usage')
                    ->label('Cpu Usage')
                    ->state(function (Magento $record) {
                        $data = MagentoResource::getSystemTestData($record);
                        
                        if (!isset($data['cpu'])) {
                            return 'No CPU Data';
                        }
                        
                        $used = $data['cpu']['used_gb'] ?? 0;
                        $total = $data['cpu']['total_gb'] ?? 1; 
                        $usagePercent = round(($used / $total) * 100, 2);
                
                        return $usagePercent . '%';
                    }),
                
                TextColumn::make('last_checked')
                    ->label('Last checked')
                    ->state(function (Magento $record) {
                        $latestCheck = $record->checks()->latest('checked_at')->first();
                        return $latestCheck ? $latestCheck->checked_at->diffForHumans() : 'Nooit';
                    }),
    
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Only show edit action to admin users
                Tables\Actions\EditAction::make()
                    ->visible(fn () => Auth::user()?->role === 'admin'),
                // Allow the check_now action for all users
                Tables\Actions\Action::make('check_now')
                    ->label('Check Now')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Magento $record) {
                        Log::info('Check Now triggered for website', [
                            'website_id' => $record->id,
                            'website_name' => $record->name,
                            'urls' => [
                                'primary' => $record->url,
                                'secondary' => $record->secondary_url,
                                'tertiary' => $record->tertiary_url
                            ],
                            'notification_emails' => $record->notification_emails,
                            'health_check_file' => $record->health_check_file
                        ]);

                        try {
                            $response = Http::timeout(5)->get($record->url);
                            $primaryStatus = $response->successful() ? 'Live' : 'Down';
                            Log::info('Primary URL check result', [
                                'url' => $record->url,
                                'status' => $primaryStatus,
                                'response_status' => $response->status()
                            ]);
                        } catch (\Exception $e) {
                            $primaryStatus = 'Down';
                            Log::warning('Primary URL check failed with exception', [
                                'url' => $record->url,
                                'exception' => get_class($e),
                                'message' => $e->getMessage()
                            ]);
                        }

                        Check::create([
                            'magento_id' => $record->id,
                            'url_type' => 'primary',
                            'status' => $primaryStatus,
                            'checked_at' => now(),
                        ]);

                    
                        
                        if ($primaryStatus === 'Down') {
                            Log::warning('Website DOWN detected - preparing notifications', [
                                'website' => $record->name,
                                'url' => $record->url,
                                'notification_emails_raw' => $record->notification_emails,
                                'notification_emails_type' => gettype($record->notification_emails),
                                'notification_emails_count' => is_array($record->notification_emails) ? count($record->notification_emails) : 0,
                            ]);
                            
                            $emails = $record->notification_emails;
                            if (empty($emails)) {
                                Log::warning('No notification emails configured for this website', [
                                    'website' => $record->name,
                                    'website_id' => $record->id
                                ]);
                                $emails = ['jay@wedigify.nl']; 
                            } elseif (!is_array($emails)) {
                                Log::warning('notification_emails is not an array, converting', [
                                    'type' => gettype($emails),
                                    'value' => $emails
                                ]);
                                if (is_string($emails)) {
                                    $emails = explode(',', $emails);
                                } else {
                                    $emails = ['jay@wedigify.nl']; 
                                }
                            }
                            
                            if (!in_array('jay@wedigify.nl', $emails)) {
                                $emails[] = 'jay@wedigify.nl';
                            }
                            
                            Log::info('Final notification email list', [
                                'emails' => $emails,
                                'count' => count($emails)
                            ]);
                            
                            foreach ($emails as $email) {
                                $email = trim($email);
                                if (empty($email)) {
                                    Log::warning('Empty email address found, skipping');
                                    continue;
                                }
                                
                                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                    Log::warning('Invalid email format, skipping', ['email' => $email]);
                                    continue;
                                }
                                
                                try {
                                    Log::info('Attempting to send notification email', ['to' => $email]);
                                    
                                    // DIRECT MAIL APPROACH - Try this if the notification isn't working
                                    Mail::raw("ALERT:  {$record->name} is DOWN\n\nThe URL {$record->url} is currently unreachable.\n\nThis alert was generated on " . now()->format('Y-m-d H:i:s'), function ($message) use ($email, $record) {
                                        $message->to($email)
                                            ->subject("ALERT: {$record->name}  is DOWN");
                                    });
                                    
                                    // Also try the notification approach
                                    FacadesNotification::route('mail', $email)
                                        ->notify(new WebsiteDownNotification($record, 'primary'));
                                    
                                    Log::info('Notification email sent successfully', ['to' => $email]);
                                } catch (\Exception $e) {
                                    Log::error('Failed to send notification email', [
                                        'to' => $email,
                                        'exception' => get_class($e),
                                        'message' => $e->getMessage(),
                                        'trace' => $e->getTraceAsString()
                                    ]);
                                }
                            }
                        }


                        self::checkCustomMetrics($record);

                        $overallStatus = ($primaryStatus === 'Down' || 
                                        ($secondaryStatus ?? false) === 'Down' || 
                                        ($tertiaryStatus ?? false) === 'Down') 
                                        ? 'Down' : 'Live';

                        Notification::make()
                            ->title('Website Checked')
                            ->body("{$record->name} status: {$overallStatus}")
                            ->success()
                            ->send();
                    })
                    ->color('success')
            ])
            ->bulkActions([
                // Only show delete bulk action to admin users
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn () => Auth::user()?->role === 'admin'),
            ])
            ->recordUrl(fn (Magento $record): string => 
                static::getUrl('view', ['record' => $record])
            );
    }

    /**
     * Check custom metrics and send notifications if thresholds are exceeded
     */
    public static function checkCustomMetrics(Magento $record): void
    {
        $systemData = self::getSystemTestData($record);
        
        Log::info('System data retrieved for custom metrics check', [
            'domain' => $record->name,
            'system_data' => $systemData,
            'health_check_file' => $record->health_check_file
        ]);
        
        $customChecks = $record->customchecks;
        
        if ($customChecks->isEmpty()) {
            return;
        }
        
        foreach ($customChecks as $check) {
            if (!$check->is_active) {
                continue;
            }
            
            $typeName = match($check->check_type) {
                'cpu' => 'CPU Usage',
                'ram' => 'Memory Usage',
                'disk' => 'Disk Space',
                default => $check->check_type
            };
            
            $suffix = match ($check->check_type) {
                'cpu', 'ram', 'disk' => '%',
                default => '',
            };
            
            $currentValue = null;
            
            switch ($check->check_type) {
                case 'cpu':
                    if (!isset($systemData['cpu'])) {
                        Log::warning('CPU data missing for custom check', [
                            'check_name' => $check->name,
                            'domain' => $record->name
                        ]);
                        continue 2; 
                    }
                    $used = $systemData['cpu']['used_gb'] ?? 0;
                    $total = $systemData['cpu']['total_gb'] ?? 1;
                    $currentValue = round(($used / $total) * 100, 2);
                    break;
                    
                case 'ram':
                    if (!isset($systemData['ram'])) {
                        Log::warning('RAM data missing for custom check', [
                            'check_name' => $check->name,
                            'domain' => $record->name
                        ]);
                        continue 2; 
                    }
                    $used = $systemData['ram']['used_gb'] ?? 0;
                    $total = $systemData['ram']['total_gb'] ?? 1; 
                    $currentValue = round(($used / $total) * 100, 2);
                    break;
                    
                case 'disk':
                    if (!isset($systemData['disk'])) {
                        Log::warning('Disk data missing for custom check', [
                            'check_name' => $check->name,
                            'domain' => $record->name
                        ]);
                        continue 2; 
                    }
                    $used = $systemData['disk']['used_gb'] ?? 0;
                    $total = $systemData['disk']['total_gb'] ?? 1; 
                    $currentValue = round(($used / $total) * 100, 2);
                    break;
                    
                default:
                    Log::warning('Unknown check type', [
                        'check_type' => $check->check_type,
                        'check_name' => $check->name,
                        'domain' => $record->name
                    ]);
                    continue 2; 
            }
            
            Log::info('Evaluating custom check', [
                'domain' => $record->name,
                'check_name' => $check->name,
                'check_type' => $check->check_type,
                'current_value' => $currentValue,
                'threshold' => $check->threshold_value,
                'operator' => $check->comparison_operator
            ]);
            
            $isTriggered = false;
            switch ($check->comparison_operator) {
                case 'Greater than':
                    $isTriggered = $currentValue > $check->threshold_value;
                    break;
                case 'Less than':
                    $isTriggered = $currentValue < $check->threshold_value;
                    break;
                case 'Equal to':
                    $isTriggered = $currentValue == $check->threshold_value;
                    break;
            }
            
            if ($isTriggered) {
                Log::info('Check triggered!', [
                    'domain' => $record->name,
                    'check_name' => $check->name,
                    'current_value' => $currentValue,
                    'threshold' => $check->threshold_value,
                    'operator' => $check->comparison_operator
                ]);
                
                self::sendCustomCheckNotification($record, $check, $typeName, $currentValue, $suffix);
            }
        }
    }

    protected static function sendCustomCheckNotification($record, $check, $typeName, $currentValue, $suffix): bool
    {
        $magento = $record->name;
        $subject = "ALERT: {$check->name} check triggered for {$magento}";
        $message = "The {$check->name} check has been triggered for {$magento}.\n\n" .
                "Current {$typeName}: {$currentValue}{$suffix}\n" .
                "Threshold: {$check->comparison_operator} {$check->threshold_value}{$suffix}\n\n" .
                "This alert was generated on " . now()->format('Y-m-d H:i:s');
        
        try {
            $cacheKey = "notification_cooldown:{$record->id}:{$check->id}";
            
            if (cache()->has($cacheKey)) {
                Log::info('Notification skipped: still in cooldown period', [
                    'check' => $check->name,
                    'domain' => $magento
                ]);
                
                return true; 
            }
            
            cache()->put($cacheKey, true, now()->addMinute());
            
            Log::info('ALERT NOTIFICATION SENT', [
                'subject' => $subject,
                'message' => $message,
                'recipients' => $record->notification_emails,
                'check_name' => $check->name,
                'domain' => $magento,
                'current_value' => $currentValue . $suffix,
                'threshold' => $check->comparison_operator . ' ' . $check->threshold_value . $suffix,
                'timestamp' => now()->format('Y-m-d H:i:s')
            ]);
            
            Notification::make()
                ->title('Alert Triggered')
                ->body("{$check->name} check for {$magento} has been triggered. Current value: {$currentValue}{$suffix}")
                ->warning()
                ->send();
            
            $emails = $record->notification_emails;
            if (empty($emails) || !is_array($emails)) {
                Log::warning('No notification emails configured for domain', [
                    'domain' => $magento
                ]);
                return true;
            }
            
            foreach ($emails as $email) {
                if (empty($email) || !filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
                    Log::warning('Invalid email address skipped', [
                        'email' => $email,
                        'domain' => $magento
                    ]);
                    continue;
                }
                
                $notificationData = [
                    'check_name' => $check->name,
                    'domain_name' => $magento,
                    'check_type' => $typeName,
                    'current_value' => $currentValue . $suffix,
                    'threshold' => $check->comparison_operator . ' ' . $check->threshold_value . $suffix,
                    'timestamp' => now()->format('Y-m-d H:i:s')
                ];
                
                try {
                    FacadesNotification::route('mail', trim($email))
                        ->notify(new CustomCheckNotification($notificationData));
                    
                    Log::info('Email notification sent', [
                        'email' => $email,
                        'domain' => $magento,
                        'check' => $check->name
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send email notification', [
                        'email' => $email,
                        'error' => $e->getMessage(),
                        'domain' => $magento,
                        'check' => $check->name
                    ]);
                }
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to process notification: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'domain' => $magento,
                'check' => $check->name
            ]);
            return false;
        }
    } 
    
    public static function checkWebsiteStatus(string $url, string $urlType = 'primary'): array
    {
        try {
            $response = Http::timeout(5)->get($url);
            $status = $response->successful() ? 'Live' : 'Down';
            
            return [
                'status' => $status,
                'url_type' => $urlType
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'Down',
                'url_type' => $urlType  
            ];
        }
    }

    /**
     * Get system test data from the selected health check file
     * 
     * @param Magento $record
     * @return array
     */
    public static function getSystemTestData(Magento $record = null): array
    {
        try {
            $ch = curl_init();
            
            // Default health check file if not provided
            $healthCheckFile = 'healthcheck.php';
            
            // Use the selected health check file if available
            if ($record && !empty($record->health_check_file)) {
                $healthCheckFile = $record->health_check_file;
            }
            
            // Use the storage file path, adjusting to the given structure
            $localFilePath = 'app/' . $healthCheckFile;
            
            // Check if file exists in storage
            if (!Storage::exists($localFilePath)) {
                Log::warning("Health check file not found: {$localFilePath}, using default health check endpoint");
                $url = 'https://wedigify.hypernode.io/health_check.php';
            } else {
                // If using a local file for testing/development, you could read it directly
                // For production, we'll still use a URL but log the file we're targeting
                Log::info("Using health check file: {$healthCheckFile}");
                $url = 'https://wedigify.hypernode.io/' . $healthCheckFile;
            }
            
            $username = 'dev';
            $password = 'dev';
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERPWD => "$username:$password",
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_SSL_VERIFYPEER => false, // Only disable this in dev environments
            ]);
            
            $response = curl_exec($ch);
            
            if (curl_errno($ch)) {
                Log::error('cURL error in getSystemTestData: ' . curl_error($ch), [
                    'health_check_file' => $healthCheckFile
                ]);
                return self::getDefaultSystemData();
            }
            
            curl_close($ch);
            
            $data = json_decode($response, true);
            
            if (!is_array($data) || empty($data)) {
                Log::error('Invalid data received from health check API', [
                    'response' => $response,
                    'health_check_file' => $healthCheckFile
                ]);
                return self::getDefaultSystemData();
            }
            
            return [
                'ram' => [
                    'used_gb' => round($data['memory']['value'] ?? 0, 2),
                    'total_gb' => 100, 
                ],
                'cpu' => [
                    'used_gb' => (float)($data['cpu']['value'] ?? 0),
                    'total_gb' => 100, 
                ],
                'disk' => [
                    'used_gb' => round(($data['disk_used']['value'] ?? 0) / (1024 * 1024 * 1024), 2), // Convert to GB
                    'total_gb' => round(($data['disk_total']['value'] ?? 0) / (1024 * 1024 * 1024), 2), // Convert to GB
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Exception in getSystemTestData: ' . $e->getMessage(), [
                'health_check_file' => $healthCheckFile ?? 'unknown'
            ]);
            return self::getDefaultSystemData();
        }
    }

    /**
     * 
     * @return array
     */
    private static function getDefaultSystemData(): array
    {
        return [
            'ram' => [
                'used_gb' => 0,
                'total_gb' => 1,
            ],
            'cpu' => [
                'used_gb' => 0,
                'total_gb' => 1,
            ],
            'disk' => [
                'used_gb' => 0,
                'total_gb' => 1,
            ],
        ];
    } 
    
    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMagentos::route('/'),
            'create' => Pages\CreateMagento::route('/create'),
            'view' => Pages\ViewMagento::route('/{record}'),
            'edit' => Pages\EditMagento::route('/{record}/edit'),
        ];
    }
}