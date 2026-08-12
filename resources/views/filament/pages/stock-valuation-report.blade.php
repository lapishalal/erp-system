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

            <x-report-table
                :title="'Detail Nilai Persediaan'"
                :rows="$report['rows']"
                :totals="(object) ['total_qty' => $report['total_qty'], 'total_value' => $report['total_value']]"
                :search="['code', 'name', 'warehouse']"
                empty="Tidak ada stok barang"
                :footer="[
                    ['text' => 'TOTAL', 'colspan' => 3, 'align' => 'text-left'],
                    ['value_key' => 'total_qty', 'type' => 'number', 'colspan' => 1, 'align' => 'text-right'],
                    ['text' => '', 'colspan' => 1, 'align' => 'text-right'],
                    ['value_key' => 'total_value', 'colspan' => 1, 'align' => 'text-right'],
                ]"
                :columns="[
                    ['label' => 'Kode', 'key' => 'code', 'mono' => true],
                    ['label' => 'Nama Barang', 'key' => 'name'],
                    ['label' => 'Gudang', 'key' => 'warehouse', 'align' => 'text-center'],
                    ['label' => 'Qty', 'key' => 'qty', 'type' => 'number', 'align' => 'text-right'],
                    ['label' => 'Harga Pokok', 'key' => 'cost', 'type' => 'money', 'align' => 'text-right'],
                    ['label' => 'Nilai', 'key' => 'value', 'type' => 'money', 'color' => 'text-blue-700 font-semibold', 'align' => 'text-right'],
                ]"
            />
        </div>
    @else
        <div class="mt-6 bg-white rounded-xl shadow-sm border p-6 text-center dark:bg-gray-800">
            <p class="text-sm text-gray-500">Tidak ada stok barang.</p>
        </div>
    @endif
</x-filament-panels::page>
