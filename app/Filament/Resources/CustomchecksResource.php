<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomchecksResource\Pages;
use App\Models\Customchecks;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
  
class CustomchecksResource extends Resource
{
    protected static ?string $model = Customchecks::class;

    protected static ?string $navigationIcon = 'heroicon-s-shield-check';
    protected static ?string $navigationLabel = 'Custom Checks';
    protected static ?string $navigationGroup = 'Monitoring';
    protected static ?int $navigationSort = 3;

    // Add canCreate method to restrict custom check creation to admin users only
    public static function canCreate(): bool
    {
        return Auth::user()?->role === 'admin';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Custom Check Configuration')
                    ->description('Configure monitoring parameters for this check')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Check Name')
                            ->placeholder('Enter a descriptive name')
                            ->columnSpanFull(),
                        
                        Forms\Components\Grid::make(2)
                            ->schema([
Forms\Components\Select::make('check_type')
    ->label('Check Type')
    ->options(function (callable $get) {
        // Default check types
        $options = [
            'cpu' => 'CPU Usage',
            'ram' => 'Memory Usage',
            'disk' => 'Disk Space'
        ];

        // Get selected domains
        $selectedDomains = $get('magentos');
        if (!empty($selectedDomains)) {
            // Get all selected domains' health check files
            $healthCheckFiles = \App\Models\Magento::whereIn('id', $selectedDomains)
                ->pluck('health_check_file')
                ->unique()
                ->toArray();

            // For each health check file, get available metrics
            foreach ($healthCheckFiles as $file) {
                $additionalOptions = \App\Models\Customchecks::getAvailableCheckTypes($file);
                // Merge with existing options, preserving unique keys
                $options = array_merge($options, $additionalOptions);
            }
        }

            return $options;                                                                                                                    // Return the options                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     
    })
                                    ->reactive()
                                    ->required()
                                    ->label('Check Type')
                                    ->helperText('Select the metric to monitor'),
                                
                                Forms\Components\Select::make('comparison_operator')
                                    ->options([
                                        'Greater than' => 'Greater than',
                                        'Less than' => 'Less than',
                                        'Equal to' => 'Equal to'
                                    ])
                                    ->required()
                                    ->label('Comparison')
                                    ->helperText('How should the value be compared'),
                            ]),
    
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('threshold_value')
                                    ->numeric()
                                    ->required()
                                    ->label('Threshold Value')
                                    ->helperText('The value that triggers this check')
                                    ->suffix(function (Forms\Get $get) {
                                        $type = $get('check_type');
                                        return match ($type) {
                                            'cpu', 'ram', 'disk' => '%',
                                            'cpu_load' => '',
                                            default => '',
                                        };
                                    })
                                    ->rule(function (Forms\Get $get) {
                                        $type = $get('check_type');
                                        return match ($type) {
                                            'cpu', 'ram', 'disk' => 'max:100',
                                            default => null,
                                        };
                                    }),
    
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active Status')
                                    ->helperText('Enable or disable this check')
                                    ->default(true),
                            ]),
    
                        Forms\Components\Section::make('Assigned Pages')
                            ->schema([
                                Forms\Components\Select::make('magentos')
                                    ->multiple()
                                    ->relationship('magentos', 'name')
                                    ->preload()
                                    ->label('Assign to Magento Pages')
                                    ->helperText('Select the Magento pages where this check should be applied')
                                    ->columnSpanFull()
                                    ->searchable()
                                    ->default(function () {
                                        $preselectedMagentoId = request()->get('preselect_magento');
                                        
                                        if ($preselectedMagentoId) {
                                            return [$preselectedMagentoId];
                                        }
                                        
                                        return [];
                                    }),
                            ]),
                    ]),
            ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\BadgeColumn::make('check_type')
                    ->colors([
                        'primary' => 'cpu',
                        'info' => 'cpu_load',
                        'danger' => 'ram',
                        'warning' => 'disk',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cpu' => 'CPU Usage',
                        'ram' => 'Memory Usage',
                        'disk' => 'Disk Usage',
                        default => $state,
                    }),
                    
                Tables\Columns\TextColumn::make('comparison_operator')
                    ->label('Operator'),
                    
                Tables\Columns\TextColumn::make('threshold_value')
                    ->label('Threshold')
                    ->formatStateUsing(function ($state, $record) {
                        $suffix = match ($record->check_type) {
                            'cpu', 'ram', 'disk' => '%',
                            'cpu_load' => '',
                            default => '',
                        };
                        return $state . $suffix;
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                    
                Tables\Columns\TextColumn::make('magentos.name')
                    ->label('Assigned Pages')
                    ->listWithLineBreaks()
                    ->limitList(3),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('check_type')
                    ->options([
                        'cpu' => 'CPU Usage',
                        'ram' => 'Memory Usage',
                        'disk' => 'Disk Usage',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

            ])
            ->actions([
                // Only show edit action to admin users
                Tables\Actions\EditAction::make()
                    ->visible(fn () => Auth::user()?->role === 'admin'),
                    
                // Only show delete action to admin users
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => Auth::user()?->role === 'admin'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Only show delete bulk action to admin users
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()?->role === 'admin'),
                        
                    // Allow activate/deactivate for all users since these are less destructive
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Checks')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn (Builder $query) => $query->update(['is_active' => true])),
                        
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Checks')
                        ->icon('heroicon-o-x-circle')
                        ->action(fn (Builder $query) => $query->update(['is_active' => false]))
                        ->color('gray'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomchecks::route('/'),
            'create' => Pages\CreateCustomchecks::route('/create'),
            'edit' => Pages\EditCustomchecks::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', true)->count();
    }
}