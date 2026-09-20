## MySQL Migration Notes

This Laravel project already supports MySQL through `config/database.php`.

### What To Prepare

- MySQL host
- MySQL port
- Database name
- Database username
- Database password
- Public domain for `APP_URL` and `QR_PUBLIC_URL`

### Recommended `.env` Values

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
QR_PUBLIC_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=capproject
DB_USERNAME=your_mysql_username
DB_PASSWORD=your_mysql_password
```

### First-Time Setup

Run these commands on the deployed server:

```bash
php artisan config:clear
php artisan migrate --seed
```

### Important Notes

- Switching from SQLite to MySQL does not copy old data automatically.
- Existing QR codes generated with an old `ngrok` domain should be regenerated after you set the final production domain.
- This app stores sessions, cache, and queue jobs in the database, so the migration step must complete successfully before use.
