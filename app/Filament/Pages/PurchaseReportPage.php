<?php

namespace App\Filament\Pages;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class PurchaseReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Laporan';
    protected static ?string $navigationLabel = 'Laporan Pembelian';
    protected static ?string $title = 'Laporan Pembelian';
    protected static string $view = 'filament.pages.purchase-report';
    protected static ?string $slug = 'laporan-pembelian';
    protected static ?int $sort = 26;

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
            'supplier_id' => null,
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
                Select::make('supplier_id')
                    ->label('Supplier')
                    ->options(Supplier::pluck('name', 'id'))
                    ->searchable()
                    ->placeholder('Semua Supplier'),
            ])
            ->columns(3)
            ->statePath('data');
    }

    public function getReportData(): array
    {
        $from = $this->data['from_date'] ?? null;
        $to = $this->data['to_date'] ?? null;
        $supplierId = $this->data['supplier_id'] ?? null;

        if (!$from || !$to) {
            return ['summary' => [], 'rows' => [], 'total_amount' => 0];
        }

        $query = PurchaseOrder::with('supplier')
            ->whereBetween('date', [$from, $to])
            ->where('status', '!=', 'CANCEL');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $orders = $query->orderBy('date')->orderBy('po_number')->get();

        $summary = $orders->groupBy(fn ($o) => $o->supplier?->name ?? 'Tanpa Supplier')
            ->map(function ($group) {
                return (object) [
                    'supplier' => $group->first()->supplier?->name ?? 'Tanpa Supplier',
                    'count' => $group->count(),
                    'total' => $group->sum('total_amount'),
                ];
            })
            ->values();

        return [
            'summary' => $summary,
            'rows' => $orders,
            'total_amount' => $orders->sum('total_amount'),
            'total_count' => $orders->count(),
        ];
    }
}
