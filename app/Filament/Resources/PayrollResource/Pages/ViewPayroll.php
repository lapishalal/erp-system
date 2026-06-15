<?php

namespace App\Filament\Resources\PayrollResource\Pages;

use App\Filament\Resources\PayrollResource;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewPayroll extends ViewRecord
{
    protected static string $resource = PayrollResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Informasi Slip Gaji')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('payroll_number')->label('No. Slip'),
                        TextEntry::make('employee.name')->label('Karyawan'),
                        TextEntry::make('payrollPeriod.period_name')->label('Periode'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match($state) {
                                'DRAFT' => 'gray',
                                'PROCESSED' => 'warning',
                                'PAID' => 'success',
                                default => 'gray',
                            }),
                    ]),

                Section::make('Penghasilan')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('basic_salary')->label('Gaji Pokok')->money('IDR'),
                        TextEntry::make('total_allowances')->label('Total Tunjangan')->money('IDR'),
                        TextEntry::make('total_earnings')->label('Total Penghasilan')->money('IDR')->weight('bold'),
                    ]),

                Section::make('BPJS (Beban Perusahaan)')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('bpjs_kesehatan_company')->label('BPJS Kesehatan')->money('IDR'),
                        TextEntry::make('bpjs_ketenagakerjaan_company')->label('BPJS Ketenagakerjaan')->money('IDR'),
                        TextEntry::make('total_bpjs_company')->label('Total BPJS Perusahaan')->money('IDR')->weight('bold'),
                    ]),

                Section::make('Potongan')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('bpjs_kesehatan_employee')->label('BPJS Kesehatan')->money('IDR'),
                        TextEntry::make('bpjs_ketenagakerjaan_employee')->label('BPJS Ketenagakerjaan')->money('IDR'),
                        TextEntry::make('pph21_deduction')->label('PPh 21')->money('IDR'),
                        TextEntry::make('loan_deduction')->label('Kasbon')->money('IDR'),
                        TextEntry::make('alpha_deduction')->label('Potongan Absensi')->money('IDR'),
                        TextEntry::make('total_deductions')->label('Total Potongan')->money('IDR')->weight('bold')->color('danger'),
                    ]),

                Section::make('Ringkasan')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('gross_salary')->label('Gross Salary')->money('IDR'),
                        TextEntry::make('net_salary')->label('Gaji Bersih (Net)')->money('IDR')->weight('bold')->size(TextEntry\TextEntrySize::Large),
                    ]),
            ]);
    }
}