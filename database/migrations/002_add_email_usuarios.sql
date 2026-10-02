-- Migração 002 — e-mail de acesso dos usuários
USE calculadora_rescisao;

ALTER TABLE usuarios
    ADD COLUMN email VARCHAR(190) NULL AFTER login;

CREATE UNIQUE INDEX uq_usuarios_email ON usuarios (email);

-- O e-mail permanece opcional para usuários existentes.
-- Depois da migração, cada usuário pode cadastrar seu e-mail em "Gerenciar perfil".
