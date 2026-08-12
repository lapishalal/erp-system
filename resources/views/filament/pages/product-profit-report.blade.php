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

            <x-report-table
                :title="'Detail Laba per Produk'"
                :rows="$report['rows']"
                :totals="(object) $report['totals']"
                :search="['code', 'name', 'brand']"
                empty="Tidak ada data penjualan pada periode ini"
                :footer="[
                    ['text' => 'TOTAL', 'colspan' => 3, 'align' => 'text-left'],
                    ['value_key' => 'qty', 'type' => 'number', 'colspan' => 1, 'align' => 'text-right'],
                    ['value_key' => 'revenue', 'colspan' => 1, 'align' => 'text-right'],
                    ['value_key' => 'cost', 'colspan' => 1, 'align' => 'text-right'],
                    ['value_key' => 'profit', 'colspan' => 1, 'align' => 'text-right'],
                    ['text' => '', 'colspan' => 1],
                ]"
                :columns="[
                    ['label' => 'Kode', 'key' => 'code', 'mono' => true],
                    ['label' => 'Nama Produk', 'key' => 'name'],
                    ['label' => 'Brand', 'key' => 'brand'],
                    ['label' => 'Qty', 'key' => 'qty', 'type' => 'number', 'align' => 'text-right'],
                    ['label' => 'Penjualan', 'key' => 'revenue', 'type' => 'money', 'align' => 'text-right'],
                    ['label' => 'HPP', 'key' => 'cost', 'type' => 'money', 'align' => 'text-right'],
                    ['label' => 'Laba', 'key' => 'profit', 'type' => 'money', 'align' => 'text-right'],
                    ['label' => 'Margin', 'key' => 'margin', 'type' => 'pct', 'align' => 'text-right'],
                ]"
            />
        </div>
    @endif
</x-filament-panels::page>
