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
        $periodeText = ($report['dari'] || $report['sampai'])
            ? ($report['dari'] ? \Carbon\Carbon::parse($report['dari'])->format('d M Y') : 'Awal')
              . ' s/d ' .
              ($report['sampai'] ? \Carbon\Carbon::parse($report['sampai'])->format('d M Y') : 'Sekarang')
            : 'Semua Tanggal';
    @endphp

    @if($report['rows']->isNotEmpty())
        <div class="mt-6 space-y-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Periode Order</p>
                    <p class="text-lg font-bold text-gray-800 mt-1">{{ $periodeText }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Jumlah Customer</p>
                    <p class="text-lg font-bold text-gray-800 mt-1">{{ $report['customer_count'] }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium text-gray-500">Total Piutang</p>
                    <p class="text-lg font-bold text-red-700 mt-1">Rp {{ number_format($report['total_remaining'], 0, ',', '.') }}</p>
                </div>
                <div class="{{ $report['needs_check_count'] > 0 ? 'bg-red-50 border-red-300 dark:bg-red-900/20 dark:border-red-700' : 'bg-white' }} rounded-xl shadow-sm border p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium {{ $report['needs_check_count'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500' }}">⚠️ Perlu Kroscek (&gt; 10 Hari)</p>
                    <p class="text-lg font-bold {{ $report['needs_check_count'] > 0 ? 'text-red-700' : 'text-gray-800' }} mt-1">
                        {{ $report['needs_check_count'] }} invoice · Rp {{ number_format($report['needs_check_total'], 0, ',', '.') }}
                    </p>
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

            @if($report['needs_check_count'] > 0)
                <x-report-table
                    :title="'⚠️ Perlu Kroscek — Invoice lewat dari 10 hari (kategori/lainnya mohon diverifikasi)'"
                    :rows="$report['needs_check']"
                    :search="['customer', 'invoice_number', 'so_number']"
                    empty="Tidak ada invoice yang perlu kroscek"
                    :columns="[
                        ['label' => 'Customer', 'key' => 'customer'],
                        ['label' => 'No. Invoice', 'key' => 'invoice_number', 'type' => 'link', 'url_key' => 'invoice_url', 'mono' => true],
                        ['label' => 'No. SO', 'key' => 'so_number', 'mono' => true],
                        ['label' => 'Tanggal Order', 'key' => 'order_date', 'type' => 'date', 'align' => 'text-center'],
                        ['label' => 'Total', 'key' => 'total', 'type' => 'money', 'align' => 'text-right'],
                        ['label' => 'Dibayar', 'key' => 'paid', 'type' => 'money', 'align' => 'text-right'],
                        ['label' => 'Sisa', 'key' => 'remaining', 'type' => 'money', 'color' => 'text-red-600 font-semibold', 'align' => 'text-right'],
                        ['label' => 'Lewat (hari)', 'key' => 'days_overdue', 'type' => 'number', 'align' => 'text-center'],
                    ]"
                />
            @endif

            <x-report-table
                :title="'Detail Piutang Outstanding'"
                :rows="$report['rows']"
                :totals="(object) ['total_remaining' => $report['total_remaining']]"
                :search="['customer', 'invoice_number', 'so_number']"
                empty="Tidak ada piutang outstanding"
                :footer="[
                    ['text' => 'TOTAL', 'colspan' => 5, 'align' => 'text-left'],
                    ['value_key' => 'total_remaining', 'colspan' => 1, 'align' => 'text-right'],
                    ['text' => '', 'colspan' => 3],
                ]"
                :columns="[
                    ['label' => 'Customer', 'key' => 'customer'],
                    ['label' => 'No. Invoice', 'key' => 'invoice_number', 'type' => 'link', 'url_key' => 'invoice_url', 'mono' => true],
                    ['label' => 'No. SO', 'key' => 'so_number', 'mono' => true],
                    ['label' => 'Tanggal Order', 'key' => 'order_date', 'type' => 'date', 'align' => 'text-center'],
                    ['label' => 'Total', 'key' => 'total', 'type' => 'money', 'align' => 'text-right'],
                    ['label' => 'Dibayar', 'key' => 'paid', 'type' => 'money', 'align' => 'text-right'],
                    ['label' => 'Sisa', 'key' => 'remaining', 'type' => 'money', 'color' => 'text-red-600 font-semibold', 'align' => 'text-right'],
                    ['label' => 'Lewat (hari)', 'key' => 'days_overdue', 'type' => 'number', 'align' => 'text-center'],
                    ['label' => 'Kategori', 'key' => 'bucket', 'type' => 'badge', 'map' => $bucketLabels, 'align' => 'text-center', 'colors' => ['current' => 'bg-green-100 text-green-700 ring-green-600/20', '1_30' => 'bg-amber-100 text-amber-700 ring-amber-600/20', '31_60' => 'bg-amber-100 text-amber-700 ring-amber-600/20', '61_90' => 'bg-orange-100 text-orange-700 ring-orange-600/20', 'above_90' => 'bg-red-100 text-red-700 ring-red-600/20']],
                ]"
            />
        </div>
    @else
        <div class="mt-6 bg-white rounded-xl shadow-sm border p-6 text-center dark:bg-gray-800">
            <p class="text-sm text-gray-500">Tidak ada piutang outstanding.</p>
        </div>
    @endif
</x-filament-panels::page>
