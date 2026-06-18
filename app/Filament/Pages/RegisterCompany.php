<?php

namespace App\Filament\Pages;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\TenantSetupSeeder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterCompany extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static string $view = 'filament.pages.register-company';
    protected static ?string $title = 'Daftar Perusahaan';
    protected static bool $shouldRegisterNavigation = false;

    public function getLayout(): string
    {
        return 'filament-panels::components.layout.simple';
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informasi Perusahaan')
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Nama Perusahaan')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('slug', Str::slug($state));
                            }),

                        TextInput::make('slug')
                            ->label('Slug / ID Perusahaan')
                            ->required()
                            ->unique('tenants', 'slug')
                            ->alphaDash()
                            ->helperText('Contoh: pt-makmur'),

                        TextInput::make('company_email')
                            ->label('Email Perusahaan')
                            ->email()
                            ->required()
                            ->maxLength(255),

                        TextInput::make('company_phone')
                            ->label('No. Telepon')
                            ->tel()
                            ->maxLength(20),

                        TextInput::make('company_address')
                            ->label('Alamat')
                            ->maxLength(500),
                    ]),

                Section::make('Informasi Admin')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Admin')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Email Admin')
                            ->email()
                            ->required()
                            ->unique('users', 'email')
                            ->maxLength(255),

                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->confirmed(),

                        TextInput::make('password_confirmation')
                            ->label('Konfirmasi Password')
                            ->password()
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function register()
    {
        $data = $this->form->getState();

        DB::beginTransaction();

        try {
            $tenantId = (string) Str::uuid();

            Tenant::create([
                'id' => $tenantId,
                'name' => $data['company_name'],
                'slug' => $data['slug'],
                'email' => $data['company_email'],
                'phone' => $data['company_phone'] ?? null,
                'address' => $data['company_address'] ?? null,
                'is_active' => true,
            ]);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'tenant_id' => $tenantId,
            ]);

            $seeder = new TenantSetupSeeder();
            $seeder->setContext($tenantId, $user->id);
            $seeder->run();

            DB::commit();

            Auth::login($user);
            $this->redirect('/admin');

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}