<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'site-mailer.php';

function bookings_data_dir() {
    return __DIR__ . DIRECTORY_SEPARATOR . 'data';
}

function bookings_file_path() {
    return bookings_data_dir() . DIRECTORY_SEPARATOR . 'bookings.json';
}

function bookings_ensure_storage() {
    $dir = bookings_data_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $path = bookings_file_path();
    if (!is_file($path)) {
        @file_put_contents($path, "[]\n");
    }
}

function bookings_load_all() {
    bookings_ensure_storage();
    $path = bookings_file_path();
    $raw = @file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function bookings_write_all($records) {
    bookings_ensure_storage();
    $path = bookings_file_path();

    $fp = @fopen($path, 'c+');
    if (!$fp) {
        return false;
    }

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return false;
    }

    $json = json_encode(array_values($records), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, $json . "\n");
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

function bookings_generate_id() {
    try {
        $random = bin2hex(random_bytes(3));
    } catch (Exception $error) {
        $random = substr(sha1((string) mt_rand()), 0, 6);
    }

    return gmdate('YmdHis') . '-bk-' . $random;
}

function bookings_now_iso() {
    return gmdate('Y-m-d\TH:i:s\Z');
}

function bookings_status_label($status) {
    $map = [
        'requested' => 'Requested',
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled'
    ];

    $status = strtolower(trim((string) $status));
    return $map[$status] ?? 'Requested';
}

function bookings_urgency_label($urgency) {
    $map = [
        'standard' => 'Standard',
        'priority' => 'Priority',
        'urgent' => 'Urgent'
    ];

    $urgency = strtolower(trim((string) $urgency));
    return $map[$urgency] ?? 'Standard';
}

function bookings_urgency_rank($urgency) {
    $urgency = strtolower(trim((string) $urgency));
    $map = [
        'standard' => 1,
        'priority' => 2,
        'urgent' => 3
    ];
    return $map[$urgency] ?? 1;
}

function bookings_normalize_timezone($timezone, $browserTimezone = '') {
    $candidate = trim((string) $timezone);
    if ($candidate === '' || strtolower($candidate) === 'browser') {
        $candidate = trim((string) $browserTimezone);
    }
    if ($candidate === '') {
        $candidate = 'UTC';
    }

    try {
        new DateTimeZone($candidate);
        return $candidate;
    } catch (Exception $error) {
        return 'UTC';
    }
}

function bookings_create_datetime($date, $time, $timezone) {
    $date = trim((string) $date);
    $time = trim((string) $time);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return null;
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        return null;
    }

    try {
        $tz = new DateTimeZone($timezone);
        $dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $time, $tz);
        if (!$dateTime instanceof DateTimeImmutable) {
            return null;
        }
        return $dateTime;
    } catch (Exception $error) {
        return null;
    }
}

function bookings_format_display_datetime(DateTimeImmutable $localDateTime, $timezone) {
    return $localDateTime->format('l, F j, Y \a\t g:i A') . ' (' . $timezone . ')';
}

