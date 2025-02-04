<?php
// 直接包含数据库连接文件
require_once '../config/database.php';
session_start();

// 简单的登录验证
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$conn = $pdo;

// 获取统计数据
$result = $conn->query("SELECT COUNT(*) as count FROM categories");
$row = $result->fetch(PDO::FETCH_ASSOC);
$categoryCount = $row['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM links");
$row = $result->fetch(PDO::FETCH_ASSOC);
$linkCount = $row['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM links WHERE screenshots IS NOT NULL AND screenshots != ''");
$row = $result->fetch(PDO::FETCH_ASSOC);
$screenshotCount = $row['count'];

// 获取每个分类的链接数量统计
$result = $conn->query("SELECT c.name, COUNT(l.id) as count 
                FROM categories c 
                LEFT JOIN links l ON c.id = l.category_id 
                GROUP BY c.id 
                ORDER BY count DESC");
$categoryStats = $result->fetchAll(PDO::FETCH_ASSOC);

// 获取最近7天的链接添加趋势
$result = $conn->query("SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM links 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                GROUP BY DATE(created_at) 
                ORDER BY date DESC");
$linkTrend = $result->fetchAll(PDO::FETCH_ASSOC);

// 处理分类添加
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add_category') {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $sort_order = (int)$_POST['sort_order'];
        
        if (!empty($name)) {
            $stmt = $conn->prepare("INSERT INTO categories (name, description, sort_order) VALUES (?, ?, ?)");
            $stmt->execute([$name, $description, $sort_order]);
        }
    }
    // 处理分类更新
    else if ($_POST['action'] == 'edit_category') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $sort_order = (int)$_POST['sort_order'];
        
        if (!empty($name)) {
            $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$name, $description, $sort_order, $id]);
        }
    }

    // 处理链接添加
    if ($_POST['action'] == 'add_link') {
        $category_id = (int)$_POST['category_id'];
        $title = trim($_POST['title']);
        $url = trim($_POST['url']);
        $icon = trim($_POST['icon']);
        $description = trim($_POST['description']);
        $screenshots = trim($_POST['screenshots']);
        
        if (!empty($title) && !empty($url)) {
            $stmt = $conn->prepare("INSERT INTO links (category_id, title, url, icon, description, screenshots) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$category_id, $title, $url, $icon, $description, $screenshots]);
        }
    }
    // 处理链接更新
    else if ($_POST['action'] == 'edit_link') {
        $id = (int)$_POST['id'];
        $category_id = (int)$_POST['category_id'];
        $title = trim($_POST['title']);
        $url = trim($_POST['url']);
        $icon = trim($_POST['icon']);
        $description = trim($_POST['description']);
        $screenshots = trim($_POST['screenshots']);
        
        if (!empty($title) && !empty($url)) {
            $stmt = $conn->prepare("UPDATE links SET category_id = ?, title = ?, url = ?, icon = ?, description = ?, screenshots = ? WHERE id = ?");
            $stmt->execute([$category_id, $title, $url, $icon, $description, $screenshots, $id]);
        }
    }

    // 添加删除图片的处理
    if (isset($_POST['delete_screenshot'])) {
        $id = (int)$_POST['link_id'];
        $screenshot = $_POST['screenshot'];
        
        // 获取当前图片列表
        $stmt = $conn->prepare("SELECT screenshots FROM links WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $screenshots = explode(',', $result['screenshots']);
            $screenshots = array_filter($screenshots, function($img) use ($screenshot) {
                return $img !== $screenshot;
            });
            
            // 更新数据库
            $newScreenshots = implode(',', $screenshots);
            $stmt = $conn->prepare("UPDATE links SET screenshots = ? WHERE id = ?");
            $stmt->execute([$newScreenshots, $id]);
            
            // 删除文件
            $filepath = $_SERVER['DOCUMENT_ROOT'] . $screenshot;
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
}

// 处理分类删除
if (isset($_GET['delete_category'])) {
    $id = (int)$_GET['delete_category'];
    // 检查分类下是否有链接
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM links WHERE category_id = ?");
    $stmt->execute([$id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $result['count'];
    
    if ($count == 0) {
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
    }
}

// 处理链接删除
if (isset($_GET['delete_link'])) {
    $id = (int)$_GET['delete_link'];
    $stmt = $conn->prepare("DELETE FROM links WHERE id = ?");
    $stmt->execute([$id]);
}

// 获取所有分类
$result = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM links WHERE category_id = c.id) as link_count 
                FROM categories c ORDER BY sort_order ASC, id ASC");
$categories = $result->fetchAll(PDO::FETCH_ASSOC);

// 处理添加用户请求
if (isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    try {
        $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$username, $email, $password]);
        $message = "用户添加成功！";
    } catch (Exception $e) {
        $error = "添加用户失败，可能用户名或邮箱已存在";
    }
}

// 处理删除用户请求
if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    try {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $message = "用户删除成功！";
    } catch (Exception $e) {
        $error = "删除用户失败";
    }
}

