<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . DIRECTORY_SEPARATOR . 'site-mailer.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'bookings-lib.php';

function contact_build_admin_email($record, $context) {
    $subject = nc_build_admin_subject(
        $record['source_label'] ?? 'Website',
        $record['submission_category'] ?? 'Contact Inquiry',
        $record['service_interest'] ?? 'Not specified',
        $record['full_name'] ?? ''
    );

    $logoUrl = nc_get_default_email_logo_url();
    $heroUrl = nc_email_asset_url($context['siteUrl'], 'assets/images/premium-online/toronto-night-cn.jpg');

    $content = '<p style="margin:0 0 18px;font-size:16px;line-height:1.8;color:#21364f;">A new website inquiry has been submitted. The request details are ready for review below.</p>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0;">'
        . '<tr>'
        . '<td class="stack-col" width="50%" style="width:50%;padding:0 8px 12px 0;vertical-align:top;">' . nc_render_email_tile('Submission Type', $record['submission_category'] ?? 'Contact Inquiry', '#d8292f') . '</td>'
        . '<td class="stack-col" width="50%" style="width:50%;padding:0 0 12px 8px;vertical-align:top;">' . nc_render_email_tile('Source', $record['source_label'] ?? 'Website', '#1d6ec5') . '</td>'
        . '</tr>'
        . '<tr>'
        . '<td class="stack-col" width="50%" style="width:50%;padding:0 8px 0 0;vertical-align:top;">' . nc_render_email_tile('Contact Name', $record['full_name'] ?? '', '#0f766e') . '</td>'
        . '<td class="stack-col" width="50%" style="width:50%;padding:0 0 0 8px;vertical-align:top;">' . nc_render_email_tile('Reference', $record['request_id'] ?? '', '#7c3aed') . '</td>'
        . '</tr>'
        . '</table>'
        . '<div style="height:24px;"></div>'
        . '<div style="padding:20px 22px;background:#f8fbff;border:1px solid #dbe7f3;border-radius:22px;">'
        . '<div style="font-size:12px;letter-spacing:0.14em;text-transform:uppercase;color:#567089;font-weight:700;margin-bottom:14px;">Inquiry Details</div>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;width:100%;border:1px solid #dbe7f3;border-radius:18px;overflow:hidden;">'
        . nc_render_email_detail_row('Submitted At', nc_esc_html($record['submitted_at'] ?? ''))
        . nc_render_email_detail_row('Email Address', '<a href="mailto:' . nc_esc_html($record['email'] ?? '') . '" style="color:#1d6ec5;text-decoration:none;font-weight:700;">' . nc_esc_html($record['email'] ?? '') . '</a>')
        . nc_render_email_detail_row('Phone Number', nc_esc_html($record['phone'] ?? 'Not provided'))
        . nc_render_email_detail_row('Page Title', nc_esc_html($record['page_title'] ?? 'Website'))
        . nc_render_email_detail_row('Page URL', '<a href="' . nc_esc_html($record['page_url'] ?? $context['siteUrl']) . '" style="color:#1d6ec5;text-decoration:none;word-break:break-all;">' . nc_esc_html($record['page_url'] ?? $context['siteUrl']) . '</a>')
        . nc_render_email_detail_row('Message', '<div style="font-size:14px;line-height:1.8;color:#1f2937;">' . nl2br(nc_esc_html($record['message'] ?? '')) . '</div>')
        . '</table>'
        . '</div>'
        . '<div style="height:22px;"></div>'
        . '<div style="padding:22px;background:#0f3150;border-radius:22px;">'
        . '<div style="font-size:12px;letter-spacing:0.14em;text-transform:uppercase;color:#bfdcff;font-weight:700;margin-bottom:14px;">Quick Actions</div>'
        . '<table role="presentation" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0 12px;">'
        . '<tr><td>' . nc_render_email_button('Reply to Contact', 'mailto:' . ($record['email'] ?? ''), '#d8292f') . '</td></tr>'
        . (!empty($record['phone_link']) ? '<tr><td>' . nc_render_email_button('Call Contact', 'tel:' . $record['phone_link'], '#1d6ec5') . '</td></tr>' : '')
        . '</table>'
        . '</div>';

    return [
        'subject' => $subject,
        'html' => nc_render_branded_email_shell([
            'title' => $subject,
            'preheader' => 'A new website inquiry is ready for admin review.',
            'eyebrow' => 'Admin Lead Alert',
            'heading' => 'A new contact inquiry has arrived.',
            'subheading' => 'This message came through the website and is ready for a fast admin response.',
            'hero_image_url' => $heroUrl,
            'hero_image_alt' => 'Toronto skyline for Newcomer Connect admin email',
            'logo_url' => $logoUrl,
            'content_html' => $content,
            'footer_html' => ''
        ])
    ];
}

