<?php
require_once __DIR__ . '/../config/auth.php';
$user   = requireLogin();
$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? ($_GET['action'] ?? '');
header('Content-Type: application/json');

if ($action === 'load') {
    $convId = (int)($_GET['conv_id'] ?? 0);
    $afterId = (int)($_GET['after'] ?? 0);
    if (!$convId) jsonResponse(['success'=>false]);
    // Verify user is a participant
    $part = DB::row("SELECT id FROM conversation_participants WHERE conversation_id=? AND user_id=?", [$convId,$user['id']]);
    if (!$part) jsonResponse(['success'=>false,'message'=>'Access denied']);
    $msgs = DB::rows(
        "SELECT m.*, u.full_name, u.id as sender_id_real, u.avatar FROM messages m
         JOIN users u ON u.id=m.sender_id
         WHERE m.conversation_id=? AND m.id > ? ORDER BY m.sent_at ASC",
        [$convId, $afterId]
    );
    // Mark as read
    DB::query("UPDATE messages SET is_read=1 WHERE conversation_id=? AND sender_id!=? AND is_read=0", [$convId,$user['id']]);
    $out = array_map(fn($m) => [
        'id'       => $m['id'],
        'content'  => $m['content'],
        'is_own'   => (int)$m['sender_id'] === (int)$user['id'],
        'initials' => avatarInitials($m['full_name']),
        'color'    => avatarColor($m['sender_id_real']),
        'time'     => date('g:i A', strtotime($m['sent_at'])),
        'avatar'   => $m['avatar'] ? UPLOAD_URL . $m['avatar'] : null,
    ], $msgs);
    jsonResponse(['success'=>true,'messages'=>$out]);
}

if ($action === 'send') {
    $convId  = (int)($data['conv_id'] ?? 0);
    $content = trim($data['content'] ?? '');
    if (!$convId || !$content) jsonResponse(['success'=>false,'message'=>'Invalid data']);
    $part = DB::row("SELECT id FROM conversation_participants WHERE conversation_id=? AND user_id=?", [$convId,$user['id']]);
    if (!$part) jsonResponse(['success'=>false,'message'=>'Access denied']);
    $msgId = DB::insert("INSERT INTO messages (conversation_id,sender_id,content) VALUES (?,?,?)", [$convId,$user['id'],$content]);
    // Notify other participant(s) — only if no unread message notification already exists for this conversation
    $others = DB::rows("SELECT user_id FROM conversation_participants WHERE conversation_id=? AND user_id!=?", [$convId,$user['id']]);
    foreach ($others as $o) {
        $existingNotif = DB::row(
            "SELECT id FROM notifications WHERE user_id=? AND actor_id=? AND type='message' AND reference_id=? AND is_read=0",
            [$o['user_id'], $user['id'], $convId]
        );
        if (!$existingNotif) {
            DB::insert("INSERT INTO notifications (user_id,actor_id,type,reference_id,reference_type,message) VALUES (?,?,?,?,?,?)",
                [$o['user_id'],$user['id'],'message',$convId,'conversation', sanitize($user['full_name']).' sent you a message']);
        }
    }
    jsonResponse(['success'=>true,'message'=>[
        'id'       => $msgId,
        'content'  => $content,
        'is_own'   => true,
        'initials' => avatarInitials($user['full_name']),
        'color'    => avatarColor($user['id']),
        'time'     => date('g:i A'),
    ]]);
}

if ($action === 'start') {
    $targetId = (int)($data['user_id'] ?? 0);
    if (!$targetId || $targetId === $user['id']) jsonResponse(['success'=>false]);
    // Check if conversation already exists
    $existing = DB::row(
        "SELECT cp1.conversation_id FROM conversation_participants cp1
         JOIN conversation_participants cp2 ON cp2.conversation_id=cp1.conversation_id AND cp2.user_id=?
         WHERE cp1.user_id=? LIMIT 1",
        [$targetId, $user['id']]
    );
    if ($existing) {
        jsonResponse(['success'=>true,'conv_id'=>$existing['conversation_id']]);
    }
    $convId = DB::insert("INSERT INTO conversations () VALUES ()");
    DB::insert("INSERT INTO conversation_participants (conversation_id,user_id) VALUES (?,?)", [$convId,$user['id']]);
    DB::insert("INSERT INTO conversation_participants (conversation_id,user_id) VALUES (?,?)", [$convId,$targetId]);
    jsonResponse(['success'=>true,'conv_id'=>$convId]);
}

jsonResponse(['success'=>false],400);
