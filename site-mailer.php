<?php

function nc_clean_input($value) {
    $value = is_string($value) ? trim($value) : '';
    $value = strip_tags($value);
    return $value;
}

function nc_esc_html($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function nc_get_env_value($key, $default = '') {
    $value = getenv($key);
    if ($value === false || trim((string)$value) === '') {
        if (isset($_ENV[$key]) && trim((string)$_ENV[$key]) !== '') {
            $value = $_ENV[$key];
        } elseif (isset($_SERVER[$key]) && trim((string)$_SERVER[$key]) !== '') {
            $value = $_SERVER[$key];
        } else {
            return $default;
        }
    }

    $value = trim((string)$value);
    return $value === '' ? $default : $value;
}

function nc_load_local_smtp_config() {
    $configPath = __DIR__ . DIRECTORY_SEPARATOR . 'smtp-config.php';
    if (!is_file($configPath)) {
        return [];
    }

    $data = include $configPath;
    return is_array($data) ? $data : [];
}

function nc_append_mail_log($requestId, $payload) {
    $logFile = __DIR__ . DIRECTORY_SEPARATOR . 'mail-debug.log';
    $line = '[' . date('Y-m-d H:i:s') . '] request=' . $requestId . ' ' . json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND);
}

function nc_smtp_read_response($socket) {
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (preg_match('/^\d{3}\s/', $line)) {
            break;
        }
    }
    return trim($response);
}

function nc_smtp_response_matches($response, $expectedCodes) {
    foreach ($expectedCodes as $code) {
        if (substr($response, 0, 3) === (string)$code) {
            return true;
        }
    }
    return false;
}

function nc_smtp_send_command($socket, $command, $expectedCodes, &$lastError) {
    if ($command !== null) {
        fwrite($socket, $command . "\r\n");
    }

    $response = nc_smtp_read_response($socket);
    if (!nc_smtp_response_matches($response, $expectedCodes)) {
        $lastError = 'SMTP error after command [' . ($command ?? 'CONNECT') . ']: ' . $response;
        return false;
    }

    return true;
}

function nc_prepare_html_email_body($htmlBody) {
    $htmlBody = (string) $htmlBody;
    $htmlBody = preg_replace("/\r\n|\r|\n/", "\r\n", $htmlBody);
    return nc_encode_base64_lines($htmlBody);
}

function nc_encode_base64_lines($content) {
    $encoded = base64_encode((string) $content);
    return rtrim(chunk_split($encoded, 76, "\r\n"));
}

function nc_get_default_email_logo_path() {
    return __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'icons' . DIRECTORY_SEPARATOR . 'Logo-real-dark.png';
}

function nc_get_default_email_logo_url() {
    return 'https://ncc.codewithusman.com/assets/icons/Logo-real-dark.png';
}

function nc_detect_mime_type_from_path($path) {
    $path = (string) $path;
    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($path);
        if (is_string($mime) && trim($mime) !== '') {
            return $mime;
        }
    }

    $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    $map = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml'
    ];

    return $map[$extension] ?? 'application/octet-stream';
}

function nc_get_default_inline_attachments($htmlBody) {
    $attachments = [];

    if (strpos((string) $htmlBody, 'cid:nc-email-logo') !== false) {
        $logoPath = nc_get_default_email_logo_path();
        if (is_file($logoPath)) {
            $attachments[] = [
                'cid' => 'nc-email-logo',
                'path' => $logoPath,
                'filename' => basename($logoPath),
                'mime' => nc_detect_mime_type_from_path($logoPath)
            ];
        }
    }

    return $attachments;
}

function nc_get_email_logo_src($fallbackUrl = '') {
    $fallbackUrl = trim((string) $fallbackUrl);
    if ($fallbackUrl === '') {
        $fallbackUrl = nc_get_default_email_logo_url();
    }

    return is_file(nc_get_default_email_logo_path()) ? 'cid:nc-email-logo' : $fallbackUrl;
}

