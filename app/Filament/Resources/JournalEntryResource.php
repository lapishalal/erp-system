<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JournalEntryResource\Pages;
use App\Models\JournalEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class JournalEntryResource extends Resource
{
    protected static ?string $model = JournalEntry::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Keuangan';
    protected static ?string $navigationLabel = 'Jurnal Umum';
    protected static ?string $modelLabel = 'Jurnal';
    protected static ?string $pluralModelLabel = 'Jurnal Umum';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole('Admin') || auth()->user()->hasPermissionTo('manage_accounting');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('date')
                    ->label('Tanggal')
                    ->required()
                    ->default(now()),

                Forms\Components\TextInput::make('description')
                    ->label('Deskripsi')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Section::make('Detail Jurnal')
                    ->schema([
                        Forms\Components\Repeater::make('details')
                            ->relationship('details')
                            ->schema([
                                Forms\Components\Select::make('account_id')
                                    ->label('Akun')
                                    ->relationship('account', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\TextInput::make('debit')
                                    ->label('Debit')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0),

                                Forms\Components\TextInput::make('credit')
                                    ->label('Kredit')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0),
                            ])
                            ->columns(3)
                            ->addActionLabel('Tambah Baris'),
                    ]),

                Forms\Components\Section::make('Ringkasan')
                    ->schema([
                        Forms\Components\TextInput::make('total_debit')
                            ->label('Total Debit')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(true),

                        Forms\Components\TextInput::make('total_credit')
                            ->label('Total Kredit')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(true),

                        Forms\Components\Placeholder::make('balance')
                            ->label('Balance')
                            ->content(function (Forms\Get $get): HtmlString {
                                $debit = $get('total_debit') ?? 0;
                                $credit = $get('total_credit') ?? 0;
                                $diff = abs($debit - $credit);
                                $color = $diff < 0.01 ? 'green' : 'red';
                                $text = $diff < 0.01 ? '✅ Balance' : '❌ Selisih: Rp ' . number_format($diff, 0, ',', '.');
                                return new HtmlString('<span style="color:' . $color . ';font-weight:bold;">' . $text . '</span>');
                            }),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['details.account', 'creator']))
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Referensi')
                    ->state(fn (JournalEntry $record): string => 
                        $record->reference_type ? class_basename($record->reference_type) . ' #' . $record->reference_id : 'Manual'
                    )
                    ->badge()
                    ->color(fn (JournalEntry $record): string => $record->reference_type ? 'gray' : 'info'),

                Tables\Columns\TextColumn::make('total_debit')
                    ->money('IDR')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_credit')
                    ->money('IDR')
                    ->alignEnd(),

                Tables\Columns\IconColumn::make('is_posted')
                    ->label('Posted')
                    ->boolean(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->placeholder('-'),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\Filter::make('periode')
                    ->form([
                        Forms\Components\DatePicker::make('dari')->label('Dari')->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('sampai')->label('Sampai')->default(now()->endOfMonth()),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['dari'], fn ($q, $d) => $q->whereDate('date', '>=', $d))
                            ->when($data['sampai'], fn ($q, $d) => $q->whereDate('date', '<=', $d));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (JournalEntry $record): bool => !$record->is_posted),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (JournalEntry $record): bool => !$record->is_posted),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJournalEntries::route('/'),
            'create' => Pages\CreateJournalEntry::route('/create'),
            'edit' => Pages\EditJournalEntry::route('/{record}/edit'),
            'view' => Pages\ViewJournalEntry::route('/{record}'),
        ];
    }
}