function bookings_build_record_from_post($post, $meta = []) {
    $firstName = nc_clean_input($post['first_name'] ?? '');
    $lastName = nc_clean_input($post['last_name'] ?? '');
    $emailRaw = trim((string) ($post['email'] ?? ''));
    $phone = nc_clean_input($post['phone'] ?? '');
    $serviceInterest = nc_clean_input($post['service_interest'] ?? '');
    $urgency = strtolower(trim((string) ($post['urgency'] ?? '')));
    $message = trim((string) ($post['message'] ?? ''));
    $preferredDate = trim((string) ($post['preferred_date'] ?? ''));
    $preferredTime = trim((string) ($post['preferred_time'] ?? ''));
    $timezone = bookings_normalize_timezone($post['timezone'] ?? '', $post['browser_timezone'] ?? '');
    $browserTimezone = trim((string) ($post['browser_timezone'] ?? ''));

    $errors = [];

    if ($firstName === '') {
        $errors[] = 'First name is required.';
    }
    if ($lastName === '') {
        $errors[] = 'Last name is required.';
    }
    if ($phone === '') {
        $errors[] = 'Phone number is required for booking.';
    }
    if ($serviceInterest === '') {
        $errors[] = 'Please select a service interest.';
    }
    if (!in_array($urgency, ['standard', 'priority', 'urgent'], true)) {
        $errors[] = 'Please select a valid urgency level.';
    }
    if ($preferredDate === '' || $preferredTime === '') {
        $errors[] = 'Preferred date and time are required.';
    }
    if ($message === '') {
        $errors[] = 'Consultation notes are required.';
    }

    $email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL);
    if ($email === false) {
        $errors[] = 'Please provide a valid email address.';
    }

    $localDateTime = bookings_create_datetime($preferredDate, $preferredTime, $timezone);
    if (!$localDateTime instanceof DateTimeImmutable) {
        $errors[] = 'Please provide a valid preferred booking date and time.';
    }

    if (empty($errors) && $localDateTime instanceof DateTimeImmutable) {
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $scheduledUtc = $localDateTime->setTimezone(new DateTimeZone('UTC'));
        if ($scheduledUtc <= $nowUtc) {
            $errors[] = 'Please choose a consultation time in the future.';
        }
    }

    if (!empty($errors) || !$localDateTime instanceof DateTimeImmutable || $email === false) {
        return ['errors' => $errors, 'record' => null];
    }

    $id = bookings_generate_id();
    $now = bookings_now_iso();
    $scheduledUtc = $localDateTime->setTimezone(new DateTimeZone('UTC'));
    $fullName = trim($firstName . ' ' . $lastName);

    $record = [
        'id' => $id,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'full_name' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'service_interest' => $serviceInterest,
        'urgency' => $urgency,
        'urgency_label' => bookings_urgency_label($urgency),
        'message' => $message,
        'timezone' => $timezone,
        'browser_timezone' => $browserTimezone,
        'preferred_date' => $preferredDate,
        'preferred_time' => $preferredTime,
        'scheduled_local' => $localDateTime->format('Y-m-d H:i'),
        'scheduled_display' => bookings_format_display_datetime($localDateTime, $timezone),
        'scheduled_at_utc' => $scheduledUtc->format('Y-m-d\TH:i:s\Z'),
        'status' => 'requested',
        'status_label' => bookings_status_label('requested'),
        'source_label' => (string) ($meta['source_label'] ?? 'Website'),
        'page_title' => (string) ($meta['page_title'] ?? ''),
        'page_url' => (string) ($meta['page_url'] ?? ''),
        'submission_label' => (string) ($meta['submission_label'] ?? 'Consultation Booking Request'),
        'day_reminder_sent_at' => null,
        'hour_reminder_sent_at' => null,
        'created_at' => $now,
        'updated_at' => $now
    ];

    return ['errors' => [], 'record' => $record];
}

function bookings_find_index($records, $bookingId) {
    foreach ($records as $index => $record) {
        if (is_array($record) && (string) ($record['id'] ?? '') === (string) $bookingId) {
            return (int) $index;
        }
    }
    return -1;
}

function bookings_remaining_seconds($record, $nowTs = null) {
    $scheduledTs = strtotime((string) ($record['scheduled_at_utc'] ?? ''));
    if ($scheduledTs === false) {
        return null;
    }
    $nowTs = $nowTs ?? time();
    return $scheduledTs - $nowTs;
}

function bookings_is_final_status($status) {
    $status = strtolower(trim((string) $status));
    return in_array($status, ['completed', 'cancelled'], true);
}

function bookings_is_due_for_day_reminder($record, $nowTs = null) {
    if (bookings_is_final_status($record['status'] ?? 'requested')) {
        return false;
    }
    if (!empty($record['day_reminder_sent_at'])) {
        return false;
    }

    $remaining = bookings_remaining_seconds($record, $nowTs);
    if ($remaining === null) {
        return false;
    }

    return $remaining <= 90000 && $remaining > 82800;
}

function bookings_is_due_for_hour_reminder($record, $nowTs = null) {
    if (bookings_is_final_status($record['status'] ?? 'requested')) {
        return false;
    }
    if (!empty($record['hour_reminder_sent_at'])) {
        return false;
    }

    $remaining = bookings_remaining_seconds($record, $nowTs);
    if ($remaining === null) {
        return false;
    }

    return $remaining <= 4500 && $remaining > 2700;
}

