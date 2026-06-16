# PeersUnity Laravel Reverb production setup

The Flutter client connects to `wss://peersunity.com/app/{REVERB_APP_KEY}`. In
production, Nginx must proxy the `/app/` path to the local Laravel Reverb
process; otherwise the request falls through to the normal Laravel/static route
and returns `404` instead of `101 Switching Protocols`.

## Required production environment

Set these values in the production `.env`, keeping the existing app key, app ID,
and secret values:

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-existing-reverb-app-id
REVERB_APP_KEY=your-existing-reverb-app-key
REVERB_APP_SECRET=your-existing-reverb-app-secret
REVERB_HOST=peersunity.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
```

After changing production environment values, run:

```bash
php artisan config:clear
php artisan config:cache
```

## Nginx

Add `deploy/nginx/peersunity-reverb-location.conf` inside the existing HTTPS
`server { ... }` block for `peersunity.com`, before generic Laravel/static
fallback locations.

Then validate and reload Nginx:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## Supervisor

Install or merge `deploy/supervisor/peersunity-reverb.conf`, adjusting the
`command`, `user`, and log path if the production document root differs from
`/var/www/peersunity.com`.

Then reload Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart peersunity-reverb:*
```

## Verification

A successful browser/client WebSocket handshake to
`wss://peersunity.com/app/{REVERB_APP_KEY}` should receive `101 Switching
Protocols`; an HTTP `404` means the request is still reaching Laravel or a static
Nginx location instead of the Reverb proxy.
