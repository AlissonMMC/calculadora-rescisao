-- Migração 003 — segurança, perfis e auditoria
USE calculadora_rescisao;

ALTER TABLE usuarios
    MODIFY COLUMN perfil VARCHAR(20) NOT NULL DEFAULT 'operacional';

UPDATE usuarios
SET perfil = 'operacional'
WHERE perfil = 'usuario' OR perfil IS NULL OR perfil = '';

CREATE TABLE IF NOT EXISTS tentativas_login (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    login_informado VARCHAR(190) NOT NULL DEFAULT '',
    usuario_id BIGINT UNSIGNED NULL,
    sucesso TINYINT(1) NOT NULL DEFAULT 0,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_data (login_informado, criado_em),
    KEY idx_usuario_data (usuario_id, criado_em),
    KEY idx_sucesso_data (sucesso, criado_em),
    CONSTRAINT fk_login_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auditoria_sistema (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NULL,
    modulo VARCHAR(50) NOT NULL,
    acao VARCHAR(80) NOT NULL,
    registro_id BIGINT UNSIGNED NULL,
    detalhes TEXT NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_auditoria_data (criado_em),
    KEY idx_auditoria_usuario (usuario_id, criado_em),
    KEY idx_auditoria_modulo (modulo, criado_em),
    KEY idx_auditoria_registro (registro_id),
    CONSTRAINT fk_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
