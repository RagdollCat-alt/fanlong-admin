<?php
require_once 'config.php';
checkLogin();

$db = getDB();
$action = $_GET['action'] ?? 'list';
$key = $_GET['key'] ?? '';
$message = '';
$message_type = '';

// 处理删除请求
if ($action === 'delete' && !empty($key) && isSuperAdmin()) {
    try {
        $stmt = $db->prepare("DELETE FROM game_config WHERE key = ?");
        $stmt->execute([$key]);
        $message = '配置项删除成功';
        $message_type = 'success';
        $action = 'list';
    } catch (Exception $e) {
        $message = '删除失败: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// 处理保存请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    $key = safeInput($_POST['key'] ?? '');
    $value = trim($_POST['value'] ?? '');
    $desc = safeInput($_POST['desc'] ?? '');
    
    $original_key = safeInput($_POST['original_key'] ?? '');
    
    try {
        if (empty($original_key)) {
            // 新增配置
            $stmt = $db->prepare("INSERT INTO game_config (key, value, desc) VALUES (?, ?, ?)");
            $stmt->execute([$key, $value, $desc]);
            $message = '配置项添加成功';
        } else {
            // 更新配置
            $stmt = $db->prepare("UPDATE game_config SET key = ?, value = ?, desc = ? WHERE key = ?");
            $stmt->execute([$key, $value, $desc, $original_key]);
            $message = '配置项更新成功';
        }
        $message_type = 'success';
        
        // 重定向到列表页
        header('Location: game_config.php?message=' . urlencode($message) . '&type=' . $message_type);
        exit();
    } catch (Exception $e) {
        $message = '保存失败: ' . $e->getMessage();
        $message_type = 'danger';
        $action = 'edit';
    }
}

// 批量更新请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update'])) {
    try {
        $updates = $_POST['updates'] ?? [];
        foreach ($updates as $config_key => $config_value) {
            $stmt = $db->prepare("UPDATE game_config SET value = ? WHERE key = ?");
            $stmt->execute([trim($config_value), safeInput($config_key)]);
        }
        $message = '批量更新成功';
        $message_type = 'success';
    } catch (Exception $e) {
        $message = '批量更新失败: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// 根据action显示不同页面
if ($action === 'view' || $action === 'edit') {
    // 查看或编辑单个配置
    $config = null;
    if (!empty($key)) {
        $stmt = $db->prepare("SELECT * FROM game_config WHERE key = ?");
        $stmt->execute([$key]);
        $config = $stmt->fetch();
    }
    
    if (!$config && !empty($key)) {
        $message = '配置项不存在';
        $message_type = 'warning';
        $action = 'list';
    }
}

// 设置页面变量
if ($action === 'list') {
    $page_title = '系统配置';
    $page_icon = 'fas fa-cogs';
    $page_subtitle = '游戏配置管理';
} elseif ($action === 'view') {
    $page_title = '查看配置';
    $page_icon = 'fas fa-eye';
    $page_subtitle = $config['key'] ?? '未知配置';
} elseif ($action === 'edit') {
    $page_title = empty($config) ? '添加配置项' : '编辑配置项';
    $page_icon = empty($config) ? 'fas fa-plus-circle' : 'fas fa-edit';
    $page_subtitle = empty($config) ? '创建新配置' : ($config['key'] ?? '未知配置');
}

require_once 'header.php';

// 显示消息
if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show alert-auto-hide" role="alert">
    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
    <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
<!-- 配置列表 -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-cogs me-2"></i>系统配置列表
        </div>
        <div>
            <a href="?action=edit" class="btn btn-primary btn-sm">
                <i class="fas fa-plus-circle me-1"></i>添加配置
            </a>
            <a href="game_config.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-sync-alt me-1"></i>刷新
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>配置键</th>
                        <th>配置值</th>
                        <th>描述</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $db->query("SELECT * FROM game_config ORDER BY key");
                    $configs = $stmt->fetchAll();
                    
                    foreach ($configs as $cfg):
                        $value_class = 'bg-secondary';
                        if (is_numeric($cfg['value'])) {
                            $value_class = $cfg['value'] > 100 ? 'bg-warning' : ($cfg['value'] > 10 ? 'bg-info' : 'bg-success');
                        }
                    ?>
                    <tr>
                        <td>
                            <code><?php echo htmlspecialchars($cfg['key']); ?></code>
                        </td>
                        <td>
                            <span class="badge <?php echo $value_class; ?>"><?php echo htmlspecialchars($cfg['value']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($cfg['desc']); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?action=view&key=<?php echo urlencode($cfg['key']); ?>" class="btn btn-outline-primary" title="查看">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="?action=edit&key=<?php echo urlencode($cfg['key']); ?>" class="btn btn-outline-success" title="编辑">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if (isSuperAdmin()): ?>
                                <a href="?action=delete&key=<?php echo urlencode($cfg['key']); ?>" 
                                   class="btn btn-outline-danger btn-delete" title="删除" onclick="return confirm('确定删除配置项 <?php echo addslashes($cfg['key']); ?> 吗？')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($configs)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            <i class="fas fa-cogs fa-2x mb-3 d-block"></i>
                            暂无配置数据
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 批量编辑表单 -->
<div class="card mt-4">
    <div class="card-header">
        <i class="fas fa-edit me-2"></i>批量编辑常用配置
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="bulk_update" value="1">
            
            <div class="row">
                <?php
                $common_configs = [
                    'daily_train_limit' => '每日训练次数',
                    'daily_box_limit' => '每日盲盒次数',
                    'stat_cap' => '单项属性上限',
                    'box_cost' => '盲盒单价',
                    'exchange_rate' => '兑换汇率'
                ];
                
                foreach ($common_configs as $config_key => $config_desc):
                    $stmt = $db->prepare("SELECT value FROM game_config WHERE key = ?");
                    $stmt->execute([$config_key]);
                    $config_value = $stmt->fetchColumn();
                ?>
                <div class="col-md-4 mb-3">
                    <label for="bulk_<?php echo $config_key; ?>" class="form-label"><?php echo $config_desc; ?></label>
                    <input type="text" class="form-control" id="bulk_<?php echo $config_key; ?>" 
                           name="updates[<?php echo $config_key; ?>]" value="<?php echo htmlspecialchars($config_value ?? ''); ?>">
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-end">
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-save me-1"></i>批量更新
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 配置分类统计 -->
<div class="row mt-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <?php
                $stmt = $db->query("SELECT COUNT(*) FROM game_config");
                $total_configs = $stmt->fetchColumn();
                ?>
                <div class="text-muted small mb-2">配置项总数</div>
                <div class="fs-3 fw-bold text-primary"><?php echo $total_configs; ?></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <?php
                $stmt = $db->query("SELECT COUNT(*) FROM game_config WHERE key LIKE 'daily_%'");
                $daily_configs = $stmt->fetchColumn();
                ?>
                <div class="text-muted small mb-2">日常配置</div>
                <div class="fs-3 fw-bold text-success"><?php echo $daily_configs; ?></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <?php
                $stmt = $db->query("SELECT COUNT(*) FROM game_config WHERE key LIKE 'stat_%'");
                $stat_configs = $stmt->fetchColumn();
                ?>
                <div class="text-muted small mb-2">属性配置</div>
                <div class="fs-3 fw-bold text-info"><?php echo $stat_configs; ?></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <?php
                $stmt = $db->query("SELECT COUNT(*) FROM game_config WHERE key LIKE 'box_%'");
                $box_configs = $stmt->fetchColumn();
                ?>
                <div class="text-muted small mb-2">盲盒配置</div>
                <div class="fs-3 fw-bold text-warning"><?php echo $box_configs; ?></div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($action === 'view' && $config): ?>
<!-- 查看配置详情 -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle me-2"></i>配置详情
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-4">配置键:</dt>
                    <dd class="col-sm-8"><code><?php echo htmlspecialchars($config['key']); ?></code></dd>
                    
                    <dt class="col-sm-4">配置值:</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-primary fs-6"><?php echo htmlspecialchars($config['value']); ?></span>
                    </dd>
                    
                    <dt class="col-sm-4">描述:</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($config['desc']); ?></dd>
                    
                    <dt class="col-sm-4">数据类型:</dt>
                    <dd class="col-sm-8">
                        <?php
                        $value = $config['value'];
                        if (is_numeric($value)) {
                            echo '<span class="badge bg-info">数字</span>';
                        } elseif (in_array(strtolower($value), ['true', 'false', 'yes', 'no'])) {
                            echo '<span class="badge bg-warning">布尔值</span>';
                        } else {
                            echo '<span class="badge bg-success">字符串</span>';
                        }
                        ?>
                    </dd>
                </dl>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <i class="fas fa-lightbulb me-2"></i>配置说明
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>配置说明：</strong><br>
                    系统配置项用于控制游戏的各种参数，修改后会影响所有用户。
                    请谨慎修改，建议在测试环境中验证后再应用到生产环境。
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-history me-2"></i>配置影响分析
                </div>
                <div>
                    <a href="?action=edit&key=<?php echo urlencode($config['key']); ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-edit me-1"></i>编辑配置
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php
                $config_impacts = [
                    'stat_cap' => '影响用户单项属性的最大值',
                    'daily_train_limit' => '影响用户每日训练次数',
                    'daily_box_limit' => '影响用户每日盲盒开启次数',
                    'box_cost' => '影响盲盒购买价格',
                    'exchange_rate' => '影响虞元兑换名誉的汇率',
                    'signin_reward_min' => '影响签到奖励最小值',
                    'signin_reward_max' => '影响签到奖励最大值'
                ];
                
                $impact = $config_impacts[$config['key']] ?? '此配置项的具体影响请参考游戏文档';
                ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>影响范围：</strong><?php echo $impact; ?>
                </div>
                
                <div class="mt-4">
                    <h6>相关配置项：</h6>
                    <ul class="list-group">
                        <?php
                        $related_key = preg_replace('/_(min|max|limit|cost|rate)$/', '', $config['key']);
                        $stmt = $db->prepare("SELECT key, value FROM game_config WHERE key LIKE ? AND key != ? LIMIT 5");
                        $stmt->execute([$related_key . '%', $config['key']]);
                        $related_configs = $stmt->fetchAll();
                        
                        foreach ($related_configs as $related):
                        ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <a href="?action=view&key=<?php echo urlencode($related['key']); ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($related['key']); ?>
                            </a>
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($related['value']); ?></span>
                        </li>
                        <?php endforeach; ?>
                        
                        <?php if (empty($related_configs)): ?>
                        <li class="list-group-item text-muted">无相关配置项</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <i class="fas fa-code me-2"></i>原始数据
            </div>
            <div class="card-body">
                <pre class="bg-light p-3 rounded">
{
    "key": "<?php echo htmlspecialchars($config['key']); ?>",
    "value": "<?php echo htmlspecialchars($config['value']); ?>",
    "desc": "<?php echo htmlspecialchars($config['desc']); ?>"
}
                </pre>
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="game_config.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>返回列表
    </a>
</div>

<?php elseif ($action === 'edit'): ?>
<!-- 编辑/添加配置表单 -->
<div class="card">
    <div class="card-header">
        <i class="<?php echo $page_icon; ?> me-2"></i><?php echo $page_title; ?>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="save_config" value="1">
            <input type="hidden" name="original_key" value="<?php echo htmlspecialchars($config['key'] ?? ''); ?>">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="key" class="form-label">配置键 *</label>
                    <input type="text" class="form-control" id="key" name="key" 
                           value="<?php echo htmlspecialchars($config['key'] ?? ''); ?>" required
                           pattern="[a-zA-Z0-9_]+" title="只能包含字母、数字和下划线">
                    <div class="form-text">配置的唯一标识符，如：daily_train_limit</div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="value" class="form-label">配置值 *</label>
                    <input type="text" class="form-control" id="value" name="value" 
                           value="<?php echo htmlspecialchars($config['value'] ?? ''); ?>" required>
                    <div class="form-text">可以是数字、字符串或布尔值</div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="desc" class="form-label">描述说明 *</label>
                <textarea class="form-control" id="desc" name="desc" rows="3" required><?php echo htmlspecialchars($config['desc'] ?? ''); ?></textarea>
                <div class="form-text">详细说明此配置项的作用和影响</div>
            </div>
            
            <!-- 配置类型建议 -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-lightbulb me-2"></i>配置类型建议
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card border-primary">
                                <div class="card-body">
                                    <h6 class="card-title">数字类型</h6>
                                    <p class="card-text small">例如：次数、上限、价格等</p>
                                    <code>100, 5, 20.5</code>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-success">
                                <div class="card-body">
                                    <h6 class="card-title">布尔类型</h6>
                                    <p class="card-text small">例如：开关、启用状态</p>
                                    <code>true, false, 1, 0</code>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-warning">
                                <div class="card-body">
                                    <h6 class="card-title">字符串类型</h6>
                                    <p class="card-text small">例如：名称、提示信息</p>
                                    <code>"礼盒碎片", "欢迎消息"</code>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-between mt-4">
                <a href="game_config.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>取消
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>保存配置
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 快速模板 -->
<div class="card mt-4">
    <div class="card-header">
        <i class="fas fa-magic me-2"></i>快速模板
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <button class="btn btn-outline-info w-100 mb-2" onclick="applyTemplate('daily_limit')">
                    日常限制
                </button>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-success w-100 mb-2" onclick="applyTemplate('stat_config')">
                    属性配置
                </button>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-warning w-100 mb-2" onclick="applyTemplate('box_config')">
                    盲盒配置
                </button>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-primary w-100 mb-2" onclick="applyTemplate('other_config')">
                    其他配置
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function applyTemplate(templateType) {
    if (templateType === 'daily_limit') {
        document.getElementById('key').value = 'daily_new_limit';
        document.getElementById('value').value = '10';
        document.getElementById('desc').value = '每日新功能次数限制';
    } else if (templateType === 'stat_config') {
        document.getElementById('key').value = 'stat_new_cap';
        document.getElementById('value').value = '500';
        document.getElementById('desc').value = '新增属性上限';
    } else if (templateType === 'box_config') {
        document.getElementById('key').value = 'box_new_rate';
        document.getElementById('value').value = '20';
        document.getElementById('desc').value = '新增盲盒掉落率';
    } else if (templateType === 'other_config') {
        document.getElementById('key').value = 'new_feature_enabled';
        document.getElementById('value').value = 'true';
        document.getElementById('desc').value = '新功能开关';
    }
}
</script>

<?php endif; ?>

<?php
require_once 'footer.php';
?>