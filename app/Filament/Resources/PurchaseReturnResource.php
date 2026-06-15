<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseReturnResource\Pages;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PurchaseReturnResource extends Resource
{
    protected static ?string $model = PurchaseReturn::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';
    protected static ?string $navigationGroup = 'Transaksi Pembelian';
    protected static ?string $navigationLabel = 'Retur Pembelian';
    protected static ?string $modelLabel = 'Retur Pembelian';
    protected static ?string $pluralModelLabel = 'Retur Pembelian';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole('Admin') || auth()->user()->hasPermissionTo('manage_purchase_returns');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Retur')
                    ->schema([
                        Forms\Components\TextInput::make('return_number')
                            ->label('Nomor Retur')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn () => 'RT-' . date('Ymd') . '-' . rand(1000, 9999))
                            ->maxLength(50),

                        Forms\Components\DatePicker::make('date')
                            ->label('Tanggal')
                            ->required()
                            ->default(now()),

                        Forms\Components\Select::make('reference_gr_id')
                            ->label('Referensi Stok Masuk / GR (Opsional)')
                            ->options(GoodsReceipt::where('status', 'RECEIVED')->pluck('gr_number', 'id'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $gr = GoodsReceipt::with(['details.product', 'supplier'])->find($state);
                                    if ($gr) {
                                        $set('supplier_id', $gr->supplier_id);
                                        $details = [];
                                        foreach ($gr->details as $d) {
                                            $details[] = [
                                                'product_id' => $d->product_id,
                                                'qty' => 0,
                                                'price' => $d->buy_price,
                                            ];
                                        }
                                        $set('details', $details);
                                    }
                                }
                            })
                            ->placeholder('Kosongkan jika retur tidak dari GR'),

                        Forms\Components\Select::make('supplier_id')
                            ->label('Supplier')
                            ->options(Supplier::pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->disabled(fn (Forms\Get $get) => $get('reference_gr_id'))
                            ->dehydrated(true),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'DRAFT' => 'Draft',
                                'PROCESSED' => 'Diproses',
                                'CANCEL' => 'Batal',
                            ])
                            ->default('DRAFT')
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Alasan Retur / Catatan')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Detail Barang Diretur')
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
                                    ->label('Qty Retur')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->live()
                                    ->afterStateUpdated(function ($state, $get, Forms\Set $set) {
                                        $price = $get('price') ?? 0;
                                        $set('subtotal', ($state ?? 0) * $price);
                                    }),

                                Forms\Components\TextInput::make('price')
                                    ->label('Harga Beli')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required()
                                    ->live()
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

                                Forms\Components\Textarea::make('notes')
                                    ->label('Keterangan')
                                    ->placeholder('Contoh: Barang rusak, salah ukuran, dll'),
                            ])
                            ->columns(4)
                            ->addActionLabel('Tambah Barang')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                $total = 0;
                                $totalQty = 0;
                                foreach ($state ?? [] as $item) {
                                    $qty = $item['qty'] ?? 0;
                                    $price = $item['price'] ?? 0;
                                    $total += $qty * $price;
                                    $totalQty += $qty;
                                }
                                $set('total_qty', $totalQty);
                                $set('total_amount', $total);
                            }),
                    ]),

                Forms\Components\Section::make('Ringkasan')
                    ->schema([
                        Forms\Components\TextInput::make('total_qty')
                            ->label('Total Qty')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(true),

                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Nilai Retur')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('return_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date('d M Y'),
                Tables\Columns\TextColumn::make('referenceGr.gr_number')
                    ->label('GR Ref')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('supplier.name'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'DRAFT' => 'gray',
                        'PROCESSED' => 'warning',
                        'CANCEL' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('total_qty'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('IDR'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'DRAFT' => 'Draft',
                        'PROCESSED' => 'Diproses',
                        'CANCEL' => 'Batal',
                    ]),
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),
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
            'index' => Pages\ListPurchaseReturns::route('/'),
            'create' => Pages\CreatePurchaseReturn::route('/create'),
            'edit' => Pages\EditPurchaseReturn::route('/{record}/edit'),
        ];
    }
}