<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockOpnameResource\Pages;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockOpname;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StockOpnameResource extends Resource
{
    protected static ?string $model = StockOpname::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Stock Opname';
    protected static ?string $modelLabel = 'Stock Opname';
    protected static ?string $pluralModelLabel = 'Stock Opname';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole('Admin') || auth()->user()->hasPermissionTo('manage_stock_opname');
    }
	
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('warehouse_id')
                    ->label('Gudang')
                    ->options(Warehouse::pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Forms\Components\DatePicker::make('opname_date')
                    ->label('Tanggal Opname')
                    ->required()
                    ->default(now()),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'DRAFT' => 'Draft',
                        'COMPLETED' => 'Selesai',
                    ])
                    ->default('DRAFT')
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->label('Catatan')
                    ->columnSpanFull(),

                Forms\Components\Section::make('Detail Barang')
                    ->schema([
                        Forms\Components\Repeater::make('details')
                            ->relationship('details')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Barang')
                                    ->options(Product::pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        $warehouseId = $get('../../warehouse_id');
                                        $stock = ProductStock::where('product_id', $state)
                                            ->where('warehouse_id', $warehouseId)
                                            ->first();
                                        $set('system_qty', $stock?->physical_stock ?? 0);
                                    }),
                                Forms\Components\TextInput::make('system_qty')
                                    ->label('Stok Sistem')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(true),
                                Forms\Components\TextInput::make('physical_qty')
                                    ->label('Stok Fisik')
                                    ->numeric()
                                    ->required()
                                    ->default(0)
                                    ->live()
                                    ->afterStateUpdated(function ($state, $get, Forms\Set $set) {
                                        $system = $get('system_qty') ?? 0;
                                        $set('difference_qty', ($state ?? 0) - $system);
                                    }),
                                Forms\Components\TextInput::make('difference_qty')
                                    ->label('Selisih')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(true),
                                Forms\Components\Textarea::make('notes')
                                    ->label('Catatan'),
                            ])
                            ->columns(5)
                            ->addActionLabel('Tambah Barang'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('opname_date')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('warehouse.name'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'DRAFT' => 'gray',
                        'COMPLETED' => 'success',
                    }),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Dibuat Oleh'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'DRAFT' => 'Draft',
                        'COMPLETED' => 'Selesai',
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
            'index' => Pages\ListStockOpnames::route('/'),
            'create' => Pages\CreateStockOpname::route('/create'),
            'edit' => Pages\EditStockOpname::route('/{record}/edit'),
        ];
    }
}