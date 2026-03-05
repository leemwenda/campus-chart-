<?php
// api/posts.php
require_once __DIR__ . '/../config/auth.php';
$user = requireLogin();
$action = $_GET['action'] ?? ($_POST['action'] ?? (json_decode(file_get_contents('php://input'),true)['action'] ?? ''));

header('Content-Type: application/json');

if ($action === 'react') {
    $data = json_decode(file_get_contents('php://input'), true);
    $postId = (int)($data['post_id'] ?? 0);
    $reaction = $data['reaction'] ?? 'like';
    if (!$postId) jsonResponse(['success'=>false,'message'=>'Invalid post']);
    $existing = DB::row("SELECT id FROM post_reactions WHERE post_id=? AND user_id=? AND reaction=?", [$postId,$user['id'],$reaction]);
    if ($existing) {
        DB::query("DELETE FROM post_reactions WHERE id=?", [$existing['id']]);
        $liked = false;
    } else {
        DB::insert("INSERT INTO post_reactions (post_id,user_id,reaction) VALUES (?,?,?)", [$postId,$user['id'],$reaction]);
        $liked = true;
        // Notify post author
        $post = DB::row("SELECT user_id FROM posts WHERE id=?", [$postId]);
        if ($post && (int)$post['user_id'] !== (int)$user['id']) {
            DB::insert("INSERT INTO notifications (user_id,actor_id,type,reference_id,reference_type,message) VALUES (?,?,?,?,?,?)",
                [$post['user_id'],$user['id'],'post_like',$postId,'post', sanitize($user['full_name']) . ' liked your post']);
        }
    }
    $count = DB::count("SELECT COUNT(*) FROM post_reactions WHERE post_id=? AND reaction=?", [$postId,$reaction]);
    jsonResponse(['success'=>true,'liked'=>$liked,'count'=>$count]);
}

if ($action === 'comments') {
    $postId = (int)($_GET['post_id'] ?? 0);
    if (!$postId) jsonResponse(['success'=>false]);
    $comments = DB::rows(
        "SELECT c.*, u.full_name, u.id as author_id, u.avatar FROM comments c
         JOIN users u ON u.id=c.user_id WHERE c.post_id=? ORDER BY c.created_at ASC LIMIT 50", [$postId]);
    $out = array_map(fn($c) => [
        'id'       => $c['id'],
        'author'   => $c['full_name'],
        'initials' => avatarInitials($c['full_name']),
        'color'    => avatarColor($c['author_id']),
        'content'  => $c['content'],
        'time_ago' => timeAgo($c['created_at']),
        'avatar'   => $c['avatar'] ? UPLOAD_URL . $c['avatar'] : null,
    ], $comments);
    jsonResponse(['success'=>true,'comments'=>$out]);
}

if ($action === 'comment') {
    $data    = json_decode(file_get_contents('php://input'), true);
    $postId  = (int)($data['post_id'] ?? 0);
    $content = trim($data['content'] ?? '');
    if (!$postId || !$content) jsonResponse(['success'=>false,'message'=>'Empty comment']);
    $id = DB::insert("INSERT INTO comments (post_id,user_id,content) VALUES (?,?,?)", [$postId,$user['id'],$content]);
    // Notify post author
    $post = DB::row("SELECT user_id FROM posts WHERE id=?", [$postId]);
    if ($post && (int)$post['user_id'] !== (int)$user['id']) {
        DB::insert("INSERT INTO notifications (user_id,actor_id,type,reference_id,reference_type,message) VALUES (?,?,?,?,?,?)",
            [$post['user_id'],$user['id'],'comment',$postId,'post', sanitize($user['full_name']) . ' commented on your post']);
    }
    $count = DB::count("SELECT COUNT(*) FROM comments WHERE post_id=?", [$postId]);
    jsonResponse(['success'=>true,'count'=>$count,'comment'=>[
        'id'       => $id,
        'author'   => $user['full_name'],
        'initials' => avatarInitials($user['full_name']),
        'color'    => avatarColor($user['id']),
        'content'  => $content,
        'time_ago' => 'Just now',
        'avatar'   => $user['avatar'] ? UPLOAD_URL . $user['avatar'] : null,
    ]]);
}

// Create post (form submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$action) {
    $content  = trim($_POST['content'] ?? '');
    $tag      = $_POST['tag'] ?? 'academic';
    $spaceId  = (int)($_POST['space_id'] ?? 0) ?: null;
    $validTags = ['academic','social','administrative','urgent','event','announcement'];
    if (!in_array($tag, $validTags)) $tag = 'academic';
    if (!$content) jsonResponse(['success'=>false,'message'=>'Post content cannot be empty']);
    $id = DB::insert("INSERT INTO posts (user_id,space_id,content,tag) VALUES (?,?,?,?)", [$user['id'],$spaceId,$content,$tag]);
    jsonResponse(['success'=>true,'post_id'=>$id]);
}

jsonResponse(['success'=>false,'message'=>'Unknown action'], 400);