function bookings_sort_requested(&$records) {
    usort($records, function ($left, $right) {
        $rankCompare = bookings_urgency_rank($right['urgency'] ?? 'standard') <=> bookings_urgency_rank($left['urgency'] ?? 'standard');
        if ($rankCompare !== 0) {
            return $rankCompare;
        }

        return strtotime((string) ($left['scheduled_at_utc'] ?? '2100-01-01')) <=> strtotime((string) ($right['scheduled_at_utc'] ?? '2100-01-01'));
    });
}

function bookings_sort_upcoming(&$records) {
    usort($records, function ($left, $right) {
        return strtotime((string) ($left['scheduled_at_utc'] ?? '2100-01-01')) <=> strtotime((string) ($right['scheduled_at_utc'] ?? '2100-01-01'));
    });
}

function bookings_sort_recent(&$records) {
    usort($records, function ($left, $right) {
        return strtotime((string) ($right['updated_at'] ?? ($right['created_at'] ?? '1970-01-01'))) <=> strtotime((string) ($left['updated_at'] ?? ($left['created_at'] ?? '1970-01-01')));
    });
}

function bookings_prepare_dashboard_payload($records) {
    $requested = [];
    $upcoming = [];
    $recent = [];
    $urgentCount = 0;
    $dayReminderDue = 0;
    $hourReminderDue = 0;
    $nowTs = time();

    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }

        $status = strtolower(trim((string) ($record['status'] ?? 'requested')));
        $remaining = bookings_remaining_seconds($record, $nowTs);

        if (!bookings_is_final_status($status) && $remaining !== null && $remaining > 0) {
            $upcoming[] = $record;
            if ($status === 'requested') {
                $requested[] = $record;
            }
            if (($record['urgency'] ?? '') === 'urgent') {
                $urgentCount++;
            }
            if (bookings_is_due_for_day_reminder($record, $nowTs)) {
                $dayReminderDue++;
            }
            if (bookings_is_due_for_hour_reminder($record, $nowTs)) {
                $hourReminderDue++;
            }
            continue;
        }

        $recent[] = $record;
    }

    bookings_sort_requested($requested);
    bookings_sort_upcoming($upcoming);
    bookings_sort_recent($recent);

    return [
        'requested' => array_slice($requested, 0, 25),
        'upcoming' => array_slice($upcoming, 0, 25),
        'recent' => array_slice($recent, 0, 25),
        'stats' => [
            'requested_count' => count($requested),
            'upcoming_count' => count($upcoming),
            'urgent_count' => $urgentCount,
            'day_reminder_due_count' => $dayReminderDue,
            'hour_reminder_due_count' => $hourReminderDue
        ]
    ];
}

function bookings_update_status(&$records, $bookingId, $status) {
    $allowed = ['requested', 'confirmed', 'completed', 'cancelled'];
    $status = strtolower(trim((string) $status));
    if (!in_array($status, $allowed, true)) {
        return ['success' => false, 'message' => 'Unsupported booking status.'];
    }

    $index = bookings_find_index($records, $bookingId);
    if ($index < 0) {
        return ['success' => false, 'message' => 'Booking not found.'];
    }

    $records[$index]['status'] = $status;
    $records[$index]['status_label'] = bookings_status_label($status);
    $records[$index]['updated_at'] = bookings_now_iso();

    return ['success' => true, 'record' => $records[$index]];
}

function bookings_email_assets($siteUrl) {
    return [
        'logo_url' => nc_get_default_email_logo_url(),
        'admin_hero_url' => nc_email_asset_url($siteUrl, 'assets/images/premium-online/toronto-night-cn.jpg'),
        'customer_hero_url' => nc_email_asset_url($siteUrl, 'assets/images/premium-online/toronto-day.jpg')
    ];
}

function bookings_render_summary_rows($record, $includeStatus = true) {
    $rows = '';
    $rows .= nc_render_email_detail_row('Consultation Slot', nc_esc_html($record['scheduled_display'] ?? ''));
    $rows .= nc_render_email_detail_row('Service Interest', nc_esc_html($record['service_interest'] ?? ''));
    $rows .= nc_render_email_detail_row('Urgency', nc_esc_html($record['urgency_label'] ?? 'Standard'));
    $rows .= nc_render_email_detail_row('Phone Number', '<a href="tel:' . nc_esc_html(nc_format_phone_href($record['phone'] ?? '')) . '" style="color:#1d6ec5;text-decoration:none;font-weight:700;">' . nc_esc_html($record['phone'] ?? '') . '</a>');
    if ($includeStatus) {
        $rows .= nc_render_email_detail_row('Status', nc_esc_html($record['status_label'] ?? 'Requested'));
    }
    $rows .= nc_render_email_detail_row('Time Zone', nc_esc_html($record['timezone'] ?? 'UTC'));
    $rows .= nc_render_email_detail_row('Notes', '<div style="font-size:14px;line-height:1.8;color:#1f2937;">' . nl2br(nc_esc_html($record['message'] ?? '')) . '</div>');
    return $rows;
}

