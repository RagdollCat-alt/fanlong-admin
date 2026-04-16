<?php
require_once 'config.php';

// 必须已有登录会话（哪怕是未完成的首次登录）
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php'); exit();
}

$force   = isset($_GET['force']);   // 首次登录强制设密码
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pwd     = $_POST['new_password']     ?? '';
    $confirm_pwd = $_POST['confirm_password'] ?? '';
    $old_pwd     = $_POST['old_password']     ?? '';

    if (strlen($new_pwd) < 8) {
        $error = '新密码至少 8 位';
    } elseif ($new_pwd !== $confirm_pwd) {
        $error = '两次输入的密码不一致';
    } else {
        try {
            $db    = getDB();
            $admin = $db->prepare("SELECT password_hash FROM admins WHERE user_id=?")->execute([$_SESSION['admin_id']]);
            $row   = $db->prepare("SELECT password_hash FROM admins WHERE user_id=?");
            $row->execute([$_SESSION['admin_id']]);
            $row   = $row->fetch();

            // 非首次（已有密码），需要验证旧密码
            if (!$force && !empty($row['password_hash'])) {
                if (!password_verify($old_pwd, $row['password_hash'])) {
                    $error = '原密码错误';
                }
            }

            if (!$error) {
                $hash = password_hash($new_pwd, PASSWORD_DEFAULT);
                $db->prepare("UPDATE admins SET password_hash=? WHERE user_id=?")
                   ->execute([$hash, $_SESSION['admin_id']]);
                logAction('admins', 'change_password', $_SESSION['admin_id']);
                $_SESSION['password_set'] = true;
                $success = '密码设置成功！';
                if ($force) {
                    header('refresh:2;url=index.php');
                }
            }
        } catch (Exception $e) {
            $error = '操作失败：' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo APP_NAME; ?> · <?php echo $force ? '设置密码' : '修改密码'; ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script>(function(){var t=localStorage.getItem('fl_theme')||'light';document.documentElement.setAttribute('data-bs-theme',t);})();</script>
<style>
  body { min-height:100vh; display:flex; align-items:center; justify-content:center;
         background:linear-gradient(135deg,#667eea,#764ba2); font-family:'Segoe UI',sans-serif; }
  .card { border-radius:20px; padding:40px; box-shadow:0 20px 60px rgba(0,0,0,.25); max-width:440px; width:100%; }
  .form-control { border-radius:10px; padding:12px 16px; }
  .form-control:focus { box-shadow:0 0 0 .25rem rgba(102,126,234,.25); border-color:#667eea; }
  .btn-primary { background:linear-gradient(135deg,#667eea,#764ba2); border:none; border-radius:10px;
                 padding:12px; font-weight:600; width:100%; }
  .btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(118,75,162,.35); }
  .brand-icon { width:60px;height:60px;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:14px;
                display:flex;align-items:center;justify-content:center;font-size:1.6rem;color:#fff;margin:0 auto 16px; }
</style>
</head>
<body>
<div style="width:100%;max-width:440px;padding:16px;">
  <div class="card bg-body">
    <div class="brand-icon"><i class="fas fa-key"></i></div>
    <h3 class="text-center fw-bold mb-1"><?php echo $force ? '首次登录 · 设置密码' : '修改密码'; ?></h3>
    <p class="text-center text-muted small mb-4">
      <?php echo $force ? '为保障安全，请立即为您的账号设置密码' : '管理员 ' . htmlspecialchars($_SESSION['admin_id']); ?>
    </p>

    <?php if ($error): ?>
    <div class="alert alert-danger border-0 rounded-3 py-2 px-3 mb-3">
      <i class="fas fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success border-0 rounded-3 py-2 px-3 mb-3">
      <i class="fas fa-circle-check me-2"></i><?php echo $success; ?>
      <?php if ($force): ?><br><small class="text-muted">2 秒后跳转到仪表盘...</small><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
      <?php if (!$force && !empty($_SESSION['password_set'])): ?>
      <div class="mb-3">
        <label class="form-label fw-semibold small">原密码</label>
        <input type="password" class="form-control" name="old_password" required placeholder="请输入当前密码">
      </div>
      <?php endif; ?>
      <div class="mb-3">
        <label class="form-label fw-semibold small">新密码 <span class="text-muted">(至少 8 位)</span></label>
        <input type="password" class="form-control" name="new_password" required placeholder="请输入新密码" id="np">
      </div>
      <div class="mb-4">
        <label class="form-label fw-semibold small">确认新密码</label>
        <input type="password" class="form-control" name="confirm_password" required placeholder="再次输入新密码" id="cp">
      </div>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-check me-2"></i><?php echo $force ? '设置密码并进入系统' : '保存新密码'; ?>
      </button>
      <?php if (!$force): ?>
      <a href="index.php" class="btn btn-outline-secondary w-100 mt-2 border-0">取消</a>
      <?php endif; ?>
    </form>
    <?php else: ?>
    <?php if (!$force): ?>
    <a href="index.php" class="btn btn-primary"><i class="fas fa-home me-2"></i>返回仪表盘</a>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
