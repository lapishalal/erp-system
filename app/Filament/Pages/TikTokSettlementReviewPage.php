<?php

namespace App\Filament\Pages;

use App\Enums\MarketplacePlatform;
use App\Models\MarketplaceOrder;
use App\Services\TikTokOrderProcessingService;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TikTokSettlementReviewPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';
    protected static ?string $navigationGroup = 'Transaksi Penjualan';
    protected static ?string $navigationLabel = 'Review Settlement TikTok';
    protected static ?string $title = 'Review Settlement TikTok';
    protected static ?string $slug = 'review-settlement-tiktok';
    protected static string $view = 'filament.pages.tiktok-settlement-review';
    protected static ?int $sort = 13;

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
            ->where('needs_review', true)
            ->where('review_status', 'pending')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function mount(): void
    {
    }

    public function getTableQuery(): Builder
    {
        return MarketplaceOrder::query()
            ->with(['salesOrder.salesInvoices', 'reviewer', 'items'])
            ->where('platform', MarketplacePlatform::TIKTOK->value)
            ->where('needs_review', true)
            ->orderByDesc('created_at');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->defaultSort('created_at', 'desc')
            ->columns([
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

                TextColumn::make('created_at')
                    ->label('Tanggal Import')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('salesOrder.so_number')
                    ->label('SO')
                    ->searchable()
                    ->placeholder('-')
                    ->weight('semibold')
                    ->color('primary'),

                TextColumn::make('review_status')
                    ->label('Status Review')
                    ->badge()
                    ->state(fn (MarketplaceOrder $record): string => $record->review_status ?? 'pending')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Perlu Cek',
                        'retur_confirmed' => 'Retur',
                        'cancel_confirmed' => 'Cancel',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'retur_confirmed' => 'danger',
                        'cancel_confirmed' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('settlement_amount')
                    ->label('Settlement')
                    ->money('IDR')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('reviewed_at')
                    ->label('Direview')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('reviewer.name')
                    ->label('Oleh')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('review_note')
                    ->label('Catatan')
                    ->limit(40)
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('review_status')
                    ->label('Status Review')
                    ->options([
                        'pending' => 'Perlu Cek',
                        'retur_confirmed' => 'Retur',
                        'cancel_confirmed' => 'Cancel',
                    ]),
            ])
            ->actions([
                Action::make('retur')
                    ->label('Retur')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Retur')
                    ->modalDescription(fn (MarketplaceOrder $record): string => 'Yakin pesanan ' . $record->platform_order_id . ' adalah RETUR? SO/DO/Invoice akan dibatalkan dan stok otomatis dikembalikan. Pastikan barang sudah diterima kembali dari ekspedisi.')
                    ->modalSubmitActionLabel('Ya, Retur')
                    ->visible(fn (MarketplaceOrder $record): bool => ($record->review_status ?? 'pending') === 'pending')
                    ->form([
                        Textarea::make('review_note')
                            ->label('Catatan Hasil Cek Ekspedisi')
                            ->placeholder('Misal: barang sudah diterima kembali dari JNE, kondisi baik')
                            ->rows(3),
                    ])
                    ->action(function (MarketplaceOrder $record, array $data): void {
                        try {
                            $result = (new TikTokOrderProcessingService())->cancelOrderChain($record, true);

                            $record->update([
                                'needs_review'  => true,
                                'review_status' => 'retur_confirmed',
                                'reviewed_at'   => now(),
                                'reviewed_by'   => auth()->id(),
                                'review_note'   => $data['review_note'] ?? null,
                            ]);

                            $notification = Notification::make()
                                ->title('Order di-retur')
                                ->body($result['message'] ?? 'Order dibatalkan & stok dikembalikan.')
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

                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Cancel')
                    ->modalDescription(fn (MarketplaceOrder $record): string => 'Tandai pesanan ' . $record->platform_order_id . ' sebagai dibatalkan? Tidak ada dokumen yang diubah, hanya menyembunyikan pesanan dari daftar.')
                    ->modalSubmitActionLabel('Ya, Cancel')
                    ->visible(fn (MarketplaceOrder $record): bool => ($record->review_status ?? 'pending') === 'pending')
                    ->form([
                        Textarea::make('review_note')
                            ->label('Catatan')
                            ->placeholder('Misal: pesanan dibatalkan tanpa pengembalian barang')
                            ->rows(3),
                    ])
                    ->action(function (MarketplaceOrder $record, array $data): void {
                        $record->update([
                            'needs_review'  => true,
                            'review_status' => 'cancel_confirmed',
                            'reviewed_at'   => now(),
                            'reviewed_by'   => auth()->id(),
                            'review_note'   => $data['review_note'] ?? null,
                            'is_hidden'     => true,
                        ]);

                        Notification::make()
                            ->title('Pesanan ditandai Cancel')
                            ->body('Pesanan ' . $record->platform_order_id . ' disembunyikan. Tidak ada dokumen yang diubah.')
                            ->success()
                            ->persistent()
                            ->send();

                        $this->resetTable();
                    }),
            ])
            ->emptyStateHeading('Tidak ada pesanan yang perlu direview')
            ->emptyStateDescription('Import file Income TikTok terlebih dahulu. Pesanan dengan settlement Rp 0 akan muncul di sini untuk dicek manual.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }
}