# Tutorial Deploy: Fitur Marketplace Integration (TikTok & Shopee)

## Ringkasan Fitur

Fitur ini menambahkan integrasi marketplace ke ERP Anda:
- Menerima order otomatis dari TikTok Shop & Shopee via Webhook
- Auto-create Sales Order & Delivery Order
- Sinkronisasi stok ke marketplace
- Dashboard badge/filter per channel

---

## Daftar File yang HARUS Diupload ke Hosting

### 1. Models (app/Models/)
- `MarketplaceConnection.php` ✅
- `MarketplaceOrder.php` ✅
- `MarketplaceLog.php` ✅
- `Product.php` (update: tambah `sku` di $fillable) ⚠️
- `SalesOrder.php` (update: tambah `source` di $fillable) ⚠️
- `DeliveryOrder.php` (update: tambah relationship `details()`) ⚠️

### 2. Enums (app/Enums/)
- `MarketplacePlatform.php` ✅

### 3. Controllers (app/Http/Controllers/)
- `MarketplaceWebhookController.php` ✅

### 4. Services (app/Services/)
- `MarketplaceOrderProcessor.php` ✅
- `MarketplaceDeliveryOrderCreator.php` ✅
- `MarketplaceStockSyncService.php` ✅
- `MarketplaceApi/TikTokApiClient.php` ✅
- `MarketplaceApi/ShopeeApiClient.php` ✅

### 5. Jobs (app/Jobs/)
- `ProcessMarketplaceWebhook.php` ✅
- `SyncStockToMarketplace.php` ✅

### 6. Observers (app/Observers/)
- `DeliveryOrderObserver.php` ✅

### 7. Filament Resources (app/Filament/Resources/)
- `MarketplaceConnectionResource.php` ✅
- `MarketplaceConnectionResource/Pages/*.php` ✅
- `SalesOrderResource.php` (update: tambah kolom source & filter) ⚠️
- `DeliveryOrderResource.php` (update: tambah filter marketplace) ⚠️

### 8. Filament Actions (app/Filament/Actions/)
- `CreateDeliveryOrderAction.php` ✅

### 9. Console Commands (app/Console/Commands/)
- `SyncProductSkuFromCode.php` ✅
- `SyncStockToMarketplaceCommand.php` ✅

### 10. Migrations (database/migrations/tenant/)
- `2026_06_20_000001_create_marketplace_connections_table.php` ✅
- `2026_06_20_000002_create_marketplace_orders_table.php` ✅
- `2026_06_20_000003_create_marketplace_logs_table.php` ✅
- `2026_06_21_000004_alter_marketplace_orders_connection_nullable.php` ✅
- `2026_06_21_000005_add_sku_to_products_table.php` ✅
- `2026_06_21_000006_add_source_to_sales_orders_table.php` ✅

### 11. Routes (routes/)
- `api.php` (update: tambah webhook routes) ⚠️

### 12. Providers (app/Providers/)
- `AppServiceProvider.php` (update: tambah observer binding) ⚠️

---

## File yang TIDAK PERLU Diupload (Sudah Ada di Hosting)

❌ `vendor/` — jangan upload, jalankan `composer install` di hosting
❌ `node_modules/` — jangan upload
❌ `.env` — sudah ada di hosting, jangan timpa
❌ `storage/logs/*` — log lokal, tidak perlu
❌ `storage/framework/cache/*` — cache lokal
❌ `storage/framework/sessions/*` — session lokal
❌ `test-webhook.php` — file test lokal, hapus sebelum upload

---

## Langkah Deploy ke Hosting

### Step 1: Backup Database Hosting
``bash
mysqldump -u user -p database_erp &gt; backup_$(date +%Y%m%d).sql

### Step 2: Upload File
Upload semua file yang tercantum di daftar HARUS Diupload di atas.

### Step 3: Jalankan Migration di Hosting
bash
php artisan tenants:migrate
php artisan sync:product-sku [tenant-id]

### Step 4: Clear Cache
bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

### Step 5: Setup Webhook URL
Di TikTok Seller Center & Shopee Developer:
TikTok: https://domain-anda.com/api/webhook/tiktok/{tenant-id}
Shopee: https://domain-anda.com/api/webhook/shopee/{tenant-id}

### Step 6: Setup Queue Worker (Production)
Jika QUEUE_CONNECTION=database, jalankan:
bash
php artisan queue:work --queue=marketplace --sleep=3 --tries=3

Atau setup dengan Supervisor:

[program:erp-marketplace]
command=php /path/to/artisan queue:work --queue=marketplace --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/erp-marketplace.log

Catatan Penting
API Key Real: Saat ini API client masih MOCK. Ganti TikTokApiClient dan ShopeeApiClient dengan API call real setelah daftar di developer portal.
Webhook Signature: Saat ini tidak ada verifikasi signature. Untuk production, tambahkan verifikasi signature TikTok/Shopee di MarketplaceWebhookController.
SSL: Webhook marketplace WAJIB HTTPS. Pastikan hosting sudah punya SSL.
Tenant ID: Ganti {tenant-id} dengan UUID tenant yang sebenarnya.

Troubleshooting

| Masalah                       | Solusi                                               |
| ----------------------------- | ---------------------------------------------------- |
| Webhook 419                   | Pastikan route di `api.php`, bukan `web.php`         |
| Webhook 500                   | Cek `storage/logs/laravel.log` di hosting            |
| SO tidak terbuat              | Cek `products.sku` harus sama dengan SKU marketplace |
| DO tidak terbuat              | Cek `delivery_orders.status` enum harus `DRAFT`      |
| Stok tidak sync               | Cek `marketplace_connections` harus ada data aktif   |
| Tombol Create DO tidak muncul | Cek SO status harus `OPEN` dan belum ada DO          |

Dibuat: 2026-06-21
Versi: 1.0