<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Payroll';
    protected static ?string $navigationLabel = 'Master Karyawan';
    protected static ?string $modelLabel = 'Karyawan';
    protected static ?string $pluralModelLabel = 'Karyawan';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Admin')
            || auth()->check() && auth()->user()->hasPermissionTo('manage_payroll');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Data Pribadi')
                    ->schema([
                        Forms\Components\TextInput::make('nik')
                            ->label('NIK')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50),
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->label('Telepon')
                            ->tel()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('npwp')
                            ->label('NPWP')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('position')
                            ->label('Jabatan')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('department')
                            ->label('Divisi / Departemen')
                            ->maxLength(100),
                        Forms\Components\DatePicker::make('join_date')
                            ->label('Tanggal Masuk'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true),
                    ])->columns(2),

                Forms\Components\Section::make('Rekening Bank')
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')
                            ->label('Nama Bank')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('bank_account')
                            ->label('No. Rekening')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('bank_account_name')
                            ->label('Atas Nama Rekening')
                            ->maxLength(255),
                    ])->columns(3),

                Forms\Components\Section::make('Komponen Gaji (per Bulan)')
                    ->schema([
                        Forms\Components\TextInput::make('basic_salary')
                            ->label('Gaji Pokok')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->default(0),
                        Forms\Components\TextInput::make('position_allowance')
                            ->label('Tunjangan Jabatan')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0),
                        Forms\Components\TextInput::make('meal_allowance')
                            ->label('Tunjangan Makan')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0),
                        Forms\Components\TextInput::make('transport_allowance')
                            ->label('Tunjangan Transport')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0),
                        Forms\Components\TextInput::make('other_allowance')
                            ->label('Tunjangan Lainnya')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0),
                    ])->columns(3),

                Forms\Components\Section::make('BPJS (Persentase dari Gaji Pokok)')
                    ->description('Default: Kesehatan Perusahaan 4% / Karyawan 1% | Ketenagakerjaan Perusahaan 6.24% / Karyawan 3%')
                    ->schema([
                        Forms\Components\TextInput::make('bpjs_kesehatan_company_pct')
                            ->label('BPJS Kesehatan - Perusahaan (%)')
                            ->numeric()
                            ->default(4.00)
                            ->suffix('%'),
                        Forms\Components\TextInput::make('bpjs_kesehatan_employee_pct')
                            ->label('BPJS Kesehatan - Karyawan (%)')
                            ->numeric()
                            ->default(1.00)
                            ->suffix('%'),
                        Forms\Components\TextInput::make('bpjs_ketenagakerjaan_company_pct')
                            ->label('BPJS Ketenagakerjaan - Perusahaan (%)')
                            ->numeric()
                            ->default(6.24)
                            ->suffix('%'),
                        Forms\Components\TextInput::make('bpjs_ketenagakerjaan_employee_pct')
                            ->label('BPJS Ketenagakerjaan - Karyawan (%)')
                            ->numeric()
                            ->default(3.00)
                            ->suffix('%'),
                    ])->columns(2),
					
				Forms\Components\Section::make('PPh 21 / Pajak Penghasilan')
					->schema([
						Forms\Components\Toggle::make('pph21_enabled')
							->label('Kena PPh 21')
							->default(false)
							->live(),
						Forms\Components\Select::make('ptkp_status')
							->label('Status PTKP')
							->options([
								'TK/0' => 'TK/0 (Rp 54.000.000)',
								'K/0' => 'K/0 (Rp 58.500.000)',
								'K/1' => 'K/1 (Rp 63.000.000)',
								'K/2' => 'K/2 (Rp 67.500.000)',
								'K/3' => 'K/3 (Rp 72.000.000)',
							])
							->default('TK/0')
							->visible(fn (Forms\Get $get) => $get('pph21_enabled')),
					])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nik')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('position')
                    ->label('Jabatan'),
                Tables\Columns\TextColumn::make('department')
                    ->label('Divisi'),
                Tables\Columns\TextColumn::make('basic_salary')
                    ->label('Gaji Pokok')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('total_allowances')
                    ->label('Total Tunjangan')
                    ->money('IDR'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department')
                    ->label('Divisi')
                    ->options(fn () => Employee::distinct()->pluck('department', 'department')->filter()),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}