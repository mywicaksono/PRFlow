# PRFlow

PRFlow is a Purchase Request workflow API built with Laravel 10+, PHP 8.2+, MySQL, and Sanctum.

## Setup (Windows Laragon)

### 1) Install dependencies

```bash
composer install
```

### 2) Create environment file

Windows CMD:

```cmd
copy .env.example .env
```

Git Bash:

```bash
cp .env.example .env
```

### 3) Generate app key

```bash
php artisan key:generate
```

### 4) Configure database

Edit `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=prflow
DB_USERNAME=root
DB_PASSWORD=
```

Create database (Laragon UI / HeidiSQL recommended), or CLI:

```bash
mysql -u root -p
CREATE DATABASE prflow;
```

If you get `Access denied ... (using password: NO)`, set correct `DB_USERNAME` and `DB_PASSWORD` from your Laragon MySQL account.

### 5) Install Sanctum (if not already installed)

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

## Daily commands

Fresh schema:

```bash
php artisan migrate:fresh
```

Run tests:

```bash
php artisan test
```

## API Auth (Bearer Token)

Use `Authorization: Bearer <token>` on protected endpoints.

Routes:

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout` (auth:sanctum)
- `GET /api/v1/auth/me` (auth:sanctum)

## Troubleshooting

- `table users already exists`
  - Check for duplicate users migrations.
  - Run `php artisan migrate:fresh`.
- `artisan not found`
  - You are not inside a full Laravel project root (missing `artisan`).
