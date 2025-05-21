<?php
namespace App\Filament\Resources;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-s-users';
    protected static ?string $navigationGroup = 'User Management';
    protected static ?string $navigationLabel = 'Users';
    protected static ?string $label = 'User';
    protected static ?string $pluralLabel = 'Users';
    protected static ?string $slug = 'users';
    protected static ?string $title = 'Users';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $modelLabel = 'User';
    protected static ?string $pluralModelLabel = 'Users';
    protected static ?string $searchLabel = 'Search Users';

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
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->required(fn ($record) => ! $record)
                    ->dehydrated(fn ($state) => filled($state))
                    ->dehydrateStateUsing(fn ($state) => bcrypt($state))
                    ->maxLength(255),
                Forms\Components\Select::make('role')
                    ->label('Role')
                    ->options([
                        'admin' => 'Admin',
                        'user' => 'User',
                    ])
                    ->required()
                    ->default('user')
                    ->disabled(fn () => Auth::user()?->role !== 'admin'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'user' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'admin' => 'Admin',
                        'user' => 'User',
                    ])
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => Auth::user()?->role === 'admin'),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => Auth::user()?->role === 'admin'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()?->role === 'admin'),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