function nc_build_multipart_related_body($htmlBody, $attachments, &$headers) {
    if (empty($attachments)) {
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: base64';
        return nc_prepare_html_email_body($htmlBody);
    }

    try {
        $boundary = '=_nc_' . bin2hex(random_bytes(8));
    } catch (Exception $error) {
        $boundary = '=_nc_' . sha1((string) mt_rand());
    }

    $headers[] = 'Content-Type: multipart/related; boundary="' . $boundary . '"';
    $parts = [];
    $parts[] = '--' . $boundary;
    $parts[] = 'Content-Type: text/html; charset=UTF-8';
    $parts[] = 'Content-Transfer-Encoding: base64';
    $parts[] = '';
    $parts[] = nc_prepare_html_email_body($htmlBody);

    foreach ($attachments as $attachment) {
        $path = (string) ($attachment['path'] ?? '');
        $cid = trim((string) ($attachment['cid'] ?? ''));
        if ($path === '' || $cid === '' || !is_file($path)) {
            continue;
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            continue;
        }

        $filename = nc_esc_html($attachment['filename'] ?? basename($path));
        $mime = nc_esc_html($attachment['mime'] ?? nc_detect_mime_type_from_path($path));

        $parts[] = '--' . $boundary;
        $parts[] = 'Content-Type: ' . $mime . '; name="' . $filename . '"';
        $parts[] = 'Content-Transfer-Encoding: base64';
        $parts[] = 'Content-ID: <' . $cid . '>';
        $parts[] = 'Content-Disposition: inline; filename="' . $filename . '"';
        $parts[] = '';
        $parts[] = nc_encode_base64_lines($content);
    }

    $parts[] = '--' . $boundary . '--';
    return implode("\r\n", $parts);
}

function nc_get_smtp_config() {
    $fileSmtpConfig = nc_load_local_smtp_config();

    return [
        'host' => nc_get_env_value('SMTP_HOST', $fileSmtpConfig['host'] ?? 'mail.newcomerconnect.ca'),
        'port' => (int) nc_get_env_value('SMTP_PORT', (string) ($fileSmtpConfig['port'] ?? '587')),
        'encryption' => strtolower(nc_get_env_value('SMTP_ENCRYPTION', $fileSmtpConfig['encryption'] ?? 'tls')),
        'username' => nc_get_env_value('SMTP_USERNAME', $fileSmtpConfig['username'] ?? 'info@newcomerconnect.ca'),
        'password' => nc_get_env_value('SMTP_PASSWORD', $fileSmtpConfig['password'] ?? ''),
        'from_email' => nc_get_env_value('MAIL_FROM_EMAIL', $fileSmtpConfig['from_email'] ?? 'info@newcomerconnect.ca'),
        'from_name' => nc_get_env_value('MAIL_FROM_NAME', $fileSmtpConfig['from_name'] ?? 'Newcomer Connect'),
        'helo_host' => nc_get_env_value('SMTP_HELO_HOST', $fileSmtpConfig['helo_host'] ?? 'newcomerconnect.ca')
    ];
}

function nc_validate_smtp_config($smtpConfig) {
    $missingConfig = [];

    if (($smtpConfig['host'] ?? '') === '') {
        $missingConfig[] = 'SMTP_HOST';
    }
    if ((int) ($smtpConfig['port'] ?? 0) <= 0) {
        $missingConfig[] = 'SMTP_PORT';
    }
    if (($smtpConfig['from_email'] ?? '') === '') {
        $missingConfig[] = 'MAIL_FROM_EMAIL';
    }
    if (($smtpConfig['username'] ?? '') === '') {
        $missingConfig[] = 'SMTP_USERNAME';
    }
    if (($smtpConfig['password'] ?? '') === '') {
        $missingConfig[] = 'SMTP_PASSWORD';
    }

    return $missingConfig;
}

