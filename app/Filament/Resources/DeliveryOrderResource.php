<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliveryOrderResource\Pages;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\ProductStock;
use App\Models\SalesOrderDetail;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Barryvdh\DomPDF\Facade\Pdf;

class DeliveryOrderResource extends Resource
{
    protected static ?string $model = DeliveryOrder::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Transaksi Penjualan';
    protected static ?string $navigationLabel = 'Surat Jalan';
    protected static ?string $modelLabel = 'Surat Jalan';
    protected static ?string $pluralModelLabel = 'Surat Jalan';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Admin') || auth()->check() && auth()->user()->hasPermissionTo('manage_delivery_orders');
    }
    
        // Tambahkan method ini di class DeliveryOrderResource
    private static function hydrateDisplayFields(Set $set, Get $get, array $state): void
    {
        $warehouseId = $get('warehouse_id');
        foreach ($state ?? [] as $key => $item) {
            $soDetail = SalesOrderDetail::with(['product', 'salesOrder'])->find($item['so_detail_id'] ?? null);
            $stock = ProductStock::where('product_id', $item['product_id'] ?? null)
                ->where('warehouse_id', $warehouseId)
                ->first();

            $remaining = $soDetail?->remaining_qty ?? 0;
            $stockQty = $stock?->available_stock ?? 0;
            $qty = $item['qty'] ?? 0;
            
            if (filled($item['id'] ?? null)) {
                $remaining += $qty;
            }

            $set("details.{$key}.so_number", $soDetail?->salesOrder?->so_number ?? '-');
            $set("details.{$key}.product_name", $soDetail?->product?->name ?? '-');
            $set("details.{$key}.remaining_qty", $remaining);
            $set("details.{$key}.available_stock", $stockQty);
            $set("details.{$key}.remaining_after", max(0, $remaining - $qty));
            $set("details.{$key}.stock_after", max(0, $stockQty - $qty));
        }
    }

    private static function loadPendingItems(?int $customerId, ?int $warehouseId, Set $set, $livewire): void
    {
        if (!$customerId || !$warehouseId) {
            $set('details', []);
            $set('total_qty', 0);
            return;
        }

        if (!($livewire instanceof \Filament\Resources\Pages\CreateRecord)) {
            return;
        }

        $details = SalesOrderDetail::whereHas('salesOrder', function ($q) use ($customerId) {
                $q->where('customer_id', $customerId)
                  ->whereIn('status', ['OPEN', 'PARTIAL']);
            })
            ->where('remaining_qty', '>', 0)
            ->with(['product', 'salesOrder'])
            ->orderBy('created_at')
            ->get();

        $data = [];
        foreach ($details as $d) {
            $stock = ProductStock::where('product_id', $d->product_id)
                ->where('warehouse_id', $warehouseId)
                ->first();

            $data[] = [
                'so_detail_id' => $d->id,
                'product_id' => $d->product_id,
                'so_number' => $d->salesOrder->so_number,
                'product_name' => $d->product->name,
                'remaining_qty' => $d->remaining_qty,
                'available_stock' => $stock ? $stock->available_stock : 0,
                'qty' => 0,
                'remaining_after' => $d->remaining_qty,
                'stock_after' => $stock ? $stock->available_stock : 0,
                'notes' => null,
            ];
        }

        $set('details', $data);
        $set('total_qty', 0);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi SJ')
                    ->schema([
                        Forms\Components\TextInput::make('do_number')
                            ->label('Nomor SJ')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn () => 'SJ-' . date('Ymd') . '-' . rand(1000, 9999))
                            ->maxLength(50),

                        Forms\Components\DatePicker::make('date')
                            ->label('Tanggal')
                            ->required()
                            ->default(now()),

                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->options(Customer::pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get, $livewire) {
                                self::loadPendingItems($state, $get('warehouse_id'), $set, $livewire);
                            })
                            ->disabled(fn (string $operation): bool => $operation === 'edit'),

                        Forms\Components\Select::make('warehouse_id')
                            ->label('Gudang')
                            ->options(Warehouse::pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get, $livewire) {
                                self::loadPendingItems($get('customer_id'), $state, $set, $livewire);
                            })
                            ->disabled(fn (string $operation): bool => $operation === 'edit'),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'DRAFT' => 'Draft',
                                'SHIPPED' => 'Dikirim',
                                'DELIVERED' => 'Terkirim',
                                'CANCEL' => 'Batal',
                            ])
                            ->default('DRAFT')
                            ->required(),

                        Forms\Components\TextInput::make('driver')
                            ->label('Driver'),

                        Forms\Components\TextInput::make('vehicle')
                            ->label('Kendaraan'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Detail Barang dari Sales Order')
                    ->visible(fn (Get $get) => filled($get('customer_id')) && filled($get('warehouse_id')))
                    ->schema([
                        Forms\Components\Repeater::make('details')
                            ->relationship('details')
                            ->afterStateHydrated(function (Set $set, Get $get, $state) {
                                self::hydrateDisplayFields($set, $get, $state);
                            })
                            ->schema([
                                Forms\Components\Hidden::make('so_detail_id'),
                                Forms\Components\Hidden::make('product_id'),

                                Forms\Components\TextInput::make('so_number')
                                    ->label('No. SO')
                                    ->disabled()
                                    ->dehydrated(false),

                                Forms\Components\TextInput::make('product_name')
                                    ->label('Barang')
                                    ->disabled()
                                    ->dehydrated(false),

                                Forms\Components\TextInput::make('remaining_qty')
                                    ->label('Sisa Order')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false),

                                Forms\Components\TextInput::make('available_stock')
                                    ->label('Stok Gudang')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->hint(function (Get $get) {
                                        $remaining = (int) ($get('remaining_qty') ?? 0);
                                        $stock = (int) ($get('available_stock') ?? 0);
                                        if ($stock < $remaining && $stock > 0) {
                                            return 'Stok tidak cukup!';
                                        }
                                        return null;
                                    })
                                    ->hintColor('danger'),

                                Forms\Components\TextInput::make('qty')
                                    ->label('Jumlah Kirim')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $qty = (int) ($state ?? 0);
                                        $remaining = (int) ($get('remaining_qty') ?? 0);
                                        $stock = (int) ($get('available_stock') ?? 0);

                                        $set('remaining_after', max(0, $remaining - $qty));
                                        $set('stock_after', max(0, $stock - $qty));
                                    })
                                    ->hint(function (Get $get) {
                                        $remaining = (int) ($get('remaining_qty') ?? 0);
                                        $stock = (int) ($get('available_stock') ?? 0);
                                        $max = min($remaining, $stock);
                                        return "Max: {$max} unit";
                                    }),

                                Forms\Components\TextInput::make('remaining_after')
                                    ->label('Sisa Setelah Kirim')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->suffix(function (?int $state): string {
                                        return ($state !== null && $state <= 0) ? '✅' : '';
                                    }),

                                Forms\Components\TextInput::make('stock_after')
                                    ->label('Stok Setelah Kirim')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->suffix(function (?int $state): string {
                                        if ($state === null) return '';
                                        if ($state < 0) return '❌';
                                        if ($state == 0) return '⚠️';
                                        return '';
                                    }),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Catatan')
                                    ->columnSpanFull(),
                            ])
                            ->columns(4)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->afterStateUpdated(function ($state, Set $set) {
                                $total = 0;
                                foreach ($state ?? [] as $item) {
                                    $total += (int) ($item['qty'] ?? 0);
                                }
                                $set('total_qty', $total);
                            }),
                    ]),

                Forms\Components\Section::make('Ringkasan')
                    ->schema([
                        Forms\Components\TextInput::make('total_qty')
                            ->label('Total Qty')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('do_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date('d M Y'),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer'),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Gudang'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'DRAFT' => 'gray',
                        'SHIPPED' => 'info',
                        'DELIVERED' => 'success',
                        'CANCEL' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('total_qty'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'DRAFT' => 'Draft',
                        'SHIPPED' => 'Dikirim',
                        'DELIVERED' => 'Terkirim',
                        'CANCEL' => 'Batal',
                    ]),
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('printPdf')
                        ->label('Print PDF')
                        ->icon('heroicon-o-printer')
                        ->color('info')
                        ->action(function (DeliveryOrder $record) {
                            $company = CompanySetting::first();
                            $pdf = Pdf::loadView('pdf.do', ['do' => $record->load('details.product', 'salesOrder', 'customer'), 'company' => $company]);
                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf->output();
                            }, 'SJ-' . $record->do_number . '.pdf');
                        }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                ]),
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
            'index' => Pages\ListDeliveryOrders::route('/'),
            'create' => Pages\CreateDeliveryOrder::route('/create'),
            'edit' => Pages\EditDeliveryOrder::route('/{record}/edit'),
        ];
    }
}