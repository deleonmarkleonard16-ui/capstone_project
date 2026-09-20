<<<<<<< HEAD
# Digital Management System for Guidance Testing and Admission

Laravel-based admission test management system for Pangasinan State University - San Carlos Campus.

## Default Staff Login

- Email: `admin@psu-scc.test`
- Password: `password`

## Local Setup

1. Install dependencies.
2. Configure `.env`.
3. Run migrations and seeders:

```bash
php artisan migrate:fresh --seed
```

4. Start the app:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

## MySQL Setup

This project can run on MySQL for online hosting.

1. Create a MySQL database and user in your hosting panel or server.
2. Update `.env` with your MySQL credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=capproject
DB_USERNAME=your_mysql_username
DB_PASSWORD=your_mysql_password
```

3. Set your public domain:

```env
APP_URL=https://your-domain.com
QR_PUBLIC_URL=https://your-domain.com
```

4. Run the database setup:

```bash
php artisan config:clear
php artisan migrate --seed
```

## Moving Existing SQLite Data To MySQL

If you already encoded applicants, sessions, attendance, or results in SQLite, those records do not move automatically when you switch `.env` to MySQL.

You have two options:

1. Start fresh in MySQL with:

```bash
php artisan migrate:fresh --seed
```

2. Re-import your data after switching to MySQL.

For applicant records, you can use the CSV import feature after deployment.

## QR Check-In URL Setup

The QR code uses `QR_PUBLIC_URL` from `.env`.

- For same-Wi-Fi testing, set it to your laptop LAN IP, for example:

```env
QR_PUBLIC_URL=http://10.174.121.9:8000
```

- For phone access over mobile data, set it to a public tunnel or deployed domain, for example:

```env
QR_PUBLIC_URL=https://your-public-tunnel-url.ngrok-free.app
```

After changing `QR_PUBLIC_URL`:

1. Clear config:

```bash
php artisan config:clear
```

2. Open the session assignment page.
3. Click `Regenerate Session QR`.

## Example Tunnel Workflow

If you use a tunnel tool such as ngrok:

1. Start Laravel:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

2. Start your tunnel and point it to port `8000`.
3. Copy the public HTTPS URL from the tunnel.
4. Paste it into `.env` as `QR_PUBLIC_URL`.
5. Run:

```bash
php artisan config:clear
```

6. Regenerate the session QR from the staff page.

## Verified Commands

- `php artisan migrate:fresh --seed`
- `php artisan route:list`
- `php vendor/bin/phpunit --do-not-cache-result`
- `php artisan view:cache`
=======
# cap_project
For Capstone Project
>>>>>>> c9e97e6b501cafe003d4a2ba3d8575eee2b016ef
