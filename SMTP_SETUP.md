# SMTP Setup for Contact Form

`contact-submit.php` now sends emails using SMTP credentials from environment variables.

## Required Environment Variables

- `SMTP_PASSWORD` (SMTP password for your mailbox)

If your hosting panel does not expose env vars to PHP, create `smtp-config.php` in the same folder as `contact-submit.php`.

Example `smtp-config.php`:

```php
<?php
return [
	'host' => 'codewithusman.com',
	'port' => 465,
	'encryption' => 'ssl',
	'username' => 'info@codewithusman.com',
	'password' => 'your-mail-password',
	'from_email' => 'info@codewithusman.com',
	'from_name' => 'Newcomer Connect',
	'helo_host' => 'codewithusman.com'
];
```

## Optional Environment Variables

- `MAIL_FROM_NAME` (default: `Newcomer Connect`)
- `ADMIN_EMAIL` (default in code: `uaqx1057@gmail.com`)
- `SUPPORT_EMAIL` (default in code: `info@codewithusman.com`)
- `SMTP_HELO_HOST` (default: `localhost`)

## Current Server Defaults in Code

- `SMTP_HOST=codewithusman.com`
- `SMTP_PORT=465`
- `SMTP_ENCRYPTION=ssl`
- `SMTP_USERNAME=info@codewithusman.com`
- `MAIL_FROM_EMAIL=info@codewithusman.com`
- `SMTP_HELO_HOST=codewithusman.com`
- `ADMIN_EMAIL=uaqx1057@gmail.com`

You still must set:

- `SMTP_PASSWORD`

## Optional Alternative (TLS 587)

If your host prefers STARTTLS instead of SSL:

- `SMTP_HOST=mail.codewithusman.com`
- `SMTP_PORT=587`
- `SMTP_ENCRYPTION=tls`
- `SMTP_USERNAME=info@codewithusman.com`
- `MAIL_FROM_EMAIL=info@codewithusman.com`

## Gmail Example

- `SMTP_HOST=smtp.gmail.com`
- `SMTP_PORT=587`
- `SMTP_ENCRYPTION=tls`
- `SMTP_USERNAME=yourgmail@gmail.com`
- `SMTP_PASSWORD=your-gmail-app-password`
- `MAIL_FROM_EMAIL=yourgmail@gmail.com`
- `ADMIN_EMAIL=uaqx1057@gmail.com`

## Troubleshooting

- Submit the contact form once and check `mail-debug.log` in project root.
- Verify `admin_sent` and `customer_sent` flags in the log.
- If both are false, confirm SMTP credentials and host firewall permissions.
- If admin is true and customer is false, customer domain may reject or spam-filter.
