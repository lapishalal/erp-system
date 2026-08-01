<x-filament-panels::page>
    <x-filament-panels::form wire:submit="getReportData">
        {{ $this->form }}

        <x-filament::button type="submit" wire:loading.attr="disabled">
            Tampilkan
        </x-filament::button>
    </x-filament-panels::form>

    @php
        $report = $this->getReportData();
    @endphp

    @if($report['rows']->isNotEmpty())
        <div class="mt-6 space-y-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Jumlah Karyawan</p>
                    <p class="text-lg font-bold text-gray-800 mt-1">{{ $report['totals']['count'] }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Total Gaji Kotor</p>
                    <p class="text-lg font-bold text-gray-800 mt-1">Rp {{ number_format($report['totals']['gross'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Total BPJS Karyawan</p>
                    <p class="text-lg font-bold text-amber-700 mt-1">Rp {{ number_format($report['totals']['bpjs_employee'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Total Gaji Bersih</p>
                    <p class="text-lg font-bold text-green-700 mt-1">Rp {{ number_format($report['totals']['net'], 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="overflow-x-auto bg-white rounded-xl shadow-sm border dark:bg-gray-800">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Periode</th>
                            <th class="px-4 py-3 text-left font-semibold">Karyawan</th>
                            <th class="px-4 py-3 text-right font-semibold">Gaji Pokok</th>
                            <th class="px-4 py-3 text-right font-semibold">Tunjangan</th>
                            <th class="px-4 py-3 text-right font-semibold">Gross</th>
                            <th class="px-4 py-3 text-right font-semibold">BPJS Karyawan</th>
                            <th class="px-4 py-3 text-right font-semibold">PPh 21</th>
                            <th class="px-4 py-3 text-right font-semibold">Kasbon</th>
                            <th class="px-4 py-3 text-right font-semibold">Bersih</th>
                            <th class="px-4 py-3 text-center font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($report['rows'] as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-3">{{ $row->period }}</td>
                                <td class="px-4 py-3 font-medium">{{ $row->employee }}</td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format($row->basic_salary, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format($row->total_allowances, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format($row->gross_salary, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format($row->bpjs_employee, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format($row->pph21, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format($row->loan_deduction, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-green-700">Rp {{ number_format($row->net_salary, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ $row->status }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-100 dark:bg-gray-700 font-semibold">
                        <tr>
                            <td class="px-4 py-3" colspan="4">TOTAL</td>
                            <td class="px-4 py-3 text-right">Rp {{ number_format($report['totals']['gross'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">Rp {{ number_format($report['totals']['bpjs_employee'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">Rp {{ number_format($report['totals']['pph21'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">Rp {{ number_format($report['totals']['loan'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-green-700">Rp {{ number_format($report['totals']['net'], 0, ',', '.') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @else
        <div class="mt-6 bg-white rounded-xl shadow-sm border p-6 text-center dark:bg-gray-800">
            <p class="text-sm text-gray-500">Tidak ada payroll pada rentang tanggal tersebut.</p>
        </div>
    @endif
</x-filament-panels::page>
