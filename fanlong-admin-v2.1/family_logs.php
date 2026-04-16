<?php
require_once 'config.php';
checkLogin();
requirePermission('family_logs','view');

$db = getDB();

// 筛选参数
$f_family   = trim($_GET['family']   ?? '');
$f_date_from = trim($_GET['date_from'] ?? '');
$f_date_to   = trim($_GET['date_to']   ?? '');
$f_op        = trim($_GET['op']        ?? '');

$where = []; $params = [];
if ($f_family   !== '') { $where[] = "fl.family_name LIKE ?";  $params[] = "%$f_family%"; }
if ($f_op       !== '') { $where[] = "fl.operator_id LIKE ?";  $params[] = "%$f_op%"; }
if ($f_date_from !== '') { $where[] = "fl.created_at >= ?";    $params[] = $f_date_from . ' 00:00:00'; }
if ($f_date_to   !== '') { $where[] = "fl.created_at <= ?";    $params[] = $f_date_to . ' 23:59:59'; }

$sql = "SELECT fl.*
        FROM family_logs fl
        " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
        ORDER BY fl.id DESC
        LIMIT 500";

$stmt = $db->prepare($sql); $stmt->execute($params);
$logs = $stmt->fetchAll();

$page_title='家族日志'; $page_icon='fas fa-scroll'; $page_subtitle='家族积分变动记录（只读）';
require_once 'header.php';
?>

<div class="alert alert-info border-0 rounded-3 mb-4 py-2 small">
  <i class="fas fa-scroll me-2"></i>
  家族积分变动由机器人自动记录，此处仅供查看。积分变动正数为增加，负数为扣除。
</div>

<!-- 筛选栏 -->
<form method="GET" class="card mb-4">
  <div class="card-body py-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label small fw-semibold mb-1">家族名</label>
        <input type="text" class="form-control form-control-sm" name="family" value="<?php echo htmlspecialchars($f_family); ?>" placeholder="模糊搜索">
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-semibold mb-1">操作人ID</label>
        <input type="text" class="form-control form-control-sm" name="op" value="<?php echo htmlspecialchars($f_op); ?>" placeholder="模糊搜索">
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-semibold mb-1">开始日期</label>
        <input type="date" class="form-control form-control-sm" name="date_from" value="<?php echo htmlspecialchars($f_date_from); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-semibold mb-1">结束日期</label>
        <input type="date" class="form-control form-control-sm" name="date_to" value="<?php echo htmlspecialchars($f_date_to); ?>">
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">筛选</button>
        <a href="family_logs.php" class="btn btn-sm btn-outline-secondary ms-1">重置</a>
      </div>
    </div>
  </div>
</form>

<div class="card">
  <div class="card-header">
    <i class="fas fa-list me-2"></i>家族日志（<?php echo count($logs); ?> 条，最多显示 500）
  </div>
  <div class="card-body p-0">
    <table class="table table-hover datatable align-middle mb-0 small">
      <thead><tr class="table-active">
        <th class="ps-4">ID</th>
        <th>家族</th>
        <th>操作人</th>
        <th>积分变动</th>
        <th>原因</th>
        <th>变动后总分</th>
        <th class="pe-4">时间</th>
      </tr></thead>
      <tbody>
      <?php foreach($logs as $row): ?>
      <tr>
        <td class="ps-4 text-muted small">#<?php echo $row['id']; ?></td>
        <td class="fw-semibold">
          <?php echo htmlspecialchars($row['family_name'] ?? '—'); ?>
        </td>
        <td class="text-muted small"><?php echo htmlspecialchars($row['operator_id'] ?? '—'); ?></td>
        <td>
          <?php $delta = intval($row['change_score'] ?? 0); ?>
          <?php if($delta > 0): ?>
          <span class="fw-semibold text-success">+<?php echo $delta; ?></span>
          <?php elseif($delta < 0): ?>
          <span class="fw-semibold text-danger"><?php echo $delta; ?></span>
          <?php else: ?>
          <span class="text-muted">0</span>
          <?php endif; ?>
        </td>
        <td><?php echo htmlspecialchars($row['reason'] ?? '—'); ?></td>
        <td class="fw-semibold"><?php echo number_format(intval($row['snapshot_score'] ?? 0)); ?></td>
        <td class="pe-4 text-muted small"><?php echo htmlspecialchars(substr($row['created_at'] ?? '', 0, 19)); ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once 'footer.php'; ?>
