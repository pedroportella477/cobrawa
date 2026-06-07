<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = auth();

$uploadDir = __DIR__ . '/../assets/uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$file = $_FILES['file'] ?? $_FILES['audio'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    jsonError('Nenhum arquivo recebido ou erro no upload');
}

$maxSize = 16 * 1024 * 1024; // 16 MB
if ($file['size'] > $maxSize) jsonError('Arquivo muito grande (máx 16MB)');

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (isset($_FILES['audio'])) $ext = 'webm';

$allowed = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','txt','csv','webm','ogg','mp3','mp4'];
if (!in_array($ext, $allowed)) jsonError('Tipo de arquivo não permitido');

$name    = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$dest    = $uploadDir . $name;

if (!move_uploaded_file($file['tmp_name'], $dest)) jsonError('Falha ao salvar arquivo');

$url = APP_URL . '/assets/uploads/' . $name;
jsonOk(['url' => $url, 'nome' => $file['name'], 'ext' => $ext]);
