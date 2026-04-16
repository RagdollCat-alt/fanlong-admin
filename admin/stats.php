<?php
require_once 'config.php';
checkLogin();

$db = getDB();
$action = $_GET['action'] ?? 'list';
$user_id = $_GET['user_id'] ?? '';
$message = '';
$message_type = '';

// 属性名称映射
$stat_names = [
    'stat_face' => '颜值',
    'stat_charm' => '魅力', 
    'stat_intel' => '智力',
    'stat_biz' => '商业',
    'stat_talk' => '口才',
    'stat_body' => '体能',
    'stat_art' => '才艺',
    'stat_obed' => '服从_威慑'
];

// 处理保存请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_stats'])) {
    $user_id = safeInput($_POST['user_id'] ?? '');
    
    if (empty($user_id)) {
        $message = '用户ID不能为空';
        $message_type = 'danger';
    } else {
        try {
            // 检查用户是否存在
            $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            if (!$stmt->fetch()) {
                $message = '用户不存在，请先创建用户';
                $message_type = 'danger';
            } else {
                // 构建更新字段
                $fields = [];
                $params = [];
                
                foreach ($stat_names as $key => $name) {
                    $value = intval($_POST[$key] ?? 0);
                    $fields[] = "$key = ?";
                    $params[] = $value;
                }
                
                $params[] = $user_id;
                
                // 检查记录是否存在
                $stmt = $db->prepare("SELECT COUNT(*) FROM user_stats WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $exists = $stmt->fetchColumn() > 0;
                
                if ($exists) {
                    // 更新
                    $sql = "UPDATE user_stats SET " . implode(', ', $fields) . " WHERE user_id = ?";
                } else {
                    // 插入
                    $fields[] = 'user_id';
                    $params[] = $user_id;
                    $sql = "INSERT INTO user_stats (" . implode(', ', array_keys($stat_names)) . ", user_id) VALUES (" . 
                           implode(', ', array_fill(0, count($stat_names), '?')) . ", ?)";
                }
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                $message = '属性保存成功';
                $message_type = 'success';
                $action = 'list';
            }
        } catch (Exception $e) {
            $message = '保存失败: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// 处理删除请求
if ($action === 'delete' && !empty($user_id) && isSuperAdmin()) {
    $stmt = $db->prepare("DELETE FROM user_stats WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $message = '属性记录删除成功';
    $message_type = 'success';
    $action = 'list';
}

// 根据action显示不同页面
if ($action === 'edit') {
    // 编辑属性
    $stats = [];
    if (!empty($user_id)) {
        $stmt = $db->prepare("SELECT * FROM user_stats WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $stats = $stmt->fetch();
        
        if (!$stats) {
            // 如果不存在，创建空记录
            $stats = ['user_id' => $user_id];
            foreach (array_keys($stat_names) as $key) {
                $stats[$key] = 0;
            }
        }
    }
}

// 设置页面变量
if ($action === 'list') {
    $page_title = '属性管理';
    $page_icon = 'fas fa-chart-line';
    $page_subtitle = '用户属性列表';
} elseif ($action === 'edit') {
    $page_title = '编辑属性';
    $page_icon = 'fas fa-edit';
    $page_subtitle = '用户: ' . htmlspecialchars($user_id);
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
<!-- 属性列表 -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-chart-line me-2"></i>用户属性列表
        </div>
        <div>
            <a href="stats.php?action=edit" class="btn btn-primary btn-sm">
                <i class="fas fa-plus-circle me-1"></i>添加属性
            </a>
            <a href="stats.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-sync-alt me-1"></i>刷新
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>用户ID</th>
                        <th>用户昵称</th>
                        <?php foreach ($stat_names as $name): ?>
                        <th><?php echo $name; ?></th>
                        <?php endforeach; ?>
                        <th>总分</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // 联表查询用户和属性
                    $stmt = $db->query("
                        SELECT u.id, u.name, s.* 
                        FROM users u 
                        LEFT JOIN user_stats s ON u.id = s.user_id 
                        ORDER BY u.created_at DESC
                    ");
                    $results = $stmt->fetchAll();
                    
                    foreach ($results as $row):
                        $total_score = 0;
                        foreach (array_keys($stat_names) as $key) {
                            $total_score += intval($row[$key] ?? 0);
                        }
                    ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($row['id']); ?></code></td>
                        <td><strong><?php echo htmlspecialchars($row['name'] ?? '未知'); ?></strong></td>
                        
                        <?php foreach (array_keys($stat_names) as $key): 
                            $value = intval($row[$key] ?? 0);
                            $percentage = min(100, $value / 5); // 假设上限500，显示百分比
                        ?>
                        <td>
                            <div class="progress" style="height: 20px;" title="<?php echo $value; ?>">
                                <div class="progress-bar 
                                    <?php echo $value >= 400 ? 'bg-success' : ($value >= 200 ? 'bg-info' : ($value >= 100 ? 'bg-warning' : 'bg-secondary')); ?>" 
                                    role="progressbar" 
                                    style="width: <?php echo $percentage; ?>%">
                                    <?php echo $value; ?>
                                </div>
                            </div>
                        </td>
                        <?php endforeach; ?>
                        
                        <td>
                            <span class="badge bg-primary fs-6"><?php echo $total_score; ?></span>
                        </td>
                        
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?action=edit&user_id=<?php echo urlencode($row['id']); ?>" class="btn btn-outline-success" title="编辑">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if (isSuperAdmin() && isset($row['user_id'])): ?>
                                <a href="?action=delete&user_id=<?php echo urlencode($row['id']); ?>" 
                                   class="btn btn-outline-danger btn-delete" title="删除" onclick="return confirm('确定删除该用户的属性记录吗？')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                                <a href="users.php?action=view&id=<?php echo urlencode($row['id']); ?>" class="btn btn-outline-info" title="查看用户">
                                    <i class="fas fa-user"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($results)): ?>
                    <tr>
                        <td colspan="<?php echo count($stat_names) + 4; ?>" class="text-center text-muted py-4">
                            <i class="fas fa-chart-bar fa-2x mb-3 d-block"></i>
                            暂无属性数据
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 属性统计 -->
<div class="card mt-4">
    <div class="card-header">
        <i class="fas fa-chart-pie me-2"></i>属性统计
    </div>
    <div class="card-body">
        <div class="row">
            <?php
            // 计算每个属性的平均值
            foreach ($stat_names as $key => $name):
                $stmt = $db->query("SELECT AVG($key) as avg FROM user_stats WHERE $key > 0");
                $avg = $stmt->fetchColumn();
                $avg = round($avg, 1);
            ?>
            <div class="col-md-3 col-6 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="text-muted small mb-2"><?php echo $name; ?></div>
                        <div class="fs-3 fw-bold text-primary"><?php echo $avg; ?></div>
                        <div class="text-muted small">平均分</div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- 属性分布图表 -->
        <div class="row mt-4">
            <div class="col-md-6">
                <canvas id="statsChart" height="250"></canvas>
            </div>
            <div class="col-md-6">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>属性</th>
                                <th>最高分</th>
                                <th>最低分</th>
                                <th>平均分</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($stat_names as $key => $name):
                                $stmt = $db->query("SELECT MAX($key) as max, MIN($key) as min, AVG($key) as avg FROM user_stats WHERE $key > 0");
                                $data = $stmt->fetch();
                            ?>
                            <tr>
                                <td><?php echo $name; ?></td>
                                <td><span class="badge bg-success"><?php echo round($data['max'] ?? 0); ?></span></td>
                                <td><span class="badge bg-danger"><?php echo round($data['min'] ?? 0); ?></span></td>
                                <td><span class="badge bg-info"><?php echo round($data['avg'] ?? 0, 1); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 绘制属性统计图表
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('statsChart').getContext('2d');
    
    // 这里需要从PHP传递数据，简化示例
    var labels = <?php echo json_encode(array_values($stat_names)); ?>;
    var data = [];
    
    <?php
    // 计算每个属性的总分数
    $sums = [];
    foreach (array_keys($stat_names) as $key) {
        $stmt = $db->query("SELECT SUM($key) as sum FROM user_stats");
        $sums[] = $stmt->fetchColumn() ?? 0;
    }
    echo "data = " . json_encode($sums) . ";";
    ?>
    
    var chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: '属性总分',
                data: data,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(255, 159, 64, 0.7)',
                    'rgba(201, 203, 207, 0.7)',
                    'rgba(255, 99, 132, 0.7)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)',
                    'rgba(201, 203, 207, 1)',
                    'rgba(255, 99, 132, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: '属性总分分布'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: '总分'
                    }
                }
            }
        }
    });
});
</script>

