# ERP System

Sistem ERP (Enterprise Resource Planning) berbasis web yang dibangun dengan **Laravel 11** dan **Filament 3**. Sistem ini mencakup modul Penjualan, Pembelian, Inventory, Akuntansi, HR/Payroll, POS, dan API Mobile dengan arsitektur multi-tenant.

---

## 📋 Tech Stack

| Komponen | Teknologi |
|----------|-----------|
| **Framework** | Laravel 11 (PHP 8.2+) |
| **Admin Panel** | Filament 3.2 |
| **Multi-Tenant** | `stancl/tenancy` |
| **RBAC** | `spatie/laravel-permission` |
| **PDF** | `barryvdh/laravel-dompdf` |
| **Excel** | `maatwebsite/excel` |
| **API Auth** | `laravel/sanctum` |
| **Database** | MySQL / MariaDB |

---

## 🚀 Fitur Utama

### 1. Master Data
- **Brand** — Manajemen merek produk
- **Category** — Kategori produk hierarkis
- **Product** — Produk dengan SKU, harga jual, harga beli terakhir, stok minimum, status aktif
- **Customer** — Pelanggan dengan limit kredit & NPWP
- **Supplier** — Pemasok
- **Warehouse** — Multi-gudang

### 2. Inventory / Stok
- **Product Stock** — Stok real-time per gudang (physical, available, outstanding)
- **Stock Transaction** — Riwayat pergerakan stok lengkap (IN, OUT, ADJUSTMENT, TRANSFER)
- **Stock Opname** — Penyesuaian stok fisik dengan otomatis hitung selisih
- **Goods Receipt** — Penerimaan barang dari PO dengan auto-load detail barang
- **Purchase Return** — Retur pembelian

### 3. Sales (Penjualan)
- **Sales Order** — Pesanan penjualan dengan approval flow
- **Delivery Order** — Surat jalan otomatis dari SO
- **Sales Invoice** — Faktur penjualan dari DO
- **POS (Point of Sale)** — Transaksi kasir dengan print struk thermal
- **Sales Report** — Filter by date, customer, status, brand, product → Export Excel

### 4. Purchase (Pembelian)
- **Purchase Order** — Pesanan pembelian dengan tracking sisa qty
- **Goods Receipt (GR)** — Penerimaan barang dengan fitur:
  - Auto-load barang dari PO (tidak perlu input manual)
  - Harga beli & barang di-lock (tidak bisa beda dengan PO)
  - Edit qty terima saja
  - Otomatis masuk stok ke gudang saat status = RECEIVED
- **Purchase Invoice** — Faktur pembelian dari GR
- **Purchase Return** — Retur pembelian

### 5. Accounting (Akuntansi)
- **Chart of Accounts** — Kode akun hierarkis (Aset, Kewajiban, Modal, Pendapatan, Beban)
- **Journal Entry** — Jurnal umum double entry
- **Cash In** — Penerimaan kas (terhubung ke invoice)
- **Cash Out** — Pengeluaran kas
- **Expense Category** — Kategori pengeluaran
- **Auto Journal** — Jurnal otomatis saat:
  - GR diterima (Persediaan ↑ vs Hutang ↑)
  - Sales Invoice dibuat (Piutang ↑ vs Penjualan ↑)
  - Cash In/Out
- **Profit & Loss** — Laporan laba rugi
- **Balance Sheet** — Laporan neraca
- **Cash Flow Report** — Arus kas

### 6. HR / Payroll
- **Employee** — Data karyawan (gaji pokok, tunjangan, BPJS, PPh21, PTKP)
- **Employee Loan** — Pinjaman karyawan
- **Payroll Period** — Periode penggajian
- **Payroll** — Slip gaji dengan kalkulasi otomatis:
  - Gaji pokok + tunjangan
  - Potongan BPJS karyawan & perusahaan
  - PPh21
  - Pinjaman karyawan
  - Alpha (tidak masuk)
- **Print Slip Gaji** — PDF

### 7. POS (Point of Sale)
- Halaman POS khusus dengan UI Filament
- Cari produk via API
- Checkout otomatis kurangi stok
- Print struk thermal
- Riwayat transaksi dengan export Excel

### 8. Reporting & Analytics
- **Dashboard Widgets** — Stats overview, Low Stock, Top Brands, Top Customers, Top Products
- **Sales Report** — Export Excel
- **Stock Report** — Export Excel
- **Financial Reports** — P&L, Balance Sheet, Cash Flow

### 9. API Mobile / External
| Endpoint | Fungsi |
|----------|--------|
| `POST /api/login` | Autentikasi Sanctum |
| `GET /api/dashboard` | Ringkasan dashboard |
| `GET /api/products` | Daftar produk |
| `GET /api/customers` | Daftar pelanggan |
| `GET /api/sales-orders` | Daftar SO |
| `POST /api/sales-orders` | Buat SO |
| `GET /api/inventory` | Stok barang |
| `GET /api/reports/*` | Laporan via API |
| `GET /api/exports/*` | Export via API |

### 10. Telegram Bot Integration
- Webhook & Polling mode
- Flow interaktif untuk cek stok, buat SO, cek laporan
- AI Parser untuk parsing pesan natural language

### 11. Multi-Tenant
- Setiap tenant punya database terpisah
- Subdomain-based tenancy
- Auto seeding per tenant