function contact_build_customer_email($record, $context) {
    $subject = nc_build_customer_subject($record['submission_category'] ?? 'request');

    $logoUrl = nc_get_default_email_logo_url();
    $heroUrl = nc_email_asset_url($context['siteUrl'], 'assets/images/premium-online/toronto-day.jpg');

    $content = '<p style="margin:0 0 18px;font-size:16px;line-height:1.8;color:#21364f;">Thank you, ' . nc_esc_html($record['first_name'] ?? '') . '. Your message has reached our admin team successfully.</p>'
        . '<p style="margin:0 0 20px;font-size:15px;line-height:1.8;color:#42566c;">We will review your inquiry and reply as soon as possible, usually within one business day.</p>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0;">'
        . '<tr>'
        . '<td class="stack-col" width="50%" style="width:50%;padding:0 8px 12px 0;vertical-align:top;">' . nc_render_email_tile('Request Type', $record['submission_category'] ?? 'Contact Inquiry', '#d8292f') . '</td>'
        . '<td class="stack-col" width="50%" style="width:50%;padding:0 0 12px 8px;vertical-align:top;">' . nc_render_email_tile('Reference', $record['request_id'] ?? '', '#1d6ec5') . '</td>'
        . '</tr>'
        . '</table>'
        . '<div style="height:24px;"></div>'
        . '<div style="padding:22px;background:linear-gradient(180deg,#f8fbff 0%,#ffffff 100%);border:1px solid #dbe7f3;border-radius:22px;">'
        . '<div style="font-size:12px;letter-spacing:0.14em;text-transform:uppercase;color:#567089;font-weight:700;margin-bottom:14px;">Your Message Summary</div>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;width:100%;border:1px solid #dbe7f3;border-radius:18px;overflow:hidden;">'
        . nc_render_email_detail_row('Submitted At', nc_esc_html($record['submitted_at'] ?? ''))
        . nc_render_email_detail_row('Email Address', '<a href="mailto:' . nc_esc_html($record['email'] ?? '') . '" style="color:#1d6ec5;text-decoration:none;font-weight:700;">' . nc_esc_html($record['email'] ?? '') . '</a>')
        . nc_render_email_detail_row('Phone Number', nc_esc_html($record['phone'] ?? 'Not provided'))
        . nc_render_email_detail_row('Message', '<div style="font-size:14px;line-height:1.8;color:#1f2937;">' . nl2br(nc_esc_html($record['message'] ?? '')) . '</div>')
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

    return [
        'subject' => $subject,
        'html' => nc_render_branded_email_shell([
            'title' => $subject,
            'preheader' => 'Your message has been received by Newcomer Connect.',
            'eyebrow' => 'Contact Confirmation',
            'heading' => 'Your message is with our admin team.',
            'subheading' => 'We have received your inquiry and will respond with the next best steps as quickly as possible.',
            'hero_image_url' => $heroUrl,
            'hero_image_alt' => 'Canada skyline for confirmation email',
            'logo_url' => $logoUrl,
            'content_html' => $content,
            'footer_html' => ''
        ])
    ];
}

