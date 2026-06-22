# Tutorial Lengkap: Mendaftar API TikTok Shop & Shopee

## 📋 Ringkasan

Setelah mendaftar API, Anda akan mendapatkan data penting yang diisi ke form **"Marketplace API"** di ERP. Tutorial ini menjelaskan langkah demi langkah.

---

## 🛒 A. TIKTOK SHOP API

### 1. Persyaratan
- Akun TikTok Shop Seller Center aktif
- Toko sudah berjualan (minimal 1 produk)
- Email bisnis aktif
- Website dengan TOS & Privacy Policy (bisa pakai domain ERP)

### 2. Langkah Pendaftaran

**Step 1:** Buka https://partner.tiktokshop.com → Sign Up → pilih **"Seller"**

**Step 2:** Verifikasi identitas (nama bisnis, alamat, telepon, email)

**Step 3:** Masuk menu **"App Management"** → **"Create App"**
- App Name: `ERP-[NamaToko]`
- Category: `Utilities`
- TOS URL: `https://domain-erp-anda.com/terms`
- Privacy URL: `https://domain-erp-anda.com/privacy`
- Platform: `Web`

**Step 4:** Pilih Permission (centang semua):
- ✅ Product Management
- ✅ Order Management
- ✅ Logistics Management
- ✅ Finance Management

**Step 5:** Submit, tunggu approval 1-3 hari kerja

### 3. Data yang Didapat

| Data | Contoh | Diisi ke Form ERP |
|------|--------|-------------------|
| **App Key** | `1234567890abcdef` | App Key |
| **App Secret** | `a1b2c3d4...` (panjang) | App Secret |
| **Shop ID** | `1234567890` | Shop ID |
| **Shop Name** | `Toko Saya Official` | Nama Toko |

### 4. OAuth: Dapatkan Access Token

**Step 1:** Di Partner Center → **"Generate Auth Link"** → pilih toko → copy link

**Step 2:** Buka link → login Seller Center → klik **"Authorize"**

**Step 3:** Browser redirect ke URL Anda:
```
https://domain-erp-anda.com/callback?code=AUTH_CODE&shop_id=1234567890
```

**Step 4:** Tukar code jadi token:
```bash
curl -X POST https://open-api.tiktokglobalshop.com/api/v2/token/get \
  -H "Content-Type: application/json" \
  -d '{
    "app_key": "YOUR_APP_KEY",
    "app_secret": "YOUR_APP_SECRET",
    "auth_code": "AUTH_CODE",
    "grant_type": "authorized_code"
  }'
```

**Response:**
```json
{
  "access_token": "act.1234567890abcdef",
  "refresh_token": "ref.1234567890abcdef",
  "expire_in": 86400
}
```

### 5. Isi ke Form ERP

Menu: **Pengaturan → Marketplace API → Tambah Koneksi API**

| Field ERP | Data dari TikTok |
|-----------|------------------|
| Platform | TikTok Shop |
| Nama Toko | Toko Saya Official |
| Shop ID | 1234567890 |
| App Key | 1234567890abcdef |
| App Secret | a1b2c3d4... |
| Access Token | act.1234567890abcdef |
| Refresh Token | ref.1234567890abcdef |
| Aktif | ✅ Centang |

---

## 🛍️ B. SHOPEE OPEN API

### 1. Persyaratan
- Akun Shopee Seller Center aktif
- Email aktif (Gmail/Yahoo)
- Website dengan HTTPS

### 2. Langkah Pendaftaran

**Step 1:** Buka https://open.shopee.com → Sign Up → verifikasi email

**Step 2:** Pilih **"Shopee Seller"** → **"Registered Business Seller"** (kalau punya PT/CV) atau **"Individual Seller"**

**Step 3:** Verifikasi akun Seller Center Anda

**Step 4:** Isi profil:
- Business Name: Nama PT/CV
- Business Registration Number: NPWP/NIK
- Business Address: Alamat lengkap
- Contact Person: Nama & email

