<?php
require_once __DIR__ . '/../config/auth.php';
$user = requireLogin();
// Only admin/staff can create spaces — redirect to admin panel spaces tab
if (in_array($user['role'], ['admin', 'staff'])) {
    header('Location: ' . SITE_URL . '/admin/index.php?tab=spaces');
} else {
    header('Location: ' . SITE_URL . '/pages/spaces.php');
}
exit;
