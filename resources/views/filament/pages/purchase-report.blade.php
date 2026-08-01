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
                    <p class="text-xs font-medium text-gray-500">Jumlah PO</p>
                    <p class="text-lg font-bold text-gray-800 mt-1">{{ $report['total_count'] }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Total Pembelian</p>
                    <p class="text-lg font-bold text-blue-700 mt-1">Rp {{ number_format($report['total_amount'], 0, ',', '.') }}</p>
                </div>
            </div>

            @if($report['summary']->isNotEmpty())
                <div class="overflow-x-auto bg-white rounded-xl shadow-sm border dark:bg-gray-800">
                    <div class="bg-gray-50 border-b border-gray-200 px-4 py-2 dark:bg-gray-700">
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Ringkasan per Supplier</p>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500 border-b border-gray-200">
                                <th class="px-4 py-2 font-medium">Supplier</th>
                                <th class="px-4 py-2 font-medium text-right">Jumlah PO</th>
                                <th class="px-4 py-2 font-medium text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($report['summary'] as $s)
                                <tr>
                                    <td class="px-4 py-2 font-medium">{{ $s->supplier }}</td>
                                    <td class="px-4 py-2 text-right">{{ $s->count }}</td>
                                    <td class="px-4 py-2 text-right font-semibold">Rp {{ number_format($s->total, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="overflow-x-auto bg-white rounded-xl shadow-sm border dark:bg-gray-800">
                <div class="bg-gray-50 border-b border-gray-200 px-4 py-2 dark:bg-gray-700">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Detail Purchase Order</p>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">No. PO</th>
                            <th class="px-4 py-3 text-center font-semibold">Tanggal</th>
                            <th class="px-4 py-3 text-left font-semibold">Supplier</th>
                            <th class="px-4 py-3 text-center font-semibold">Status</th>
                            <th class="px-4 py-3 text-right font-semibold">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($report['rows'] as $po)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-3 font-mono text-xs">{{ $po->po_number }}</td>
                                <td class="px-4 py-3 text-center">{{ $po->date ? \Carbon\Carbon::parse($po->date)->format('d M Y') : '-' }}</td>
                                <td class="px-4 py-3">{{ $po->supplier?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ $po->status }}</span>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold">Rp {{ number_format($po->total_amount, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-6 text-center text-gray-500" colspan="5">
                                    Tidak ada pembelian pada periode ini
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100 dark:bg-gray-700 font-semibold">
                        <tr>
                            <td class="px-4 py-3" colspan="4">TOTAL</td>
                            <td class="px-4 py-3 text-right text-blue-700">Rp {{ number_format($report['total_amount'], 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
