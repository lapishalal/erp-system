<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\SalesInvoice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class ReceivableReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';
    protected static ?string $navigationGroup = 'Laporan';
    protected static ?string $navigationLabel = 'Laporan Piutang';
    protected static ?string $title = 'Laporan Piutang (AR Aging)';
    protected static string $view = 'filament.pages.receivable-report';
    protected static ?string $slug = 'laporan-piutang';
    protected static ?int $sort = 24;

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

        $invoices = SalesInvoice::with('customer')
            ->whereIn('status', ['UNPAID', 'PARTIAL', 'OVERDUE'])
            ->orderBy('customer_id')
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
                'customer' => $inv->customer?->name ?? '-',
                'invoice_number' => $inv->invoice_number,
                'due_date' => $inv->due_date,
                'total' => (float) $inv->total,
                'paid' => (float) $inv->paid_amount,
                'remaining' => $remaining,
                'days_overdue' => max(0, (int) $daysOverdue),
                'bucket' => $bucket,
            ];
        });

        $totalRemaining = $rows->sum('remaining');

        return [
            'as_of' => $asOf,
            'rows' => $rows,
            'buckets' => $buckets,
            'total_remaining' => $totalRemaining,
            'customer_count' => Customer::whereHas('salesInvoices', function ($q) {
                $q->whereIn('status', ['UNPAID', 'PARTIAL', 'OVERDUE']);
            })->count(),
        ];
    }
}
