# Copilot instructions for SRMS

This repository is a PHP + MySQL student result management system. Keep changes compatible with the existing server-side PHP structure and the Docker-based setup.

## How to run
```bash
docker compose up --build
```

## Project conventions
- Use the existing PHP file layout and naming conventions.
- Prefer direct, minimal code changes.
- Preserve the current Bootstrap-based UI patterns.
- Keep database interactions compatible with MySQL and the current config in `includes/config.php`.
- Update SQL definitions when schema changes are needed.

## Validation
Run the smallest relevant syntax check after edits:
```bash
php -l <edited-file.php>
```

## Important files
- `includes/config.php`
- `dashboard.php`
- `manage-students.php`
- `manage-results.php`
- `srms.sql`

## Avoid
- Large framework migrations
- Broad refactors without need
- Breaking session, redirect, or authentication flows
- Unnecessary database abstraction layers
