# Deploy a Jenkins image locally

Jenkins checks the code, builds an image and pushes it to Docker Hub. Deployment
starts that published image with its environment settings and a database. This
exercise performs deployment manually before adding it to the pipeline.

```text
GitHub push -> Jenkins tests/build -> Docker Hub image -> local staging
```

The standalone `docker-compose.staging.yml` uses project `srms-staging`, its own
network and database volume, and loopback port 5001. The existing development
stack uses port 5000. Compose isolates resources by
[project name](https://docs.docker.com/compose/how-tos/project-name/).

The application runs entirely from the selected image. A short-lived `schema`
container copies `srms.sql` from that same image into a staging volume. MySQL
imports it only when its data volume is empty. The database becomes healthy
before the web container starts. SMS and email are disabled in this environment.

## 1. Select the image and create private settings

Use the numbered tag shown in a successful Jenkins build's Docker Push output,
such as `alexistechiz/srms:16`. That tag identifies the Jenkins build, so check
that build's commit when comparing it with your working tree. A digest reference
such as `alexistechiz/srms@sha256:...` fixes the exact image even if a tag changes.

From the repository root, create `.env.staging` only if it does not already exist:

```powershell
if (-not (Test-Path -LiteralPath .env.staging)) {
    Copy-Item -LiteralPath .env.staging.example -Destination .env.staging
}
```

Edit `.env.staging`: set `SRMS_STAGING_IMAGE` to the published tag or digest, and
replace both password placeholders with distinct strong passwords. This file is
ignored by Git. Staging uses its own settings rather than the development `.env`.

Define a PowerShell argument array to keep every command on the same project:

```powershell
$stagingCompose = @('compose', '-p', 'srms-staging', '--env-file', '.env.staging', '-f', 'docker-compose.staging.yml')
```

## 2. Download and start

Start Docker Desktop with its Linux engine. Validate the configuration without
printing passwords, then download the images:

```powershell
docker @stagingCompose config --quiet
docker @stagingCompose pull
```

[`pull`](https://docs.docker.com/reference/cli/docker/compose/pull/) downloads
images without starting containers. The next command starts the stack and waits
for its health checks; `--no-build` uses the published application image:

```powershell
docker @stagingCompose up -d --no-build --wait --wait-timeout 240
docker @stagingCompose ps -a
Invoke-RestMethod http://127.0.0.1:5001/health.php
```

Expect `db` and `web` to be healthy, `schema` to have exited with code 0, and the
HTTP response to contain `status: ok`. See Docker's
[`up` reference](https://docs.docker.com/reference/cli/docker/compose/up/).

Open [the staging staff login](http://127.0.0.1:5001/staff/login). Use
`127.0.0.1` for staging and `localhost` for development to keep their browser
session cookies separate; cookies are not isolated by port. Local staging uses
HTTP, so its secure-cookie setting is false. Production remains an HTTPS setup.

## 3. Create the first staging account

A fresh database has no default login. Choose credentials for this practice
environment. The following command passes them to the existing setup script,
which initializes the academic configuration and creates the first account:

```powershell
$env:SETUP_DEAN_USERNAME = Read-Host 'Staging Dean username'
$env:SETUP_DEAN_PASSWORD = [System.Net.NetworkCredential]::new('', (Read-Host 'Staging Dean password (12-72 characters)' -AsSecureString)).Password
$env:SETUP_STAFF_ROLE = 'administrator'
try {
    docker @stagingCompose exec -T -e SETUP_DEAN_USERNAME -e SETUP_DEAN_PASSWORD -e SETUP_STAFF_ROLE web php scripts/setup.php
    if ($LASTEXITCODE -ne 0) { throw 'Staging account setup failed.' }
} finally {
    Remove-Item Env:SETUP_DEAN_USERNAME, Env:SETUP_DEAN_PASSWORD, Env:SETUP_STAFF_ROLE -ErrorAction SilentlyContinue
}
```

`SETUP_STAFF_ROLE` explicitly authorizes the initial administrator on releases
that require it. Setup does not replace an existing account. Sign in through
**Dean of Studies Login** and use synthetic data for this exercise.

## Inspect and stop

If startup fails, inspect the staging services:

```powershell
docker @stagingCompose ps -a
docker @stagingCompose logs --tail 60 schema db web
```

When finished, stop this practice stack to free laptop memory:

```powershell
docker @stagingCompose stop
```

The database volume retains the practice records. Run the `up` command again to
resume. Changing passwords in `.env.staging` does not change database users in an
already initialized volume.

## Subsequent releases

Update `SRMS_STAGING_IMAGE` to the next successful Jenkins image, then repeat
`pull` and `up`. Editing local PHP files does not change the deployed image.
Review the release's migration requirements first: the seed is imported only
into an empty database, and an existing account makes `scripts/setup.php` exit
early. Updates therefore need the release's explicit migration commands. An
image rollback does not undo database migrations.

Once manual deployment and login work, the same deployment and health checks
can become a Jenkins stage.

## Verified on 20 September 2026

The initial local deployment used published build `16`, pinned to
`alexistechiz/srms@sha256:cd32861773cc9c525afb2e7974b8f52a80ae9a75717bc7f771f4447f8540a16b`.
This image predates the latest dashboard changes in the working tree.

Compose validation passed. A fresh staging database initialized from the image's
schema; `db` and `web` became healthy and `schema` exited successfully. The health
endpoint returned `status: ok`; staff, Dean and guardian login pages returned
HTTP 200. `/.env.staging`, `/srms.sql` and `/scripts/setup.php` returned HTTP 403.
The private `.env.staging` was created with unique generated database passwords.

The stack was left running on `127.0.0.1:5001`. The setup script packaged in
build `16` subsequently completed its migrations and created the first practice
Dean account, `staging_dean`. Its generated password is stored locally in
`.env.staging-account` at the repository root; Git ignores that file. Open the
file locally to retrieve the password, then use **Dean of Studies Login**.

Validation covered a successful CSRF-protected sign-in, HTTP 200 from the
authenticated dashboard, session persistence on a second dashboard request,
and `status: ok` from the health endpoint after setup. The next learning step is
adding deployment and health checks to Jenkins.
