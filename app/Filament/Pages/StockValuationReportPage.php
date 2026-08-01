<?php

namespace App\Filament\Pages;

use App\Models\ProductStock;
use App\Models\Warehouse;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class StockValuationReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Laporan';
    protected static ?string $navigationLabel = 'Nilai Persediaan';
    protected static ?string $title = 'Nilai Persediaan (Stock Valuation)';
    protected static string $view = 'filament.pages.stock-valuation-report';
    protected static ?string $slug = 'nilai-persediaan';
    protected static ?int $sort = 28;

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
            'as_of' => now()->format('Y-m-d'),
            'warehouse_id' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('as_of')
                    ->label('Per Tanggal')
                    ->required()
                    ->default(now()),
                Select::make('warehouse_id')
                    ->label('Gudang')
                    ->options(Warehouse::pluck('name', 'id'))
                    ->searchable()
                    ->placeholder('Semua Gudang'),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function getReportData(): array
    {
        $warehouseId = $this->data['warehouse_id'] ?? null;
        $asOf = $this->data['as_of'] ?? now()->format('Y-m-d');

        $query = ProductStock::with('product', 'warehouse')
            ->where('physical_stock', '!=', 0);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $stocks = $query->orderBy('product_id')->get();

        $rows = $stocks->map(function ($stock) {
            $cost = $stock->product?->getHpp() ?? 0;
            $qty = (int) $stock->physical_stock;

            return (object) [
                'code' => $stock->product?->code ?? '-',
                'name' => $stock->product?->name ?? '-',
                'warehouse' => $stock->warehouse?->name ?? '-',
                'qty' => $qty,
                'cost' => (float) $cost,
                'value' => round($qty * $cost),
            ];
        });

        return [
            'as_of' => $asOf,
            'rows' => $rows,
            'total_qty' => $rows->sum('qty'),
            'total_value' => $rows->sum('value'),
            'item_count' => $rows->count(),
        ];
    }
}
