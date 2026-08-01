<x-filament-panels::page>
    <x-filament-panels::form wire:submit="getReportData">
        {{ $this->form }}

        <x-filament::button type="submit" wire:loading.attr="disabled">
            Tampilkan
        </x-filament::button>
    </x-filament-panels::form>

    @php
        $report = $this->getReportData();
        $bucketLabels = [
            'current' => 'Belum Jatuh Tempo',
            '1_30' => '1 - 30 Hari',
            '31_60' => '31 - 60 Hari',
            '61_90' => '61 - 90 Hari',
            'above_90' => '> 90 Hari',
        ];
    @endphp

    @if($report['rows']->isNotEmpty())
        <div class="mt-6 space-y-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Per Tanggal</p>
                    <p class="text-lg font-bold text-gray-800 mt-1">{{ \Carbon\Carbon::parse($report['as_of'])->format('d M Y') }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Jumlah Customer</p>
                    <p class="text-lg font-bold text-gray-800 mt-1">{{ $report['customer_count'] }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Total Piutang</p>
                    <p class="text-lg font-bold text-red-700 mt-1">Rp {{ number_format($report['total_remaining'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Jumlah Invoice</p>
                    <p class="text-lg font-bold text-gray-800 mt-1">{{ $report['rows']->count() }}</p>
                </div>
            </div>

            <div class="grid grid-cols-5 gap-3">
                @foreach($bucketLabels as $key => $label)
                    <div class="{{ $key === 'above_90' ? 'bg-red-50 border-red-200' : 'bg-white border' }} rounded-xl shadow-sm p-3">
                        <p class="text-xs font-medium text-gray-500">{{ $label }}</p>
                        <p class="text-lg font-bold mt-1 {{ $key === 'above_90' ? 'text-red-700' : 'text-gray-800' }}">
                            Rp {{ number_format($report['buckets'][$key] ?? 0, 0, ',', '.') }}
                        </p>
                    </div>
                @endforeach
            </div>

            <div class="overflow-x-auto bg-white rounded-xl shadow-sm border dark:bg-gray-800">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Customer</th>
                            <th class="px-4 py-3 text-left font-semibold">No. Invoice</th>
                            <th class="px-4 py-3 text-center font-semibold">Jatuh Tempo</th>
                            <th class="px-4 py-3 text-right font-semibold">Total</th>
                            <th class="px-4 py-3 text-right font-semibold">Dibayar</th>
                            <th class="px-4 py-3 text-right font-semibold">Sisa</th>
                            <th class="px-4 py-3 text-center font-semibold">Tempo (hari)</th>
                            <th class="px-4 py-3 text-left font-semibold">Kategori</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($report['rows'] as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-3 font-medium">{{ $row->customer }}</td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $row->invoice_number }}</td>
                                <td class="px-4 py-3 text-center">{{ $row->due_date ? \Carbon\Carbon::parse($row->due_date)->format('d M Y') : '-' }}</td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format($row->total, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-green-600">Rp {{ number_format($row->paid, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-red-600">Rp {{ number_format($row->remaining, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-center">{{ $row->days_overdue }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-xs px-2 py-0.5 rounded-full
                                        {{ $row->bucket === 'above_90' ? 'bg-red-100 text-red-700' : ($row->bucket === 'current' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700') }}">
                                        {{ $bucketLabels[$row->bucket] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-6 text-center text-gray-500" colspan="8">
                                    Tidak ada piutang outstanding
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100 dark:bg-gray-700 font-semibold">
                        <tr>
                            <td class="px-4 py-3" colspan="5">TOTAL</td>
                            <td class="px-4 py-3 text-right text-red-600">Rp {{ number_format($report['total_remaining'], 0, ',', '.') }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @else
        <div class="mt-6 bg-white rounded-xl shadow-sm border p-6 text-center dark:bg-gray-800">
            <p class="text-sm text-gray-500">Tidak ada piutang outstanding.</p>
        </div>
    @endif
</x-filament-panels::page>
