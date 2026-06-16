<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountResource\Pages;
use App\Models\Account;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?string $navigationLabel = 'Buku Besar / COA';
    protected static ?string $modelLabel = 'Akun';
    protected static ?string $pluralModelLabel = 'Akun';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole('Admin') || auth()->user()->hasPermissionTo('manage_accounting');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->label('Kode Akun')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),

                Forms\Components\TextInput::make('name')
                    ->label('Nama Akun')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('type')
                    ->label('Tipe')
                    ->options([
                        'ASSET' => 'Aset',
                        'LIABILITY' => 'Kewajiban',
                        'EQUITY' => 'Modal',
                        'REVENUE' => 'Pendapatan',
                        'EXPENSE' => 'Beban',
                    ])
                    ->required(),

                Forms\Components\Select::make('parent_id')
                    ->label('Akun Induk')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Pilih akun induk (opsional)'),

                Forms\Components\TextInput::make('level')
                    ->label('Level')
                    ->numeric()
                    ->default(1)
                    ->required(),

                Forms\Components\Select::make('normal_balance')
                    ->label('Saldo Normal')
                    ->options([
                        'DEBIT' => 'Debit',
                        'CREDIT' => 'Kredit',
                    ])
                    ->required(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ASSET' => 'success',
                        'LIABILITY' => 'warning',
                        'EQUITY' => 'info',
                        'REVENUE' => 'primary',
                        'EXPENSE' => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ASSET' => 'Aset',
                        'LIABILITY' => 'Kewajiban',
                        'EQUITY' => 'Modal',
                        'REVENUE' => 'Pendapatan',
                        'EXPENSE' => 'Beban',
                    }),
                Tables\Columns\TextColumn::make('parent.name')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('level')
                    ->numeric(),
                Tables\Columns\TextColumn::make('normal_balance')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'ASSET' => 'Aset',
                        'LIABILITY' => 'Kewajiban',
                        'EQUITY' => 'Modal',
                        'REVENUE' => 'Pendapatan',
                        'EXPENSE' => 'Beban',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active'),
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
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }
}