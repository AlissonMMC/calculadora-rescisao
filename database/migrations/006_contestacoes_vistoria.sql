-- Migração 006: módulo independente de contestação de vistoria.
-- Execute no banco de TESTE antes de homologar.
CREATE TABLE IF NOT EXISTS contestacoes_vistoria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    nome_inquilino VARCHAR(180) NOT NULL,
    cpf_inquilino VARCHAR(14) NOT NULL DEFAULT '',
    endereco_locacao VARCHAR(255) NOT NULL,
    data_contestacao DATE NOT NULL,
    numero_contrato VARCHAR(80) NOT NULL DEFAULT '',
    fianca VARCHAR(180) NOT NULL DEFAULT '',
    comodos_json LONGTEXT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contestacao_usuario (usuario_id, criado_em),
    KEY idx_contestacao_inquilino (nome_inquilino),
    CONSTRAINT fk_contestacao_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contestacoes_vistoria_fotos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    contestacao_id BIGINT UNSIGNED NOT NULL,
    comodo_indice SMALLINT UNSIGNED NOT NULL,
    lado ENUM('entrada','saida') NOT NULL,
    nome_arquivo VARCHAR(255) NOT NULL,
    mime_type VARCHAR(40) NOT NULL,
    conteudo MEDIUMBLOB NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contestacao_fotos (contestacao_id, comodo_indice, lado),
    CONSTRAINT fk_contestacao_fotos FOREIGN KEY (contestacao_id) REFERENCES contestacoes_vistoria(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
