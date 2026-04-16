<?php
require_once 'config.php';
checkLogin();
requirePermission('fund_types','view');

$db = getDB();
$msg=''; $msg_type='';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pa = $_POST['action'] ?? '';
    if ($pa === 'save') {
        $is_add = empty($_POST['orig_name']);
        requirePermission('fund_types',$is_add?'add':'edit');
        $name        = trim($_POST['name']        ?? '');
        $cost        = floatval($_POST['cost']    ?? 0);
        $min_return  = floatval($_POST['min_return'] ?? 0);
        $max_return  = floatval($_POST['max_return'] ?? 0);
        $period      = intval($_POST['period']    ?? 7);
        $overdue     = intval($_POST['overdue_days'] ?? 8);
        $is_index    = isset($_POST['is_index_bound'])?1:0;
        $is_active   = isset($_POST['is_active'])?1:0;
        $desc        = trim($_POST['desc'] ?? '');
        if (empty($name)){ $msg='基金名不能为空'; $msg_type='danger'; }
        else {
            try {
                $old=null;
                if (!$is_add){ $r=$db->prepare("SELECT * FROM fund_types WHERE name=?"); $r->execute([$_POST['orig_name']]); $old=$r->fetch(); }
                $db->prepare("INSERT OR REPLACE INTO fund_types (name,cost,min_return,max_return,period,overdue_days,is_index_bound,is_active,`desc`) VALUES (?,?,?,?,?,?,?,?,?)")
                   ->execute([$name,$cost,$min_return,$max_return,$period,$overdue,$is_index,$is_active,$desc]);
                logAction('fund_types',$is_add?'create':'update',$name,$old,['cost'=>$cost,'period'=>$period]);
                setFlash('success','基金产品已保存'); header('Location: fund_types.php'); exit();
            } catch(Exception $e){ $msg='保存失败：'.$e->getMessage(); $msg_type='danger'; }
        }
    }
    if ($pa === 'delete') {
        requirePermission('fund_types','delete');
        $dn=$_POST['del_name']??'';
        $old=$db->prepare("SELECT * FROM fund_types WHERE name=?"); $old->execute([$dn]); $old=$old->fetch();
        $db->prepare("DELETE FROM fund_types WHERE name=?")->execute([$dn]);
        logAction('fund_types','delete',$dn,$old);
        setFlash('success','基金产品已删除'); header('Location: fund_types.php'); exit();
    }
    if ($pa === 'toggle') {
        requirePermission('fund_types','edit');
        $tn=$_POST['toggle_name']??'';
        $cur=$db->prepare("SELECT is_active FROM fund_types WHERE name=?"); $cur->execute([$tn]); $cur=$cur->fetchColumn();
        $db->prepare("UPDATE fund_types SET is_active=? WHERE name=?")->execute([$cur?0:1,$tn]);
        logAction('fund_types','update',$tn,['is_active'=>$cur],['is_active'=>$cur?0:1]);
        setFlash('success','状态已切换'); header('Location: fund_types.php'); exit();
    }
}

$action = $_GET['action'] ?? 'list';
$name   = $_GET['name']   ?? '';
$edit_fund = null;
if ($action==='edit' && !empty($name)){
    $r=$db->prepare("SELECT * FROM fund_types WHERE name=?"); $r->execute([$name]); $edit_fund=$r->fetch();
}
if ($action==='add') $edit_fund=['name'=>'','cost'=>0,'min_return'=>0,'max_return'=>0,'period'=>7,'overdue_days'=>8,'is_index_bound'=>0,'is_active'=>1,'desc'=>''];

$funds = $db->query("SELECT f.*, (SELECT COUNT(*) FROM fund_holdings h WHERE h.fund_type=f.name) AS holder_cnt FROM fund_types f ORDER BY is_active DESC, name")->fetchAll();

$page_title='基金产品'; $page_icon='fas fa-chart-line'; $page_subtitle='理财产品配置';
require_once 'header.php';
?>

