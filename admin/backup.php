<?php
require_once 'config.php';
checkLogin();

// 只有超级管理员可以备份
if (!isSuperAdmin()) {
    die('<div class="alert alert-danger">只有超级管理员可以执行备份操作。</div>');
}

$db = getDB();
$message = '';
$message_type = '';

// 处理备份请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    try {
        $backup_dir = dirname(__FILE__) . '/backups';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $timestamp = date('Ymd_His');
        $backup_file = $backup_dir . '/fanlong_backup_' . $timestamp . '.sqlite';
        
        // 复制数据库文件
        if (copy(DB_PATH, $backup_file)) {
            // 压缩备份（可选）
            $zip_file = $backup_dir . '/fanlong_backup_' . $timestamp . '.zip';
            $zip = new ZipArchive();
            if ($zip->open($zip_file, ZipArchive::CREATE) === TRUE) {
                $zip->addFile($backup_file, 'fanlong.db');
                $zip->close();
                unlink($backup_file); // 删除原始备份文件
                $backup_file = $zip_file;
            }
            
            $message = '备份创建成功：' . basename($backup_file);
            $message_type = 'success';
        } else {
            throw new Exception('无法复制数据库文件');
        }
    } catch (Exception $e) {
        $message = '备份失败: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// 获取现有备份文件
$backup_dir = dirname(__FILE__) . '/backups';
$backups = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && (preg_match('/\.(sqlite|zip)$/i', $file))) {
            $filepath = $backup_dir . '/' . $file;
            $backups[] = [
                'name' => $file,
                'path' => $filepath,
                'size' => filesize($filepath),
                'time' => filemtime($filepath)
            ];
        }
    }
    // 按时间倒序排序
    usort($backups, function($a, $b) {
        return $b['time'] - $a['time'];
    });
}

$page_title = '数据备份';
$page_icon = 'fas fa-download';
$page_subtitle = '数据库备份与恢复';
require_once 'header.php';
?>

<!-- 显示消息 -->
<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
    <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- 备份操作 -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-database me-2"></i>创建备份
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>注意：</strong>备份操作将复制当前数据库文件。建议在低峰期执行。
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="create_backup" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">备份选项</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="compress" name="compress" value="1" checked>
                            <label class="form-check-label" for="compress">压缩备份文件（ZIP格式）</label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">备份说明</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="可选：添加备份说明"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save me-1"></i>立即创建备份
                    </button>
                </form>
                
                <div class="mt-4">
                    <h6>数据库信息：</h6>
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between">
                            <span>数据库路径</span>
                            <code><?php echo htmlspecialchars(DB_PATH); ?></code>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>数据库大小</span>
                            <span><?php echo file_exists(DB_PATH) ? round(filesize(DB_PATH) / 1024 / 1024, 2) : 0; ?> MB</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>备份目录</span>
                            <code><?php echo htmlspecialchars($backup_dir); ?></code>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 备份列表 -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-history me-2"></i>历史备份
                </div>
                <div>
                    <span class="badge bg-primary"><?php echo count($backups); ?> 个备份</span>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($backups)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">暂无备份文件</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>文件名</th>
                                <th>大小</th>
                                <th>时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $backup): ?>
                            <tr>
                                <td>
                                    <code><?php echo htmlspecialchars($backup['name']); ?></code>
                                </td>
                                <td><?php echo round($backup['size'] / 1024, 2); ?> KB</td>
                                <td><?php echo date('Y-m-d H:i:s', $backup['time']); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="backups/<?php echo urlencode($backup['name']); ?>" 
                                           class="btn btn-outline-primary" download title="下载">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <button class="btn btn-outline-danger btn-delete" 
                                                onclick="deleteBackup('<?php echo urlencode($backup['name']); ?>')" title="删除">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 备份建议 -->
        <div class="card mt-4">
            <div class="card-header">
                <i class="fas fa-lightbulb me-2"></i>备份建议
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>建议每天自动备份一次</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>重要更新前手动创建备份</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>保留最近7天的备份文件</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>定期下载备份到本地存储</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- 恢复功能（高级） -->
<div class="card mt-4">
    <div class="card-header">
        <i class="fas fa-upload me-2"></i>数据恢复（谨慎操作）
    </div>
    <div class="card-body">
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>警告：</strong>数据恢复将覆盖当前数据库，可能导致数据丢失。请务必先创建备份！
        </div>
        
        <form method="POST" action="restore.php" enctype="multipart/form-data" onsubmit="return confirm('确定要恢复数据库吗？这将覆盖所有现有数据！')">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="backup_file" class="form-label">选择备份文件</label>
                    <input type="file" class="form-control" id="backup_file" name="backup_file" accept=".sqlite,.zip">
                    <div class="form-text">支持 .sqlite 或 .zip 格式</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">恢复选项</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="verify_backup" name="verify_backup" value="1" checked>
                        <label class="form-check-label" for="verify_backup">恢复前验证备份文件完整性</label>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-danger w-100">
                <i class="fas fa-upload me-1"></i>执行数据恢复
            </button>
        </form>
    </div>
</div>

<script>
function deleteBackup(filename) {
    if (confirm('确定要删除备份文件 ' + filename + ' 吗？')) {
        window.location.href = 'backup_delete.php?file=' + encodeURIComponent(filename);
    }
}
</script>

<?php
require_once 'footer.php';
?>