### 12. Security & Audit
- **RBAC** — Role-based access control (Spatie Permission)
- **Audit Log** — Tracking create, update, delete pada semua model utama
- **Auditable Trait** — Otomatis log perubahan

---

## 📁 Struktur Modul

```
app/
├── Models/              # 30+ model (Product, Stock, Sales, Purchase, Accounting, HR)
├── Filament/Resources/  # Filament CRUD resources
├── Observers/           # Business logic (GoodsReceiptObserver, dll)
├── Services/            # Reusable services (StockService, JournalService)
├── Http/Controllers/    # API & Telegram controllers
├── Providers/           # AppServiceProvider (observer registration)
├── Traits/              # Auditable trait
database/
├── migrations/          # 35+ migration
├── seeders/             # ChartOfAccountSeeder, TenantDatabaseSeeder
```

---

## ⚙️ Instalasi

### 1. Clone Repository
```bash
git clone https://github.com/lapishalal/erp-system.git
cd erp-system
```

### 2. Install Dependencies
```bash
composer install
npm install && npm run build
```

### 3. Environment Setup
```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:
```env
DB_DATABASE=erp_system
DB_USERNAME=root
DB_PASSWORD=your_password

# Untuk multi-tenant (opsional)
TENANCY_CENTRAL_DOMAINS=localhost,erp.local
```

### 4. Database & Migration
```bash
php artisan migrate
```

### 5. Seed Chart of Accounts (WAJIB untuk jurnal otomatis)
```bash
php artisan db:seed --class=ChartOfAccountSeeder
```

### 6. Seed Permissions & Roles
```bash
php artisan db:seed --class=PermissionSeeder
```

### 7. Create Admin User
```bash
php artisan tinker
>>> App\Models\User::create(['name'=>'Admin','email'=>'admin@erp.com','password'=>bcrypt('password')])
```

### 8. Run
```bash
php artisan serve
```

Buka: `http://localhost/admin`

---

## 🏗️ Flow Bisnis

### Purchase (Pembelian)
```
Purchase Order → Goods Receipt → Stok Masuk → Purchase Invoice
     ↓                ↓               ↓              ↓
  ORDERED      DRAFT/RECEIVED   ProductStock   Hutang Supplier
```

### Sales (Penjualan)
```
Sales Order → Delivery Order → Sales Invoice → Cash In
    ↓              ↓                ↓              ↓
 PENDING      SURAT JALAN      FAKTUR       PELUNASAN
```

### Inventory
```
Goods Receipt (IN)     → physical_stock ↑
Sales Invoice/DO (OUT)  → physical_stock ↓
Stock Opname            → adjustment
```

---

## 🛠️ Perbaikan Terbaru

### v1.1.0 - Fix Goods Receipt & Stok
- ✅ **Fix input qty lag** — Hapus `live()` dari Repeater, input bebas tanpa kehapus
- ✅ **Fix stok tidak masuk** — Register `GoodsReceiptObserver` di `AppServiceProvider`
- ✅ **GR auto-load dari PO** — Pilih PO → barang & harga auto-load, user edit qty terima saja
- ✅ **Fix total_qty NULL** — Hitung otomatis via model events (`saved`/`deleted`)
- ✅ **Warehouse tracking** — Stok masuk ke gudang yang dipilih (bukan hardcoded ID 1)
- ✅ **Auto Journal** — Jurnal otomatis saat GR diterima (Persediaan vs Hutang)
- ✅ **Chart of Accounts Seeder** — Seed akun default untuk jurnal otomatis

---

## 📝 Catatan Penting

### Chart of Accounts (Wajib di-seed)
Akun yang harus ada untuk jurnal otomatis:

| Kode | Nama | Tipe |
|------|------|------|
| 1-10001 | Kas | Aset |
| 1-10003 | Piutang Dagang | Aset |
| 1-20001 | Persediaan Barang Dagang | Aset |
| 2-10001 | Hutang Dagang | Kewajiban |
| 4-10001 | Penjualan | Pendapatan |
| 4-20001 | Pendapatan Lain-lain | Pendapatan |
| 5-10001 | HPP | Beban |
| 5-20001 | Beban Operasional | Beban |

Jalankan: `php artisan db:seed --class=ChartOfAccountSeeder`

### Multi-Tenant Setup
```bash
# Buat tenant baru
php artisan tenant:create --domain=tenant1.erp.local

# Setup database tenant
php artisan setup:tenant
```

---

## 📄 Lisensi

Open source untuk penggunaan pribadi dan komersial.

---

## 👤 Kontributor

- **lapishalal** — Initial development & maintenance

---

## 🆘 Troubleshooting

### Menu GR tidak muncul
Pastikan `navigationGroup` di `GoodsReceiptResource` sama dengan resource lain di grup Pembelian:
```php
protected static ?string $navigationGroup = 'Transaksi Pembelian';
```

### Stok tidak masuk setelah GR RECEIVED
1. Cek `AppServiceProvider` sudah register observer:
   ```php
   GoodsReceipt::observe(GoodsReceiptObserver::class);
   ```
2. Cek Chart of Accounts sudah di-seed
3. Cek `storage/logs/laravel.log` untuk error exact

### Input qty "1000" kehapus jadi "10"
Pastikan tidak ada `->live()` di field `qty` dalam Repeater. Gunakan `->live(onBlur: true)` jika perlu.

---

*Dibuat dengan ❤️ menggunakan Laravel 11 + Filament 3*
