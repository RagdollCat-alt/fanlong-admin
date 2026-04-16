<?php
require_once 'config.php';
checkLogin();

$db = getDB();
$action = $_GET['action'] ?? 'list';
$name = $_GET['name'] ?? '';
$message = '';
$message_type = '';

// 物品类型选项
$item_types = [
    'equip' => '装备',
    'consumable' => '消耗品'
];

// 货币类型选项
$currency_types = [
    'yuCoin' => '虞元',
    'reputation' => '名誉'
];

// 装备位置选项
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

// 处理删除请求
if ($action === 'delete' && !empty($name) && isSuperAdmin()) {
    try {
        $stmt = $db->prepare("DELETE FROM items WHERE name = ?");
        $stmt->execute([$name]);
        $message = '物品删除成功';
        $message_type = 'success';
        $action = 'list';
    } catch (Exception $e) {
        $message = '删除失败: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// 处理上下架请求
if ($action === 'toggle' && !empty($name)) {
    try {
        $stmt = $db->prepare("SELECT is_selling FROM items WHERE name = ?");
        $stmt->execute([$name]);
        $item = $stmt->fetch();
        
        if ($item) {
            $new_status = $item['is_selling'] == 1 ? 0 : 1;
            $stmt = $db->prepare("UPDATE items SET is_selling = ? WHERE name = ?");
            $stmt->execute([$new_status, $name]);
            $message = $new_status ? '物品已上架' : '物品已下架';
            $message_type = 'success';
        }
        $action = 'list';
    } catch (Exception $e) {
        $message = '操作失败: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// 处理保存请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_item'])) {
    $name = safeInput($_POST['name'] ?? '');
    $price = intval($_POST['price'] ?? 0);
    $currency = safeInput($_POST['currency'] ?? 'yuCoin');
    $type = safeInput($_POST['type'] ?? 'consumable');
    $slot = safeInput($_POST['slot'] ?? '');
    $desc = safeInput($_POST['desc'] ?? '');
    $stats_raw = trim($_POST['stats'] ?? '');
    $effect_raw = trim($_POST['effect'] ?? '');
    // 验证 JSON 格式
    if (!empty($stats_raw) && !json_decode($stats_raw, true)) {
        $message = '属性字段 JSON 格式无效';
        $message_type = 'danger';
        $action = 'edit';
        $stats = '{}';
    } else {
        $stats = $stats_raw ?: '{}';
    }
    if (!empty($effect_raw) && !json_decode($effect_raw, true)) {
        $message = '效果字段 JSON 格式无效';
        $message_type = 'danger';
        $action = 'edit';
        $effect = '{}';
    } else {
        $effect = $effect_raw ?: '{}';
    }
    $is_selling = isset($_POST['is_selling']) ? 1 : 0;
    $stock_qty = $_POST['stock_qty'] === '' ? -1 : intval($_POST['stock_qty']);
    $max_hold = intval($_POST['max_hold'] ?? 0);

    
    $original_name = safeInput($_POST['original_name'] ?? '');
    
    // 如果有错误，跳过保存
    if ($message_type === 'danger') {
        // 保持编辑状态，显示错误消息
        $action = 'edit';
    } else {
        try {
        // 验证JSON格式
        json_decode($stats);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('属性JSON格式错误');
        }
        
        json_decode($effect);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('效果JSON格式错误');
        }
        
        if (empty($original_name)) {
            // 新增物品
            $stmt = $db->prepare("INSERT INTO items (name, price, currency, type, slot, `desc`, stats, effect, is_selling, stock_qty, max_hold) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $price, $currency, $type, $slot, $desc, $stats, $effect, $is_selling, $stock_qty, $max_hold]);
            $message = '物品添加成功';
        } else {
            // 更新物品
            $stmt = $db->prepare("UPDATE items SET name = ?, price = ?, currency = ?, type = ?, slot = ?, `desc` = ?, stats = ?, effect = ?, is_selling = ?, stock_qty = ?, max_hold = ? WHERE name = ?");
            $stmt->execute([$name, $price, $currency, $type, $slot, $desc, $stats, $effect, $is_selling, $stock_qty, $max_hold, $original_name]);
            $message = '物品更新成功';
        }
        $message_type = 'success';
        
        // 重定向到列表页
        header('Location: items.php?message=' . urlencode($message) . '&type=' . $message_type);
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
    // 查看或编辑单个物品
    $item = null;
    if (!empty($name)) {
        $stmt = $db->prepare("SELECT * FROM items WHERE name = ?");
        $stmt->execute([$name]);
        $item = $stmt->fetch();
    }
    
    if (!$item && !empty($name)) {
        $message = '物品不存在';
        $message_type = 'warning';
        $action = 'list';
    }
}

// 设置页面变量
if ($action === 'list') {
    $page_title = '物品管理';
    $page_icon = 'fas fa-shopping-bag';
    $page_subtitle = '游戏物品列表';
} elseif ($action === 'view') {
    $page_title = '查看物品';
    $page_icon = 'fas fa-eye';
    $page_subtitle = $item['name'] ?? '未知物品';
} elseif ($action === 'edit') {
    $page_title = empty($item) ? '添加物品' : '编辑物品';
    $page_icon = empty($item) ? 'fas fa-plus-circle' : 'fas fa-edit';
    $page_subtitle = empty($item) ? '创建新物品' : ($item['name'] ?? '未知物品');
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
<!-- 物品列表 -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-shopping-bag me-2"></i>物品列表
        </div>
        <div>
            <a href="?action=edit" class="btn btn-primary btn-sm">
                <i class="fas fa-plus-circle me-1"></i>添加物品
            </a>
            <a href="items.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-sync-alt me-1"></i>刷新
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>名称</th>
                        <th>类型</th>
                        <th>价格</th>
                        <th>库存</th>
                        <th>装备位置</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $db->query("SELECT * FROM items ORDER BY type, price DESC");
                    $items = $stmt->fetchAll();
                    
                    foreach ($items as $it):
                        $stock_text = $it['stock_qty'] == -1 ? '无限' : $it['stock_qty'];
                        $stock_class = $it['stock_qty'] == -1 ? 'bg-success' : ($it['stock_qty'] <= 5 ? 'bg-danger' : ($it['stock_qty'] <= 20 ? 'bg-warning' : 'bg-info'));
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($it['name']); ?></strong>
                            <?php if ($it['type'] === 'equip'): ?>
                            <span class="badge bg-purple ms-1">装备</span>
                            <?php else: ?>
                            <span class="badge bg-green ms-1">消耗品</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $item_types[$it['type']] ?? $it['type']; ?></td>
                        <td>
                            <span class="badge bg-<?php echo $it['currency'] === 'yuCoin' ? 'warning' : 'info'; ?>">
                                <?php echo $it['price']; ?> <?php echo $currency_types[$it['currency']] ?? $it['currency']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $stock_class; ?>"><?php echo $stock_text; ?></span>
                        </td>
                        <td>
                            <?php if (!empty($it['slot'])): ?>
                            <span class="badge bg-secondary"><?php echo $slot_options[$it['slot']] ?? $it['slot']; ?></span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($it['is_selling'] == 1): ?>
                            <span class="badge bg-success">上架中</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">已下架</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?action=view&name=<?php echo urlencode($it['name']); ?>" class="btn btn-outline-primary" title="查看">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="?action=edit&name=<?php echo urlencode($it['name']); ?>" class="btn btn-outline-success" title="编辑">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?action=toggle&name=<?php echo urlencode($it['name']); ?>" class="btn btn-outline-<?php echo $it['is_selling'] == 1 ? 'warning' : 'info'; ?>" title="<?php echo $it['is_selling'] == 1 ? '下架' : '上架'; ?>">
                                    <i class="fas fa-<?php echo $it['is_selling'] == 1 ? 'arrow-down' : 'arrow-up'; ?>"></i>
                                </a>
                                <?php if (isSuperAdmin()): ?>
                                <a href="?action=delete&name=<?php echo urlencode($it['name']); ?>" 
                                   class="btn btn-outline-danger btn-delete" title="删除" onclick="return confirm('确定删除物品 <?php echo addslashes($it['name']); ?> 吗？')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="fas fa-box-open fa-2x mb-3 d-block"></i>
                            暂无物品数据
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 物品统计 -->
<div class="row mt-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <?php
                $stmt = $db->query("SELECT COUNT(*) FROM items WHERE type = 'equip'");
                $equip_count = $stmt->fetchColumn();
                ?>
                <div class="text-muted small mb-2">装备数量</div>
                <div class="fs-3 fw-bold text-primary"><?php echo $equip_count; ?></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <?php
                $stmt = $db->query("SELECT COUNT(*) FROM items WHERE type = 'consumable'");
                $consumable_count = $stmt->fetchColumn();
                ?>
                <div class="text-muted small mb-2">消耗品数量</div>
                <div class="fs-3 fw-bold text-success"><?php echo $consumable_count; ?></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <?php
                $stmt = $db->query("SELECT COUNT(*) FROM items WHERE is_selling = 1");
                $selling_count = $stmt->fetchColumn();
                ?>
                <div class="text-muted small mb-2">上架物品</div>
                <div class="fs-3 fw-bold text-warning"><?php echo $selling_count; ?></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <?php
                $stmt = $db->query("SELECT COUNT(*) FROM items WHERE stock_qty = 0");
                $out_of_stock = $stmt->fetchColumn();
                ?>
                <div class="text-muted small mb-2">缺货物品</div>
                <div class="fs-3 fw-bold text-danger"><?php echo $out_of_stock; ?></div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($action === 'view' && $item): ?>
<!-- 查看物品详情 -->
<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle me-2"></i>基本信息
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-4">物品名称:</dt>
                    <dd class="col-sm-8"><strong><?php echo htmlspecialchars($item['name']); ?></strong></dd>
                    
                    <dt class="col-sm-4">类型:</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-<?php echo $item['type'] === 'equip' ? 'purple' : 'green'; ?>">
                            <?php echo $item_types[$item['type']] ?? $item['type']; ?>
                        </span>
                    </dd>
                    
                    <dt class="col-sm-4">价格:</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-<?php echo $item['currency'] === 'yuCoin' ? 'warning' : 'info'; ?>">
                            <?php echo $item['price']; ?> <?php echo $currency_types[$item['currency']] ?? $item['currency']; ?>
                        </span>
                    </dd>
                    
                    <dt class="col-sm-4">装备位置:</dt>
                    <dd class="col-sm-8">
                        <?php if (!empty($item['slot'])): ?>
                        <span class="badge bg-secondary"><?php echo $slot_options[$item['slot']] ?? $item['slot']; ?></span>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
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
                                    $stat_name = [
                                        'stat_face' => '颜值', 'stat_charm' => '魅力', 'stat_intel' => '智力',
                                        'stat_biz' => '商业', 'stat_talk' => '口才', 'stat_body' => '体能',
                                        'stat_art' => '才艺', 'stat_obed' => '服从_威慑'
                                    ][$key] ?? $key;
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
                    <i class="fas fa-info-circle me-2"></i>该物品没有属性加成
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
                    <i class="fas fa-info-circle me-2"></i>该物品没有特殊效果
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
                        <i class="fas fa-edit me-1"></i>编辑物品
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
    <a href="items.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>返回列表
    </a>
</div>

<?php elseif ($action === 'edit'): ?>
<!-- 编辑/添加物品表单 -->
<div class="card">
    <div class="card-header">
        <i class="<?php echo $page_icon; ?> me-2"></i><?php echo $page_title; ?>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="save_item" value="1">
            <input type="hidden" name="original_name" value="<?php echo htmlspecialchars($item['name'] ?? ''); ?>">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">物品名称 *</label>
                    <input type="text" class="form-control" id="name" name="name" 
                           value="<?php echo htmlspecialchars($item['name'] ?? ''); ?>" required>
                    <div class="form-text">物品的唯一名称，不能重复</div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="type" class="form-label">物品类型 *</label>
                    <select class="form-select" id="type" name="type" required onchange="toggleSlotField()">
                        <?php foreach ($item_types as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo (($item['type'] ?? '') === $value) ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="currency" class="form-label">货币类型 *</label>
                    <select class="form-select" id="currency" name="currency" required>
                        <?php foreach ($currency_types as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo (($item['currency'] ?? 'yuCoin') === $value) ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="price" class="form-label">价格 *</label>
                    <input type="number" class="form-control" id="price" name="price" 
                           value="<?php echo htmlspecialchars($item['price'] ?? 0); ?>" min="0" required>
                </div>
                
                <div class="col-md-3 mb-3" id="slotField">
                    <label for="slot" class="form-label">装备位置</label>
                    <select class="form-select" id="slot" name="slot">
                        <?php foreach ($slot_options as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo (($item['slot'] ?? '') === $value) ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">仅装备类物品需要选择</div>
                </div>
                
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
            </div>
            
            <div class="row">

                
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
                <label for="desc" class="form-label">物品描述</label>
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
                <a href="items.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>取消
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>保存物品
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
                <button class="btn btn-outline-info w-100 mb-2" onclick="applyTemplate('equip')">
                    装备模板
                </button>
            </div>
            <div class="col-md-4">
                <button class="btn btn-outline-success w-100 mb-2" onclick="applyTemplate('consumable')">
                    消耗品模板
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
function toggleSlotField() {
    var type = document.getElementById('type').value;
    var slotField = document.getElementById('slotField');
    if (type === 'equip') {
        slotField.style.display = 'block';
    } else {
        slotField.style.display = 'none';
    }
}

function applyTemplate(templateType) {
    if (templateType === 'equip') {
        document.getElementById('type').value = 'equip';
        document.getElementById('slot').value = 'head';
        document.getElementById('stats').value = '{"stat_face": 5, "stat_charm": 3}';
        document.getElementById('effect').value = '{}';
        document.getElementById('desc').value = '精致的装备，提升角色属性';
    } else if (templateType === 'consumable') {
        document.getElementById('type').value = 'consumable';
        document.getElementById('slot').value = '';
        document.getElementById('stats').value = '{}';
        document.getElementById('effect').value = '{"yuCoin": 100, "reputation": 10}';
        document.getElementById('desc').value = '使用后获得相应效果';
    }
    toggleSlotField();
    formatAllJson();
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
        alert('JSON格式错误: ' + e.message);
    }
}

// 初始显示
document.addEventListener('DOMContentLoaded', function() {
    toggleSlotField();
});
</script>

<?php endif; ?>

<?php
require_once 'footer.php';
?>