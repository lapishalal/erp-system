<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductStockResource\Pages;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductStockResource extends Resource
{
    protected static ?string $model = ProductStock::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent';
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Cek Stok';
    protected static ?string $modelLabel = 'Stok Barang';
    protected static ?string $pluralModelLabel = 'Stok Barang';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Admin') || auth()->check() && auth()->user()->hasPermissionTo('manage_inventory');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->relationship('product', 'name')
                    ->required()
                    ->disabled(),
                Forms\Components\Select::make('warehouse_id')
                    ->relationship('warehouse', 'name')
                    ->required()
                    ->disabled(),
                Forms\Components\TextInput::make('physical_stock')
                    ->numeric()
                    ->disabled(),
                Forms\Components\TextInput::make('outstanding_stock')
                    ->numeric()
                    ->disabled(),
                Forms\Components\TextInput::make('available_stock')
                    ->numeric()
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Gudang'),
                Tables\Columns\TextColumn::make('physical_stock')
                    ->label('Stok Fisik')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('outstanding_stock')
                    ->label('Outstanding')
                    ->numeric(),
                Tables\Columns\TextColumn::make('available_stock')
                    ->label('Available')
                    ->numeric()
                    ->color(fn (int $state): string => $state <= 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('product.min_stock')
                    ->label('Min Stok')
                    ->numeric(),
                Tables\Columns\TextColumn::make('total_pending_customer')
                    ->label('Pending ke Customer')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            DB::table('sales_order_details')
                                ->selectRaw('COALESCE(SUM(remaining_qty), 0)')
                                ->whereColumn('sales_order_details.product_id', 'product_stocks.product_id')
                                ->where('sales_order_details.remaining_qty', '>', 0)
                                ->whereExists(function ($q) {
                                    $q->select(DB::raw(1))
                                      ->from('sales_orders')
                                      ->whereColumn('sales_orders.id', 'sales_order_details.so_id')
                                      ->whereIn('sales_orders.status', ['OPEN', 'PARTIAL']);
                                }),
                            $direction
                        );
                    })
                    ->alignment('center')
                    ->color('warning')
                    ->icon('heroicon-o-clock')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => number_format($state, 0, ',', '.') . ' unit')
                    ->tooltip('Klik tombol "Lihat" untuk detail per customer'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('low_stock')
                    ->label('Stok Menipis')
                    ->query(fn ($query) => 
                        $query->whereColumn('available_stock', '<=', 'products.min_stock')
                            ->join('products', 'product_stocks.product_id', '=', 'products.id')
                    ),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('createPO')
                        ->label('Buat PO')
                        ->icon('heroicon-o-shopping-cart')
                        ->color('warning')
                        ->visible(fn (ProductStock $record): bool => 
                            $record->available_stock <= ($record->product?->min_stock ?? 0)
                        )
                        ->form([
                            Forms\Components\Select::make('supplier_id')
                                ->label('Supplier')
                                ->options(Supplier::pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                            Forms\Components\TextInput::make('qty')
                                ->label('Qty Order')
                                ->numeric()
                                ->required()
                                ->default(fn (ProductStock $record) => 
                                    max(1, ($record->product?->min_stock ?? 10) - $record->available_stock)
                                ),
                            Forms\Components\TextInput::make('unit_price')
                                ->label('Harga Beli')
                                ->numeric()
                                ->prefix('Rp')
                                ->required()
                                ->default(fn (ProductStock $record) => $record->product?->last_buy_price ?? 0),
                        ])
                        ->action(function (ProductStock $record, array $data) {
                            $po = PurchaseOrder::create([
                                'po_number' => 'PO-' . date('Ymd') . '-' . rand(1000, 9999),
                                'date' => now(),
                                'supplier_id' => $data['supplier_id'],
                                'status' => 'DRAFT',
                            ]);

                            $po->details()->create([
                                'product_id' => $record->product_id,
                                'qty' => $data['qty'],
                                'unit_price' => $data['unit_price'],
                            ]);

                            Notification::make()
                                ->title('PO berhasil dibuat')
                                ->body('PO ' . $po->po_number . ' untuk ' . $record->product?->name . ' telah dibuat.')
                                ->success()
                                ->send();

                            return redirect(PurchaseOrderResource::getUrl('edit', ['record' => $po]));
                        })
                        ->modalHeading('Buat Purchase Order')
                        ->modalSubmitActionLabel('Buat PO'),

                    Tables\Actions\Action::make('viewPending')
                        ->label('Lihat')
                        ->icon('heroicon-o-eye')
                        ->color('warning')
                        ->button()
                        ->size('sm')
                        ->modalHeading(function (ProductStock $record): string {
                            $name = $record->product ? $record->product->name : '-';
                            return 'Pending ke Customer: ' . $name;
                        })
                        ->modalDescription(function (ProductStock $record): string {
                            $sku = ($record->product && $record->product->code) ? $record->product->code : '-';
                            $wh = $record->warehouse ? $record->warehouse->name : '-';
                            return 'Kode: ' . $sku . ' | Gudang: ' . $wh;
                        })
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup')
                        ->modalWidth('4xl')
                        ->modalContent(function (ProductStock $record) {
                            $productId = $record->product_id;
                            $pendingRows = DB::table('sales_order_details')
                                ->select([
                                    'sales_order_details.so_id',
                                    'sales_order_details.qty',
                                    'sales_order_details.delivered_qty',
                                    'sales_order_details.remaining_qty',
                                    'sales_order_details.unit_price',
                                    'sales_orders.so_number',
                                    'sales_orders.date as so_date',
                                    'sales_orders.status as so_status',
                                    'customers.id as customer_id',
                                    'customers.name as customer_name',
                                ])
                                ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_details.so_id')
                                ->leftJoin('customers', 'customers.id', '=', 'sales_orders.customer_id')
                                ->where('sales_order_details.product_id', $productId)
                                ->where('sales_order_details.remaining_qty', '>', 0)
                                ->whereIn('sales_orders.status', ['OPEN', 'PARTIAL'])
                                ->orderBy('customers.name')
                                ->orderBy('sales_orders.date')
                                ->get();

                            $customers = [];
                            $totalPending = 0;

                            foreach ($pendingRows as $row) {
                                $cName = $row->customer_name ?: 'Tanpa Customer';
                                $cId = $row->customer_id ?: 0;

                                if (!isset($customers[$cId])) {
                                    $customers[$cId] = [
                                        'customer_id' => $cId,
                                        'customer_name' => $cName,
                                        'total_pending' => 0,
                                        'orders' => [],
                                    ];
                                }

                                $customers[$cId]['orders'][] = [
                                    'so_number' => $row->so_number ?: ('SO-' . str_pad($row->so_id, 5, '0', STR_PAD_LEFT)),
                                    'so_date' => $row->so_date ? date('d M Y', strtotime($row->so_date)) : '-',
                                    'so_status' => $row->so_status,
                                    'qty' => (int) $row->qty,
                                    'delivered_qty' => (int) $row->delivered_qty,
                                    'remaining_qty' => (int) $row->remaining_qty,
                                    'unit_price' => (float) $row->unit_price,
                                ];

                                $customers[$cId]['total_pending'] += (int) $row->remaining_qty;
                                $totalPending += (int) $row->remaining_qty;
                            }

                            $customers = array_values($customers);
                            usort($customers, function ($a, $b) {
                                return $b['total_pending'] <=> $a['total_pending'];
                            });

                            return view('filament.modals.pending-stock-customer', [
                                'product' => $record->product,
                                'warehouse' => $record->warehouse,
                                'totalPending' => $totalPending,
                                'customers' => $customers,
                            ]);
                        }),
                ]),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductStocks::route('/'),
            'view' => Pages\ViewProductStock::route('/{record}'),
        ];
    }
}