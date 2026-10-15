# Local Development Setup — FindBack

This guide walks through setting up the FindBack backend on a Windows machine.

## Prerequisites

| Tool         | Version   | Notes                                        |
| ------------ | --------- | -------------------------------------------- |
| PHP          | 8.3       | with `pdo_pgsql`, `openssl`, `mbstring`, `curl`, `zip` extensions enabled |
| Composer     | 2.x       | PHP dependency manager                       |
| PostgreSQL   | 15        | database server                              |

### Installing the toolchain (Windows)

**PHP & Composer via winget:**

```powershell
# PHP
winget install --id PHP.PHP.8.3 --source winget

# Composer: download the installer from https://getcomposer.org/installer
php composer-setup.php --install-dir=<dir> --filename=composer
```

After installing PHP, make sure the required extensions are enabled in
`php.ini` (remove the leading `;`):

```ini
extension=pdo_pgsql
extension=pgsql
extension=openssl
extension=mbstring
extension=curl
extension=zip
extension=fileinfo
```

**PostgreSQL 15:** install from https://www.postgresql.org/download/windows/
(or via `winget install PostgreSQL.PostgreSQL.15`). Note the superuser
(`postgres`) password you choose during setup.

---

## 1. Clone & install dependencies

```powershell
git clone https://github.com/Kaoserahamed/FindBack-Smart-Lost-Found-Network.git
cd FindBack-Smart-Lost-Found-Network\backend
composer install
```

## 2. Configure environment

```powershell
copy .env.example .env
php artisan key:generate
```

Edit `.env` and set your PostgreSQL connection:

```env
APP_NAME=FindBack
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=findback
DB_USERNAME=postgres
DB_PASSWORD=your_postgres_password
```

> **Never commit `.env`.** It is git-ignored and holds your secrets.

## 3. Create the database

```powershell
# Using psql (set PGPASSWORD or you will be prompted)
psql -U postgres -h 127.0.0.1 -c "CREATE DATABASE findback;"
```

## 4. Run migrations

```powershell
php artisan migrate
```

To rebuild the database from scratch (drops all tables):

```powershell
php artisan migrate:fresh
```

## 5. Verify the connection

```powershell
php artisan migrate:status
```

## 6. Start the dev server

```powershell
php artisan serve
```

The app is now available at http://localhost:8000.

---

## Useful commands

| Task                         | Command                       |
| ---------------------------- | ----------------------------- |
| Start PostgreSQL (if manual) | `Start-Service postgresql-x64-15` |
| Run migrations               | `php artisan migrate`         |
| Fresh DB + migrate           | `php artisan migrate:fresh`   |
| Open Tinker REPL             | `php artisan tinker`          |
| List routes                  | `php artisan route:list`      |
> **Note:** `php artisan db:seed` also creates a `community_memberships` row
> enrolling the seeded test user (`test@example.com`) as an **active member** of
> the SUST community. This is required so the community dropdown in the
> **Create Report** form is populated (it only lists communities you are an
> active member of).