function nc_send_html_email($smtpConfig, $to, $subject, $htmlBody, $replyToEmail = '') {
    $to = trim((string) $to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['sent' => false, 'error' => 'Invalid recipient email address.'];
    }

    $host = (string) ($smtpConfig['host'] ?? '');
    $port = (int) ($smtpConfig['port'] ?? 0);
    $encryption = strtolower((string) ($smtpConfig['encryption'] ?? 'ssl'));
    $username = (string) ($smtpConfig['username'] ?? '');
    $password = (string) ($smtpConfig['password'] ?? '');
    $fromEmail = (string) ($smtpConfig['from_email'] ?? '');
    $fromName = (string) ($smtpConfig['from_name'] ?? 'Newcomer Connect');
    $heloHost = (string) ($smtpConfig['helo_host'] ?? 'newcomerconnect.ca');
    $replyToHeader = filter_var($replyToEmail, FILTER_VALIDATE_EMAIL) ? $replyToEmail : $fromEmail;

    $remoteHost = $encryption === 'ssl' ? ('ssl://' . $host) : $host;
    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($remoteHost, $port, $errno, $errstr, 20);
    if (!$socket) {
        return ['sent' => false, 'error' => 'SMTP connect failed: ' . $errstr . ' (' . $errno . ')'];
    }

    stream_set_timeout($socket, 20);
    $lastError = null;

    if (!nc_smtp_send_command($socket, null, [220], $lastError)) {
        fclose($socket);
        return ['sent' => false, 'error' => $lastError];
    }

    if (!nc_smtp_send_command($socket, 'EHLO ' . $heloHost, [250], $lastError)) {
        fclose($socket);
        return ['sent' => false, 'error' => $lastError];
    }

    if ($encryption === 'tls') {
        if (!nc_smtp_send_command($socket, 'STARTTLS', [220], $lastError)) {
            fclose($socket);
            return ['sent' => false, 'error' => $lastError];
        }

        $tlsEnabled = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if ($tlsEnabled !== true) {
            fclose($socket);
            return ['sent' => false, 'error' => 'Failed to enable TLS encryption.'];
        }

        if (!nc_smtp_send_command($socket, 'EHLO ' . $heloHost, [250], $lastError)) {
            fclose($socket);
            return ['sent' => false, 'error' => $lastError];
        }
    }

    if ($username !== '' || $password !== '') {
        if (!nc_smtp_send_command($socket, 'AUTH LOGIN', [334], $lastError)
            || !nc_smtp_send_command($socket, base64_encode($username), [334], $lastError)
            || !nc_smtp_send_command($socket, base64_encode($password), [235], $lastError)) {
            fclose($socket);
            return ['sent' => false, 'error' => $lastError];
        }
    }

    if (!nc_smtp_send_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250], $lastError)
        || !nc_smtp_send_command($socket, 'RCPT TO:<' . $to . '>', [250, 251], $lastError)
        || !nc_smtp_send_command($socket, 'DATA', [354], $lastError)) {
        fclose($socket);
        return ['sent' => false, 'error' => $lastError];
    }

    $attachments = nc_get_default_inline_attachments($htmlBody);
    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'To: <' . $to . '>',
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'MIME-Version: 1.0',
        'Reply-To: ' . $replyToHeader,
        'X-Mailer: NewcomerConnectSMTP'
    ];

    $bodyPayload = nc_build_multipart_related_body($htmlBody, $attachments, $headers);
    $rawMessage = implode("\r\n", $headers) . "\r\n\r\n" . $bodyPayload;
    $rawMessage = str_replace("\r\n.", "\r\n..", $rawMessage);
    fwrite($socket, $rawMessage . "\r\n.\r\n");

    $dataResponse = nc_smtp_read_response($socket);
    if (!nc_smtp_response_matches($dataResponse, [250])) {
        fclose($socket);
        return ['sent' => false, 'error' => 'SMTP DATA failed: ' . $dataResponse];
    }

    nc_smtp_send_command($socket, 'QUIT', [221], $lastError);
    fclose($socket);

    return ['sent' => true, 'error' => null];
}

function nc_is_nonpublic_host($host) {
    $host = strtolower(trim((string) $host));
    if ($host === '' || $host === 'localhost' || $host === '::1' || $host === '0.0.0.0') {
        return true;
    }

    if (substr($host, -6) === '.local') {
        return true;
    }

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    return false;
}

function nc_get_email_asset_base_url($siteUrl, $fallback = 'https://newcomerconnect.ca') {
    $candidate = nc_normalize_public_url($siteUrl, $fallback);
    $host = parse_url($candidate, PHP_URL_HOST);
    if ($host === null || nc_is_nonpublic_host($host)) {
        return rtrim((string) $fallback, '/');
    }

    return rtrim((string) $candidate, '/');
}

function nc_email_asset_url($siteUrl, $path) {
    return nc_get_email_asset_base_url($siteUrl) . '/' . ltrim((string) $path, '/');
}

function nc_normalize_public_url($url, $fallback) {
    $url = trim((string) $url);
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        return rtrim($url, '/');
    }
    return rtrim((string) $fallback, '/');
}

function nc_format_phone_href($phone) {
    return preg_replace('/[^0-9\+]/', '', (string) $phone);
}

