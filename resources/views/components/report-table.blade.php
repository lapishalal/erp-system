@props([
    'rows' => [],
    'search' => [],
    'columns' => [],
    'totals' => null,
    'footer' => [],
    'empty' => 'Tidak ada data',
    'title' => null,
])

@once
<script>
    window.reportTable = (cfg) => ({
        rows: cfg.rows || [],
        searchKeys: cfg.search || [],
        columns: cfg.columns || [],
        totals: cfg.totals || {},
        q: '',
        page: 1,
        perPage: 10,
        get filtered() {
            const qu = (this.q || '').toString().trim().toLowerCase();
            if (!qu) return this.rows;
            return this.rows.filter(r => this.searchKeys.some(k => {
                const v = this.getVal(r, k);
                return v != null && String(v).toLowerCase().includes(qu);
            }));
        },
        get pages() {
            return Math.max(1, Math.ceil(this.filtered.length / this.perPage));
        },
        get safePage() {
            return Math.max(1, Math.min(this.page, this.pages));
        },
        get paged() {
            const s = (this.safePage - 1) * this.perPage;
            return this.filtered.slice(s, s + this.perPage);
        },
        getVal(obj, path) {
            return path.split('.').reduce((o, p) => (o == null ? o : o[p]), obj);
        },
        rangeText() {
            if (!this.filtered.length) return '0 hasil';
            const from = (this.safePage - 1) * this.perPage + 1;
            const to = Math.min(this.perPage * this.safePage, this.filtered.length);
            return `Menampilkan ${from}-${to} dari ${this.filtered.length}`;
        },
        fmtMoney(n) {
            if (n == null || isNaN(Number(n))) return '-';
            return 'Rp ' + Number(n).toLocaleString('id-ID', { maximumFractionDigits: 0 });
        },
        fmtNum(n) {
            if (n == null || isNaN(Number(n))) return '-';
            return Number(n).toLocaleString('id-ID', { maximumFractionDigits: 0 });
        },
        fmtPct(n) {
            if (n == null || isNaN(Number(n))) return '-';
            return Number(n).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + '%';
        },
        fmtDate(v) {
            if (!v || v === '' || v === '-') return '-';
            let d = new Date(v);
            if (isNaN(d.getTime())) {
                const m = String(v).match(/^(\d{4})-(\d{2})-(\d{2})/);
                if (!m) return '-';
                d = new Date(+m[1], +m[2] - 1, +m[3]);
            }
            return this.fmtParts(d, 'date');
        },
        fmtDateTime(v) {
            if (!v || v === '' || v === '-') return '-';
            let d = new Date(v);
            if (isNaN(d.getTime())) return '-';
            return this.fmtParts(d, 'datetime');
        },
        fmtParts(d, mode) {
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Des'];
            const pad = n => String(n).padStart(2, '0');
            const base = `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
            return mode === 'datetime' ? `${base} ${pad(d.getHours())}:${pad(d.getMinutes())}` : base;
        },
        cellText(row, col) {
            const v = this.getVal(row, col.key);
            switch (col.type) {
                case 'date': return this.fmtDate(v);
                case 'datetime': return this.fmtDateTime(v);
                case 'money': return this.fmtMoney(v);
                case 'number': return this.fmtNum(v);
                case 'pct': return this.fmtPct(v);
                default:
                    if (v == null || v === '') return (col.empty ?? '-');
                    if (col.map) return col.map[v] ?? (col.default ?? String(v));
                    return String(v);
            }
        },
        badgeClass(row, col) {
            const v = this.getVal(row, col.key);
            const key = v != null ? String(v) : '-';
            return (col.colors || {})[key] || 'bg-gray-100 text-gray-600 ring-gray-500/10';
        },
        cellClass(col) {
            let cls = 'px-4 py-3 whitespace-nowrap';
            if (col.align) cls += ' ' + col.align;
            if (col.mono) cls += ' font-mono text-xs';
            if (col.color) cls += ' ' + col.color;
            return cls;
        },
        footText(key, type) {
            const v = this.getVal(this.totals, key);
            if (type === 'number') return this.fmtNum(v);
            return this.fmtMoney(v);
        },
    });
</script>
@endonce

@php
    $config = [
        'rows' => $rows,
        'search' => $search,
        'columns' => $columns,
        'totals' => $totals,
    ];
@endphp

<div class="overflow-x-auto bg-white rounded-xl shadow-sm border dark:bg-gray-800"
     x-data="reportTable({{ Js::from($config) }})">
    @if($title)
        <div class="bg-gray-50 border-b border-gray-200 px-4 py-2 dark:bg-gray-700">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $title }}</p>
        </div>
    @endif

    <div class="flex items-center justify-between gap-3 p-3 border-b border-gray-200 dark:border-gray-700">
        <div class="relative w-full max-w-xs">
            <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"></path></svg>
            <input type="search" x-model="q"
                   placeholder="Cari..."
                   class="w-full rounded-lg border-gray-300 bg-white py-2 pl-9 pr-3 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200" />
        </div>
        <select x-model="perPage" @change="page = 1"
                class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200">
            <option value="10">10 / halaman</option>
            <option value="25">25 / halaman</option>
            <option value="50">50 / halaman</option>
        </select>
    </div>

    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
                <template x-for="col in columns" :key="col.label">
                    <th class="px-4 py-3 font-semibold" :class="col.align || 'text-left'">
                        <span x-text="col.label"></span>
                    </th>
                </template>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <template x-for="(row, i) in paged" :key="i">
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <template x-for="col in columns" :key="col.label">
                        <td :class="cellClass(col)">
                            <template x-if="col.type === 'badge'">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="badgeClass(row, col)" x-text="cellText(row, col)"></span>
                            </template>
                            <template x-if="col.type === 'link'">
                                <a :href="getVal(row, col.url_key)"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="inline-flex items-center gap-1 font-medium text-primary-600 hover:underline dark:text-primary-400">
                                    <span x-text="cellText(row, col)"></span>
                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                </a>
                            </template>
                            <template x-if="col.type !== 'badge' && col.type !== 'link'">
                                <span x-text="cellText(row, col)"></span>
                            </template>
                        </td>
                    </template>
                </tr>
            </template>
            <template x-if="filtered.length === 0">
                <tr>
                    <td class="px-4 py-6 text-center text-gray-500" :colspan="columns.length">{{ $empty }}</td>
                </tr>
            </template>
        </tbody>
        @if($footer)
            <tfoot class="bg-gray-100 dark:bg-gray-700 font-semibold">
                <tr>
                    @foreach($footer as $cell)
                        <td :class="'{{ $cell['align'] ?? 'text-left' }}'" class="px-4 py-3"
                            @isset($cell['colspan']) colspan="{{ $cell['colspan'] }}" @endisset>
                            @if(!empty($cell['value_key']))
                                <span x-text="footText('{{ $cell['value_key'] }}', '{{ $cell['type'] ?? 'money' }}')"></span>
                            @else
                                {{ $cell['text'] ?? '' }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="flex items-center justify-between gap-3 border-t border-gray-200 px-4 py-3 dark:border-gray-700">
        <span class="text-sm text-gray-500 dark:text-gray-400" x-text="rangeText()"></span>
        <div class="flex items-center gap-2">
            <button type="button"
                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium hover:bg-gray-50 disabled:opacity-40 dark:border-gray-600 dark:hover:bg-gray-800"
                    :disabled="safePage <= 1" @click="page = safePage - 1">
                Sebelumnya
            </button>
            <span class="text-sm text-gray-600 dark:text-gray-300" x-text="`Halaman ${safePage} dari ${pages}`"></span>
            <button type="button"
                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium hover:bg-gray-50 disabled:opacity-40 dark:border-gray-600 dark:hover:bg-gray-800"
                    :disabled="safePage >= pages" @click="page = safePage + 1">
                Selanjutnya
            </button>
        </div>
    </div>
</div>