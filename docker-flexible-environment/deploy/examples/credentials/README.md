# Contratos de credenciais dos ambientes compartilhados

Estes arquivos são apenas contratos de nomes, sem valores reais. Há um contrato explícito por
site para deixar visível que cada país usa um seller de teste próprio. Os arquivos ativos ficam
exclusivamente em `deploy/secrets/<site>.env`, diretório ignorado integralmente pelo Git.

Sites suportados: `mla`, `mlb`, `mlc`, `mlm`, `mco`, `mlu` e `mpe`.

Para criar um arquivo real na instância compartilhada:

```bash
cd /home/ubuntu/woo-e2e-poc/docker-flexible-environment/deploy
install -d -m 700 secrets
umask 077
cp examples/credentials/mlb.env.example secrets/mlb.env
nano secrets/mlb.env
chmod 600 secrets/mlb.env
./validate-shared-secrets.sh secrets/mlb.env
```

Nunca preencha os arquivos em `examples/credentials/`. A validação deve ser executada no arquivo
real dentro de `secrets/`, e sua saída nunca mostra os valores.
