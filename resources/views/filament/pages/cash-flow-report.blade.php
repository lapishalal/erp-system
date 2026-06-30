<x-filament-panels::page>
    {{ $this->form }}

    <!-- Card Summary Kas & Bank (Accounting Standard: Harus ada Saldo Awal, Cash In, Cash Out, Net, dan Saldo Akhir) -->
    <div class="grid grid-cols-5 gap-4 mt-6">
        <!-- 1. Saldo Awal -->
        <div class="rounded-xl bg-gray-50 dark:bg-gray-800 p-4 border border-gray-200 dark:border-gray-700">
            <div class="text-sm text-gray-500 dark:text-gray-400 font-medium">Saldo Awal Kas & Bank</div>
            <div class="text-lg font-bold text-gray-700 dark:text-gray-300">
                Rp {{ number_format($this->getSaldoAwal(), 0, ',', '.') }}
            </div>
        </div>
        <!-- 2. Kas Masuk -->
        <div class="rounded-xl bg-green-50 dark:bg-green-950/20 p-4 border border-green-200 dark:border-green-800">
            <div class="text-sm text-green-600 dark:text-green-400 font-medium">Total Kas Masuk</div>
            <div class="text-lg font-bold text-green-700 dark:text-green-300">
                Rp {{ number_format($this->getTotalCashIn(), 0, ',', '.') }}
            </div>
        </div>
        <!-- 3. Kas Keluar -->
        <div class="rounded-xl bg-red-50 dark:bg-red-950/20 p-4 border border-red-200 dark:border-red-800">
            <div class="text-sm text-red-600 dark:text-red-400 font-medium">Total Kas Keluar</div>
            <div class="text-lg font-bold text-red-700 dark:text-red-300">
                Rp {{ number_format($this->getTotalCashOut(), 0, ',', '.') }}
            </div>
        </div>
        <!-- 4. Net Cash Flow -->
        <div class="rounded-xl bg-blue-50 dark:bg-blue-950/20 p-4 border border-blue-200 dark:border-blue-800">
            <div class="text-sm text-blue-600 dark:text-blue-400 font-medium">Net Cash Flow</div>
            <div class="text-lg font-bold {{ $this->getNetCashFlow() >= 0 ? 'text-blue-700' : 'text-red-700' }}">
                Rp {{ number_format($this->getNetCashFlow(), 0, ',', '.') }}
            </div>
        </div>
        <!-- 5. Saldo Akhir -->
        <div class="rounded-xl bg-indigo-50 dark:bg-indigo-950/20 p-4 border border-indigo-200 dark:border-indigo-800">
            <div class="text-sm text-indigo-600 dark:text-indigo-400 font-medium">Saldo Akhir Kas & Bank</div>
            <div class="text-lg font-bold text-indigo-700 dark:text-indigo-300">
                Rp {{ number_format($this->getSaldoAkhir(), 0, ',', '.') }}
            </div>
        </div>
    </div>

    <!-- Export Button -->
    <div class="flex justify-end mt-4">
        <!-- =====================================================================
        PERBAIKAN PROGRAMATIK (PRIORITAS TINGGI):
        Mengubah route export yang awalnya salah arah 'profit-loss.export' 
        menjadi 'cash-flow.export' yang mengunduh data Arus Kas yang valid.
        ===================================================================== -->
        <a href="{{ route('cash-flow.export', ['year' => $this->data['year'] ?? now()->year, 'month' => $this->data['month'] ?? now()->month]) }}" 
           target="_blank"
           class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            Export Excel Arus Kas
        </a>
    </div>

    <div class="mt-8 space-y-8">
        {{-- Kas Masuk --}}
        <div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                Kas Masuk
            </h2>
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Tanggal</th>
                            <th class="px-4 py-3 font-semibold">Jenis</th>
                            <th class="px-4 py-3 font-semibold">Customer</th>
                            <th class="px-4 py-3 font-semibold text-right">Jumlah</th>
                            <th class="px-4 py-3 font-semibold">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($this->getCashInQuery() as $item)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3">{{ $item->date->format('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-md bg-green-50 dark:bg-green-950/40 px-2 py-1 text-xs font-medium text-green-700 dark:text-green-400 ring-1 ring-inset ring-green-600/20">
                                    {{ $item->type === 'CUSTOMER_PAYMENT' ? 'Penerimaan Piutang' : 'Lain-lain' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ $item->customer?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-right font-medium text-green-700 dark:text-green-400">
                                Rp {{ number_format($item->amount, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $item->description ?? '-' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Tidak ada data kas masuk</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Kas Keluar --}}
        <div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                Pengeluaran / Kas Keluar
            </h2>
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Tanggal</th>
                            <th class="px-4 py-3 font-semibold">Jenis</th>
                            <th class="px-4 py-3 font-semibold">Kategori</th>
                            <th class="px-4 py-3 font-semibold text-right">Jumlah</th>
                            <th class="px-4 py-3 font-semibold">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($this->getCashOutQuery() as $item)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3">{{ $item->date->format('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-md bg-red-50 dark:bg-red-950/40 px-2 py-1 text-xs font-medium text-red-700 dark:text-red-400 ring-1 ring-inset ring-red-600/20">
                                    {{ match($item->type) {
                                        'OPERATIONAL' => 'Operasional',
                                        'SALARY' => 'Gaji',
                                        'TRANSPORT' => 'Transport',
                                        'MARKETING' => 'Marketing',
                                        'UTILITIES' => 'Listrik & Air',
                                        'RENT' => 'Sewa',
                                        'TAX' => 'Pajak',
                                        default => 'Lain-lain',
                                    } }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ $item->category?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-right font-medium text-red-700 dark:text-red-400">
                                Rp {{ number_format($item->amount, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $item->description ?? '-' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Tidak ada data pengeluaran</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
