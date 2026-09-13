# WYY Home Assistant App documentation

## Ingress

WYY listens on port `8099` only for the Home Assistant Supervisor ingress
gateway. The application detects `X-Ingress-Path` centrally and uses it when
generating routes, forms, redirects and assets. Its service worker is not
registered in ingress mode, preventing a PWA cache from taking control of
tokenized ingress URLs. Direct WYY deployments retain their normal PWA.

## Persistent storage

All mutable application data is below `/data/wyy`:

- `database/wyy.sqlite`: SQLite application database.
- `storage`: Laravel cache, sessions, logs, image uploads and branding media.
- `app.key`: persistent Laravel encryption key.

SQLite is initialized with WAL, foreign-key enforcement and a 5-second busy
timeout. Startup runs pending migrations before the web server starts. A
failed migration exits the container and does not mark the App ready.

## Updating

Before migrations, Home Assistant's cold backup mechanism protects `/data`.
The startup script runs migrations idempotently. Do not replace `/data/wyy`
when updating the App image.

## Troubleshooting

- **Ingress 502:** Open App logs. A migration or PHP extension error prevents
  the web server from starting.
- **Migration failed:** Restore the latest Home Assistant backup and include
  the WYY App data. Do not delete `app.key`.
- **HEIC unavailable:** The image contains ImageMagick and libheif. If a
  specific HEIC still cannot be decoded, WYY reports a recoverable upload
  error; use JPEG as a fallback.
- **OpenAI errors:** Configure or test providers in WYY Administration. API
  keys are encrypted in the WYY database and are never Home Assistant options.
- **Storage full:** Free host storage, then restart the App. Inspect WYY logs
  from the Home Assistant UI.

## Security

The App requests no host network, Docker socket, privileged mode, device or
Home Assistant configuration mount. It runs only behind Ingress, and WYY
continues to enforce its own login, roles and per-user data authorization.
