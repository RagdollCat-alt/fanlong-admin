<?php
require_once 'config.php';
checkLogin();
$db    = getDB();
$today = date('Y-m-d');

// 统计数据
$stat = [];
$stat['users']       = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stat['items']       = $db->query("SELECT COUNT(*) FROM items")->fetchColumn();
$s = $db->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at)=?");
$s->execute([$today]); $stat['today_users'] = $s->fetchColumn();
$stat['admins']      = $db->query("SELECT COUNT(*) FROM admins")->fetchColumn();
try { $s=$db->query("SELECT COUNT(*) FROM families WHERE is_deleted=0"); $stat['families']=$s->fetchColumn(); } catch(Exception $e){ $stat['families']=0; }
try { $s=$db->query("SELECT COUNT(*) FROM fund_types WHERE is_active=1"); $stat['fund_types']=$s->fetchColumn(); } catch(Exception $e){ $stat['fund_types']=0; }
try { $s=$db->query("SELECT COUNT(*) FROM fund_holdings"); $stat['holdings']=$s->fetchColumn(); } catch(Exception $e){ $stat['holdings']=0; }

// 最近10个用户
$recent_users = $db->query("SELECT id, name, created_at FROM users ORDER BY created_at DESC LIMIT 10")->fetchAll();

// 颜值TOP5（使用术语翻译）
$top_face = $db->query("SELECT u.name, u.id, s.stat_face FROM users u JOIN user_stats s ON u.id=s.user_id ORDER BY s.stat_face DESC LIMIT 5")->fetchAll();

// 低库存物品（在售且有限库存）
$low_stock = $db->query("SELECT name, stock_qty, price, currency FROM items WHERE is_selling=1 AND stock_qty != -1 ORDER BY stock_qty ASC LIMIT 8")->fetchAll();

// 最近操作日志
try {
    $recent_logs = $db->query("SELECT admin_id, module, action, target_id, created_at FROM admin_logs ORDER BY id DESC LIMIT 8")->fetchAll();
} catch(Exception $e) { $recent_logs = []; }

$page_title    = '仪表盘';
$page_icon     = 'fas fa-gauge-high';
$page_subtitle = '系统概览';
require_once 'header.php';
?>

<!-- 统计卡片 -->
<div class="row g-3 mb-4">
<?php
$cards = [
  ['id'=>'stat-users',    'icon'=>'fas fa-users',       'color'=>'#667eea', 'val'=>$stat['users'],       'label'=>'总用户数',    'link'=>'users.php'],
  ['id'=>'stat-items',    'icon'=>'fas fa-shop',         'color'=>'#22c55e', 'val'=>$stat['items'],       'label'=>'物品数量',    'link'=>'items.php'],
  ['id'=>'stat-today',    'icon'=>'fas fa-user-plus',    'color'=>'#f59e0b', 'val'=>$stat['today_users'], 'label'=>'今日新增',    'link'=>'users.php'],
  ['id'=>'stat-admins',   'icon'=>'fas fa-user-shield',  'color'=>'#ec4899', 'val'=>$stat['admins'],      'label'=>'管理员',      'link'=>'admins.php'],
  ['id'=>'stat-families', 'icon'=>'fas fa-flag',         'color'=>'#14b8a6', 'val'=>$stat['families'],    'label'=>'活跃家族',    'link'=>'families.php'],
  ['id'=>'stat-fund',     'icon'=>'fas fa-chart-line',   'color'=>'#8b5cf6', 'val'=>$stat['fund_types'],  'label'=>'活跃基金',    'link'=>'fund_types.php'],
  ['id'=>'stat-holdings', 'icon'=>'fas fa-wallet',       'color'=>'#0ea5e9', 'val'=>$stat['holdings'],    'label'=>'基金持仓',    'link'=>'fund_holdings.php'],
];
foreach($cards as $c): ?>
<div class="col-xl-3 col-sm-6">
  <a href="<?php echo $c['link']; ?>" class="text-decoration-none">
    <div class="card h-100" style="transition:transform .2s,box-shadow .2s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 12px 30px rgba(0,0,0,.12)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
      <div class="card-body d-flex align-items-center gap-3 p-4">
        <div style="width:52px;height:52px;border-radius:14px;background:<?php echo $c['color']; ?>22;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="<?php echo $c['icon']; ?>" style="font-size:1.4rem;color:<?php echo $c['color']; ?>"></i>
        </div>
        <div>
          <div class="fw-bold fs-4 lh-1 mb-1" id="<?php echo $c['id']; ?>"><?php echo $c['val']; ?></div>
          <div class="text-muted small"><?php echo $c['label']; ?></div>
        </div>
      </div>
    </div>
  </a>
