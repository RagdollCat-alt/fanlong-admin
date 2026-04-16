<?php
require_once 'config.php';

// 如果已经登录，跳转到首页
if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = safeInput($_POST['user_id'] ?? '');
    $password = $_POST['password'] ?? ''; // 实际系统中应该有密码，这里简化用QQ号登录
    
    if (!empty($user_id)) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT user_id, level FROM admins WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $admin = $stmt->fetch();
            
            if ($admin) {
                // 登录成功 (简化验证，实际应加密码)
                $_SESSION['admin_id'] = $admin['user_id'];
                $_SESSION['admin_level'] = $admin['level'];
                $_SESSION['login_time'] = time();
                
                // 记录登录日志
                $stmt = $db->prepare("INSERT INTO login_logs (user_id, ip, user_agent) VALUES (?, ?, ?)");
                $stmt->execute([
                    $admin['user_id'],
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]);
                
                header('Location: index.php');
                exit();
            } else {
                $error = '该QQ号不是管理员';
            }
        } catch (Exception $e) {
            $error = '登录失败: ' . $e->getMessage();
        }
    } else {
        $error = '请输入QQ号';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>繁笼机器人后台 - 登录</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            padding: 40px;
            width: 100%;
            max-width: 420px;
            animation: fadeIn 0.6s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header i {
            font-size: 3.5rem;
            color: #764ba2;
            margin-bottom: 15px;
        }
        .login-header h1 {
            font-weight: 700;
            color: #333;
            font-size: 1.8rem;
        }
        .login-header p {
            color: #666;
            font-size: 0.95rem;
        }
        .form-floating {
            margin-bottom: 20px;
        }
        .form-control {
            border-radius: 10px;
            padding: 15px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #764ba2;
            box-shadow: 0 0 0 0.25rem rgba(118, 75, 162, 0.25);
        }
        .btn-login {
            background: linear-gradient(to right, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            padding: 15px;
            font-weight: 600;
            font-size: 1.1rem;
            width: 100%;
            color: white;
            transition: all 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(118, 75, 162, 0.3);
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .login-footer {
            text-align: center;
            margin-top: 25px;
            color: #888;
            font-size: 0.9rem;
        }
        .login-footer a {
            color: #764ba2;
            text-decoration: none;
            font-weight: 500;
        }
        .login-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="fas fa-robot"></i>
            <h1>繁笼机器人后台</h1>
            <p>请输入您的管理员QQ号登录</p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-floating">
                <input type="text" class="form-control" id="user_id" name="user_id" 
                       placeholder="请输入QQ号" required autofocus>
                <label for="user_id"><i class="fas fa-user me-2"></i>QQ号</label>
            </div>
            
            <!-- 实际系统中应有密码验证，这里简化 -->
            <div class="form-floating">
                <input type="password" class="form-control" id="password" name="password" 
                       placeholder="请输入密码" value="admin123" readonly>
                <label for="password"><i class="fas fa-lock me-2"></i>密码 (当前版本免密)</label>
            </div>
            
            <button type="submit" class="btn btn-login mt-3">
                <i class="fas fa-sign-in-alt me-2"></i>登录后台
            </button>
        </form>
        
        <div class="login-footer">
            <p>首次登录请使用您的QQ号: <strong>821605970</strong></p>
            <p>© 2026 繁笼帝国 · 仅限管理员访问</p>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 自动聚焦输入框
        document.getElementById('user_id').focus();
        
        // 密码字段只读提示
        document.getElementById('password').addEventListener('click', function() {
            alert('当前版本为简化演示，免密登录。实际使用时请添加密码验证。');
        });
    </script>
</body>
</html>