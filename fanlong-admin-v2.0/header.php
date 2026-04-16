<?php
require_once 'config.php';
checkLogin();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="zh-CN" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo APP_NAME; ?> · <?php echo $page_title ?? '管理面板'; ?></title>
<script>(function(){var t=localStorage.getItem('fl_theme')||'light';document.documentElement.setAttribute('data-bs-theme',t);})();</script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
<style>
/* ===== 全局变量 ===== */
:root {
  --sidebar-w: 256px;
  --sidebar-bg: linear-gradient(180deg,#1e2a3a 0%,#0f1924 100%);
  --primary: #667eea;
  --primary-end: #764ba2;
  --header-h: 64px;
}
/* ===== 侧边栏 ===== */
#sidebar {
  position:fixed; top:0; left:0; height:100vh; width:var(--sidebar-w);
  background:var(--sidebar-bg); color:#c8d3e0;
  display:flex; flex-direction:column;
  z-index:1040; transition:width .28s ease;
  box-shadow:3px 0 20px rgba(0,0,0,.18);
  overflow:hidden;
}
#sidebar.collapsed { width:70px; }
.sidebar-brand {
  display:flex; align-items:center; gap:12px;
  padding:20px 20px 18px; border-bottom:1px solid rgba(255,255,255,.07);
  flex-shrink:0;
}
.sidebar-brand .brand-icon {
  width:38px;height:38px;min-width:38px;
  background:linear-gradient(135deg,var(--primary),var(--primary-end));
  border-radius:10px; display:flex; align-items:center; justify-content:center;
  font-size:1.1rem; color:#fff;
}
.sidebar-brand .brand-text { overflow:hidden; transition:opacity .2s; }
.sidebar-brand .brand-text h5 { margin:0;font-size:.95rem;font-weight:700;color:#fff;white-space:nowrap; }
.sidebar-brand .brand-text p { margin:0;font-size:.72rem;color:#8899a6;white-space:nowrap; }
.sidebar-nav { flex:1; overflow-y:auto; padding:12px 0; scrollbar-width:none; }
.sidebar-nav::-webkit-scrollbar { display:none; }
.nav-section {
  padding:16px 20px 6px; font-size:.68rem; font-weight:700;
  text-transform:uppercase; color:#546e7a; letter-spacing:.08em;
  white-space:nowrap; overflow:hidden; transition:opacity .2s;
}
.nav-item a {
  display:flex; align-items:center; gap:12px;
  padding:9px 20px; color:#9eaec0; text-decoration:none;
  border-radius:0; transition:all .2s; white-space:nowrap;
  font-size:.875rem; border-left:3px solid transparent;
}
.nav-item a:hover { background:rgba(255,255,255,.06); color:#e0e8f0; }
.nav-item a.active {
  background:rgba(102,126,234,.18); color:#a5b4fc;
  border-left-color:var(--primary);
}
.nav-item a i { width:20px;text-align:center;font-size:1rem;flex-shrink:0; }
.nav-label { overflow:hidden; transition:opacity .2s; }
.sidebar-footer {
  padding:14px 20px; border-top:1px solid rgba(255,255,255,.06);
  display:flex; align-items:center; gap:10px;
}
.sidebar-footer a { color:#8899a6; text-decoration:none; font-size:.82rem; white-space:nowrap;
                    overflow:hidden; transition:color .2s; }
.sidebar-footer a:hover { color:#e0e8f0; }
/* 折叠状态 */
#sidebar.collapsed .brand-text,
#sidebar.collapsed .nav-section,
#sidebar.collapsed .nav-label { opacity:0; width:0; pointer-events:none; }
#sidebar.collapsed .nav-item a { padding:10px 24px; justify-content:center; }
#sidebar.collapsed .nav-item a i { width:auto; }
#sidebar.collapsed .sidebar-footer a span { display:none; }
/* ===== 主内容区 ===== */
#main-content {
  margin-left:var(--sidebar-w); min-height:100vh;
  transition:margin-left .28s ease;
  background:var(--bs-body-bg);
}
#main-content.expanded { margin-left:70px; }
/* ===== 顶部栏 ===== */
.topbar {
  height:var(--header-h); position:sticky; top:0; z-index:100;
  background:var(--bs-body-bg); border-bottom:1px solid var(--bs-border-color);
  display:flex; align-items:center; justify-content:space-between;
  padding:0 28px; gap:16px;
}
.topbar-left h1 { font-size:1.25rem; font-weight:700; margin:0; }
.topbar-left .breadcrumb { margin:0; font-size:.78rem; }
.topbar-right { display:flex; align-items:center; gap:10px; }
.topbar-btn {
  width:38px;height:38px; border-radius:10px;
  border:1px solid var(--bs-border-color);
  background:transparent; color:var(--bs-body-color);
  display:flex;align-items:center;justify-content:center;
  cursor:pointer; transition:all .18s; font-size:.95rem;
}
.topbar-btn:hover { background:var(--bs-secondary-bg); }
.admin-chip {
  display:flex; align-items:center; gap:8px;
  background:var(--bs-secondary-bg); border-radius:10px; padding:5px 12px 5px 6px;
}
.admin-avatar {
  width:30px;height:30px;border-radius:8px;
  background:linear-gradient(135deg,var(--primary),var(--primary-end));
  color:#fff;display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:700;
}
/* ===== 页面内容 ===== */
.page-body { padding:24px 28px; }
/* ===== 通用卡片 ===== */
.card { border-radius:14px; border:1px solid var(--bs-border-color); box-shadow:none; }
.card-header { border-radius:14px 14px 0 0 !important; font-weight:600; }
/* ===== 实时更新指示器 ===== */
.live-dot {
  width:8px;height:8px;border-radius:50%;
  background:#22c55e;display:inline-block;
  animation:pulse-green 2s infinite;
}
@keyframes pulse-green {
  0%,100%{opacity:1;transform:scale(1)}
  50%{opacity:.5;transform:scale(.8)}
}
/* ===== 响应式 ===== */
@media(max-width:992px){
  #sidebar{width:70px;}
  #main-content{margin-left:70px;}
  #sidebar .brand-text,.nav-section,.nav-label{opacity:0;width:0;pointer-events:none;}
  #sidebar .nav-item a{padding:10px 24px;justify-content:center;}
}
/* ===== 深色模式微调 ===== */
[data-bs-theme="dark"] #sidebar { box-shadow:3px 0 20px rgba(0,0,0,.5); }
[data-bs-theme="dark"] .topbar { box-shadow:0 1px 0 rgba(255,255,255,.05); }
</style>
</head>
<body>

<!-- ===== 侧边栏 ===== -->
<div id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon"><i class="fas fa-dragon"></i></div>
    <div class="brand-text">
      <h5>繁笼后台</h5>
      <p>管理系统 v<?php echo APP_VERSION; ?></p>
    </div>
  </div>

  <nav class="sidebar-nav">
    <?php $cur = basename($_SERVER['PHP_SELF']); ?>

    <!-- 仪表盘 -->
    <ul class="list-unstyled mb-0">
      <li class="nav-item">
        <a href="index.php" class="<?php echo $cur==='index.php'?'active':''; ?>">
          <i class="fas fa-gauge-high"></i><span class="nav-label">仪表盘</span>
        </a>
      </li>
    </ul>

    <!-- 用户数据 -->
    <?php if(can('users')||can('stats')||can('user_bag')||can('user_equip')): ?>
    <div class="nav-section">用户数据</div>
    <ul class="list-unstyled mb-0">
      <?php if(can('users')): ?>
      <li class="nav-item"><a href="users.php" class="<?php echo $cur==='users.php'?'active':''; ?>">
        <i class="fas fa-users"></i><span class="nav-label">用户列表</span></a></li>
      <?php endif; ?>
      <?php if(can('stats')): ?>
      <li class="nav-item"><a href="stats.php" class="<?php echo $cur==='stats.php'?'active':''; ?>">
        <i class="fas fa-chart-bar"></i><span class="nav-label">属性管理</span></a></li>
      <?php endif; ?>
      <?php if(can('user_bag')): ?>
      <li class="nav-item"><a href="user_bag.php" class="<?php echo $cur==='user_bag.php'?'active':''; ?>">
        <i class="fas fa-bag-shopping"></i><span class="nav-label">用户背包</span></a></li>
      <?php endif; ?>
      <?php if(can('user_equip')): ?>
      <li class="nav-item"><a href="equip.php" class="<?php echo $cur==='equip.php'?'active':''; ?>">
        <i class="fas fa-shirt"></i><span class="nav-label">装备穿戴</span></a></li>
      <?php endif; ?>
    </ul>
    <?php endif; ?>

    <!-- 游戏数据 -->
    <?php if(can('items')||can('families')||can('family_rules')||can('fund_types')||can('fund_holdings')||can('item_instances')||can('auction_history')||can('family_logs')): ?>
    <div class="nav-section">游戏数据</div>
    <ul class="list-unstyled mb-0">
      <?php if(can('items')): ?>
      <li class="nav-item"><a href="items.php" class="<?php echo $cur==='items.php'?'active':''; ?>">
        <i class="fas fa-shop"></i><span class="nav-label">商店管理</span></a></li>
      <?php endif; ?>
      <?php if(can('families')): ?>
      <li class="nav-item"><a href="families.php" class="<?php echo $cur==='families.php'?'active':''; ?>">
        <i class="fas fa-flag"></i><span class="nav-label">家族管理</span></a></li>
      <?php endif; ?>
      <?php if(can('family_rules')): ?>
      <li class="nav-item"><a href="family_rules.php" class="<?php echo $cur==='family_rules.php'?'active':''; ?>">
        <i class="fas fa-scale-balanced"></i><span class="nav-label">评分规则</span></a></li>
      <?php endif; ?>
      <?php if(can('drama_archives')): ?>
      <li class="nav-item"><a href="drama_archives.php" class="<?php echo $cur==='drama_archives.php'?'active':''; ?>">
        <i class="fas fa-book-open"></i><span class="nav-label">剧情档案</span></a></li>
      <?php endif; ?>
      <?php if(can('fund_types')): ?>
      <li class="nav-item"><a href="fund_types.php" class="<?php echo $cur==='fund_types.php'?'active':''; ?>">
        <i class="fas fa-chart-line"></i><span class="nav-label">基金产品</span></a></li>
      <?php endif; ?>
      <?php if(can('fund_holdings')): ?>
      <li class="nav-item"><a href="fund_holdings.php" class="<?php echo $cur==='fund_holdings.php'?'active':''; ?>">
        <i class="fas fa-wallet"></i><span class="nav-label">基金持仓</span></a></li>
      <?php endif; ?>
      <?php if(can('history_stats')): ?>
      <li class="nav-item"><a href="history_stats.php" class="<?php echo $cur==='history_stats.php'?'active':''; ?>">
        <i class="fas fa-chart-area"></i><span class="nav-label">历史统计</span></a></li>
      <?php endif; ?>
      <?php if(can('item_instances')): ?>
      <li class="nav-item"><a href="item_instances.php" class="<?php echo $cur==='item_instances.php'?'active':''; ?>">
        <i class="fas fa-boxes-stacked"></i><span class="nav-label">物品实例</span></a></li>
      <?php endif; ?>
      <?php if(can('auction_history')): ?>
      <li class="nav-item"><a href="auction_history.php" class="<?php echo $cur==='auction_history.php'?'active':''; ?>">
        <i class="fas fa-gavel"></i><span class="nav-label">竞拍历史</span></a></li>
      <?php endif; ?>
      <?php if(can('family_logs')): ?>
      <li class="nav-item"><a href="family_logs.php" class="<?php echo $cur==='family_logs.php'?'active':''; ?>">
        <i class="fas fa-scroll"></i><span class="nav-label">家族日志</span></a></li>
      <?php endif; ?>
    </ul>
    <?php endif; ?>

    <!-- 系统配置 -->
    <?php if(can('game_config')||can('game_terms')||can('custom_replies')||can('system_vars')): ?>
    <div class="nav-section">系统配置</div>
    <ul class="list-unstyled mb-0">
      <?php if(can('game_config')): ?>
      <li class="nav-item"><a href="game_config.php" class="<?php echo $cur==='game_config.php'?'active':''; ?>">
        <i class="fas fa-sliders"></i><span class="nav-label">游戏配置</span></a></li>
      <?php endif; ?>
      <?php if(can('game_terms')): ?>
      <li class="nav-item"><a href="terms.php" class="<?php echo $cur==='terms.php'?'active':''; ?>">
        <i class="fas fa-language"></i><span class="nav-label">术语翻译</span></a></li>
      <?php endif; ?>
      <?php if(can('custom_replies')): ?>
      <li class="nav-item"><a href="custom_replies.php" class="<?php echo $cur==='custom_replies.php'?'active':''; ?>">
        <i class="fas fa-comments"></i><span class="nav-label">自定义回复</span></a></li>
      <?php endif; ?>
      <?php if(can('system_vars')): ?>
      <li class="nav-item"><a href="system_vars.php" class="<?php echo $cur==='system_vars.php'?'active':''; ?>">
        <i class="fas fa-terminal"></i><span class="nav-label">系统变量</span></a></li>
      <?php endif; ?>
    </ul>
    <?php endif; ?>

    <!-- 高级工具 -->
    <?php if(can('tables')||can('backup')||can('broadcast','execute')): ?>
    <div class="nav-section">高级工具</div>
    <ul class="list-unstyled mb-0">
      <?php if(can('broadcast','execute')): ?>
      <li class="nav-item"><a href="broadcast.php" class="<?php echo $cur==='broadcast.php'?'active':''; ?>">
        <i class="fas fa-bullhorn"></i><span class="nav-label">批量发送</span></a></li>
      <?php endif; ?>
      <?php if(can('tables')): ?>
      <li class="nav-item"><a href="tables.php" class="<?php echo $cur==='tables.php'?'active':''; ?>">
        <i class="fas fa-database"></i><span class="nav-label">数据表浏览</span></a></li>
      <?php endif; ?>
      <?php if(can('backup','execute')): ?>
      <li class="nav-item"><a href="backup.php" class="<?php echo $cur==='backup.php'?'active':''; ?>">
        <i class="fas fa-floppy-disk"></i><span class="nav-label">数据备份</span></a></li>
      <?php endif; ?>
      <?php if(isSuperAdmin()): ?>
      <li class="nav-item"><a href="cleanup.php" class="<?php echo $cur==='cleanup.php'?'active':''; ?>">
        <i class="fas fa-broom"></i><span class="nav-label">数据清理</span></a></li>
      <?php endif; ?>
    </ul>
    <?php endif; ?>

    <!-- 权限管理 -->
    <?php if(can('admins')||can('logs')): ?>
    <div class="nav-section">权限与日志</div>
    <ul class="list-unstyled mb-0">
      <?php if(can('admins')): ?>
      <li class="nav-item"><a href="admins.php" class="<?php echo $cur==='admins.php'?'active':''; ?>">
        <i class="fas fa-user-shield"></i><span class="nav-label">管理员管理</span></a></li>
      <?php endif; ?>
      <?php if(can('logs')): ?>
      <li class="nav-item"><a href="login_logs.php" class="<?php echo $cur==='login_logs.php'?'active':''; ?>">
        <i class="fas fa-right-to-bracket"></i><span class="nav-label">登录记录</span></a></li>
      <li class="nav-item"><a href="admin_logs.php" class="<?php echo $cur==='admin_logs.php'?'active':''; ?>">
        <i class="fas fa-clipboard-list"></i><span class="nav-label">操作日志</span></a></li>
      <?php endif; ?>
    </ul>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <a href="logout.php" onclick="return confirm('确认退出登录？')">
      <i class="fas fa-right-from-bracket me-2"></i><span>退出登录</span>
    </a>
  </div>
</div>

<!-- ===== 主内容 ===== -->
<div id="main-content">
  <!-- 顶部栏 -->
  <div class="topbar">
    <div class="topbar-left">
      <h1>
        <?php if(isset($page_icon)): ?><i class="<?php echo $page_icon; ?> me-2" style="color:var(--primary)"></i><?php endif; ?>
        <?php echo htmlspecialchars($page_title ?? '管理面板'); ?>
      </h1>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">首页</a></li>
          <?php if(isset($page_subtitle)): ?>
          <li class="breadcrumb-item active"><?php echo htmlspecialchars($page_subtitle); ?></li>
          <?php endif; ?>
        </ol>
      </nav>
    </div>
    <div class="topbar-right">
      <!-- 实时更新指示器 -->
      <span class="d-flex align-items-center gap-2 small text-muted me-2">
        <span class="live-dot"></span>
        <span id="lastUpdate"><?php echo date('H:i:s'); ?></span>
      </span>
      <!-- 主题切换 -->
      <button class="topbar-btn" id="themeToggle" title="切换深色/浅色模式">
        <i class="fas fa-moon" id="themeIcon"></i>
      </button>
      <!-- 修改密码 -->
      <a href="change_password.php" class="topbar-btn" title="修改密码">
        <i class="fas fa-key"></i>
      </a>
      <!-- 管理员信息 -->
      <div class="admin-chip">
        <div class="admin-avatar"><?php echo mb_substr($_SESSION['admin_id'] ?? 'A', 0, 1); ?></div>
        <div class="d-none d-md-block">
          <div class="fw-semibold small lh-1"><?php echo htmlspecialchars($_SESSION['admin_nickname'] ?? $_SESSION['admin_id'] ?? ''); ?></div>
          <div class="text-muted" style="font-size:.7rem;"><?php echo isSuperAdmin() ? '超级管理员' : '管理员'; ?></div>
        </div>
      </div>
      <!-- 折叠侧边栏 -->
      <button class="topbar-btn" id="sidebarToggle" title="折叠侧边栏">
        <i class="fas fa-bars"></i>
      </button>
    </div>
  </div>

  <!-- Flash 消息 -->
  <?php if($flash): ?>
  <div id="flashMsg" class="mx-4 mt-3">
    <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible border-0 rounded-3 shadow-sm fade show py-2 px-3">
      <i class="fas fa-<?php echo $flash['type']==='success'?'circle-check':($flash['type']==='danger'?'circle-exclamation':'info-circle'); ?> me-2"></i>
      <?php echo htmlspecialchars($flash['message']); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  </div>
  <?php endif; ?>

  <!-- 页面内容区 -->
  <div class="page-body">
