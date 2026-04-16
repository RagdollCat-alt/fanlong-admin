<?php
require_once 'config.php';
checkLogin();

if (!isSuperAdmin()) {
    die('只有超级管理员可以执行此操作。');
}

$db = getDB();
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_cleanup'])) {
    try {
        $db->beginTransaction();
        
        // 清理 users 表
        $stmt = $db->prepare("UPDATE users SET currency = REPLACE(REPLACE(currency, '&quot;', '\"'), '&amp;quot;', '\"') WHERE currency LIKE '%&quot;%' OR currency LIKE '%&amp;quot;%'");
        $users_currency = $stmt->execute();
        $stmt = $db->prepare("UPDATE users SET profile = REPLACE(REPLACE(profile, '&quot;', '\"'), '&amp;quot;', '\"') WHERE profile LIKE '%&quot;%' OR profile LIKE '%&amp;quot;%'");
        $users_profile = $stmt->execute();
        $stmt = $db->prepare("UPDATE users SET limits = REPLACE(REPLACE(limits, '&quot;', '\"'), '&amp;quot;', '\"') WHERE limits LIKE '%&quot;%' OR limits LIKE '%&amp;quot;%'");
        $users_limits = $stmt->execute();
        
        // 清理 items 表
        $stmt = $db->prepare("UPDATE items SET stats = REPLACE(REPLACE(stats, '&quot;', '\"'), '&amp;quot;', '\"') WHERE stats LIKE '%&quot;%' OR stats LIKE '%&amp;quot;%'");
        $items_stats = $stmt->execute();
        $stmt = $db->prepare("UPDATE items SET effect = REPLACE(REPLACE(effect, '&quot;', '\"'), '&amp;quot;', '\"') WHERE effect LIKE '%&quot;%' OR effect LIKE '%&amp;quot;%'");
        $items_effect = $stmt->execute();
        
        // 清理 game_config 表
        $stmt = $db->prepare("UPDATE game_config SET value = REPLACE(REPLACE(value, '&quot;', '\"'), '&amp;quot;', '\"') WHERE value LIKE '%&quot;%' OR value LIKE '%&amp;quot;%'");
        $config_value = $stmt->execute();
        
        $db->commit();
        
        $message = '数据库清理完成。已修复可能被 HTML 实体转义损坏的 JSON 字段。';
        $message_type = 'success';
        
    } catch (Exception $e) {
        $db->rollBack();
        $message = '清理失败: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

$page_title = '数据库清理工具';
$page_icon = 'fas fa-broom';
$page_subtitle = '修复 JSON 字段中的 HTML 实体转义';
require_once 'header.php';
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-broom me-2"></i>数据库 JSON 字段清理工具
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <h5><i class="fas fa-info-circle me-2"></i>功能说明</h5>
            <p>此工具用于修复因 PHP 后台表单转义导致的 JSON 字段损坏问题。</p>
            <p>主要问题：<code>htmlspecialchars()</code> 将双引号 <code>"</code> 转义为 <code>&amp;quot;</code>，导致机器人无法解析 JSON。</p>
            <p>本工具将执行以下清理操作：</p>
            <ul>
                <li><strong>users 表</strong>: currency, profile, limits 字段中的 <code>&amp;quot;</code> 和 <code>&amp;amp;quot;</code> 还原为 <code>"</code></li>
                <li><strong>items 表</strong>: stats, effect 字段中的 HTML 实体还原</li>
                <li><strong>game_config 表</strong>: value 字段中的 HTML 实体还原</li>
            </ul>
            <p class="mb-0"><strong>注意：</strong>执行前建议先备份数据库（使用 <a href="backup.php">备份功能</a>）。</p>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="text-center py-5">
            <form method="POST" action="">
                <input type="hidden" name="run_cleanup" value="1">
                <button type="submit" class="btn btn-lg btn-warning" onclick="return confirm('确定要执行数据库清理吗？请确保已备份。')">
                    <i class="fas fa-play-circle me-2"></i>执行 JSON 字段清理
                </button>
            </form>
            <p class="text-muted mt-3">点击按钮将立即执行清理操作，不可撤销。</p>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <i class="fas fa-code me-2"></i>手动 SQL 语句（供参考）
            </div>
            <div class="card-body">
                <pre class="bg-light p-3 rounded"><code>-- 清理 users 表
UPDATE users SET currency = REPLACE(REPLACE(currency, '&amp;quot;', '"'), '&amp;amp;quot;', '"') WHERE currency LIKE '%&amp;quot;%' OR currency LIKE '%&amp;amp;quot;%';
UPDATE users SET profile = REPLACE(REPLACE(profile, '&amp;quot;', '"'), '&amp;amp;quot;', '"') WHERE profile LIKE '%&amp;quot;%' OR profile LIKE '%&amp;amp;quot;%';
UPDATE users SET limits = REPLACE(REPLACE(limits, '&amp;quot;', '"'), '&amp;amp;quot;', '"') WHERE limits LIKE '%&amp;quot;%' OR limits LIKE '%&amp;amp;quot;%';

-- 清理 items 表
UPDATE items SET stats = REPLACE(REPLACE(stats, '&amp;quot;', '"'), '&amp;amp;quot;', '"') WHERE stats LIKE '%&amp;quot;%' OR stats LIKE '%&amp;amp;quot;%';
UPDATE items SET effect = REPLACE(REPLACE(effect, '&amp;quot;', '"'), '&amp;amp;quot;', '"') WHERE effect LIKE '%&amp;quot;%' OR effect LIKE '%&amp;amp;quot;%';

-- 清理 game_config 表
UPDATE game_config SET value = REPLACE(REPLACE(value, '&amp;quot;', '"'), '&amp;amp;quot;', '"') WHERE value LIKE '%&amp;quot;%' OR value LIKE '%&amp;amp;quot;%';</code></pre>
            </div>
        </div>
    </div>
</div>

<div class="text-center mt-4">
    <a href="index.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>返回首页
    </a>
</div>

<?php
require_once 'footer.php';
?>