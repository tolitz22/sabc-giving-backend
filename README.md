# Scripture Alone Baptist Church Online Giving API

Laravel 12 backend for SABC Online Giving. It supports bank transfer submissions with private proof uploads, PayMongo hosted checkout sessions for card/e-wallet giving, PayMongo webhooks, admin review, status tracking, and email receipts.

## Requirements

- PHP 8.2+
- Composer
- MySQL or PostgreSQL
- Queue worker for email jobs
- Private Cloudflare R2 bucket
- PayMongo account and webhook

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

The seeded admin defaults to `ADMIN_EMAIL=admin@sabc.local` and `ADMIN_PASSWORD=change-me`. Change these before production.

## Important Security Notes

This API never collects, processes, or stores raw card details. Card and e-wallet payments are created through PayMongo Hosted Checkout only. Proof files are uploaded directly to a private bucket, exposed to admins through temporary signed URLs only, and deleted after verification/rejection or by the expiry cleanup command.

## Environment

Set these in production:

```env
APP_NAME="SABC Giving"
APP_ENV=production
APP_DEBUG=false
APP_URL=
FRONTEND_URL=https://your-frontend-domain.com

LOG_CHANNEL=stack
LOG_STACK=stderr
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=
SESSION_DOMAIN=

PAYMONGO_PUBLIC_KEY=
PAYMONGO_SECRET_KEY=
PAYMONGO_WEBHOOK_SECRET=
PAYMONGO_API_BASE=https://api.paymongo.com/v2

FILESYSTEM_DISK=r2
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=
R2_ENDPOINT=
R2_REGION=auto

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=sabcpmzoom@gmail.com
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=sabcpmzoom@gmail.com
MAIL_FROM_NAME="Scripture Alone Baptist Church"
```

For Gmail SMTP, `MAIL_PASSWORD` must be a Google App Password, not the normal Gmail login password. Enable 2-Step Verification on the Gmail account, create an App Password for Mail, paste the generated 16-character password into Railway, then redeploy the web and worker services.

## API

Public:

- `POST /api/donations/bank-transfer/proof-upload`
- `POST /api/donations/bank-transfer`
- `POST /api/donations/checkout`
- `GET /api/donations/{uuid}`
- `POST /api/webhooks/paymongo`

Admin:

- `POST /api/admin/login`
- `POST /api/admin/logout`
- `GET /api/admin/me`
- `GET /api/admin/donations`
- `GET /api/admin/donations/{uuid}`
- `PATCH /api/admin/donations/{uuid}/verify`
- `PATCH /api/admin/donations/{uuid}/reject`
- `DELETE /api/admin/donations/{uuid}/proof`
- `GET /api/admin/stats`

Use the bearer token returned by `/api/admin/login` for admin routes.

### Donor-entered PayMongo checkout amount

For card or e-wallet giving, collect the donation amount in the frontend and create a hosted PayMongo checkout session through this API. Do not create fixed-amount dashboard links when the donor chooses the amount.

```http
POST /api/donations/checkout
Content-Type: application/json
```

```json
{
  "donor_name": "Maria Santos",
  "donor_email": "maria@example.com",
  "donor_mobile": "09171234567",
  "amount": 750,
  "category": "Tithes & Offering",
  "giving_method": "card",
  "is_anonymous": false,
  "note": "Sunday giving"
}
```

`amount` is a peso value with up to two decimal places and must be at least `20`. The API stores the donation as `pending`, sends the amount to PayMongo in centavos, and returns a `checkout_url`.

```json
{
  "uuid": "019...",
  "status": "pending",
  "checkout_url": "https://checkout.paymongo.com/..."
}
```

Redirect the donor to `checkout_url`. After payment, PayMongo calls `/api/webhooks/paymongo`; the webhook marks PayMongo donations as `awaiting_settlement`, stores available fee/net settlement values, and dispatches `SendDonationConfirmationJob`. This means the donor has paid, but the church bank settlement is not yet confirmed. Once the church bank settlement is confirmed in PayMongo/bank records, an admin can mark the donation paid:

```http
PATCH /api/admin/donations/{uuid}/settle
Authorization: Bearer {admin_token}
```

Admin donation responses show the net received amount as `amount` when PayMongo provides settlement details. The original donor-entered amount remains available as `gross_amount`, with `gateway_fee_amount`, `gateway_tax_amount`, and `gateway_net_amount` included for reconciliation.

## Railway

The included `Procfile` defines a web process and queue worker. On Railway, set the PHP runtime to 8.2+ and add the environment variables above. Run migrations with:

```bash
php artisan migrate --force
```

Run a worker process for queued email:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=90
```

## Monitoring

Production logs include structured monitoring events for security and operations. Keep `LOG_STACK=stderr` on Railway so these events appear in the service logs.

Watch for these event names:

```text
admin_login_failed
admin_login_succeeded
queue_job_failed
```

Failed admin logins are logged with the attempted email, IP address, user agent, and whether the account exists or is disabled. Failed queue jobs are logged with the queue connection, queue name, job id, job class, attempt count, exception class, and exception message.

For alerts, you can add Laravel's Slack log channel to the stack:

```env
LOG_CHANNEL=stack
LOG_STACK=stderr,slack
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/...
LOG_LEVEL=warning
```

## Cloudflare R2

Create a private bucket and set `FILESYSTEM_DISK=r2`. The app stores bank transfer proofs at:

```text
donations/{year}/{month}/{donation_uuid}/proof-of-transfer.ext
```

Keep the bucket private. The donor frontend first calls `POST /api/donations/bank-transfer/proof-upload`, uploads the file directly to the signed URL, then submits the returned object key to `POST /api/donations/bank-transfer`.

Admin detail responses include temporary signed proof URLs when supported by the R2/S3 adapter. Proof files are considered temporary verification evidence:

```bash
php artisan donations:delete-expired-proofs
```

Run the scheduler or this command daily in production. Verifying, rejecting, or calling `DELETE /api/admin/donations/{uuid}/proof` also deletes the stored proof file.

## PayMongo Webhook

Create a PayMongo webhook pointing to:

```text
https://your-api-domain.com/api/webhooks/paymongo
```

Subscribe to `checkout_session.payment.paid`. Set `PAYMONGO_WEBHOOK_SECRET` when available so signatures can be verified.

## Tests

```bash
php artisan test
```

This local workspace currently has PHP 8.1, which cannot run Laravel 11. Use PHP 8.2+ locally or in CI.
