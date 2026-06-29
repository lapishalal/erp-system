<?php

namespace App\Filament\Pages;

use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderItem;
use App\Models\Product;
use App\Services\TikTokOrderProcessingService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class TikTokUnmappedProductPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationGroup = 'Transaksi Penjualan';
    protected static ?string $navigationLabel = 'Produk Belum Ter-map TikTok';
    protected static ?string $title = 'Produk Belum Ter-map TikTok';
    protected static ?string $slug = 'tiktok-unmapped-products';
    protected static string $view = 'filament.pages.tiktok-unmapped-products';
    protected static ?int $sort = 11;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && (
            auth()->user()->hasRole('Admin')
            || auth()->user()->hasPermissionTo('manage_sales_orders')
        );
    }

    /**
     * Badge count di navigation menu
     */
    public static function getNavigationBadge(): ?string
    {
        $count = MarketplaceOrderItem::unmapped()
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function mount(): void
    {
        // nothing special
    }

    /**
     * @var bool Whether to show only unmapped items (default) or all items
     */
    public bool $showAllItems = false;

    public function toggleShowAll(): void
    {
        $this->showAllItems = !$this->showAllItems;
    }

    public function getTableQuery(): Builder
    {
        $query = MarketplaceOrderItem::query()
            ->with(['marketplaceOrder', 'mappedProduct']);

        if (!$this->showAllItems) {
            $query->where('is_mapped', false);
        }

        return $query->orderByDesc('created_at');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('marketplaceOrder.platform_order_id')
                    ->label('Order ID TikTok')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('product_name')
                    ->label('Nama Produk TikTok')
                    ->searchable()
                    ->wrap()
                    ->formatStateUsing(fn($record) => $record->display_name),

                TextColumn::make('seller_sku')
                    ->label('Seller SKU')
                    ->searchable()
                    ->placeholder('-')
                    ->badge()
                    ->color(fn($state) => filled($state) ? 'gray' : 'danger'),

                TextColumn::make('is_mapped')
                    ->label('Status')
                    ->badge()
                    ->toggleable()
                    ->formatStateUsing(fn($state) => $state ? 'Ter-map' : 'Belum Map')
                    ->color(fn($state) => $state ? 'success' : 'danger'),

                TextColumn::make('mappedProduct.name')
                    ->label('Produk ERP')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('-')
                    ->formatStateUsing(fn($record) => $record->is_mapped && $record->mappedProduct
                        ? $record->mappedProduct->name . ' [' . $record->mappedProduct->code . ']'
                        : '-'),

                TextColumn::make('variation')
                    ->label('Variasi')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->alignCenter(),

                TextColumn::make('unit_price')
                    ->label('Harga Satuan')
                    ->money('IDR')
                    ->alignEnd(),

                TextColumn::make('subtotal_after_discount')
                    ->label('Subtotal')
                    ->money('IDR')
                    ->alignEnd()
                    ->weight('bold'),

                TextColumn::make('marketplaceOrder.status')
                    ->label('Status Order')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'COMPLETE' => 'success',
                        'OPEN' => 'warning',
                        'CANCEL' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Tanggal Import')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status_order')
                    ->label('Status Order')
                    ->options([
                        'OPEN' => 'Open / Menunggu',
                        'COMPLETE' => 'Selesai',
                        'CANCEL' => 'Dibatalkan',
                    ])
                    ->query(function ($query, $state) {
                        if (filled($state)) {
                            $query->whereHas('marketplaceOrder', fn($q) => $q->where('status', $state));
                        }
                    }),
            ])
            ->actions([
                // Map to existing product
                Action::make('mapProduct')
                    ->label('Map ke Produk')
                    ->icon('heroicon-o-link')
                    ->color('primary')
                    ->visible(fn(MarketplaceOrderItem $record) => !$record->is_mapped)
                    ->form([
                        Placeholder::make('product_info')
                            ->label('Produk TikTok')
                            ->content(fn($record) => new HtmlString(
                                '<strong>' . e($record->product_name) . '</strong>' .
                                ($record->variation ? '<br>Variasi: ' . e($record->variation) : '') .
                                '<br>SKU: ' . e($record->seller_sku ?? '-') .
                                '<br>Harga: Rp ' . number_format($record->unit_price, 0, ',', '.')
                            )),

                        Select::make('mapped_product_id')
                            ->label('Pilih Produk ERP')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->options(function () {
                                return Product::where('is_active', true)
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn($p) => [
                                        $p->id => $p->name . ($p->sku ? " [SKU: {$p->sku}]" : '') . " [Kode: {$p->code}]"
                                    ]);
                            })
                            ->helperText('Cari berdasarkan nama produk, kode, atau SKU'),
                    ])
                    ->action(function (array $data, MarketplaceOrderItem $record): void {
                        try {
                            $processingService = new TikTokOrderProcessingService();
                            $isFullyMapped = $processingService->mapItem($record, (int) $data['mapped_product_id']);

                            if ($isFullyMapped) {
                                Notification::make()
                                    ->title('Semua item order sudah di-map!')
                                    ->body('Order ' . $record->marketplaceOrder->platform_order_id . ' siap diproses. Klik tombol "Proses Order" atau langsung import file Income.')
                                    ->success()
                                    ->persistent()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Item berhasil di-map')
                                    ->body('Masih ada item lain yang belum di-map pada order ini.')
                                    ->success()
                                    ->send();
                            }
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal map produk')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                // Create new product & map
                Action::make('createAndMap')
                    ->label('Buat Produk Baru & Map')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->visible(fn(MarketplaceOrderItem $record) => !$record->is_mapped)
                    ->form([
                        Placeholder::make('product_info')
                            ->label('Produk TikTok')
                            ->content(fn($record) => new HtmlString(
                                '<strong>' . e($record->product_name) . '</strong>' .
                                ($record->variation ? '<br>Variasi: ' . e($record->variation) : '') .
                                '<br>SKU TikTok: ' . e($record->seller_sku ?? '-') .
                                '<br>Harga: Rp ' . number_format($record->unit_price, 0, ',', '.')
                            )),

                        TextInput::make('product_code')
                            ->label('Kode Produk')
                            ->required()
                            ->maxLength(50)
                            ->default(fn($record) => 'TTK-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $record->product_name ?? 'XX'), 0, 6)))
                            ->helperText('Kode unik produk di ERP (contoh: PRD-001)'),

                        TextInput::make('product_name')
                            ->label('Nama Produk')
                            ->required()
                            ->maxLength(255)
                            ->default(fn($record) => $record->product_name),

                        TextInput::make('product_sku')
                            ->label('SKU')
                            ->maxLength(100)
                            ->default(fn($record) => $record->seller_sku)
                            ->helperText('Kosongkan jika tidak ada SKU. Jika diisi, akan digunakan untuk auto-match selanjutnya.'),

                        TextInput::make('default_sale_price')
                            ->label('Harga Jual Default')
                            ->numeric()
                            ->default(fn($record) => $record->unit_price)
                            ->helperText('Harga jual default yang akan digunakan di SO/Invoice'),

                        TextInput::make('last_buy_price')
                            ->label('Harga Beli Terakhir (HPP)')
                            ->numeric()
                            ->default(0)
                            ->helperText('Untuk perhitungan HPP. Isi 0 jika belum tahu.'),

                        TextInput::make('unit')
                            ->label('Satuan')
                            ->maxLength(20)
                            ->default('pcs'),
                    ])
                    ->action(function (array $data, MarketplaceOrderItem $record): void {
                        try {
                            $processingService = new TikTokOrderProcessingService();
                            $isFullyMapped = $processingService->mapItemWithNewProduct($record, [
                                'code' => $data['product_code'],
                                'name' => $data['product_name'],
                                'sku' => $data['product_sku'] ?? $record->seller_sku,
                                'default_sale_price' => $data['default_sale_price'] ?? $record->unit_price,
                                'last_buy_price' => $data['last_buy_price'] ?? 0,
                                'unit' => $data['unit'] ?? 'pcs',
                            ]);

                            if ($isFullyMapped) {
                                Notification::make()
                                    ->title('Produk baru dibuat & semua item order lengkap!')
                                    ->body('Produk "' . $data['product_name'] . '" [' . $data['product_code'] . '] berhasil dibuat. Order ' . $record->marketplaceOrder->platform_order_id . ' siap diproses.')
                                    ->success()
                                    ->persistent()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Produk baru dibuat & item di-map')
                                    ->body('Produk "' . $data['product_name'] . '" berhasil dibuat. Masih ada item lain yang belum di-map pada order ini.')
                                    ->success()
                                    ->send();
                            }
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal buat produk')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                // Quick auto-match by SKU
                Action::make('autoMatch')
                    ->label('Auto-Match SKU')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('gray')
                    ->visible(fn(MarketplaceOrderItem $record) => filled($record->seller_sku))
                    ->action(function (MarketplaceOrderItem $record): void {
                        $product = Product::where('sku', $record->seller_sku)->first();

                        if (!$product) {
                            Notification::make()
                                ->title('SKU tidak ditemukan')
                                ->body("Tidak ada produk di ERP dengan SKU: {$record->seller_sku}. Pastikan field SKU di data Barang sudah diisi.")
                                ->warning()
                                ->send();
                            return;
                        }

                        try {
                            $processingService = new TikTokOrderProcessingService();
                            $isFullyMapped = $processingService->mapItem($record, $product->id);

                            if ($isFullyMapped) {
                                Notification::make()
                                    ->title('Auto-match berhasil, semua item order sudah lengkap!')
                                    ->body("Ter-map ke: {$product->name} [{$product->code}]")
                                    ->success()
                                    ->persistent()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Auto-match berhasil')
                                    ->body("Ter-map ke: {$product->name} [{$product->code}]. Masih ada item lain yang belum di-map.")
                                    ->success()
                                    ->send();
                            }
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal auto-match')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                // Bulk auto-match
                \Filament\Tables\Actions\BulkAction::make('bulkAutoMatch')
                    ->label('Auto-Match Semua (by SKU)')
                    ->icon('heroicon-o-bolt')
                    ->color('warning')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                        $matched = 0;
                        $failed = 0;

                        foreach ($records as $record) {
                            if (empty($record->seller_sku)) {
                                $failed++;
                                continue;
                            }

                            $product = Product::where('sku', $record->seller_sku)->first();
                            if (!$product) {
                                $failed++;
                                continue;
                            }

                            try {
                                $processingService = new TikTokOrderProcessingService();
                                $processingService->mapItem($record, $product->id);
                                $matched++;
                            } catch (\Throwable $e) {
                                $failed++;
                            }
                        }

                        $notification = Notification::make()
                            ->title("Auto-match selesai")
                            ->body("Berhasil: {$matched} | Gagal: {$failed} (SKU tidak ditemukan di ERP)")
                            ->persistent();

                        if ($failed > 0) {
                            $notification->warning();
                        } else {
                            $notification->success();
                        }
                        $notification->send();
                    }),
            ])
            ->headerActions([
                // Toggle show all / unmapped only
                Action::make('toggleShowAll')
                    ->label(fn() => $this->showAllItems ? 'Tampilkan Hanya Belum Map' : 'Tampilkan Semua Item')
                    ->icon(fn() => $this->showAllItems ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn() => $this->showAllItems ? 'gray' : 'warning')
                    ->action(fn() => $this->toggleShowAll()),

                // Process all fully-mapped orders
                Action::make('processMappedOrders')
                    ->label('Proses Semua Order Siap')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(function () {
                        return MarketplaceOrder::query()
                            ->where('is_mapped', true)
                            ->whereNull('sales_order_id')
                            ->where('platform', 'tiktok')
                            ->exists();
                    })
                    ->action(function (): void {
                        $orders = MarketplaceOrder::query()
                            ->where('is_mapped', true)
                            ->whereNull('sales_order_id')
                            ->where('platform', 'tiktok')
                            ->get();

                        $success = 0;
                        $errors = 0;

                        foreach ($orders as $mkOrder) {
                            try {
                                $processingService = new TikTokOrderProcessingService();
                                $result = $processingService->createOrderChain($mkOrder);

                                if (($result['action'] ?? '') === 'created') {
                                    $success++;
                                }
                            } catch (\Throwable $e) {
                                $errors++;
                                \Illuminate\Support\Facades\Log::error('Process mapped order failed', [
                                    'order_id' => $mkOrder->platform_order_id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }

                        if ($success > 0) {
                            Notification::make()
                                ->title("{$success} order berhasil diproses!")
                                ->body('POS, SO, DO, dan Invoice telah dibuat. Stok otomatis berkurang untuk status Dikirim/Selesai.')
                                ->success()
                                ->persistent()
                                ->send();
                        }

                        if ($errors > 0) {
                            Notification::make()
                                ->title("{$errors} order gagal diproses")
                                ->body('Cek log untuk detail error.')
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),
            ])
            ->emptyStateHeading('Tidak ada produk yang belum di-map')
            ->emptyStateDescription('Semua produk dari import TikTok sudah ter-map dengan produk di ERP.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}