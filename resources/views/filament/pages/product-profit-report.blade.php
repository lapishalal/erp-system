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
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Periode</p>
                    <p class="text-lg font-bold text-gray-800 mt-1">
                        {{ \Carbon\Carbon::parse($from)->format('d M Y') }} s.d. {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
                    </p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Jumlah Terjual</p>
                    <p class="text-lg font-bold text-gray-800 mt-1">{{ number_format($report['totals']['qty'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Total Penjualan</p>
                    <p class="text-lg font-bold text-gray-800 mt-1">Rp {{ number_format($report['totals']['revenue'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Total Laba Kotor</p>
                    <p class="text-lg font-bold text-green-700 mt-1">Rp {{ number_format($report['totals']['profit'], 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="overflow-x-auto bg-white rounded-xl shadow-sm border dark:bg-gray-800">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Kode</th>
                            <th class="px-4 py-3 text-left font-semibold">Nama Produk</th>
                            <th class="px-4 py-3 text-left font-semibold">Brand</th>
                            <th class="px-4 py-3 text-right font-semibold">Qty</th>
                            <th class="px-4 py-3 text-right font-semibold">Penjualan</th>
                            <th class="px-4 py-3 text-right font-semibold">HPP</th>
                            <th class="px-4 py-3 text-right font-semibold">Laba</th>
                            <th class="px-4 py-3 text-right font-semibold">Margin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($report['rows'] as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-3 font-mono text-xs">{{ $row->code }}</td>
                                <td class="px-4 py-3 font-medium">{{ $row->name }}</td>
                                <td class="px-4 py-3">{{ $row->brand }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row->qty, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format($row->revenue, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format($row->cost, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold {{ $row->profit >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                    Rp {{ number_format($row->profit, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format($row->margin, 1) }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-6 text-center text-gray-500" colspan="8">
                                    Tidak ada data penjualan pada periode ini
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100 dark:bg-gray-700 font-semibold">
                        <tr>
                            <td class="px-4 py-3" colspan="3">TOTAL</td>
                            <td class="px-4 py-3 text-right">{{ number_format($report['totals']['qty'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">Rp {{ number_format($report['totals']['revenue'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">Rp {{ number_format($report['totals']['cost'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-green-700">Rp {{ number_format($report['totals']['profit'], 0, ',', '.') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
