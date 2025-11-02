# Hosting Guide

This project is a Laravel application (located in `src/`) that builds its public assets into `src/public/`. When hosting on shared or managed PHP plans you typically do **not** have root server access, so the goal is to:

1. Upload the application code and dependencies.
2. Point the web server document root at `src/public` (or proxy requests to it).
3. Configure environment variables and file permissions.
4. Run database migrations and any scheduled jobs.

The sections below walk through a typical shared hosting workflow for both Apache-based plans and Windows/IIS plans that offer PHP support.

> ℹ️ Always confirm platform-specific steps with your hosting provider—they can clarify document root paths, PHP modules, and deployment policies unique to their environment.

---

## Requirements

- **PHP 8.2+** with typical Laravel extensions: `pdo_mysql`, `openssl`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, and `fileinfo`.
- **Composer 2+** locally (shared hosts rarely allow Composer at runtime).
- **MySQL 8+** (or MariaDB 10.4+) database credentials from your hosting control panel.
- Optional: **Node.js 18+** locally if you need to rebuild Vite assets.

> 💡 Most shared hosts ship with PHP configured for Apache + `mod_rewrite`. Confirm that URL rewriting is available before deploying.

---

## Prepare the build locally

1. **Install dependencies** (run from the repository root):
   ```bash
   cd src
   composer install --no-dev --optimize-autoloader
   npm install
   npm run build           # optional during updates; produces public/build assets
   ```

2. **Create the production environment file**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Update the `.env` file with production database credentials, mail settings, and `APP_URL` that matches your domain. For production set `APP_ENV=production` and `APP_DEBUG=false`.

3. **Warm Laravel caches** (reduces runtime configuration overhead):
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

4. **Create an archive for upload** (optional but convenient):
   ```bash
   cd ..
   zip -r report-generator.zip src
   ```

Upload `src/` (or the generated archive) to your hosting account. Place it *outside* the public web root when possible (e.g., `/home/<user>/apps/reportgen`), keeping only the `public` directory web accessible.

---

## Shared hosting on Apache + PHP

### 1. Pointing the document root at `public/`

The safest approach is to configure your hosting control panel so that the site's document root is `.../src/public`. Some providers call this setting *Web Root*, *Document Root*, or *Application Root*. When set correctly, no additional rewrite rules are required and the bundled `public/.htaccess` will handle pretty URLs.

### 2. When you cannot change the document root

If the host forces you to serve from a folder such as `public_html/`, move only the contents of `src/public/` into that folder and keep the rest of the Laravel project one level up:

```
/home/<user>/
├─ report-generator/          # contains the uploaded repository
│  └─ src/                    # Laravel app core
│     ├─ app/
│     ├─ bootstrap/
│     ├─ vendor/
│     └─ ...
└─ public_html/               # web-visible directory
   ├─ index.php
   ├─ .htaccess
   └─ build/
```

Update the paths in `public_html/index.php` so Laravel can reach the bootstrap and vendor directories:

```php
require __DIR__.'/../report-generator/src/vendor/autoload.php';
$app = require_once __DIR__.'/../report-generator/src/bootstrap/app.php';
```

If your host does not allow editing `index.php`, keep the app inside `public_html/reportgen` and use an `.htaccess` file in `public_html/` to proxy requests:

```apacheconf
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

Ensure that `public/index.php` and the `public` directory remain identical to the version shipped in this repository so Laravel boots correctly.

### 3. File permissions

- Set `storage/` and `bootstrap/cache/` to be writable by the web server user. Most shared hosts accept `chmod -R 775 storage bootstrap/cache` (or use the control panel's permission editor).
- Avoid granting world-writable (`777`) permissions unless absolutely required.

### 4. PHP configuration

- Increase the PHP memory limit if your host allows it (e.g., `memory_limit = 256M`).
- Set `post_max_size` and `upload_max_filesize` according to your expected report sizes.
- If you use a custom `php.ini` or `.user.ini`, place it inside `public_html/`.

---

## Shared hosting on Windows / IIS with PHP support

For hosts built around ASP.NET/IIS but offering PHP, keep the project structure the same as above but add a `web.config` file next to `public/index.php` so IIS rewrites requests into Laravel's `public` directory:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
  <system.webServer>
    <defaultDocument>
      <files>
        <clear />
        <add value="index.php" />
      </files>
    </defaultDocument>
    <rewrite>
      <rules>
        <rule name="Laravel Force public">
          <match url="(.*)" ignoreCase="false" />
          <action type="Rewrite" url="public/{R:1}" />
        </rule>
        <rule name="Laravel Routes" stopProcessing="true">
          <conditions>
            <add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" />
            <add input="{REQUEST_FILENAME}" matchType="IsDirectory" negate="true" />
          </conditions>
          <match url="^" ignoreCase="false" />
          <action type="Rewrite" url="public/index.php" />
        </rule>
      </rules>
    </rewrite>
  </system.webServer>
</configuration>
```

