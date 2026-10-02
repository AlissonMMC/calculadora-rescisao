# Segurança

## Como reportar uma vulnerabilidade

Não publique credenciais, dados de clientes ou detalhes exploráveis de uma vulnerabilidade em issues públicas.

Para problemas de segurança, entre em contato diretamente com o mantenedor do projeto antes de divulgar a falha publicamente.

## Boas práticas para implantação

- Use HTTPS.
- Mantenha PHP, MySQL/MariaDB e Python atualizados.
- Armazene credenciais fora do código-fonte.
- Não versione arquivos `.env`, dumps do banco ou dados reais.
- Restrinja permissões de escrita do diretório `storage/`.
- Valide arquivos enviados pelo usuário.
- Desative mensagens detalhadas de erro em produção.
- Faça backups periódicos e teste a restauração.
