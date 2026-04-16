<?php
require_once 'config.php';
checkLogin();

$db = getDB();
$action = $_GET['action'] ?? 'list';
$name = $_GET['name'] ?? '';
$message = '';
$message_type = '';

// 装备位置选项（与items.php一致）
$slot_options = [
    '' => '无',
    'hair' => '发型',
    'top' => '上衣',
    'bottom' => '下装',
    'head' => '头饰',
    'neck' => '颈饰',
    'inner1' => '内饰1',
    'inner2' => '内饰2',
    'acc1' => '配饰1',
    'acc2' => '配饰2',
    'acc3' => '配饰3',
    'acc4' => '配饰4'
];

// 属性映射
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

// 处理删除请求（仅限超级管理员）
if ($action === 'delete' && !empty($name) && isSuperAdmin()) {
    try {
        // 检查是否为装备类型
        $stmt = $db->prepare("SELECT type FROM items WHERE name = ?");
        $stmt->execute([$name]);
        $item = $stmt->fetch();
        
        if ($item && $item['type'] === 'equip') {
            $stmt = $db->prepare("DELETE FROM items WHERE name = ? AND type = 'equip'");
            $stmt->execute([$name]);
            $message = '装备删除成功';
            $message_type = 'success';
            $action = 'list';
        } else {
            $message = '只能删除装备类型的物品';
            $message_type = 'warning';
            $action = 'list';
        }
    } catch (Exception $e) {
        $message = '删除失败: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// 处理上下架请求
if ($action === 'toggle' && !empty($name)) {
    try {
        $stmt = $db->prepare("SELECT is_selling, type FROM items WHERE name = ? AND type = 'equip'");
        $stmt->execute([$name]);
        $item = $stmt->fetch();
        
        if ($item) {
            $new_status = $item['is_selling'] == 1 ? 0 : 1;
            $stmt = $db->prepare("UPDATE items SET is_selling = ? WHERE name = ? AND type = 'equip'");
            $stmt->execute([$new_status, $name]);
            $message = $new_status ? '装备已上架' : '装备已下架';
            $message_type = 'success';
        } else {
            $message = '装备不存在或不是装备类型';
            $message_type = 'warning';
        }
        $action = 'list';
    } catch (Exception $e) {
        $message = '操作失败: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// 处理保存请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_equip'])) {
    $name = trim($_POST['name'] ?? '');
    $price = intval($_POST['price'] ?? 0);
    $currency = trim($_POST['currency'] ?? 'yuCoin');
    $slot = trim($_POST['slot'] ?? '');
    $desc = trim($_POST['desc'] ?? '');
    $stats_raw = trim($_POST['stats'] ?? '');
    $effect_raw = trim($_POST['effect'] ?? '');
    $is_selling = isset($_POST['is_selling']) ? 1 : 0;
    $stock_qty = $_POST['stock_qty'] === '' ? -1 : intval($_POST['stock_qty']);
    $max_hold = intval($_POST['max_hold'] ?? 0);
    $original_name = trim($_POST['original_name'] ?? '');
    
    // 验证 JSON 格式
    $stats = '{}';
    $effect = '{}';
    
    if (!empty($stats_raw)) {
        $stats_data = json_decode($stats_raw, true);
        if ($stats_data === null) {
            $message = '属性字段 JSON 格式无效';
            $message_type = 'danger';
            $action = 'edit';
        } else {
            $stats = json_encode($stats_data, JSON_UNESCAPED_UNICODE);
        }
    }
    
    if (!empty($effect_raw)) {
        $effect_data = json_decode($effect_raw, true);
        if ($effect_data === null) {
            $message = '效果字段 JSON 格式无效';
            $message_type = 'danger';
            $action = 'edit';
        } else {
            $effect = json_encode($effect_data, JSON_UNESCAPED_UNICODE);
        }
    }
    
    // 如果没有错误，保存数据
    if ($message_type !== 'danger') {
        try {
            if (empty($original_name)) {
                // 新增装备
                $stmt = $db->prepare("INSERT INTO items (name, price, currency, type, slot, `desc`, stats, effect, is_selling, stock_qty, max_hold) VALUES (?, ?, ?, 'equip', ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $price, $currency, $slot, $desc, $stats, $effect, $is_selling, $stock_qty, $max_hold]);
                $message = '装备添加成功';
            } else {
                // 更新装备
                $stmt = $db->prepare("UPDATE items SET name = ?, price = ?, currency = ?, slot = ?, `desc` = ?, stats = ?, effect = ?, is_selling = ?, stock_qty = ?, max_hold = ? WHERE name = ? AND type = 'equip'");
                $stmt->execute([$name, $price, $currency, $slot, $desc, $stats, $effect, $is_selling, $stock_qty, $max_hold, $original_name]);
                $message = '装备更新成功';
            }
            $message_type = 'success';
            
            // 重定向到列表页
            header('Location: equip.php?message=' . urlencode($message) . '&type=' . $message_type);
            exit();
        } catch (Exception $e) {
            $message = '保存失败: ' . $e->getMessage();
            $message_type = 'danger';
            $action = 'edit';
        }
    }
}

// 根据action显示不同页面
if ($action === 'view' || $action === 'edit') {
    // 查看或编辑单个装备
    $item = null;
    if (!empty($name)) {
        $stmt = $db->prepare("SELECT * FROM items WHERE name = ? AND type = 'equip'");
        $stmt->execute([$name]);
        $item = $stmt->fetch();
    }
    
    if (!$item && !empty($name)) {
        $message = '装备不存在';
        $message_type = 'warning';
        $action = 'list';
    }
}

// 设置页面变量
if ($action === 'list') {
    $page_title = '装备管理';
    $page_icon = 'fas fa-tshirt';
    $page_subtitle = '游戏装备列表';
} elseif ($action === 'view') {
    $page_title = '查看装备';
    $page_icon = 'fas fa-eye';
    $page_subtitle = $item['name'] ?? '未知装备';
} elseif ($action === 'edit') {
    $page_title = empty($item) ? '添加装备' : '编辑装备';
    $page_icon = empty($item) ? 'fas fa-plus-circle' : 'fas fa-edit';
    $page_subtitle = empty($item) ? '创建新装备' : ($item['name'] ?? '未知装备');
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
<!-- 装备列表 -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-tshirt me-2"></i>装备列表
            <span class="badge bg-purple ms-2">仅显示装备类型</span>
        </div>
        <div>
            <a href="?action=edit" class="btn btn-primary btn-sm">
                <i class="fas fa-plus-circle me-1"></i>添加装备
            </a>
            <a href="items.php" class="btn btn-outline-secondary btn-sm ms-2">
                <i class="fas fa-shopping-bag me-1"></i>查看所有物品
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>装备名称</th>
                        <th>装备位置</th>
                        <th>价格</th>
                        <th>库存</th>
                        <th>属性加成</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $db->query("SELECT * FROM items WHERE type = 'equip' ORDER BY slot, price DESC");
                    $equips = $stmt->fetchAll();
                    
                    foreach ($equips as $eq):
                        $stock_text = $eq['stock_qty'] == -1 ? '无限' : $eq['stock_qty'];
                        $stock_class = $eq['stock_qty'] == -1 ? 'bg-success' : ($eq['stock_qty'] <= 5 ? 'bg-danger' : ($eq['stock_qty'] <= 20 ? 'bg-warning' : 'bg-info'));
                        
                        // 解析属性加成
                        $stats = safeJsonDecode($eq['stats'], true);
                        $stat_summary = '';
                        if ($stats && count($stats) > 0) {
                            $stat_items = [];
                            foreach ($stats as $key => $value) {
                                if (is_numeric($value) && $value != 0) {
                                    $stat_name = $stat_names[$key] ?? $key;
                                    $stat_items[] = $stat_name . '+' . $value;
                                }
                            }
                            $stat_summary = implode(', ', $stat_items);
                            if (strlen($stat_summary) > 30) {
                                $stat_summary = substr($stat_summary, 0, 30) . '...';
                            }
                        }
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($eq['name']); ?></strong>
                            <?php if (!empty($eq['desc'])): ?>
                            <div class="text-muted small mt-1"><?php echo htmlspecialchars(substr($eq['desc'], 0, 50)); ?><?php echo strlen($eq['desc']) > 50 ? '...' : ''; ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($eq['slot'])): ?>
                            <span class="badge bg-secondary"><?php echo $slot_options[$eq['slot']] ?? $eq['slot']; ?></span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-warning">
                                <?php echo $eq['price']; ?> 虞元
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $stock_class; ?>"><?php echo $stock_text; ?></span>
                        </td>
                        <td>
                            <?php if (!empty($stat_summary)): ?>
                            <span class="badge bg-success small"><?php echo htmlspecialchars($stat_summary); ?></span>
                            <?php else: ?>
                            <span class="text-muted small">无加成</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($eq['is_selling'] == 1): ?>
                            <span class="badge bg-success">上架中</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">已下架</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?action=view&name=<?php echo urlencode($eq['name']); ?>" class="btn btn-outline-primary" title="查看">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="?action=edit&name=<?php echo urlencode($eq['name']); ?>" class="btn btn-outline-success" title="编辑">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?action=toggle&name=<?php echo urlencode($eq['name']); ?>" class="btn btn-outline-<?php echo $eq['is_selling'] == 1 ? 'warning' : 'info'; ?>" title="<?php echo $eq['is_selling'] == 1 ? '下架' : '上架'; ?>">
                                    <i class="fas fa-<?php echo $eq['is_selling'] == 1 ? 'arrow-down' : 'arrow-up'; ?>"></i>
                                </a>
                                <?php if (isSuperAdmin()): ?>
                                <a href="?action=delete&name=<?php echo urlencode($eq['name']); ?>" 
                                   class="btn btn-outline-danger btn-delete" title="删除" onclick="return confirm('确定删除装备 <?php echo addslashes($eq['name']); ?> 吗？')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($equips)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="fas fa-tshirt fa-2x mb-3 d-block"></i>
                            暂无装备数据，请先添加装备
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 装备统计 -->
<div class="row mt-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <?php
                $stmt = $db->query("SELECT COUNT(*) FROM items WHERE type = 'equip'");
                $equip_count = $stmt->fetchColumn();
                ?>
                <div class="text-muted small mb-2">总装备数</div>
                <div class="fs-3 fw-bold text-primary"><?php echo $equip_count; ?></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <?php
                $stmt = $db->query("SELECT COUNT(*) FROM items WHERE type = 'equip' AND is_selling = 1");
                $selling_count = $stmt->fetchColumn();
                ?>
                <div class="text-muted small mb-2">上架装备</div>
                <div class="fs-3 fw-bold text-success"><?php echo $selling_count; ?></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <?php
                $stmt = $db->query("SELECT COUNT(DISTINCT slot) FROM items WHERE type = 'equip' AND slot != ''");
                $slot_types = $stmt->fetchColumn();
                ?>
                <div class="text-muted small mb-2">装备位置类型</div>
                <div class="fs-3 fw-bold text-warning"><?php echo $slot_types; ?></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <?php
                $stmt = $db->query("SELECT COUNT(*) FROM items WHERE type = 'equip' AND stock_qty = 0");
                $out_of_stock = $stmt->fetchColumn();
                ?>
                <div class="text-muted small mb-2">缺货装备</div>
                <div class="fs-3 fw-bold text-danger"><?php echo $out_of_stock; ?></div>
            </div>
        </div>
    </div>
</div>

<!-- 装备位置分布 -->
<div class="card mt-4">
    <div class="card-header">
        <i class="fas fa-layer-group me-2"></i>装备位置分布
    </div>
    <div class="card-body">
        <div class="row">
            <?php
            $stmt = $db->query("SELECT slot, COUNT(*) as count FROM items WHERE type = 'equip' AND slot != '' GROUP BY slot ORDER BY count DESC");
            $slot_dist = $stmt->fetchAll();
            
            foreach ($slot_dist as $slot_item):
                $slot_name = $slot_options[$slot_item['slot']] ?? $slot_item['slot'];
                $percentage = $equip_count > 0 ? round(($slot_item['count'] / $equip_count) * 100, 1) : 0;
            ?>
            <div class="col-md-3 mb-3">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <div class="text-muted small mb-1"><?php echo $slot_name; ?></div>
                        <div class="fs-4 fw-bold text-primary"><?php echo $slot_item['count']; ?></div>
                        <div class="progress mt-2" style="height: 8px;">
                            <div class="progress-bar bg-info" role="progressbar" 
                                 style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                        <div class="text-muted small mt-1"><?php echo $percentage; ?>%</div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php
            $stmt = $db->query("SELECT COUNT(*) as count FROM items WHERE type = 'equip' AND (slot = '' OR slot IS NULL)");
            $no_slot = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            if ($no_slot > 0):
            ?>
            <div class="col-md-3 mb-3">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <div class="text-muted small mb-1">未指定位置</div>
                        <div class="fs-4 fw-bold text-secondary"><?php echo $no_slot; ?></div>
                        <div class="progress mt-2" style="height: 8px;">
                            <div class="progress-bar bg-secondary" role="progressbar" 
                                 style="width: <?php echo $equip_count > 0 ? round(($no_slot / $equip_count) * 100, 1) : 0; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php elseif ($action === 'view' && $item): ?>
<!-- 查看装备详情 -->
<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle me-2"></i>基本信息
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-4">装备名称:</dt>
                    <dd class="col-sm-8"><strong><?php echo htmlspecialchars($item['name']); ?></strong></dd>
                    
                    <dt class="col-sm-4">装备位置:</dt>
                    <dd class="col-sm-8">
                        <?php if (!empty($item['slot'])): ?>
                        <span class="badge bg-secondary"><?php echo $slot_options[$item['slot']] ?? $item['slot']; ?></span>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                    </dd>
                    
                    <dt class="col-sm-4">价格:</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-warning">
                            <?php echo $item['price']; ?> 虞元
                        </span>
                    </dd>
                    
                    <dt class="col-sm-4">库存:</dt>
                    <dd class="col-sm-8">
                        <?php if ($item['stock_qty'] == -1): ?>
                        <span class="badge bg-success">无限库存</span>
                        <?php else: ?>
                        <span class="badge bg-<?php echo $item['stock_qty'] <= 5 ? 'danger' : ($item['stock_qty'] <= 20 ? 'warning' : 'info'); ?>">
                            <?php echo $item['stock_qty']; ?> 个
                        </span>
                        <?php endif; ?>
                    </dd>
                    
                    <dt class="col-sm-4">个人持有上限:</dt>
                    <dd class="col-sm-8">
                        <?php if ($item['max_hold'] == 0): ?>
                        <span class="badge bg-success">无限制</span>
                        <?php else: ?>
                        <span class="badge bg-info"><?php echo $item['max_hold']; ?> 个</span>
                        <?php endif; ?>
                    </dd>
                    
                    <dt class="col-sm-4">状态:</dt>
                    <dd class="col-sm-8">
                        <?php if ($item['is_selling'] == 1): ?>
                        <span class="badge bg-success">上架中</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">已下架</span>
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <i class="fas fa-file-alt me-2"></i>描述
            </div>
            <div class="card-body">
                <?php echo nl2br(htmlspecialchars($item['desc'])); ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-bar me-2"></i>属性加成
            </div>
            <div class="card-body">
                <?php
                $stats = safeJsonDecode($item['stats'], true);
                if ($stats && count($stats) > 0):
                ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>属性</th>
                                <th>加成值</th>
                                <th>进度</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats as $key => $value): 
                                if (is_numeric($value) && $value != 0):
                                    $stat_name = $stat_names[$key] ?? $key;
                            ?>
                            <tr>
                                <td width="30%"><?php echo $stat_name; ?></td>
                                <td width="20%">
                                    <span class="badge bg-<?php echo $value > 0 ? 'success' : 'danger'; ?>">
                                        <?php echo $value > 0 ? '+' : ''; ?><?php echo $value; ?>
                                    </span>
                                </td>
                                <td width="50%">
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-<?php echo $value > 0 ? 'success' : 'danger'; ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo min(100, abs($value) * 2); ?>%">
                                            <?php echo $value; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>该装备没有属性加成
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <i class="fas fa-bolt me-2"></i>使用效果
            </div>
            <div class="card-body">
                <?php
                $effect = safeJsonDecode($item['effect'], true);
                if ($effect && count($effect) > 0):
                ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>效果类型</th>
                                <th>数值</th>
                                <th>描述</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($effect as $key => $value): 
                                $effect_name = [
                                    'yuCoin' => '虞元', 'reputation' => '名誉',
                                    'change_name' => '改名', 'heal' => '治疗'
                                ][$key] ?? $key;
                            ?>
                            <tr>
                                <td width="30%"><?php echo $effect_name; ?></td>
                                <td width="20%">
                                    <span class="badge bg-<?php echo is_numeric($value) && $value > 0 ? 'success' : 'info'; ?>">
                                        <?php echo $value; ?>
                                    </span>
                                </td>
                                <td width="50%">
                                    <?php if ($key === 'change_name'): ?>
                                    允许用户修改角色姓名
                                    <?php elseif ($key === 'yuCoin' || $key === 'reputation'): ?>
                                    增加 <?php echo $value; ?> 点<?php echo $effect_name; ?>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>该装备没有特殊效果
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-cogs me-2"></i>原始数据
                </div>
                <div>
                    <a href="?action=edit&name=<?php echo urlencode($item['name']); ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-edit me-1"></i>编辑装备
                    </a>
                    <a href="items.php?action=edit&name=<?php echo urlencode($item['name']); ?>" class="btn btn-sm btn-outline-secondary ms-1">
                        <i class="fas fa-external-link-alt me-1"></i>完整编辑
                    </a>
                </div>
            </div>
            <div class="card-body">
                <pre class="bg-light p-3 rounded" style="max-height: 300px; overflow: auto;">
<?php 
$item_display = $item;
unset($item_display['stats']);
unset($item_display['effect']);
$item_display['stats'] = safeJsonDecode($item['stats'], true);
$item_display['effect'] = safeJsonDecode($item['effect'], true);
echo htmlspecialchars(json_encode($item_display, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); 
?>
                </pre>
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="equip.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>返回列表
    </a>
</div>

<?php elseif ($action === 'edit'): ?>
<!-- 编辑/添加装备表单 -->
<div class="card">
    <div class="card-header">
        <i class="<?php echo $page_icon; ?> me-2"></i><?php echo $page_title; ?>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="save_equip" value="1">
            <input type="hidden" name="original_name" value="<?php echo htmlspecialchars($item['name'] ?? ''); ?>">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">装备名称 *</label>
                    <input type="text" class="form-control" id="name" name="name" 
                           value="<?php echo htmlspecialchars($item['name'] ?? ''); ?>" required>
                    <div class="form-text">装备的唯一名称，不能重复</div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="slot" class="form-label">装备位置 *</label>
                    <select class="form-select" id="slot" name="slot" required>
                        <?php foreach ($slot_options as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo (($item['slot'] ?? '') === $value) ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">选择装备穿戴的位置</div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="price" class="form-label">价格 (虞元) *</label>
                    <input type="number" class="form-control" id="price" name="price" 
                           value="<?php echo htmlspecialchars($item['price'] ?? 0); ?>" min="0" required>
                    <input type="hidden" name="currency" value="yuCoin">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="stock_qty" class="form-label">库存数量</label>
                    <input type="number" class="form-control" id="stock_qty" name="stock_qty" 
                           value="<?php echo $item['stock_qty'] ?? -1; ?>" min="-1">
                    <div class="form-text">-1 表示无限库存</div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="max_hold" class="form-label">个人持有上限</label>
                    <input type="number" class="form-control" id="max_hold" name="max_hold" 
                           value="<?php echo $item['max_hold'] ?? 0; ?>" min="0">
                    <div class="form-text">0 表示无限制</div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label d-block">状态</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="is_selling" name="is_selling" value="1" 
                               <?php echo (($item['is_selling'] ?? 1) == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_selling">上架销售</label>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="desc" class="form-label">装备描述</label>
                <textarea class="form-control" id="desc" name="desc" rows="3"><?php echo htmlspecialchars($item['desc'] ?? ''); ?></textarea>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="stats" class="form-label">属性加成 (JSON)</label>
                    <textarea class="form-control" id="stats" name="stats" rows="6"><?php 
                        if (isset($item['stats']) && $item['stats'] !== '{}') {
                            echo htmlspecialchars($item['stats']);
                        } else {
                            echo '{}';
                        }
                    ?></textarea>
                    <div class="form-text">例如: {"stat_face": 5, "stat_charm": 3}</div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="applyStatTemplate()">
                            <i class="fas fa-magic me-1"></i>常用属性模板
                        </button>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="effect" class="form-label">使用效果 (JSON)</label>
                    <textarea class="form-control" id="effect" name="effect" rows="6"><?php 
                        if (isset($item['effect']) && $item['effect'] !== '{}') {
                            echo htmlspecialchars($item['effect']);
                        } else {
                            echo '{}';
                        }
                    ?></textarea>
                    <div class="form-text">例如: {"yuCoin": 100, "change_name": 1}</div>
                </div>
            </div>
            
            <div class="d-flex justify-content-between mt-4">
                <a href="equip.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>取消
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>保存装备
                </button>
            </div>
        </form>
    </div>
</div>

<!-- JSON模板助手 -->
<div class="card mt-4">
    <div class="card-header">
        <i class="fas fa-magic me-2"></i>快速模板
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <button class="btn btn-outline-info w-100 mb-2" onclick="applyTemplate('weapon')">
                    武器模板
                </button>
            </div>
            <div class="col-md-4">
                <button class="btn btn-outline-success w-100 mb-2" onclick="applyTemplate('armor')">
                    防具模板
                </button>
            </div>
            <div class="col-md-4">
                <button class="btn btn-outline-warning w-100 mb-2" onclick="formatAllJson()">
                    格式化JSON
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function applyTemplate(templateType) {
    if (templateType === 'weapon') {
        document.getElementById('slot').value = 'acc1';
        document.getElementById('stats').value = '{"stat_face": 3, "stat_charm": 2, "stat_body": 5}';
        document.getElementById('effect').value = '{}';
        document.getElementById('desc').value = '锋利的武器，提升战斗力';
    } else if (templateType === 'armor') {
        document.getElementById('slot').value = 'top';
        document.getElementById('stats').value = '{"stat_body": 8, "stat_obed": 3}';
        document.getElementById('effect').value = '{}';
        document.getElementById('desc').value = '坚固的防具，提供良好保护';
    }
    formatAllJson();
}

function applyStatTemplate() {
    var template = {
        "stat_face": "颜值",
        "stat_charm": "魅力", 
        "stat_intel": "智力",
        "stat_biz": "商业",
        "stat_talk": "口才",
        "stat_body": "体能",
        "stat_art": "才艺",
        "stat_obed": "服从_威慑"
    };
    
    var templateText = JSON.stringify(template, null, 2);
    if (confirm('是否使用属性键名模板？这会将当前JSON替换为属性键名列表。')) {
        document.getElementById('stats').value = '// 属性键名参考\n' + templateText + '\n\n// 实际数据示例\n{\n  "stat_face": 5,\n  "stat_charm": 3\n}';
    }
}

function formatAllJson() {
    formatJson('stats');
    formatJson('effect');
}

function formatJson(textareaId) {
    var textarea = document.getElementById(textareaId);
    try {
        var obj = JSON.parse(textarea.value);
        textarea.value = JSON.stringify(obj, null, 2);
    } catch(e) {
        // 忽略JSON格式错误
    }
}
</script>

<?php endif; ?>

<?php
require_once 'footer.php';
?>