USE calculadora_rescisao;

ALTER TABLE usuarios ADD COLUMN ultimo_login DATETIME NULL;

ALTER TABLE historico_rescisoes
    ADD COLUMN status VARCHAR(30) NOT NULL DEFAULT 'Rascunho',
    ADD COLUMN atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ADD COLUMN ultimo_editor_id BIGINT UNSIGNED NULL,
    ADD COLUMN conferido_em DATETIME NULL,
    ADD COLUMN conferido_por BIGINT UNSIGNED NULL,
    ADD COLUMN cobrado_em DATETIME NULL,
    ADD COLUMN cobrado_por BIGINT UNSIGNED NULL,
    ADD COLUMN total_adm DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN total_repasse DECIMAL(14,2) NOT NULL DEFAULT 0.00;

UPDATE historico_rescisoes SET status = 'Rascunho' WHERE status IS NULL OR status = '';

ALTER TABLE historico_rescisoes
    ADD KEY idx_status (status),
    ADD KEY idx_atualizado_em (atualizado_em),
    ADD KEY idx_ultimo_editor (ultimo_editor_id),
    ADD KEY idx_conferido_por (conferido_por),
    ADD KEY idx_cobrado_por (cobrado_por);

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

-- Mantém registros existentes coerentes: o responsável original é também o último editor.
UPDATE historico_rescisoes SET ultimo_editor_id = usuario_id WHERE ultimo_editor_id IS NULL;
