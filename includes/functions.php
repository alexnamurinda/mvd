<?php
/**
 * Global helper functions
 */

// Escape HTML output
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Format money with currency
function money($amount, $currency = null) {
    $currency = $currency ?: ($_SESSION['currency'] ?? 'UGX');
    return $currency . ' ' . number_format((float)$amount, 0);
}

// Friendly date "Today, Yesterday, Mon 12"
function friendlyDate($date) {
    if (!$date) return '—';
    $ts = strtotime($date);
    $today = strtotime(date('Y-m-d'));
    $yesterday = strtotime('-1 day', $today);
    $tomorrow = strtotime('+1 day', $today);
    if (date('Y-m-d', $ts) === date('Y-m-d', $today))     return 'Today';
    if (date('Y-m-d', $ts) === date('Y-m-d', $yesterday)) return 'Yesterday';
    if (date('Y-m-d', $ts) === date('Y-m-d', $tomorrow))  return 'Tomorrow';
    return date('D, M j', $ts);
}

// Time-ago
function timeAgo($datetime) {
    if (!$datetime) return '';
    $diff = time() - strtotime($datetime);
    if ($diff < 60)      return $diff . 's ago';
    if ($diff < 3600)    return floor($diff/60) . 'm ago';
    if ($diff < 86400)   return floor($diff/3600) . 'h ago';
    if ($diff < 2592000) return floor($diff/86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

// Auth check — redirect if not logged in
function requireLogin() {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Current user id
function uid() { return (int)($_SESSION['user_id'] ?? 0); }

// Flash messages
function flash($key, $value = null) {
    if ($value === null) {
        $v = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $v;
    }
    $_SESSION['flash'][$key] = $value;
}

// Generate unique receipt number
function generateReceiptNo($prefix = 'RCP') {
    return $prefix . '-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

// Generate voucher code
function generateVoucherCode($len = 8) {
    $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // no confusing chars
    $code = '';
    for ($i=0; $i<$len; $i++) $code .= $chars[random_int(0, strlen($chars)-1)];
    return $code;
}

// Log activity
function logActivity($pdo, $userId, $action, $details = '', $icon = 'bi-activity') {
    $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, icon) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $details, $icon]);
}

// CSRF
function csrfToken() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function verifyCsrf($token) {
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$token);
}

// Default business id for the current user (the WiFi one)
function defaultBusinessId($pdo) {
    static $cache = null;
    if ($cache !== null) return $cache;
    $stmt = $pdo->prepare("SELECT id FROM businesses WHERE user_id = ? AND is_active = 1 ORDER BY id ASC LIMIT 1");
    $stmt->execute([uid()]);
    $cache = (int)($stmt->fetchColumn() ?: 0);
    return $cache;
}

// Sum helper
function sumQuery($pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (float)$stmt->fetchColumn();
}

// Compute next scheduled occurrence of a recurring expense (returns DateTime >= today, or null)
function nextRecurringDate($expenseDate, $period) {
    if (!$period || !$expenseDate) return null;
    $base  = new DateTime($expenseDate);
    $today = new DateTime(date('Y-m-d'));
    while ($base < $today) {
        switch ($period) {
            case 'daily':   $base->modify('+1 day');   break;
            case 'weekly':  $base->modify('+7 days');  break;
            case 'monthly': $base->modify('+1 month'); break;
            case 'yearly':  $base->modify('+1 year');  break;
            default: return null;
        }
    }
    return $base;
}

// Count recurring expenses whose next due date falls within $days days from today
function getRecurringDueSoon($pdo, $userId, $days = 7) {
    $stmt = $pdo->prepare("SELECT expense_date, recurring_period FROM expenses WHERE user_id=? AND is_recurring=1 AND recurring_period IS NOT NULL");
    $stmt->execute([$userId]);
    $rows      = $stmt->fetchAll();
    $today     = new DateTime(date('Y-m-d'));
    $threshold = (clone $today)->modify("+{$days} days");
    $count = 0;
    foreach ($rows as $r) {
        $next = nextRecurringDate($r['expense_date'], $r['recurring_period']);
        if ($next !== null && $next <= $threshold) $count++;
    }
    return $count;
}
