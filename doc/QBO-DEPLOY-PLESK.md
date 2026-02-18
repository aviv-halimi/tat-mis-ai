# Deploy QBO (QuickBooks SDK) on Plesk server

The app uses the QuickBooks PHP SDK via **Composer** (`composer.json` → `vendor/`). After you push code via git, install dependencies on the server as below.

---

## Step 1: SSH into the Plesk server

- In Plesk: **Tools & Settings** → **SSH Access** (enable if needed), or use your host’s SSH.
- Connect, e.g. `ssh user@your-server.com`, and go to the site’s document root (e.g. `cd /var/www/vhosts/yourdomain.com/httpdocs` or the path Plesk uses for this domain).

---

## Step 2: Pull latest code (if not already deployed)

```bash
cd /var/www/vhosts/yourdomain.com/httpdocs   # or your actual path
git pull origin main
```

Use your real branch name if it’s not `main`.

---

## Step 3: Install Composer (if not already installed)

Check if Composer is available:

```bash
composer --version
```

If not installed, install it (one-time):

```bash
cd /tmp
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
# or for current user only (no sudo):
# mkdir -p $HOME/bin && mv composer.phar $HOME/bin/composer && export PATH="$HOME/bin:$PATH"
```

Or use the Plesk “PHP Composer” extension if you have it: **Domains** → **your domain** → **PHP Composer** → enable and run from there.

---

## Step 4: Install PHP dependencies (QBO SDK and others)

From the **project root** (where `composer.json` is):

```bash
cd /var/www/vhosts/yourdomain.com/httpdocs   # project root
php -v                                       # confirm PHP version (e.g. 8.0+)
composer install --no-dev
```

- `composer install` reads `composer.json` and creates/updates the `vendor/` folder (including `quickbooks/v3-php-sdk`).
- `--no-dev` skips dev dependencies (recommended on production).

If your site runs under a specific PHP version in Plesk (e.g. 8.3), use that CLI:

```bash
/opt/plesk/php/8.3/bin/php /usr/local/bin/composer install --no-dev
```

Or:

```bash
/opt/plesk/php/8.3/bin/php $(which composer) install --no-dev
```

Adjust `8.3` to the PHP version configured for the domain.

---

## Step 5: Confirm the QBO SDK is present

```bash
ls -la vendor/quickbooks/v3-php-sdk
```

You should see the SDK files. The app loads them via `vendor/autoload.php` in `inc/qbo.php`.

---

## Step 6: Permissions (if needed)

If the web server user is different from the user that ran `composer install`, fix ownership so the app can read `vendor/`:

```bash
# Replace www-data and your-user with your server’s web user and your SSH user
sudo chown -R www-data:www-data vendor
# or
sudo chown -R apache:apache vendor
```

Plesk often uses `nginx` or the domain’s system user; adjust as per your host.

---

## Summary (copy-paste)

From the project root on the server:

```bash
cd /var/www/vhosts/yourdomain.com/httpdocs
git pull origin main
composer install --no-dev
```

If `vendor/` is not in git (recommended), this is required after every deploy. If you use a deploy script or CI, add `composer install --no-dev` there.

---

## Optional: Add `vendor/` to .gitignore (local)

So you don’t commit the whole `vendor/` folder from your machine, add to the project root `.gitignore`:

```
/vendor/
composer.lock
```

Then on the server you **must** run `composer install` after each pull. Keeping `composer.lock` in git is recommended so the server installs the same versions as you.
