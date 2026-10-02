-- INSTALAÇÃO NOVA — Folha de Cálculo / Calculadora de Rescisão v3
CREATE DATABASE IF NOT EXISTS calculadora_rescisao CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
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
    ultimo_login DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_login (login),
    KEY idx_usuarios_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- O primeiro administrador deve ser criado pelo procedimento de instalação.
-- Não mantenha credenciais administrativas padrão em produção.

CREATE TABLE IF NOT EXISTS historico_rescisoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NULL,
    ultimo_editor_id BIGINT UNSIGNED NULL,
    conferido_por BIGINT UNSIGNED NULL,
    cobrado_por BIGINT UNSIGNED NULL,
    nome VARCHAR(150) NOT NULL DEFAULT '',
    endereco VARCHAR(255) NOT NULL DEFAULT '',
    total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_adm DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_repasse DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    conferido_em DATETIME NULL,
    cobrado_em DATETIME NULL,
    modo_nome VARCHAR(50) NOT NULL DEFAULT '',
    status VARCHAR(30) NOT NULL DEFAULT 'Rascunho',
    dados_json LONGTEXT NOT NULL,
    PRIMARY KEY (id),
    KEY idx_nome (nome), KEY idx_endereco (endereco), KEY idx_criado_em (criado_em), KEY idx_usuario_id (usuario_id), KEY idx_status (status), KEY idx_atualizado_em (atualizado_em), KEY idx_ultimo_editor (ultimo_editor_id), KEY idx_conferido_por (conferido_por), KEY idx_cobrado_por (cobrado_por)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auditoria_rescisoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    historico_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    acao VARCHAR(40) NOT NULL,
    detalhes TEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_historico (historico_id, criado_em),
    KEY idx_audit_usuario (usuario_id, criado_em),
    CONSTRAINT fk_audit_historico FOREIGN KEY (historico_id) REFERENCES historico_rescisoes(id) ON DELETE CASCADE,
    CONSTRAINT fk_audit_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rascunhos_rescisoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(150) NOT NULL DEFAULT '',
    dados_json LONGTEXT NOT NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rascunho_usuario (usuario_id),
    KEY idx_rascunho_atualizado (atualizado_em),
    CONSTRAINT fk_rascunho_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
