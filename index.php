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

// 准备查询条件
$where = [];
$params = [];
$types = "";

if ($search) {
    $where[] = "MATCH(title, description, keywords) AGAINST(? IN BOOLEAN MODE)";
    $params[] = "*$search*";
    $types .= "s";
}

if ($category_id) {
    $where[] = "category_id = ?";
    $params[] = $category_id;
    $types .= "i";
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// 如果有搜索条件，使用预处理语句
if (!empty($params)) {
    $stmt = $pdo->prepare("SELECT l.*, c.name as category_name 
                           FROM links l 
                           JOIN categories c ON l.category_id = c.id 
                           $whereClause 
                           ORDER BY l.id DESC");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $links = $result->fetch_all(MYSQLI_ASSOC);
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
            background: var(--card-bg);
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .search-form {
            max-width: 800px;
            margin: 0 auto;
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

        .search-input {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .search-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .search-select {
            min-width: 120px;
            border-radius: 8px;
        }

        .search-button {
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            background-color: var(--primary-color);
            border: none;
        }

        .search-button:hover {
            background-color: var(--hover-color);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class='bx bx-compass'></i> 导航网站
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <div class="nav-right">
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <span class="welcome">欢迎，<?php echo htmlspecialchars($_SESSION['username']); ?></span>
                                <a href="logout.php" class="nav-link">退出</a>
                            <?php else: ?>
                                <a href="login.php" class="nav-link">登录</a>
                                <a href="register.php" class="nav-link">注册</a>
                            <?php endif; ?>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="search-box">
        <div class="container">
            <form method="get" class="search-form">
                <div class="row g-3 justify-content-center">
                    <div class="col-md-8">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control search-input" 
                                   placeholder="搜索网站..." value="<?php echo htmlspecialchars($search); ?>">
                            <select name="category" class="form-select search-select">
                                <option value="">所有分类</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" 
                                            <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary search-button" type="submit">
                                <i class='bx bx-search'></i> 搜索
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="container">
        <?php if ($search || $category_id): ?>
            <div class="search-results mb-4">
                <div class="category-header">
                    <h2 class="category-title">
                        <i class='bx bx-search-alt'></i> 搜索结果
                    </h2>
                </div>
                <div class="row g-4">
                    <?php
                    foreach($links as $link):
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="link-card">
                            <div class="d-flex align-items-center mb-3">
                                <?php if($link['icon']): ?>
                                    <img src="<?php echo htmlspecialchars($link['icon']); ?>" 
                                         alt="" class="link-icon">
                                <?php else: ?>
                                    <i class='bx bx-link link-icon'></i>
                                <?php endif; ?>
                                <a href="detail.php?id=<?php echo $link['id']; ?>" 
                                   class="link-title">
                                    <?php echo htmlspecialchars($link['title']); ?>
                                </a>
                            </div>
                            <p class="link-description">
                                <?php echo htmlspecialchars($link['description']); ?>
                            </p>
                            <div class="mt-2">
                                <span class="badge bg-light text-dark">
                                    <?php echo htmlspecialchars($link['category_name']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <?php foreach($categories as $category): ?>
                <?php
                $stmt = $pdo->prepare("SELECT * FROM links WHERE category_id = ? ORDER BY id DESC");
                $stmt->execute([$category['id']]);
                $result = $stmt->get_result();
                $links = $result->fetch_all(MYSQLI_ASSOC);
                if (empty($links)) continue;
                ?>
                <div class="category-section">
                    <div class="category-header">
                        <h2 class="category-title">
                            <i class='bx bx-category'></i>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </h2>
                    </div>
                    <div class="row g-4">
                        <?php foreach($links as $link): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="link-card">
                                <div class="d-flex align-items-center mb-3">
                                    <?php if($link['icon']): ?>
                                        <img src="<?php echo htmlspecialchars($link['icon']); ?>" 
                                             alt="" class="link-icon">
                                    <?php else: ?>
                                        <i class='bx bx-link link-icon'></i>
                                    <?php endif; ?>
                                    <a href="detail.php?id=<?php echo $link['id']; ?>" 
                                       class="link-title">
                                        <?php echo htmlspecialchars($link['title']); ?>
                                    </a>
                                </div>
                                <p class="link-description">
                                    <?php echo htmlspecialchars($link['description']); ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <div class="container text-center text-muted">
            <p class="mb-0">© <?php echo date('Y'); ?> 导航网站. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>