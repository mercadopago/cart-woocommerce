# Docker Flexible Environment

Ambiente de desenvolvimento local para o plugin WooCommerce Mercado Pago.
Sobe uma loja WooCommerce completa, configurada por pais, com gateways de pagamento
ativos, credenciais de teste e Xdebug pronto para uso.

## Requisitos

| Ferramenta | Instalacao | Notas |
|---|---|---|
| Docker | `brew install --cask docker` | Obrigatorio |
| cloudflared | `brew install cloudflared` | Recomendado para HTTPS tunnel (funciona com VPN, rapido, sem interstitial) |
| ngrok | `brew install ngrok` | Alternativa se cloudflared nao estiver disponivel |

## Quick Start

```bash
# 1. Configure as credenciais MP (uma vez)
cd docker-flexible-environment
cp .env.example .env
# Edite .env com suas credenciais de teste do Mercado Pago

# 2. Sobe a loja
make up                          # Loja brasileira, PHP 7.4, localhost:8080
```

Pronto. Acesse http://localhost:8080/shop (admin: `admin` / `admin`).

## Configuracao inicial (.env)

O arquivo `.env` configura credenciais MP e tunnel. Copie o exemplo e preencha:

```bash
cp .env.example .env
```

| Variavel | Obrigatoria | Descricao |
|---|---|---|
| `MP_PUBLIC_KEY_TEST` | Sim (para pagamentos) | Public key de teste do MP |
| `MP_ACCESS_TOKEN_TEST` | Sim (para pagamentos) | Access token de teste do MP |
| `MP_PUBLIC_KEY_PROD` | Nao | Public key de producao |
| `MP_ACCESS_TOKEN_PROD` | Nao | Access token de producao |
| `MP_CUSTOM_DOMAIN` | Sim (para webhooks) | URL do tunnel para notification_url |
| `NGROK_DOMAIN` | Nao | Dominio fixo do ngrok (se usar ngrok) |

As credenciais sao injetadas automaticamente no WordPress via `setup-store.sh`
durante o `make up` / `make reset`. Nao e necessario configurar manualmente pelo
painel admin.

## Comandos

### Ciclo de vida

```bash
make up                          # Sobe o ambiente (preserva dados se ja existir)
make up SITE=mla                 # Loja argentina
make up SITE=mlb PHP=8.4         # Brasil com PHP 8.4
make down                        # Para o container (dados preservados)
make reset                       # Destroi tudo e recria do zero
make reset SITE=mco              # Recria como loja colombiana
```

### Paises disponiveis

| Codigo | Pais | Moeda | Imposto | Gateways | Particularidades |
|---|---|---|---|---|---|
| `mlb` | Brasil | BRL | ICMS 18% | Custom, PIX, Ticket, Basic, Credits | Plugin CPF/CNPJ, locale pt_BR |
| `mla` | Argentina | ARS | IVA 21% | Custom, Ticket, Basic, Credits | locale es_AR |
| `mlm` | Mexico | MXN | IVA 16% | Custom, Ticket, Basic, Credits | locale es_MX |
| `mco` | Colombia | COP | IVA 19% | Custom, Ticket, Basic, PSE | locale es_CO |
| `mlc` | Chile | CLP | IVA 19% | Custom, Basic | locale es_CL |
| `mlu` | Uruguai | UYU | IVA 22% | Custom, Ticket, Basic | locale es_UY |
| `mpe` | Peru | PEN | IGV 18% | Custom, Ticket, Basic, Yape | locale es_PE |

Cada pais recebe: moeda configurada, impostos, zona de frete com flat rate,
5 produtos com nomes e precos locais, locale do WordPress, gateways habilitados
e plugins especificos (ex: CPF/CNPJ para Brasil).

### HTTPS e Webhooks

O Mercado Pago envia notificacoes (IPN/Webhook) via HTTPS.
O tunnel cria uma URL publica que aponta para sua maquina local.

```bash
make tunnel                      # Auto-detecta: cloudflared > ngrok
make tunnel TUNNEL=ngrok         # Forca ngrok
make tunnel-stop                 # Encerra e restaura localhost
make tunnel-url                  # Mostra a URL ativa
```

**Cloudflared vs ngrok:**

| Aspecto | cloudflared (recomendado) | ngrok |
|---|---|---|
| VPN corporativa | Funciona (outbound HTTPS/443) | Conflita com rotas VPN |
| Velocidade mobile | Rapido (Cloudflare CDN global) | Lento no free tier |
| Interstitial | Nenhuma | "Visit Site" no browser |
| URL fixa (free) | Nao (Quick Tunnel gera random) | Sim (1 dominio por conta) |
| Conta obrigatoria | Nao | Sim |

