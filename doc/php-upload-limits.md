# Increasing PHP upload limits

When you see **"The uploaded file exceeds the upload_max_filesize directive in php.ini"**, the file is larger than PHP’s current limit. Increase the limits as follows.

**This repo includes a `.user.ini`** in the project root with `upload_max_filesize=64M` and `post_max_size=64M`. Many hosts (e.g. Apache with mod_php) apply it automatically. **If you still see 2M/8M**, your host may ignore `.user.ini` (common with PHP-FPM). In that case set the same values in **Plesk → Domains → your domain → PHP Settings** and apply.

## 1. Set the limits

You need to raise **both** (and keep `post_max_size` ≥ `upload_max_filesize`):

- **`upload_max_filesize`** – max size of one uploaded file (e.g. `20M` for 20 MB).
- **`post_max_size`** – max size of the whole POST request; must be at least as large as `upload_max_filesize` (e.g. `25M`).

## 2. Where to set them

### Option A: `php.ini` (server-wide or PHP-FPM)

Edit `php.ini` and set:

```ini
upload_max_filesize = 20M
post_max_size = 25M
```

Then restart PHP (or the web server), e.g.:

- **PHP-FPM:** `sudo systemctl restart php8.3-fpm` (adjust version).
- **Apache:** `sudo systemctl restart apache2`.

### Option B: Plesk – PHP settings

1. In Plesk: **Domains** → your domain → **PHP Settings**.
2. Find **upload_max_filesize** and **post_max_size** and set them (e.g. `20M` and `25M`).
3. Apply and let Plesk restart PHP.

### Option C: `.user.ini` (per-directory, many shared hosts)

In the app root (same folder as `index.php` or the public entry point), create or edit `.user.ini`:

```ini
upload_max_filesize = 20M
post_max_size = 25M
```

Save the file. Changes can take a few minutes (or one request) to apply. No restart needed.

### Option D: Apache (if allowed)

In `.htaccess` or the vhost config:

```apache
php_value upload_max_filesize 20M
php_value post_max_size 25M
```

Only works if PHP is running as Apache module (not with PHP-FPM + proxy).

## 3. Check the limits

Create a small PHP file (e.g. `phpinfo.php`) and run it in the browser:

```php
<?php
echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . "\n";
echo 'post_max_size: ' . ini_get('post_max_size') . "\n";
```

Delete the file after checking.

## Suggested values for menu PDFs

For uploading multiple brand menu PDFs, a reasonable minimum is:

- **upload_max_filesize = 20M**
- **post_max_size = 25M**

Increase further if you often upload many or very large PDFs at once.
