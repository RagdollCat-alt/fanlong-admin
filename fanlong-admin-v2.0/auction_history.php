<?php
require_once 'config.php';
checkLogin();
requirePermission('auction_history','view');

$db = getDB();

// 筛选参数
$f_item   = trim($_GET['item']   ?? '');
$f_winner = trim($_GET['winner'] ?? '');
$f_status = trim($_GET['status'] ?? '');

$where = []; $params = [];
if ($f_item   !== '') { $where[] = "item_name LIKE ?";   $params[] = "%$f_item%"; }
if ($f_winner !== '') { $where[] = "winner_name LIKE ?"; $params[] = "%$f_winner%"; }
if ($f_status !== '') { $where[] = "status=?";           $params[] = $f_status; }

$sql = "SELECT * FROM auction_history"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " ORDER BY id DESC LIMIT 500";

$stmt = $db->prepare($sql); $stmt->execute($params);
$records = $stmt->fetchAll();

// 状态选项
$statuses = $db->query("SELECT DISTINCT status FROM auction_history ORDER BY status")->fetchAll(PDO::FETCH_COLUMN, 0);

$page_title='竞拍历史'; $page_icon='fas fa-gavel'; $page_subtitle='拍卖成交记录（只读）';
require_once 'header.php';
?>

<div class="alert alert-info border-0 rounded-3 mb-4 py-2 small">
  <i class="fas fa-gavel me-2"></i>
  竞拍记录由机器人自动写入，此处仅供查看。如需操作竞拍，请通过群聊与机器人交互。
</div>

<!-- 筛选栏 -->
<form method="GET" class="card mb-4">
  <div class="card-body py-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small fw-semibold mb-1">物品名</label>
        <input type="text" class="form-control form-control-sm" name="item" value="<?php echo htmlspecialchars($f_item); ?>" placeholder="模糊搜索">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold mb-1">得标者</label>
        <input type="text" class="form-control form-control-sm" name="winner" value="<?php echo htmlspecialchars($f_winner); ?>" placeholder="模糊搜索">
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-semibold mb-1">状态</label>
        <select class="form-select form-select-sm" name="status">
          <option value="">全部</option>
          <?php foreach($statuses as $s): ?>
          <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $f_status===$s?'selected':''; ?>>
            <?php echo htmlspecialchars($s); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">筛选</button>
        <a href="auction_history.php" class="btn btn-sm btn-outline-secondary ms-1">重置</a>
      </div>
    </div>
  </div>
</form>

<div class="card">
  <div class="card-header">
    <i class="fas fa-list me-2"></i>竞拍记录（<?php echo count($records); ?> 条，最多显示 500）
  </div>
  <div class="card-body p-0">
    <table class="table table-hover datatable align-middle mb-0 small">
      <thead><tr class="table-active">
        <th class="ps-4">ID</th>
        <th>所在群</th>
        <th>物品名</th>
        <th>成交价</th>
        <th>得标者</th>
        <th>状态</th>
        <th class="pe-4">时间</th>
      </tr></thead>
      <tbody>
      <?php foreach($records as $row): ?>
      <tr>
        <td class="ps-4 text-muted small">#<?php echo $row['id']; ?></td>
        <td class="text-muted small"><?php echo htmlspecialchars($row['group_id'] ?? '—'); ?></td>
        <td class="fw-semibold"><?php echo htmlspecialchars($row['item_name'] ?? '—'); ?></td>
        <td>
          <?php $price = intval($row['price'] ?? 0); ?>
          <span class="fw-semibold text-warning"><?php echo number_format($price); ?></span>
          <span class="text-muted small"><?php echo t('term_yuCoin','虞元'); ?></span>
        </td>
        <td><?php echo htmlspecialchars($row['winner_name'] ?? '—'); ?></td>
        <td>
          <?php
          $status = $row['status'] ?? '';
          $sc = $status==='成交' ? 'success' : ($status==='流拍' ? 'secondary' : 'info');
          echo "<span class='badge bg-$sc'>".htmlspecialchars($status)."</span>";
          ?>
        </td>
        <td class="pe-4 text-muted small"><?php echo htmlspecialchars(substr($row['created_at'] ?? '', 0, 19)); ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once 'footer.php'; ?>
