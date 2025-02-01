<?php
require_once '../config/database.php';
session_start();

// 验证登录状态
if (!isset($_SESSION['admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// 检查是否有文件上传
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['image'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

// 验证文件类型
if (!in_array($file['type'], $allowedTypes)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid file type']);
    exit;
}

// 创建上传目录
$uploadDir = '../uploads/screenshots/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// 生成唯一文件名
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid() . '.' . $extension;
$filepath = $uploadDir . $filename;

// 移动文件
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'url' => '/uploads/screenshots/' . $filename
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Upload failed']);
} 