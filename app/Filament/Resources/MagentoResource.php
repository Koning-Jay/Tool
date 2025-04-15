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

class MagentoResource extends Resource //dwad
{
    protected static ?string $model = Magento::class;
    protected static ?string $navigationIcon = 'heroicon-s-computer-desktop';
    protected static ?string $navigationGroup = 'Monitoring';
    protected static ?string $label = 'Magento Site';


    protected static ?string $navigationLabel = 'Pages';

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
                    ->label('Naam')
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
                        
                        // Check primary URL status
                        $primaryCheck = $record->checks()
                            ->where('url_type', 'primary')
                            ->latest('checked_at')
                            ->first();
                        $primaryStatus = $primaryCheck ? $primaryCheck->status : self::checkWebsiteStatus($record->url)['status'];
                        $statuses[] = $primaryStatus;
                        
                        // Check secondary URL status if it exists
                        if (!empty($record->secondary_url)) {
                            $secondaryCheck = $record->checks()
                                ->where('url_type', 'secondary')
                                ->latest('checked_at')
                                ->first();
                            $secondaryStatus = $secondaryCheck ? $secondaryCheck->status : self::checkWebsiteStatus($record->secondary_url, 'secondary')['status'];
                            $statuses[] = $secondaryStatus;
                        }
                        
                        // Check tertiary URL status if it exists
                        if (!empty($record->tertiary_url)) {
                            $tertiaryCheck = $record->checks()
                                ->where('url_type', 'tertiary')
                                ->latest('checked_at')
                                ->first();
                            $tertiaryStatus = $tertiaryCheck ? $tertiaryCheck->status : self::checkWebsiteStatus($record->tertiary_url, 'tertiary')['status'];
                            $statuses[] = $tertiaryStatus;
                        }
                        
                        return in_array('Down', $statuses) ? 'Down' : 'Live';
                    })
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Live' ? 'success' : 'danger'),
    
                TextColumn::make('last_checked')
                    ->label('Laatste controle')
                    ->state(function (Magento $record) {
                        $latestCheck = $record->checks()->latest('checked_at')->first();
                        return $latestCheck ? $latestCheck->checked_at->diffForHumans() : 'Nooit';
                    }),
    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Aangemaakt op')
                    ->dateTime(),
    
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->label('Laatste update')
                    ->tooltip(fn ($record) => 'Laatste wijziging op: ' . $record->updated_at->timezone('Europe/Amsterdam')->format('d-m-Y H:i:s'))
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
    
                Tables\Actions\Action::make('check_now')
                    ->label('Nu controleren')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Magento $record) {
                        // Check primary URL
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
                            foreach ($record->notification_emails as $email) {
                                FacadesNotification::route('mail', trim($email))
                                    ->notify(new WebsiteDownNotification($record, 'primary'));
                            }
                        }
    
                        // Check secondary URL if present
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
                                foreach ($record->notification_emails as $email) {
                                    FacadesNotification::route('mail', trim($email))
                                        ->notify(new WebsiteDownNotification($record, 'secondary'));
                                }
                            }
                        }
    
                        // Check tertiary URL if present
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
                                foreach ($record->notification_emails as $email) {
                                    FacadesNotification::route('mail', trim($email))
                                        ->notify(new WebsiteDownNotification($record, 'tertiary'));
                                }
                            }
                        }
    
                        // Generate overall status message
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
                    ->color('success'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->recordUrl(fn (Magento $record): string => 
                static::getUrl('view', ['record' => $record])
            );
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