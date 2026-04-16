<?php
require_once 'config.php';
checkLogin();

$db = getDB();
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? '';
$message = '';
$message_type = '';

// 处理删除请求
if ($action === 'delete' && !empty($id) && isSuperAdmin()) {
    try {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $message = '用户删除成功';
        $message_type = 'success';
        $action = 'list'; // 回到列表
    } catch (Exception $e) {
        $message = '删除失败: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// 处理保存请求 (添加/编辑)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $id = safeInput($_POST['id'] ?? '');
    $uid = safeInput($_POST['uid'] ?? '');
    $name = safeInput($_POST['name'] ?? '');
    $currency_raw = trim($_POST['currency'] ?? '');
    if (!empty($currency_raw) && !json_decode($currency_raw, true)) {
        $message = '货币字段 JSON 格式无效';
        $message_type = 'danger';
        $action = 'edit';
        $currency = '{"yuCoin": 0, "reputation": 0}';
    } else {
        $currency = $currency_raw ?: '{"yuCoin": 0, "reputation": 0}';
    }
    $profile_raw = trim($_POST['profile'] ?? '');
    if (!empty($profile_raw) && !json_decode($profile_raw, true)) {
        $message = '档案字段 JSON 格式无效';
        $message_type = 'danger';
        $action = 'edit';
        $profile = '{}';
    } else {
        $profile = $profile_raw ?: '{}';
    }
    $limits_raw = trim($_POST['limits'] ?? '');
    if (!empty($limits_raw) && !json_decode($limits_raw, true)) {
        $message = '限制字段 JSON 格式无效';
        $message_type = 'danger';
        $action = 'edit';
        $limits = '{}';
    } else {
        $limits = $limits_raw ?: '{}';
    }
    
    // 如果有错误，跳过保存
    if ($message_type === 'danger') {
        // 保持编辑状态，显示错误消息
        $action = 'edit';
    } else {
        try {
        if (empty($id)) {
            // 新增
            $stmt = $db->prepare("INSERT INTO users (id, uid, name, currency, profile, limits) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id, $uid, $name, $currency, $profile, $limits]);
            $message = '用户添加成功';
            $message_type = 'success';
        } else {
            // 更新
            $stmt = $db->prepare("UPDATE users SET uid = ?, name = ?, currency = ?, profile = ?, limits = ? WHERE id = ?");
            $stmt->execute([$uid, $name, $currency, $profile, $limits, $id]);
            $message = '用户更新成功';
            $message_type = 'success';
        }
        // 重定向到列表页
        header('Location: users.php?message=' . urlencode($message) . '&type=' . $message_type);
        exit();
    } catch (Exception $e) {
        $message = '保存失败: ' . $e->getMessage();
        $message_type = 'danger';
        $action = 'edit'; // 保持在编辑页面
    }
}
}

// 根据action显示不同页面
if ($action === 'view' || $action === 'edit') {
    // 查看或编辑单个用户
    $user = null;
    if (!empty($id)) {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
    }
    
    if (!$user && !empty($id)) {
        $message = '用户不存在';
        $message_type = 'warning';
        $action = 'list';
    }
}

// 设置页面变量
if ($action === 'list') {
    $page_title = '用户管理';
    $page_icon = 'fas fa-users';
    $page_subtitle = '用户列表';
} elseif ($action === 'view') {
    $page_title = '查看用户';
    $page_icon = 'fas fa-eye';
    $page_subtitle = $user['name'] ?? '未知用户';
} elseif ($action === 'edit') {
    $page_title = empty($user) ? '添加用户' : '编辑用户';
    $page_icon = empty($user) ? 'fas fa-user-plus' : 'fas fa-edit';
    $page_subtitle = empty($user) ? '创建新用户' : ($user['name'] ?? '未知用户');
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
<!-- 用户列表 -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-users me-2"></i>用户列表
        </div>
        <div>
            <a href="?action=edit" class="btn btn-primary btn-sm">
                <i class="fas fa-user-plus me-1"></i>添加用户
            </a>
            <a href="users.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-sync-alt me-1"></i>刷新
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>UID</th>
                        <th>昵称</th>
                        <th>货币</th>
                        <th>注册时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
                    $users = $stmt->fetchAll();
                    foreach ($users as $u):
                        $currency_data = safeJsonDecode($u['currency'], true);
                        $yuCoin = $currency_data['yuCoin'] ?? 0;
                        $reputation = $currency_data['reputation'] ?? 0;
                    ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($u['id']); ?></code></td>
                        <td><?php echo htmlspecialchars($u['uid']); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($u['name']); ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-warning">虞元: <?php echo $yuCoin; ?></span>
                            <span class="badge bg-info">名誉: <?php echo $reputation; ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?action=view&id=<?php echo urlencode($u['id']); ?>" class="btn btn-outline-primary" title="查看">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="?action=edit&id=<?php echo urlencode($u['id']); ?>" class="btn btn-outline-success" title="编辑">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if (isSuperAdmin()): ?>
                                <a href="?action=delete&id=<?php echo urlencode($u['id']); ?>" 
                                   class="btn btn-outline-danger btn-delete" title="删除" onclick="return confirm('确定删除用户 <?php echo addslashes($u['name']); ?> 吗？')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="fas fa-users fa-2x mb-3 d-block"></i>
                            暂无用户数据
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 批量操作 -->
<div class="card mt-4">
    <div class="card-header">
        <i class="fas fa-tools me-2"></i>批量操作
    </div>
    <div class="card-body">
        <form method="POST" action="users_batch.php" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">操作类型</label>
                <select class="form-select" name="batch_action">
                    <option value="add_currency">增加货币</option>
                    <option value="reset_limits">重置限制</option>
                    <option value="export">导出数据</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">选择用户 (留空为全部)</label>
                <input type="text" class="form-control" name="user_ids" placeholder="用户ID，用逗号分隔">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-warning w-100">
                    <i class="fas fa-play me-1"></i>执行批量操作
                </button>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action === 'view' && $user): ?>
<!-- 查看用户详情 -->
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-id-card me-2"></i>基本信息
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-5">用户ID:</dt>
                    <dd class="col-sm-7"><code><?php echo htmlspecialchars($user['id']); ?></code></dd>
                    
                    <dt class="col-sm-5">UID:</dt>
                    <dd class="col-sm-7"><?php echo htmlspecialchars($user['uid']); ?></dd>
                    
                    <dt class="col-sm-5">昵称:</dt>
                    <dd class="col-sm-7"><strong><?php echo htmlspecialchars($user['name']); ?></strong></dd>
                    
                    <dt class="col-sm-5">注册时间:</dt>
                    <dd class="col-sm-7"><?php echo htmlspecialchars($user['created_at']); ?></dd>
                </dl>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <i class="fas fa-coins me-2"></i>货币信息
            </div>
            <div class="card-body">
                <?php
                $currency = safeJsonDecode($user['currency'], true);
                $profile = safeJsonDecode($user['profile'], true);
                $limits = safeJsonDecode($user['limits'], true);
                ?>
                <div class="d-flex justify-content-between mb-3">
                    <div class="text-center">
                        <div class="fs-2 text-warning"><?php echo $currency['yuCoin'] ?? 0; ?></div>
                        <div class="text-muted">虞元</div>
                    </div>
                    <div class="text-center">
                        <div class="fs-2 text-info"><?php echo $currency['reputation'] ?? 0; ?></div>
                        <div class="text-muted">名誉</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-circle me-2"></i>档案信息
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tbody>
                            <?php
                            $profile_fields = [
                                'age' => '年龄', 'sex' => '性别', 'char' => '性格', 'look' => '外貌',
                                'height' => '身高', 'family' => '家世', 'job' => '职位', 'bg' => '背景',
                                'like' => '喜恶', 'taboo' => '禁忌', 'citizen' => '户籍', 'salary' => '薪资',
                                'group' => '隶属', 'note' => '备注'
                            ];
                            
                            // 检查档案数据是否有效
                            $profile_raw = $user['profile'] ?? '';
                            $profile_is_valid = !empty($profile) && is_array($profile);
                            
                            if (!$profile_is_valid && !empty($profile_raw)) {
                                // JSON 解析失败，显示原始数据和警告
                                ?>
                                <tr>
                                    <td colspan="2">
                                        <div class="alert alert-warning mb-0">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <strong>档案数据格式异常</strong>
                                            <p class="mb-2">JSON 解析失败，可能是由于 HTML 实体转义导致。</p>
                                            <p class="mb-0">
                                                <a href="cleanup.php" class="alert-link">点击这里运行数据库清理工具</a> 修复此问题，
                                                或者 <a href="?action=edit&id=<?php echo urlencode($user['id']); ?>" class="alert-link">编辑用户</a> 手动修正档案字段。
                                            </p>
                                        </div>
                                        <div class="mt-3">
                                            <strong>原始数据预览（前200字符）：</strong>
                                            <pre class="bg-light p-2 mt-2 small"><?php echo htmlspecialchars(substr($profile_raw, 0, 200)); ?>...</pre>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            } else {
                                // 正常显示档案字段
                                foreach ($profile_fields as $key => $label):
                                    $value = $profile[$key] ?? $profile["profile_$key"] ?? '未设置';
                                ?>
                                <tr>
                                    <th width="30%"><?php echo $label; ?></th>
                                    <td><?php echo htmlspecialchars($value); ?></td>
                                </tr>
                                <?php endforeach;
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-cogs me-2"></i>限制信息
                </div>
                <div>
                    <a href="?action=edit&id=<?php echo urlencode($user['id']); ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-edit me-1"></i>编辑用户
                    </a>
                </div>
            </div>
            <div class="card-body">
                <pre class="bg-light p-3 rounded" style="max-height: 200px; overflow: auto;">
<?php echo htmlspecialchars(json_encode($limits, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?>
                </pre>
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="users.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>返回列表
    </a>
</div>

<?php elseif ($action === 'edit'): ?>
<!-- 编辑/添加用户表单 -->
<div class="card">
    <div class="card-header">
        <i class="<?php echo $page_icon; ?> me-2"></i><?php echo $page_title; ?>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="save_user" value="1">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($user['id'] ?? ''); ?>">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="id" class="form-label">用户ID *</label>
                    <input type="text" class="form-control" id="id" name="id" 
                           value="<?php echo htmlspecialchars($user['id'] ?? ''); ?>" 
                           <?php echo !empty($user) ? 'readonly' : 'required'; ?>>
                    <div class="form-text">用户的唯一标识符，通常是QQ号</div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="uid" class="form-label">UID</label>
                    <input type="number" class="form-control" id="uid" name="uid" 
                           value="<?php echo htmlspecialchars($user['uid'] ?? ''); ?>">
                    <div class="form-text">内部使用的用户序号</div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">昵称</label>
                    <input type="text" class="form-control" id="name" name="name" 
                           value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="currency" class="form-label">货币 (JSON)</label>
                    <textarea class="form-control" id="currency" name="currency" rows="2"><?php 
                        if (isset($user['currency'])) {
                            echo htmlspecialchars($user['currency']);
                        } else {
                            echo '{"yuCoin": 0, "reputation": 0}';
                        }
                    ?></textarea>
                    <div class="form-text">格式: {"yuCoin": 100, "reputation": 50}</div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="profile" class="form-label">档案 (JSON)</label>
                <textarea class="form-control" id="profile" name="profile" rows="6"><?php 
                    if (isset($user['profile'])) {
                        echo htmlspecialchars($user['profile']);
                    } else {
                        echo '{}';
                    }
                ?></textarea>
                <div class="form-text">用户档案信息，JSON格式</div>
            </div>
            
            <div class="mb-3">
                <label for="limits" class="form-label">限制 (JSON)</label>
                <textarea class="form-control" id="limits" name="limits" rows="4"><?php 
                    if (isset($user['limits'])) {
                        echo htmlspecialchars($user['limits']);
                    } else {
                        echo '{}';
                    }
                ?></textarea>
                <div class="form-text">用户限制信息，如最后签到时间等</div>
            </div>
            
            <div class="d-flex justify-content-between mt-4">
                <a href="users.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>取消
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>保存用户
                </button>
            </div>
        </form>
    </div>
</div>

<!-- JSON编辑助手 -->
<div class="card mt-4">
    <div class="card-header">
        <i class="fas fa-code me-2"></i>JSON编辑助手
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <button class="btn btn-outline-info w-100 mb-2" onclick="insertJson('profile', 'age', '18')">
                    添加年龄字段
                </button>
            </div>
            <div class="col-md-4">
                <button class="btn btn-outline-info w-100 mb-2" onclick="insertJson('profile', 'sex', '女')">
                    添加性别字段
                </button>
            </div>
            <div class="col-md-4">
                <button class="btn btn-outline-info w-100 mb-2" onclick="formatJson('profile')">
                    格式化JSON
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function insertJson(textareaId, key, value) {
    var textarea = document.getElementById(textareaId);
    var json = textarea.value.trim();
    var obj = {};
    
    try {
        if (json) obj = JSON.parse(json);
    } catch(e) {
        obj = {};
    }
    
    obj[key] = value;
    textarea.value = JSON.stringify(obj, null, 2);
}

function formatJson(textareaId) {
    var textarea = document.getElementById(textareaId);
    try {
        var obj = JSON.parse(textarea.value);
        textarea.value = JSON.stringify(obj, null, 2);
    } catch(e) {
        alert('JSON格式错误: ' + e.message);
    }
}
</script>

<?php endif; ?>

<?php
require_once 'footer.php';
?>