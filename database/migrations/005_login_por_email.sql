-- Migração 005 — usa o e-mail como usuário de acesso
-- Execute no banco de TESTE primeiro e, após validar, no banco de PRODUÇÃO.

ALTER TABLE usuarios
    MODIFY COLUMN login VARCHAR(190) NOT NULL;

-- Os registros existentes permanecem válidos.
-- Para novos usuários, o campo login será preenchido automaticamente
-- com o mesmo valor informado no campo email.
