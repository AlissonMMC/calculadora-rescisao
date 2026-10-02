-- MIGRAÇÃO DA V1 PARA V2 — rode este arquivo no phpMyAdmin
USE calculadora_rescisao;

CREATE TABLE IF NOT EXISTS usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(120) NOT NULL,
    login VARCHAR(60) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    perfil VARCHAR(20) NOT NULL DEFAULT 'usuario',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_login (login),
    KEY idx_usuarios_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO usuarios (nome, login, senha_hash, perfil, ativo)
VALUES ('Administrador', 'admin', '$2y$12$9Pv3Xhg.qoNonmuEz5fzL.JkLFZiTXG6l2vOY/lGgFrU9cknJ8k6q', 'admin', 1);

SET @tem_usuario_id := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'historico_rescisoes' AND COLUMN_NAME = 'usuario_id'
);
SET @sql_usuario_id := IF(
  @tem_usuario_id = 0,
  'ALTER TABLE historico_rescisoes ADD COLUMN usuario_id BIGINT UNSIGNED NULL AFTER id',
  'SELECT 1'
);
PREPARE stmt_usuario_id FROM @sql_usuario_id;
EXECUTE stmt_usuario_id;
DEALLOCATE PREPARE stmt_usuario_id;

SET @tem_idx_usuario_id := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'historico_rescisoes' AND INDEX_NAME = 'idx_usuario_id'
);
SET @sql_idx_usuario_id := IF(
  @tem_idx_usuario_id = 0,
  'ALTER TABLE historico_rescisoes ADD INDEX idx_usuario_id (usuario_id)',
  'SELECT 1'
);
PREPARE stmt_idx_usuario_id FROM @sql_idx_usuario_id;
EXECUTE stmt_idx_usuario_id;
DEALLOCATE PREPARE stmt_idx_usuario_id;

-- Registros antigos passam a aparecer como criados pelo administrador inicial.
UPDATE historico_rescisoes
SET usuario_id = (SELECT id FROM usuarios WHERE login = 'admin' LIMIT 1)
WHERE usuario_id IS NULL;
