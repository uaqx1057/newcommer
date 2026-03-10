<?php
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

function clean_input($value) {
    $value = is_string($value) ? trim($value) : '';
    $value = strip_tags($value);
    return $value;
}

function esc_html($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function get_env_value($key, $default = '') {
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

function load_local_smtp_config() {
  $configPath = __DIR__ . DIRECTORY_SEPARATOR . 'smtp-config.php';
  if (!is_file($configPath)) {
    return [];
  }

  $data = include $configPath;
  return is_array($data) ? $data : [];
}

function append_mail_log($requestId, $payload) {
  $logFile = __DIR__ . DIRECTORY_SEPARATOR . 'mail-debug.log';
  $line = '[' . date('Y-m-d H:i:s') . '] request=' . $requestId . ' ' . json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL;
  @file_put_contents($logFile, $line, FILE_APPEND);
}

function smtp_read_response($socket) {
  $response = '';
  while (($line = fgets($socket, 515)) !== false) {
    $response .= $line;
    if (preg_match('/^\d{3}\s/', $line)) {
      break;
    }
  }
  return trim($response);
}

function smtp_response_matches($response, $expectedCodes) {
  foreach ($expectedCodes as $code) {
    if (substr($response, 0, 3) === (string)$code) {
      return true;
    }
  }
  return false;
}

function smtp_send_command($socket, $command, $expectedCodes, &$lastError) {
  if ($command !== null) {
    fwrite($socket, $command . "\r\n");
  }

  $response = smtp_read_response($socket);
  if (!smtp_response_matches($response, $expectedCodes)) {
    $lastError = 'SMTP error after command [' . ($command ?? 'CONNECT') . ']: ' . $response;
    return false;
  }
  return true;
}

function send_html_email($smtpConfig, $to, $subject, $htmlBody, $replyToEmail) {
  $to = trim((string)$to);
  if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    return ['sent' => false, 'error' => 'Invalid recipient email address.'];
  }

  $host = $smtpConfig['host'];
  $port = (int)$smtpConfig['port'];
  $encryption = strtolower($smtpConfig['encryption']);
  $username = $smtpConfig['username'];
  $password = $smtpConfig['password'];
  $fromEmail = $smtpConfig['from_email'];
  $fromName = $smtpConfig['from_name'];
  $heloHost = $smtpConfig['helo_host'];

  $remoteHost = $encryption === 'ssl' ? ('ssl://' . $host) : $host;
  $errno = 0;
  $errstr = '';
  $socket = @fsockopen($remoteHost, $port, $errno, $errstr, 20);
  if (!$socket) {
    return ['sent' => false, 'error' => 'SMTP connect failed: ' . $errstr . ' (' . $errno . ')'];
  }

  stream_set_timeout($socket, 20);
  $lastError = null;

  if (!smtp_send_command($socket, null, [220], $lastError)) {
    fclose($socket);
    return ['sent' => false, 'error' => $lastError];
  }

  if (!smtp_send_command($socket, 'EHLO ' . $heloHost, [250], $lastError)) {
    fclose($socket);
    return ['sent' => false, 'error' => $lastError];
  }

  if ($encryption === 'tls') {
    if (!smtp_send_command($socket, 'STARTTLS', [220], $lastError)) {
      fclose($socket);
      return ['sent' => false, 'error' => $lastError];
    }

    $tlsEnabled = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    if ($tlsEnabled !== true) {
      fclose($socket);
      return ['sent' => false, 'error' => 'Failed to enable TLS encryption.'];
    }

    if (!smtp_send_command($socket, 'EHLO ' . $heloHost, [250], $lastError)) {
      fclose($socket);
      return ['sent' => false, 'error' => $lastError];
    }
  }

  if ($username !== '' || $password !== '') {
    if (!smtp_send_command($socket, 'AUTH LOGIN', [334], $lastError)
      || !smtp_send_command($socket, base64_encode($username), [334], $lastError)
      || !smtp_send_command($socket, base64_encode($password), [235], $lastError)) {
      fclose($socket);
      return ['sent' => false, 'error' => $lastError];
    }
  }

  if (!smtp_send_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250], $lastError)
    || !smtp_send_command($socket, 'RCPT TO:<' . $to . '>', [250, 251], $lastError)
    || !smtp_send_command($socket, 'DATA', [354], $lastError)) {
    fclose($socket);
    return ['sent' => false, 'error' => $lastError];
  }

  $headers = [
    'Date: ' . date(DATE_RFC2822),
    'From: ' . $fromName . ' <' . $fromEmail . '>',
    'To: <' . $to . '>',
    'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'Reply-To: ' . $replyToEmail,
    'X-Mailer: NewcomerConnectSMTP'
  ];

  $rawMessage = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody;
  $rawMessage = str_replace("\r\n.", "\r\n..", $rawMessage);
  fwrite($socket, $rawMessage . "\r\n.\r\n");

  $dataResponse = smtp_read_response($socket);
  if (!smtp_response_matches($dataResponse, [250])) {
    fclose($socket);
    return ['sent' => false, 'error' => 'SMTP DATA failed: ' . $dataResponse];
  }

  smtp_send_command($socket, 'QUIT', [221], $lastError);
  fclose($socket);

  return ['sent' => true, 'error' => null];
}

