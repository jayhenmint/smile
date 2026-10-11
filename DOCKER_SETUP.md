# SMILE local development

SMILE has its own PHP image, PostgreSQL database, Docker network, and persistent volume. Run commands from this project folder with Docker Desktop running.

```powershell
docker compose up -d --build
docker compose exec php composer install
docker compose exec php php artisan migrate
npm.cmd run build
```

Open http://localhost:8001. This uses Laravel's development server, not a production server.

Inside Docker, Laravel connects to `postgres:5432`. In a Windows database client use:

| Setting | Value |
|---|---|
| Host | `127.0.0.1` |
| Port | `5433` |
| Database | `smile_dev` |
| Username | `smile_app` |
| Password | Read `DB_PASSWORD` from your local `.env` |

The password is generated locally and is not committed. Copy `.env.example` for a new installation and set a new database password before starting, then run `docker compose exec php php artisan key:generate`. PostgreSQL initialization credentials apply only when the volume is first created.

Run Artisan inside SMILE's PHP container:

```powershell
docker compose exec php php artisan migrate:status
docker compose exec php php artisan test
docker compose stop
docker compose up -d
```

Stopping preserves the database. Avoid `docker compose down -v`, which deletes it. SNEDY uses different containers and ports; these commands manage only the `smile` Compose project.

Public registration is disabled. Seed the five required role definitions with `docker compose exec php php artisan db:seed`; this creates no default accounts. School setup and administrator account creation remain unfinished. No SMILE learner tables exist yet.

The existing `smile_testing` PostgreSQL database is reserved for tests. To run backend tests there instead of the default in-memory SQLite:

```powershell
docker compose exec -T -e DB_CONNECTION=pgsql -e DB_DATABASE=smile_testing php php artisan test
```

Never use `smile_dev` for tests: test migrations can reset their target database.