// 添加SMTP配置处理代码
if(isset($_POST['update_smtp'])) {
    $smtp_host = trim($_POST['smtp_host']);
    $smtp_port = (int)$_POST['smtp_port'];
    $smtp_user = trim($_POST['smtp_user']);
    $smtp_pass = trim($_POST['smtp_pass']);
    $smtp_from = trim($_POST['smtp_from']);
    $smtp_from_name = trim($_POST['smtp_from_name'] ?? '');  // 添加发件人名称
    
    // 验证必填字段
    if(empty($smtp_host) || empty($smtp_port) || empty($smtp_user) || empty($smtp_pass) || empty($smtp_from)) {
        echo "<script>alert('所有字段都必须填写！');</script>";
    } else {
        // 检查是否已存在配置
        $check_smtp = $conn->query("SELECT id FROM smtp_config LIMIT 1");
        $exists = $check_smtp->fetch(PDO::FETCH_ASSOC);
        
        try {
            if($exists) {
                // 更新现有配置
                $stmt = $conn->prepare("UPDATE smtp_config SET 
                        smtp_host = ?,
                        smtp_port = ?,
                        smtp_user = ?,
                        smtp_pass = ?,
                        smtp_from = ?,
                        smtp_from_name = ?");
                $result = $stmt->execute([$smtp_host, $smtp_port, $smtp_user, $smtp_pass, $smtp_from, $smtp_from_name]);
            } else {
                // 插入新配置
                $stmt = $conn->prepare("INSERT INTO smtp_config 
                        (smtp_host, smtp_port, smtp_user, smtp_pass, smtp_from, smtp_from_name) 
                        VALUES (?, ?, ?, ?, ?, ?)");
                $result = $stmt->execute([$smtp_host, $smtp_port, $smtp_user, $smtp_pass, $smtp_from, $smtp_from_name]);
            }
            
            if($result) {
                echo "<script>alert('SMTP配置已保存');</script>";
            } else {
                echo "<script>alert('保存失败');</script>";
            }
        } catch (PDOException $e) {
            echo "<script>alert('数据库错误：" . addslashes($e->getMessage()) . "');</script>";
        }
    }
}

// 在PHP处理部分添加测试邮件发送功能
if(isset($_POST['test_smtp'])) {
    require_once '../includes/Mailer.php';
    
    $test_email = $_POST['test_email'];
    
    // 重新获取最新的SMTP配置
    $smtp_config = $conn->query("SELECT * FROM smtp_config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    
    // 检查是否已配置SMTP
    if (!$smtp_config || empty($smtp_config['smtp_from'])) {
        echo "<script>alert('请先完成SMTP配置！');</script>";
    } else {
        try {
            // 验证发件人邮箱格式
            if (!filter_var($smtp_config['smtp_from'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("发件人邮箱格式无效");
            }
            
            // 验证测试邮箱格式
            if (!filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("测试邮箱格式无效");
            }
            
            // 开启调试模式，查看详细错误信息
            $smtp_config['debug'] = true;
            
            $mailer = new Mailer($smtp_config);
            $result = $mailer->sendTest($test_email);
            
            if($result['success']) {
                echo "<script>alert('测试邮件发送成功！请检查收件箱。');</script>";
            } else {
                echo "<script>alert('发送失败：" . addslashes($result['message']) . "');</script>";
            }
        } catch (Exception $e) {
            echo "<script>alert('系统错误：" . addslashes($e->getMessage()) . "');</script>";
        }
    }
}

// 获取现有SMTP配置（用于显示表单）
$smtp_config = $conn->query("SELECT * FROM smtp_config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台 - 导航网站</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4338ca;
            --secondary-color: #6366f1;
            --success-color: #059669;
            --danger-color: #dc2626;
            --warning-color: #d97706;
            --info-color: #0891b2;
            --sidebar-width: 250px;
        }
        
        body {
            background-color: #f3f4f6;
            min-height: 100vh;
        }
        
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            background: #1e1e2d;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .sidebar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header h3 {
            margin: 0;
            color: white;
            font-size: 1.25rem;
        }
        
        .home-link {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .home-link:hover {
            color: white;
            transform: translateY(-2px);
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .menu-item {
            padding: 0.75rem 1.5rem;
            color: rgba(255,255,255,0.7);
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .menu-item:hover, .menu-item.active {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        
        .menu-item i {
            margin-right: 0.75rem;
            font-size: 1.25rem;
        }
        
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 2rem;
        }
        
        .top-bar {
            background: white;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 900;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .dashboard-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.08);
        }
        
        .stat-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
        }
        
        .stat-info {
            flex: 1;
        }
        
        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            color: #1f2937;
        }
        
        .stat-info p {
            color: #6b7280;
            margin: 0;
            font-size: 0.9rem;
        }
        
        .welcome-section {
            background: linear-gradient(120deg, var(--primary-color), var(--secondary-color));
            border-radius: 20px;
            margin: 1rem 0 2rem;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-section::after {
            content: '';
            position: absolute;
            right: 0;
            top: 0;
            width: 300px;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1));
        }
        
        .recent-links {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .recent-links .card-header {
            background: transparent;
            border-bottom: 1px solid #e5e7eb;
            padding: 1.25rem;
        }
        
        .link-item {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e5e7eb;
            transition: all 0.2s ease;
        }
        
        .link-item:hover {
            background: #f8fafc;
        }
        
        .link-title {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
        }
        
        .quick-action-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
        }
        
        .quick-action-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            border-radius: 12px;
            background: #f8fafc;
            color: #1f2937;
            border: 1px solid #e5e7eb;
            transition: all 0.2s ease;
        }
        
        .quick-action-btn:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }
        
        .screenshot-preview img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .screenshot-thumbnails img {
            transition: all 0.3s ease;
        }
        
        .screenshot-thumbnails img:hover {
            transform: scale(1.1);
        }
        
        .delete-screenshot {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .screenshot-preview .position-relative:hover .delete-screenshot {
            opacity: 1;
        }
        
        .image-source {
            position: absolute;
            bottom: 5px;
            right: 5px;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
            color: white;
            background: rgba(0, 0, 0, 0.6);
        }
        
        .image-source.uploaded {
            background: rgba(var(--success-color-rgb), 0.8);
        }
        
        .image-source.external {
            background: rgba(var(--info-color-rgb), 0.8);
        }
        
        .screenshot-preview .position-relative {
            border-radius: 8px;
            overflow: hidden;
        }

        .trend-bar {
            height: 30px;
            background: rgba(67, 56, 202, 0.1);
            border-radius: 15px;
            position: relative;
            min-width: 30px;
            max-width: 100%;
            margin-right: 10px;
        }

        .trend-bar-inner {
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            background: var(--primary-color);
            border-radius: 15px;
            width: 100%;
            animation: growBar 1s ease-out;
        }

        .visit-count {
            min-width: 50px;
            text-align: right;
            color: var(--primary-color);
            font-weight: 600;
        }

        @keyframes growBar {
            from { width: 0; }
            to { width: 100%; }
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .card-title {
            color: #1e293b;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- 侧边栏 -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>管理后台</h3>
                <a href="../" class="home-link" title="访问首页" target="_blank">
                    <i class='bx bx-home'></i>
                </a>
            </div>
            <div class="sidebar-menu">
                <a href="index.php" class="menu-item active">
                    <i class='bx bx-dashboard'></i> 仪表盘
                </a>
                <a href="category_manage.php" class="menu-item">
                    <i class='bx bx-category'></i> 分类管理
                </a>
                <a href="link_manage.php" class="menu-item">
                    <i class='bx bx-link'></i> 链接管理
                </a>
                <a href="users.php" class="menu-item">
                    <i class='bx bx-user'></i> 用户管理
                </a>
                <a href="smtp_config.php" class="menu-item">
                    <i class='bx bx-envelope'></i> 邮箱配置
                </a>
                <a href="logout.php" class="menu-item text-danger">
                    <i class='bx bx-log-out'></i> 退出登录
                </a>
            </div>
        </div>

        <!-- 主要内容区域 -->
        <div class="main-content">
            <!-- 顶部栏 -->
            <div class="top-bar mb-4">
                <div>
                    <h4 class="mb-0">控制面板</h4>
                </div>
                <div class="d-flex align-items-center">
                    <span class="me-3">
                        <i class='bx bx-calendar'></i>
                        <?php echo date('Y年m月d日'); ?>
                    </span>
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                            <i class='bx bx-user'></i>
                            管理员
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="logout.php">退出登录</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 分类管理内容 -->
            <div class="category-management" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">分类管理</h4>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
                        <i class='bx bx-plus'></i> 添加分类
                    </button>
                </div>

                <div class="category-card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>名称</th>
                                    <th>描述</th>
                                    <th>排序</th>
                                    <th>链接数量</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($categories as $category): ?>
                                    <tr>
                                        <td><?php echo $category['id']; ?></td>
                                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                                        <td><?php echo htmlspecialchars($category['description']); ?></td>
                                        <td><?php echo $category['sort_order']; ?></td>
                                        <td><?php echo $category['link_count']; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" 
                                                    onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                                <i class='bx bx-edit'></i> 编辑
                                            </button>
                                            <?php if ($category['link_count'] == 0): ?>
                                                <button class="btn btn-sm btn-danger"
                                                       onclick="deleteCategory(<?php echo $category['id']; ?>)">
                                                    <i class='bx bx-trash'></i> 删除
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 链接管理内容 -->
            <div class="link-management" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">链接管理</h4>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#linkModal">
                        <i class='bx bx-plus'></i> 添加链接
                    </button>
                </div>

                <div class="link-card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>标题</th>
                                    <th>URL</th>
                                    <th>分类</th>
                                    <th>图标</th>
                                    <th>演示图片</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $stmt = $conn->prepare("SELECT l.*, c.name as category_name 
                                                  FROM links l 
                                                  JOIN categories c ON l.category_id = c.id 
                                                  ORDER BY l.id DESC");
                                $stmt->execute();
                                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach($result as $link): 
                                ?>
                                    <tr>
                                        <td><?php echo $link['id']; ?></td>
                                        <td><?php echo htmlspecialchars($link['title']); ?></td>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($link['url']); ?>" 
                                               target="_blank" class="text-primary">
                                                <?php echo htmlspecialchars($link['url']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($link['category_name']); ?></td>
                                        <td>
                                            <?php if($link['icon']): ?>
                                                <img src="<?php echo htmlspecialchars($link['icon']); ?>" 
                                                     alt="icon" style="height: 20px;">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if($link['screenshots']): ?>
                                            <div class="screenshot-thumbnails d-flex gap-2">
                                                <?php foreach(explode(',', $link['screenshots']) as $screenshot): ?>
                                                <div class="position-relative">
                                                    <a href="<?php echo htmlspecialchars($screenshot); ?>" 
                                                       data-fslightbox="screenshots-<?php echo $link['id']; ?>">
                                                        <img src="<?php echo htmlspecialchars($screenshot); ?>" 
                                                             alt="screenshot" style="height: 40px;" class="rounded">
                                                    </a>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" 
                                                    onclick="editLink(<?php echo htmlspecialchars(json_encode($link)); ?>)">
                                                <i class='bx bx-edit'></i> 编辑
                                            </button>
                                            <button class="btn btn-sm btn-danger"
                                                    onclick="deleteLink(<?php echo $link['id']; ?>)">
                                                <i class='bx bx-trash'></i> 删除
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 用户管理内容 -->
            <div class="user-management" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">用户管理</h4>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addUserForm">
                            <i class='bx bx-user-plus'></i> 添加新用户
                        </button>
                    </div>
                </div>

                <?php if (isset($message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- 添加用户表单 -->
                <div class="collapse mb-4" id="addUserForm">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">添加新用户</h5>
                            <form method="POST" onsubmit="return validateUserForm()">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">用户名</label>
                                        <input type="text" class="form-control" name="username" required minlength="3" maxlength="50">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">邮箱</label>
                                        <input type="email" class="form-control" name="email" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">密码</label>
                                        <input type="password" class="form-control" name="password" required minlength="6">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">确认密码</label>
                                        <input type="password" class="form-control" name="confirm_password" required minlength="6">
                                    </div>
                                </div>
                                <div class="text-end">
                                    <button type="button" class="btn btn-secondary" data-bs-toggle="collapse" data-bs-target="#addUserForm">
                                        取消
                                    </button>
                                    <button type="submit" name="add_user" class="btn btn-primary">
                                        <i class='bx bx-user-plus'></i> 添加用户
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 用户列表 -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">用户列表</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th style="width: 60px">ID</th>
                                        <th>用户名</th>
                                        <th>邮箱</th>
                                        <th>注册时间</th>
                                        <th style="width: 100px">状态</th>
                                        <th style="width: 100px">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $stmt = $conn->prepare("SELECT id, username, email, created_at, status FROM users ORDER BY id DESC");
                                    $stmt->execute();
                                    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    if (count($users) > 0):
                                        foreach($users as $user): 
                                    ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class='bx bx-user-circle fs-4 me-2'></i>
                                                <?php echo htmlspecialchars($user['username']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <span class="badge <?php echo $user['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $user['status'] ? '活跃' : '禁用'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除用户 <?php echo htmlspecialchars($user['username']); ?> 吗？')">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-danger btn-sm" title="删除用户">
                                                    <i class='bx bx-trash'></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php 
                                        endforeach;
                                    else:
                                    ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class='bx bx-user-x fs-1'></i>
                                                <p class="mt-2">暂无用户数据</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 控制面板内容 -->
            <div class="dashboard-content">
                <!-- 欢迎区域 -->
                <div class="welcome-section text-white mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="display-6 fw-bold mb-2">👋 欢迎回来，管理员</h2>
                            <p class="lead mb-0 opacity-75">
                                今天是 <?php echo date('Y年m月d日'); ?>，开始管理您的导航站点吧
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 数据统计卡片 -->
                <div class="row g-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: var(--primary-color);">
                                <i class='bx bx-category'></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $categoryCount; ?></h3>
                                <p>分类总数</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: var(--success-color);">
                                <i class='bx bx-link'></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $linkCount; ?></h3>
                                <p>链接总数</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: var(--warning-color);">
                                <i class='bx bx-image'></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $screenshotCount; ?></h3>
                                <p>图片总数</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: var(--info-color);">
                                <i class='bx bx-time'></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo date('H:i'); ?></h3>
                                <p>当前时间</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 在统计卡片下方添加详细统计 -->
                <div class="row g-4 mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-4">最近7天访问统计</h5>
                                <div class="visit-trend">
                                    <?php
                                    // 获取最近7天的访问数据
                                    $stmt = $conn->prepare("SELECT visit_date, visit_count 
                                                      FROM visits 
                                                      WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                                                      ORDER BY visit_date DESC");
                                    $stmt->execute();
                                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    // 确保有7天的数据
                                    $stats = [];
                                    for ($i = 0; $i < 7; $i++) {
                                        $date = date('Y-m-d', strtotime("-$i days"));
                                        $stats[$date] = 0;
                                    }
                                    
                                    foreach ($result as $stat) {
                                        $stats[$stat['visit_date']] = $stat['visit_count'];
                                    }
                                    
                                    // 计算最大值用于显示比例
                                    $maxCount = max($stats) ?: 1;
                                    
                                    foreach ($stats as $date => $count):
                                    ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span><?php echo date('m-d', strtotime($date)); ?></span>
                                        <div class="d-flex align-items-center flex-grow-1 ms-3">
                                            <div class="trend-bar" style="width: <?php echo ($count / $maxCount * 100); ?>%">
                                                <div class="trend-bar-inner"></div>
                                            </div>
                                            <span class="ms-2 visit-count"><?php echo number_format($count); ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 分类表单模态框 -->
            <div class="modal fade" id="categoryModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalTitle">添加分类</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="categoryForm" method="post">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="add_category">
                                <input type="hidden" name="id" id="categoryId">
                                
                                <div class="mb-3">
                                    <label class="form-label">分类名称</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">描述</label>
                                    <textarea class="form-control" name="description" rows="3"></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">排序</label>
                                    <input type="number" class="form-control" name="sort_order" value="0">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                                <button type="submit" class="btn btn-primary">保存</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- 链接表单模态框 -->
            <div class="modal fade" id="linkModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="linkModalTitle">添加链接</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="linkForm" method="post">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="add_link">
                                <input type="hidden" name="id" id="linkId">
                                
                                <div class="mb-3">
                                    <label class="form-label">分类</label>
                                    <select class="form-select" name="category_id" required>
                                        <?php foreach($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">标题</label>
                                    <input type="text" class="form-control" name="title" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">URL</label>
                                    <input type="url" class="form-control" name="url" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">图标URL</label>
                                    <input type="url" class="form-control" name="icon">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">描述</label>
                                    <textarea class="form-control" name="description" rows="3"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">网站演示图片</label>
                                    <div class="screenshot-upload">
                                        <div class="mb-3">
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="screenshotUrl" 
                                                       placeholder="输入图片URL">
                                                <button type="button" class="btn btn-secondary" onclick="addScreenshotUrl()">
                                                    <i class='bx bx-plus'></i> 添加
                                                </button>
                                            </div>
                                            <div class="form-text">支持直接输入图片URL或上传图片</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <input type="file" class="form-control" id="screenshotInput" 
                                                   multiple accept="image/*">
                                        </div>
                                        
                                        <input type="hidden" name="screenshots" id="screenshotsData">
                                        <div class="screenshot-preview mt-2 row g-2" id="screenshotPreview"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                                <button type="submit" class="btn btn-primary">保存</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- SMTP配置内容 -->
            <div class="smtp-config" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">邮箱配置</h4>
                </div>

                <div class="card">
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="mb-3">
                                <label class="form-label">SMTP服务器</label>
                                <input type="text" class="form-control" name="smtp_host" value="<?php echo $smtp_config['smtp_host'] ?? ''; ?>" required>
                                <div class="form-text">例如：smtp.qq.com</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">SMTP端口</label>
                                <input type="number" class="form-control" name="smtp_port" value="<?php echo $smtp_config['smtp_port'] ?? ''; ?>" required>
                                <div class="form-text">常用端口：465（SSL）或 25（非SSL）</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">SMTP用户名</label>
                                <input type="text" class="form-control" name="smtp_user" value="<?php echo $smtp_config['smtp_user'] ?? ''; ?>" required>
                                <div class="form-text">通常是完整的邮箱地址</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">SMTP密码</label>
                                <input type="password" class="form-control" name="smtp_pass" value="<?php echo $smtp_config['smtp_pass'] ?? ''; ?>" required>
                                <div class="form-text">QQ邮箱需要使用授权码，而不是邮箱密码</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">发件人邮箱</label>
                                <input type="email" class="form-control" name="smtp_from" value="<?php echo $smtp_config['smtp_from'] ?? ''; ?>" required>
                                <div class="form-text">用于发送系统邮件的邮箱地址</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">发件人名称</label>
                                <input type="text" class="form-control" name="smtp_from_name" value="<?php echo $smtp_config['smtp_from_name'] ?? ''; ?>">
                                <div class="form-text">显示的发件人名称，可选</div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" name="update_smtp" class="btn btn-primary">
                                    <i class='bx bx-save'></i> 保存配置
                                </button>
                                <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#testEmailModal">
                                    <i class='bx bx-send'></i> 发送测试
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 添加测试邮件发送模态框 -->
    <div class="modal fade" id="testEmailModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">发送测试邮件</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">测试接收邮箱</label>
                            <input type="email" class="form-control" name="test_email" required>
                            <div class="form-text">请输入用于接收测试邮件的邮箱地址</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" name="test_smtp" class="btn btn-primary">
                            <i class='bx bx-send'></i> 发送测试
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fslightbox@3.3.1/index.min.js"></script>
    <script>
        // 切换内容显示
        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const target = this.getAttribute('href');
                
                document.querySelectorAll('.menu-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                
                document.querySelector('.dashboard-content').style.display = 'none';
                document.querySelector('.category-management').style.display = 'none';
                document.querySelector('.link-management').style.display = 'none';
                document.querySelector('.user-management').style.display = 'none';
                document.querySelector('.smtp-config').style.display = 'none';
                
                if (target === 'index.php') {
                    document.querySelector('.dashboard-content').style.display = 'block';
                } else if (target === 'category_manage.php') {
                    document.querySelector('.category-management').style.display = 'block';
                } else if (target === 'link_manage.php') {
                    document.querySelector('.link-management').style.display = 'block';
                } else if (target === 'users.php') {
                    document.querySelector('.user-management').style.display = 'block';
                } else if (target === 'smtp_config.php') {
                    document.querySelector('.smtp-config').style.display = 'block';
                }
            });
        });

        // 编辑分类
        function editCategory(category) {
            const form = document.getElementById('categoryForm');
            const modal = new bootstrap.Modal(document.getElementById('categoryModal'));
            
            document.getElementById('modalTitle').textContent = '编辑分类';
            form.action.value = 'edit_category';
            form.id.value = category.id;
            form.name.value = category.name;
            form.description.value = category.description;
            form.sort_order.value = category.sort_order;
            
            modal.show();
        }

        // 删除分类
        function deleteCategory(id) {
            if (confirm('确定要删除这个分类吗？')) {
                fetch(`?delete_category=${id}`)
                    .then(response => window.location.reload());
            }
        }

        // 重置表单
        document.getElementById('categoryModal').addEventListener('hidden.bs.modal', function () {
            const form = document.getElementById('categoryForm');
            document.getElementById('modalTitle').textContent = '添加分类';
            form.reset();
            form.action.value = 'add_category';
            form.id.value = '';
        });

        // 图片上传处理
        const screenshotInput = document.getElementById('screenshotInput');
        const screenshotsData = document.getElementById('screenshotsData');
        const screenshotPreview = document.getElementById('screenshotPreview');
        let uploadedImages = [];

        screenshotInput.addEventListener('change', async function(e) {
            const files = Array.from(e.target.files);
            
            for (const file of files) {
                if (!file.type.startsWith('image/')) continue;
                
                const formData = new FormData();
                formData.append('image', file);
                
                try {
                    const response = await fetch('upload.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        uploadedImages.push(data.url);
                        updateScreenshotPreview();
                    }
                } catch (error) {
                    console.error('Upload failed:', error);
                }
            }
            
            screenshotsData.value = uploadedImages.join(',');
        });

        function updateScreenshotPreview() {
            screenshotPreview.innerHTML = uploadedImages.map((url, index) => {
                const isUploadedFile = url.startsWith('/uploads/');
                return `
                    <div class="col-4 col-md-3">
                        <div class="position-relative">
                            <a href="${url}" data-fslightbox="upload-preview">
                                <img src="${url}" class="img-fluid" alt="Screenshot ${index + 1}">
                            </a>
                            <button type="button" class="delete-screenshot"
                                    onclick="removeScreenshot(${index}, '${url}')">
                                <i class='bx bx-x'></i>
                            </button>
                            <span class="image-source ${isUploadedFile ? 'uploaded' : 'external'}">
                                ${isUploadedFile ? '本地' : '外链'}
                            </span>
                        </div>
                    </div>
                `;
            }).join('');
            
            refreshFsLightbox();
        }

        async function removeScreenshot(index, url) {
            if (!confirm('确定要删除这张图片吗？')) return;
            
            const linkId = document.getElementById('linkId').value;
            const isUploadedFile = url.startsWith('/uploads/');
            
            if (linkId && isUploadedFile) {
                try {
                    const response = await fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `delete_screenshot=1&link_id=${linkId}&screenshot=${url}`
                    });
                    
                    const data = await response.json();
                    if (!data.success) return;
                } catch (error) {
                    console.error('Delete failed:', error);
                    return;
                }
            }
            
            uploadedImages.splice(index, 1);
            screenshotsData.value = uploadedImages.join(',');
            updateScreenshotPreview();
        }

        // 编辑链接
        function editLink(link) {
            const form = document.getElementById('linkForm');
            const modal = new bootstrap.Modal(document.getElementById('linkModal'));
            
            document.getElementById('linkModalTitle').textContent = '编辑链接';
            form.action.value = 'edit_link';
            form.id.value = link.id;
            form.category_id.value = link.category_id;
            form.title.value = link.title;
            form.url.value = link.url;
            form.icon.value = link.icon || '';
            form.description.value = link.description || '';
            
            uploadedImages = link.screenshots ? link.screenshots.split(',') : [];
            updateScreenshotPreview();
            
            modal.show();
        }

        // 删除链接
        function deleteLink(id) {
            if (confirm('确定要删除这个链接吗？')) {
                fetch(`?delete_link=${id}`)
                    .then(response => window.location.reload());
            }
        }

        // 重置链接表单
        document.getElementById('linkModal').addEventListener('hidden.bs.modal', function () {
            const form = document.getElementById('linkForm');
            document.getElementById('linkModalTitle').textContent = '添加链接';
            form.reset();
            form.action.value = 'add_link';
            form.id.value = '';
            
            uploadedImages = [];
            updateScreenshotPreview();
        });

        // 添加URL图片
        function addScreenshotUrl() {
            const urlInput = document.getElementById('screenshotUrl');
            const url = urlInput.value.trim();
            
            if (!url) return;
            
            // 简单的URL验证
            if (!isValidImageUrl(url)) {
                alert('请输入有效的图片URL');
                return;
            }
            
            // 检查图片是否可访问
            checkImageUrl(url).then(valid => {
                if (valid) {
                    uploadedImages.push(url);
                    screenshotsData.value = uploadedImages.join(',');
                    updateScreenshotPreview();
                    urlInput.value = '';
                } else {
                    alert('无法访问该图片，请检查URL是否正确');
                }
            });
        }

        // 验证图片URL格式
        function isValidImageUrl(url) {
            try {
                const parsed = new URL(url);
                const ext = url.split('.').pop().toLowerCase();
                const validExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                return validExts.includes(ext);
            } catch {
                return false;
            }
        }

        // 检查图片是否可访问
        function checkImageUrl(url) {
            return new Promise(resolve => {
                const img = new Image();
                img.onload = () => resolve(true);
                img.onerror = () => resolve(false);
                img.src = url;
            });
        }

        // 添加用户表单验证
        function validateUserForm() {
            var password = document.querySelector('input[name="password"]').value;
            var confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            
            if (password !== confirmPassword) {
                alert('两次输入的密码不一致！');
                return false;
            }
            return true;
        }
    </script>
</body>
</html>