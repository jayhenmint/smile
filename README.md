# SMILE

Student Monitoring for Inclusive Learning Education is a teacher-first Laravel, Inertia, and React app for SNEd classes. DepEd LIS remains the official enrollment registry.

For local development, see [DOCKER_SETUP.md](DOCKER_SETUP.md). SMILE uses separate PHP and PostgreSQL Docker services, with the app on port 8001 and database-client access on port 5433.

Requirements and build order are in [PROJECT_SPEC.md](PROJECT_SPEC.md), agent instructions in [AGENTS.md](AGENTS.md), intended database design in [schema.sql](schema.sql), and current work in [PROGRESS.md](PROGRESS.md).

Public registration is disabled. The five required roles are defined, users have UUIDs, and schools and user records have scoped access. Administrator account provisioning and school setup screens are next; learner features and their required authorization tests have not been implemented. Use fake data only.