</div>
<?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
  <!-- 最近注册用户 -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-user-clock me-2 text-primary"></i>最近注册用户</span>
        <a href="users.php" class="btn btn-sm btn-outline-primary">全部</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead><tr class="table-active">
              <th class="ps-4">用户 ID</th><th>昵称</th><th>注册时间</th><th class="pe-4">操作</th>
            </tr></thead>
            <tbody>
            <?php foreach($recent_users as $u): ?>
            <tr>
              <td class="ps-4"><code><?php echo htmlspecialchars($u['id']); ?></code></td>
              <td><?php echo htmlspecialchars($u['name'] ?? '—'); ?></td>
              <td class="text-muted small"><?php echo htmlspecialchars($u['created_at'] ?? ''); ?></td>
              <td class="pe-4">
                <a href="users.php?action=view&id=<?php echo urlencode($u['id']); ?>" class="btn btn-xs btn-outline-primary btn-sm py-0 px-2">查看</a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($recent_users)): ?>
            <tr><td colspan="4" class="text-center py-4 text-muted">暂无用户数据</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- 颜值排行 -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-crown me-2 text-warning"></i><?php echo t('stat_face','颜值'); ?> 排行 TOP5</span>
        <a href="stats.php" class="btn btn-sm btn-outline-warning">全部</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead><tr class="table-active">
              <th class="ps-4 w-25">排名</th><th>角色名</th><th class="pe-4"><?php echo t('stat_face','颜值'); ?></th>
            </tr></thead>
            <tbody>
            <?php $rank=1; foreach($top_face as $p): ?>
            <tr>
              <td class="ps-4">
                <?php
                  $medals=['1'=>'🥇','2'=>'🥈','3'=>'🥉'];
                  echo isset($medals[$rank])?"<span class='fs-5'>{$medals[$rank]}</span>":"<span class='badge bg-secondary'>#{$rank}</span>";
                ?>
              </td>
              <td><?php echo htmlspecialchars($p['name'] ?? '—'); ?></td>
              <td class="pe-4"><span class="badge rounded-pill" style="background:#667eea"><?php echo $p['stat_face']; ?></span></td>
            </tr>
            <?php $rank++; endforeach; ?>
            <?php if(empty($top_face)): ?>
            <tr><td colspan="3" class="text-center py-4 text-muted">暂无数据</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- 低库存物品 -->
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-triangle-exclamation me-2 text-warning"></i>库存提醒</span>
        <a href="items.php" class="btn btn-sm btn-outline-warning">管理物品</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead><tr class="table-active">
              <th class="ps-4">物品名称</th><th>价格</th><th>库存</th><th class="pe-4">状态</th>
            </tr></thead>
            <tbody>
            <?php foreach($low_stock as $item): ?>
            <tr>
              <td class="ps-4 fw-semibold"><?php echo htmlspecialchars($item['name']); ?></td>
              <td><?php echo $item['price']; ?> <?php echo htmlspecialchars($item['currency'] ?? '虞元'); ?></td>
              <td>
                <?php if($item['stock_qty']==0): ?>
                <span class="badge bg-danger">缺货</span>
                <?php elseif($item['stock_qty']<=5): ?>
                <span class="badge bg-warning text-dark"><?php echo $item['stock_qty']; ?></span>
                <?php else: ?>
                <span class="badge bg-info text-dark"><?php echo $item['stock_qty']; ?></span>
                <?php endif; ?>
              </td>
              <td class="pe-4">
                <?php if($item['stock_qty']==0): ?>
                <span class="badge bg-danger">断货</span>
                <?php elseif($item['stock_qty']<=5): ?>
                <span class="badge bg-warning text-dark">紧张</span>
                <?php else: ?>
                <span class="badge bg-secondary">偏低</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($low_stock)): ?>
            <tr><td colspan="4" class="text-center py-4 text-muted">库存充足，无需补货</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- 最近操作日志 -->
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-clipboard-list me-2 text-info"></i>最近操作</span>
        <?php if(can('logs')): ?>
        <a href="admin_logs.php" class="btn btn-sm btn-outline-info">全部</a>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <?php if(empty($recent_logs)): ?>
        <div class="text-center py-5 text-muted"><i class="fas fa-clock-rotate-left fa-2x mb-2 d-block opacity-25"></i>暂无操作记录</div>
        <?php else: ?>
        <ul class="list-group list-group-flush">
        <?php
        $aColors=['login'=>'success','logout'=>'secondary','create'=>'primary','update'=>'warning','delete'=>'danger','execute'=>'info'];
        $aIcons=['login'=>'right-to-bracket','logout'=>'right-from-bracket','create'=>'plus','update'=>'pen','delete'=>'trash','execute'=>'play'];
        foreach($recent_logs as $log):
          $a=strtolower($log['action']);
          $color=$aColors[$a]??'secondary';
          $icon=$aIcons[$a]??'circle';
        ?>
        <li class="list-group-item border-0 py-2 px-3">
          <div class="d-flex gap-2 align-items-start">
            <span class="badge bg-<?php echo $color; ?> mt-1"><i class="fas fa-<?php echo $icon; ?>"></i></span>
            <div class="flex-grow-1 overflow-hidden">
              <div class="small fw-semibold text-truncate">
                <?php echo htmlspecialchars($log['admin_id']); ?>
                <span class="fw-normal text-muted">· <?php echo htmlspecialchars($log['module']); ?></span>
              </div>
              <div class="text-muted" style="font-size:.72rem;"><?php echo htmlspecialchars($log['created_at']); ?></div>
            </div>
          </div>
        </li>
        <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- 快速操作 -->