**VPN bloqueia o tunnel?**

A VPN corporativa pode bloquear o DNS de dominios como `trycloudflare.com` e
`ngrok-free.app`. O `make tunnel` resolve isso automaticamente no laptop
(adiciona o IP ao `/etc/hosts` via sudo). Para dispositivos moveis:

**iPhone:** Ajustes > Wi-Fi > (i) na rede > Configurar DNS > Manual >
Adicionar `1.1.1.1` e `8.8.8.8` como servidores DNS

**Android:** Configuracoes > Rede > DNS Privado >
Nome do host: `one.one.one.one` (Cloudflare) ou `dns.google` (Google)

**Para URL fixa com ngrok:**
```bash
# 1. Acesse https://dashboard.ngrok.com/domains → "New Domain"
# 2. Configure no .env:
NGROK_DOMAIN=seu-dominio.ngrok-free.app

# 3. Forca ngrok:
make tunnel TUNNEL=ngrok
```

### Checkout

O plugin suporta dois modos de checkout. Ambos funcionam no Docker:

```bash
make checkout-classic            # Shortcode [woocommerce_checkout] (vanilla JS + jQuery)
make checkout-blocks             # WC Blocks (React) — insere inner blocks completos
```

O `make checkout-blocks` insere o conteudo completo com todos os inner blocks
(contact-information, shipping-address, payment, etc.). O bloco self-closing
`<!-- wp:woocommerce/checkout /-->` nao funciona — o WC render callback precisa
dos inner blocks para renderizar o formulario.

### Temas

O tema pode ser definido ao subir o ambiente ou trocado em tempo real:

```bash
# Definir ao criar o ambiente (reset automatico se mudar)
make up THEME=kadence                    # Sobe com Kadence
make up SITE=mla THEME=oceanwp           # Argentina com OceanWP

# Trocar em tempo real (sem reset)
make theme THEME=astra                   # Troca para Astra
make theme THEME=storefront              # Volta ao padrao
```

**Temas pre-instalados** (disponiveis instantaneamente, sem download):

| Slug | Nome | Destaque |
|---|---|---|
| `storefront` | Storefront | WC oficial, default, compatibilidade garantida |
| `astra` | Astra | Mais popular do mundo, rapido, starter templates |
| `kadence` | Kadence | Gutenberg-native, deep WC customization |
| `oceanwp` | OceanWP | Feature-rich: quick view, wishlist, floating cart |
| `blocksy` | Blocksy | Block-editor-first, excelente WC free tier |
| `generatepress` | GeneratePress | Ultra-leve (<10KB), ideal para conexoes lentas |
| `neve` | Neve | Leve, rapido, bom para lojas simples |
| `hestia` | Hestia | One-page, bom para lojas pequenas |

**Top 5 por pais** (todos pre-instalados):

| Pais | Top 5 temas (em ordem de popularidade) |
|---|---|
| Brasil | astra, kadence, oceanwp, blocksy, neve |
| Argentina | astra, generatepress, kadence, oceanwp, storefront |
| Mexico | astra, kadence, oceanwp, blocksy, generatepress |
| Colombia | astra, oceanwp, kadence, generatepress, neve |
| Chile | astra, kadence, blocksy, generatepress, storefront |
| Uruguai | astra, generatepress, kadence, oceanwp, hestia |
| Peru | astra, oceanwp, kadence, generatepress, neve |

### Debug (Xdebug + VS Code)

O Xdebug **ja vem ativo** no container (mode=debug). O dev so precisa
iniciar o listener no VS Code.

```bash
make debug-status                # Mostra estado atual do Xdebug
make debug-off                   # Desativa (se precisar de performance maxima)
make debug-on                    # Reativa
```

**Setup do VS Code (obrigatorio, uma unica vez):**

O arquivo `.vscode/launch.json` esta no `.gitignore` do projeto, entao cada
desenvolvedor precisa criar o seu. Crie o arquivo `.vscode/launch.json` na raiz
do plugin com o seguinte conteudo:

```json
{
  "version": "0.2.0",
  "configurations": [
    {
      "name": "Listen for Xdebug (Docker)",
      "type": "php",
      "request": "launch",
      "port": 9003,
      "pathMappings": {
        "/woocommerce-mercadopago": "${workspaceFolder}"
      },
      "xdebugSettings": {
        "max_children": 128,
        "max_data": 1024,
        "max_depth": 5,
        "show_hidden": 1
      }
    }
  ]
}
```

