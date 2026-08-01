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
        <div class="mt-6 space-y-6">
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Total Retur Pembelian</p>
                    <p class="text-lg font-bold text-red-700 mt-1">Rp {{ number_format($report['purchase_total'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Total Retur Marketplace</p>
                    <p class="text-lg font-bold text-red-700 mt-1">Rp {{ number_format($report['marketplace_total'], 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="overflow-x-auto bg-white rounded-xl shadow-sm border dark:bg-gray-800">
                <div class="bg-gray-50 border-b border-gray-200 px-4 py-2 dark:bg-gray-700">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Retur Pembelian (ke Supplier)</p>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">No. Retur</th>
                            <th class="px-4 py-3 text-center font-semibold">Tanggal</th>
                            <th class="px-4 py-3 text-left font-semibold">Supplier</th>
                            <th class="px-4 py-3 text-center font-semibold">Status</th>
                            <th class="px-4 py-3 text-right font-semibold">Qty</th>
                            <th class="px-4 py-3 text-right font-semibold">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($report['purchase_returns'] as $r)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-3 font-mono text-xs">{{ $r->return_number }}</td>
                                <td class="px-4 py-3 text-center">{{ $r->date ? \Carbon\Carbon::parse($r->date)->format('d M Y') : '-' }}</td>
                                <td class="px-4 py-3">{{ $r->supplier?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ $r->status }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">{{ $r->total_qty }}</td>
                                <td class="px-4 py-3 text-right font-semibold">Rp {{ number_format($r->total_amount, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-6 text-center text-gray-500" colspan="6">
                                    Tidak ada retur pembelian pada periode ini
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="overflow-x-auto bg-white rounded-xl shadow-sm border dark:bg-gray-800">
                <div class="bg-gray-50 border-b border-gray-200 px-4 py-2 dark:bg-gray-700">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Pembatalan / Retur Order Marketplace</p>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Platform</th>
                            <th class="px-4 py-3 text-left font-semibold">Order ID</th>
                            <th class="px-4 py-3 text-center font-semibold">Diproses</th>
                            <th class="px-4 py-3 text-right font-semibold">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($report['marketplace_returns'] as $r)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-3">{{ strtoupper($r->platform?->value ?? '') }}</td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $r->platform_order_id }}</td>
                                <td class="px-4 py-3 text-center">{{ $r->processed_at ? $r->processed_at->format('d M Y H:i') : '-' }}</td>
                                <td class="px-4 py-3 text-right font-semibold">Rp {{ number_format($r->items->sum('subtotal_after_discount'), 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-6 text-center text-gray-500" colspan="4">
                                    Tidak ada pembatalan order marketplace pada periode ini
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