<div class="card mt-4">
  <div class="card-header"><i class="fas fa-bolt me-2 text-warning"></i>快速操作</div>
  <div class="card-body">
    <div class="row g-3">
      <?php
      $quick=[
        ['url'=>'users.php?action=add',     'icon'=>'fas fa-user-plus',    'label'=>'新增用户',    'color'=>'primary'],
        ['url'=>'items.php?action=add',      'icon'=>'fas fa-plus-circle',  'label'=>'新增物品',    'color'=>'success'],
        ['url'=>'game_config.php',           'icon'=>'fas fa-sliders',      'label'=>'游戏配置',    'color'=>'info'],
        ['url'=>'terms.php',                 'icon'=>'fas fa-language',     'label'=>'术语翻译',    'color'=>'secondary'],
        ['url'=>'backup.php',                'icon'=>'fas fa-floppy-disk',  'label'=>'备份数据',    'color'=>'warning'],
        ['url'=>'admin_logs.php',            'icon'=>'fas fa-list-check',   'label'=>'操作日志',    'color'=>'danger'],
      ];
      foreach($quick as $q): ?>
      <div class="col-6 col-md-4 col-lg-2">
        <a href="<?php echo $q['url']; ?>" class="btn btn-outline-<?php echo $q['color']; ?> w-100 py-3 d-flex flex-column align-items-center gap-2">
          <i class="<?php echo $q['icon']; ?> fs-4"></i>
          <span class="small"><?php echo $q['label']; ?></span>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php require_once 'footer.php'; ?>
