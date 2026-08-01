<x-filament-panels::page>
    <x-filament-panels::form wire:submit="getReportData">
        {{ $this->form }}

        <x-filament::button type="submit" wire:loading.attr="disabled">
            Tampilkan
        </x-filament::button>
    </x-filament-panels::form>

    @php
        $report = $this->getReportData();
        $from = $this->data['from_date'] ?? null;
        $to = $this->data['to_date'] ?? null;
    @endphp

    @if($from && $to)
        <div class="mt-6 space-y-4">
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Periode</p>
                    <p class="text-lg font-bold text-gray-800 mt-1">
                        {{ \Carbon\Carbon::parse($from)->format('d M Y') }} s.d. {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
                    </p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Total Debit</p>
                    <p class="text-lg font-bold text-green-700 mt-1">Rp {{ number_format($report['total_debit'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Total Kredit</p>
                    <p class="text-lg font-bold text-red-700 mt-1">Rp {{ number_format($report['total_credit'], 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="overflow-x-auto bg-white rounded-xl shadow-sm border dark:bg-gray-800">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Kode</th>
                            <th class="px-4 py-3 text-left font-semibold">Nama Akun</th>
                            <th class="px-4 py-3 text-center font-semibold">Tipe</th>
                            <th class="px-4 py-3 text-right font-semibold">Debit</th>
                            <th class="px-4 py-3 text-right font-semibold">Kredit</th>
                            <th class="px-4 py-3 text-right font-semibold">Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($report['rows'] as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-3 font-mono text-xs">{{ $row->code }}</td>
                                <td class="px-4 py-3 font-medium">{{ $row->name }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700">
                                        {{ $row->type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-green-600">
                                    {{ $row->debit > 0 ? 'Rp ' . number_format($row->debit, 0, ',', '.') : '-' }}
                                </td>
                                <td class="px-4 py-3 text-right text-red-600">
                                    {{ $row->credit > 0 ? 'Rp ' . number_format($row->credit, 0, ',', '.') : '-' }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold">
                                    Rp {{ number_format($row->saldo, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-6 text-center text-gray-500" colspan="6">
                                    Tidak ada transaksi dalam periode ini
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100 dark:bg-gray-700 font-semibold">
                        <tr>
                            <td class="px-4 py-3" colspan="3">TOTAL</td>
                            <td class="px-4 py-3 text-right text-green-600">Rp {{ number_format($report['total_debit'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-red-600">Rp {{ number_format($report['total_credit'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
