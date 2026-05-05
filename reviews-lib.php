<?php

function reviews_clean_input($value) {
    $value = is_string($value) ? trim($value) : '';
    $value = strip_tags($value);
    return $value;
}

function reviews_esc_html($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function reviews_data_dir() {
    return __DIR__ . DIRECTORY_SEPARATOR . 'data';
}

function reviews_file_path($fileName) {
    return reviews_data_dir() . DIRECTORY_SEPARATOR . $fileName;
}

function reviews_ensure_storage() {
    $dir = reviews_data_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $files = ['reviews-pending.json', 'reviews-approved.json', 'reviews-rejected.json'];
    foreach ($files as $fileName) {
        $path = reviews_file_path($fileName);
        if (!is_file($path)) {
            @file_put_contents($path, "[]\n");
        }
    }
}

function reviews_load_list($fileName) {
    reviews_ensure_storage();
    $path = reviews_file_path($fileName);
    if (!is_file($path)) {
        return [];
    }

    $raw = @file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function reviews_write_list($fileName, $records) {
    reviews_ensure_storage();
    $path = reviews_file_path($fileName);

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

function reviews_append_record($fileName, $record) {
    $list = reviews_load_list($fileName);
    $list[] = $record;
    return reviews_write_list($fileName, $list);
}

function reviews_get_client_ip() {
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR'
    ];

    foreach ($headers as $header) {
        if (empty($_SERVER[$header])) {
            continue;
        }

        $raw = (string)$_SERVER[$header];
        $parts = explode(',', $raw);
        $ip = trim($parts[0]);
        if ($ip !== '') {
            return $ip;
        }
    }

    return 'unknown';
}

function reviews_is_local_ip($ip) {
    $value = trim((string)$ip);
    return in_array($value, ['127.0.0.1', '::1', 'localhost'], true);
}

function reviews_now_iso() {
    return gmdate('Y-m-d\TH:i:s\Z');
}

function reviews_generate_id() {
    try {
        $random = bin2hex(random_bytes(3));
    } catch (Exception $e) {
        $random = substr(sha1((string)mt_rand()), 0, 6);
    }

    return gmdate('YmdHis') . '-' . $random;
}

function reviews_sanitize_message($value, $maxLength) {
    $value = reviews_clean_input($value);
    $value = preg_replace('/\s+/', ' ', $value);
    return mb_substr($value, 0, $maxLength, 'UTF-8');
}

function reviews_validate_rating($value) {
    $rating = (int)$value;
    if ($rating < 1 || $rating > 5) {
        return 0;
    }
    return $rating;
}

function reviews_build_initials($name) {
    $name = reviews_sanitize_message($name, 80);
    if ($name === '') {
        return 'NC';
    }

    $parts = preg_split('/\s+/u', $name) ?: [];
    $letters = [];
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        $letter = mb_substr($part, 0, 1, 'UTF-8');
        $letters[] = mb_strtoupper($letter, 'UTF-8');
        if (count($letters) === 2) {
            break;
        }
    }

    if (empty($letters)) {
        return 'NC';
    }

    return implode('', $letters);
}

function reviews_build_public_name($record) {
    $name = (string)($record['name'] ?? '');
    $allowPublicName = !empty($record['allow_public_name']);

    if ($allowPublicName && $name !== '') {
        return $name;
    }

    return reviews_build_initials($name);
}

function reviews_build_record_from_post($post, $ip) {
    $name = reviews_sanitize_message($post['name'] ?? '', 80);
    $city = reviews_sanitize_message($post['city'] ?? '', 80);
    $service = reviews_sanitize_message($post['service'] ?? '', 120);
    $message = reviews_sanitize_message($post['message'] ?? '', 650);
    $emailRaw = trim((string)($post['email'] ?? ''));
    $rating = reviews_validate_rating($post['rating'] ?? 0);
    $allowPublicName = !empty($post['allow_public_name']);

    $errors = [];

    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if ($city === '') {
        $errors[] = 'City is required.';
    }
    if ($service === '') {
        $errors[] = 'Service is required.';
    }
    if ($message === '') {
        $errors[] = 'Review message is required.';
    }
    if ($rating === 0) {
        $errors[] = 'Please select a rating between 1 and 5.';
    }

    $email = '';
    if ($emailRaw !== '') {
        $emailCandidate = filter_var($emailRaw, FILTER_VALIDATE_EMAIL);
        if ($emailCandidate === false) {
            $errors[] = 'Please provide a valid email address.';
        } else {
            $email = $emailCandidate;
        }
    } else {
        $errors[] = 'Email is required for moderation.';
    }

    if (!empty($errors)) {
        return ['errors' => $errors, 'record' => null];
    }

    $now = reviews_now_iso();
    $id = reviews_generate_id();

    $record = [
        'id' => $id,
        'name' => $name,
        'city' => $city,
        'service' => $service,
        'message' => $message,
        'rating' => $rating,
        'email' => $email,
        'allow_public_name' => $allowPublicName,
        'status' => 'pending',
        'ip' => $ip,
        'created_at' => $now,
        'updated_at' => $now,
    ];

    return ['errors' => [], 'record' => $record];
}

function reviews_is_rate_limited($pendingRecords, $clientIp, $cooldownSeconds = 900) {
    $nowTs = time();
    foreach (array_reverse($pendingRecords) as $record) {
        if (!is_array($record)) {
            continue;
        }

        if (($record['ip'] ?? '') !== $clientIp) {
            continue;
        }

        $createdAt = strtotime((string)($record['created_at'] ?? ''));
        if ($createdAt === false) {
            continue;
        }

        if (($nowTs - $createdAt) < $cooldownSeconds) {
            return true;
        }

        break;
    }

    return false;
}

function reviews_get_public_payload($record) {
    $displayName = reviews_build_public_name($record);

    return [
        'id' => (string)($record['id'] ?? ''),
        'name' => $displayName,
        'display_name' => $displayName,
        'avatar_text' => reviews_build_initials((string)($record['name'] ?? '')),
        'city' => (string)($record['city'] ?? ''),
        'service' => (string)($record['service'] ?? ''),
        'message' => (string)($record['message'] ?? ''),
        'rating' => (int)($record['rating'] ?? 5),
        'published_at' => (string)($record['published_at'] ?? ($record['updated_at'] ?? '')),
    ];
}

function reviews_admin_start_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('nc_reviews_admin');
    session_start();
}

