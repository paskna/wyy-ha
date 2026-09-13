# WYY Home Assistant repository

This repository contains the WYY Home Assistant App for the `paskna` GitHub
account.

## Install from GitHub

1. In Home Assistant open **Settings -> Apps -> App Store**.
2. Open the menu, choose **Repositories**, and add this GitHub repository URL.
3. Find **WYY**, install it and start it.
4. Optionally enable **Show in sidebar**, then open WYY and complete the WYY
   administrator onboarding.

This App requires Home Assistant OS or a supervised Home Assistant installation
with App support. It supports `amd64` and `aarch64` (including Raspberry Pi 5
64-bit).

## Publishing images

The App points to `ghcr.io/paskna/wyy-ha`. GitHub Actions builds `amd64` and
`aarch64` images for tags and publishes a multi-architecture manifest. Publish
the GHCR package publicly before sharing the repository; Home Assistant must be
able to pull it without a registry login.

## Local checks

```sh
yamllint repository.yaml wyy/config.yaml wyy/build.yaml
docker build --build-arg BUILD_FROM=ghcr.io/home-assistant/amd64-base:3.24-2026.06.1 -t wyy-ha:dev wyy
```

See [wyy/DOCS.md](wyy/DOCS.md) for persistence, backup, ingress and recovery
details, and [wyy/TESTING.md](wyy/TESTING.md) for hardware and Supervisor
acceptance checks.
