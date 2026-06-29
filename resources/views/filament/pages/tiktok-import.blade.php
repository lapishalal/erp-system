<x-filament-panels::page>
    @php
        $unmappedCount = \App\Models\MarketplaceOrderItem::unmapped()->count();
        $readyCount = \App\Models\MarketplaceOrder::where('is_mapped', true)->whereNull('sales_order_id')->where('platform', 'tiktok')->count();
    @endphp

    @if($unmappedCount > 0 || $readyCount > 0)
    <div class="mb-4 space-y-2">
        @if($unmappedCount > 0)
        <a href="{{ \App\Filament\Pages\TikTokUnmappedProductPage::getUrl() }}" class="block">
            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 rounded-lg p-3 hover:bg-amber-100 dark:hover:bg-amber-900/40 transition cursor-pointer">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        <span class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                            {{ $unmappedCount }} produk belum ter-map
                        </span>
                    </div>
                    <span class="text-xs text-amber-600 dark:text-amber-400 font-medium">Klik untuk mapping →</span>
                </div>
            </div>
        </a>
        @endif

        @if($readyCount > 0)
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-300 dark:border-green-700 rounded-lg p-3">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="text-sm font-semibold text-green-800 dark:text-green-300">
                    {{ $readyCount }} order siap diproses
                </span>
                <a href="{{ \App\Filament\Pages\TikTokUnmappedProductPage::getUrl() }}" class="text-xs text-green-600 dark:text-green-400 font-medium hover:underline ml-auto">
                    Proses sekarang →
                </a>
            </div>
        </div>
        @endif
    </div>
    @endif

    {{ $this->form }}
</x-filament-panels::page>