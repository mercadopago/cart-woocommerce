# Deploy de Homologação (local → AWS)

> Este fluxo continua sendo o ambiente **pessoal** do desenvolvedor. Para a dupla compartilhada
> staging × homol da PSW-4320, veja [POC compartilhada](#poc-compartilhada-staging--homol-psw-4320).

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

## Ambientes compartilhados staging × homol (PSW-4320)

Esta é a documentação canônica para developers, owner e agentes de IA. A interface pública é o
`Makefile` da raiz; os scripts Node e o Makefile deste diretório são detalhes de implementação.
Execute `make e2e-shared-help` na raiz para listar os comandos suportados.

### Operação normal de uma release: um comando

```bash
SMOOTH_USER=<seu-usuario-de-rede> \
SMOOTH_KNOWN_HOSTS_PATH=/caminho/para/known_hosts-validado \
make e2e-shared-release \
  SITE=MLB PRODUCTION_VERSION=8.9.3
```

Não é necessário baixar ou enviar manualmente a RC. O comando executa, nesta ordem:

1. consulta a instância `big-shared-xlarge` com `smooth get-instances` e confirma que os 14
   domínios staging/homol resolvem somente para o IPv4 atual;
2. lê `RC_VERSION` de `package.json` (ou usa o valor explicitamente informado);
3. gera `./woocommerce-mercadopago.zip` pelo build oficial do repositório;
4. baixa de `downloads.wordpress.org` o ZIP oficial de `PRODUCTION_VERSION` para a cache ignorada
   `e2e/results/artifacts/`;
5. valida versão, estrutura, links, path traversal, quantidade/tamanho descompactado e SHA-256 dos
   dois ZIPs;
6. adquire um único lease exclusivo, inicia a renovação a cada 60 segundos e instala produção em
   `homol` e a RC em `staging`, confirmando via WP-CLI a versão realmente instalada. Se a segunda
   instalação falhar, executa uma transação compensatória que reinstala o ZIP produtivo verificado
   em `staging`; a comparação não começa enquanto o par não estiver consistente;
7. sem liberar esse lease, executa o mesmo recorte em Classic e Blocks nas duas lanes, com timeout
   `standard`, um retry, monitor ao vivo e relatório pareado. Antes de cada checkout, confirma via
   WP-CLI que as versões instaladas nas duas lanes ainda correspondem à RC e produção declaradas.
   Blocks roda mesmo se Classic falhar;
8. cada heartbeat tenta a renovação SSH até três vezes; ownership divergente ou esgotamento das
   tentativas encerra os processos e grava ambas as lanes como `LEASE_LOST`;
9. libera o lease somente depois que os dois checkouts e todos os processos filhos terminarem.

O SHA-256 protege a integridade do mesmo arquivo entre validação, upload e instalação; ele não é
uma assinatura do fornecedor. A autenticidade da baseline depende do download HTTPS sem redirects
do hostname fixo `downloads.wordpress.org`, da validação do header de versão e do trust store TLS da
máquina. Tanto o tamanho recebido quanto o total descompactado e a quantidade de entradas são
limitados; os limites são barreiras de recurso, não prova criptográfica de procedência.

O mapeamento é fixo e não pode ser escolhido por argumento:

| Lane | Conteúdo | Origem |
| --- | --- | --- |
| `staging` | RC em revisão | ZIP criado do checkout local atual |
| `homol` | última versão produtiva | ZIP oficial versionado do WordPress.org |

Exemplo com uma versão diferente da registrada no `package.json`:

```bash
SMOOTH_USER=<seu-usuario-de-rede> make e2e-shared-release \
  SITE=MLB RC_VERSION=8.9.4 PRODUCTION_VERSION=8.9.3
```

`RC_VERSION` precisa coincidir com o header do plugin dentro do ZIP. `PRODUCTION_VERSION` deve ser a
versão realmente publicada que será usada como baseline; não é inferida nem promovida a partir de
staging. Reexecutar o comando com uma nova RC atualiza explicitamente as duas lanes antes de testar,
evitando uma comparação com baseline desconhecida.

### Arquitetura e capacidade

`docker-compose.e2e-poc.yml` define 14 lojas persistentes e independentes: staging e homol para
`mla`, `mlb`, `mlc`, `mlm`, `mco`, `mlu` e `mpe`. Cada loja tem volumes próprios de WordPress e
MariaDB, rotas fixas `/checkout-classic/` e `/checkout-blocks/` e um hostname HTTPS dedicado em
`Caddyfile.e2e`.

O host é a instância ARM64 `big-shared-xlarge` (`i-06d1545936c070469`), `t4g.xlarge` (4 vCPUs,
16 GiB), acessível para automação em `big-shared-xlarge.ppolimpo.io`. O runner mantém um worker
Playwright por lane; aumente o paralelismo somente depois de medir CPU, memória, swap e latência.

O proxy bloqueia publicamente `wp-login.php`, `wp-admin`, `xmlrpc.php` e
`/wp-content/debug.log`, inclusive variantes com `PATH_INFO` como `/wp-login.php/`,
`/xmlrpc.php/` e `/wp-content/debug.log/`. A exceção exata
`/wp-admin/admin-ajax.php` permanece pública porque o frontend Classic do WooCommerce depende dela.
Todo boot compartilhado também desativa o debug/log/display do WordPress e remove um log persistido.
Publicação e administração usam SSH sob identidade individual; WP-CLI que carrega ou ativa o plugin
roda como `www-data`, não como root.

### Acesso individual do time

O owner libera somente a chave pública de cada integrante:

```bash
smooth add-user i-06d1545936c070469 <usuario-de-rede>
```

Cada pessoa usa sua própria identidade e nunca recebe a chave privada do owner:

```bash
export SMOOTH_USER=<usuario-de-rede>
export SMOOTH_PK_PATH=/caminho/para/sua/chave-privada
export SMOOTH_KNOWN_HOSTS_PATH=/caminho/para/known_hosts-validado
chmod 600 "$SMOOTH_PK_PATH"
chmod 600 "$SMOOTH_KNOWN_HOSTS_PATH"

ssh -o StrictHostKeyChecking=yes \
  -o UserKnownHostsFile="$SMOOTH_KNOWN_HOSTS_PATH" \
  -o IdentitiesOnly=yes -i "$SMOOTH_PK_PATH" \
  ubuntu@big-shared-xlarge.ppolimpo.io true
```

Obtenha a linha de host key por um canal controlado pelo owner e confira o fingerprint fora do
canal SSH antes de gravá-la. Não inicialize confiança com `ssh-keyscan` sem essa conferência. A
automação exige que o arquivo seja regular, não vazio, no máximo `1 MiB`, não gravável por
grupo/outros e contenha `big-shared-xlarge.ppolimpo.io`; não há fallback TOFU.

Se a chave estiver no `ssh-agent`, omita `SMOOTH_PK_PATH`. Quando `SMOOTH_PK_PATH` é informado, a
automação aplica `IdentitiesOnly=yes` automaticamente e usa somente essa chave. Toda publicação e
execução adquirem e renovam o mesmo lease remoto; se estiver ocupado, aguarde o owner atual. A
criação publica os metadados do lease de uma só vez, renew troca o timestamp atomicamente e release
move o diretório inteiro antes da remoção. Locks legados incompletos são recuperados sob `flock`;
não existe `force-unlock` público.

### Bootstrap e manutenção do owner

Estes comandos operam Docker e secrets locais ao host, portanto devem ser executados na instância
compartilhada, dentro do checkout do repositório. O fluxo de release da seção anterior, por outro
lado, começa no clone local de cada developer.

```bash
ssh -o IdentitiesOnly=yes -i "$SMOOTH_PK_PATH" \
  ubuntu@big-shared-xlarge.ppolimpo.io
cd /home/ubuntu/woo-e2e-poc
command -v docker flock node npm php
```

O host precisa de Node.js `>=20.19`, `npm` e PHP CLI para os validadores locais do checkout. Os
testes de tooling usam APIs nativas do Node e não exigem instalar Playwright/Appium no host; não
contorne políticas do registry para baixar dependências que só pertencem ao runner macOS.

Da raiz desse checkout remoto:

```bash
make e2e-shared-validate
make e2e-shared-infra-check
make e2e-shared-secrets-check-all
make e2e-shared-infra-seed RC_VERSION=8.9.4 PRODUCTION_VERSION=8.9.3
make e2e-shared-infra-up
make e2e-shared-infra-status
make e2e-shared-infra-live-check
```

`e2e-shared-validate` não publica artefatos nem executa pagamentos. `e2e-shared-infra-up` deve ser
executado depois que DNS, os contratos de credenciais e os artefatos iniciais estiverem resolvidos.
Em um checkout limpo, `e2e-shared-infra-seed` valida/extrai a RC para staging e a produção para
homol antes de criar qualquer container; `shared-up` falha fechado se um dos dois seeds estiver
ausente ou inválido. O seed é somente o bootstrap inicial: depois do provisionamento, atualize cada
lane pelo publisher normal sob lease. `e2e-shared-infra-live-check` exercita as 14 origens reais e
confirma as rotas públicas e os bloqueios exatos/PATH_INFO do Caddy.
Use `make e2e-shared-infra-down` somente em uma janela de manutenção conhecida.

Depois de atualizar o checkout remoto com uma mudança no Dockerfile, entrypoint, Caddy ou wrapper
WP-CLI, execute novamente `make e2e-shared-infra-up`. Esse rebuild aplica o usuário `www-data`,
desativa/remove `debug.log` nos volumes persistentes e recarrega o bloqueio público do Caddy. O
alvo reconstrói cada par WordPress com `--build`; recriar containers sobre imagens antigas não
aplica mudanças de runtime.

Um país está pronto para a release apenas depois de seu par staging/homol passar por secrets check,
health check, TLS, identidade de site e smoke Classic/Blocks. A composição contém os sete países,
mas a presença do serviço não substitui essa validação operacional.

Somente containers compartilhados sincronizam credenciais, domínio, modo de teste e locale em todo
boot. A configuração inicial mantém a moeda alinhada ao país. Lojas locais/pessoais preservam as
escolhas feitas pelo developer no wp-admin e não dependem do catálogo remoto para iniciar.
WordPress.org não
publica catálogos WooCommerce `es_UY` e `es_PE`; essas duas lojas preservam seus locales e usam um
fallback explícito para o catálogo `es_ES`; arquivos e links de idioma ficam sob ownership de
`www-data`. Os demais países usam seus catálogos oficiais:

| Site | Moeda | Locale WooCommerce |
| --- | --- | --- |
| `MLA` | `ARS` | `es_AR` |
| `MLB` | `BRL` | `pt_BR` |
| `MLC` | `CLP` | `es_CL` |
| `MLM` | `MXN` | `es_MX` |
| `MCO` | `COP` | `es_CO` |
| `MLU` | `UYU` | `es_UY` com fallback `es_ES` |
| `MPE` | `PEN` | `es_PE` com fallback `es_ES` |

Depois de injetar ou rotacionar as credenciais **de teste**, o bootstrap limpa e recompõe os caches
de métodos pela API, com até três tentativas para falhas transitórias, e falha fechado se o site ou
a lista de métodos não puderem ser confirmados. Credenciais produtivas não fazem parte desse
contrato. Capabilities ausentes
no seller compartilhado ficam versionadas em `e2e/config/shared-capabilities.json`; hoje o seller
MLB não oferece PIX, portanto esse cenário vira `EXPECTED_SKIP` em vez de uma falsa regressão. Ao
trocar o seller, o owner deve validar a API, atualizar o manifesto e executar
`make e2e-shared-validate` antes de recriar o par.

### Credenciais: criação, rotação e novas chaves

Valores reais vivem apenas no host, em
`docker-flexible-environment/deploy/secrets/<site>.env`. O diretório é `0700`, os arquivos são
`0600` e todo `secrets/` é ignorado pelo Git. Os contratos vazios versionados ficam em
`examples/credentials/` para documentar os nomes exigidos em cada país:

```text
secrets/mla.env  secrets/mlb.env  secrets/mlc.env  secrets/mlm.env
secrets/mco.env  secrets/mlu.env  secrets/mpe.env
```

Para criar um país na instância, dentro de
`/home/ubuntu/woo-e2e-poc/docker-flexible-environment/deploy`:

```bash
install -d -m 700 secrets
umask 077
cp examples/credentials/mlb.env.example secrets/mlb.env
nano secrets/mlb.env
chmod 600 secrets/mlb.env
./validate-shared-secrets.sh secrets/mlb.env
make shared-secrets-check-all
```

Cada arquivo representa um seller de teste do país correspondente e é compartilhado somente entre
staging e homol desse país. Seller e buyer devem ser contas de teste distintas e do mesmo país.
`WP_ADMIN_USER` não pode ser `admin`; `WP_ADMIN_PASSWORD` deve ter pelo menos 20 caracteres.

Esse compartilhamento resulta em sete sellers para 14 lojas. Isolamento estrito entre candidate e
baseline requer dois pares de credenciais por país e uma evolução revisada do contrato/compose;
duplicar o mesmo par em dois arquivos não constitui isolamento.

Para rotacionar sem expor valores:

```bash
umask 077
cp examples/credentials/mlb.env.example secrets/mlb.env.next
nano secrets/mlb.env.next
chmod 600 secrets/mlb.env.next
./validate-shared-secrets.sh secrets/mlb.env.next
mv secrets/mlb.env secrets/mlb.env.previous
mv secrets/mlb.env.next secrets/mlb.env
make e2e-shared-infra-recreate SITE=MLB
```

Faça health/smoke test e só então descarte a cópia anterior pelo processo seguro adotado para
credenciais. Para rollback, restaure `mlb.env.previous` e execute
`make e2e-shared-infra-recreate SITE=MLB` a partir da raiz do checkout remoto.

Uma nova variável exige mudança revisada no repositório:

1. adicionar apenas o nome vazio aos sete contratos em `examples/credentials/`;
2. incluir o nome na allowlist de `validate-shared-secrets.sh`;
3. mapear a variável em `sync-runtime-config.php`, se persistir no WordPress;
4. adicionar cobertura em `test-sync-runtime-config.php`;
5. preencher e validar os sete arquivos reais no host;
6. recriar cada par afetado.

Nunca use `docker compose config` sem `--quiet`, `docker inspect`, `env`, `printenv`, `set -x` ou
argumentos de WP-CLI para diagnosticar valores. Acesso ao Docker equivale a acesso às credenciais.

### Comandos granulares

O fluxo normal é `e2e-shared-release`. Estes alvos existem para diagnóstico ou recuperação:

```bash
# Obter e validar somente o ZIP oficial de produção
make e2e-shared-fetch-production PRODUCTION_VERSION=8.9.3

# Publicações individuais, sempre com versão esperada
make e2e-shared-fetch-production PRODUCTION_VERSION=8.9.3
SMOOTH_USER=<user> make e2e-shared-publish-baseline \
  SITE=MLB PRODUCTION_VERSION=8.9.3 \
  ARTIFACT=./e2e/results/artifacts/woocommerce-mercadopago.8.9.3.zip
SMOOTH_USER=<user> make e2e-shared-publish-rc \
  SITE=MLB RC_VERSION=8.9.4 ARTIFACT=<zip-rc>

# Executar sem republicar
SMOOTH_USER=<user> make e2e-shared-test \
  SITE=MLB CHECKOUT=classic RC_VERSION=8.9.4 PRODUCTION_VERSION=8.9.3
SMOOTH_USER=<user> make e2e-shared-test-both \
  SITE=MLB RC_VERSION=8.9.4 PRODUCTION_VERSION=8.9.3

# Matriz completa recomendada: quatro países concorrentes, Classic + Blocks
SMOOTH_USER=<user> make e2e-shared-test-matrix \
  RC_VERSION=8.9.4 PRODUCTION_VERSION=8.9.3

# Diagnóstico excepcional sem retry
SMOOTH_USER=<user> make e2e-shared-test-no-retries \
  SITE=MLB CHECKOUT=classic RC_VERSION=8.9.4 PRODUCTION_VERSION=8.9.3
```

`e2e-shared-test-matrix` é a operação normal depois que as versões já foram publicadas. Ela mantém
no máximo quatro países ativos, executa Classic e Blocks sequencialmente dentro de cada país e
compara staging e homol em paralelo. A fila inicia pelos países historicamente mais longos para
evitar que MLB ou MPE fiquem sozinhos na cauda. Após o benchmark mostrar falsos negativos em
checkouts válidos, o padrão operacional passou a ser
`standard` com um retry. Um preflight HTTP ou de identidade transitório recebe uma única nova
tentativa antes de invalidar a lane; divergência comprovada de país falha imediatamente.
O perfil `fast` reduz pela metade os orçamentos de timeout do Playwright; use-o somente para
diagnóstico de capacidade, nunca como veredito de release:

```bash
SMOOTH_USER=<user> make e2e-shared-test-matrix \
  TIMEOUT_PROFILE=fast \
  RC_VERSION=8.9.4 PRODUCTION_VERSION=8.9.3
```

É possível limitar a investigação sem editar scripts:

```bash
SMOOTH_USER=<user> make e2e-shared-test-matrix \
  SITES=MLA,MLB MATRIX_CHECKOUT=classic CONCURRENCY=2 \
  RC_VERSION=8.9.4 PRODUCTION_VERSION=8.9.3
```

Benchmark de capacidade em 2026-08-28, com as 14 lojas em `8.9.3` nas duas lanes:

| Execução | Estratégia | Duração |
| --- | --- | ---: |
| Baseline | países sequenciais, timeouts standard | `04:34:18` |
| Primeira matriz | 4 países concorrentes, timeouts fast | `01:03:36` |
| Diagnóstico fast | mesma configuração + países longos primeiro | estimativa `00:55–01:00` |

A primeira matriz reduziu o tempo em aproximadamente 77%. Ela mediu capacidade e estabilidade,
não uma release: staging e homol usavam a mesma versão. Diferenças desse benchmark não provam
regressão de código. Hoje esse uso excepcional deve ser declarado explicitamente:

```bash
SMOOTH_USER=<user> make e2e-shared-test-matrix \
  RC_VERSION=8.9.3 PRODUCTION_VERSION=8.9.3 PARITY_VALIDATION=1
```

Sem `PARITY_VALIDATION=1`, os runners rejeitam versões iguais. Nesse modo, os relatórios registram
`comparisonMode=PARITY_VALIDATION`, `releaseEligible=false` e classificações `PARITY_*`, que não
atribuem causalidade a uma das lanes. O fluxo canônico `e2e-shared-release` não aceita esse modo.
Uma decisão de release exige versões diferentes e `comparisonMode=RELEASE_COMPARISON`.

`e2e-shared-publish-baseline` é propositalmente separado, fixo em homol e aceita somente o caminho
da cache oficial criado por `e2e-shared-fetch-production`; o publisher da RC não aceita flag de
ambiente. `e2e-shared-publish-rc-all` e `e2e-shared-test-all` percorrem a matriz, mas só devem ser
usados quando os sete países estiverem operacionalmente validados.

#### Como a publicação funciona (e por que `SITE` não a restringe)

Os sete países de um ambiente compartilham **uma** lane de artefatos. Cada container monta a raiz
da lane em modo somente-leitura (`./artifacts/<lane>:/e2e-artifacts:ro`) e alcança o plugin por
`current/woocommerce-mercadopago`, um symlink para `releases/<versão>-<sha16>`. Publicar é:

1. validar o ZIP e enviá-lo para `~/.woo-e2e-artifacts/incoming/` no host;
2. conferir o SHA-256 no host e extrair em `releases/<versão>-<sha16>` (reaproveitado se já existir);
3. repontar `current/woocommerce-mercadopago` com `rename(2)` — atômico, sem janela sem plugin;
4. `apache2ctl -k graceful` em cada um dos sete containers;
5. confirmar versão e status `active` via WP-CLI como `www-data` em cada loja.

Consequências operacionais:

- **`SITE` escolhe o país que será testado, não o alcance da publicação.** Publicar a RC muda os
  sete países de staging de uma vez. Isso é intencional: uma release valida a mesma RC em todos.
- **Publicar exige a lane livre.** O publisher toma um lock de escrita da lane
  (`<lock>-publish`) e recusa se qualquer país tiver execução ativa; um runner recusa se houver
  publicação em andamento. Os testes seguem usando o lease por país, então a matriz continua
  rodando quatro países em paralelo.
- **Rollback é repontar.** As releases anteriores continuam em `releases/`; não as apague.
- O passo 4 é obrigatório: a imagem oficial do WordPress habilita opcache e o PHP cacheia symlinks
  resolvidos por `realpath_cache_ttl` (120s). Sem o reload, a troca fica no disco enquanto o Apache
  continua servindo a release antiga.

#### Antes de qualquer release: `make e2e-shared-preflight`

Rode **da sua máquina**, antes de publicar. É somente leitura, não pega lease e não altera nada:

```bash
make e2e-shared-preflight
```

Primeiro, ele usa `smooth get-instances` para obter o IPv4 atual da instância e compara os registros
A dos 14 domínios staging/homol. Depois inspeciona os 14 containers por SSH e responde a única
pergunta que importa — *a publicação vai funcionar?* — listando layout de mount, ponteiro e
permissões de cada lane, a versão instalada em cada uma das 7 lojas e os leases ativos. Se algo
bloquear, ele imprime a pendência **e o comando que resolve**; sai com código 1 nesse caso, então
serve como gate em automação. A etapa DNS também pode ser executada isoladamente:

```bash
make e2e-shared-domain-check
```

Ela não altera DNS. Quando encontra divergência, mostra um `smooth add-domain <instance-id>
<prefixo>` para cada hostname afetado; a correção continua sendo uma decisão explícita do operador.

```
[E2E] Estado das lanes compartilhadas

  staging  | ../releases/8.9.4-1a2b3c4d5e6f7a8b | 2 release(s) | modos 755/755/755
  homol    | ../releases/8.9.3-9f8e7d6c5b4a3210 | 1 release(s) | modos 755/755/755

  staging  | 8.9.4          | 7/7 lojas
  homol    | 8.9.3          | 7/7 lojas

[E2E] Lanes prontas para publicar. Proximo passo:
  SMOOTH_USER=<user> make e2e-shared-release SITE=<SITE> PRODUCTION_VERSION=<X.Y.Z>
```

Uma lane meio publicada aparece como `6/7 lojas`, com os containers divergentes nomeados.

#### Migração para o mount na raiz da lane (uma vez, no checkout remoto)

Pares criados antes dessa mudança montam `artifacts/<lane>/current/woocommerce-mercadopago`
direto. O Docker resolve o symlink de origem no momento do mount, então esses containers ficam
presos a uma release e o publisher recusa com exit 69 (`a lane nao usa o layout de bind-mount
atual`). O preflight detecta esse caso e aponta para:

```bash
git pull
make e2e-shared-infra-migrate
```

`e2e-shared-infra-migrate` encadeia, na ordem obrigatória: `repair-permissions` (lanes antigas são
`0700` e `www-data` é uid 33 dentro do container), `infra-up` (recria os 14 pares com `--build`,
necessário porque o entrypoint é copiado para a imagem) e `infra-status`. Os volumes `wp-*-data`
são preservados: as lojas não são reconfiguradas, o entrypoint apenas reaponta o symlink e
revalida o runtime.

> Para reduzir o risco, dá para migrar um país primeiro —
> `make e2e-shared-infra-repair-permissions && make e2e-shared-infra-recreate SITE=MLB` — e validar
> com um `e2e-shared-publish-rc` isolado antes de recriar os 14. Como a lane é compartilhada, esse
> teste exercita exatamente o mesmo caminho que os outros seis países vão usar.

### Monitor, resultados e decisão

Cada checkout recebe um `run_id` e grava em `e2e/results/dual/<run_id>/`:

- `report.md`: resumo humano, versões por lane e diferenças;
- `comparison.json`: comparação estruturada;
- `staging/` e `homol/`: relatório Playwright, progresso e logs de cada lane.

Cada execução completa também recebe um `matrix_id` e grava
`e2e/results/matrix/<matrix_id>/report.md`, `matrix.json` e um log isolado por combinação. O
monitor da matriz mostra somente contadores das lanes ativas; os relatórios detalhados continuam
nos diretórios `dual/<run_id>/` indicados na tabela final.

O painel mostra somente metadados do teste (arquivo, linha, título e status), nunca erro bruto,
stdout, variáveis, respostas de API ou credenciais. As principais classificações são:

| Classificação | Interpretação |
| --- | --- |
| `OK` | passou nas duas versões |
| `PROBABLE_REGRESSION` | falhou na RC e passou em produção; reexecutar e investigar |
| `COMMON_FAILURE` | falhou nas duas; provável baseline, sandbox ou teste |
| `CANDIDATE_ONLY_PASS` | passou somente na RC |
| `UNSTABLE_CANDIDATE_ONLY` | somente a RC variou entre tentativa e retry |
| `UNSTABLE_BASELINE_ONLY` | somente a baseline variou entre tentativa e retry |
| `UNSTABLE_BOTH` | as duas lanes variaram entre tentativa e retry |
| `SELECTION_DRIFT` / `INVALID` | as lanes não são comparáveis; o relatório é inválido |

No modo excepcional de paridade, `PARITY_OK`, `PARITY_DIVERGENCE`, `PARITY_COMMON_FAILURE` e
`PARITY_INSTABILITY` descrevem somente consistência entre ambientes com o mesmo artefato. Mesmo
quando `Valid comparison: yes`, `Release verdict eligible: no` permanece obrigatório.

Se uma lane não produzir JSON, produzir JSON corrompido ou não selecionar nenhum cenário, o runner
ainda grava `report.md` e `comparison.json`, marca `Valid comparison: no` e identifica `staging`,
`homol` ou ambas como indisponíveis. Ausência de resultado nunca é apresentada como “No differences”.

Uma `PROBABLE_REGRESSION` é sinal de investigação, não causalidade provada. Compare trace,
screenshot e erro das duas lanes antes de abrir um bug da RC. Specs `@serial-store` permanecem
excluídas, inclusive no rerun de falhas, até existir snapshot/restore confiável. O relatório lista
`EXPECTED_SKIP` em uma seção própria de limitações de cobertura. A navegação direta para
`/checkout-classic/` ou `/checkout-blocks/` valida o checkout, mas não cobre a transição carrinho →
checkout; essa lacuna não deve ser interpretada como cenário aprovado.

### Problemas comuns

| Sintoma | Ação |
| --- | --- |
| `SMOOTH_USER ausente ou invalido` | exporte seu usuário individual de rede |
| `SMOOTH_KNOWN_HOSTS_PATH ausente ou invalido` | use o arquivo `known_hosts` validado pelo owner, com permissão `0600` |
| chave recusada | confirme `smooth add-user` e permissão `0600` |
| lease ocupado | já existe publicação/teste para o mesmo país; aguarde a execução ativa ou o TTL |
| `LEASE_LOST` | as tentativas de renovação falharam ou o ownership divergiu; descarte o resultado, restaure SSH/rede e execute novamente |
| versão do ZIP divergente | gere novamente a RC ou corrija a versão informada; não force a publicação |
| versão instalada divergente | a publicação ficou parcial ou a loja foi alterada; publique novamente o par sob lease antes de testar |
| WordPress.org não encontrou a versão | confirme que ela está realmente publicada e use `X.Y.Z` |
| painel em `WAITING` | leia o log da lane; o setup pode ter falhado antes do Playwright |
| `PROBABLE_REGRESSION` isolada | reexecute o comando padrão e compare as duas evidências/retries |
| falha somente no perfil `fast` | o perfil é diagnóstico; confirme com o padrão `standard` antes de classificar |
| alteração de secret não refletiu | execute `shared-recreate` somente para o país rotacionado |
| DNS/URL divergente | corrija configuração/DNS; não use override de host |

Interrupções tratáveis e perda de lease enviam `SIGTERM`, aguardam os processos e usam `SIGKILL`
após um limite; só então tentam liberar o lease e preservam resultados parciais. Um runner morto por
`SIGKILL` ou fechamento abrupto deixa de renovar e depende da recuperação automática pelo TTL.
