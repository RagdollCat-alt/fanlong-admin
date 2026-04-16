<?php
require_once 'config.php';
checkLogin();

// 获取统计数据
$db = getDB();
$stats = [];

// 用户数
$stmt = $db->query("SELECT COUNT(*) FROM users");
$stats['user_count'] = $stmt->fetchColumn();

// 物品数
$stmt = $db->query("SELECT COUNT(*) FROM items");
$stats['item_count'] = $stmt->fetchColumn();

// 今日新增用户（示例，按created_at判断）
$today = date('Y-m-d');
$stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) = ?");
$stmt->execute([$today]);
$stats['today_users'] = $stmt->fetchColumn();

// 管理员数
$stmt = $db->query("SELECT COUNT(*) FROM admins");
$stats['admin_count'] = $stmt->fetchColumn();

// 获取最近10个用户
$stmt = $db->query("SELECT id, name, created_at FROM users ORDER BY created_at DESC LIMIT 10");
$recent_users = $stmt->fetchAll();

// 获取物品库存概况
$stmt = $db->query("SELECT name, stock_qty, price FROM items WHERE is_selling = 1 ORDER BY stock_qty ASC LIMIT 10");
$low_stock_items = $stmt->fetchAll();

// 获取属性排行（颜值）
$stmt = $db->query("SELECT u.name, s.stat_face FROM users u JOIN user_stats s ON u.id = s.user_id ORDER BY s.stat_face DESC LIMIT 5");
$top_face = $stmt->fetchAll();

$page_title = '仪表盘';
$page_icon = 'fas fa-tachometer-alt';
$page_subtitle = '系统概览';
require_once 'header.php';
?>

<div class="row">
    <!-- 统计卡片 -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card">
            <div class="card-body stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $stats['user_count']; ?></div>
                <div class="stat-label">总用户数</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card">
            <div class="card-body stat-card">
                <div class="stat-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <div class="stat-number"><?php echo $stats['item_count']; ?></div>
                <div class="stat-label">物品数量</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card">
            <div class="card-body stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stat-number"><?php echo $stats['today_users']; ?></div>
                <div class="stat-label">今日新增</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card">
            <div class="card-body stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-number"><?php echo $stats['admin_count']; ?></div>
                <div class="stat-label">管理员</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- 最近用户 -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-clock me-2"></i>最近注册用户
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>用户ID</th>
                                <th>昵称</th>
                                <th>注册时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $user): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($user['id']); ?></code></td>
                                <td><?php echo htmlspecialchars($user['name'] ?? '未命名'); ?></td>
                                <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                <td>
                                    <a href="users.php?action=view&id=<?php echo urlencode($user['id']); ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recent_users)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">暂无用户数据</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-end mt-3">
                    <a href="users.php" class="btn btn-primary">查看所有用户</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 颜值排行 -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-crown me-2"></i>颜值排行榜 (TOP 5)
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>排名</th>
                                <th>用户</th>
                                <th>颜值</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; ?>
                            <?php foreach ($top_face as $player): ?>
                            <tr>
                                <td>
                                    <?php if ($rank == 1): ?>
                                    <span class="badge bg-warning">🥇</span>
                                    <?php elseif ($rank == 2): ?>
                                    <span class="badge bg-secondary">🥈</span>
                                    <?php elseif ($rank == 3): ?>
                                    <span class="badge bg-danger">🥉</span>
                                    <?php else: ?>
                                    <span class="badge bg-light text-dark">#<?php echo $rank; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($player['name'] ?? '未知'); ?></td>
                                <td>
                                    <span class="badge bg-success"><?php echo $player['stat_face']; ?> 点</span>
                                </td>
                                <td>
                                    <a href="stats.php?user_id=<?php echo urlencode($player['name']); ?>" class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-chart-bar"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php $rank++; ?>
                            <?php endforeach; ?>
                            <?php if (empty($top_face)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">暂无属性数据</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-end mt-3">
                    <a href="stats.php" class="btn btn-primary">查看所有属性</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- 低库存物品 -->
    <div class="col-lg-12 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-exclamation-triangle me-2"></i>低库存物品提醒
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>物品名称</th>
                                <th>当前库存</th>
                                <th>单价</th>
                                <th>状态</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($low_stock_items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td>
                                    <?php if ($item['stock_qty'] == -1): ?>
                                    <span class="badge bg-success">无限</span>
                                    <?php elseif ($item['stock_qty'] <= 5): ?>
                                    <span class="badge bg-danger"><?php echo $item['stock_qty']; ?></span>
                                    <?php elseif ($item['stock_qty'] <= 20): ?>
                                    <span class="badge bg-warning"><?php echo $item['stock_qty']; ?></span>
                                    <?php else: ?>
                                    <span class="badge bg-info"><?php echo $item['stock_qty']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $item['price']; ?> 虞元</td>
                                <td>
                                    <?php if ($item['stock_qty'] == 0): ?>
                                    <span class="badge bg-danger">缺货</span>
                                    <?php elseif ($item['stock_qty'] <= 5): ?>
                                    <span class="badge bg-warning">紧张</span>
                                    <?php else: ?>
                                    <span class="badge bg-success">充足</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="items.php?action=edit&name=<?php echo urlencode($item['name']); ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i> 补货
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($low_stock_items)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">暂无低库存物品</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 快速操作卡片 -->
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-bolt me-2"></i>快速操作
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 text-center mb-3">
                        <a href="users.php?action=add" class="btn btn-lg btn-outline-primary w-100 py-3">
                            <i class="fas fa-user-plus fa-2x mb-2"></i><br>
                            添加用户
                        </a>
                    </div>
                    <div class="col-md-3 text-center mb-3">
                        <a href="items.php?action=add" class="btn btn-lg btn-outline-success w-100 py-3">
                            <i class="fas fa-plus-circle fa-2x mb-2"></i><br>
                            添加物品
                        </a>
                    </div>
                    <div class="col-md-3 text-center mb-3">
                        <a href="game_config.php?action=edit&key=daily_train_limit" class="btn btn-lg btn-outline-info w-100 py-3">
                            <i class="fas fa-cog fa-2x mb-2"></i><br>
                            修改配置
                        </a>
                    </div>
                    <div class="col-md-3 text-center mb-3">
                        <a href="backup.php" class="btn btn-lg btn-outline-warning w-100 py-3">
                            <i class="fas fa-download fa-2x mb-2"></i><br>
                            数据备份
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'footer.php';
?>