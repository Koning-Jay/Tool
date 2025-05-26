<?php
namespace App\Filament\Pages;
 
use Filament\Forms\Form;
use Filament\Pages\Auth\Register;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Wizard;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
 
class Registration extends Register
{
    protected ?string $maxWidth = '2xl';
    
    protected string $adminCode = 'wedigo2025';
    
    protected string $temporaryRole = 'user';
 
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([
                    Wizard\Step::make('Contact')
                        ->schema([
                            $this->getNameFormComponent(),
                            $this->getEmailFormComponent(),
                        ]),
                    Wizard\Step::make('Password')
                        ->schema([
                            $this->getPasswordFormComponent(),
                            $this->getPasswordConfirmationFormComponent(),
                        ]),
                    Wizard\Step::make('Role')
                        ->schema([
                            Section::make('Admin Access (Optional)')
                                ->description('Enter the admin code if you have been provided one to get admin privileges.')
                                ->schema([
                                    TextInput::make('admin_code')
                                        ->label('Admin Code')
                                        ->placeholder('Enter admin code (optional)')
                                        ->password()
                                        ->revealable()
                                        ->helperText('Leave blank to register as a regular user'),
                                ])
                                ->collapsible()
                                ->collapsed(),
                        ]),
                ])->submitAction(new HtmlString(Blade::render(<<<BLADE
                    <x-filament::button
                        type="submit"
                        size="sm"
                        wire:submit="register"
                    >
                        Register
                    </x-filament::button>
                    BLADE))),
            ]);
    }
 
    protected function getFormActions(): array
    {
        return [];
    }
    
    public function register(): ?\Filament\Http\Responses\Auth\Contracts\RegistrationResponse
    {
        $data = $this->form->getState();
        
        // Check if admin code was provided
        if (!empty($data['admin_code'])) {
            if ($data['admin_code'] === $this->adminCode) {
                // Valid admin code
                $this->temporaryRole = 'admin';
                Notification::make()
                    ->title('Admin privileges granted!')
                    ->success()
                    ->send();
            } else {
                // Invalid admin code - stop registration and show notification
                Notification::make()
                    ->title('Invalid Admin Code')
                    ->body('The admin code you entered is incorrect. Please try again with the correct code or remove the admin code to register as a regular user.')
                    ->warning()
                    ->persistent() // Makes the notification stay until dismissed
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('tryAgain')
                            ->label('Try Again')
                            ->button()
                            ->close(),
                        \Filament\Notifications\Actions\Action::make('continueAsUser')
                            ->label('Continue as User')
                            ->button()
                            ->action(function () {
                                // Clear the admin code field and continue registration
                                $this->form->fill(array_merge($this->form->getState(), ['admin_code' => '']));
                                $this->temporaryRole = 'user';
                                // Call parent register method
                                return parent::register();
                            }),
                    ])
                    ->send();
                
                // Return null to prevent registration from continuing
                return null;
            }
        } else {
            // No admin code provided, register as regular user
            $this->temporaryRole = 'user';
        }
        
        // Call parent register method
        return parent::register();
    }
    
    // Override the handleRegistration method to add role before user creation
    protected function handleRegistration(array $data): Model
    {
        // Remove admin_code from data and add role
        unset($data['admin_code']);
        $data['role'] = $this->temporaryRole;
        
        // Create user with role
        return $this->getUserModel()::create($data);
    }
}