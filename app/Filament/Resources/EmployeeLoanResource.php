<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeLoanResource\Pages;
use App\Models\EmployeeLoan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmployeeLoanResource extends Resource
{
    protected static ?string $model = EmployeeLoan::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Payroll';
    protected static ?string $navigationLabel = 'Kasbon Karyawan';
    protected static ?string $modelLabel = 'Kasbon';
    protected static ?string $pluralModelLabel = 'Kasbon Karyawan';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole('Admin')
            || auth()->user()->hasPermissionTo('manage_payroll');
    }

    public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\Select::make('employee_id')
                ->label('Karyawan')
                ->relationship('employee', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\DatePicker::make('loan_date')
                ->label('Tanggal Pinjam')
                ->required()
                ->default(now()),
            Forms\Components\TextInput::make('amount')
                ->label('Jumlah Pinjaman')
                ->numeric()
                ->prefix('Rp')
                ->required()
                ->live()
                ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                    $amount = (float) $get('amount');
                    $count = (int) $get('installment_count');
                    if ($count > 0 && $amount > 0) {
                        $set('installment_amount', round($amount / $count, 2));
                        $set('remaining_amount', $amount);
                    }
                }),
            Forms\Components\TextInput::make('installment_count')
                ->label('Jumlah Cicilan (bulan)')
                ->numeric()
                ->default(1)
                ->required()
                ->live()
                ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                    $amount = (float) $get('amount');
                    $count = (int) $get('installment_count');
                    if ($count > 0 && $amount > 0) {
                        $set('installment_amount', round($amount / $count, 2));
                    }
                }),
            Forms\Components\TextInput::make('installment_amount')
                ->label('Cicilan per Bulan (Potongan Gaji)')
                ->numeric()
                ->prefix('Rp')
                ->required()
                ->helperText('Bisa diubah manual kalau cicilan tidak rata'),
            Forms\Components\TextInput::make('remaining_amount')
                ->label('Sisa Pinjaman')
                ->numeric()
                ->prefix('Rp')
                ->default(0)
                ->disabled()
                ->dehydrated(),
            Forms\Components\Textarea::make('description')
                ->label('Keterangan')
                ->columnSpanFull(),
        ])->columns(2);
	}

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('loan_date')
                    ->date('d M Y'),
                Tables\Columns\TextColumn::make('amount')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('installment_amount')
                    ->label('Cicilan/Bulan')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('paid_count')
                    ->label('Sudah Bayar'),
                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Sisa')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'ACTIVE' => 'warning',
                        'PAID' => 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['ACTIVE' => 'Belum Lunas', 'PAID' => 'Lunas']),
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
            'index' => Pages\ListEmployeeLoans::route('/'),
            'create' => Pages\CreateEmployeeLoan::route('/create'),
            'edit' => Pages\EditEmployeeLoan::route('/{record}/edit'),
        ];
    }
}