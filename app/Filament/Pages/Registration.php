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
    
    // Define your admin code here - you can also move this to config or environment variable
    protected string $adminCode = 'wedigo';
    
    // Property to temporarily store the determined role
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
        
        // Determine role based on admin code
        $role = 'user'; // default role
        
        if (!empty($data['admin_code'])) {
            if ($data['admin_code'] === $this->adminCode) {
                $role = 'admin';
                Notification::make()
                    ->title('Admin privileges granted!')
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Invalid admin code')
                    ->body('The admin code you entered is incorrect. You will be registered as a regular user.')
                    ->warning()
                    ->send();
            }
        }
        
        // Store role for later use in handleRegistration
        $this->temporaryRole = $role;
        
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