function respond_for_dual_mail_result($adminResult, $customerResult, $successMessage, $partialMessage, $failureMessage, $httpFailureCode = 500) {
    $adminSent = !empty($adminResult['sent']);
    $customerSent = !empty($customerResult['sent']);

    if ($adminSent && $customerSent) {
        echo json_encode([
            'success' => true,
            'message' => $successMessage
        ]);
        exit;
    }

    if ($adminSent || $customerSent) {
        echo json_encode([
            'success' => true,
            'message' => $partialMessage
        ]);
        exit;
    }

    http_response_code($httpFailureCode);
    echo json_encode([
        'success' => false,
        'message' => $failureMessage
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

if (!empty($_POST['website'])) {
    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your request has been received.'
    ]);
    exit;
}

$smtpConfig = nc_get_smtp_config();
$missingConfig = nc_validate_smtp_config($smtpConfig);
if (!empty($missingConfig)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'SMTP is not configured. Missing: ' . implode(', ', $missingConfig) . '. Add these as server env vars or in smtp-config.php.'
    ]);
    exit;
}

$adminEmail = nc_get_env_value('ADMIN_EMAIL', 'info@newcomerconnect.ca');
if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Admin email is invalid. Set a valid ADMIN_EMAIL on the server.'
    ]);
    exit;
}

$supportEmail = nc_get_env_value('SUPPORT_EMAIL', 'info@newcomerconnect.ca');
if (!filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
    $supportEmail = (string) ($smtpConfig['from_email'] ?? 'info@newcomerconnect.ca');
}

$siteUrl = nc_normalize_public_url(nc_get_env_value('SITE_URL', 'https://newcomerconnect.ca'), 'https://newcomerconnect.ca');

$submissionType = nc_clean_input($_POST['submission_type'] ?? 'contact_inquiry');
$submissionLabel = nc_clean_input($_POST['submission_label'] ?? '');
$submissionSource = nc_clean_input($_POST['submission_source'] ?? '');
$submissionSourceLabel = nc_clean_input($_POST['submission_source_label'] ?? '');
$pageTitle = nc_clean_input($_POST['page_title'] ?? '');
$pageUrl = trim((string) ($_POST['page_url'] ?? ''));
$referrer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));

if (!filter_var($pageUrl, FILTER_VALIDATE_URL)) {
    $pageUrl = filter_var($referrer, FILTER_VALIDATE_URL) ? $referrer : ($siteUrl . '/contact.html');
}

$pageTitle = $pageTitle !== '' ? $pageTitle : 'Contact Page';
$submissionSourceLabel = nc_build_submission_source_label($submissionSourceLabel, $pageTitle, $submissionSource);

if ($submissionType === 'consultation_booking') {
    $bookingBuild = bookings_build_record_from_post($_POST, [
        'source_label' => $submissionSourceLabel,
        'page_title' => $pageTitle,
        'page_url' => $pageUrl,
        'submission_label' => $submissionLabel !== '' ? $submissionLabel : 'Consultation Booking Request'
    ]);

    if (!empty($bookingBuild['errors'])) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => implode(' ', $bookingBuild['errors'])
        ]);
        exit;
    }

    $bookingRecord = $bookingBuild['record'];
    $records = bookings_load_all();
    $records[] = $bookingRecord;
    if (!bookings_write_all($records)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Could not save your booking right now. Please try again shortly.'
        ]);
        exit;
    }

    $adminMail = bookings_build_admin_request_email($bookingRecord, [
        'siteUrl' => $siteUrl,
        'adminEmail' => $adminEmail,
        'supportEmail' => $supportEmail
    ]);
    $customerMail = bookings_build_customer_request_email($bookingRecord, [
        'siteUrl' => $siteUrl,
        'supportEmail' => $supportEmail
    ]);

    $adminResult = nc_send_html_email($smtpConfig, $adminEmail, $adminMail['subject'], $adminMail['html'], $bookingRecord['email']);
    $customerResult = nc_send_html_email($smtpConfig, $bookingRecord['email'], $customerMail['subject'], $customerMail['html'], $adminEmail);

    nc_append_mail_log($bookingRecord['id'], [
        'kind' => 'booking-request',
        'booking_id' => $bookingRecord['id'],
        'admin_to' => $adminEmail,
        'customer_to' => $bookingRecord['email'],
        'admin_sent' => $adminResult['sent'],
        'customer_sent' => $customerResult['sent'],
        'admin_error' => $adminResult['error'],
        'customer_error' => $customerResult['error'],
        'smtp_host' => $smtpConfig['host'],
        'smtp_port' => $smtpConfig['port'],
        'smtp_encryption' => $smtpConfig['encryption']
    ]);

    respond_for_dual_mail_result(
        $adminResult,
        $customerResult,
        'Thank you! Your consultation request has been added to our booking queue and confirmation emails have been sent.',
        'Your consultation request has been saved in our admin booking queue. Some email notifications could not be delivered right now, but your booking is recorded.',
        'Sorry, we could not send your booking notifications right now. Please contact ' . $supportEmail . ' and mention booking id ' . $bookingRecord['id'] . '.'
    );
}

