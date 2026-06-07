<?php
/**
 * COBRAWA — Configuração do Banco de Dados
 * Gerado pelo instalador. Edite conforme necessário.
 */

// Banco de dados — preencha antes de usar sem o instalador
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cobrawa');

// URL base do sistema (sem barra final)
define('APP_URL', 'https://lebarone.deltatelecomti.com.br');

// Chave secreta para tokens — mude em produção!
define('SECRET_KEY', 'MUDE_ESTA_CHAVE_EM_PRODUCAO_12345678');

// Tempo de sessão em minutos
define('SESSION_TIMEOUT', 480);

// Máximo de tentativas de login
define('MAX_LOGIN_ATTEMPTS', 5);