O `pathMappings` conecta o caminho dentro do container (`/woocommerce-mercadopago`)
com o diretorio no editor (`${workspaceFolder}`). Sem isso, o VS Code nao encontra
os arquivos quando o Xdebug pausa.

**Fluxo:**

1. VS Code → Run and Debug (Ctrl+Shift+D) → **"Listen for Xdebug (Docker)"** → F5
2. Coloque breakpoints em qualquer arquivo PHP do plugin
3. Acesse a loja no browser → VS Code pausa no breakpoint

Nao e necessario rodar `make debug-on` — o Xdebug ja inicia ativo.
O Xdebug so conecta quando um IDE esta escutando; quando nao ha IDE,
a tentativa de conexao falha silenciosamente sem impacto perceptivel.

### Testes E2E

Duas formas de rodar os testes E2E (Playwright):

**Do host** (requer Playwright instalado localmente):
```bash
make e2e                         # Roda testes do site ativo
make e2e-reset                   # Reset + roda testes
make e2e-all                     # Reset + roda para TODOS os paises
```

**De dentro do container** (usa Chromium do container):
```bash
make e2e-docker TESTS=tests/health       # Health tests
make e2e-docker TESTS=tests/mlb/pix      # PIX tests
make e2e-docker                          # Todos os testes
```

O `e2e-docker` automaticamente:
- Seta `siteurl` para `host.docker.internal` (Chromium no container nao resolve `localhost`)
- Usa Chromium completo (nao headless_shell) com Secure Context para WC Blocks
- Restaura `siteurl` para `localhost` ao terminar

### Build

```bash
make build                       # npm install + composer install + npm run build (dentro do container)
```

### Utilitarios

```bash
make info                        # PHP, WC, site, tunnel, git branch, Xdebug status
make git-info                    # Git remote, branch e status
make shell                       # Shell interativo no container
make logs                        # Logs do container (Apache + MySQL + WP)
make status                      # Lista plugins instalados
```

## Como funciona

### Arquitetura

```
Host (sua maquina)                          Container Docker
+------------------------------+            +--------------------------------+
| woocommerce-mercadopago/     | ← volume → | /woocommerce-mercadopago/      |
| (git repo, voce edita aqui)  |   mount    |                                |
|                               |            | /var/www/html/wp-content/      |
|                               |            |   plugins/wc-mercadopago/ →    |
|                               |            |     symlink p/ /wc-mp/         |
+------------------------------+            +--------------------------------+
                                            | WordPress + WooCommerce 10.x   |
                                            | MariaDB (single container)     |
                                            | PHP 7.4-8.4 + Xdebug 3        |
                                            | Node 20 + Composer             |
                                            | Chromium (para E2E)            |
                                            +--------------------------------+
```

### Volume mount

O diretorio do plugin no host e montado no container. Qualquer edicao no host
(no seu editor) reflete **instantaneamente** na loja — sem rebuild, sem restart.

### Persistencia de dados

| Comando | Volume WP | Volume DB | Setup |
|---|---|---|---|
| `make down` + `make up` | Preservado | Preservado | Pulado (~5s) |
| `make reset` | Destruido | Destruido | Completo (~30s) |

Os dados da loja (produtos, configs, plugins, orders) sobrevivem entre
`make down` / `make up`. Somente `make reset` limpa tudo e reconfigura do zero.

### Auto-deteccao de pais/PHP

O Makefile grava a config ativa em `.current-site`. Se voce roda `make up SITE=mla`
quando o ambiente era `mlb`, ele detecta a mudanca e faz reset automatico.

### O que o setup-store.sh configura automaticamente

Para cada pais, o script configura:

1. Moeda, locale, separadores de decimal/milhar
2. Endereco da loja (pais, estado, cidade, CEP)
3. Taxas de imposto (ICMS, IVA, IGV)
4. Zona de frete com flat rate
5. 5 produtos com nomes e precos locais
6. Gateways de pagamento habilitados (por pais)
7. Credenciais MP (via env vars do `.env`)
8. Custom domain para notification_url (webhooks)
9. Modo de teste ativado
10. Plugins especificos (ex: CPF/CNPJ para Brasil)

### HTTPS (reverse proxy)

O `wp-config.php` inclui deteccao automatica de proxy reverso.
Quando o tunnel esta ativo, o WordPress gera URLs `https://` para todos os assets
(CSS, JS, imagens) — sem mixed content.

O `make tunnel` tambem adiciona o dominio gerado ao `/etc/hosts` do laptop
(via sudo) para contornar bloqueios de DNS da VPN corporativa.

### WC Blocks checkout no Docker

O WC Blocks checkout requer:

