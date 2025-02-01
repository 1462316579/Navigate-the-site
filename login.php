<?php
require_once 'config/database.php';
session_start();

// 如果已登录则跳转到首页
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    try {
        // 首先检查数据库连接
        if (!$pdo) {
            throw new Exception("数据库连接失败");
        }
        
        $sql = "SELECT id, username, password, status FROM users WHERE username = ?";
        $stmt = $pdo->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("预处理语句创建失败: " . $pdo->errorInfo()[2]);
        }
        
        // 使用 PDO 的 execute 方法（不是 bind_param）
        if (!$stmt->execute([$username])) {
            throw new Exception("执行查询失败: " . $stmt->errorInfo()[2]);
        }
        
        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($user['status'] == 1 && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header('Location: index.php');
                exit;
            }
        }
        
        $error = '用户名或密码错误';
        
    } catch (Exception $e) {
        $error = '登录时发生错误：' . $e->getMessage();
        error_log($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户登录</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-form {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .error-message {
            color: #dc3545;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-form">
            <h2 class="text-center mb-4">用户登录</h2>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">用户名</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">密码</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">登录</button>
                </div>
            </form>
            
            <div class="text-center mt-3">
                还没有账号？<a href="register.php">立即注册</a>
            </div>
        </div>
    </div>
</body>
</html>