<?php elseif ($action === 'edit'): ?>
<!-- 编辑属性表单 -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-edit me-2"></i>编辑属性
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="save_stats" value="1">
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <label for="user_id" class="form-label">用户ID *</label>
                    <input type="text" class="form-control" id="user_id" name="user_id" 
                           value="<?php echo htmlspecialchars($stats['user_id'] ?? ''); ?>" required
                           <?php echo !empty($stats['user_id']) ? 'readonly' : ''; ?>>
                    <div class="form-text">输入用户ID (QQ号)，用户必须在users表中存在</div>
                </div>
                <div class="col-md-6">
                    <div class="form-label">用户信息</div>
                    <?php if (!empty($stats['user_id'])): 
                        $stmt = $db->prepare("SELECT name FROM users WHERE id = ?");
                        $stmt->execute([$stats['user_id']]);
                        $user_info = $stmt->fetch();
                    ?>
                    <div class="alert alert-info">
                        <?php if ($user_info): ?>
                        用户: <strong><?php echo htmlspecialchars($user_info['name']); ?></strong>
                        <a href="users.php?action=view&id=<?php echo urlencode($stats['user_id']); ?>" class="btn btn-sm btn-outline-primary float-end">
                            查看详情
                        </a>
                        <?php else: ?>
                        <span class="text-danger">用户不存在！请先在用户管理中创建。</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="row">
                <?php foreach ($stat_names as $key => $name): 
                    $value = intval($stats[$key] ?? 0);
                    $percentage = min(100, $value / 5);
                ?>
                <div class="col-md-6 col-lg-3 mb-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="text-muted small mb-2"><?php echo $name; ?></div>
                            <div class="mb-3">
                                <input type="range" class="form-range" min="0" max="500" step="1" 
                                       id="<?php echo $key; ?>_range" 
                                       oninput="document.getElementById('<?php echo $key; ?>').value = this.value; updateProgress('<?php echo $key; ?>', this.value);"
                                       value="<?php echo $value; ?>">
                            </div>
                            <div class="d-flex align-items-center justify-content-center mb-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="adjustValue('<?php echo $key; ?>', -10)">-10</button>
                                <input type="number" class="form-control form-control-lg text-center mx-2" 
                                       id="<?php echo $key; ?>" name="<?php echo $key; ?>" 
                                       value="<?php echo $value; ?>" min="0" max="500" style="width: 80px;"
                                       oninput="document.getElementById('<?php echo $key; ?>_range').value = this.value; updateProgress('<?php echo $key; ?>', this.value);">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="adjustValue('<?php echo $key; ?>', 10)">+10</button>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div id="<?php echo $key; ?>_progress" class="progress-bar 
                                    <?php echo $value >= 400 ? 'bg-success' : ($value >= 200 ? 'bg-info' : ($value >= 100 ? 'bg-warning' : 'bg-secondary')); ?>" 
                                    role="progressbar" 
                                    style="width: <?php echo $percentage; ?>%">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-outline-primary w-100 mb-2" onclick="setAll(100)">全部设为100</button>
                                </div>
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-outline-info w-100 mb-2" onclick="setAll(200)">全部设为200</button>
                                </div>
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-outline-warning w-100 mb-2" onclick="setAll(0)">全部清零</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-between mt-4">
                <a href="stats.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>取消
                </a>
                <div>
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-save me-1"></i>保存属性
                    </button>
                    <a href="stats.php" class="btn btn-outline-primary">
                        <i class="fas fa-list me-1"></i>返回列表
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function adjustValue(field, delta) {
    var input = document.getElementById(field);
    var current = parseInt(input.value) || 0;
    var newVal = current + delta;
    if (newVal < 0) newVal = 0;
    if (newVal > 500) newVal = 500;
    input.value = newVal;
    document.getElementById(field + '_range').value = newVal;
    updateProgress(field, newVal);
}

function updateProgress(field, value) {
    var percentage = Math.min(100, value / 5);
    var progressBar = document.getElementById(field + '_progress');
    progressBar.style.width = percentage + '%';
    
    // 更新颜色
    progressBar.className = 'progress-bar ';
    if (value >= 400) {
        progressBar.className += 'bg-success';
    } else if (value >= 200) {
        progressBar.className += 'bg-info';
    } else if (value >= 100) {
        progressBar.className += 'bg-warning';
    } else {
        progressBar.className += 'bg-secondary';
    }
}

function setAll(value) {
    <?php foreach (array_keys($stat_names) as $key): ?>
    document.getElementById('<?php echo $key; ?>').value = value;
    document.getElementById('<?php echo $key; ?>_range').value = value;
    updateProgress('<?php echo $key; ?>', value);
    <?php endforeach; ?>
}
</script>

<?php endif; ?>

<?php
require_once 'footer.php';
?>