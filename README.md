# Ikusasa Lethu — BBD Learner Tracking System

PHP application for learner projects, attendance, learning content, badges and moderated community messages.

## Render + existing Aiven database

This release is intended for the existing Render service at https://ikusasa-lethu-bbd.onrender.com and its Aiven MySQL database. Deployment remains manual. The code does not import a schema or alter the Aiven database on startup.

1. Merge the reviewed changes into `ikusasa-lethu-bbd`, or select the fix branch in Render. Keep automatic deployment disabled until the checks below are complete.
2. Keep your existing `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` and `DB_PASS` environment variables. `DB_PORT` must be Aiven's assigned port. TLS certificate verification is enabled for Aiven hosts. The bundled `config/aiven-ca.pem` must match the certificate for your Aiven service; optionally set `DB_SSL_CA` to a mounted CA certificate path.
3. Use the repository's `Dockerfile`, with the default Docker command. It listens on Render's `PORT` value. Set the health-check path to `/health.php` and `SESSION_SECURE=1`.
4. Attach persistent storage outside the web root, for example a Render disk mounted at `/var/data/ikusasa`, and set `UPLOAD_ROOT=/var/data/ikusasa`. This holds project files, photos, sessions and login-rate-limit counters. Render's normal filesystem is ephemeral: an Aiven database does not preserve uploaded files. Persistent disks require a paid service. If you use a free service, arrange durable object storage before relying on uploads across deployments; that integration is not included in this release.
5. Back up the existing Aiven database and the current `uploads/` files **before** a redeploy replaces the running container. Restore old uploads through the migration command below. Startup never deletes or automatically migrates your existing files.
6. Manually deploy the reviewed commit. Existing sessions require a new login. Rotate any live password that was published in the old README or Compose file.
7. Verify admin/learner login, project creation, upload, editing, save, preview, attendance and password changes. Confirm the uploaded files remain after a second deployment/restart.

See [Render disks](https://render.com/docs/disks) and [Render Docker deployment](https://render.com/docs/docker).

### Existing upload migration

Restore a backup of the old `uploads` folder outside the served directory, then run in the deployed container's shell:

```sh
php scripts/migrate-uploads.php /absolute/path/to/backup/uploads
chown -R www-data:www-data /var/data/ikusasa
```

The command copies approved files into protected storage, preserving the existing database paths. It never deletes the source files, overwrites different destination files, or executes uploaded PHP. It reports skipped unsafe/unsupported files; keep the backup and resolve skipped files before allowing edits. Run this command once, and verify several existing projects afterwards.

### Preview behaviour

HTML, CSS and JavaScript run inside an opaque-origin iframe with `sandbox="allow-scripts"`. Local CSS, JavaScript, images and fonts are bundled into the preview. Project previews cannot access the application session, call network APIs, submit forms, embed other sites or navigate the parent page. Remote CDN scripts/styles and JavaScript module imports are not supported by this isolated preview. Download a project to run those features in a separate local development environment.

Project ZIP uploads: 20 MB compressed, 50 MB extracted, up to 200 entries, 10 MB per extracted file. Allowed files include HTML, CSS, JS, TXT, JSON, supported raster images and fonts. PHP, SVG, hidden configuration files, links, traversal paths and encrypted ZIP members are rejected. Downloads require a current authorised account. Code files and documents are served as attachments, never executed by Apache.

## Local development

The application code currently lives on `ikusasa-lethu-bbd`; `main` contains the original README only. A later reviewed merge can make `main` the complete default branch.

```sh
git clone --branch ikusasa-lethu-bbd https://github.com/jovilondon51/ikusasa-lethu-bbd.git
cd ikusasa-lethu-bbd
cp .env.example .env
```

Fill `.env` locally; never commit it. To use Aiven, keep its real connection details and run:

```sh
docker compose up --build
```

For an isolated local MySQL database, set `LOCAL_DB_PASSWORD` and `LOCAL_DB_ROOT_PASSWORD` to your own strong values. The base Compose file still requires nonempty `DB_HOST`, `DB_NAME`, `DB_USER` and `DB_PASS` placeholders; the local override replaces them:

```sh
docker compose -f docker-compose.yml -f compose.local.yml up --build
```

Open http://localhost:8080. The local override imports `database/schema.sql` into a **new** local database. There are no default account credentials. Set `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_USERNAME` and a 12–72 byte `ADMIN_PASSWORD` for the one-time CLI command `php scripts/create-admin.php`, then remove the password from the environment. Use `docker compose exec -e ... web php scripts/create-admin.php` if running locally in Docker. This is not needed for your existing Aiven accounts.

## Validation

```sh
php tests/security.php
find . -name '*.php' -not -path './.git/*' -exec php -l {} \;
node --check assets/js/main.js
node --check assets/js/projects.js
node tests/projects.js
```

With Playwright and Chromium installed, run `node tests/preview-browser.js` to verify actual browser isolation.

The HTTP integration test (`tests/integration.py`) uses a disposable database and PHP server. Its required fixture environment is documented in that file. Never run its fixture setup against the live Aiven database. GitHub Actions runs PHP 8.2 syntax/security checks and MySQL 8 integration checks.

## Operational limits

- File storage, PHP sessions and login throttling are designed for one Render instance with a persistent disk.
- Password resets, inactive accounts and deleted accounts revoke access on the next request. Sessions expire after 30 minutes without activity.
- Attendance streaks use consecutive distinct recorded class dates, excluding future dates; a missing/absent/late entry breaks the streak. Already-earned badges are not removed.
- This release does not automatically migrate or recreate the existing Aiven schema, rotate live passwords, change Render settings or deploy the service.