function bookings_build_admin_request_email($record, $context) {
    $assets = bookings_email_assets($context['siteUrl']);
    $subject = ($record['source_label'] ?? 'Website') . ' | Consultation Booking | ' . ($record['service_interest'] ?? 'Consultation') . ' | ' . ($record['full_name'] ?? 'Client');
    $content = '<p style="margin:0 0 18px;font-size:16px;line-height:1.8;color:#21364f;">A new consultation booking has been requested through the website. The preferred slot and urgency details are below.</p>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0;">'
        . '<tr>'
        . '<td class="stack-col" width="50%" style="width:50%;padding:0 8px 12px 0;vertical-align:top;">' . nc_render_email_tile('Preferred Slot', $record['scheduled_display'] ?? '', '#d8292f') . '</td>'
        . '<td class="stack-col" width="50%" style="width:50%;padding:0 0 12px 8px;vertical-align:top;">' . nc_render_email_tile('Urgency', $record['urgency_label'] ?? 'Standard', '#1d6ec5') . '</td>'
        . '</tr>'
        . '<tr>'
        . '<td class="stack-col" width="50%" style="width:50%;padding:0 8px 0 0;vertical-align:top;">' . nc_render_email_tile('Client', $record['full_name'] ?? '', '#0f766e') . '</td>'
        . '<td class="stack-col" width="50%" style="width:50%;padding:0 0 0 8px;vertical-align:top;">' . nc_render_email_tile('Booking ID', $record['id'] ?? '', '#7c3aed') . '</td>'
        . '</tr>'
        . '</table>'
        . '<div style="height:24px;"></div>'
        . '<div style="padding:20px 22px;background:#f8fbff;border:1px solid #dbe7f3;border-radius:22px;">'
        . '<div style="font-size:12px;letter-spacing:0.14em;text-transform:uppercase;color:#567089;font-weight:700;margin-bottom:14px;">Booking Summary</div>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;width:100%;border:1px solid #dbe7f3;border-radius:18px;overflow:hidden;">'
        . nc_render_email_detail_row('Submitted At', nc_esc_html($record['created_at'] ?? ''))
        . nc_render_email_detail_row('Email Address', '<a href="mailto:' . nc_esc_html($record['email'] ?? '') . '" style="color:#1d6ec5;text-decoration:none;font-weight:700;">' . nc_esc_html($record['email'] ?? '') . '</a>')
        . nc_render_email_detail_row('Source', nc_esc_html($record['source_label'] ?? 'Website'))
        . bookings_render_summary_rows($record)
        . '</table>'
        . '</div>'
        . '<div style="height:22px;"></div>'
        . '<div style="padding:22px;background:#0f3150;border-radius:22px;">'
        . '<div style="font-size:12px;letter-spacing:0.14em;text-transform:uppercase;color:#bfdcff;font-weight:700;margin-bottom:14px;">Quick Actions</div>'
        . '<table role="presentation" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0 12px;">'
        . '<tr><td>' . nc_render_email_button('Reply to Client', 'mailto:' . ($record['email'] ?? ''), '#d8292f') . '</td></tr>'
        . '<tr><td>' . nc_render_email_button('Call Client', 'tel:' . nc_format_phone_href($record['phone'] ?? ''), '#1d6ec5') . '</td></tr>'
        . (!empty($record['page_url']) ? '<tr><td>' . nc_render_email_button('Open Source Page', $record['page_url'], '#0f766e') . '</td></tr>' : '')
        . '</table>'
        . '</div>';

    return [
        'subject' => $subject,
        'html' => nc_render_branded_email_shell([
            'title' => $subject,
            'preheader' => 'A new consultation booking has been requested on the website.',
            'eyebrow' => 'Booking Alert',
            'heading' => 'A new consultation booking is waiting for review.',
            'subheading' => 'The client has selected a preferred consultation time and urgency level so your admin team can prioritize the follow-up.',
            'hero_image_url' => $assets['admin_hero_url'],
            'hero_image_alt' => 'Toronto skyline for Newcomer Connect',
            'logo_url' => $assets['logo_url'],
            'content_html' => $content,
            'footer_html' => ''
        ])
    ];
}

