# Changelog

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
