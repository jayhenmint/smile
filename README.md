# SMILE

Student Monitoring for Inclusive Learning Education is a teacher-first Laravel, Inertia, and React app for SNEd classes. DepEd LIS remains the official enrollment registry.

For local development, see [DOCKER_SETUP.md](DOCKER_SETUP.md). SMILE uses separate PHP and PostgreSQL Docker services, with the app on port 8001 and database-client access on port 5433.

Requirements and build order are in [PROJECT_SPEC.md](PROJECT_SPEC.md), agent instructions in [AGENTS.md](AGENTS.md), intended database design in [schema.sql](schema.sql), and current work in [PROGRESS.md](PROGRESS.md).

This is currently the starter scaffold. Public registration still needs to be disabled, and learner features and authorization tests have not been implemented. Use fake data only.