function reviews_admin_get_credentials() {
    $envUser = trim((string)getenv('ADMIN_REVIEW_USER'));
    $envPassHash = trim((string)getenv('ADMIN_REVIEW_PASS_HASH'));

    if ($envUser !== '' && $envPassHash !== '') {
        return [
            'username' => $envUser,
            'password_hash' => $envPassHash,
            'password_plain' => null,
        ];
    }

    $configPath = __DIR__ . DIRECTORY_SEPARATOR . 'review-admin-config.php';
    if (is_file($configPath)) {
        $data = include $configPath;
        if (is_array($data)) {
            return [
                'username' => trim((string)($data['username'] ?? 'admin')),
                'password_hash' => trim((string)($data['password_hash'] ?? '')),
                'password_plain' => (string)($data['password_plain'] ?? ''),
            ];
        }
    }

    return [
        'username' => 'admin',
        'password_hash' => '',
        'password_plain' => 'ChangeMe123!',
    ];
}

function reviews_admin_is_authenticated() {
    reviews_admin_start_session();
    return !empty($_SESSION['reviews_admin_authenticated']);
}

function reviews_admin_attempt_login($username, $password) {
    $username = trim((string)$username);
    $password = (string)$password;

    $creds = reviews_admin_get_credentials();

    if ($username !== (string)$creds['username']) {
        return false;
    }

    $isValid = false;
    $hash = (string)($creds['password_hash'] ?? '');
    $plain = (string)($creds['password_plain'] ?? '');

    if ($hash !== '') {
        $isValid = password_verify($password, $hash);
    } elseif ($plain !== '') {
        $isValid = hash_equals($plain, $password);
    }

    if ($isValid) {
        reviews_admin_start_session();
        $_SESSION['reviews_admin_authenticated'] = true;
        $_SESSION['reviews_admin_username'] = $username;
    }

    return $isValid;
}

function reviews_admin_logout() {
    reviews_admin_start_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}
