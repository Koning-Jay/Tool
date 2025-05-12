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
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Hidden;
use Illuminate\Validation\Rule;
use Filament\Notifications\Notification;
use App\Notifications\WebsiteDownNotification;
use Filament\Forms\Components\Card;
use Illuminate\Support\Facades\Notification as FacadesNotification;
use Illuminate\Support\Facades\Log;

class MagentoResource extends Resource
{
    protected static ?string $model = Magento::class;
    protected static ?string $navigationIcon = 'heroicon-s-computer-desktop';
    protected static ?string $navigationGroup = 'Monitoring';
    protected static ?string $label = 'Domains';
    protected static ?string $navigationLabel = 'Domains';
    protected static string $notificationEmail = 'Jay@wedigify.nl';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
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
                        Forms\Components\TextInput::make('api_key')
                            ->maxLength(255),
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

                        // Check custom metrics
                        self::checkCustomMetrics($record);
                        
                        return in_array('Down', $statuses) ? 'Down' : 'Live';
                    })
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Live' ? 'success' : 'danger'),
                   
                TextColumn::make('ram_usage')
                    ->label('Ram')
                    ->state(function () {
                        $data = MagentoResource::getSystemTestData();
                        
                        if (!isset($data['ram'])) {
                            return 'No Ram Data';
                        }
                        
                        // Calculate RAM usage percentage using the same method as in the command
                        $used = $data['ram']['used_gb'] ?? 0;
                        $total = $data['ram']['total_gb'] ?? 1; // Avoid division by zero
                        $usagePercent = round(($used / $total) * 100, 2);
                
                        return $usagePercent . '%';
                    }),
                
                TextColumn::make('disk_usage')
                    ->label('Disk Usage')
                    ->state(function () {
                        $data = MagentoResource::getSystemTestData();
                        
                        if (!isset($data['disk'])) {
                            return 'No Disk Data';
                        }
                        
                        // Calculate disk usage percentage using the same method as in the command
                        $used = $data['disk']['used_gb'] ?? 0;
                        $total = $data['disk']['total_gb'] ?? 1; // Avoid division by zero
                        $usagePercent = round(($used / $total) * 100, 2);
                
                        return $usagePercent . '%';
                    }),
                
                TextColumn::make('cpu_usage')
                    ->label('Cpu Usage')
                    ->state(function () {
                        $data = MagentoResource::getSystemTestData();
                        
                        if (!isset($data['cpu'])) {
                            return 'No CPU Data';
                        }
                        
                        // Calculate CPU usage percentage using the same method as in the command
                        $used = $data['cpu']['used_gb'] ?? 0;
                        $total = $data['cpu']['total_gb'] ?? 1; // Avoid division by zero
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
                Tables\Actions\EditAction::make(),
    
                /**
                 * Modified check_now action with disabled email sending
                 */
                Tables\Actions\Action::make('check_now')
                ->label('Check Now')
                ->icon('heroicon-o-arrow-path')
                ->action(function (Magento $record) {
                    try {
                        $response = Http::timeout(5)->get($record->url);
                        $primaryStatus = $response->successful() ? 'Live' : 'Down';
                    } catch (\Exception $e) {
                        $primaryStatus = 'Down';
                    }

                    Check::create([
                        'magento_id' => $record->id,
                        'url_type' => 'primary',
                        'status' => $primaryStatus,
                        'checked_at' => now(),
                    ]);

                    if ($primaryStatus === 'Down' && !empty($record->notification_emails)) {
                        // Log instead of sending emails
                        Log::warning('WEBSITE DOWN ALERT (EMAIL DISABLED)', [
                            'website' => $record->name,
                            'url' => $record->url,
                            'url_type' => 'primary',
                            'recipients_would_be' => $record->notification_emails,
                            'timestamp' => now()->format('Y-m-d H:i:s')
                        ]);
                        
                        // Uncomment when Mailtrap issue is resolved
                        /*
                        foreach ($record->notification_emails as $email) {
                            FacadesNotification::route('mail', trim($email))
                                ->notify(new WebsiteDownNotification($record, 'primary'));
                        }
                        */
                    }

                    $secondaryStatus = null;
                    if (!empty($record->secondary_url)) {
                        try {
                            $response = Http::timeout(5)->get($record->secondary_url);
                            $secondaryStatus = $response->successful() ? 'Live' : 'Down';
                        } catch (\Exception $e) {
                            $secondaryStatus = 'Down';
                        }

                        Check::create([
                            'magento_id' => $record->id,
                            'url_type' => 'secondary',
                            'status' => $secondaryStatus,
                            'checked_at' => now(),
                        ]);

                        if ($secondaryStatus === 'Down' && !empty($record->notification_emails)) {
                            // Log instead of sending emails
                            Log::warning('WEBSITE DOWN ALERT (EMAIL DISABLED)', [
                                'website' => $record->name,
                                'url' => $record->secondary_url,
                                'url_type' => 'secondary',
                                'recipients_would_be' => $record->notification_emails,
                                'timestamp' => now()->format('Y-m-d H:i:s')
                            ]);
                            
                            // Uncomment when Mailtrap issue is resolved
                            
                            foreach ($record->notification_emails as $email) {
                                FacadesNotification::route('mail', trim($email))
                                    ->notify(new WebsiteDownNotification($record, 'secondary'));
                            }
                            
                        }
                    }

                    $tertiaryStatus = null;
                    if (!empty($record->tertiary_url)) {
                        try {
                            $response = Http::timeout(5)->get($record->tertiary_url);
                            $tertiaryStatus = $response->successful() ? 'Live' : 'Down';
                        } catch (\Exception $e) {
                            $tertiaryStatus = 'Down';
                        }

                        Check::create([
                            'magento_id' => $record->id,
                            'url_type' => 'tertiary',
                            'status' => $tertiaryStatus,
                            'checked_at' => now(),
                        ]);

                        if ($tertiaryStatus === 'Down' && !empty($record->notification_emails)) {
                            // Log instead of sending emails
                            Log::warning('WEBSITE DOWN ALERT (EMAIL DISABLED)', [
                                'website' => $record->name,
                                'url' => $record->tertiary_url,
                                'url_type' => 'tertiary',
                                'recipients_would_be' => $record->notification_emails,
                                'timestamp' => now()->format('Y-m-d H:i:s')
                            ]);
                            
                            // Uncomment when Mailtrap issue is resolved
                            
                            foreach ($record->notification_emails as $email) {
                                FacadesNotification::route('mail', trim($email))
                                    ->notify(new WebsiteDownNotification($record, 'tertiary'));
                            }
                        
                        }
                    }

                    // Check custom metrics
                    self::checkCustomMetrics($record);

                    $overallStatus = ($primaryStatus === 'Down' || 
                                    ($secondaryStatus === 'Down' && !empty($record->secondary_url)) || 
                                    ($tertiaryStatus === 'Down' && !empty($record->tertiary_url))) 
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
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->recordUrl(fn (Magento $record): string => 
                static::getUrl('view', ['record' => $record])
            );
    }


    
    public static function checkCustomMetrics(Magento $record): void
    {
        // Get system metrics
        $systemData = self::getSystemTestData();
        
        // Get all custom checks for this Magento record
        $customChecks = $record->customchecks;
        
        if ($customChecks->isEmpty()) {
            return;
        }
        
        // Use a tracking array to avoid sending duplicate notifications
        static $notifiedChecks = [];
        $currentRunId = uniqid();
        
        foreach ($customChecks as $check) {
            // Skip inactive checks
            if (!$check->is_active) {
                continue;
            }
            
            // Create a unique identifier for this check in this run
            $checkIdentifier = $record->id . '-' . $check->id . '-' . $currentRunId;
            
            // Skip if we've already processed this check in the current execution
            if (isset($notifiedChecks[$checkIdentifier])) {
                continue;
            }
            
            // Get type name for notifications
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
            
            // Only process check if data exists for the specific check type
            $currentValue = null;
            
            switch ($check->check_type) {
                case 'cpu':
                    if (!isset($systemData['cpu'])) {
                        continue 2; // Skip to next check
                    }
                    // Calculate CPU usage percentage
                    $used = $systemData['cpu']['used_gb'] ?? 0;
                    $total = $systemData['cpu']['total_gb'] ?? 1; // Avoid division by zero
                    $currentValue = round(($used / $total) * 100, 2);
                    break;
                    
                case 'ram':
                    if (!isset($systemData['ram'])) {
                        continue 2; // Skip to next check
                    }
                    // Calculate RAM usage percentage
                    $used = $systemData['ram']['used_gb'] ?? 0;
                    $total = $systemData['ram']['total_gb'] ?? 1; // Avoid division by zero
                    $currentValue = round(($used / $total) * 100, 2);
                    break;
                    
                case 'disk':
                    if (!isset($systemData['disk'])) {
                        continue 2; // Skip to next check
                    }
                    // Calculate disk usage percentage
                    $used = $systemData['disk']['used_gb'] ?? 0;
                    $total = $systemData['disk']['total_gb'] ?? 1; // Avoid division by zero
                    $currentValue = round(($used / $total) * 100, 2);
                    break;
                    
                default:
                    continue 2; // Skip to next check if unknown type
            }
            
            // Determine if check is triggered based on comparison operator
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
            
            // Send notification if check is triggered
            if ($isTriggered) {
                // Mark this check as notified to prevent duplicates
                $notifiedChecks[$checkIdentifier] = true;
                
                // Call the notification method only once for this check
                self::sendCustomCheckNotification($record, $check, $typeName, $currentValue, $suffix);
                
                // Log the trigger for debugging
                Log::info("Triggered custom check: {$check->name} for domain {$record->name}");
            }
        }
    }

    /**
     * Send notification for custom check with email sending disabled
     */
    /**
 * Very simple implementation of notification throttling with a 1-minute delay
 */
protected static function sendCustomCheckNotification($record, $check, $typeName, $currentValue, $suffix): bool
{
    $magento = $record->name;
    $subject = "ALERT: {$check->name} check triggered for {$magento}";
    $message = "The {$check->name} check has been triggered for {$magento}.\n\n" .
            "Current {$typeName}: {$currentValue}{$suffix}\n" .
            "Threshold: {$check->comparison_operator} {$check->threshold_value}{$suffix}\n\n" .
            "This alert was generated on " . now()->format('Y-m-d H:i:s');
    
    try {
        // Simple cache key for this specific check+domain combination
        $cacheKey = "notification_cooldown:{$record->id}:{$check->id}";
        
        // If we find the key in cache, that means the cooldown period is active
        if (cache()->has($cacheKey)) {
            // Log skipped notification
            Log::info('Notification skipped: still in cooldown period', [
                'check' => $check->name,
                'domain' => $magento
            ]);
            
            return true; // Return success without sending notification
        }
        
        // Set cooldown cache - this will prevent additional notifications for the next minute
        cache()->put($cacheKey, true, now()->addMinute());
        
        // Log the notification instead of sending email
        Log::info('ALERT NOTIFICATION (EMAIL DISABLED)', [
            'subject' => $subject,
            'message' => $message,
            'to' => self::$notificationEmail,
            'check_name' => $check->name,
            'domain' => $magento,
            'current_value' => $currentValue . $suffix,
            'threshold' => $check->comparison_operator . ' ' . $check->threshold_value . $suffix,
            'timestamp' => now()->format('Y-m-d H:i:s')
        ]);
        
        // Show UI notification
        Notification::make()
            ->title('Alert Triggered')
            ->body("{$check->name} check for {$magento} has been triggered. Current value: {$currentValue}{$suffix}")
            ->warning()
            ->send();
        
        return true;
    } catch (\Exception $e) {
        Log::error('Failed to process notification: ' . $e->getMessage());
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
 * Updated getSystemTestData method for MagentoResource class to fetch
 * real-time system data from the health check endpoint
 */
public static function getSystemTestData(): array
    {
        try {
            // Initialize cURL session to fetch data from the health check endpoint
            $ch = curl_init();
            $url = 'https://wedigify.hypernode.io/health_check.php';
            $username = 'dev';
            $password = 'dev';
            
            // Set cURL options
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERPWD => "$username:$password",
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_SSL_VERIFYPEER => false, // Only disable this in dev environments
            ]);
            
            // Execute the request
            $response = curl_exec($ch);
            
            // Check for errors
            if (curl_errno($ch)) {
                Log::error('cURL error in getSystemTestData: ' . curl_error($ch));
                return self::getDefaultSystemData();
            }
            
            // Close the cURL session
            curl_close($ch);
            
            // Parse JSON response
            $data = json_decode($response, true);
            
            // Verify data structure
            if (!is_array($data) || empty($data)) {
                Log::error('Invalid data received from health check API', ['response' => $response]);
                return self::getDefaultSystemData();
            }
            
            // Format the data according to the expected structure in the view
            return [
                'ram' => [
                    'used_gb' => round($data['memory']['value'], 2),
                    'total_gb' => 100, // Memory usage is already in percentage
                ],
                'cpu' => [
                    'used_gb' => (float)$data['cpu']['value'],
                    'total_gb' => 100, // CPU usage is already in percentage
                ],
                'disk' => [
                    'used_gb' => round($data['disk_used']['value'] / (1024 * 1024 * 1024), 2), // Convert to GB
                    'total_gb' => round($data['disk_total']['value'] / (1024 * 1024 * 1024), 2), // Convert to GB
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Exception in getSystemTestData: ' . $e->getMessage());
            return self::getDefaultSystemData();
        }
    }

    /**
     * Get default system data when API call fails
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