// Honeypot field.
if (!empty($_POST['website'])) {
    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your request has been received.'
    ]);
    exit;
}

$firstName = clean_input($_POST['first_name'] ?? '');
$lastName = clean_input($_POST['last_name'] ?? '');
$email = clean_input($_POST['email'] ?? '');
$phone = clean_input($_POST['phone'] ?? '');
$serviceInterest = clean_input($_POST['service_interest'] ?? '');
$message = trim($_POST['message'] ?? '');

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

$adminEmail = get_env_value('ADMIN_EMAIL', 'uaqx1057@gmail.com');
$supportEmail = get_env_value('SUPPORT_EMAIL', 'info@codewithusman.com');
$siteUrl = 'https://newcomerconnect.ca';
$fullName = trim($firstName . ' ' . $lastName);
$submittedAt = date('F j, Y \a\t g:i A');
$serviceInterest = $serviceInterest !== '' ? $serviceInterest : 'Not specified';
$phone = $phone !== '' ? $phone : 'Not provided';

$safeFullName = esc_html($fullName);
$safeFirstName = esc_html($firstName);
$safeEmail = esc_html($email);
$safePhone = esc_html($phone);
$safeService = esc_html($serviceInterest);
$safeSubmittedAt = esc_html($submittedAt);
$safeMessage = nl2br(esc_html($message));

$adminSubject = 'New Consultation Request | Newcomer Connect';
$customerSubject = 'We Received Your Request | Newcomer Connect';
$fileSmtpConfig = load_local_smtp_config();

$smtpConfig = [
  'host' => get_env_value('SMTP_HOST', $fileSmtpConfig['host'] ?? 'codewithusman.com'),
  'port' => (int)get_env_value('SMTP_PORT', (string)($fileSmtpConfig['port'] ?? '465')),
  'encryption' => strtolower(get_env_value('SMTP_ENCRYPTION', $fileSmtpConfig['encryption'] ?? 'ssl')),
  'username' => get_env_value('SMTP_USERNAME', $fileSmtpConfig['username'] ?? 'info@codewithusman.com'),
  'password' => get_env_value('SMTP_PASSWORD', $fileSmtpConfig['password'] ?? ''),
  'from_email' => get_env_value('MAIL_FROM_EMAIL', $fileSmtpConfig['from_email'] ?? 'info@codewithusman.com'),
  'from_name' => get_env_value('MAIL_FROM_NAME', $fileSmtpConfig['from_name'] ?? 'Newcomer Connect'),
  'helo_host' => get_env_value('SMTP_HELO_HOST', $fileSmtpConfig['helo_host'] ?? 'codewithusman.com')
];

$missingConfig = [];
if ($smtpConfig['host'] === '') {
  $missingConfig[] = 'SMTP_HOST';
}
if ($smtpConfig['port'] <= 0) {
  $missingConfig[] = 'SMTP_PORT';
}
if ($smtpConfig['from_email'] === '') {
  $missingConfig[] = 'MAIL_FROM_EMAIL';
}
if ($smtpConfig['username'] === '') {
  $missingConfig[] = 'SMTP_USERNAME';
}
if ($smtpConfig['password'] === '') {
  $missingConfig[] = 'SMTP_PASSWORD';
}

if (!empty($missingConfig)) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'SMTP is not configured. Missing: ' . implode(', ', $missingConfig) . '. Add these as server env vars or in smtp-config.php.'
  ]);
  exit;
}

if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Admin email is invalid. Set a valid ADMIN_EMAIL on the server.'
  ]);
  exit;
}

$safeSupportEmail = esc_html($supportEmail);

try {
  $requestId = date('YmdHis') . '-' . bin2hex(random_bytes(3));
} catch (Exception $e) {
  $requestId = date('YmdHis') . '-' . uniqid();
}

