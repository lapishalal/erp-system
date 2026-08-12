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
        <x-report-table
            :title="'Kas Masuk'"
            :rows="$this->getCashInQuery()"
            :search="['date', 'customer.name', 'description']"
            empty="Tidak ada data kas masuk"
            :columns="[
                ['label' => 'Tanggal', 'key' => 'date', 'type' => 'date'],
                ['label' => 'Jenis', 'key' => 'type', 'type' => 'text', 'map' => ['CUSTOMER_PAYMENT' => 'Penerimaan Piutang'], 'default' => 'Lain-lain'],
                ['label' => 'Customer', 'key' => 'customer.name'],
                ['label' => 'Jumlah', 'key' => 'amount', 'type' => 'money', 'align' => 'text-right'],
                ['label' => 'Keterangan', 'key' => 'description'],
            ]"
        />

        {{-- Kas Keluar --}}
        <x-report-table
            :title="'Pengeluaran / Kas Keluar'"
            :rows="$this->getCashOutQuery()"
            :search="['date', 'category.name', 'description']"
            empty="Tidak ada data pengeluaran"
            :columns="[
                ['label' => 'Tanggal', 'key' => 'date', 'type' => 'date'],
                ['label' => 'Jenis', 'key' => 'type', 'type' => 'text', 'map' => ['OPERATIONAL' => 'Operasional', 'SALARY' => 'Gaji', 'TRANSPORT' => 'Transport', 'MARKETING' => 'Marketing', 'UTILITIES' => 'Listrik & Air', 'RENT' => 'Sewa', 'TAX' => 'Pajak'], 'default' => 'Lain-lain'],
                ['label' => 'Kategori', 'key' => 'category.name'],
                ['label' => 'Jumlah', 'key' => 'amount', 'type' => 'money', 'align' => 'text-right'],
                ['label' => 'Keterangan', 'key' => 'description'],
            ]"
        />
    </div>
</x-filament-panels::page>
