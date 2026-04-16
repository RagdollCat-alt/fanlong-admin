<?php
require_once '../config.php';
checkLogin();
header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    $today = date('Y-m-d');

    $users       = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $items       = $db->query("SELECT COUNT(*) FROM items")->fetchColumn();
    $today_users = $db->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at)=?")->execute([$today]) ? $db->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at)=?")->execute([$today]) : 0;
    // Re-fetch properly
    $s = $db->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at)=?");
    $s->execute([$today]);
    $today_users = $s->fetchColumn();

    $admins      = $db->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    $families    = 0;
    try { $families = $db->query("SELECT COUNT(*) FROM families WHERE is_deleted=0")->fetchColumn(); } catch(Exception $e){}
    $fund_types  = 0;
    try { $fund_types = $db->query("SELECT COUNT(*) FROM fund_types WHERE is_active=1")->fetchColumn(); } catch(Exception $e){}

    echo json_encode([
        'users'       => intval($users),
        'items'       => intval($items),
        'today_users' => intval($today_users),
        'admins'      => intval($admins),
        'families'    => intval($families),
        'fund_types'  => intval($fund_types),
        'time'        => date('H:i:s'),
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'time' => date('H:i:s')]);
}
