<?php
require_once 'config.php';
checkLogin();
requirePermission('family_rules','view');

$db = getDB();
$msg=''; $msg_type='';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pa = $_POST['action'] ?? '';
    if ($pa === 'save') {
        $is_add = empty($_POST['orig_rule_type']);
        requirePermission('family_rules',$is_add?'add':'edit');
        $rule_type = trim($_POST['rule_type'] ?? '');
        $key_name  = trim($_POST['key_name']  ?? '');
        $score     = intval($_POST['score']   ?? 0);
        $desc      = trim($_POST['desc']      ?? '');
        if (empty($rule_type)||empty($key_name)){ $msg='规则类型和键名不能为空'; $msg_type='danger'; }
        else {
            try {
                $old=null;
                if (!$is_add){ $r=$db->prepare("SELECT * FROM family_rules WHERE rule_type=? AND key_name=?"); $r->execute([$_POST['orig_rule_type'],$_POST['orig_key_name']]); $old=$r->fetch(); }
                $db->prepare("INSERT OR REPLACE INTO family_rules (rule_type,key_name,score,`desc`) VALUES (?,?,?,?)")->execute([$rule_type,$key_name,$score,$desc]);
                logAction('family_rules',$is_add?'create':'update',"$rule_type/$key_name",$old,['score'=>$score]);
                setFlash('success','规则已保存'); header('Location: family_rules.php'); exit();
            } catch(Exception $e){ $msg='保存失败：'.$e->getMessage(); $msg_type='danger'; }
        }
    }
    if ($pa === 'delete') {
        requirePermission('family_rules','delete');
        $old=$db->prepare("SELECT * FROM family_rules WHERE rule_type=? AND key_name=?"); $old->execute([$_POST['del_type'],$_POST['del_key']]); $old=$old->fetch();
        $db->prepare("DELETE FROM family_rules WHERE rule_type=? AND key_name=?")->execute([$_POST['del_type'],$_POST['del_key']]);
        logAction('family_rules','delete',$_POST['del_type'].'/'.$_POST['del_key'],$old);
        setFlash('success','规则已删除'); header('Location: family_rules.php'); exit();
    }
}

$rules = $db->query("SELECT * FROM family_rules ORDER BY rule_type, score DESC")->fetchAll();
$rule_types_list = array_unique(array_column($rules,'rule_type'));

$edit_rule = null;
if (!empty($_GET['type']) && !empty($_GET['key'])) {
    foreach($rules as $r){ if($r['rule_type']===$_GET['type'] && $r['key_name']===$_GET['key']){ $edit_rule=$r; break; } }
}

$page_title='评分规则'; $page_icon='fas fa-scale-balanced'; $page_subtitle='家族评分规则';
require_once 'header.php';
?>

