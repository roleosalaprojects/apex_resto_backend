# Scheduled Reports — Production Setup

## Overview

The system sends automated sales reports via email to configured recipients:
- **Daily reports** — sent every day at 8:00 AM
- **Weekly reports** — sent every Monday at 8:00 AM

Reports include sales summary, top items, peak hours, and margin alerts.

---

## 1. Cron Job (Required)

Laravel's scheduler needs a single cron entry on the server. Add this to the server's crontab:

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

For Sail/Docker, run inside the container:

```bash
* * * * * cd /path-to-project && vendor/bin/sail artisan schedule:run >> /dev/null 2>&1
```

This is the **only** cron entry needed. Laravel handles the rest via `routes/console.php`:

| Schedule | Command |
|----------|---------|
| Daily at 8:00 AM | `report:generate --type=daily` |
| Weekly Monday 8:00 AM | `report:generate --type=weekly` |

---

## 2. Mail Configuration

Set the following in `.env`:

```env
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.mailersend.net
MAIL_PORT=2525
MAIL_USERNAME=<your-mailersend-username>
MAIL_PASSWORD=<your-mailersend-password>
MAIL_FROM_ADDRESS=<verified-sender@yourdomain.com>
MAIL_FROM_NAME="${APP_NAME}"
```

**Important:**
- `MAIL_SCHEME=null` — Laravel 12 does not use `tls`/`ssl`, only `smtp`, `smtps`, or `null`
- The `MAIL_FROM_ADDRESS` must be a verified sender in your MailerSend account
- Reports use `sendNow()` to bypass the queue and send immediately

---

## 3. Add Recipients

### Via Admin Panel

Navigate to **Reports > Scheduled Reports** in the admin sidebar to add, view, or remove recipients.

### Via Artisan Tinker

```bash
php artisan tinker
```

```php
App\Models\ReportRecipient::create([
    'user_id' => 1,          // The account whose data to report on
    'email' => 'manager@example.com',
    'report_type' => 'both', // 'daily', 'weekly', or 'both'
    'is_active' => true,
]);
```

### Via API

```
POST /api/v1/reports/recipients
Authorization: Bearer <token>

{
    "email": "manager@example.com",
    "report_type": "both",
    "is_active": true
}
```

---

## 4. Manual Send / Testing

Send a daily report to a specific email:

```bash
php artisan report:generate --type=daily --user=1 --email=test@example.com
```

Send a weekly report:

```bash
php artisan report:generate --type=weekly --user=1 --email=test@example.com
```

Send to all active recipients:

```bash
php artisan report:generate --type=daily
```

---

## 5. Verify It Works

1. Add at least one recipient (step 3)
2. Run a manual test (step 4)
3. Check inbox for the email from "Apex Backend"
4. Confirm the cron job is running: `php artisan schedule:list`

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| No email received | Check `MAIL_MAILER` is `smtp`, not `log` |
| Email in spam | Verify sender domain DNS (SPF, DKIM) in MailerSend |
| "No active recipients" | Ensure `is_active = true` and `report_type` matches |
| Schedule not running | Verify cron entry exists: `crontab -l` |
| Emails queued but not sent | Reports use `sendNow()` — this should not happen. Check logs if it does |
