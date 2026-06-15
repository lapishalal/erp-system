<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashOutResource\Pages;
use App\Models\Account;
use App\Models\CashOut;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CashOutResource extends Resource
{
    protected static ?string $model = CashOut::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-circle';
    protected static ?string $navigationGroup = 'Keuangan';
    protected static ?string $navigationLabel = 'Kas Keluar';
    protected static ?string $modelLabel = 'Kas Keluar';
    protected static ?string $pluralModelLabel = 'Kas Keluar';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole('Admin') || auth()->user()->hasPermissionTo('manage_cash_out');
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
                    ->label('Jenis Pengeluaran')
                    ->options([
                        'OPERATIONAL' => 'Operasional',
                        'SALARY' => 'Gaji',
                        'TRANSPORT' => 'Transport',
                        'MARKETING' => 'Marketing',
                        'UTILITIES' => 'Listrik & Air',
                        'RENT' => 'Sewa',
                        'TAX' => 'Pajak',
                        'OTHER' => 'Lain-lain',
                    ])
                    ->required(),
                Forms\Components\Select::make('category_id')
                    ->label('Kategori Expense')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('amount')
                    ->label('Jumlah')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label('Keterangan')
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('attachment')
                    ->label('Lampiran')
                    ->directory('expense-attachments')
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
                    ->badge(),
                Tables\Columns\TextColumn::make('category.name')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('amount')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'OPERATIONAL' => 'Operasional',
                        'SALARY' => 'Gaji',
                        'TRANSPORT' => 'Transport',
                        'MARKETING' => 'Marketing',
                        'UTILITIES' => 'Listrik & Air',
                        'RENT' => 'Sewa',
                        'TAX' => 'Pajak',
                        'OTHER' => 'Lain-lain',
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
            'index' => Pages\ListCashOuts::route('/'),
            'create' => Pages\CreateCashOut::route('/create'),
            'edit' => Pages\EditCashOut::route('/{record}/edit'),
        ];
    }
}