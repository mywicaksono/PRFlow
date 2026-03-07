# PRFlow (Laravel)

PRFlow adalah aplikasi berbasis Laravel. Repository ini sudah disiapkan agar bisa dijalankan dari nol dan lolos migrasi + test default Laravel (SQLite in-memory untuk test).

## Requirements

- PHP 8.2+
- Composer 2+
- MySQL/MariaDB (untuk local development Laragon)
- (Opsional) Git Bash

## Setup dari Nol (Windows Laragon)

### 1) Install dependency

```bash
composer install
```

### 2) Buat file `.env`

**Windows CMD:**

```cmd
copy .env.example .env
```

**Git Bash:**

```bash
cp .env.example .env
```

### 3) Generate app key

```bash
php artisan key:generate
```

### 4) Konfigurasi database MySQL Laragon

Edit `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=prflow
DB_USERNAME=root
DB_PASSWORD=
```

> Sesuaikan `DB_USERNAME` dan `DB_PASSWORD` dengan konfigurasi MySQL Laragon Anda.

#### Cara membuat database `prflow`

- Opsi mudah: pakai HeidiSQL/Laragon UI, lalu buat database baru bernama `prflow`.
- Opsi CLI:

```bash
mysql -u root -p
```

Lalu di prompt MySQL:

```sql
CREATE DATABASE prflow;
```

### 5) Publish Sanctum migration (sekali saja bila belum ada)

```bash
php artisan vendor:publish --provider="Laravel\\Sanctum\\SanctumServiceProvider"
```

### 6) Jalankan migration

```bash
php artisan migrate
```

### 7) Jalankan test

```bash
php artisan test
```

---

## Verifikasi Clean Setup

Kalau ingin reset database dan memastikan semua migration bersih:

```bash
php artisan migrate:fresh
php artisan test
```

## Konfigurasi Testing

Testing menggunakan SQLite in-memory melalui `phpunit.xml`:

- `DB_CONNECTION=sqlite`
- `DB_DATABASE=:memory:`

Tidak perlu setup database tambahan untuk test.

## Troubleshooting

### 1) `table users already exists`

Penyebab umum:
- ada migration duplikat untuk tabel yang sama, atau
- database belum bersih.

Solusi:

```bash
php artisan migrate:fresh
```

### 2) `Access denied ... (using password: NO)`

Artinya MySQL Anda butuh password / user berbeda. Sesuaikan `.env`:

- `DB_USERNAME`
- `DB_PASSWORD`

sesuai akun MySQL Laragon yang aktif.

### 3) `artisan not found`

Biasanya berarti folder project bukan root Laravel yang benar, atau file `artisan` belum ada di direktori saat ini.

Pastikan jalankan perintah dari root repository PRFlow.


## Dokumentasi API

Dokumentasi endpoint API v1 tersedia di `docs/api.md`.