<?php if($msg): ?>
<div class="alert alert-<?php echo $msg_type; ?> border-0 rounded-3 alert-dismissible"><?php echo htmlspecialchars($msg); ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i>评分规则列表（<?php echo count($rules); ?> 条）</span>
        <?php if(can('family_rules','add')): ?>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#ruleModal" onclick="clearForm()">
          <i class="fas fa-plus me-1"></i>新增规则
        </button>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <?php $cur_type=''; ?>
        <?php foreach($rules as $r): ?>
        <?php if($r['rule_type']!==$cur_type): $cur_type=$r['rule_type']; ?>
        <div class="px-4 pt-3 pb-1">
          <h6 class="fw-bold text-primary mb-0"><?php echo htmlspecialchars($r['rule_type']); ?></h6>
        </div>
        <?php endif; ?>
        <div class="d-flex align-items-center justify-content-between px-4 py-2 border-bottom">
          <div>
            <span class="fw-semibold"><?php echo htmlspecialchars($r['key_name']); ?></span>
            <?php if($r['desc']): ?><span class="text-muted small ms-2"><?php echo htmlspecialchars($r['desc']); ?></span><?php endif; ?>
          </div>
          <div class="d-flex align-items-center gap-3">
            <span class="badge <?php echo $r['score']>=0?'bg-success':'bg-danger'; ?> rounded-pill px-3">
              <?php echo ($r['score']>=0?'+':'').intval($r['score']); ?> 分
            </span>
            <?php if(can('family_rules','edit')): ?>
            <button class="btn btn-xs btn-sm btn-outline-primary py-0 px-2"
                    onclick="editRule('<?php echo htmlspecialchars($r['rule_type'],ENT_QUOTES); ?>','<?php echo htmlspecialchars($r['key_name'],ENT_QUOTES); ?>',<?php echo $r['score']; ?>,'<?php echo htmlspecialchars($r['desc']??'',ENT_QUOTES); ?>')">
              编辑
            </button>
            <?php endif; ?>
            <?php if(can('family_rules','delete')): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('确认删除？')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="del_type" value="<?php echo htmlspecialchars($r['rule_type']); ?>">
              <input type="hidden" name="del_key"  value="<?php echo htmlspecialchars($r['key_name']); ?>">
              <button class="btn btn-xs btn-sm btn-outline-danger py-0 px-2">删除</button>
            </form>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($rules)): ?><div class="text-center py-5 text-muted">暂无评分规则</div><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="fas fa-circle-info me-2"></i>规则说明</div>
      <div class="card-body small text-muted">
        <p class="mb-2"><i class="fas fa-circle-info me-1 text-primary"></i><strong>家族最终得分 = 基础分 + 各规则匹配分 + 手动加分</strong></p>
        <hr class="my-2">
        <p class="mb-1"><strong>规则类型</strong>：规则的分组名称</p>
        <p class="mb-1 ps-2 text-muted">例：官职、贡献次数、惩罚</p>
        <p class="mb-1"><strong>键名</strong>：具体的匹配条件</p>
        <p class="mb-1 ps-2 text-muted">例：正四品、献礼10次</p>
        <p class="mb-1"><strong>分值</strong>：正数加分，负数扣分</p>
        <hr class="my-2">
        <p class="mb-0 text-info"><i class="fas fa-lightbulb me-1"></i>机器人根据家族成员的官职、贡献等自动计算总分</p>
      </div>
    </div>
  </div>
</div>

<!-- 新增/编辑弹窗 -->
<div class="modal fade" id="ruleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content rounded-4">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold" id="ruleModalTitle">新增评分规则</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="orig_rule_type" id="origRuleType">
        <input type="hidden" name="orig_key_name"  id="origKeyName">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold small">规则类型 <span class="text-muted">(如：官职、贡献、惩罚)</span></label>
            <input type="text" class="form-control" name="rule_type" id="ruleType" required list="ruleTypeList" placeholder="输入或选择已有类型">
            <datalist id="ruleTypeList"><?php foreach($rule_types_list as $rt): ?><option value="<?php echo htmlspecialchars($rt); ?>"><?php endforeach; ?></datalist>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">具体条件 <span class="text-muted">(如：正四品、献礼10次)</span></label>
            <input type="text" class="form-control" name="key_name" id="keyName" required placeholder="输入具体匹配条件">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">加减分值 <span class="text-muted">(正数加分，负数扣分)</span></label>
            <input type="number" class="form-control" name="score" id="ruleScore" value="0">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">备注说明 <span class="text-muted">(选填)</span></label>
            <input type="text" class="form-control" name="desc" id="ruleDesc" placeholder="对该规则的简短说明">
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
          <button type="submit" class="btn btn-primary px-4">保存</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function clearForm(){
  ['origRuleType','origKeyName','ruleType','keyName','ruleDesc'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('ruleScore').value=0;
  document.getElementById('ruleModalTitle').textContent='新增评分规则';
}
function editRule(type,key,score,desc){
  document.getElementById('origRuleType').value = type;
  document.getElementById('origKeyName').value  = key;
  document.getElementById('ruleType').value     = type;
  document.getElementById('keyName').value      = key;
  document.getElementById('ruleScore').value    = score;
  document.getElementById('ruleDesc').value     = desc;
  document.getElementById('ruleModalTitle').textContent = '编辑评分规则';
  new bootstrap.Modal(document.getElementById('ruleModal')).show();
}
</script>

<?php require_once 'footer.php'; ?>
