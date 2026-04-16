<?php
require_once 'config.php';
checkLogin();
requirePermission('fund_holdings','view');

$db = getDB();

$filter_user = trim($_GET['user'] ?? '');
$filter_fund = trim($_GET['fund'] ?? '');

$sql = "SELECT h.user_id, u.name, h.fund_type, h.buy_time, h.cost,
        ft.period, ft.min_return, ft.max_return,
        CAST((julianday('now') - julianday(h.buy_time)) AS INTEGER) AS days_held
        FROM fund_holdings h
        LEFT JOIN users u ON h.user_id=u.id
        LEFT JOIN fund_types ft ON h.fund_type=ft.name
        WHERE 1=1";
$params=[];
if($filter_user){ $sql.=" AND (h.user_id LIKE ? OR u.name LIKE ?)"; $params[]="%$filter_user%"; $params[]="%$filter_user%"; }
if($filter_fund){ $sql.=" AND h.fund_type LIKE ?"; $params[]="%$filter_fund%"; }
$sql.=" ORDER BY h.buy_time DESC";
$stmt=$db->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll();

$fund_names = $db->query("SELECT DISTINCT name FROM fund_types ORDER BY name")->fetchAll(PDO::FETCH_COLUMN,0);

// 统计
$total = count($rows);
$total_cost = array_sum(array_column($rows,'cost'));

$page_title='基金持仓'; $page_icon='fas fa-wallet'; $page_subtitle='用户基金持仓（只读）';
require_once 'header.php';
?>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card"><div class="card-body py-3 px-4 d-flex align-items-center gap-3">
      <div style="width:42px;height:42px;border-radius:10px;background:#667eea22;display:flex;align-items:center;justify-content:center;">
        <i class="fas fa-wallet" style="color:#667eea"></i></div>
      <div><div class="fw-bold fs-5"><?php echo $total; ?></div><div class="text-muted small">持仓总笔数</div></div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card"><div class="card-body py-3 px-4 d-flex align-items-center gap-3">
      <div style="width:42px;height:42px;border-radius:10px;background:#22c55e22;display:flex;align-items:center;justify-content:center;">
        <i class="fas fa-coins" style="color:#22c55e"></i></div>
      <div><div class="fw-bold fs-5"><?php echo number_format($total_cost,1); ?></div><div class="text-muted small">总申购金额</div></div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card"><div class="card-body py-3 px-4 d-flex align-items-center gap-3">
      <div style="width:42px;height:42px;border-radius:10px;background:#f59e0b22;display:flex;align-items:center;justify-content:center;">
        <i class="fas fa-chart-line" style="color:#f59e0b"></i></div>
      <div><div class="fw-bold fs-5"><?php echo count($fund_names); ?></div><div class="text-muted small">基金产品种类</div></div>
    </div></div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-body">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label fw-semibold small">用户</label>
        <input type="text" class="form-control" name="user" value="<?php echo htmlspecialchars($filter_user); ?>" placeholder="QQ号或角色名">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold small">基金产品</label>
        <input type="text" class="form-control" name="fund" value="<?php echo htmlspecialchars($filter_fund); ?>" placeholder="基金名称" list="fundList">
        <datalist id="fundList"><?php foreach($fund_names as $fn): ?><option value="<?php echo htmlspecialchars($fn); ?>"><?php endforeach; ?></datalist>
      </div>
      <div class="col-md-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-search me-1"></i>查询</button>
        <a href="fund_holdings.php" class="btn btn-outline-secondary">重置</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="fas fa-list me-2"></i>持仓记录（<?php echo count($rows); ?> 笔）<span class="badge bg-secondary ms-2">只读</span></div>
  <div class="card-body p-0">
    <table class="table table-hover datatable align-middle mb-0">
      <thead><tr class="table-active">
        <th class="ps-4">用户</th><th>基金名称</th><th>申购金额</th>
        <th>申购时间</th><th>持有天数</th><th>周期</th><th class="pe-4">状态</th>
      </tr></thead>
      <tbody>
      <?php foreach($rows as $r):
        $days = intval($r['days_held']);
        $period = intval($r['period'] ?? 7);
        $is_matured = $days >= $period;
        $is_overdue = $days >= ($period + 8);
      ?>
      <tr>
        <td class="ps-4">
          <div class="fw-semibold"><?php echo htmlspecialchars($r['name']??'—'); ?></div>
          <div class="text-muted small"><?php echo htmlspecialchars($r['user_id']); ?></div>
        </td>
        <td><span class="badge bg-body-secondary text-body border"><?php echo htmlspecialchars($r['fund_type']); ?></span></td>
        <td class="fw-semibold"><?php echo number_format(floatval($r['cost']),1); ?></td>
        <td class="text-muted small"><?php echo htmlspecialchars(substr($r['buy_time']??'',0,10)); ?></td>
        <td>
          <div class="d-flex align-items-center gap-2">
            <div class="progress flex-grow-1" style="height:6px;max-width:80px">
              <div class="progress-bar <?php echo $is_matured?'bg-success':'bg-primary'; ?>"
                   style="width:<?php echo min(100,round($days/$period*100)); ?>%"></div>
            </div>
            <span class="small"><?php echo $days; ?>天</span>
          </div>
        </td>
        <td><?php echo $period; ?>天</td>
        <td class="pe-4">
          <?php if($is_overdue): ?><span class="badge bg-danger">已超期</span>
          <?php elseif($is_matured): ?><span class="badge bg-success">已到期</span>
          <?php else: ?><span class="badge bg-primary">持有中</span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once 'footer.php'; ?>
