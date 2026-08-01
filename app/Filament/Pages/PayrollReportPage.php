<?php

namespace App\Filament\Pages;

use App\Models\Payroll;
use App\Models\PayrollPeriod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class PayrollReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Payroll';
    protected static ?string $navigationLabel = 'Laporan Payroll';
    protected static ?string $title = 'Laporan Payroll';
    protected static string $view = 'filament.pages.payroll-report';
    protected static ?string $slug = 'laporan-payroll';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && (
            auth()->user()->hasRole('Admin')
            || auth()->user()->hasPermissionTo('manage_payroll')
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
            return ['periods' => collect(), 'rows' => [], 'totals' => []];
        }

        // Ambil payroll yang periodenya jatuh dalam rentang tanggal (via payment_date)
        $periodIds = PayrollPeriod::whereBetween('payment_date', [$from, $to])
            ->orWhereBetween('cutoff_date', [$from, $to])
            ->pluck('id');

        $payrolls = Payroll::with('employee', 'payrollPeriod')
            ->whereIn('payroll_period_id', $periodIds)
            ->orderByDesc('payroll_period_id')
            ->get();

        $rows = $payrolls->map(function ($p) {
            return (object) [
                'period' => $p->payrollPeriod?->period_name ?? '-',
                'employee' => $p->employee?->name ?? '-',
                'payroll_number' => $p->payroll_number,
                'basic_salary' => (float) $p->basic_salary,
                'total_allowances' => (float) $p->total_allowances,
                'gross_salary' => (float) $p->gross_salary,
                'bpjs_employee' => (float) $p->total_bpjs_employee,
                'bpjs_company' => (float) $p->total_bpjs_company,
                'pph21' => (float) $p->pph21_deduction,
                'loan_deduction' => (float) $p->loan_deduction,
                'net_salary' => (float) $p->net_salary,
                'status' => $p->status,
            ];
        });

        $totals = [
            'count' => $rows->count(),
            'gross' => $rows->sum('gross_salary'),
            'bpjs_employee' => $rows->sum('bpjs_employee'),
            'bpjs_company' => $rows->sum('bpjs_company'),
            'pph21' => $rows->sum('pph21'),
            'loan' => $rows->sum('loan_deduction'),
            'net' => $rows->sum('net_salary'),
        ];

        return [
            'periods' => $periodIds,
            'rows' => $rows,
            'totals' => $totals,
        ];
    }
}
