<?php
require_once 'config.php';
checkLogin();
requirePermission('families','view');

$db = getDB();
$action = $_GET['action'] ?? 'list';
$id     = $_GET['id']     ?? '';
$msg=''; $msg_type='';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pa = $_POST['action'] ?? '';
    if ($pa === 'save') {
        $is_add = empty($_POST['orig_id']);
        requirePermission('families',$is_add?'add':'edit');
        $name         = trim($_POST['name'] ?? '');
        $tier         = trim($_POST['tier'] ?? '平民');
        $manual_score = intval($_POST['manual_score'] ?? 0);
        $is_deleted   = isset($_POST['is_deleted'])?1:0;
        if (empty($name)){ $msg='家族名不能为空'; $msg_type='danger'; }
        else {
            try {
                if ($is_add) {
                    $db->prepare("INSERT INTO families (name,tier,manual_score,is_deleted) VALUES (?,?,?,?)")->execute([$name,$tier,$manual_score,$is_deleted]);
                    logAction('families','create',$name,null,['tier'=>$tier]);
                } else {
                    $old=$db->prepare("SELECT * FROM families WHERE id=?"); $old->execute([$_POST['orig_id']]); $old=$old->fetch();
                    $db->prepare("UPDATE families SET name=?,tier=?,manual_score=?,is_deleted=? WHERE id=?")->execute([$name,$tier,$manual_score,$is_deleted,$_POST['orig_id']]);
                    logAction('families','update',$_POST['orig_id'],$old,['name'=>$name,'tier'=>$tier]);
                }
                setFlash('success','家族数据已保存'); header('Location: families.php'); exit();
            } catch(Exception $e){ $msg='保存失败：'.$e->getMessage(); $msg_type='danger'; }
        }
    }
    if ($pa === 'delete') {
        requirePermission('families','delete');
        $did = $_POST['del_id'] ?? '';
        $old=$db->prepare("SELECT * FROM families WHERE id=?"); $old->execute([$did]); $old=$old->fetch();
        $db->prepare("UPDATE families SET is_deleted=1 WHERE id=?")->execute([$did]); // 软删除
        logAction('families','delete',$did,$old);
        setFlash('success','家族已标记删除'); header('Location: families.php'); exit();
    }
}

$edit_fam = null;
if (in_array($action,['edit','add']) && !empty($id)) {
    $r=$db->prepare("SELECT * FROM families WHERE id=?"); $r->execute([$id]); $edit_fam=$r->fetch();
}
if ($action==='add') $edit_fam=['id'=>'','name'=>'','tier'=>'平民','manual_score'=>0,'is_deleted'=>0];

// 从 family_rules 表读取阶层选项（按基础分从高到低）
$tier_options = $db->query("SELECT key_name,score,\"desc\" FROM family_rules WHERE rule_type='tier' ORDER BY score DESC")->fetchAll();

$show_deleted = isset($_GET['show_deleted']);
$families_sql = "SELECT f.* FROM families f
    LEFT JOIN family_rules fr ON fr.rule_type='tier' AND fr.key_name=f.tier
    " . ($show_deleted?'':'WHERE f.is_deleted=0') . "
    ORDER BY COALESCE(fr.score,0) DESC, f.name";
$families = $db->query($families_sql)->fetchAll();

$page_title='家族管理'; $page_icon='fas fa-flag'; $page_subtitle='势力与家族';
require_once 'header.php';
?>

<?php if($msg): ?>
<div class="alert alert-<?php echo $msg_type; ?> border-0 rounded-3 alert-dismissible"><?php echo htmlspecialchars($msg); ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if ($edit_fam !== null): ?>
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-pen me-2"></i><?php echo $action==='add'?'新增家族':'编辑家族'; ?></span>
    <a href="families.php" class="btn btn-sm btn-outline-secondary">返回</a>
  </div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="orig_id" value="<?php echo htmlspecialchars($edit_fam['id']); ?>">
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label fw-semibold small">家族名称 *</label>
          <input type="text" class="form-control" name="name" required value="<?php echo htmlspecialchars($edit_fam['name']); ?>"></div>
        <div class="col-md-2"><label class="form-label fw-semibold small">阶层</label>
          <select class="form-select" name="tier">
            <?php foreach($tier_options as $to): ?>
            <option value="<?php echo htmlspecialchars($to['key_name']); ?>" <?php echo ($edit_fam['tier']===$to['key_name'])?'selected':''; ?>>
              <?php echo htmlspecialchars($to['key_name']); ?>（基础分<?php echo $to['score']; ?>）
            </option>
            <?php endforeach; ?>
            <?php if(empty($tier_options)): ?>
            <option value="<?php echo htmlspecialchars($edit_fam['tier']); ?>" selected><?php echo htmlspecialchars($edit_fam['tier']); ?></option>
            <?php endif; ?>
          </select>
        </div>
        <div class="col-md-2"><label class="form-label fw-semibold small">手动分</label>
          <input type="number" class="form-control" name="manual_score" value="<?php echo $edit_fam['manual_score']; ?>"></div>
        <div class="col-md-2"><label class="form-label fw-semibold small">状态</label>
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="is_deleted" value="1" <?php echo $edit_fam['is_deleted']?'checked':''; ?>>
            <label class="form-check-label">已删除</label>
          </div>
        </div>
      </div>
      <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>保存</button>
        <a href="families.php" class="btn btn-outline-secondary">取消</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-list me-2"></i>家族列表</span>
    <div class="d-flex gap-2">
      <a href="families.php<?php echo $show_deleted?'':'?show_deleted=1'; ?>" class="btn btn-sm btn-outline-secondary">
        <?php echo $show_deleted?'隐藏已删除':'显示已删除'; ?>
      </a>
      <?php if(can('families','add')): ?>
      <a href="families.php?action=add&id=0" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>新增家族</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover datatable align-middle mb-0">
      <thead><tr class="table-active">
        <th class="ps-4">ID</th><th>家族名称</th><th>阶层</th><th>手动分</th><th>状态</th><th>创建时间</th><th class="pe-4">操作</th>
      </tr></thead>
      <tbody>
      <?php foreach($families as $f): ?>
      <tr>
        <td class="ps-4 text-muted small"><?php echo $f['id']; ?></td>
        <td class="fw-semibold"><?php echo htmlspecialchars($f['name']); ?></td>
        <td><?php
          $tier_colors=['平民'=>'secondary','士绅'=>'info','新贵'=>'primary','贵族'=>'warning','权贵'=>'danger'];
          $tc=$tier_colors[$f['tier']]??'secondary';
          echo "<span class='badge bg-$tc'>".htmlspecialchars($f['tier'])."</span>";
        ?></td>
        <td><?php echo $f['manual_score']; ?></td>
        <td><?php echo $f['is_deleted']?"<span class='badge bg-danger'>已删除</span>":"<span class='badge bg-success'>正常</span>"; ?></td>
        <td class="text-muted small"><?php echo htmlspecialchars(substr($f['created_at']??'',0,10)); ?></td>
        <td class="pe-4">
          <?php if(can('families','edit')): ?>
          <a href="families.php?action=edit&id=<?php echo $f['id']; ?>" class="btn btn-sm btn-outline-primary py-0 px-2">编辑</a>
          <?php endif; ?>
          <?php if(can('families','delete') && !$f['is_deleted']): ?>
          <form method="POST" style="display:inline" onsubmit="return confirm('确认删除家族？')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="del_id" value="<?php echo $f['id']; ?>">
            <button class="btn btn-sm btn-outline-danger py-0 px-2">删除</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once 'footer.php'; ?>
