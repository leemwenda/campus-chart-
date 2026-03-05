<?php
require_once __DIR__ . '/../config/auth.php';
$user   = requireLogin();
$action = $_GET['action'] ?? '';
header('Content-Type: application/json');

if ($action === 'mark_all_read') {
    DB::query("UPDATE notifications SET is_read=1 WHERE user_id=?", [$user['id']]);
    jsonResponse(['success'=>true]);
}
if ($action === 'mark_read') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) DB::query("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?", [$id,$user['id']]);
    jsonResponse(['success'=>true]);
}
jsonResponse(['success'=>false],400);
