<?php

namespace App\Filament\Pages;

use App\Models\MarketplaceOrder;
use App\Models\PurchaseReturn;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class ReturnReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';
    protected static ?string $navigationGroup = 'Laporan';
    protected static ?string $navigationLabel = 'Laporan Retur';
    protected static ?string $title = 'Laporan Retur';
    protected static string $view = 'filament.pages.return-report';
    protected static ?string $slug = 'laporan-retur';
    protected static ?int $sort = 27;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && (
            auth()->user()->hasRole('Admin')
            || auth()->user()->hasPermissionTo('view_reports')
        );
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'from_date' => now()->startOfMonth()->format('Y-m-d'),
            'to_date' => now()->endOfMonth()->format('Y-m-d'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('from_date')
                    ->label('Dari Tanggal')
                    ->required()
                    ->default(now()->startOfMonth()),
                DatePicker::make('to_date')
                    ->label('Sampai Tanggal')
                    ->required()
                    ->default(now()->endOfMonth()),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function getReportData(): array
    {
        $from = $this->data['from_date'] ?? null;
        $to = $this->data['to_date'] ?? null;

        if (!$from || !$to) {
            return ['purchase_returns' => [], 'marketplace_returns' => []];
        }

        $purchaseReturns = PurchaseReturn::with('supplier')
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get();

        // Retur/pembatalan order marketplace yang sudah pernah diproses
        $marketplaceReturns = MarketplaceOrder::with('items')
            ->where('status', 'CANCEL')
            ->whereNotNull('processed_at')
            ->whereBetween('processed_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->orderBy('processed_at')
            ->get();

        return [
            'purchase_returns' => $purchaseReturns,
            'marketplace_returns' => $marketplaceReturns,
            'purchase_total' => $purchaseReturns->sum('total_amount'),
            'marketplace_total' => $marketplaceReturns->sum(fn ($o) => $o->items->sum('subtotal_after_discount')),
        ];
    }
}
