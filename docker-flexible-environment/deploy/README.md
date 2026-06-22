# Deploy de Homologação (local → AWS)

Publica o plugin WooCommerce Mercado Pago **do seu estado local** (working tree,
inclusive mudanças não-commitadas) numa instância AWS de homologação (gerenciada
pelo `smooth`), rodando **uma loja por país** simultaneamente, cada uma num
subdomínio HTTPS público — pronto pra receber webhook/IPN do Mercado Pago.

Reusa o **mesmo** `Dockerfile` / `entrypoint.sh` / `setup-store.sh` do ambiente
local (`docker-flexible-environment`), então o que funciona local funciona em
homologação (paridade total).

**É por dev:** os domínios usam o **seu** usuário de rede (`$SMOOTH_USER`) →
`https://<seu-usuario>-<site>.ppolimpo.io`, e cada um aponta a **sua** instância.

> Atalho via Claude Code: a skill **`/woo-homolog`** chama exatamente estes comandos.

## Arquitetura

```
seu laptop                       sua instância AWS (smooth / ppolimpo.io)
  plugin local  --tar-over-ssh-->  ~/woo-homolog/woocommerce-mercadopago (volume)
                                   ┌──────── Caddy (80/443, TLS Let's Encrypt) ────────┐
  <user>-mlb.ppolimpo.io ────────► reverse_proxy ► wp-mlb  (WordPress+WC+plugin, MLB)
  <user>-mla.ppolimpo.io ────────► reverse_proxy ► wp-mla  (MLA)
  <user>-mlm.ppolimpo.io ────────► reverse_proxy ► wp-mlm  (MLM)
```

- **TLS**: o Caddy emite e **renova** certificados Let's Encrypt sozinho.
- **Webhook**: cada loja nasce com `MP_CUSTOM_DOMAIN=https://<user>-<site>.ppolimpo.io`.
- **Domínios**: criados via `smooth add-domain` apontando pro IP da sua instância.
- **Transferência**: `tar-over-ssh` (o `rsync` do macOS falha com essas instâncias).

## Pré-requisitos (no seu laptop)

- **`smooth` configurado** (`SMOOTH_USER` + chave no ssh-agent) — é o pré-requisito
  central: fornece o seu usuário de rede e lista as suas instâncias.
- chave `~/.ssh/id_aws` (acesso `ubuntu@<inst>.ppolimpo.io`)
- credenciais de teste do MP no `../.env` (as mesmas do ambiente local)
- **sua instância** apontada uma vez:
  ```bash
  cp .deploy.env.example .deploy.env
  smooth get-my-instances          # veja as suas
  echo 'HOMOLOG_INSTANCE=<nome>' >> .deploy.env
  ```

> Se você não definir `HOMOLOG_INSTANCE`, o `deploy.sh` lista as suas instâncias e
> orienta. Acesso a instâncias **novas** depende do time liberar a chave na AWS (o
> `smooth add-user` falha em instância recém-criada) — reaproveite uma existente
> sua ou peça a liberação.

## Uso

```bash
cd docker-flexible-environment/deploy

make publish SITE=mlb     # sobe/atualiza a loja Brasil  -> https://<user>-mlb.ppolimpo.io
make publish SITE=mla     # idem Argentina
# sites: mlb mla mlm mco mlc mlu mpe

make sync                          # re-envia o CÓDIGO local e reinicia as lojas no ar
make config KEY=MP_SDK_ENV VAL=beta # define uma constante do wp-config em TODAS as lojas
make status                        # estado + URLs
make logs SITE=mlb                 # logs
make shell SITE=mlb                # shell no container
make down SITE=mlb                 # para (mantém dados)
make destroy SITE=mlb              # remove loja + dados (irreversível)
```

Login do wp-admin: `admin` / `admin`. (Sem `make`: `./deploy.sh <comando> [args]`.)

## Qual comando para qual mudança (a regra mental)

| Você mudou… | Comando | Precisa build? |
|---|---|---|
| **Arquivo PHP** do plugin | `make sync` | ❌ reflete na hora (volume); envia até não-commitado |
| **JS-fonte** (`assets/js/*.js`) | `npm run build` *(local)* → `make sync` | ✅ são os `.min.js` que são enviados |
| **Constante/option** (SDK JS, test mode, …) | `make config KEY=.. VAL=..` | ❌ não toca em código |
| **Loja/país** novo ou trocar versão | `make publish SITE=<x>` | (build da imagem) |

> **Resumo:** arquivo do plugin → `sync` · configuração → `config` · loja → `publish`.

### Exemplo: apontar o SDK JS pra beta em todos os ambientes

O plugin escolhe a URL do SDK JS por uma constante (`MP_SDK_ENV`, lida em
`src/Helpers/Url.php` → `prod` | `beta` | `gama`). É **configuração**, não código:

```bash
make config KEY=MP_SDK_ENV VAL=beta    # aplica em todas as lojas ativas
# voltar:  make config KEY=MP_SDK_ENV VAL=prod
```

Se você **editar a própria URL** no `Url.php` (código), aí é `make sync`.

## Configuração (env vars / `.deploy.env`)

| Var | Default | Para quê |
|-----|---------|----------|
| `HOMOLOG_INSTANCE` | *(obrigatório)* | sua instância no smooth (veja `smooth get-my-instances`) |
| `HOMOLOG_PREFIX` | `$SMOOTH_USER` | prefixo do subdomínio (`<prefix>-<site>`) |
| `HOMOLOG_BASE_DOMAIN` | `ppolimpo.io` | domínio base do smooth |
| `HOMOLOG_SSH_KEY` | `~/.ssh/id_aws` | chave de acesso à instância |
| `PHP_VERSION` | `7.4` | versão do PHP do container |

## Troubleshooting

- **Mudei o código e o ambiente continua igual** → você editou um arquivo
  (PHP/JS); rode `make sync`. O `config` só leva constante, não código. Se for
  JS-fonte, `npm run build` antes.
- **`Permission denied (publickey)` / `smooth add-user` dá "exit status 1"** →
  instância nova sem a chave liberada na AWS; reaproveite uma instância existente
  sua (`HOMOLOG_INSTANCE`) ou peça a liberação ao responsável.
- **`rsync ... unexpected end of file`** → esperado; a automação usa `tar-over-ssh`
  de propósito (o `rsync` do macOS/openrsync falha no handshake com essas instâncias).
- **Cert do domínio inválido** → o Caddy renova sozinho; se um subdomínio novo não
  pegou cert, `make publish SITE=<x>` (re-roda `add-domain` + reinicia o Caddy).

## Notas

- `node_modules`, `.git` e logs são excluídos do envio; `vendor/` (deps PHP) e os
  assets buildados vão junto.
- **Credenciais por país**: hoje as lojas usam o mesmo `.env`. Para credenciais
  distintas por país, evolua o compose para `env_file` por serviço.
- Constantes aplicadas via `config` persistem no `wp-config.php` (volume), mas
  somem em `destroy`/recriação — re-rode o `config`. Loja nova nasce em `prod`.
- Várias lojas rodam na mesma instância; dimensione conforme o uso (poucos acessos
  cabem numa t2.medium/swap).
