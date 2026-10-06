# Deploy FUL Move to Render with Aiven MySQL

The root `Dockerfile` builds production Composer dependencies and Vite assets, then
serves Laravel through PHP 8.3 and Apache. Only `public/` is exposed. Node, Composer,
local `.env` files, logs, cached configuration and demo databases stay out of the
runtime image. Production configuration is cached when the container starts.

## 1. Create the database

Create an **Aiven for MySQL Free** service. Record its host, port, database,
username and password, and download its **CA certificate**. Use the port Aiven
shows, which may differ from 3306. Use a fresh database for this deployment.

## 2. Build and generate an application key

With Docker Desktop running in Linux container mode, run from the project root:

```sh
docker build -t ful-move:render .
docker run --rm ful-move:render php artisan key:generate --show
```

Save the generated `base64:...` value as `APP_KEY` in Render. Generate it once and
keep it across deployments. The command prints a new key without changing your
local `.env`. It does not need a database connection.

## 3. Configure Render

Push the project to a GitHub repository. The supplied `.gitignore` excludes local
secrets, dependencies and the `tmp/` demo database. This folder must be initialized
as a Git repository first if it is not already one.

In Render, choose **New > Web Service**, connect the repository, and set:

| Setting | Value |
| --- | --- |
| Language | Docker |
| Instance type | Free |
| Dockerfile path | `./Dockerfile` |
| Docker build context | `.` |
| Docker command | Leave blank; use the image default |
| Health check path | `/up` |

Add these environment variables. Replace all placeholders, including the public
URL with the actual address Render assigns to your service.

```dotenv
APP_NAME="FUL Move"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:YOUR_GENERATED_KEY
APP_URL=https://YOUR_SERVICE.onrender.com
TRUSTED_PROXIES=*
RUN_MIGRATIONS=true
DB_CONNECTION=mysql
DB_HOST=YOUR_AIVEN_HOST
DB_PORT=YOUR_AIVEN_PORT
DB_DATABASE=YOUR_AIVEN_DATABASE
DB_USERNAME=YOUR_AIVEN_USERNAME
DB_PASSWORD=YOUR_AIVEN_PASSWORD
MYSQL_ATTR_SSL_CA=/etc/secrets/aiven-ca.pem
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
QUEUE_CONNECTION=sync
LOG_CHANNEL=stderr
```

Under **Environment > Secret Files**, add a file named **`aiven-ca.pem`** containing
the downloaded CA certificate, including its BEGIN/END lines. Redeploy after both
the certificate and environment variables are saved. Startup deliberately fails
if the configured certificate is missing. Do not disable certificate verification.

Alternatively, **New > Blueprint** can load the included `render.yaml`. It defines
one free web service and prompts for the values marked `sync: false`. Add the CA
secret file manually, then redeploy; the Blueprint does not provision Aiven or
upload its certificate. There is no paid Render database in this configuration.

The container listens on `0.0.0.0:$PORT` (default 10000). Render terminates HTTPS;
`TRUSTED_PROXIES=*` lets Laravel generate HTTPS links behind that trusted proxy.
Leave this variable unset when serving directly without a trusted reverse proxy.

`RUN_MIGRATIONS=true` runs `php artisan migrate --force` before Apache starts and
stops startup if migration fails. This suits one free demo instance. It never runs
`migrate:fresh`, demo seeding or sample boarding. Set it to `false` if you manage
migrations separately. Back up data before deploying schema changes.

## 4. Create the first administrator

Free Render services do not provide a shell. Use the same image locally to run the
existing interactive command against Aiven. Create a private `.env.render-admin`
file containing the production environment values above, but set
`MYSQL_ATTR_SSL_CA=/etc/secrets/aiven-ca.pem` and `RUN_MIGRATIONS=false`.

Docker `--env-file` values should be unquoted (for example, `APP_NAME=FUL Move`).
The filename is ignored by Git and Docker. Keep the downloaded certificate
outside the repository or in the ignored `tmp/` folder.

Run this in PowerShell, replacing the absolute certificate path:

```powershell
docker run --rm -it --env-file .env.render-admin --mount "type=bind,source=C:\path\to\aiven-ca.pem,target=/etc/secrets/aiven-ca.pem,readonly" ful-move:render php artisan app:create-admin
```

Enter your name, email and a unique password of at least 12 characters. This
command bypasses web startup and does not rerun migrations. If you disabled
automatic migrations, first run the same Docker command with
`php artisan migrate --force` instead of `php artisan app:create-admin`.

Sign in on the deployed site and add terminals and fares. Drivers can then
register their buses for approval. Do not upload the local demo database or reuse
the shared demo passwords.

## Verify and operate

- Open `/up`, the homepage and `/login`. `/up` checks Laravel startup, not database
  connectivity; verify registration/login and the board to check the database.
- Confirm the CSS/JS load over HTTPS and sign-in stays logged in between requests.
- Check Render logs for migration or certificate errors if startup fails.
- Use Aiven for durable data. Render's free container filesystem is temporary;
  local uploads and file cache are lost on restart. Sessions use MySQL. File-based
  rate-limit counters also reset on restart. The current app needs no file uploads,
  queue worker or websocket service.
- Render free services sleep after 15 minutes without requests and take about a
  minute to wake. Aiven's free plan has 1 GB storage and can pause inactive services.
  These plans suit a coursework demo; real transport operations need reliable hosting.

References: [Render Docker](https://render.com/docs/docker),
[Render ports and HTTPS](https://render.com/docs/web-services),
[Render secrets](https://render.com/docs/configure-environment-variables),
[Render free limits](https://render.com/docs/free),
[Aiven free MySQL](https://aiven.io/docs/products/mysql/concepts/mysql-free-tier).
