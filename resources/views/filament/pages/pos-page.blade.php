<x-filament-panels::page>
<style>
    /* Override Filament container untuk full width */
    .fi-page > div:first-child { max-width: 100% !important; }
    .fi-page-content-ctn { max-width: 100% !important; padding: 0 !important; }
    .fi-main { max-width: 100% !important; }
    .fi-main-ctn { max-width: 100% !important; }
    .max-w-7xl, .max-w-8xl, .max-w-6xl { max-width: 100% !important; }

    /* Hapus padding bawaan page */
    .fi-page { padding: 0 !important; margin: 0 !important; }
    .fi-page-content { padding: 0 !important; }

    /* Scrollbar custom */
    .pos-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
    .pos-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
    .pos-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .pos-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

    /* Animasi */
    @keyframes popIn { 0% { transform: scale(0.9); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
    .animate-pop { animation: popIn 0.2s ease-out; }

    /* Product card */
    .product-card { transition: all 0.15s ease; }
    .product-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px -5px rgba(0,0,0,0.12); }
    .product-card:active { transform: scale(0.97); }

    /* Category chip */
    .cat-chip { transition: all 0.2s ease; }
    .cat-chip.active { background: #2563eb; color: white; border-color: #2563eb; }

    /* Quick pay */
    .quick-pay { transition: all 0.15s ease; }
    .quick-pay:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .quick-pay:active { transform: translateY(0); }
</style>

<div x-data="posApp()" x-init="init()" class="w-full bg-gray-50 dark:bg-gray-900" style="min-height: calc(100vh - 64px);">

    {{-- Header & Search --}}
    <div class="w-full bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-4 py-3 lg:px-6 lg:py-4">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
            {{-- Search --}}
            <div class="flex-1 max-w-2xl">
                <div class="flex items-center bg-gray-100 dark:bg-gray-700 rounded-xl border border-gray-300 dark:border-gray-600 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/30 transition-all overflow-hidden">
                    <div class="pl-4 pr-2 flex items-center justify-center">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input 
                        type="text" 
                        x-ref="searchInput"
                        x-model="search"
                        @input.debounce.300ms="fetchProducts()"
                        placeholder="Cari barang (nama/kode) atau scan barcode..."
                        class="flex-1 py-3 pr-4 bg-transparent border-0 focus:ring-0 focus:outline-none text-gray-900 dark:text-white placeholder-gray-400 text-base"
                        autofocus
                    >
                    <div x-show="isSearching" class="pr-4">
                        <svg class="animate-spin h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Stats --}}
            <div class="flex items-center gap-3 text-sm text-gray-600 dark:text-gray-400 flex-shrink-0">
                <span class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-green-500"></span>
                    <span x-text="products.length + ' produk'"></span>
                </span>
            </div>
        </div>

        {{-- Category Chips --}}
        <div class="flex gap-2 mt-3 overflow-x-auto pos-scroll pb-1">
            <button @click="filterCategory('all')" :class="activeCategory === 'all' ? 'active' : ''" class="cat-chip px-4 py-1.5 rounded-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-sm font-medium whitespace-nowrap hover:bg-gray-50 dark:hover:bg-gray-600">
                Semua
            </button>
            @foreach(\App\Models\Category::where('is_active', true)->orderBy('name')->get() as $cat)
            <button @click="filterCategory({{ $cat->id }})" :class="activeCategory == {{ $cat->id }} ? 'active' : ''" class="cat-chip px-4 py-1.5 rounded-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-sm font-medium whitespace-nowrap hover:bg-gray-50 dark:hover:bg-gray-600">
                {{ $cat->name }}
            </button>
            @endforeach
        </div>
    </div>

    {{-- Main Content --}}
    <div class="flex flex-col lg:flex-row w-full" >

        {{-- LEFT: Products --}}
        <div class="lg:w-2/3 flex flex-col p-4 overflow-hidden">
            <div class="flex-1 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-y-auto pos-scroll p-4">

                {{-- Product Grid --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-3">
                    <template x-for="(product, idx) in products" :key="product.id">
                        <button
                            @click="addToCart(product)"
                            class="product-card relative bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3 text-left border border-gray-200 dark:border-gray-600 animate-pop"
                            :style="`animation-delay: ${idx * 0.03}s`"
                            :class="product.stock <= 0 ? 'opacity-50 cursor-not-allowed' : ''"
                            :disabled="product.stock <= 0"
                        >
                            <div class="absolute top-2 right-2 z-10">
                                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold" :class="product.stock <= 5 ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'" x-text="product.stock"></span>
                            </div>

                            <div class="aspect-[4/3] rounded-lg bg-white dark:bg-gray-600 mb-2 flex items-center justify-center overflow-hidden">
                                <img x-show="product.image" :src="product.image" class="w-full h-full object-cover" x-on:error="$el.style.display='none'">
                                <div x-show="!product.image" class="text-3xl">📦</div>
                            </div>

                            <div class="space-y-0.5">
                                <div class="font-semibold text-sm text-gray-900 dark:text-white leading-tight line-clamp-2" x-text="product.name"></div>
                                <div class="text-[11px] text-gray-500 dark:text-gray-400" x-text="product.code"></div>
                                <div class="flex items-center justify-between pt-1">
                                    <div class="font-bold text-blue-600 dark:text-blue-400 text-sm" x-text="'Rp ' + formatNumber(product.price)"></div>
                                    <div class="w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold shadow-md">+</div>
                                </div>
                            </div>
                        </button>
                    </template>
                </div>

                {{-- Empty State --}}
                <div x-show="products.length === 0 && !isSearching" class="flex flex-col items-center justify-center h-full text-gray-400 py-20">
                    <div class="text-5xl mb-3">📦</div>
                    <p class="text-base font-medium">Ketik untuk mencari barang...</p>
                    <p class="text-sm">atau pilih kategori di atas</p>
                </div>

                <div x-show="products.length === 0 && isSearching" class="flex flex-col items-center justify-center h-full text-gray-400 py-20">
                    <div class="text-5xl mb-3">🔍</div>
                    <p class="text-base font-medium">Produk tidak ditemukan</p>
                </div>
            </div>
        </div>

        {{-- RIGHT: Cart --}}
        <div class="lg:w-1/3 flex flex-col p-4 pt-0 lg:pt-4 gap-3">

            {{-- Cart Items --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 flex flex-col" style="min-height: 350px;">
                <div class="flex items-center justify-between p-3 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        Keranjang (<span x-text="cart.length"></span>)
                    </h3>
                    <button x-show="cart.length > 0" @click="clearCart()" class="text-xs text-red-500 hover:text-red-700 font-medium">Kosongkan</button>
                </div>

                <div class="overflow-y-auto pos-scroll p-3 space-y-2" style="max-height: 400px;">
                    <template x-for="(item, index) in cart" :key="index">
                        <div class="flex items-center gap-2 p-2 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-100 dark:border-gray-600">
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-sm text-gray-900 dark:text-white truncate" x-text="item.name"></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400" x-text="'@ Rp ' + formatNumber(item.price)"></div>
                            </div>
                            <div class="flex items-center gap-1">
                                <button @click="decreaseQty(index)" class="w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 flex items-center justify-center text-xs font-bold">-</button>
                                <span class="w-6 text-center text-sm font-bold" x-text="item.qty"></span>
                                <button @click="increaseQty(index)" class="w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900 hover:bg-blue-200 flex items-center justify-center text-xs font-bold text-blue-700">+</button>
                            </div>
                            <div class="text-right min-w-[70px]">
                                <div class="font-bold text-sm text-gray-900 dark:text-white" x-text="'Rp ' + formatNumber(item.price * item.qty)"></div>
                            </div>
                            <button @click="removeFromCart(index)" class="w-6 h-6 rounded-full hover:bg-red-100 flex items-center justify-center text-red-400">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </template>

                    <div x-show="cart.length === 0" class="flex flex-col items-center justify-center h-32 text-gray-400">
                        <svg class="w-12 h-12 mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <p class="text-sm">Keranjang kosong</p>
                    </div>
                </div>
            </div>

            {{-- Checkout Summary --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3">

                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Subtotal</span>
                    <span class="font-bold text-gray-900 dark:text-white" x-text="'Rp ' + formatNumber(subtotal)"></span>
                </div>

                <div>
                    <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">Diskon (Rp)</label>
                    <input type="number" x-model.number="discount" @input="calculateChange()" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 dark:bg-gray-700 dark:text-white text-right">
                </div>

                <div class="flex justify-between items-center pt-2 border-t border-gray-200 dark:border-gray-700">
                    <span class="font-bold text-gray-900 dark:text-white">Total</span>
                    <span class="text-xl font-black text-blue-600 dark:text-blue-400" x-text="'Rp ' + formatNumber(total)"></span>
                </div>

                <div>
                    <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">Bayar</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">Rp</span>
                        <input type="number" x-model.number="paidAmount" @input="calculateChange()" class="w-full pl-8 pr-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-right font-bold" placeholder="0">
                    </div>
                </div>

                <div class="grid grid-cols-4 gap-2">
                    <button @click="setExactPay()" class="quick-pay py-1.5 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 text-xs font-bold border border-blue-200 dark:border-blue-800">UANG PAS</button>
                    <button @click="addPay(50000)" class="quick-pay py-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-bold border border-gray-200 dark:border-gray-600">+50K</button>
                    <button @click="addPay(100000)" class="quick-pay py-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-bold border border-gray-200 dark:border-gray-600">+100K</button>
                    <button @click="addPay(1000000)" class="quick-pay py-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-bold border border-gray-200 dark:border-gray-600">+1JT</button>
                </div>

                <div class="flex justify-between items-center p-2 rounded-lg" :class="change >= 0 ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20'">
                    <span class="text-sm font-medium" :class="change >= 0 ? 'text-green-700' : 'text-red-700'">Kembalian</span>
                    <span class="font-bold" :class="change >= 0 ? 'text-green-600' : 'text-red-600'" x-text="'Rp ' + formatNumber(change)"></span>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <select x-model="paymentMethod" class="rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 dark:bg-gray-700 dark:text-white text-sm">
                        <option value="CASH">Tunai</option>
                        <option value="DEBIT">Debit</option>
                        <option value="QRIS">QRIS</option>
                        <option value="TRANSFER">Transfer</option>
                    </select>
                    <select x-model="customerId" class="rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 dark:bg-gray-700 dark:text-white text-sm">
                        <option value="">Walk-in</option>
                        @foreach(\App\Models\Customer::where('is_active', true)->get() as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>

                <button
                    type="button"
                    @click="checkout()"
                    :disabled="cart.length === 0 || total <= 0 || isProcessing"
                    class="w-full py-3 rounded-lg font-bold text-white text-base transition-all"
                    style="background-color: #2563eb;"
                    :style="cart.length === 0 || total <= 0 || isProcessing ? 'background-color: #9ca3af;' : 'background-color: #2563eb;'"
                >
                    <span x-show="isProcessing" class="animate-spin inline-block">&#9203;</span>
                    <span x-text="isProcessing ? 'Memproses...' : 'BAYAR & PRINT STRUK'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- Receipt Modal --}}
    <div x-show="showReceipt" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" style="display: none;">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 max-w-sm w-full mx-4 shadow-2xl">
            <div class="text-center mb-4">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">Struk Pembayaran</h3>
                <p class="text-gray-500 dark:text-gray-400 text-sm" x-text="receiptData ? receiptData.transaction_number : ''"></p>
            </div>
            <div class="space-y-2 text-sm mb-4">
                <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Total</span> <span class="font-bold text-gray-900 dark:text-white" x-text="'Rp ' + formatNumber(receiptData ? receiptData.total : 0)"></span></div>
                <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Bayar</span> <span x-text="'Rp ' + formatNumber(receiptData ? receiptData.paid_amount : 0)"></span></div>
                <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-700"><span class="text-gray-600 dark:text-gray-400">Kembalian</span> <span class="text-green-600 font-bold" x-text="'Rp ' + formatNumber(receiptData ? receiptData.change : 0)"></span></div>
            </div>
            <div class="flex gap-2">
                <button @click="printReceipt()" class="flex-1 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium">Print</button>
                <button @click="showReceipt = false; resetCart()" class="flex-1 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-white rounded-lg font-medium">Transaksi Baru</button>
            </div>
        </div>
    </div>
</div>

<script>
function posApp() {
    return {
        search: '',
        products: [],
        cart: [],
        discount: 0,
        paidAmount: 0,
        change: 0,
        paymentMethod: 'CASH',
        customerId: '',
        isProcessing: false,
        showReceipt: false,
        receiptData: null,
        isSearching: false,
        activeCategory: 'all',

        init() {
            this.fetchProducts();
        },

        async fetchProducts() {
            this.isSearching = true;
            try {
                let url = '/pos/api/products?search=' + encodeURIComponent(this.search);
                if (this.activeCategory !== 'all') {
                    url += '&category=' + this.activeCategory;
                }
                const res = await fetch(url);
                const data = await res.json();
                this.products = data;
            } catch (e) {
                console.error(e);
                this.products = [];
            } finally {
                this.isSearching = false;
            }
        },

        filterCategory(catId) {
            this.activeCategory = catId;
            this.fetchProducts();
        },

        addToCart(product) {
            if (product.stock <= 0) {
                alert('Stok habis!');
                return;
            }
            const existing = this.cart.find(item => item.id === product.id);
            if (existing) {
                if (existing.qty < product.stock) {
                    existing.qty++;
                } else {
                    alert('Stok tidak mencukupi!');
                }
            } else {
                this.cart.push({
                    id: product.id,
                    code: product.code,
                    name: product.name,
                    price: parseFloat(product.price),
                    qty: 1,
                    stock: product.stock
                });
            }
            this.calculateChange();
        },

        increaseQty(index) {
            const item = this.cart[index];
            if (item.qty < item.stock) {
                item.qty++;
                this.calculateChange();
            }
        },

        decreaseQty(index) {
            const item = this.cart[index];
            if (item.qty > 1) {
                item.qty--;
            } else {
                this.cart.splice(index, 1);
            }
            this.calculateChange();
        },

        removeFromCart(index) {
            this.cart.splice(index, 1);
            this.calculateChange();
        },

        clearCart() {
            if (confirm('Kosongkan keranjang?')) {
                this.cart = [];
                this.calculateChange();
            }
        },

        get subtotal() {
            return this.cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        },

        get total() {
            return Math.max(0, this.subtotal - this.discount);
        },

        calculateChange() {
            this.change = this.paidAmount - this.total;
        },

        setExactPay() {
            this.paidAmount = this.total;
            this.calculateChange();
        },

        addPay(amount) {
            this.paidAmount = (parseFloat(this.paidAmount) || 0) + amount;
            this.calculateChange();
        },

        async checkout() {
            if (this.cart.length === 0) return;
            if (this.paidAmount < this.total) {
                alert('Uang pembayaran kurang!');
                return;
            }

            this.isProcessing = true;
            try {
                const res = await fetch('/pos/api/checkout', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        items: this.cart.map(item => ({
                            product_id: item.id,
                            qty: item.qty,
                            price: item.price
                        })),
                        discount: this.discount,
                        tax: 0,
                        paid_amount: this.paidAmount,
                        payment_method: this.paymentMethod,
                        customer_id: this.customerId || null
                    })
                });

                const data = await res.json();

                if (data.success) {
                    this.receiptData = {
                        transaction_number: data.transaction_number,
                        total: data.total,
                        paid_amount: this.paidAmount,
                        change: data.change,
                        pos_id: data.pos_id
                    };
                    this.showReceipt = true;
                    // Refresh stok produk dari server
                    this.fetchProducts();
                } else {
                    alert(data.message || 'Transaksi gagal');
                }
            } catch (e) {
                console.error(e);
                alert('Terjadi kesalahan jaringan');
            } finally {
                this.isProcessing = false;
            }
        },

        printReceipt() {
            if (this.receiptData && this.receiptData.pos_id) {
                window.open('/pos/' + this.receiptData.pos_id + '/print', '_blank');
            }
        },

        resetCart() {
            this.cart = [];
            this.discount = 0;
            this.paidAmount = 0;
            this.change = 0;
            this.customerId = '';
            this.receiptData = null;
            // Jangan kosongkan search & products agar tampilan tetap ada
        },

        formatNumber(num) {
            if (!num) return '0';
            return new Intl.NumberFormat('id-ID').format(Math.round(num));
        }
    }
}
</script>
</x-filament-panels::page>