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
                    <p class="text-xs font-medium text-gray-500">Per Tanggal</p>
                    <p class="text-lg font-bold text-gray-800 mt-1">{{ \Carbon\Carbon::parse($report['as_of'])->format('d M Y') }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Jumlah Item</p>
                    <p class="text-lg font-bold text-gray-800 mt-1">{{ $report['item_count'] }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Total Qty</p>
                    <p class="text-lg font-bold text-gray-800 mt-1">{{ number_format($report['total_qty'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Nilai Persediaan</p>
                    <p class="text-lg font-bold text-blue-700 mt-1">Rp {{ number_format($report['total_value'], 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="overflow-x-auto bg-white rounded-xl shadow-sm border dark:bg-gray-800">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Kode</th>
                            <th class="px-4 py-3 text-left font-semibold">Nama Barang</th>
                            <th class="px-4 py-3 text-center font-semibold">Gudang</th>
                            <th class="px-4 py-3 text-right font-semibold">Qty</th>
                            <th class="px-4 py-3 text-right font-semibold">Harga Pokok</th>
                            <th class="px-4 py-3 text-right font-semibold">Nilai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($report['rows'] as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-3 font-mono text-xs">{{ $row->code }}</td>
                                <td class="px-4 py-3 font-medium">{{ $row->name }}</td>
                                <td class="px-4 py-3 text-center">{{ $row->warehouse }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row->qty, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format($row->cost, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-blue-700">Rp {{ number_format($row->value, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-6 text-center text-gray-500" colspan="6">
                                    Tidak ada stok barang
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100 dark:bg-gray-700 font-semibold">
                        <tr>
                            <td class="px-4 py-3" colspan="3">TOTAL</td>
                            <td class="px-4 py-3 text-right">{{ number_format($report['total_qty'], 0, ',', '.') }}</td>
                            <td></td>
                            <td class="px-4 py-3 text-right text-blue-700">Rp {{ number_format($report['total_value'], 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @else
        <div class="mt-6 bg-white rounded-xl shadow-sm border p-6 text-center dark:bg-gray-800">
            <p class="text-sm text-gray-500">Tidak ada stok barang.</p>
        </div>
    @endif
</x-filament-panels::page>