<?php if($msg): ?>
<div class="alert alert-<?php echo $msg_type; ?> border-0 rounded-3 alert-dismissible"><?php echo htmlspecialchars($msg); ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if ($edit_fund !== null): ?>
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-pen me-2"></i><?php echo $action==='add'?'新增基金产品':'编辑基金产品'; ?></span>
    <a href="fund_types.php" class="btn btn-sm btn-outline-secondary">返回</a>
  </div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="orig_name" value="<?php echo htmlspecialchars($edit_fund['name']); ?>">
      <div class="row g-3">
        <div class="col-md-3"><label class="form-label fw-semibold small">基金名称 *</label>
          <input type="text" class="form-control" name="name" required value="<?php echo htmlspecialchars($edit_fund['name']); ?>"></div>
        <div class="col-md-2"><label class="form-label fw-semibold small">申购费用</label>
          <input type="number" step="0.01" class="form-control" name="cost" value="<?php echo $edit_fund['cost']; ?>"></div>
        <div class="col-md-2"><label class="form-label fw-semibold small">最低回报率%</label>
          <input type="number" step="0.01" class="form-control" name="min_return" value="<?php echo $edit_fund['min_return']; ?>"></div>
        <div class="col-md-2"><label class="form-label fw-semibold small">最高回报率%</label>
          <input type="number" step="0.01" class="form-control" name="max_return" value="<?php echo $edit_fund['max_return']; ?>"></div>
        <div class="col-md-1"><label class="form-label fw-semibold small">周期(天)</label>
          <input type="number" class="form-control" name="period" min="1" value="<?php echo $edit_fund['period']; ?>"></div>
        <div class="col-md-2"><label class="form-label fw-semibold small">超期宽限(天)</label>
          <input type="number" class="form-control" name="overdue_days" min="0" value="<?php echo $edit_fund['overdue_days']??8; ?>"></div>
        <div class="col-md-6"><label class="form-label fw-semibold small">描述</label>
          <input type="text" class="form-control" name="desc" value="<?php echo htmlspecialchars($edit_fund['desc']??''); ?>"></div>
        <div class="col-md-3">
          <div class="form-check mt-4">
            <input class="form-check-input" type="checkbox" name="is_index_bound" value="1" <?php echo $edit_fund['is_index_bound']?'checked':''; ?>>
            <label class="form-check-label">与指数绑定</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" <?php echo $edit_fund['is_active']?'checked':''; ?>>
            <label class="form-check-label">开放申购</label>
          </div>
        </div>
      </div>
      <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>保存</button>
        <a href="fund_types.php" class="btn btn-outline-secondary">取消</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-list me-2"></i>基金产品列表</span>
    <?php if(can('fund_types','add')): ?>
    <a href="fund_types.php?action=add" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>新增</a>
    <?php endif; ?>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover datatable align-middle mb-0">
      <thead><tr class="table-active">
        <th class="ps-4">基金名称</th><th>申购费</th><th>回报率区间</th>
        <th>周期</th><th>持有人数</th><th>状态</th><th class="pe-4">操作</th>
      </tr></thead>
      <tbody>
      <?php foreach($funds as $f): ?>
      <tr>
        <td class="ps-4 fw-semibold"><?php echo htmlspecialchars($f['name']); ?>
          <?php if($f['is_index_bound']): ?><span class="badge bg-info ms-1 small">指数</span><?php endif; ?>
        </td>
        <td><?php echo $f['cost']; ?></td>
        <td><?php echo $f['min_return']; ?>% ~ <?php echo $f['max_return']; ?>%</td>
        <td><?php echo $f['period']; ?> 天</td>
        <td><span class="badge bg-primary rounded-pill"><?php echo $f['holder_cnt']??0; ?></span></td>
        <td><?php echo $f['is_active']?"<span class='badge bg-success'>开放</span>":"<span class='badge bg-secondary'>关闭</span>"; ?></td>
        <td class="pe-4">
          <div class="d-flex gap-1">
            <?php if(can('fund_types','edit')): ?>
            <a href="fund_types.php?action=edit&name=<?php echo urlencode($f['name']); ?>" class="btn btn-sm btn-outline-primary py-0 px-2">编辑</a>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="toggle_name" value="<?php echo htmlspecialchars($f['name']); ?>">
              <button class="btn btn-sm py-0 px-2 <?php echo $f['is_active']?'btn-outline-warning':'btn-outline-success'; ?>">
                <?php echo $f['is_active']?'关闭':'开放'; ?>
              </button>
            </form>
            <?php endif; ?>
            <?php if(can('fund_types','delete')): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('确认删除该基金产品？')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="del_name" value="<?php echo htmlspecialchars($f['name']); ?>">
              <button class="btn btn-sm btn-outline-danger py-0 px-2">删除</button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once 'footer.php'; ?>