$firstName = nc_clean_input($_POST['first_name'] ?? '');
$lastName = nc_clean_input($_POST['last_name'] ?? '');
$email = nc_clean_input($_POST['email'] ?? '');
$phone = nc_clean_input($_POST['phone'] ?? '');
$message = trim((string) ($_POST['message'] ?? ''));
$serviceInterest = nc_clean_input($_POST['service_interest'] ?? '');

if ($firstName === '' || $lastName === '' || $email === '' || trim($message) === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please complete all required fields before submitting.'
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please provide a valid email address.'
    ]);
    exit;
}

$serviceInterest = $serviceInterest !== '' ? $serviceInterest : 'Not specified';
$submissionCategory = nc_build_submission_category($submissionType, $submissionLabel, $serviceInterest);

try {
    $requestId = date('YmdHis') . '-' . bin2hex(random_bytes(3));
} catch (Exception $error) {
    $requestId = date('YmdHis') . '-' . uniqid();
}

$contactRecord = [
    'request_id' => $requestId,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'full_name' => trim($firstName . ' ' . $lastName),
    'email' => $email,
    'phone' => $phone !== '' ? $phone : 'Not provided',
    'phone_link' => $phone !== '' ? nc_format_phone_href($phone) : '',
    'service_interest' => $serviceInterest,
    'message' => $message,
    'submitted_at' => date('F j, Y \a\t g:i A'),
    'submission_category' => $submissionCategory,
    'source_label' => $submissionSourceLabel,
    'page_title' => $pageTitle,
    'page_url' => $pageUrl
];

$adminMail = contact_build_admin_email($contactRecord, [
    'siteUrl' => $siteUrl,
    'adminEmail' => $adminEmail,
    'supportEmail' => $supportEmail
]);
$customerMail = contact_build_customer_email($contactRecord, [
    'siteUrl' => $siteUrl,
    'supportEmail' => $supportEmail
]);

$adminResult = nc_send_html_email($smtpConfig, $adminEmail, $adminMail['subject'], $adminMail['html'], $email);
$customerResult = nc_send_html_email($smtpConfig, $email, $customerMail['subject'], $customerMail['html'], $adminEmail);

nc_append_mail_log($requestId, [
    'kind' => 'contact-inquiry',
    'admin_to' => $adminEmail,
    'customer_to' => $email,
    'admin_sent' => $adminResult['sent'],
    'customer_sent' => $customerResult['sent'],
    'admin_error' => $adminResult['error'],
    'customer_error' => $customerResult['error'],
    'smtp_host' => $smtpConfig['host'],
    'smtp_port' => $smtpConfig['port'],
    'smtp_encryption' => $smtpConfig['encryption']
]);

respond_for_dual_mail_result(
    $adminResult,
    $customerResult,
    'Thank you! Your request has been sent to our admin team and a confirmation email has been sent to you.',
    'Thank you! Your request reached our admin workflow. One of the confirmation emails could not be delivered this time, but your inquiry was still received.',
    'Sorry, we could not send your request emails right now. Please email ' . $supportEmail . ' and mention reference ' . $requestId . '.'
);