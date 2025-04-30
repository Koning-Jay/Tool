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

class CustomchecksResource extends Resource
{
    protected static ?string $model = Customchecks::class;

    protected static ?string $navigationIcon = 'heroicon-s-shield-check';
    protected static ?string $navigationLabel = 'Custom Checks';
    protected static ?string $navigationGroup = 'Monitoring';
    protected static ?int $navigationSort = 3;

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
                                    ->options([
                                        'cpu' => 'CPU Usage',
                                        'ram' => 'Memory Usage',
                                        'disk' => 'Disk Usage',
                                    ])
                                    ->required()
                                    ->reactive()
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
                                        // Check if we're coming from a ViewMagento page with preselection
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
                    
                Tables\Columns\TextColumn::make('current_value')
                    ->label('Current Value')
                    ->formatStateUsing(function ($state, $record) {
                        // Get system data for the current metric
                        $systemData = MagentoResource::getSystemTestData();
                        
                        $currentValue = null;
                        $isTriggered = false;
                        $suffix = '';
                        
                        if ($record->check_type === 'cpu' && isset($systemData['cpu']['usage_percent'])) {
                            $currentValue = $systemData['cpu']['usage_percent'];
                            $suffix = '%';
                            
                            // Determine if check is triggered
                            if ($record->comparison_operator === 'Greater than') {
                                $isTriggered = $currentValue > $record->threshold_value;
                            } elseif ($record->comparison_operator === 'Less than') {
                                $isTriggered = $currentValue < $record->threshold_value;
                            } elseif ($record->comparison_operator === 'Equal to') {
                                $isTriggered = $currentValue == $record->threshold_value;
                            }
                        } elseif ($record->check_type === 'cpu_load' && isset($systemData['cpu']['load_avg'])) {
                            $currentValue = $systemData['cpu']['load_avg'];
                            
                            // Determine if check is triggered
                            if ($record->comparison_operator === 'Greater than') {
                                $isTriggered = $currentValue > $record->threshold_value;
                            } elseif ($record->comparison_operator === 'Less than') {
                                $isTriggered = $currentValue < $record->threshold_value;
                            } elseif ($record->comparison_operator === 'Equal to') {
                                $isTriggered = $currentValue == $record->threshold_value;
                            }
                        } elseif ($record->check_type === 'ram' && isset($systemData['ram']['usage_percent'])) {
                            $currentValue = $systemData['ram']['usage_percent'];
                            $suffix = '%';
                            
                            // Determine if check is triggered
                            if ($record->comparison_operator === 'Greater than') {
                                $isTriggered = $currentValue > $record->threshold_value;
                            } elseif ($record->comparison_operator === 'Less than') {
                                $isTriggered = $currentValue < $record->threshold_value;
                            } elseif ($record->comparison_operator === 'Equal to') {
                                $isTriggered = $currentValue == $record->threshold_value;
                            }
                        } elseif ($record->check_type === 'disk' && isset($systemData['disk']['usage_percent'])) {
                            $currentValue = $systemData['disk']['usage_percent'];
                            $suffix = '%';
                            
                            // Determine if check is triggered
                            if ($record->comparison_operator === 'Greater than') {
                                $isTriggered = $currentValue > $record->threshold_value;
                            } elseif ($record->comparison_operator === 'Less than') {
                                $isTriggered = $currentValue < $record->threshold_value;
                            } elseif ($record->comparison_operator === 'Equal to') {
                                $isTriggered = $currentValue == $record->threshold_value;
                            }
                        }
                        
                        if ($currentValue === null) {
                            return 'N/A';
                        }
                        
                        $color = $isTriggered ? 'text-red-600 font-bold' : 'text-green-600';
                        return '<span class="' . $color . '">' . $currentValue . $suffix . '</span>';
                    })
                    ->html(),
                    
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(function ($record) {
                        // Get system data for the current metric
                        $systemData = MagentoResource::getSystemTestData();
                        
                        $currentValue = null;
                        
                        if ($record->check_type === 'cpu' && isset($systemData['cpu']['usage_percent'])) {
                            $currentValue = $systemData['cpu']['usage_percent'];
                        } elseif ($record->check_type === 'cpu_load' && isset($systemData['cpu']['load_avg'])) {
                            $currentValue = $systemData['cpu']['load_avg'];
                        } elseif ($record->check_type === 'ram' && isset($systemData['ram']['usage_percent'])) {
                            $currentValue = $systemData['ram']['usage_percent'];
                        } elseif ($record->check_type === 'disk' && isset($systemData['disk']['usage_percent'])) {
                            $currentValue = $systemData['disk']['usage_percent'];
                        }
                        
                        if ($currentValue === null) {
                            return 'Unknown';
                        }
                        
                        $isTriggered = false;
                        
                        if ($record->comparison_operator === 'Greater than') {
                            $isTriggered = $currentValue > $record->threshold_value;
                        } elseif ($record->comparison_operator === 'Less than') {
                            $isTriggered = $currentValue < $record->threshold_value;
                        } elseif ($record->comparison_operator === 'Equal to') {
                            $isTriggered = $currentValue == $record->threshold_value;
                        }
                        
                        return $isTriggered ? 'Triggered' : 'Normal';
                    })
                    ->colors([
                        'success' => 'Normal',
                        'danger' => 'Triggered',
                        'gray' => 'Unknown',
                    ]),
                    
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
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Normal' => 'Normal',
                        'Triggered' => 'Triggered',
                    ])
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'];
                        
                        if ($value === null) {
                            return $query;
                        }
                        
                        // This is a placeholder - in a real app you would need to implement logic
                        // to filter based on the current system status compared to the threshold
                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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