function bookings_build_customer_request_email($record, $context) {
    $assets = bookings_email_assets($context['siteUrl']);
    $subject = 'We received your consultation booking request | Newcomer Connect';
    $content = '<p style="margin:0 0 18px;font-size:16px;line-height:1.8;color:#21364f;">Thank you, ' . nc_esc_html($record['first_name'] ?? '') . '. Your preferred consultation slot has been received by our admin team.</p>'
        . '<p style="margin:0 0 20px;font-size:15px;line-height:1.8;color:#42566c;">We will review your requested date, time, service focus, and urgency. If anything needs adjustment, our team will contact you directly.</p>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0;">'
        . '<tr>'
        . '<td class="stack-col" width="50%" style="width:50%;padding:0 8px 12px 0;vertical-align:top;">' . nc_render_email_tile('Preferred Slot', $record['scheduled_display'] ?? '', '#d8292f') . '</td>'
        . '<td class="stack-col" width="50%" style="width:50%;padding:0 0 12px 8px;vertical-align:top;">' . nc_render_email_tile('Urgency', $record['urgency_label'] ?? 'Standard', '#1d6ec5') . '</td>'
        . '</tr>'
        . '<tr>'
        . '<td class="stack-col" width="50%" style="width:50%;padding:0 8px 0 0;vertical-align:top;">' . nc_render_email_tile('Service', $record['service_interest'] ?? '', '#0f766e') . '</td>'
        . '<td class="stack-col" width="50%" style="width:50%;padding:0 0 0 8px;vertical-align:top;">' . nc_render_email_tile('Next Step', 'Our team will confirm your consultation details soon.', '#7c3aed') . '</td>'
        . '</tr>'
        . '</table>'
        . '<div style="height:24px;"></div>'
        . '<div style="padding:22px;background:linear-gradient(180deg,#f8fbff 0%,#ffffff 100%);border:1px solid #dbe7f3;border-radius:22px;">'
        . '<div style="font-size:12px;letter-spacing:0.14em;text-transform:uppercase;color:#567089;font-weight:700;margin-bottom:14px;">Your Booking Details</div>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;width:100%;border:1px solid #dbe7f3;border-radius:18px;overflow:hidden;">'
        . bookings_render_summary_rows($record, false)
        . '</table>'
        . '</div>'
        . '<div style="height:22px;"></div>'
        . '<div style="padding:22px;background:#0f3150;border-radius:22px;">'
        . '<div style="font-size:12px;letter-spacing:0.14em;text-transform:uppercase;color:#bfdcff;font-weight:700;margin-bottom:14px;">Helpful Links</div>'
        . '<table role="presentation" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0 12px;">'
        . '<tr><td>' . nc_render_email_button('Explore Services', rtrim($context['siteUrl'], '/') . '/services.html', '#d8292f') . '</td></tr>'
        . '<tr><td>' . nc_render_email_button('Read FAQs', rtrim($context['siteUrl'], '/') . '/faq.html', '#1d6ec5') . '</td></tr>'
        . '</table>'
        . '</div>';

    $footer = '<div style="padding:18px 20px;background:#f8fafc;border:1px solid #dbe7f3;border-radius:18px;font-size:13px;line-height:1.8;color:#5b6b7f;">'
        . 'We will follow up after reviewing your preferred consultation slot and service focus.'
        . '</div>';

    return [
        'subject' => $subject,
        'html' => nc_render_branded_email_shell([
            'title' => $subject,
            'preheader' => 'Your consultation booking request has been received.',
            'eyebrow' => 'Booking Confirmation',
            'heading' => 'Your consultation request is in our admin queue.',
            'subheading' => 'We have captured your preferred consultation date, time, service interest, and urgency for review.',
            'hero_image_url' => $assets['customer_hero_url'],
            'hero_image_alt' => 'Canada skyline for newcomer planning',
            'logo_url' => $assets['logo_url'],
            'content_html' => $content,
            'footer_html' => $footer
        ])
    ];
}

