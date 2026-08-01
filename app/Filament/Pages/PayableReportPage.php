<?php

namespace App\Filament\Pages;

use App\Models\PurchaseInvoice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class PayableReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-down';
    protected static ?string $navigationGroup = 'Laporan';
    protected static ?string $navigationLabel = 'Laporan Hutang';
    protected static ?string $title = 'Laporan Hutang (AP Aging)';
    protected static string $view = 'filament.pages.payable-report';
    protected static ?string $slug = 'laporan-hutang';
    protected static ?int $sort = 25;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && (
            auth()->user()->hasRole('Admin')
            || auth()->user()->hasPermissionTo('view_reports')
            || auth()->user()->hasPermissionTo('view_financial_report')
        );
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'as_of' => now()->format('Y-m-d'),
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
            ])
            ->statePath('data');
    }

    public function getReportData(): array
    {
        $asOf = $this->data['as_of'] ?? now()->format('Y-m-d');

        $invoices = PurchaseInvoice::with('supplier')
            ->whereIn('status', ['UNPAID', 'PARTIAL', 'OVERDUE'])
            ->orderBy('supplier_id')
            ->orderBy('due_date')
            ->get();

        $buckets = ['current' => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, 'above_90' => 0];

        $rows = $invoices->map(function ($inv) use ($asOf, &$buckets) {
            $remaining = (float) $inv->total - (float) $inv->paid_amount;
            $due = $inv->due_date ? \Carbon\Carbon::parse($inv->due_date) : null;
            $daysOverdue = $due ? $due->diffInDays(\Carbon\Carbon::parse($asOf), false) : 0;

            $bucket = 'current';
            if ($daysOverdue > 90) {
                $bucket = 'above_90';
            } elseif ($daysOverdue > 60) {
                $bucket = '61_90';
            } elseif ($daysOverdue > 30) {
                $bucket = '31_60';
            } elseif ($daysOverdue > 0) {
                $bucket = '1_30';
            }

            if ($remaining > 0) {
                $buckets[$bucket] += $remaining;
            }

            return (object) [
                'supplier' => $inv->supplier?->name ?? '-',
                'invoice_number' => $inv->invoice_number,
                'due_date' => $inv->due_date,
                'total' => (float) $inv->total,
                'paid' => (float) $inv->paid_amount,
                'remaining' => $remaining,
                'days_overdue' => max(0, (int) $daysOverdue),
                'bucket' => $bucket,
            ];
        });

        return [
            'as_of' => $asOf,
            'rows' => $rows,
            'buckets' => $buckets,
            'total_remaining' => $rows->sum('remaining'),
            'supplier_count' => $rows->pluck('supplier')->filter()->unique()->count(),
        ];
    }
}
