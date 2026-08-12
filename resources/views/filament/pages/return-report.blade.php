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

            <x-report-table
                :title="'Retur Pembelian (ke Supplier)'"
                :rows="$report['purchase_returns']"
                :search="['return_number', 'supplier.name', 'status']"
                empty="Tidak ada retur pembelian pada periode ini"
                :columns="[
                    ['label' => 'No. Retur', 'key' => 'return_number', 'mono' => true],
                    ['label' => 'Tanggal', 'key' => 'date', 'type' => 'date', 'align' => 'text-center'],
                    ['label' => 'Supplier', 'key' => 'supplier.name'],
                    ['label' => 'Status', 'key' => 'status', 'align' => 'text-center'],
                    ['label' => 'Qty', 'key' => 'total_qty', 'type' => 'number', 'align' => 'text-right'],
                    ['label' => 'Total', 'key' => 'total_amount', 'type' => 'money', 'align' => 'text-right'],
                ]"
            />

            <x-report-table
                :title="'Pembatalan / Retur Order Marketplace'"
                :rows="$report['marketplace_returns']"
                :search="['platform', 'order_id']"
                empty="Tidak ada pembatalan order marketplace pada periode ini"
                :columns="[
                    ['label' => 'Platform', 'key' => 'platform'],
                    ['label' => 'Order ID', 'key' => 'order_id', 'mono' => true],
                    ['label' => 'Diproses', 'key' => 'processed_at', 'type' => 'datetime', 'align' => 'text-center'],
                    ['label' => 'Total', 'key' => 'total', 'type' => 'money', 'align' => 'text-right'],
                ]"
            />
        </div>
    @endif
</x-filament-panels::page>
