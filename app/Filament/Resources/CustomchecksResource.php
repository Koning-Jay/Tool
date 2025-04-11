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
            Forms\Components\Card::make()
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(2),
                    
                    Forms\Components\Select::make('check_type')
                        ->options([
                            'cpu' => 'CPU Usage',
                            'ram' => 'Memory Usage',
                            'sales' => 'Sales Performance',
                        ])
                        ->required()
                        ->reactive(),
                    
                    Forms\Components\Select::make('comparison_operator')
                        ->options([
                            'Greater than' => 'Greater than',
                            'Less than' => 'Less than',
                            'Equal to' => 'Equal to'
                        ])
                        ->required(),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('threshold_value')
                                ->numeric()
                                ->required()
                                ->suffix(function (Forms\Get $get) {
                                    $type = $get('check_type');
                                    return match ($type) {
                                        'cpu', 'ram' => '%',
                                        'sales' => 'Sales',
                                        default => '',
                                    };
                                })
                                ->rule(function (Forms\Get $get) {
                                    $type = $get('check_type');
                                    return match ($type) {
                                        'cpu', 'ram' => 'max:100',
                                        default => null,
                                    };
                                }),

                            Forms\Components\Toggle::make('is_active')
                                ->label('Active')
                                ->default(true),
                        ]),

                    Forms\Components\Select::make('magentos')
                        ->multiple()
                        ->relationship('magentos', 'name')
                        ->preload()
                        ->label('Assign to Magento Pages')
                        ->helperText('Select the Magento pages where this check should be applied')
                        ->columnSpan(2),
                ])
                ->columns(2),
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
                        'danger' => 'ram',
                        'success' => 'sales',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cpu' => 'CPU Usage',
                        'ram' => 'Memory Usage',
                        'sales' => 'Sales Performance',
                        default => $state,
                    }),
                    
                Tables\Columns\TextColumn::make('comparison_operator')
                    ->label('Operator'),
                    
                Tables\Columns\TextColumn::make('threshold_value')
                    ->label('Threshold')
                    ->formatStateUsing(function ($state, $record) {
                        $suffix = match ($record->check_type) {
                            'cpu', 'ram' => '%',
                            'sales' => ' - Sales',
                            'load_time' => 'sec',
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
                        'sales' => 'Sales Performance',
                    ]),
          
                    
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
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
            //
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