<?php

namespace App\Filament\Pages;

use App\Models\Brand;
use App\Models\Category;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ProductProfitReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Laporan';
    protected static ?string $navigationLabel = 'Laba per Produk';
    protected static ?string $title = 'Laba per Produk';
    protected static string $view = 'filament.pages.product-profit-report';
    protected static ?string $slug = 'laba-per-produk';
    protected static ?int $sort = 29;

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
            'brand_id' => null,
            'category_id' => null,
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
                Select::make('brand_id')
                    ->label('Brand')
                    ->options(Brand::pluck('name', 'id'))
                    ->searchable()
                    ->placeholder('Semua Brand'),
                Select::make('category_id')
                    ->label('Kategori')
                    ->options(Category::pluck('name', 'id'))
                    ->searchable()
                    ->placeholder('Semua Kategori'),
            ])
            ->columns(4)
            ->statePath('data');
    }

    public function getReportData(): array
    {
        $from = $this->data['from_date'] ?? null;
        $to = $this->data['to_date'] ?? null;
        $brandId = $this->data['brand_id'] ?? null;
        $categoryId = $this->data['category_id'] ?? null;

        if (!$from || !$to) {
            return ['rows' => [], 'totals' => []];
        }

        $query = DB::table('sales_order_details as sod')
            ->join('sales_orders as so', 'so.id', '=', 'sod.so_id')
            ->join('products as p', 'p.id', '=', 'sod.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->whereBetween('so.date', [$from, $to])
            ->where('so.status', '!=', 'CANCEL')
            ->when($brandId, fn ($q) => $q->where('p.brand_id', $brandId))
            ->when($categoryId, fn ($q) => $q->where('p.category_id', $categoryId))
            ->select(
                'p.id',
                'p.code',
                'p.name',
                'p.sku',
                'b.name as brand_name'
            )
            ->selectRaw('SUM(sod.qty) as qty')
            ->selectRaw('SUM(sod.subtotal) as revenue')
            ->selectRaw('SUM(sod.profit) as profit')
            ->groupBy('p.id', 'p.code', 'p.name', 'p.sku', 'b.name')
            ->orderByDesc(DB::raw('SUM(sod.subtotal)'))
            ->get();

        $rows = $query->map(function ($r) {
            $revenue = (float) $r->revenue;
            $profit = (float) $r->profit;
            $cost = $revenue - $profit;

            return (object) [
                'code' => $r->code,
                'name' => $r->name,
                'sku' => $r->sku,
                'brand' => $r->brand_name ?? '-',
                'qty' => (int) $r->qty,
                'revenue' => $revenue,
                'cost' => $cost,
                'profit' => $profit,
                'margin' => $revenue > 0 ? ($profit / $revenue) * 100 : 0,
            ];
        });

        $totals = [
            'qty' => $rows->sum('qty'),
            'revenue' => $rows->sum('revenue'),
            'cost' => $rows->sum('cost'),
            'profit' => $rows->sum('profit'),
        ];

        return [
            'rows' => $rows,
            'totals' => $totals,
        ];
    }
}
