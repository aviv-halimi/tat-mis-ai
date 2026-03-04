# Fixing 504 Gateway Time-out on “Sync PO with menu”

When you click **Sync PO with menu (AI)**, the request can take 40–90+ seconds (upload to Gemini, model run, response). If nginx or PHP cuts the request before that, you get **504 Gateway Time-out**.

Apply **one or both** of the following.

---

## 1. Increase timeouts (recommended)

The sync request must be allowed to run long enough end-to-end.

### Nginx (proxy/gateway)

Increase the proxy read timeout so nginx waits longer for the PHP response.

**Option A – Plesk**  
- **Domains** → your domain → **Apache & nginx Settings** (or **Proxy mode**).  
- Find **Additional nginx directives** (or similar).  
- Add (or merge into `location` that proxies to PHP):

```nginx
proxy_read_timeout 120s;
proxy_connect_timeout 120s;
proxy_send_timeout 120s;
```

If PHP is passed via FastCGI to a unix socket or upstream, you may need:

```nginx
fastcgi_read_timeout 120s;
```

Apply and reload nginx.

**Option B – Custom nginx config**  
In the `server` or `location` block that handles the app:

```nginx
location ~ \.php$ {
    # ... existing fastcgi params ...
    fastcgi_read_timeout 120s;
}
# or if using proxy_pass to PHP-FPM:
proxy_read_timeout 120s;
```

Then: `sudo nginx -t` and `sudo systemctl reload nginx`.

**Suggested value:** **120** seconds. If you still get 504, use **180** or **300** seconds (large PDFs or 1000+ PO products often need 90+ seconds). Ensure these directives apply to the **server** or **location** block that serves this app (e.g. run `sudo nginx -T | grep read_timeout` to verify).

### PHP (max execution time)

Ensure PHP doesn’t kill the script before the request finishes.

- **Plesk:** **Domains** → your domain → **PHP Settings** → set **max_execution_time** to **120** (or **0** for no limit; use with care).  
- **php.ini / .user.ini** in the app root:

```ini
max_execution_time = 120
```

Restart PHP-FPM (or the web server) if needed so the new value is used.

---

## 2. Use a faster Gemini model (optional)

A smaller, faster model can shorten the sync time and reduce the chance of hitting the timeout.

In **\_config.php** (or in environment), set a model used **only** for PO menu sync:

```php
// Faster model for PO menu sync (helps avoid 504). Use gemini-1.5-flash.
define('GEMINI_PO_MENU_MODEL', 'gemini-1.5-flash');
```

If `GEMINI_PO_MENU_MODEL` is set, the app uses it for “Sync PO with menu” only; other features (e.g. invoice validation) keep using `GEMINI_MODEL`.  
**Some API keys or regions only support certain models.** If you get a 404 "model not found", leave `GEMINI_PO_MENU_MODEL` commented out so the app uses `GEMINI_MODEL` (e.g. gemini-2.0-flash). If you still see 504 timeouts, increase Nginx/PHP timeouts as in section 1.

---

## Quick check

After changing settings:

1. Reload nginx (and PHP-FPM if you changed PHP settings).  
2. Run **Sync PO with menu** again and wait; it may take 30–90 seconds.  
3. If you still get 504, raise the timeouts further (e.g. 180s) and/or use `GEMINI_PO_MENU_MODEL` as above.
