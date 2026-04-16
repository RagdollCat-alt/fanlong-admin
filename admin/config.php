<?php
/**
 * 繁笼机器人 B 端后台 - 配置文件
 * 请根据您的实际路径修改以下常量
 */

// 数据库路径 (请确保宝塔面板的 PHP 用户有读写权限)
define('DB_PATH', 'C:/Users/Administrator/Desktop/青果核-py代码/plugin/app/fanlong_core/data/fanlong.db');
// 或者使用相对路径（如果后台放在机器人目录内）：
// define('DB_PATH', dirname(__DIR__) . '/fanlong_core/data/fanlong.db');

// 网站根目录 URL (用于资源引用)
define('BASE_URL', 'http://122.51.158.182/admin/'); // 请修改为实际访问地址

// 会话设置
session_start();

// 错误报告 (开发时开启，上线后关闭)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 数据库连接函数
function getDB() {
    static $db = null;
    if ($db === null) {
        try {
            $db = new PDO('sqlite:' . DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            // 设置外键支持
            $db->exec('PRAGMA foreign_keys = ON;');
        } catch (PDOException $e) {
            die("数据库连接失败: " . $e->getMessage() . "<br>请检查 config.php 中的 DB_PATH 设置。");
        }
    }
    return $db;
}

// 检查管理员登录状态
function checkLogin() {
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_level'])) {
        header('Location: login.php');
        exit();
    }
}

// 权限检查 (level >= 999 为超级管理员)
function isSuperAdmin() {
    return isset($_SESSION['admin_level']) && $_SESSION['admin_level'] >= 999;
}

// 安全过滤函数
function safeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// 获取所有表名 (用于导航)
function getAllTables() {
    $db = getDB();
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}

// 安全 JSON 解码函数，处理可能包含 HTML 实体的 JSON 字符串
function safeJsonDecode($json_string, $assoc = true) {
    if (empty($json_string)) {
        return $assoc ? [] : null;
    }
    
    // 尝试直接解码
    $result = json_decode($json_string, $assoc);
    if ($result !== null) {
        return $result;
    }
    
    // 如果失败，尝试去除 HTML 实体后再解码
    $decoded = htmlspecialchars_decode($json_string, ENT_QUOTES);
    $result = json_decode($decoded, $assoc);
    if ($result !== null) {
        return $result;
    }
    
    // 如果仍然失败，尝试替换常见的 HTML 实体
    $cleaned = str_replace(['&quot;', '&amp;quot;', '&#34;'], '"', $json_string);
    $result = json_decode($cleaned, $assoc);
    if ($result !== null) {
        return $result;
    }
    
    // 如果所有尝试都失败，返回空数组或 null
    return $assoc ? [] : null;
}
?>