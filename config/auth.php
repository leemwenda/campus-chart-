<?php
// ============================================================
// KCA CHART - Auth & Session Helpers
// ============================================================
require_once __DIR__ . '/db.php';

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        session_set_cookie_params(SESSION_LIFETIME);
        session_start();
    }
}

function currentUser(): ?array {
    startSession();
    if (empty($_SESSION['user_id'])) return null;
    return DB::row("SELECT id, full_name, email, student_id, role, department, course, year_of_study, bio, avatar, is_online FROM users WHERE id = ? AND is_active = 1", [$_SESSION['user_id']]);
}

function requireLogin(): array {
    $user = currentUser();
    if (!$user) {
        header('Location: ' . SITE_URL . '/index.php');
        exit;
    }
    return $user;
}

function requireRole(string ...$roles): array {
    $user = requireLogin();
    if (!in_array($user['role'], $roles)) {
        header('Location: ' . SITE_URL . '/pages/feed.php');
        exit;
    }
    return $user;
}

function login(string $identifier, string $password): array {
    $identifier = trim($identifier);
    // Accept Student ID OR email (both @kcau.ac.ke and @students.kcau.ac.ke)
    $user = DB::row(
        "SELECT * FROM users WHERE (student_id = ? OR email = ?) AND is_active = 1",
        [$identifier, $identifier]
    );
    if (!$user) {
        return ['success' => false, 'message' => 'No account found with that Student ID or email address.'];
    }
    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Incorrect password. Please try again.'];
    }
    startSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role']    = $user['role'];
    DB::query("UPDATE users SET is_online = 1, last_seen = NOW() WHERE id = ?", [$user['id']]);
    return ['success' => true, 'role' => $user['role']];
}

function logout(): void {
    startSession();
    if (!empty($_SESSION['user_id'])) {
        DB::query("UPDATE users SET is_online = 0, last_seen = NOW() WHERE id = ?", [$_SESSION['user_id']]);
    }
    session_destroy();
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

function avatarInitials(string $name): string {
    $parts = explode(' ', trim($name));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $initials .= strtoupper($p[0] ?? '');
    }
    return $initials ?: 'U';
}

function avatarColor(int $userId): string {
    $colors = ['#003087','#0057B8','#1565C0','#00695C','#4527A0','#283593','#1B5E20','#BF360C','#880E4F','#0277BD'];
    return $colors[$userId % count($colors)];
}

function timeAgo(string $datetime): string {
    $now  = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->getTimestamp() - $past->getTimestamp();
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff/60) . 'm ago';
    if ($diff < 86400)  return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return $past->format('M j, Y');
}

function sanitize(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function redirect(string $path): never {
    header('Location: ' . SITE_URL . '/' . ltrim($path, '/'));
    exit;
}

function csrfToken(): string {
    startSession();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verifyCsrf(string $token): bool {
    startSession();
    return hash_equals($_SESSION['csrf'] ?? '', $token);
}

function unreadNotifCount(int $userId): int {
    return DB::count("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [$userId]);
}

function unreadMessageCount(int $userId): int {
    return DB::count(
        "SELECT COUNT(*) FROM messages m
         JOIN conversation_participants cp ON cp.conversation_id = m.conversation_id
         WHERE cp.user_id = ? AND m.sender_id != ? AND m.is_read = 0",
        [$userId, $userId]
    );
}
