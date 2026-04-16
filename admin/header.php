<?php
require_once 'config.php';
checkLogin();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>繁笼机器人后台 - <?php echo $page_title ?? '管理面板'; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --sidebar-width: 250px;
            --header-height: 60px;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fb;
            color: #333;
            overflow-x: hidden;
        }
        #sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
            color: #ecf0f1;
            padding-top: 20px;
            z-index: 1000;
            box-shadow: 3px 0 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }
        #sidebar.collapsed {
            width: 70px;
        }
        #sidebar.collapsed .sidebar-text {
            display: none;
        }
        .sidebar-header {
            text-align: center;
            padding: 0 15px 30px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .sidebar-header h3 {
            font-weight: 700;
            font-size: 1.4rem;
            color: white;
            margin: 0;
        }
        .sidebar-header p {
            font-size: 0.85rem;
            color: #bdc3c7;
            margin: 5px 0 0 0;
        }
        .nav-link {
            color: #bdc3c7;
            padding: 12px 20px;
            margin: 5px 15px;
            border-radius: 10px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
        }
        .nav-link i {
            width: 24px;
            font-size: 1.2rem;
            margin-right: 12px;
        }
        .sidebar-footer {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            padding: 0 20px;
            text-align: center;
            color: #95a5a6;
            font-size: 0.8rem;
        }
        #content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: all 0.3s;
        }
        #content.expanded {
            margin-left: 70px;
        }
        .topbar {
            background: white;
            border-radius: 15px;
            padding: 15px 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-title {
            font-weight: 700;
            color: #2c3e50;
            margin: 0;
            font-size: 1.5rem;
        }
        .page-title i {
            color: var(--primary-color);
            margin-right: 10px;
        }
        .user-info {
            display: flex;
            align-items: center;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card-header {
            background: white;
            border-bottom: 1px solid #eee;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
            font-weight: 700;
            color: #2c3e50;
        }
        .card-body {
            padding: 25px;
        }
        .btn-primary {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .stat-card {
            text-align: center;
            padding: 25px;
        }
        .stat-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #7f8c8d;
            font-size: 0.95rem;
        }
        .table {
            border-radius: 10px;
            overflow: hidden;
        }
        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 700;
            color: #495057;
            padding: 15px;
        }
        .table td {
            padding: 15px;
            vertical-align: middle;
        }
        .badge-admin {
            background: linear-gradient(to right, #ff7e5f, #feb47b);
            color: white;
        }
        .badge-user {
            background: linear-gradient(to right, #36d1dc, #5b86e5);
            color: white;
        }
        @media (max-width: 992px) {
            #sidebar {
                width: 70px;
            }
            #sidebar .sidebar-text {
                display: none;
            }
            #content {
                margin-left: 70px;
            }
        }
    </style>
</head>
<body>
    <!-- 侧边栏 -->
    <div id="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-robot"></i> <span class="sidebar-text">繁笼后台</span></h3>
            <p class="sidebar-text">B端管理面板</p>
        </div>
        
        <nav class="mt-4">
            <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span class="sidebar-text">仪表盘</span>
            </a>
            
            <div class="sidebar-text mt-4 mb-2 ps-4" style="color: #95a5a6; font-size: 0.8rem; text-transform: uppercase;">用户管理</div>
            <a href="users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span class="sidebar-text">用户列表</span>
            </a>
            <a href="stats.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'stats.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span class="sidebar-text">属性管理</span>
            </a>
            
            <div class="sidebar-text mt-4 mb-2 ps-4" style="color: #95a5a6; font-size: 0.8rem; text-transform: uppercase;">游戏数据</div>
            <a href="items.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'items.php' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-bag"></i>
                <span class="sidebar-text">物品管理</span>
            </a>
            <a href="equip.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'equip.php' ? 'active' : ''; ?>">
                <i class="fas fa-tshirt"></i>
                <span class="sidebar-text">装备管理</span>
            </a>
            <a href="game_config.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'game_config.php' ? 'active' : ''; ?>">
                <i class="fas fa-cogs"></i>
                <span class="sidebar-text">系统配置</span>
            </a>
            
            <div class="sidebar-text mt-4 mb-2 ps-4" style="color: #95a5a6; font-size: 0.8rem; text-transform: uppercase;">高级功能</div>
            <a href="tables.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'tables.php' ? 'active' : ''; ?>">
                <i class="fas fa-database"></i>
                <span class="sidebar-text">所有数据表</span>
            </a>
            <a href="backup.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'backup.php' ? 'active' : ''; ?>">
                <i class="fas fa-download"></i>
                <span class="sidebar-text">数据备份</span>
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <p class="sidebar-text">版本 1.0.0</p>
            <p><a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> <span class="sidebar-text">安全退出</span></a></p>
        </div>
    </div>

    <!-- 主要内容区 -->
    <div id="content">
        <!-- 顶部栏 -->
        <div class="topbar">
            <div>
                <h1 class="page-title">
                    <?php if (isset($page_icon)): ?>
                    <i class="<?php echo $page_icon; ?>"></i>
                    <?php endif; ?>
                    <?php echo $page_title ?? '管理面板'; ?>
                </h1>
                <nav aria-label="breadcrumb" style="margin-top: 5px;">
                    <ol class="breadcrumb" style="margin-bottom: 0;">
                        <li class="breadcrumb-item"><a href="index.php"><i class="fas fa-home"></i> 首页</a></li>
                        <?php if (isset($page_subtitle)): ?>
                        <li class="breadcrumb-item active"><?php echo $page_subtitle; ?></li>
                        <?php endif; ?>
                    </ol>
                </nav>
            </div>
            
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo substr($_SESSION['admin_id'] ?? 'A', 0, 1); ?>
                </div>
                <div>
                    <div style="font-weight: 600;">管理员 <?php echo $_SESSION['admin_id'] ?? ''; ?></div>
                    <div style="font-size: 0.85rem; color: #7f8c8d;">
                        <?php echo isSuperAdmin() ? '超级管理员' : '普通管理员'; ?>
                        · <?php echo date('Y-m-d H:i:s'); ?>
                    </div>
                </div>
                <button class="btn btn-outline-secondary btn-sm ms-3" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>

        <!-- 页面内容开始 -->