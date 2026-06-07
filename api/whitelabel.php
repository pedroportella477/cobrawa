<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = auth();
if (!nivel($user, ['MASTER'])) jsonError('Sem permissão', 403);
$body = json_decode(file_get_contents('php://input'), true) ?? [];

$nome    = trim($body['nome']    ?? '');
$tagline = trim($body['tagline'] ?? '');
$titulo  = trim($body['titulo']  ?? '');
$logo    = trim($body['logo']    ?? '');
$tema    = in_array($body['tema'] ?? '', ['green','red']) ? $body['tema'] : 'green';

if (!$nome) jsonError('Nome obrigatório');

$configs = [
    'sistema_nome'          => $nome,
    'sistema_tagline'       => $tagline,
    'sistema_titulo_browser'=> $titulo ?: $nome,
    'sistema_logo'          => $logo,
    'sistema_tema'          => $tema,
];
foreach ($configs as $k => $v) setConfig($k, $v);

logAudit($user['id'], $user['login'], 'Configuração', "Whitelabel: $nome / tema: $tema");
jsonOk(['nome' => $nome, 'tagline' => $tagline, 'titulo' => $titulo, 'logo' => $logo, 'tema' => $tema]);
