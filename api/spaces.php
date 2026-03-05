<?php
require_once __DIR__ . '/../config/auth.php';
$user   = requireLogin();
$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? ($_GET['action'] ?? '');
header('Content-Type: application/json');

if ($action === 'toggle') {
    $spaceId = (int)($data['space_id'] ?? 0);
    if (!$spaceId) jsonResponse(['success'=>false]);
    $existing = DB::row("SELECT id FROM space_members WHERE space_id=? AND user_id=?", [$spaceId,$user['id']]);
    if ($existing) {
        DB::query("DELETE FROM space_members WHERE id=?", [$existing['id']]);
        $joined = false;
    } else {
        DB::insert("INSERT INTO space_members (space_id,user_id) VALUES (?,?)", [$spaceId,$user['id']]);
        $joined = true;
    }
    $count = DB::count("SELECT COUNT(*) FROM space_members WHERE space_id=?", [$spaceId]);
    jsonResponse(['success'=>true,'joined'=>$joined,'count'=>$count]);
}
jsonResponse(['success'=>false],400);
