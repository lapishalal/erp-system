<?php

namespace App\Filament\Pages;

use App\Models\MarketplaceOrderItem;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Illuminate\Support\HtmlString;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class TikTokImportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationGroup = 'Transaksi Penjualan';
    protected static ?string $navigationLabel = 'Import TikTok';
    protected static ?string $title = 'Import Data TikTok Shop';
    protected static ?string $slug = 'tiktok-import';
    protected static string $view = 'filament.pages.tiktok-import';
    protected static ?int $sort = 10;

    public ?array $data = [];

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && (
            auth()->user()->hasRole('Admin')
            || auth()->user()->hasPermissionTo('manage_sales_orders')
        );
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Import Pesanan TikTok')
                    ->description('Upload file CSV "Semua Pesanan" dari TikTok Shop Seller Center. Order yang sudah pernah diimport akan di-skip atau diupdate statusnya saja.')
                    ->schema([
                        FileUpload::make('order_file')
                            ->label('File Pesanan')
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                            ->maxSize(10240)
                            ->directory('imports/tiktok-temp')
                            ->helperText('Format: CSV atau XLSX "Semua Pesanan" dari TikTok Seller Center'),
                        Placeholder::make('order_info')
                            ->label(' ')
                            ->content(new HtmlString('
                                <div class="space-y-1 text-sm text-gray-500">
                                    <p><strong>Apa yang dilakukan:</strong></p>
                                    <ul class="list-disc list-inside space-y-0.5 ml-2">
                                        <li>Membuat POS Transaction, Sales Order, Delivery Order, Sales Invoice</li>
                                        <li>Stok otomatis berkurang untuk status "Dikirim" / "Selesai"</li>
                                        <li>Mapping produk menggunakan field <strong>SKU</strong> di data Barang</li>
                                        <li>Order duplikat otomatis di-skip, status berubah otomatis diupdate</li>
                                        <li>Produk yang <strong>SKU-nya tidak cocok</strong> akan disimpan dan bisa di-map manual di halaman <strong>"Produk Belum Ter-map TikTok"</strong></li>
                                    </ul>
                                    <p class="mt-2"><strong>Mapping Status:</strong></p>
                                    <ul class="list-disc list-inside space-y-0.5 ml-2">
                                        <li>Perlu dikirim / Belum dibayar → SO(OPEN) + DO(DRAFT)</li>
                                        <li>Dikirim / Selesai → SO(COMPLETE) + DO(DELIVERED) + Stok berkurang</li>
                                        <li>Dibatalkan → Skip (tidak diproses)</li>
                                    </ul>
                                </div>
                            '),)
                    ])
                    ->columnSpanFull(),

                        Actions::make([
                            Action::make('importOrders')
                                ->label('Import Pesanan')
                                ->action('importOrders')
                                ->color('primary')
                                ->icon('heroicon-o-arrow-up-tray')
                                ->button(),
                        ]),

                Section::make('Import Income / Settlement TikTok')
                    ->description('Upload file CSV Income dari TikTok Shop Seller Center. File ini berisi pesanan yang sudah settlement (uang cair).')
                    ->schema([
                        FileUpload::make('income_file')
                            ->label('File Income')
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                            ->maxSize(10240)
                            ->directory('imports/tiktok-temp')
                            ->helperText('Format: CSV atau XLSX Income dari TikTok Seller Center'),
                        Placeholder::make('income_info')
                            ->label(' ')
                            ->content(new HtmlString('
                                <div class="space-y-1 text-sm text-gray-500">
                                    <p><strong>Apa yang dilakukan:</strong></p>
                                    <ul class="list-disc list-inside space-y-0.5 ml-2">
                                        <li>Mencocokkan dengan SO yang sudah ada (dari import pesanan)</li>
                                        <li>Membuat CashIn (penerimaan kas) sesuai settlement amount</li>
                                        <li>Auto-jurnal: Debit Kas, Credit Penjualan</li>
                                        <li>Auto-jurnal HPP: Debit HPP, Credit Persediaan</li>
                                        <li>Update Invoice status → PAID</li>
                                        <li>Jika order belum pernah diimport, otomatis dibuatkan semua dokumen</li>
                                    </ul>
                                    <p class="mt-2"><strong>Jumlah penyelesaian pembayaran</strong> = uang yang cair ke rekening (netto setelah komisi & ongkir)</p>
                                </div>
                            '),)
                    ])
                    ->columnSpanFull(),

                        Actions::make([
                            Action::make('importIncome')
                                ->label('Import Income')
                                ->action('importIncome')
                                ->color('success')
                                ->icon('heroicon-o-banknotes')
                                ->button(),
                        ]),
            ])
            ->statePath('data');
    }

    public function importOrders(): void
    {
        $data = $this->form->getState();
        $orderFile = $data['order_file'] ?? null;

        if (empty($orderFile)) {
            Notification::make()
                ->title('File pesanan belum dipilih')
                ->danger()
                ->send();
            return;
        }

        try {
            $filePath = storage_path('app/public/' . $orderFile);
            $service = new \App\Services\TikTokCsvImportService();
            $results = $service->importOrders($filePath);

            Notification::make()
                ->title('Import Pesanan Berhasil')
                ->body(sprintf(
                    "Total: %d | Baru: %d | Diupdate: %d | Dilewati: %d | Belum Map: %d | Error: %d",
                    $results['total'], $results['created'], $results['updated'],
                    $results['skipped'], $results['unmapped'] ?? 0, $results['errors']
                ))
                ->success()
                ->persistent()
                ->send();

            // Notify about unmapped items
            if (($results['unmapped'] ?? 0) > 0) {
                Notification::make()
                    ->title($results['unmapped'] . ' order perlu mapping manual')
                    ->body('Ada produk yang SKU-nya tidak ditemukan di ERP. Silakan buka halaman "Produk Belum Ter-map TikTok" untuk mapping manual.')
                    ->warning()
                    ->persistent()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('goToUnmapped')
                            ->label('Buka halaman Mapping')
                            ->button()
                            ->url(TikTokUnmappedProductPage::getUrl(), shouldOpenInNewTab: false),
                    ])
                    ->send();
            }

            if ($results['errors'] > 0) {
                $errorDetails = collect($results['details'])
                    ->where('action', 'error')
                    ->take(5)
                    ->map(fn($d) => "{$d['order_id']}: {$d['message']}")
                    ->join("\n");

                Notification::make()
                    ->title('Detail Error (' . $results['errors'] . ' total)')
                    ->body($errorDetails)
                    ->warning()
                    ->persistent()
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Import Pesanan')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }

        $this->form->fill();
    }

    public function importIncome(): void
    {
        $data = $this->form->getState();
        $incomeFile = $data['income_file'] ?? null;

        if (empty($incomeFile)) {
            Notification::make()
                ->title('File income belum dipilih')
                ->danger()
                ->send();
            return;
        }

        try {
            $filePath = storage_path('app/public/' . $incomeFile);
            $service = new \App\Services\TikTokIncomeImportService();
            $results = $service->importIncome($filePath);

            Notification::make()
                ->title('Import Income Berhasil')
                ->body(sprintf(
                    "Total: %d | Dibuat/Dibayar: %d | Dilewati: %d | Error: %d",
                    $results['total'], $results['created'], $results['skipped'], $results['errors']
                ))
                ->success()
                ->persistent()
                ->send();

            if ($results['errors'] > 0) {
                $errorDetails = collect($results['details'])
                    ->where('action', 'error')
                    ->take(5)
                    ->map(fn($d) => "{$d['order_id']}: {$d['message']}")
                    ->join("\n");

                Notification::make()
                    ->title('Detail Error (' . $results['errors'] . ' total)')
                    ->body($errorDetails)
                    ->warning()
                    ->persistent()
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Import Income')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }

        $this->form->fill();
    }
}