# Data Flow

 Workflow management (Laravel app under `backend/`).

## Local setup

Requires Docker + PHP 8.2+ + Composer + Node 20+.

```bash
cd backend
cp .env.example .env
docker compose up -d      # MySQL 8.0 on 127.0.0.1:3306 (db: dataflow)
composer setup            # install, migrate, build assets
composer dev              # serve + queue + vite
```

Default DB: MySQL 8.0 via `backend/docker-compose.yml`. Tests use sqlite `:memory:`
for speed (see `backend/phpunit.xml`).
