# WYY for Home Assistant

WYY runs as a Home Assistant App with Home Assistant Ingress and its own WYY
accounts. Home Assistant authentication protects the embedded entry point;
WYY's user, role and wine-data separation remain unchanged.

## First start

1. Install and start the App from the Home Assistant App Store.
2. Open **WYY** from the sidebar.
3. Create the first WYY administrator in the onboarding page.
4. Configure optional recognition providers in **Administration -> API & Integrationen**.

The App creates its SQLite database, migrations and an encryption key in
`/data/wyy` automatically. Do not delete this directory: it contains users,
encrypted API settings, uploaded images and all wine data.

## Technical options

- `log_level`: Container log verbosity.
- `timezone`: IANA timezone used by the container, e.g. `Europe/Zurich`.

No database or API credentials are configured in Home Assistant App options.

## Data and backups

Home Assistant stores `/data` as App data. The App requests a cold backup, so
the SQLite database is not copied while it is being written. Retain the
generated `/data/wyy/app.key` in every backup and restore; it is required to
decrypt WYY settings and credentials after restore.

## Development

From this directory:

```sh
docker build -t wyy-ha:dev .
docker run --rm -p 8099:8099 -v "$PWD/data:/data" wyy-ha:dev
```

The App has no host port mapping in Home Assistant; access is provided through
Ingress. The direct port mapping above is solely for local development.

See [TESTING.md](TESTING.md) for the real Home Assistant OS acceptance steps.
