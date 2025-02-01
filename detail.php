<?php
require_once 'config/database.php';

// 获取链接ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 获取链接详情
$stmt = $db->prepare("SELECT l.*, c.name as category_name 
                      FROM links l 
                      JOIN categories c ON l.category_id = c.id 
                      WHERE l.id = ?");
$stmt->execute([$id]);
$link = $stmt->fetch(PDO::FETCH_ASSOC);

// 如果链接不存在，跳转到首页
if (!$link) {
    header('Location: /');
    exit;
}

// 获取相关链接（同分类的其他链接）
$stmt = $db->prepare("SELECT * FROM links 
                      WHERE category_id = ? AND id != ? 
                      ORDER BY id DESC LIMIT 6");
$stmt->execute([$link['category_id'], $id]);
$relatedLinks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($link['title']); ?> - 导航网站</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fslightbox@3.3.1/index.min.css" rel="stylesheet">
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

        .detail-header {
            background: var(--card-bg);
            padding: 3rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .link-icon-large {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            margin-right: 1.5rem;
            object-fit: contain;
        }

        .link-title-large {
            font-size: 2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .link-meta {
            color: #64748b;
            font-size: 0.95rem;
        }

        .link-meta i {
            margin-right: 0.25rem;
        }

        .link-description-full {
            color: #334155;
            font-size: 1.1rem;
            line-height: 1.7;
            margin: 2rem 0;
        }

        .visit-button {
            padding: 0.75rem 2rem;
            font-size: 1.1rem;
            border-radius: 8px;
            background-color: var(--primary-color);
            border: none;
            transition: all 0.3s ease;
        }

        .visit-button:hover {
            background-color: var(--hover-color);
            transform: translateY(-2px);
        }

        .related-card {
            background: var(--card-bg);
            border-radius: 12px;
            border: none;
            padding: 1.5rem;
            height: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .related-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .related-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            margin-right: 0.75rem;
        }

        .related-title {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .related-title:hover {
            color: var(--hover-color);
        }

        .footer {
            background: var(--card-bg);
            padding: 2rem 0;
            margin-top: 3rem;
            border-top: 1px solid #e2e8f0;
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
                        <a class="nav-link" href="/admin/login.php">管理入口</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="detail-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center mb-3">
                        <?php if($link['icon']): ?>
                            <img src="<?php echo htmlspecialchars($link['icon']); ?>" 
                                 alt="" class="link-icon-large">
                        <?php else: ?>
                            <i class='bx bx-link link-icon-large'></i>
                        <?php endif; ?>
                        <div>
                            <h1 class="link-title-large mb-2">
                                <?php echo htmlspecialchars($link['title']); ?>
                            </h1>
                            <div class="link-meta">
                                <span class="me-3">
                                    <i class='bx bx-folder'></i>
                                    <?php echo htmlspecialchars($link['category_name']); ?>
                                </span>
                                <span>
                                    <i class='bx bx-time'></i>
                                    <?php echo date('Y-m-d', strtotime($link['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="<?php echo htmlspecialchars($link['url']); ?>" 
                       target="_blank" 
                       class="btn btn-primary btn-lg visit-button">
                        <i class='bx bx-link-external'></i> 访问网站
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">网站介绍</h5>
                        <?php if ($link['screenshots']): ?>
                        <div class="screenshots-gallery mb-4">
                            <div class="row g-3">
                                <?php foreach(explode(',', $link['screenshots']) as $screenshot): ?>
                                <div class="col-md-6">
                                    <a href="<?php echo htmlspecialchars($screenshot); ?>" 
                                       data-fslightbox="screenshots">
                                        <img src="<?php echo htmlspecialchars($screenshot); ?>" 
                                             class="img-fluid rounded" alt="网站截图">
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="link-description-full">
                            <?php echo nl2br(htmlspecialchars($link['description'])); ?>
                        </div>
                        <div class="mt-4">
                            <a href="<?php echo htmlspecialchars($link['url']); ?>" 
                               target="_blank" 
                               class="text-decoration-none">
                                <?php echo htmlspecialchars($link['url']); ?>
                                <i class='bx bx-link-external'></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <?php if (!empty($relatedLinks)): ?>
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">相关网站</h5>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach($relatedLinks as $related): ?>
                            <div class="related-card">
                                <div class="d-flex align-items-center">
                                    <?php if($related['icon']): ?>
                                        <img src="<?php echo htmlspecialchars($related['icon']); ?>" 
                                             alt="" class="related-icon">
                                    <?php else: ?>
                                        <i class='bx bx-link related-icon'></i>
                                    <?php endif; ?>
                                    <a href="detail.php?id=<?php echo $related['id']; ?>" 
                                       class="related-title">
                                        <?php echo htmlspecialchars($related['title']); ?>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container text-center text-muted">
            <p class="mb-0">© <?php echo date('Y'); ?> 导航网站. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fslightbox@3.3.1/index.min.js"></script>
</body>
</html> 