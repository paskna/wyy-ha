# Manual Home Assistant acceptance checklist

Run these checks on a real Home Assistant OS or supervised installation after
publishing the GHCR image as a public multi-architecture
manifest.

## Installation and ingress

- Add the GitHub repository through the Home Assistant App Store.
- Install WYY on an `amd64` Home Assistant host and on an `aarch64` host such
  as Raspberry Pi 5.
- Start WYY and confirm `/health` becomes healthy with no restart loop.
- Open the WYY sidebar item through Ingress and confirm the onboarding form
  appears exactly once.
- Create the first WYY administrator, sign in and reopen WYY through Ingress.
- Confirm routes, CSS, JavaScript, CSRF forms, login and logout keep the
  Ingress path and do not redirect to `/install`.

## Persistence and updates

- Add a wine, a note and an image; restart the App and confirm all data remains.
- Install a newer WYY version with a pending migration and confirm a dated
  pre-migration SQLite snapshot is created under `/data/wyy/backups`.
- Restore a Home Assistant backup and confirm `/data/wyy/app.key` is restored
  with the database, encrypted integration settings and branding assets.

## Mobile and media

- Use Home Assistant Companion App on iOS and Android for camera capture and
  gallery upload.
- Upload JPEG, PNG, WEBP, HEIC and HEIF. Confirm orientation, thumbnail,
  EXIF stripping and clear fallback messaging for an unsupported HEIC sample.
- Confirm an Ingress view does not register WYY's service worker, while a
  direct WYY deployment still exposes the normal PWA manifest and worker.

## Integrations and security

- Configure OpenAI only through WYY Administration and verify keys persist
  across a restart without appearing in App options or logs.
- Verify WYY user roles, personal data separation and admin access through
  Ingress.
- Verify the container has no direct external port configured and accepts only
  the Supervisor ingress gateway plus loopback health checks.
