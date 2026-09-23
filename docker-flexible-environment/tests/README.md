# Testes da infraestrutura Docker

`fixtures/` contém somente entradas falsas e determinísticas usadas pelos testes de
`validate-shared-secrets.sh`. Elas exercitam três comportamentos:

- um contrato válido, para provar o happy path;
- uma senha curta, que deve ser rejeitada;
- valores entre aspas tentando contornar a política de usuário/senha, que também devem ser
  rejeitados.

Esses arquivos não configuram nenhuma loja, não são lidos pelo compose e não contêm credenciais
reais. O sufixo `.env.example` deixa explícito que são test data. Arquivos ativos existem apenas
em `deploy/secrets/<site>.env`, fora do Git.
