<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashInResource\Pages;
use App\Models\Account;
use App\Models\CashIn;
use App\Models\Customer;
use App\Models\SalesInvoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CashInResource extends Resource
{
    protected static ?string $model = CashIn::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-circle';
    protected static ?string $navigationGroup = 'Keuangan';
    protected static ?string $navigationLabel = 'Kas Masuk';
    protected static ?string $modelLabel = 'Kas Masuk';
    protected static ?string $pluralModelLabel = 'Kas Masuk';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Admin') || auth()->check() && auth()->user()->hasPermissionTo('manage_cash_in');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('account_id')
                    ->label('Akun Kas/Bank')
                    ->options(Account::where('type', 'ASSET')->whereIn('code', ['1-10001', '1-10002'])->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Forms\Components\DatePicker::make('date')
                    ->label('Tanggal')
                    ->required()
                    ->default(now()),
                Forms\Components\Select::make('type')
                    ->label('Jenis')
                    ->options([
                        'CUSTOMER_PAYMENT' => 'Pembayaran Customer',
                        'OTHER_INCOME' => 'Pendapatan Lain',
                    ])
                    ->required()
                    ->reactive(),
                // =========================================================
                // DROPDOWN FAKTUR (hanya muncul kalau CUSTOMER_PAYMENT)
                // =========================================================
                Forms\Components\Select::make('reference_id')
                    ->label('Faktur (Opsional)')
                    ->options(function (Forms\Get $get) {
                        $customerId = $get('customer_id');
                        $query = SalesInvoice::whereIn('status', ['UNPAID', 'PARTIAL']);
                        if ($customerId) {
                            $query->where('customer_id', $customerId);
                        }
                        return $query->pluck('invoice_number', 'id');
                    })
                    ->searchable()
                    ->visible(fn (Forms\Get $get) => $get('type') === 'CUSTOMER_PAYMENT')
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state) {
                            $invoice = SalesInvoice::find($state);
                            if ($invoice) {
                                $set('customer_id', $invoice->customer_id);
                                $set('amount', $invoice->total - $invoice->paid_amount);
                            }
                        }
                    })
                    ->placeholder('Pilih faktur yang dilunasi'),
                Forms\Components\Select::make('customer_id')
                    ->label('Customer')
                    ->options(Customer::pluck('name', 'id'))
                    ->searchable()
                    ->visible(fn (Forms\Get $get) => $get('type') === 'CUSTOMER_PAYMENT'),
                Forms\Components\TextInput::make('amount')
                    ->label('Jumlah')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label('Keterangan')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'CUSTOMER_PAYMENT' => 'Pembayaran Customer',
                        'OTHER_INCOME' => 'Pendapatan Lain',
                    }),
                Tables\Columns\TextColumn::make('customer.name')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('amount')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'CUSTOMER_PAYMENT' => 'Pembayaran Customer',
                        'OTHER_INCOME' => 'Pendapatan Lain',
                    ]),
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
            'index' => Pages\ListCashIns::route('/'),
            'create' => Pages\CreateCashIn::route('/create'),
            'edit' => Pages\EditCashIn::route('/{record}/edit'),
        ];
    }
}