1. **Conteudo com inner blocks** — o bloco self-closing `<!-- wp:woocommerce/checkout /-->`
   nao funciona. O `make checkout-blocks` insere a estrutura completa.
2. **Secure Context** — `crypto.randomUUID()` (usado pelo WC Blocks JS) so funciona
   em contextos seguros (HTTPS ou localhost). Dentro do Docker, o Chromium acessa via
   `host.docker.internal` que nao e Secure Context por padrao. O `playwright.config.js`
   usa `--unsafely-treat-insecure-origin-as-secure` com Chromium completo (nao headless_shell)
   para resolver isso.
3. **Chromium completo** — o `headless_shell` do Playwright ignora o flag de Secure Context.
   O `e2e-docker` passa `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` para usar o Chromium full.

## Fluxo de trabalho do desenvolvedor

```bash
# 1. Sobe o ambiente (uma vez, ou apos make down)
cd docker-flexible-environment
make up SITE=mlb

# 2. Desenvolve (no host, nao no container)
cd ..
git checkout -b feature/PSW-XXXX
# ... edita arquivos PHP/JS ...

# 3. Se editou JS: rebuild
cd docker-flexible-environment && make build

# 4. Testa no browser
# http://localhost:8080/shop

# 5. Se precisa debugar PHP
# VS Code → Run and Debug → "Listen for Xdebug (Docker)" → F5
# Coloca breakpoint → refresh no browser

# 6. Se precisa testar em outro checkout mode
make checkout-classic            # ou make checkout-blocks

# 7. Se precisa de webhooks (notificacoes MP)
make tunnel
# Configura URL no painel do Mercado Pago

# 8. Se precisa testar no mobile
# Configura DNS do celular para 1.1.1.1 (ver secao VPN acima)
# Acessa a URL do tunnel no browser do celular

# 9. Roda testes E2E
make e2e                         # Do host
make e2e-docker TESTS=tests/mlb  # Do container

# 10. Commita e pusha (do host)
cd ..
git add . && git commit -m "feature(PSW-XXXX): descricao"
git push origin feature/PSW-XXXX

# 11. Encerra o dia
cd docker-flexible-environment
make tunnel-stop
make down                        # Dados preservados para amanha
```

## Estrutura dos arquivos

```
docker-flexible-environment/
├── Dockerfile           Imagem: WP + MariaDB + Node + Composer + Xdebug + Chromium
├── docker-compose.yml   Orquestracao: volumes, portas, env vars, security opts
├── entrypoint.sh        Boot: MySQL → WordPress → WooCommerce → Plugin → Pais
├── setup-store.sh       Config por pais: moeda, imposto, frete, produtos, gateways, credenciais
├── Makefile             Interface do desenvolvedor (todos os make targets)
├── .env.example         Template para credenciais MP e config de tunnel
├── .gitignore           Ignora .env, .current-site, arquivos de tunnel
└── README.md            Este arquivo
```

## Troubleshooting

**Container nao sobe:**
```bash
make logs                        # Ver o que falhou
make reset                       # Recriar do zero
```

**Loja retorna 404:**
```bash
docker exec mp-wc-dev wp --allow-root rewrite flush --hard
```

**CSS nao carrega via HTTPS tunnel:**
Verifique se o `wp-config.php` tem o snippet de reverse proxy detection.
Um `make reset` regenera o arquivo corretamente.

**Blocks checkout vazio (sem formulario):**
O conteudo da pagina de checkout pode estar com o bloco self-closing.
Rode `make checkout-blocks` para inserir a estrutura completa com inner blocks.

**Xdebug nao para no breakpoint:**
1. Confirme que o VS Code esta escutando: Run and Debug → "Listen for Xdebug (Docker)" → F5
2. Verifique: `make debug-status` (deve mostrar `Mode: debug`)
3. O breakpoint deve estar em arquivo PHP do plugin, nao do WordPress core
4. Teste a conexao: `docker exec mp-wc-dev bash -c "timeout 2 bash -c 'echo > /dev/tcp/host.docker.internal/9003' && echo OK || echo FAIL"`

**Tunnel bloqueado pela VPN:**
O `make tunnel` adiciona automaticamente o IP ao `/etc/hosts`.
Se falhar, rode manualmente o comando que o Makefile sugere.
Para mobile, configure DNS para `1.1.1.1` no dispositivo.

**Porta 8080 ocupada:**
```bash
make up PORT=9090                # Usa outra porta
```

**Gateways nao aparecem no checkout:**
Verifique se as credenciais estao no `.env` e rode `make reset` para reconfigurar.
O `setup-store.sh` ativa automaticamente os gateways corretos para cada pais.
