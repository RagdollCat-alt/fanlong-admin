<?php
require_once 'config.php';

if (isset($_SESSION['admin_id'])) { header('Location: index.php'); exit(); }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id  = trim($_POST['user_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($user_id)) {
        $error = '请输入 QQ 号';
    } elseif (empty($password)) {
        $error = '请输入密码';
    } else {
        try {
            $db    = getDB();
            $admin = $db->prepare("SELECT user_id, level, password_hash, permissions, nickname FROM admins WHERE user_id = ?");
            $admin->execute([$user_id]);
            $admin = $admin->fetch();

            if (!$admin) {
                $error = '该 QQ 号不在管理员名单中，请联系超级管理员添加账号';
            } elseif (empty($admin['password_hash'])) {
                // 首次登录：将输入的密码直接设为登录密码
                if (strlen($password) < 6) {
                    $error = '首次登录请设置密码，密码长度不少于 6 位';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $db->prepare("UPDATE admins SET password_hash=?,last_login=datetime('now','localtime') WHERE user_id=?")
                       ->execute([$hash, $admin['user_id']]);
                    $_SESSION['admin_id']       = $admin['user_id'];
                    $_SESSION['admin_level']    = $admin['level'];
                    $_SESSION['admin_nickname'] = $admin['nickname'] ?? $admin['user_id'];
                    $_SESSION['permissions']    = $admin['permissions'] ?? '{}';
                    $_SESSION['password_set']   = true;
                    $_SESSION['login_time']     = time();
                    $db->prepare("INSERT INTO login_logs (user_id, ip, user_agent) VALUES (?,?,?)")
                       ->execute([$admin['user_id'], $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                    logAction('admins', 'login', $admin['user_id']);
                    setFlash('success', '密码设置成功！欢迎首次登录系统。');
                    header('Location: index.php');
                    exit();
                }
            } elseif (password_verify($password, $admin['password_hash'])) {
                $_SESSION['admin_id']       = $admin['user_id'];
                $_SESSION['admin_level']    = $admin['level'];
                $_SESSION['admin_nickname'] = $admin['nickname'] ?? $admin['user_id'];
                $_SESSION['permissions']    = $admin['permissions'] ?? '{}';
                $_SESSION['password_set']   = true;
                $_SESSION['login_time']     = time();
                // 更新最后登录时间
                $db->prepare("UPDATE admins SET last_login=datetime('now','localtime') WHERE user_id=?")
                   ->execute([$admin['user_id']]);
                // 记录登录日志
                $db->prepare("INSERT INTO login_logs (user_id, ip, user_agent) VALUES (?,?,?)")
                   ->execute([$admin['user_id'], $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                logAction('admins', 'login', $admin['user_id']);
                header('Location: index.php');
                exit();
            } else {
                $error = '密码错误';
            }
        } catch (Exception $e) {
            $error = '登录失败：' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo APP_NAME; ?> · 登录</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script>
(function(){
  var t = localStorage.getItem('fl_theme') || 'light';
  document.documentElement.setAttribute('data-bs-theme', t);
})();
</script>
<style>
  body {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    font-family: 'Segoe UI', sans-serif;
  }
  [data-bs-theme="dark"] body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); }
  .login-wrap {
    width: 100%;
    max-width: 440px;
    padding: 16px;
  }
  .login-card {
    border-radius: 20px;
    padding: 44px 40px 36px;
    box-shadow: 0 20px 60px rgba(0,0,0,.25);
    animation: fadeUp .5s ease;
  }
  @keyframes fadeUp { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }
  .brand-icon {
    width: 72px; height: 72px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 18px;
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem; color: #fff;
    margin: 0 auto 20px;
    box-shadow: 0 8px 24px rgba(118,75,162,.4);
  }
  .form-control {
    border-radius: 10px;
    padding: 12px 16px;
    transition: border-color .2s, box-shadow .2s;
  }
  .form-control:focus { box-shadow: 0 0 0 .25rem rgba(102,126,234,.25); border-color: #667eea; }
  .btn-login {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none; border-radius: 10px;
    padding: 13px; font-weight: 600;
    font-size: 1.05rem; color: #fff;
    width: 100%; transition: all .2s;
  }
  .btn-login:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(118,75,162,.4); color:#fff; }
  .theme-toggle {
    position: fixed; top: 20px; right: 20px;
    background: rgba(255,255,255,.2); border: none;
    border-radius: 50%; width: 40px; height: 40px;
    color: #fff; font-size: 1.1rem; cursor: pointer;
    backdrop-filter: blur(8px); transition: all .2s;
  }
  .theme-toggle:hover { background: rgba(255,255,255,.35); transform: scale(1.1); }
</style>
</head>
<body>
<button class="theme-toggle" onclick="toggleTheme()" title="切换主题">
  <i class="fas fa-moon" id="themeIcon"></i>
</button>

<div class="login-wrap">
  <div class="login-card bg-body">
    <div class="brand-icon"><i class="fas fa-dragon"></i></div>
    <h2 class="text-center fw-bold mb-1" style="font-size:1.4rem">繁笼后台管理系统</h2>
    <p class="text-center text-muted mb-4 small">v<?php echo APP_VERSION; ?> · 仅限管理员访问</p>

    <?php if ($error): ?>
    <div class="alert alert-danger rounded-3 border-0 py-2 px-3 mb-3">
      <i class="fas fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="mb-3">
        <label class="form-label fw-semibold small">QQ 号</label>
        <div class="input-group">
          <span class="input-group-text rounded-start-3 border-end-0"><i class="fas fa-user text-muted"></i></span>
          <input type="text" class="form-control border-start-0 ps-2" name="user_id"
                 placeholder="请输入 QQ 号" autofocus required
                 value="<?php echo htmlspecialchars($_POST['user_id'] ?? ''); ?>">
        </div>
      </div>
      <div class="mb-4">
        <label class="form-label fw-semibold small">密码</label>
        <div class="input-group">
          <span class="input-group-text rounded-start-3 border-end-0"><i class="fas fa-lock text-muted"></i></span>
          <input type="password" class="form-control border-start-0 ps-2" name="password"
                 placeholder="请输入密码" required id="pwdInput">
          <button type="button" class="btn btn-outline-secondary border-start-0 rounded-end-3"
                  onclick="togglePwd()"><i class="fas fa-eye" id="pwdEye"></i></button>
        </div>
      </div>
      <button type="submit" class="btn btn-login">
        <i class="fas fa-right-to-bracket me-2"></i>登录后台
      </button>
    </form>

    <p class="text-center text-muted small mt-4 mb-0">
      <i class="fas fa-info-circle me-1"></i>首次登录时，您输入的密码将成为登录密码（至少 6 位）
    </p>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleTheme() {
  var html = document.documentElement;
  var cur = html.getAttribute('data-bs-theme');
  var next = cur === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-bs-theme', next);
  localStorage.setItem('fl_theme', next);
  document.getElementById('themeIcon').className = next === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
}
function togglePwd() {
  var inp = document.getElementById('pwdInput');
  var eye = document.getElementById('pwdEye');
  inp.type = inp.type === 'password' ? 'text' : 'password';
  eye.className = inp.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}
// 初始化主题图标
(function(){
  var t = localStorage.getItem('fl_theme') || 'light';
  document.getElementById('themeIcon').className = t === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
})();
</script>
</body>
</html>
