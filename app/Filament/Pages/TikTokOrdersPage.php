<?php

namespace App\Filament\Pages;

use App\Enums\MarketplacePlatform;
use App\Models\MarketplaceOrder;
use App\Services\TikTokOrderProcessingService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TikTokOrdersPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Transaksi Penjualan';
    protected static ?string $navigationLabel = 'List Orderan TikTok';
    protected static ?string $title = 'List Orderan TikTok';
    protected static ?string $slug = 'tiktok-orders';
    protected static string $view = 'filament.pages.tiktok-orders';
    protected static ?int $sort = 12;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && (
            auth()->user()->hasRole('Admin')
            || auth()->user()->hasPermissionTo('manage_sales_orders')
        );
    }

    public static function getNavigationBadge(): ?string
    {
        $count = MarketplaceOrder::query()
            ->where('platform', MarketplacePlatform::TIKTOK->value)
            ->where('is_mapped', true)
            ->whereNull('processed_at')
            ->where('status', '!=', 'CANCEL')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function mount(): void
    {
    }

    public bool $showHidden = false;

    public function toggleHidden(): void
    {
        $this->showHidden = !$this->showHidden;
        $this->resetTable();
    }

    public function getTableQuery(): Builder
    {
        return MarketplaceOrder::query()
            ->withCount('items')
            ->with(['salesOrder', 'items'])
            ->where('platform', MarketplacePlatform::TIKTOK->value)
            ->when(!$this->showHidden, fn (Builder $q) => $q->where('is_hidden', false))
            ->orderByDesc('created_at');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tanggal Import')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('platform_order_id')
                    ->label('Order ID TikTok')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('platform_order_sn')
                    ->label('No. Order')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'COMPLETE' => 'Selesai / Dikirim',
                        'OPEN' => 'Belum dibayar / Menunggu',
                        'CANCEL' => 'Dibatalkan',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'COMPLETE' => 'success',
                        'OPEN' => 'warning',
                        'CANCEL' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('items_count')
                    ->label('Item')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('mapped_items_count')
                    ->label('Ter-map')
                    ->alignCenter()
                    ->badge()
                    ->formatStateUsing(fn (MarketplaceOrder $record): string => $record->items_count > 0
                        ? "{$record->mapped_items_count}/{$record->items_count}"
                        : '-')
                    ->color(fn (MarketplaceOrder $record): string => $record->has_unmapped_items
                        ? 'warning'
                        : ($record->items_count > 0 ? 'success' : 'gray')),

                TextColumn::make('salesOrder.so_number')
                    ->label('SO')
                    ->searchable()
                    ->placeholder('-')
                    ->weight('semibold')
                    ->color('primary'),

                TextColumn::make('processed_at')
                    ->label('Proses')
                    ->alignCenter()
                    ->formatStateUsing(fn (MarketplaceOrder $record): string => match (true) {
                        $record->status === 'CANCEL' => 'Skipped',
                        $record->processed_at !== null => 'Selesai',
                        default => 'Belum',
                    })
                    ->badge()
                    ->color(fn (MarketplaceOrder $record): string => match (true) {
                        $record->status === 'CANCEL' => 'danger',
                        $record->processed_at !== null => 'success',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'COMPLETE' => 'Selesai / Dikirim',
                        'OPEN' => 'Belum dibayar / Menunggu',
                        'CANCEL' => 'Dibatalkan',
                    ]),

                SelectFilter::make('proses')
                    ->label('Status Proses')
                    ->options([
                        'belum' => 'Belum Diproses',
                        'selesai' => 'Sudah Diproses',
                        'cancel' => 'Dibatalkan (Skipped)',
                    ])
                    ->query(function (Builder $query, array $data): void {
                        $value = $data['value'] ?? null;
                        if (filled($value)) {
                            match ($value) {
                                'belum' => $query->whereNull('processed_at')->where('status', '!=', 'CANCEL'),
                                'selesai' => $query->whereNotNull('processed_at')->where('status', '!=', 'CANCEL'),
                                'cancel' => $query->where('status', 'CANCEL'),
                                default => null,
                            };
                        }
                    }),
            ])
            ->actions([
                Action::make('proses')
                    ->label('Proses')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (MarketplaceOrder $record): bool => $record->processed_at === null && $record->status !== 'CANCEL')
                    ->action(function (MarketplaceOrder $record): void {
                        if ($record->has_unmapped_items) {
                            Notification::make()
                                ->title('Masih ada item yang belum ter-map')
                                ->body('Map semua produk terlebih dahulu di halaman "Produk Belum Ter-map TikTok", lalu proses kembali.')
                                ->warning()
                                ->actions([
                                    \Filament\Notifications\Actions\Action::make('goMapping')
                                        ->label('Buka Mapping')
                                        ->button()
                                        ->url(TikTokUnmappedProductPage::getUrl()),
                                ])
                                ->send();
                            return;
                        }

                        try {
                            $result = (new TikTokOrderProcessingService())->createOrderChain($record);

                            Notification::make()
                                ->title('Order berhasil diproses')
                                ->body($result['message'] ?? 'Chain berhasil dibuat.')
                                ->success()
                                ->persistent()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal memproses order')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }

                        $this->resetTable();
                    }),

                Action::make('retur')
                    ->label('Retur')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Retur / Batalkan Order')
                    ->modalDescription(fn (MarketplaceOrder $record): string => 'Batalkan seluruh order ' . $record->platform_order_id . '? Stok akan dikembalikan, SO/DO dan Invoice dibatalkan.')
                    ->modalSubmitActionLabel('Ya, Batalkan')
                    ->visible(fn (MarketplaceOrder $record): bool => $record->sales_order_id !== null && $record->status !== 'CANCEL')
                    ->action(function (MarketplaceOrder $record): void {
                        try {
                            $result = (new TikTokOrderProcessingService())->cancelOrderChain($record, true);

                            $notification = Notification::make()
                                ->title('Order dibatalkan (Retur)')
                                ->body($result['message'] ?? 'Order dibatalkan.')
                                ->persistent();

                            if (($result['was_paid'] ?? false)) {
                                $notification->warning();
                            } else {
                                $notification->success();
                            }

                            $notification->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal retur order')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }

                        $this->resetTable();
                    }),

                Action::make('hide')
                    ->label('Hapus')
                    ->icon('heroicon-o-trash')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Sembunyikan Order')
                    ->modalDescription(fn (MarketplaceOrder $record): string => 'Sembunyikan order ' . $record->platform_order_id . ' dari daftar? Data tidak dihapus dari database.')
                    ->modalSubmitActionLabel('Ya, Sembunyikan')
                    ->visible(fn (MarketplaceOrder $record): bool => !$record->is_hidden)
                    ->action(function (MarketplaceOrder $record): void {
                        $record->update(['is_hidden' => true]);

                        Notification::make()
                            ->title('Order disembunyikan')
                            ->body('Order ' . $record->platform_order_id . ' tidak lagi tampil di daftar (data tetap tersimpan).')
                            ->success()
                            ->send();

                        $this->resetTable();
                    }),

                Action::make('unhide')
                    ->label('Tampilkan')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(fn (MarketplaceOrder $record): bool => $record->is_hidden)
                    ->action(function (MarketplaceOrder $record): void {
                        $record->update(['is_hidden' => false]);

                        Notification::make()
                            ->title('Order ditampilkan kembali')
                            ->body('Order ' . $record->platform_order_id . ' kembali tampil di daftar.')
                            ->success()
                            ->send();

                        $this->resetTable();
                    }),
            ])
            ->headerActions([
                Action::make('toggleHidden')
                    ->label(fn (): string => $this->showHidden ? 'Sembunyikan yang di-Hidden' : 'Tampilkan yang di-Hidden')
                    ->icon(fn (): string => $this->showHidden ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (): string => $this->showHidden ? 'gray' : 'info')
                    ->action(fn (): mixed => $this->toggleHidden()),

                Action::make('goImport')
                    ->label('Import TikTok')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->url(TikTokImportPage::getUrl()),

                Action::make('goMapping')
                    ->label('Produk Belum Ter-map')
                    ->icon('heroicon-o-map-pin')
                    ->color('warning')
                    ->url(TikTokUnmappedProductPage::getUrl()),
            ])
            ->emptyStateHeading('Tidak ada orderan TikTok')
            ->emptyStateDescription('Import file pesanan TikTok terlebih dahulu di menu "Import TikTok".')
            ->emptyStateIcon('heroicon-o-shopping-bag');
    }
}
