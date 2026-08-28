# AGENTS.md

## Project overview
This repository contains the Student Result Management System (SRMS), a PHP-based web application for managing students, classes, subjects, notices, and exam results. The application uses MySQL and is designed to run via Docker Compose.

## Primary stack
- Frontend: HTML, CSS, JavaScript, Bootstrap-based templates
- Backend: PHP
- Database: MySQL
- Containerization: Docker and Docker Compose

## Run locally
From the repository root:

```bash
docker compose up --build
```

Then open:

```text
http://localhost:5000
```

## Important repository files
- `index.php` — landing page entry point
- `dashboard.php` — main dashboard
- `includes/config.php` — database and application configuration
- `add-students.php`, `manage-students.php`, `edit-student.php` — student CRUD flow
- `create-class.php`, `manage-classes.php`, `edit-class.php` — class management
- `create-subject.php`, `manage-subjects.php`, `edit-subject.php` — subject management
- `add-result.php`, `manage-results.php`, `edit-result.php` — result management
- `app.py` — app helper or supporting script if present
- `srms.sql` — database schema seed

## Coding guidance for agents
- Preserve the existing PHP structure and naming conventions.
- Keep database access compatible with the current MySQL setup in `includes/config.php`.
- Prefer small, direct changes over broad refactors.
- Validate PHP syntax on edited files when possible with `php -l <file>`.
- If database schema changes are required, update the relevant SQL scripts and document them clearly.
- Maintain compatibility with the current Bootstrap-style UI and server-side PHP flow.
- Avoid introducing frameworks or major architectural shifts unless the task explicitly requires them.

## Testing and validation
Use the smallest relevant validation command:

```bash
php -l add-students.php
php -l dashboard.php
```

For full local verification:

```bash
docker compose up --build
```

## Notes for future contributors
This project is a straightforward PHP application, not a modern MVC framework. Most business logic is embedded in page-level PHP files and includes, so be careful to preserve existing session, database, and redirect patterns.
