# Teacher Account and Notification System

## Purpose

This extension adds richer teacher account details, centralized in-system notifications, email delivery tracking, unread counters, and a reusable notification service.

## Database

Run this migration on a fresh or existing database:

Use the repeatable setup/migration procedure in the [README](../README.md); it reads database credentials from the private environment.

The migration extends `tblusers` with teacher profile and email fields, extends `tblteachernotifications` with category, read status, related records, and action links, and adds:

- `tblnotificationdeliveries`
- `tblnotificationpreferences`

## Teacher Accounts

The Dean creates teacher accounts in `manage-teachers.php`.

The form now captures:

- First, middle, and last name
- Staff number
- Email address
- Phone number
- Username
- Role
- Department
- Password and confirm password
- Account status

Email is normalized to lowercase and validated on the backend and frontend. Passwords continue to use `password_hash()`.

## Notification Service

Reusable service file:

```text
includes/notification-service.php
```

Use `notification_create()` from other modules instead of inserting notification rows directly.

The service supports:

- In-system notifications
- Email delivery records
- Categories
- Related class, subject, and entity links
- Unread counts
- Mark-one and mark-all read behavior

## Email Queue

Email delivery records are stored in `tblnotificationdeliveries`. The main SRMS action is allowed to finish even when email sending is disabled or unavailable.

The queue processor is:

```bash
docker compose run --rm web php process-email-queue.php
```

Set email environment values in `.env` before enabling delivery:

- `MAIL_ENABLED`
- `MAIL_HOST`
- `MAIL_PORT`
- `MAIL_USERNAME`
- `MAIL_PASSWORD`
- `MAIL_ENCRYPTION`
- `MAIL_FROM_ADDRESS`
- `MAIL_FROM_NAME`

No real mail credentials should be committed to the repository.

## Teacher Experience

Teachers see a notification bell in the top navigation. The badge only appears when unread notifications exist.

Teachers can use:

- `teacher-notifications.php`
- `teacher-notification-count.php`

The notification centre supports filters, explicit mark-as-read actions, mark-all-as-read, and direct action links.

## Dean Experience

The Dean can review queued, sent, failed, skipped, and retrying delivery records in:

```text
dean-notification-deliveries.php
```

This page shows delivery status without exposing mail credentials.
