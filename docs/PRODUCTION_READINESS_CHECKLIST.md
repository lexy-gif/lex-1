# Senior School deployment checklist

The current implementation and executed checks are recorded in [SENIOR_SYSTEM_AUDIT.md](SENIOR_SYSTEM_AUDIT.md). This replaces the former lower-grade/default-account setup checklist.

## Repository and installation

- [x] Restrict new academic configuration/admissions to Grades 10, 11 and 12.
- [x] Replace public result lookup with authenticated, explicitly linked guardians.
- [x] Require submission, class review and Dean approval before publication.
- [x] Queue parent notifications on publication and protect against repeated sends.
- [x] Provide schema-only fresh installation and repeatable, data-preserving migrations.
- [x] Require private database/provider configuration; no default Dean password.
- [x] Separate production Compose from development source mounts and database administration ports.
- [x] Pass isolated production MySQL 8.4 installation, secure cookies, health, assets and anonymous-route checks.
- [x] Disable public diagnostics, directory indexes, source/config downloads and web execution of CLI helpers.

## School go-live requirements

- [ ] Set school identity, HTTPS APP_URL, private database passwords and appropriate access to the host.
- [ ] Put TLS termination in front of the loopback web port and verify secure-cookie login through the real domain.
- [ ] Review pathways, subject policy, individual registrations, teachers and school-approved performance bands.
- [ ] Verify guardian identities and child links, temporary-password handover and SMS contact preferences.
- [ ] Test Africa's Talking with an authorized recipient; reconcile provider acceptance with actual delivery.
- [ ] Enable/supervise the SMS worker; test SMTP separately if teacher email is enabled.
- [ ] Configure encrypted off-host backups, retention, restore rehearsal and health/error monitoring.
- [ ] Record the deployed image version, migration result and rollback/recovery procedure.
- [ ] Review retained Bootstrap 3 compatibility and plan its eventual replacement; it is an end-of-life dependency.

Use the exact local and production commands in the [README](../README.md). Never import the fresh schema over an existing school database or remove its volume to fix a startup problem.
