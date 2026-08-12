# Deployment

## Docker Compose

```bash
cp .env.example .env
$EDITOR .env          # APP_SECRET, BASE_URL, mail settings
docker compose up -d
```

The `web` container serves the site on port 8080, `db` holds the data in a
named volume, `mail` is a Mailpit inbox for development, and `cron` runs the
background jobs. For production put a reverse proxy with TLS in front and set
`STAGE=production`.

## Apache

```apache
<VirtualHost *:443>
    ServerName qa.example.com
    DocumentRoot /var/www/askbot_php/html

    <Directory /var/www/askbot_php/html>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    SetEnv MYSQL_HOST   127.0.0.1
    SetEnv MYSQL_USER   askbot
    SetEnv MYSQL_PASSWORD secret
    SetEnv MYSQL_DB     askbot
    SetEnv BASE_URL     https://qa.example.com
    SetEnv APP_SECRET   change-me-to-32-random-characters
</VirtualHost>
```

`mod_rewrite` and `mod_headers` must be enabled; `html/.htaccess` does the rest.

## nginx + php-fpm

```nginx
server {
    listen 443 ssl http2;
    server_name qa.example.com;
    root /var/www/askbot_php/html;
    index index.php;

    location / { try_files $uri $uri/ /index.php$is_args$args; }

    location ~ ^/app/ { return 404; }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param MYSQL_HOST 127.0.0.1;
        fastcgi_param MYSQL_USER askbot;
        fastcgi_param MYSQL_PASSWORD secret;
        fastcgi_param MYSQL_DB askbot;
        fastcgi_param BASE_URL https://qa.example.com;
        fastcgi_param APP_SECRET change-me-to-32-random-characters;
    }

    location ~* \.(css|js|png|jpg|svg|webp|ico)$ {
        expires 7d;
        access_log off;
    }
}
```

Note the `location ~ ^/app/ { return 404; }` — the application directory must
never be reachable over HTTP.

## Shared hosting

1. Upload the repository, point the domain at `html/`.
2. Create a database, open the site, fill in the installer.
3. Set up the cron jobs from `crontab` (many panels offer a UI for this). If
   you cannot run cron at all, the site still works; mail is then sent on the
   next page view that triggers the queue.

## Checklist before going live

* `APP_SECRET` is long and random, and different from the example.
* `STAGE=production` so stack traces never reach a visitor.
* TLS everywhere; the session cookie only gets the `secure` flag over HTTPS.
* `html/uploads` is writable, `html/app` is not reachable over HTTP
  (`/admin/health` checks both).
* Backups: the database plus `html/uploads`.
* Mail: send a test message from the admin area and watch the queue drain.

## Updating

```bash
git pull
php html/app/app.php migrate      # idempotent, safe to run twice
docker compose restart web        # or reload php-fpm
```
