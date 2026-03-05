<?php
// api/members.php
require_once __DIR__ . '/../config/auth.php';
$user   = requireLogin();
$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? ($_GET['action'] ?? '');
header('Content-Type: application/json');

if ($action === 'follow') {
    $targetId = (int)($data['user_id'] ?? 0);
    if (!$targetId || (int)$targetId === (int)$user['id']) jsonResponse(['success'=>false]);
    $existing = DB::row("SELECT id FROM follows WHERE follower_id=? AND following_id=?", [$user['id'],$targetId]);
    if ($existing) {
        DB::query("DELETE FROM follows WHERE id=?", [$existing['id']]);
        $following = false;
    } else {
        DB::insert("INSERT INTO follows (follower_id,following_id) VALUES (?,?)", [$user['id'],$targetId]);
        $following = true;
        DB::insert("INSERT INTO notifications (user_id,actor_id,type,reference_id,reference_type,message) VALUES (?,?,?,?,?,?)",
            [$targetId,$user['id'],'new_post',$user['id'],'user', sanitize($user['full_name']).' is now following you']);
    }
    jsonResponse(['success'=>true,'following'=>$following]);
}
jsonResponse(['success'=>false],400);