function nc_pretty_label_from_slug($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[#?].*$/', '', $value);
    $value = preg_replace('/\.html?$/i', '', $value);
    $value = str_replace(['_', '-'], ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    return ucwords(trim($value));
}

function nc_build_submission_category($submissionType, $submissionLabel, $serviceInterest) {
    if ($submissionLabel !== '') {
        return $submissionLabel;
    }

    $type = strtolower(trim((string) $submissionType));
    if ($type !== '') {
        if (strpos($type, 'booking') !== false || strpos($type, 'consultation') !== false) {
            return 'Consultation Booking';
        }
        if (strpos($type, 'assessment') !== false) {
            return 'Assessment Request';
        }
        if (strpos($type, 'contact') !== false) {
            return 'Contact Inquiry';
        }
        if (strpos($type, 'legal') !== false) {
            return 'Legal Consultation Request';
        }
    }

    $serviceInterest = trim((string) $serviceInterest);
    if ($serviceInterest !== '' && strcasecmp($serviceInterest, 'Not specified') !== 0 && strcasecmp($serviceInterest, 'General Inquiry') !== 0) {
        return $serviceInterest . ' Request';
    }

    if (strcasecmp($serviceInterest, 'General Inquiry') === 0) {
        return 'General Inquiry';
    }

    return 'Website Inquiry';
}

function nc_build_submission_source_label($sourceLabel, $pageTitle, $submissionSource) {
    if ($sourceLabel !== '') {
        return $sourceLabel;
    }

    $pageTitle = trim((string) $pageTitle);
    if ($pageTitle !== '') {
        $pageTitle = preg_replace('/\s*\|\s*Newcomer Connect\s*$/i', '', $pageTitle);
        $pageTitle = preg_replace('/\s+/', ' ', $pageTitle);
        if ($pageTitle !== '') {
            return $pageTitle;
        }
    }

    $submissionSource = trim((string) $submissionSource);
    if ($submissionSource !== '') {
        $base = basename(preg_replace('/[#?].*$/', '', $submissionSource));
        $label = nc_pretty_label_from_slug($base);
        if ($label !== '') {
            return $label . ' Page';
        }
    }

    return 'Website';
}

function nc_build_admin_subject($sourceLabel, $submissionCategory, $serviceInterest, $fullName) {
    $parts = [];
    if ($sourceLabel !== '') {
        $parts[] = $sourceLabel;
    }
    if ($submissionCategory !== '') {
        $parts[] = $submissionCategory;
    }
    if ($serviceInterest !== '' && strcasecmp($serviceInterest, 'Not specified') !== 0) {
        $parts[] = $serviceInterest;
    }
    if ($fullName !== '') {
        $parts[] = $fullName;
    }

    return implode(' | ', array_values(array_unique(array_filter($parts))));
}

function nc_build_customer_subject($submissionCategory) {
    $label = trim((string) $submissionCategory);
    if ($label === '') {
        $label = 'request';
    }

    return 'We received your ' . strtolower($label) . ' | Newcomer Connect';
}

function nc_render_email_tile($label, $value, $accentColor) {
    $safeLabel = nc_esc_html($label);
    $safeValue = nc_esc_html($value);
    $safeAccent = nc_esc_html($accentColor);

    return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:separate;background:#f8fbff;border:1px solid #d8e6f8;border-radius:18px;">'
        . '<tr><td style="padding:16px 18px;">'
        . '<div style="font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#5b6f87;font-weight:700;margin-bottom:8px;">' . $safeLabel . '</div>'
        . '<div style="font-size:16px;line-height:1.5;color:#10263f;font-weight:700;border-left:4px solid ' . $safeAccent . ';padding-left:12px;">' . $safeValue . '</div>'
        . '</td></tr></table>';
}

function nc_render_email_detail_row($label, $value) {
    $safeLabel = nc_esc_html($label);
    return '<tr>'
        . '<td style="padding:12px 14px;background:#f8fafc;border-bottom:1px solid #dbe7f3;font-size:13px;font-weight:700;color:#22405f;width:34%;vertical-align:top;">' . $safeLabel . '</td>'
        . '<td style="padding:12px 14px;border-bottom:1px solid #dbe7f3;font-size:14px;line-height:1.7;color:#1f2937;">' . $value . '</td>'
        . '</tr>';
}

function nc_render_email_button($label, $url, $background, $textColor = '#ffffff') {
    $safeLabel = nc_esc_html($label);
    $safeUrl = nc_esc_html($url);
    $safeBackground = nc_esc_html($background);
    $safeTextColor = nc_esc_html($textColor);

    return '<a href="' . $safeUrl . '" style="display:inline-block;padding:13px 20px;background:' . $safeBackground . ';color:' . $safeTextColor . ';text-decoration:none;border-radius:999px;font-size:14px;font-weight:700;letter-spacing:0.02em;">' . $safeLabel . '</a>';
}

function nc_render_branded_email_shell($options) {
    $title = nc_esc_html($options['title'] ?? 'Newcomer Connect');
    $preheader = nc_esc_html($options['preheader'] ?? 'Newcomer Connect update');
    $eyebrow = nc_esc_html($options['eyebrow'] ?? 'Newcomer Connect');
    $heading = nc_esc_html($options['heading'] ?? 'Newcomer Connect');
    $subheading = nc_esc_html($options['subheading'] ?? '');
    $heroImageUrl = nc_esc_html($options['hero_image_url'] ?? '');
    $heroImageAlt = nc_esc_html($options['hero_image_alt'] ?? 'Canada skyline');
    $logoUrl = (string) ($options['logo_url'] ?? '');
    $resolvedLogoSrc = nc_esc_html(nc_get_email_logo_src($logoUrl));
    $contentHtml = $options['content_html'] ?? '';
    $footerHtml = $options['footer_html'] ?? '';
    $brandLogo = $resolvedLogoSrc !== ''
        ? '<img src="' . $resolvedLogoSrc . '" alt="Newcomer Connect logo" width="272" style="display:block;width:100%;max-width:272px;height:auto;border:0;outline:none;text-decoration:none;">'
        : '';
    $heroTopRow = $brandLogo !== ''
        ? '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;">'
            . '<tr>'
            . '<td valign="top" align="left">' . $brandLogo . '</td>'
            . '</tr>'
            . '</table>'
        : '';
    return '<!doctype html>'
        . '<html lang="en">'
        . '<head>'
        . '<meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>' . $title . '</title>'
        . '<style>'
        . 'body{margin:0;padding:0;background:#edf3f8;font-family:Segoe UI,Arial,sans-serif;color:#13243a;}'
        . '@media only screen and (max-width:640px){.email-shell{padding:14px!important;}.email-card{border-radius:18px!important;}.hero-copy{padding:22px 20px 0!important;}.body-copy{padding:22px 20px!important;}.stack-col,.stack-col td{display:block!important;width:100%!important;padding-right:0!important;padding-left:0!important;}}'
        . '</style>'
        . '</head>'
        . '<body style="margin:0;padding:0;background:#edf3f8;">'
        . '<span style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;mso-hide:all;">' . $preheader . '</span>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;background:#edf3f8;">'
        . '<tr><td class="email-shell" align="center" style="padding:26px 12px;">'
        . '<table role="presentation" width="720" cellspacing="0" cellpadding="0" class="email-card" style="width:100%;max-width:720px;background:#ffffff;border:1px solid #dce7f2;border-radius:28px;overflow:hidden;box-shadow:0 20px 52px rgba(18,37,62,0.10);">'
        . '<tr><td style="padding:0;">'
        . '<div style="position:relative;background:linear-gradient(135deg,#0f3150 0%,#1d6ec5 48%,#d8292f 100%);overflow:hidden;">'
        . '<div class="hero-copy" style="padding:28px 28px 0;">'
        . $heroTopRow
        . '<div style="margin-top:18px;font-size:11px;letter-spacing:0.18em;text-transform:uppercase;color:#fdf2f2;font-weight:700;">' . $eyebrow . '</div>'
        . '<h1 style="margin:12px 0 10px;font-size:31px;line-height:1.18;color:#ffffff;font-weight:800;">' . $heading . '</h1>'
        . '<p style="margin:0 0 18px;font-size:16px;line-height:1.75;color:rgba(255,255,255,0.92);max-width:560px;">' . $subheading . '</p>'
        . '</div>'
        . '<div style="height:28px;"></div>'
        . '</div>'
        . '</td></tr>'
        . '<tr><td class="body-copy" style="padding:28px;">' . $contentHtml . '</td></tr>'
        . '<tr><td style="padding:0 28px 28px;">' . $footerHtml . '</td></tr>'
        . '</table>'
        . '</td></tr>'
        . '</table>'
        . '</body>'
        . '</html>';
}