function bookings_build_admin_reminder_email($record, $context, $reminderType) {
    $assets = bookings_email_assets($context['siteUrl']);
    $isHour = $reminderType === 'hour';
    $subject = $isHour
        ? 'Admin Reminder | Consultation in about 1 hour | ' . ($record['full_name'] ?? 'Client')
        : 'Admin Reminder | Consultation tomorrow | ' . ($record['full_name'] ?? 'Client');
    $content = '<p style="margin:0 0 18px;font-size:16px;line-height:1.8;color:#21364f;">This is your admin reminder for the upcoming consultation with ' . nc_esc_html($record['full_name'] ?? 'the client') . '.</p>'
        . '<div style="padding:20px 22px;background:#f8fbff;border:1px solid #dbe7f3;border-radius:22px;">'
        . '<div style="font-size:12px;letter-spacing:0.14em;text-transform:uppercase;color:#567089;font-weight:700;margin-bottom:14px;">Reminder Snapshot</div>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;width:100%;border:1px solid #dbe7f3;border-radius:18px;overflow:hidden;">'
        . bookings_render_summary_rows($record)
        . '</table>'
        . '</div>'
        . '<div style="height:22px;"></div>'
        . '<table role="presentation" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0 12px;">'
        . '<tr><td>' . nc_render_email_button('Email Client', 'mailto:' . ($record['email'] ?? ''), '#d8292f') . '</td></tr>'
        . '<tr><td>' . nc_render_email_button('Call Client', 'tel:' . nc_format_phone_href($record['phone'] ?? ''), '#1d6ec5') . '</td></tr>'
        . '</table>';

    return [
        'subject' => $subject,
        'html' => nc_render_branded_email_shell([
            'title' => $subject,
            'preheader' => 'Upcoming consultation reminder for admin.',
            'eyebrow' => 'Admin Reminder',
            'heading' => $isHour ? 'A consultation starts in about one hour.' : 'A consultation is scheduled for tomorrow.',
            'subheading' => 'Keep the client details, notes, and urgency close at hand for your upcoming consultation.',
            'hero_image_url' => $assets['admin_hero_url'],
            'hero_image_alt' => 'Toronto skyline for admin reminders',
            'logo_url' => $assets['logo_url'],
            'content_html' => $content,
            'footer_html' => ''
        ])
    ];
}

function bookings_build_customer_reminder_email($record, $context, $reminderType) {
    $assets = bookings_email_assets($context['siteUrl']);
    $isHour = $reminderType === 'hour';
    $subject = $isHour
        ? 'Reminder: your consultation starts in about 1 hour | Newcomer Connect'
        : 'Reminder: your consultation is tomorrow | Newcomer Connect';
    $content = '<p style="margin:0 0 18px;font-size:16px;line-height:1.8;color:#21364f;">This is a reminder for your upcoming Newcomer Connect consultation.</p>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0;">'
        . '<tr>'
        . '<td class="stack-col" width="50%" style="width:50%;padding:0 8px 12px 0;vertical-align:top;">' . nc_render_email_tile('Consultation Slot', $record['scheduled_display'] ?? '', '#d8292f') . '</td>'
        . '<td class="stack-col" width="50%" style="width:50%;padding:0 0 12px 8px;vertical-align:top;">' . nc_render_email_tile('Service', $record['service_interest'] ?? '', '#1d6ec5') . '</td>'
        . '</tr>'
        . '</table>'
        . '<div style="height:24px;"></div>'
        . '<div style="padding:22px;background:linear-gradient(180deg,#f8fbff 0%,#ffffff 100%);border:1px solid #dbe7f3;border-radius:22px;">'
        . '<div style="font-size:12px;letter-spacing:0.14em;text-transform:uppercase;color:#567089;font-weight:700;margin-bottom:14px;">Reminder Details</div>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;width:100%;border:1px solid #dbe7f3;border-radius:18px;overflow:hidden;">'
        . bookings_render_summary_rows($record, false)
        . '</table>'
        . '</div>'
        . '<div style="height:22px;"></div>'
        . '<div style="padding:18px 20px;background:#fff7ed;border:1px solid #fed7aa;border-radius:18px;color:#9a3412;font-size:14px;line-height:1.8;">'
        . ($isHour ? 'Your consultation is approaching soon. Please keep your phone and email nearby in case our admin team needs to reach you.' : 'Your consultation is scheduled for tomorrow. Review your notes and keep any documents ready that you want to discuss during the session.')
        . '</div>';

    $footer = '<div style="padding:18px 20px;background:#f8fafc;border:1px solid #dbe7f3;border-radius:18px;font-size:13px;line-height:1.8;color:#5b6b7f;">'
        . 'We look forward to speaking with you soon.'
        . '</div>';

    return [
        'subject' => $subject,
        'html' => nc_render_branded_email_shell([
            'title' => $subject,
            'preheader' => 'Reminder for your upcoming consultation.',
            'eyebrow' => 'Consultation Reminder',
            'heading' => $isHour ? 'Your consultation begins in about one hour.' : 'Your consultation is scheduled for tomorrow.',
            'subheading' => 'Keep this message handy so you can quickly review your consultation slot and service focus.',
            'hero_image_url' => $assets['customer_hero_url'],
            'hero_image_alt' => 'Canada skyline for reminder email',
            'logo_url' => $assets['logo_url'],
            'content_html' => $content,
            'footer_html' => $footer
        ])
    ];
}

