<?php
declare(strict_types=1);

function wantsJson(): bool {
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function failRequest(int $status, string $message): never {
    http_response_code($status);
    if (wantsJson()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => $message]);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
    }
    exit;
}

function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">';
}

function validateCsrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
        failRequest(403, 'Your form expired. Reload the page and try again.');
    }
}

function flash(string $message, string $type = 'success'): void {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function renderFlash(): string {
    $notice = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $notice ? '<div class="alert alert-' . h($notice['type']) . '">' . h($notice['message']) . '</div>' : '';
}

function requireText(string $key, int $maxLength, bool $required = true): string {
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) throw new InvalidArgumentException('Invalid ' . $key . '.');
    $value = trim($value);
    if (($required && $value === '') || strlen($value) > $maxLength || !preg_match('//u', $value)) {
        throw new InvalidArgumentException('Please enter a valid ' . str_replace('_', ' ', $key) . '.');
    }
    return $value;
}

function validDate(string $date, string $format = 'Y-m-d'): bool {
    $parsed = DateTimeImmutable::createFromFormat('!' . $format, $date);
    return $parsed !== false && $parsed->format($format) === $date;
}

function validPassword(string $password): bool {
    return strlen($password) >= 12 && strlen($password) <= 72;
}

function destroyLogin(): void {
    $_SESSION = [];
    if (PHP_SAPI !== 'cli') session_regenerate_id(true);
    csrfToken();
}

function accountRevision(string $role, int $id): string {
    $root = getenv('UPLOAD_ROOT') ?: dirname(__DIR__, 2) . '/ikusasa-storage';
    $file = rtrim($root, '/') . '/revocations/' . $role . '-' . $id;
    return is_file($file) ? (string) file_get_contents($file) : 'initial';
}

function revokeAccount(string $role, int $id): void {
    $root = getenv('UPLOAD_ROOT') ?: dirname(__DIR__, 2) . '/ikusasa-storage';
    $dir = rtrim($root, '/') . '/revocations';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('Session revocation is unavailable.');
    if (file_put_contents($dir . '/' . $role . '-' . $id, bin2hex(random_bytes(16)), LOCK_EX) === false) throw new RuntimeException('Session revocation is unavailable.');
}

function loginSession(array $user, string $role): void {
    session_regenerate_id(true);
    $_SESSION = [
        'user_id' => (int) $user['id'], 'user_type' => $role,
        'user_name' => $user['full_name'],
        'credential_version' => hash('sha256', $user['password_hash']),
        'last_activity' => time(),
        'account_revision' => accountRevision($role, (int) $user['id']),
    ];
    csrfToken();
}

// Limits are stored outside the web root and survive across PHP requests.
// Use a shared rate-limit service if the application is scaled to multiple instances.
function loginBucketKeys(string $role, string $username): array {
    return [
        'ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
        $role . ':' . strtolower($username),
    ];
}

function updateLoginBucket(string $key, callable $callback): array {
    $root = getenv('UPLOAD_ROOT') ?: dirname(__DIR__, 2) . '/ikusasa-storage';
    $dir = rtrim($root, '/') . '/rate-limits';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Login protection storage is unavailable.');
    }
    $file = fopen($dir . '/' . hash('sha256', $key) . '.json', 'c+');
    if (!$file || !flock($file, LOCK_EX)) throw new RuntimeException('Login protection is unavailable.');
    try {
        $state = json_decode(stream_get_contents($file), true) ?: ['start' => time(), 'failures' => 0];
        if (time() - $state['start'] >= 900) $state = ['start' => time(), 'failures' => 0];
        $state = $callback($state);
        rewind($file);
        ftruncate($file, 0);
        fwrite($file, json_encode($state));
        fflush($file);
        return $state;
    } finally {
        flock($file, LOCK_UN);
        fclose($file);
    }
}

function loginIsLimited(string $role, string $username): bool {
    foreach (loginBucketKeys($role, $username) as $index => $key) {
        $state = updateLoginBucket($key, fn($state) => $state);
        if ($state['failures'] >= ($index === 0 ? 30 : 5)) return true;
    }
    return false;
}

function recordLoginFailure(string $role, string $username): void {
    foreach (loginBucketKeys($role, $username) as $key) {
        updateLoginBucket($key, function ($state) { $state['failures']++; return $state; });
    }
}

function clearAccountLoginFailures(string $role, string $username): void {
    $key = loginBucketKeys($role, $username)[1];
    updateLoginBucket($key, fn($state) => ['start' => time(), 'failures' => 0]);
}

if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $secure = getenv('SESSION_SECURE') === '1' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params(['httponly' => true, 'secure' => $secure, 'samesite' => 'Lax', 'path' => '/']);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cache-Control: private, no-store');
    set_exception_handler(function (Throwable $error): void {
        error_log((string) $error);
        failRequest($error instanceof InvalidArgumentException ? 422 : 500,
            $error instanceof InvalidArgumentException ? $error->getMessage() : 'Unable to complete this request. Please try again.');
    });
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') validateCsrf();
}