$adminBody = '
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>New Consultation Request</title>
</head>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,sans-serif;color:#1f2937;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7fb;padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="680" cellspacing="0" cellpadding="0" style="max-width:680px;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;">
          <tr>
            <td style="background:linear-gradient(135deg,#d8292f,#1d6ec5);padding:22px 28px;color:#ffffff;">
              <p style="margin:0;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;opacity:0.9;">New Website Lead</p>
              <h1 style="margin:8px 0 0;font-size:24px;line-height:1.3;">Consultation Request Submitted</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:24px 28px 10px;">
              <p style="margin:0 0 14px;font-size:15px;line-height:1.7;">A new inquiry has been submitted on the Newcomer Connect website. Full details are below.</p>
            </td>
          </tr>
          <tr>
            <td style="padding:0 28px 8px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
                <tr><td style="padding:11px 14px;background:#f9fafb;border-bottom:1px solid #e5e7eb;font-weight:700;width:34%;">Submitted At</td><td style="padding:11px 14px;border-bottom:1px solid #e5e7eb;">' . $safeSubmittedAt . '</td></tr>
                <tr><td style="padding:11px 14px;background:#f9fafb;border-bottom:1px solid #e5e7eb;font-weight:700;">Full Name</td><td style="padding:11px 14px;border-bottom:1px solid #e5e7eb;">' . $safeFullName . '</td></tr>
                <tr><td style="padding:11px 14px;background:#f9fafb;border-bottom:1px solid #e5e7eb;font-weight:700;">Email</td><td style="padding:11px 14px;border-bottom:1px solid #e5e7eb;"><a href="mailto:' . $safeEmail . '" style="color:#1d6ec5;text-decoration:none;">' . $safeEmail . '</a></td></tr>
                <tr><td style="padding:11px 14px;background:#f9fafb;border-bottom:1px solid #e5e7eb;font-weight:700;">Phone</td><td style="padding:11px 14px;border-bottom:1px solid #e5e7eb;">' . $safePhone . '</td></tr>
                <tr><td style="padding:11px 14px;background:#f9fafb;border-bottom:1px solid #e5e7eb;font-weight:700;">Service Interest</td><td style="padding:11px 14px;border-bottom:1px solid #e5e7eb;">' . $safeService . '</td></tr>
                <tr><td style="padding:11px 14px;background:#f9fafb;font-weight:700;vertical-align:top;">Message</td><td style="padding:11px 14px;line-height:1.6;">' . $safeMessage . '</td></tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:18px 28px 28px;">
              <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.6;">Customer contact quick actions: <a href="mailto:' . $safeEmail . '" style="color:#1d6ec5;text-decoration:none;">Email customer</a> | <a href="tel:' . esc_html(preg_replace('/[^0-9\+]/', '', $phone)) . '" style="color:#1d6ec5;text-decoration:none;">Call customer</a></p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';

$customerBody = '
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Thank You from Newcomer Connect</title>
</head>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,sans-serif;color:#1f2937;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7fb;padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="680" cellspacing="0" cellpadding="0" style="max-width:680px;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;">
          <tr>
            <td style="background:linear-gradient(135deg,#d8292f,#1d6ec5);padding:22px 28px;color:#ffffff;">
              <p style="margin:0;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;opacity:0.9;">Newcomer Connect</p>
              <h1 style="margin:8px 0 0;font-size:24px;line-height:1.3;">Thank You, ' . $safeFirstName . '.</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:24px 28px 10px;">
              <p style="margin:0 0 12px;font-size:15px;line-height:1.7;">We have received your consultation request and shared it with our admin team. You can expect a response within one business day.</p>
              <p style="margin:0;font-size:15px;line-height:1.7;">Our support covers every stage of the journey:</p>
              <ul style="margin:12px 0 0;padding-left:20px;color:#374151;line-height:1.8;">
                <li>Pre-Arrival planning and documentation guidance</li>
                <li>Post-Arrival settlement support</li>
                <li>Immigration and legal service direction</li>
              </ul>
            </td>
          </tr>
          <tr>
            <td style="padding:18px 28px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0 10px;">
                <tr>
                  <td><a href="' . $siteUrl . '/services.html" style="display:inline-block;padding:11px 16px;background:#d8292f;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;font-size:14px;">Explore Services</a></td>
                </tr>
                <tr>
                  <td><a href="' . $siteUrl . '/faq.html" style="display:inline-block;padding:11px 16px;background:#1d6ec5;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;font-size:14px;">Read FAQ</a></td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:6px 28px 28px;">
              <p style="margin:0 0 8px;font-size:14px;color:#374151;line-height:1.7;">Need urgent help? Contact us directly:</p>
              <p style="margin:0;font-size:14px;line-height:1.8;color:#374151;">
                Email: <a href="mailto:' . $safeSupportEmail . '" style="color:#1d6ec5;text-decoration:none;">' . $safeSupportEmail . '</a><br>
                Canada: <a href="tel:+12893000321" style="color:#1d6ec5;text-decoration:none;">+1-289-300-0321</a><br>
                Pakistan: <a href="tel:+923370222232" style="color:#1d6ec5;text-decoration:none;">+92-337-022-2232</a>
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';

$adminResult = send_html_email($smtpConfig, $adminEmail, $adminSubject, $adminBody, $email);
$customerResult = send_html_email($smtpConfig, $email, $customerSubject, $customerBody, $adminEmail);

append_mail_log($requestId, [
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

$adminSent = $adminResult['sent'];
$customerSent = $customerResult['sent'];

if ($adminSent && $customerSent) {
    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your request has been sent to our admin team and a confirmation email has been sent to you.'
    ]);
    exit;
}

if ($adminSent && !$customerSent) {
    echo json_encode([
        'success' => true,
    'message' => 'Thank you! Your request reached our admin team. Confirmation email to your inbox could not be delivered this time.'
    ]);
    exit;
}

if (!$adminSent && $customerSent) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'We could not deliver your request to our admin inbox right now. Please email ' . $supportEmail . ' directly and mention reference ' . $requestId . '.'
  ]);
  exit;
}

http_response_code(500);
echo json_encode([
    'success' => false,
  'message' => 'Sorry, we could not send your request emails right now. Please email ' . $supportEmail . ' and mention reference ' . $requestId . '.'
]);