If your host cannot reference directories above the site root, place `public` at the root of the site (similar to the Apache option above) and adjust the `index.php` paths to reach your `vendor` and `bootstrap` directories.

---

## Dedicated / VPS / Cloud hosting

When you control the full server (or VM/container), you can follow standard Laravel production practices:

1. **Provision the stack**
   - Install PHP 8.2+ (with OPcache) and required extensions (`pdo_mysql`, `mbstring`, etc.).
   - Install a web server such as Nginx or Apache. For Nginx, proxy traffic to PHP-FPM; for Apache, enable `mod_proxy_fcgi` or `mod_php`.
   - Install and secure MySQL/MariaDB. Consider managed database services if available.

2. **Deploy the code**
   - Clone the repository or use CI/CD to publish the `src/` directory to `/var/www/report-generator` (or similar).
   - Run `composer install --no-dev --optimize-autoloader` and `npm run build` on the server or as part of your pipeline.
   - Set the web root of your virtual host to `.../src/public`. Sample Nginx server block:
     ```nginx
     server {
         server_name your-domain.com;
         root /var/www/report-generator/src/public;
         index index.php;

         location / {
             try_files $uri $uri/ /index.php?$query_string;
         }

         location ~ \.php$ {
             fastcgi_pass unix:/run/php/php8.2-fpm.sock;
             include fastcgi_params;
             fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
         }
     }
     ```

3. **Environment and secrets management**
   - Populate `src/.env` manually or inject environment variables through your process manager/CI secrets.
   - Run `php artisan key:generate` once and store the key securely.

4. **Permissions and ownership**
   - Ensure the web server user (e.g., `www-data`) owns or has write access to `storage/` and `bootstrap/cache/`.
   - Use POSIX ACLs or `chown`/`chmod` to prevent overly permissive settings while keeping deploy automation functional.

5. **Process supervision**
   - Configure a cron entry for `php artisan schedule:run` every minute.
   - Use `supervisor`, `systemd`, or `pm2` to run `php artisan queue:work` continuously if you rely on queues.

6. **Security and hardening**
   - Enforce HTTPS (Let’s Encrypt certificates via Certbot or your provider’s tooling).
   - Enable firewalls (UFW, security groups) and restrict database access to trusted hosts.
   - Keep system packages, PHP, and Composer dependencies patched.

7. **Observability**
   - Capture logs with centralized logging (journalctl, CloudWatch, etc.).
   - Monitor application health via uptime checks or APM tools.

Even with full control, your hosting provider or infrastructure platform may have specific guidelines for backups, firewall rules, or load balancers—consult their documentation to avoid conflicts.

---

## Post-deployment tasks

1. **Database migrations** – run via SSH or the control-panel terminal:
   ```bash
   php /home/<user>/report-generator/src/artisan migrate --force
   ```

2. **Storage symlink** – if the application serves user-uploaded files, run:
   ```bash
   php /home/<user>/report-generator/src/artisan storage:link
   ```

3. **Scheduler** – configure a cron job (or IIS scheduled task) to invoke Laravel's scheduler every minute:
   ```
   * * * * * php /home/<user>/report-generator/src/artisan schedule:run >> /dev/null 2>&1
   ```

4. **Queue workers** – on shared hosts without persistent processes, prefer the `database` queue driver (already available in `.env.example`). Trigger jobs with `php artisan queue:work --once` from cron if background processing is needed.

5. **Log rotation** – monitor `storage/logs/laravel.log`. Many shared hosts limit disk space, so download/rotate logs periodically.

---

## Troubleshooting checklist

- 500 error immediately after upload usually means the document root is not pointing at `public/` or `vendor/` is missing. Verify paths and rerun Composer locally.
- Blank pages or 404s often indicate `mod_rewrite` or IIS URL Rewrite is disabled. Contact your host to enable it.
- If environment variables are ignored, ensure your `.env` file sits in the Laravel root (`src/.env`) and clear cached configuration (`php artisan config:clear`).
- Slow performance in production can improve by enabling caches (`config:cache`, `route:cache`) and ensuring PHP opcode caching is active (most shared hosts provide OPcache).

With these steps your Laravel application should run reliably on standard shared hosting, whether the provider uses Apache or IIS under the hood.
