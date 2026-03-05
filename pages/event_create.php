<?php
require_once __DIR__ . '/../config/auth.php';
$user = requireLogin();
// Only admin/staff can create events — redirect to admin panel events tab
if (in_array($user['role'], ['admin', 'staff'])) {
    header('Location: ' . SITE_URL . '/admin/index.php?tab=events');
} else {
    header('Location: ' . SITE_URL . '/pages/events.php');
}
exit;
