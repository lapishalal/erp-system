<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Filament\Resources\SalesInvoiceResource;
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
            'dari' => null,
            'sampai' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('dari')
                    ->label('Dari Tanggal Order')
                    ->placeholder('Semua'),
                DatePicker::make('sampai')
                    ->label('Sampai Tanggal Order')
                    ->placeholder('Semua'),
            ])
            ->statePath('data');
    }

    public function getReportData(): array
    {
        $dari = $this->data['dari'] ?? null;
        $sampai = $this->data['sampai'] ?? null;

        // As-of untuk aging: mengikuti tanggal "Sampai", fallback ke hari ini
        $asOf = $sampai ?? now()->format('Y-m-d');

        $invoices = SalesInvoice::with('customer', 'salesOrder')
            ->whereIn('status', ['UNPAID', 'PARTIAL', 'OVERDUE'])
            ->when($dari || $sampai, function ($q) use ($dari, $sampai) {
                $q->whereHas('salesOrder', function ($sq) use ($dari, $sampai) {
                    $sq->when($dari, fn ($qq) => $qq->whereDate('date', '>=', $dari))
                        ->when($sampai, fn ($qq) => $qq->whereDate('date', '<=', $sampai));
                });
            })
            ->orderBy('customer_id')
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
                'id' => $inv->id,
                'customer' => $inv->customer?->name ?? '-',
                'invoice_number' => $inv->invoice_number,
                'invoice_url' => SalesInvoiceResource::getUrl('view', ['record' => $inv]),
                'so_number' => $inv->salesOrder?->so_number ?? '-',
                'order_date' => $inv->salesOrder?->date,
                'due_date' => $inv->due_date,
                'total' => (float) $inv->total,
                'paid' => (float) $inv->paid_amount,
                'remaining' => $remaining,
                'days_overdue' => max(0, (int) $daysOverdue),
                'bucket' => $bucket,
                'needs_check' => $daysOverdue > 10,
            ];
        });

        $needsCheck = $rows->filter(fn ($row) => $row->needs_check)->values();

        return [
            'dari' => $dari,
            'sampai' => $sampai,
            'as_of' => $asOf,
            'rows' => $rows,
            'buckets' => $buckets,
            'total_remaining' => $rows->sum('remaining'),
            'needs_check' => $needsCheck,
            'needs_check_count' => $needsCheck->count(),
            'needs_check_total' => $needsCheck->sum('remaining'),
            'customer_count' => Customer::whereHas('salesInvoices', function ($q) {
                $q->whereIn('status', ['UNPAID', 'PARTIAL', 'OVERDUE']);
            })->count(),
        ];
    }
}
