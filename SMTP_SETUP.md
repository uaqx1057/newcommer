# SMTP Setup for Contact Form

`contact-submit.php` sends branded admin and customer emails using SMTP credentials from environment variables or `smtp-config.php`.

## Required Environment Variables

- `SMTP_PASSWORD` (SMTP password for your mailbox)

If your hosting panel does not expose env vars to PHP, create `smtp-config.php` in the same folder as `contact-submit.php`.

Example `smtp-config.php`:

```php
<?php
return [
	'host' => 'mail.newcomerconnect.ca',
	'port' => 587,
	'encryption' => 'tls',
	'username' => 'info@newcomerconnect.ca',
	'password' => 'use-the-email-account-password',
	'from_email' => 'info@newcomerconnect.ca',
	'from_name' => 'Newcomer Connect',
	'helo_host' => 'newcomerconnect.ca'
];
```

## Optional Environment Variables

- `MAIL_FROM_NAME` (default: `Newcomer Connect`)
- `ADMIN_EMAIL` (default in code: `info@newcomerconnect.ca`)
- `SUPPORT_EMAIL` (default in code: `info@newcomerconnect.ca`)
- `SITE_URL` (default: `https://newcomerconnect.ca`)
- `SMTP_HELO_HOST` (default: `localhost`)
- `BOOKING_REMINDER_TOKEN` (optional token for secure HTTP access to `booking-reminders.php` when no admin session is present)

## Current Server Defaults in Code

- `SMTP_HOST=mail.newcomerconnect.ca`
- `SMTP_PORT=587`
- `SMTP_ENCRYPTION=tls`
- `SMTP_USERNAME=info@newcomerconnect.ca`
- `MAIL_FROM_EMAIL=info@newcomerconnect.ca`
- `SMTP_HELO_HOST=newcomerconnect.ca`
- `ADMIN_EMAIL=info@newcomerconnect.ca`

You still must set:

- `SMTP_PASSWORD` or the same password inside `smtp-config.php`

## Email Behavior

- Admin inbox target defaults to `info@newcomerconnect.ca`.
- Submitters receive an automatic branded confirmation email.
- Consultation bookings are stored in `data/bookings.json` for the admin panel and reminder workflow.
- Booking reminder emails can be sent one day before and about one hour before the scheduled consultation time.
- Subjects adapt using hidden form metadata such as `submission_type`, `submission_label`, and page context.
- Email templates use the website logo, Canada imagery, brand colors, and decorative maple-leaf animation with graceful fallback on clients that do not support CSS animation.

## Booking Reminder Cron

- Manual admin trigger: `admin-bookings.php` via the `Run Due Reminders` button.
- CLI trigger: `php booking-reminders.php`
- HTTP trigger for hosting cron: `https://your-domain.com/booking-reminders.php?token=YOUR_BOOKING_REMINDER_TOKEN`
- Recommended frequency: every 5 to 10 minutes.
- Reminder windows in code:
	- day-before reminder: approximately 23 to 25 hours before the consultation
	- one-hour reminder: approximately 45 to 75 minutes before the consultation

## Optional Alternative (TLS 587)

If your host prefers STARTTLS instead of SSL:

- `SMTP_HOST=mail.newcomerconnect.ca`
- `SMTP_PORT=587`
- `SMTP_ENCRYPTION=tls`
- `SMTP_USERNAME=info@newcomerconnect.ca`
- `MAIL_FROM_EMAIL=info@newcomerconnect.ca`

## Gmail Example

- `SMTP_HOST=smtp.gmail.com`
- `SMTP_PORT=587`
- `SMTP_ENCRYPTION=tls`
- `SMTP_USERNAME=yourgmail@gmail.com`
- `SMTP_PASSWORD=your-gmail-app-password`
- `MAIL_FROM_EMAIL=yourgmail@gmail.com`
- `ADMIN_EMAIL=info@newcomerconnect.ca`

## Troubleshooting

- Submit the contact form once and check `mail-debug.log` in project root.
- Verify `admin_sent` and `customer_sent` flags in the log.
- If both are false, confirm SMTP credentials and host firewall permissions.
- If admin is true and customer is false, customer domain may reject or spam-filter.
