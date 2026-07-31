<x-filament-panels::page>
    <div class="mb-4">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Siap Diproses</p>
                        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                            {{ \App\Models\MarketplaceOrder::where('platform', 'tiktok')->where('is_mapped', true)->whereNull('processed_at')->where('status', '!=', 'CANCEL')->where('is_hidden', false)->count() }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900 flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Perlu Mapping</p>
                        <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">
                            {{ \App\Models\MarketplaceOrder::where('platform', 'tiktok')->where('is_hidden', false)->where('status', '!=', 'CANCEL')->where('is_mapped', false)->count() }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Sudah Diproses</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                            {{ \App\Models\MarketplaceOrder::where('platform', 'tiktok')->whereNotNull('processed_at')->where('status', '!=', 'CANCEL')->where('is_hidden', false)->count() }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Dibatalkan</p>
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400">
                            {{ \App\Models\MarketplaceOrder::where('platform', 'tiktok')->where('status', 'CANCEL')->where('is_hidden', false)->count() }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-300">Panduan Alur Orderan TikTok</h3>
                    <div class="mt-1 text-sm text-blue-700 dark:text-blue-400 space-y-1">
                        <p><strong>1.</strong> Import file pesanan di menu <strong>"Import TikTok"</strong> — semua order masuk daftar ini, <strong>tidak ada yang otomatis diproses</strong>.</p>
                        <p><strong>2.</strong> Order berstatus <strong>Dibatalkan</strong> langsung ditandai <em>Skipped</em> (tidak diproses).</p>
                        <p><strong>3.</strong> Pastikan semua item sudah ter-map (kolom <strong>Ter-map</strong>). Jika belum, klik tombol <strong>"Produk Belum Ter-map"</strong>.</p>
                        <p><strong>4.</strong> Klik <strong>"Proses"</strong> per order — SO/DO/Invoice dibuat sesuai status (Selesai/Dikirim → stok terpotong).</p>
                        <p><strong>5.</strong> Import file <strong>Income</strong> untuk mencatat settlement → Invoice LUNAS.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
