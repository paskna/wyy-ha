# Changelog

## 0.1.3

- Preserve the external Home Assistant host, port and Ingress prefix in redirects and asset URLs.
- Forward reverse-proxy origin headers to PHP without treating plain HTTP as HTTPS.
- Replace the stale frontend bundle and disable service-worker registration during Ingress use.
- Exercise the complete Ingress redirect and frontend-asset flow in container CI.

## 0.1.2

- Fix the blank Home Assistant Ingress page caused by unreadable Laravel cache manifests.
- Restore the normal process umask before Artisan startup work and normalize runtime permissions.
- Verify PHP-FPM access to Laravel cache manifests and the complete health response in CI.

## 0.1.1

- Correct Home Assistant watchdog placeholder syntax.
- Use the current Dockerfile base-image convention without legacy `build.yaml`.
- Verify first boot, SQLite migrations and the health endpoint in CI.

## 0.1.0

- Initial standalone WYY Home Assistant App.
- Ingress deployment with persistent SQLite storage.
- Automatic technical bootstrap and WYY administrator onboarding.
