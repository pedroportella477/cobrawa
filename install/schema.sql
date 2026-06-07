-- ============================================================
-- COBRAWA — Schema do Banco de Dados
-- MySQL 8+ / MariaDB 10.4+
-- ============================================================
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `configuracoes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `chave` VARCHAR(100) NOT NULL UNIQUE,
  `valor` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `login` VARCHAR(50) NOT NULL UNIQUE,
  `senha` VARCHAR(255) NOT NULL,
  `nome` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100),
  `nivel` ENUM('MASTER','ADMIN','SUPERVISOR','OPERADOR') NOT NULL DEFAULT 'OPERADOR',
  `setor` VARCHAR(100),
  `avatar` VARCHAR(255),
  `status_operador` ENUM('online','ausente','almoco','cafe','offline') NOT NULL DEFAULT 'offline',
  `ultimo_acesso` TIMESTAMP NULL,
  `ip_ultimo_acesso` VARCHAR(45),
  `tentativas_login` INT DEFAULT 0,
  `bloqueado` TINYINT(1) DEFAULT 0,
  `primeiro_acesso` TINYINT(1) DEFAULT 1,
  `ativo` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `clientes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(150) NOT NULL,
  `cpf_cnpj` VARCHAR(20),
  `telefone` VARCHAR(20),
  `whatsapp` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100),
  `endereco` VARCHAR(200),
  `cidade` VARCHAR(100),
  `estado` CHAR(2),
  `cep` VARCHAR(10),
  `produto` VARCHAR(100),
  `valor_divida` DECIMAL(10,2) DEFAULT 0,
  `data_vencimento` DATE,
  `status_cobranca` ENUM('Pendente','Em negociacao','Pago','Judicial','Equipamento retirado','Sem Contato') DEFAULT 'Pendente',
  `observacoes` TEXT,
  `operador_id` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`operador_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conversas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `cliente_id` INT NOT NULL,
  `operador_id` INT,
  `protocolo` VARCHAR(30) UNIQUE,
  `status` ENUM('aberta','encerrada','transferida') DEFAULT 'aberta',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`operador_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mensagens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `conversa_id` INT NOT NULL,
  `cliente_id` INT NOT NULL,
  `waha_id` VARCHAR(100),
  `direcao` ENUM('enviada','recebida') NOT NULL,
  `tipo` ENUM('texto','imagem','audio','documento','video','sticker') DEFAULT 'texto',
  `conteudo` TEXT,
  `arquivo_url` VARCHAR(500),
  `lido` TINYINT(1) DEFAULT 0,
  `enviado_por` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`conversa_id`) REFERENCES `conversas`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`enviado_por`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `msgs_prontas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `titulo` VARCHAR(100) NOT NULL,
  `categoria` VARCHAR(80),
  `corpo` TEXT NOT NULL,
  `ativo` TINYINT(1) DEFAULT 1,
  `criado_por` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`criado_por`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transferencias` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `conversa_id` INT,
  `cliente_id` INT,
  `operador_origem_id` INT,
  `operador_destino_id` INT,
  `motivo` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`conversa_id`) REFERENCES `conversas`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`operador_origem_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`operador_destino_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `alertas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `remetente_id` INT,
  `destinatario_id` INT NULL,
  `mensagem` TEXT NOT NULL,
  `prioridade` ENUM('Normal','Urgente','Critico') DEFAULT 'Normal',
  `lido` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`remetente_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`destinatario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT,
  `usuario_login` VARCHAR(50),
  `ip` VARCHAR(45),
  `acao` VARCHAR(80) NOT NULL,
  `detalhes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `waha_config` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `servidor` VARCHAR(255) NOT NULL DEFAULT 'http://127.0.0.1:3000',
  `sessao` VARCHAR(100) NOT NULL DEFAULT 'default',
  `api_key` VARCHAR(255) NOT NULL DEFAULT '',
  `webhook_url` VARCHAR(255),
  `ativo` TINYINT(1) DEFAULT 1,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dados iniciais
INSERT IGNORE INTO `configuracoes` (`chave`,`valor`) VALUES
('sistema_nome','CobraWA'),
('sistema_tagline','Sistema de Cobrança WhatsApp'),
('sistema_logo',''),
('sistema_favicon',''),
('sistema_tema','green'),
('sistema_titulo_browser','CobraWA — Sistema de Cobrança'),
('versao','1.0.0');

-- Senha inicial: admin123
INSERT IGNORE INTO `usuarios` (`login`,`senha`,`nome`,`email`,`nivel`,`setor`,`status_operador`,`primeiro_acesso`) VALUES
('master','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uSc/dLyBi','Master Admin','master@empresa.com','MASTER','TI','online',1);

INSERT IGNORE INTO `waha_config` (`servidor`,`sessao`,`api_key`,`webhook_url`) VALUES
('http://127.0.0.1:3000','default','','');

INSERT IGNORE INTO `msgs_prontas` (`titulo`,`categoria`,`corpo`) VALUES
('Cobrança Amigável','Cobrança Amigável','Olá, *{nome}*! 👋\n\nIdentificamos uma pendência no valor de *{valor}* referente ao produto *{produto}*.\n\nGostaríamos de resolver isso de forma amigável. Podemos conversar?\n\nProtocolo: {protocolo}'),
('Segunda Cobrança','Segunda Cobrança','Olá, *{nome}*!\n\nEsta é a 2ª notificação sobre sua dívida de *{valor}*.\n\nTemos condições especiais. Entre em contato antes de *{vencimento}*.\n\nProtocolo: {protocolo}'),
('Proposta de Acordo','Acordo','Olá, *{nome}*! 🤝\n\nOferta especial: parcelamento em até *12x sem juros* da dívida de *{valor}*.\n\nProtocolo: {protocolo}'),
('Confirmação de Pagamento','Confirmação de Pagamento','✅ Olá, *{nome}*!\n\nConfirmamos o recebimento do pagamento referente a *{produto}*.\n\nProtocolo: *{protocolo}*\n\nObrigado!'),
('Aviso Judicial','Cobrança Judicial','⚠️ Prezado(a) *{nome}*,\n\nCaso não haja regularização da pendência de *{valor}* em 48h, o processo será encaminhado para ação judicial.\n\nProtocolo: *{protocolo}*');

SET FOREIGN_KEY_CHECKS = 1;
