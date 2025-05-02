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


    /**
     * Check custom metrics and send notifications if thresholds are exceeded
     */
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
    protected static function sendCustomCheckNotification($record, $check, $typeName, $currentValue, $suffix): bool
    {
        $magento = $record->name;
        $subject = "ALERT: {$check->name} check triggered for {$magento}";
        $message = "The {$check->name} check has been triggered for {$magento}.\n\n" .
                "Current {$typeName}: {$currentValue}{$suffix}\n" .
                "Threshold: {$check->comparison_operator} {$check->threshold_value}{$suffix}\n\n" .
                "This alert was generated on " . now()->format('Y-m-d H:i:s');
        
        try {
            // Generate a unique key for this notification to prevent duplicates
            $notificationKey = md5($record->id . $check->id . $currentValue . date('Y-m-d-H'));
            $cacheKey = "notification_sent:{$notificationKey}";
            
            // Check if we've recently sent this exact notification
            if (cache()->has($cacheKey)) {
                Log::info('Duplicate notification prevented', [
                    'check' => $check->name,
                    'domain' => $magento
                ]);
                return true;
            }
            
            // Add to cache to prevent duplicate notifications for a period of time
            cache()->put($cacheKey, true, now()->addHour()); // Cache for 1 hour
            
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
            
            // Show UI notification (this will still work)
            Notification::make()
                ->title('Alert Triggered')
                ->body("{$check->name} check for {$magento} has been triggered. Current value: {$currentValue}{$suffix}")
                ->warning()
                ->send();
            
            // NOTE: Email sending is completely disabled
            // Uncomment this section when Mailtrap limit is resolved or alternative is set up
            /*
            Mail::raw($message, function($message) use ($subject, $magento) {
                $message->to(self::$notificationEmail)
                    ->subject($subject);
            });
            */
            
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

    public static function getSystemTestData(): array
    {
        $path = storage_path('app/system_testdata.json');

        if (!file_exists($path)) {
            return [
                'ram' => null,
                'disk' => null,
                'cpu' => null,
            ];
        }

        return json_decode(file_get_contents($path), true);
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