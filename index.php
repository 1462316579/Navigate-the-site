<?php
require_once 'config/database.php';
session_start();

// 获取所有分类
$stmt = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC, id ASC");
$categories = $stmt->fetchAll();

// 获取所有链接
$stmt = $pdo->query("SELECT l.*, c.name as category_name 
                     FROM links l 
                     JOIN categories c ON l.category_id = c.id 
                     ORDER BY l.id DESC");
$links = $stmt->fetchAll();

// 记录访问量
$today = date('Y-m-d');
try {
    // 尝试更新今天的访问记录
    $stmt = $pdo->prepare("UPDATE visits SET visit_count = visit_count + 1 WHERE visit_date = ?");
    if (!$stmt->execute([$today])) {
        // 如果没有更新到记录，说明今天还没有访问记录，创建新记录
        $stmt = $pdo->prepare("INSERT INTO visits (visit_date, visit_count) VALUES (?, 1)");
        $stmt->execute([$today]);
    }
} catch (PDOException $e) {
    // 可以记录错误日志
    error_log("访问统计失败：" . $e->getMessage());
}

// 处理搜索
$search = $_GET['search'] ?? '';
$category_id = $_GET['category'] ?? '';

// 修改搜索逻辑部分
if ($search || $category_id) {
    $sql = "SELECT l.*, c.name as category_name 
            FROM links l 
            JOIN categories c ON l.category_id = c.id 
            WHERE 1=1";
    $params = [];
    
    if ($search) {
        $sql .= " AND (l.title LIKE ? OR l.description LIKE ? OR l.keywords LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if ($category_id) {
        $sql .= " AND l.category_id =?";
        $params[] = $category_id;
    }
    
    $sql .= " ORDER BY l.id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $links = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>导航网站</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root {
            --primary-color: #4f46e5;
            --hover-color: #4338ca;
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
        }
        
        body {
            background-color: var(--bg-color);
            color: #1e293b;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--hover-color));
            padding: 1rem 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .search-box {
            background: var(--bg-color);
            padding: 1.5rem 0;
            margin-bottom: 2rem;
        }

        .search-wrapper {
            max-width: 800px;
            margin: 0 auto;
            background: var(--card-bg);
            border-radius: 12px;
            padding: 0.75rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .search-form {
            display: flex;
            width: 100%;
        }

        .search-input-wrapper {
            flex: 1;
            position: relative;
            display: flex;
            align-items: center;
            background: var(--bg-color);
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .search-input-wrapper:focus-within {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            color: #64748b;
            font-size: 1.25rem;
            pointer-events: none;
        }

        .search-input {
            flex: 1;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: none;
            background: transparent;
            font-size: 1rem;
        }

        .search-input:focus {
            outline: none;
            box-shadow: none;
        }

        .search-category {
            min-width: 140px;
            border-left: 1px solid #e2e8f0;
        }

        .search-category .form-select {
            border: none;
            padding: 0.75rem 1rem;
            background-color: transparent;
            color: #1e293b;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-category .form-select:focus {
            outline: none;
            box-shadow: none;
        }

        .category-section {
            margin-bottom: 3rem;
        }

        .category-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color);
        }

        .category-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
            display: flex;
            align-items: center;
        }

        .category-title i {
            margin-right: 0.5rem;
            font-size: 1.5rem;
        }

        .link-card {
            height: 100%;
            background: var(--card-bg);
            border-radius: 12px;
            border: none;
            padding: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .link-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .link-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            margin-right: 0.75rem;
            object-fit: contain;
        }

        .link-title {
            color: var(--primary-color);
            font-weight: 500;
            text-decoration: none;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        .link-title:hover {
            color: var(--hover-color);
        }

        .link-description {
            color: #64748b;
            font-size: 0.9rem;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .footer {
            background: var(--card-bg);
            padding: 2rem 0;
            margin-top: 3rem;
            border-top: 1px solid #e2e8f0;
        }

        .search-box,
        .search-form,
        .search-input,
        .search-select,
        .search-button {
            display: block;
        }

        .nav-right {
            display: flex;
            align-items: center;
        }

        .nav-right .nav-link {
            color: rgba(255, 255, 255, 0.9);
            padding: 0.5rem;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .nav-right .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .user-menu .dropdown-toggle::after {
            display: none;
        }

        .user-menu .dropdown-menu {
            min-width: 200px;
            padding: 0.5rem 0;
            margin-top: 0.5rem;
            border: none;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
        }

        .user-menu .dropdown-item {
            padding: 0.6rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #1e293b;
        }

        .user-menu .dropdown-item:hover {
            background-color: #f8f9fa;
            color: var(--primary-color);
        }

        .user-menu .dropdown-item-text {
            padding: 0.6rem 1.5rem;
            color: #64748b;
            font-weight: 500;
        }

        .auth-links {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .auth-links .nav-link i {
            font-size: 1.5rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class='bx bx-compass'></i> 导航网站
            </a>
            <div class="nav-right ms-auto">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="user-menu dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class='bx bxs-user-circle fs-4'></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text">Hi, <?php echo htmlspecialchars($_SESSION['username']); ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class='bx bx-log-out'></i> 退出</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="auth-links">
                        <a href="login.php" class="nav-link" title="登录">
                            <i class='bx bx-user fs-4'></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="search-box">
        <div class="container">
            <div class="search-wrapper">
                <form method="get" class="search-form">
                    <div class="search-input-wrapper">
                        <i class='bx bx-search search-icon'></i>
                        <input type="text" name="search" class="search-input" 
                               placeholder="搜索网站..." value="<?php echo htmlspecialchars($search); ?>">
                        <div class="search-category">
                            <select name="category" class="form-select">
                                <option value="">全部分类</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" 
                                            <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <!-- 左侧分类侧边栏 -->
            <div class="col-md-2">
                <div class="card category-sidebar-card">
                    <div class="card-header">
                        <h5 class="mb-0">网站分类</h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="nav flex-column category-sidebar">
                            <?php
                            $stmt = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC, id ASC");
                            while($row = $stmt->fetch()) {
                                echo '<li class="nav-item">';
                                echo '<a class="nav-link py-2 px-3" href="#category-'.$row['id'].'">';
                                echo '<i class="bx bx-chevron-right"></i> '.htmlspecialchars($row['name']);
                                echo '</a>';
                                echo '</li>';
                            }
                            ?>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- 右侧网站列表内容 -->
            <div class="col-md-10">
                <?php
                $stmt = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC, id ASC");
                while($category = $stmt->fetch()) {
                    echo '<div id="category-'.$category['id'].'" class="category-section mb-4">';
                    echo '<div class="category-header">';
                    echo '<h2 class="category-title">';
                    echo '<i class="bx bx-category"></i> '.htmlspecialchars($category['name']);
                    echo '</h2>';
                    echo '</div>';
                    echo '<div class="row g-4">';
                    
                    $links_stmt = $pdo->prepare("SELECT * FROM links WHERE category_id = ? ORDER BY id DESC");
                    $links_stmt->execute([$category['id']]);
                    
                    while($link = $links_stmt->fetch()) {
                        echo '<div class="col-md-6 col-lg-4">';
                        echo '<div class="link-card">';
                        echo '<div class="d-flex align-items-center mb-3">';
                        if($link['icon']) {
                            echo '<img src="'.htmlspecialchars($link['icon']).'" alt="" class="link-icon">';
                        } else {
                            echo '<i class="bx bx-link link-icon"></i>';
                        }
                        echo '<a href="detail.php?id='.$link['id'].'" class="link-title">';
                        echo htmlspecialchars($link['title']);
                        echo '</a>';
                        echo '</div>';
                        echo '<p class="link-description">';
                        echo htmlspecialchars($link['description']);
                        echo '</p>';
                        echo '</div>';
                        echo '</div>';
                    }
                    
                    echo '</div>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container text-center text-muted">
            <p class="mb-0">© <?php echo date('Y'); ?> 导航网站. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>