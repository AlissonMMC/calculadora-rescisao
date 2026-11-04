CREATE TABLE IF NOT EXISTS empresas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    razao_social VARCHAR(180) NOT NULL,
    cnpj CHAR(14) NOT NULL,
    endereco VARCHAR(500) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_empresas_cnpj (cnpj),
    KEY idx_empresas_ativo_razao (ativo, razao_social)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