**Step 5:** Submit, tunggu approval 3-7 hari kerja

### 3. Data yang Didapat

Masuk ke **"Console"** → **"App Management"**:

| Data | Contoh | Diisi ke Form ERP |
|------|--------|-------------------|
| **Partner ID** | `123456` | App Key |
| **Partner Key** | `a1b2c3d4...` | App Secret |
| **Shop ID** | `987654321` | Shop ID |
| **Shop Name** | `Toko Saya Shopee` | Nama Toko |

> **Catatan:** Shopee pakai istilah **Partner ID** (bukan App Key) dan **Partner Key** (bukan App Secret).

### 4. OAuth: Dapatkan Access Token

**Step 1:** Build auth link:
```
https://partner.shopeemobile.com/api/v2/shop/auth_partner?partner_id=123456&redirect=https://domain-erp-anda.com/shopee/callback&timestamp=1697215282&sign=GENERATED_SIGN
```

**Step 2:** Buka link → login Seller Center → klik **"Authorize"**

**Step 3:** Redirect ke URL Anda:
```
https://domain-erp-anda.com/shopee/callback?code=abc123&shop_id=987654321
```

**Step 4:** Tukar code jadi token:
```bash
curl -X POST https://partner.shopeemobile.com/api/v2/auth/token/get \
  -H "Content-Type: application/json" \
  -d '{
    "code": "abc123",
    "partner_id": 123456,
    "shop_id": 987654321
  }'
```

**Response:**
```json
{
  "access_token": "786b4c74526e5242...",
  "refresh_token": "527a424f54494572...",
  "expire_in": 14400,
  "shop_id": 987654321
}
```

> **Penting:** Access Token Shopee hanya berlaku **4 jam**. Refresh pakai `refresh_token` (berlaku 30 hari).

### 5. Isi ke Form ERP

Menu: **Pengaturan → Marketplace API → Tambah Koneksi API**

| Field ERP | Data dari Shopee |
|-----------|------------------|
| Platform | Shopee |
| Nama Toko | Toko Saya Shopee |
| Shop ID | 987654321 |
| App Key | 123456 (Partner ID) |
| App Secret | a1b2c3d4... (Partner Key) |
| Access Token | 786b4c74526e5242... |
| Refresh Token | 527a424f54494572... |
| Aktif | ✅ Centang |

---

## ⚠️ Catatan Penting

### Webhook URL
| Platform | URL |
|----------|-----|
| TikTok | `https://domain-erp-anda.com/api/webhook/tiktok/{tenant-id}` |
| Shopee | `https://domain-erp-anda.com/api/webhook/shopee/{tenant-id}` |

### SSL WAJIB
Marketplace tidak menerima HTTP. Pastikan hosting punya SSL (HTTPS).

### Refresh Token
- **TikTok**: Access token expired ~24 jam, refresh otomatis
- **Shopee**: Access token expired 4 jam, perlu cron job auto-refresh

### Rate Limit
- **TikTok**: ~1000 request/minute
- **Shopee**: ~100 request/minute

---

## 🆘 Troubleshooting

| Masalah | Solusi |
|---------|--------|
| Aplikasi TikTok ditolak | Pastikan TOS & Privacy Policy URL bisa diakses publik |
| Shopee minta dokumen | Siapkan SIUP/NIB, NPWP, screenshot website |
| "Invalid sign" di Shopee | Timestamp harus dalam detik (bukan milidetik) |
| "Invalid auth code" | Code hanya berlaku 10 menit, generate ulang |
| Webhook tidak menerima data | Cek firewall hosting, port 443 harus terbuka |

---

## 📞 Kontak Support

| Platform | URL |
|----------|-----|
| TikTok Shop Developer | https://seller.tiktokglobalshop.com/university |
| Shopee Open Platform | https://open.shopee.com/developer-guide |
