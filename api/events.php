<?php
// api/events.php
require_once __DIR__ . '/../config/auth.php';
$user   = requireLogin();
$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? ($_GET['action'] ?? '');
header('Content-Type: application/json');

if ($action === 'rsvp') {
    $eventId = (int)($data['event_id'] ?? 0);
    if (!$eventId) jsonResponse(['success'=>false]);
    $existing = DB::row("SELECT id FROM event_rsvps WHERE event_id=? AND user_id=?", [$eventId,$user['id']]);
    if ($existing) {
        DB::query("DELETE FROM event_rsvps WHERE id=?", [$existing['id']]);
        $going = false;
    } else {
        DB::insert("INSERT INTO event_rsvps (event_id,user_id,status) VALUES (?,?,'going')", [$eventId,$user['id']]);
        $going = true;
    }
    $count = DB::count("SELECT COUNT(*) FROM event_rsvps WHERE event_id=? AND status='going'", [$eventId]);
    jsonResponse(['success'=>true,'going'=>$going,'count'=>$count]);
}
jsonResponse(['success'=>false,'message'=>'Unknown action'],400);