function bookings_process_due_reminders($smtpConfig, $context) {
    $records = bookings_load_all();
    $changed = false;
    $summary = [
        'success' => true,
        'checked' => count($records),
        'day_sent' => 0,
        'hour_sent' => 0,
        'errors' => []
    ];
    $nowIso = bookings_now_iso();
    $nowTs = time();

    foreach ($records as $index => $record) {
        if (!is_array($record)) {
            continue;
        }

        $reminderType = null;
        if (bookings_is_due_for_day_reminder($record, $nowTs)) {
            $reminderType = 'day';
        } elseif (bookings_is_due_for_hour_reminder($record, $nowTs)) {
            $reminderType = 'hour';
        }

        if ($reminderType === null) {
            continue;
        }

        $adminEmail = trim((string) ($context['adminEmail'] ?? ''));
        $supportEmail = trim((string) ($context['supportEmail'] ?? ($smtpConfig['from_email'] ?? '')));
        $siteUrl = trim((string) ($context['siteUrl'] ?? 'https://newcomerconnect.ca'));
        $requestId = 'booking-reminder-' . ($record['id'] ?? bookings_generate_id()) . '-' . $reminderType;

        $adminMail = bookings_build_admin_reminder_email($record, [
            'siteUrl' => $siteUrl,
            'adminEmail' => $adminEmail,
            'supportEmail' => $supportEmail
        ], $reminderType);
        $customerMail = bookings_build_customer_reminder_email($record, [
            'siteUrl' => $siteUrl,
            'supportEmail' => $supportEmail
        ], $reminderType);

        $adminResult = nc_send_html_email($smtpConfig, $adminEmail, $adminMail['subject'], $adminMail['html'], $record['email'] ?? '');
        $customerResult = nc_send_html_email($smtpConfig, $record['email'] ?? '', $customerMail['subject'], $customerMail['html'], $adminEmail);

        nc_append_mail_log($requestId, [
            'kind' => 'booking-reminder',
            'booking_id' => $record['id'] ?? '',
            'reminder_type' => $reminderType,
            'admin_to' => $adminEmail,
            'customer_to' => $record['email'] ?? '',
            'admin_sent' => $adminResult['sent'],
            'customer_sent' => $customerResult['sent'],
            'admin_error' => $adminResult['error'],
            'customer_error' => $customerResult['error']
        ]);

        if ($adminResult['sent'] && $customerResult['sent']) {
            $field = $reminderType === 'day' ? 'day_reminder_sent_at' : 'hour_reminder_sent_at';
            $records[$index][$field] = $nowIso;
            $records[$index]['updated_at'] = $nowIso;
            $summary[$reminderType . '_sent'] += 1;
            $changed = true;
            continue;
        }

        $summary['success'] = false;
        $summary['errors'][] = [
            'booking_id' => $record['id'] ?? '',
            'reminder_type' => $reminderType,
            'admin_error' => $adminResult['error'],
            'customer_error' => $customerResult['error']
        ];
    }

    if ($changed) {
        bookings_write_all($records);
    }

    return $summary;
}