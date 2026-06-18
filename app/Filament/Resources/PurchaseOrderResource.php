<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Transaksi Pembelian';
    protected static ?string $navigationLabel = 'Purchase Order';
    protected static ?string $modelLabel = 'Purchase Order';
    protected static ?string $pluralModelLabel = 'Purchase Order';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Admin') || auth()->check() && auth()->user()->hasPermissionTo('manage_purchase_orders');
    }
	
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi PO')
                    ->schema([
                        Forms\Components\TextInput::make('po_number')
                            ->label('Nomor PO')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn () => 'PO-' . date('Ymd') . '-' . rand(1000, 9999))
                            ->maxLength(50),
                        Forms\Components\DatePicker::make('date')
                            ->label('Tanggal')
                            ->required()
                            ->default(now()),
                        Forms\Components\Select::make('supplier_id')
                            ->label('Supplier')
                            ->options(Supplier::pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'DRAFT' => 'Draft',
                                'ORDERED' => 'Ordered',
                                'PARTIAL' => 'Partial',
                                'COMPLETE' => 'Complete',
                                'CANCEL' => 'Cancel',
                            ])
                            ->default('DRAFT')
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Detail Barang')
    ->schema([
        Forms\Components\Repeater::make('details')
            ->relationship('details')
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label('Barang')
                    ->options(Product::pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                Forms\Components\TextInput::make('qty')
                    ->label('Qty')
                    ->numeric()
                    ->required()
                    ->default(1)
                    ->live(onBlur: true) // ✅ hanya update saat blur
                    ->afterStateUpdated(function ($state, $get, Forms\Set $set) {
                        $price = $get('unit_price') ?? 0;
                        $set('subtotal', ($state ?? 0) * $price);
                    }),

                Forms\Components\TextInput::make('unit_price')
                    ->label('Harga Beli')
                    ->numeric()
                    ->prefix('Rp')
                    ->required()
                    ->live(onBlur: true) // ✅ hanya update saat blur
                    ->afterStateUpdated(function ($state, $get, Forms\Set $set) {
                        $qty = $get('qty') ?? 0;
                        $set('subtotal', $qty * ($state ?? 0));
                    }),

                Forms\Components\TextInput::make('subtotal')
                    ->label('Subtotal')
                    ->numeric()
                    ->prefix('Rp')
                    ->disabled()
                    ->dehydrated(true),
            ])
            ->columns(4)
            ->addActionLabel('Tambah Barang')
            // ->live() // ❌ HAPUS live() dari Repeater
            ->afterStateUpdated(function ($state, Forms\Set $set) {
                $total = 0;
                foreach ($state ?? [] as $item) {
                    $total += ($item['qty'] ?? 0) * ($item['unit_price'] ?? 0);
                }
                $set('total_amount', $total);
            }),
    ]),

                Forms\Components\Section::make('Ringkasan')
                    ->schema([
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('po_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date('d M Y'),
                Tables\Columns\TextColumn::make('supplier.name'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'DRAFT' => 'gray',
                        'ORDERED' => 'info',
                        'PARTIAL' => 'warning',
                        'COMPLETE' => 'success',
                        'CANCEL' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('IDR'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'DRAFT' => 'Draft',
                        'ORDERED' => 'Ordered',
                        'PARTIAL' => 'Partial',
                        'COMPLETE' => 'Complete',
                        'CANCEL' => 'Cancel',
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
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}