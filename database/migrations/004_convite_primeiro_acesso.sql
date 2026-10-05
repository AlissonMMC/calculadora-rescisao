-- Migração 004 — convite de primeiro acesso e definição de senha
-- Execute no banco de TESTE primeiro, com calculadora_rescisao_teste selecionado no phpMyAdmin.
-- Em produção, execute a mesma estrutura no banco calculadora_rescisao.

ALTER TABLE usuarios
    ADD COLUMN senha_definida TINYINT(1) NOT NULL DEFAULT 1 AFTER senha_hash;

UPDATE usuarios
SET senha_definida = 1
WHERE senha_definida IS NULL;

CREATE TABLE IF NOT EXISTS convites_usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expira_em DATETIME NOT NULL,
    usado_em DATETIME NULL,
    criado_por BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_convite_token_hash (token_hash),
    KEY idx_convite_usuario (usuario_id, criado_em),
    KEY idx_convite_expiracao (expira_em),
    CONSTRAINT fk_convite_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_convite_criador
        FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
