# PeersUnity Laravel Reverb production setup

The Flutter client connects to `wss://peersunity.com/app/{REVERB_APP_KEY}`. If
that request returns HTTP `404`, the request is reaching Laravel/static routing
instead of the Reverb process. The expected successful handshake is `101
Switching Protocols`.

## What was inspected in this repository

- `config/reverb.php` defines the Reverb server listener from
  `REVERB_SERVER_HOST` / `REVERB_SERVER_PORT` and the public app options from
  `REVERB_HOST` / `REVERB_PORT` / `REVERB_SCHEME`.
- `config/broadcasting.php` uses the `reverb` driver with `REVERB_APP_ID`,
  `REVERB_APP_KEY`, and `REVERB_APP_SECRET` from environment variables.
- `app/Providers/BroadcastServiceProvider.php` registers the auth endpoint at
  `POST /api/broadcasting/auth` with `api` and `auth:sanctum` middleware.
- `app/Http/Middleware/VerifyCsrfToken.php` excludes only
  `api/broadcasting/auth`, so mobile Bearer-token auth is not blocked by CSRF.
- `routes/channels.php` keeps private/presence authorization checks in Laravel;
  private channels are not made public.
- `mobile_reference/lib/core/realtime/realtime_service.dart` is the Flutter
  connection-lifecycle reference for the app: it waits for the real connected
  callback before subscribing, avoids duplicate subscriptions, retries with
  bounded backoff, and sends only the Bearer token to `/api/broadcasting/auth`.

This repository does not contain a production `.env` file, production `/etc/nginx`
files, or the full Flutter app source. Do not commit `REVERB_APP_SECRET` or copy
it into Flutter; Flutter only uses the public `REVERB_APP_KEY`.

## Required production `.env`

Set these values in the production `.env`, preserving the existing real app ID,
key, and secret. The public key seen in the Flutter logs is
`z8hlmhiqxac2alirbdtx`.

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-existing-reverb-app-id
REVERB_APP_KEY=z8hlmhiqxac2alirbdtx
REVERB_APP_SECRET=your-existing-reverb-secret
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_HOST=peersunity.com
REVERB_PORT=443
REVERB_SCHEME=https
```

After changing production environment values on Linux through SSH:

```bash
cd /var/www/peersunity.com
php artisan optimize:clear
php artisan config:cache
php artisan reverb:restart
```

## Local Windows PowerShell commands

Run these only on the local Windows development machine:

```powershell
php artisan optimize:clear
php artisan reverb:restart
php artisan reverb:start --host=127.0.0.1 --port=8080
```

Keep the Reverb terminal open. Run Laravel in a second terminal:

```powershell
php artisan serve
```

Do not run `sudo`, `systemctl`, or `nginx -t` from Windows PowerShell unless a
local Nginx installation is intentionally configured and its executable path is
known.

## Production Nginx

On the production Linux server, first find the live domain config and back it up:

```bash
nginx -v
sudo grep -R "server_name peersunity.com" /etc/nginx
sudo cp /etc/nginx/sites-available/peersunity.com /etc/nginx/sites-available/peersunity.com.$(date +%Y%m%d%H%M%S).bak
```

If the live file is instead in `/etc/nginx/sites-enabled/peersunity.com` or
`/etc/nginx/conf.d/peersunity.com.conf`, back up that actual file.

Add `deploy/nginx/peersunity-reverb-location.conf` inside the existing HTTPS
`server { ... }` block for `peersunity.com`, before generic Laravel/static/PHP
fallback locations. Do not replace the whole server block and do not modify SSL
certificate directives.

Validate before reload, and never reload if validation fails:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## Persistent Reverb process

Use the process manager already used by the production server. If Supervisor is
used, install or merge `deploy/supervisor/peersunity-reverb.conf`, adjusting
`/usr/bin/php`, `/var/www/peersunity.com`, and `www-data` only if the server uses
different real values.

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart peersunity-reverb
sudo supervisorctl status peersunity-reverb
```

If systemd is used instead, install or merge `deploy/systemd/peersunity-reverb.service`:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now peersunity-reverb
sudo systemctl restart peersunity-reverb
sudo systemctl status peersunity-reverb
```

Confirm the process is listening locally and remains running after SSH logout:

```bash
ss -ltnp | grep ':8080'
```

## Verification checklist

1. `ss -ltnp | grep ':8080'` shows Reverb listening on `127.0.0.1:8080`.
2. Supervisor or systemd shows the Reverb service as running after reconnecting
   over SSH.
3. `wss://peersunity.com/app/{REVERB_APP_KEY}` returns `101 Switching Protocols`,
   not `404 Not Found`.
4. `POST https://peersunity.com/api/broadcasting/auth` with a valid Bearer token
   returns channel authorization JSON and unauthenticated requests return JSON
   `401`.
5. REST APIs, admin pages, FCM notifications, and TLS certificates continue to
   work unchanged.
