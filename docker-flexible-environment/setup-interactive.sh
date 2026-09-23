#!/usr/bin/env bash

set -euo pipefail

if [ -n "${MP_DEV_SCRIPT_DIR:-}" ]; then
    SCRIPT_DIR="$(cd "$MP_DEV_SCRIPT_DIR" && pwd)"
else
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
fi
ENV_FILE="${SCRIPT_DIR}/.env"
PORT_VALUE="${PORT:-8080}"
CHOICE=""

show_banner() {
    local cyan=''
    local blue=''
    local bold=''
    local reset=''

    if [ -t 1 ] && [ "${TERM:-dumb}" != 'dumb' ] && [ -z "${NO_COLOR:-}" ]; then
        cyan=$'\033[36m'
        blue=$'\033[34m'
        bold=$'\033[1m'
        reset=$'\033[0m'
    fi

    echo ''
    printf '%s' "$cyan"
    echo '                 ##         .'
    echo '           ## ## ##        =='
    echo '        ## ## ## ## ##    ==='
    printf '%s' "$blue"
    echo '    /""""""""""""""""\___/ ==='
    echo '   {      MP DEV STORE            /'
    echo '    \____________________________/'
    printf '%s%s' "$bold" "$cyan"
    echo '       WooCommerce + Mercado Pago'
    printf '%s' "$reset"
    echo ''
}

show_store_ready_art() {
    local green=''
    local yellow=''
    local bold=''
    local reset=''

    if [ -t 1 ] && [ "${TERM:-dumb}" != 'dumb' ] && [ -z "${NO_COLOR:-}" ]; then
        green=$'\033[32m'
        yellow=$'\033[33m'
        bold=$'\033[1m'
        reset=$'\033[0m'
    fi

    echo ''
    printf '%s' "$yellow"
    echo '                 ________________________'
    echo '                /_______________________/|'
    echo '               /  MP DEV STORE         / |'
    echo '              /_______________________/  |'
    printf '%s' "$green"
    echo '              |  ┌───┐  ┌───┐  OPEN  |   |'
    echo '              |  │   │  │   │        |   |'
    echo '              |  └───┘  └───┘  ┌──┐  |  /'
    echo '              |________________│  │__|/'
    echo '                               └──┘'
    printf '%s%s' "$bold" "$green"
    echo '                         Loja pronta!'
    printf '%s' "$reset"
}

read_choice() {
    printf '> '
    IFS= read -r CHOICE || exit 0
}

read_env_value() {
    local name="$1"
    local line
    local value

    if value=$(printenv "$name" 2>/dev/null); then
        printf '%s' "$value"
        return
    fi

    line=$(grep -E "^${name}=" "$ENV_FILE" 2>/dev/null | tail -n 1 || true)
    [ -n "$line" ] || return 0
    value="${line#*=}"
    value="${value%$'\r'}"
    case "$value" in
        \"*\") value="${value#\"}"; value="${value%\"}" ;;
        \'*\') value="${value#\'}"; value="${value%\'}" ;;
    esac
    printf '%s' "$value"
}

require_prerequisites() {
    if [ ! -f "$ENV_FILE" ]; then
        echo '[mp-dev] ERROR: copie .env.example para .env antes de continuar.' >&2
        exit 1
    fi
    if ! command -v docker >/dev/null 2>&1 || ! docker info >/dev/null 2>&1; then
        echo '[mp-dev] ERROR: abra o Docker Desktop e tente novamente.' >&2
        exit 1
    fi
    if [[ ! "$PORT_VALUE" =~ ^[1-9][0-9]{0,4}$ ]] || [ "$PORT_VALUE" -gt 65535 ]; then
        echo '[mp-dev] ERROR: PORT deve estar entre 1 e 65535.' >&2
        exit 1
    fi
}

site_label() {
    printf '%s' "$1" | tr '[:lower:]' '[:upper:]'
}

detect_current_store() {
    CURRENT_SITE=''
    CURRENT_PHP=''
    CURRENT_THEME=''
    CURRENT_WORDPRESS=''
    CURRENT_WORDPRESS_SIGNATURE=''
    CURRENT_WORDPRESS_INSTALLED=''
    CURRENT_STORE_MARKER_UNKNOWN=false

    if [ -f "${SCRIPT_DIR}/.current-site" ]; then
        local signature
        signature=$(<"${SCRIPT_DIR}/.current-site")
        if [[ "$signature" =~ ^(mlb|mla|mlm|mco|mlc|mlu|mpe)-php(7\.4|8\.0|8\.1|8\.2|8\.3|8\.4)-(storefront|astra|kadence|oceanwp|blocksy|generatepress|neve|hestia)$ ]]; then
            CURRENT_SITE="${BASH_REMATCH[1]}"
            CURRENT_WORDPRESS='latest'
            CURRENT_WORDPRESS_SIGNATURE='latest'
            CURRENT_PHP="${BASH_REMATCH[2]}"
            CURRENT_THEME="${BASH_REMATCH[3]}"
        elif [[ "$signature" =~ ^(mlb|mla|mlm|mco|mlc|mlu|mpe)-wp(latest|[0-9]+\.[0-9]+(\.[0-9]+)?)-php(7\.4|8\.0|8\.1|8\.2|8\.3|8\.4)-(storefront|astra|kadence|oceanwp|blocksy|generatepress|neve|hestia)$ ]]; then
            CURRENT_SITE="${BASH_REMATCH[1]}"
            CURRENT_WORDPRESS="${BASH_REMATCH[2]}"
            CURRENT_WORDPRESS_SIGNATURE="${BASH_REMATCH[2]}"
            CURRENT_PHP="${BASH_REMATCH[4]}"
            CURRENT_THEME="${BASH_REMATCH[5]}"
        elif [[ "$signature" =~ ^(mlb|mla|mlm|mco|mlc|mlu|mpe)-wp([^-]+)-php(7\.4|8\.0|8\.1|8\.2|8\.3|8\.4)-(storefront|astra|kadence|oceanwp|blocksy|generatepress|neve|hestia)-mp(test|production)-orderpaydata(true|false)-checkout(blocks|classic|both)$ ]]; then
            local candidate_site="${BASH_REMATCH[1]}"
            local candidate_wordpress="${BASH_REMATCH[2]}"
            local candidate_php="${BASH_REMATCH[3]}"
            local candidate_theme="${BASH_REMATCH[4]}"
            if [[ "$candidate_wordpress" = 'latest' || "$candidate_wordpress" =~ ^[0-9]+\.[0-9]+(\.[0-9]+)?$ ]]; then
                CURRENT_SITE="$candidate_site"
                CURRENT_WORDPRESS="$candidate_wordpress"
                CURRENT_WORDPRESS_SIGNATURE="$candidate_wordpress"
                CURRENT_PHP="$candidate_php"
                CURRENT_THEME="$candidate_theme"
            fi
        fi

        if [ -n "$signature" ] && [ -z "$CURRENT_SITE" ]; then
            CURRENT_STORE_MARKER_UNKNOWN=true
        fi
    fi

    if detected_wordpress=$(docker exec mp-wc-dev wp --allow-root core version 2>/dev/null) && [ -n "$detected_wordpress" ]; then
        CURRENT_WORDPRESS_INSTALLED="$detected_wordpress"
        [ -n "$CURRENT_WORDPRESS" ] || CURRENT_WORDPRESS="$detected_wordpress"
    fi
}

choose_site() {
    while true; do
        echo ''
        echo 'País:'
        echo '  1. MLB — Brasil       2. MLA — Argentina'
        echo '  3. MLM — México       4. MCO — Colômbia'
        echo '  5. MLC — Chile        6. MLU — Uruguai'
        echo '  7. MPE — Peru'
        echo ''
        read_choice
        case "$CHOICE" in
            1) SITE_VALUE='mlb'; return ;; 2) SITE_VALUE='mla'; return ;;
            3) SITE_VALUE='mlm'; return ;; 4) SITE_VALUE='mco'; return ;;
            5) SITE_VALUE='mlc'; return ;; 6) SITE_VALUE='mlu'; return ;;
            7) SITE_VALUE='mpe'; return ;;
            *) echo '[mp-dev] Digite um número de 1 a 7.' ;;
        esac
    done
}

choose_wordpress() {
    while true; do
        echo ''
        echo 'WordPress:'
        if [ -n "$CURRENT_WORDPRESS" ]; then
            if [ -n "$CURRENT_WORDPRESS_SIGNATURE" ]; then
                if [ -n "$CURRENT_WORDPRESS_INSTALLED" ] && [ "$CURRENT_WORDPRESS_INSTALLED" != "$CURRENT_WORDPRESS_SIGNATURE" ]; then
                    echo "  1. Manter configuração atual: ${CURRENT_WORDPRESS_SIGNATURE} (instalado: ${CURRENT_WORDPRESS_INSTALLED})"
                else
                    echo "  1. Manter configuração atual: ${CURRENT_WORDPRESS_SIGNATURE}"
                fi
            else
                echo "  1. Usar WordPress detectado: ${CURRENT_WORDPRESS} (a loja será recriada)"
            fi
            echo '  2. Última versão disponível (latest)'
            echo '  3. Informar uma versão específica'
        else
            echo '  1. Última versão disponível (latest)'
            echo '  2. Informar uma versão específica'
        fi
        echo ''
        read_choice

        if [ -n "$CURRENT_WORDPRESS" ]; then
            case "$CHOICE" in
                1)
                    WORDPRESS_VALUE="$CURRENT_WORDPRESS"
                    if [ -n "$CURRENT_WORDPRESS_SIGNATURE" ]; then
                        WORDPRESS_VALUE="$CURRENT_WORDPRESS_SIGNATURE"
                        WORDPRESS_PRESERVES_DATA=true
                    else
                        WORDPRESS_PRESERVES_DATA=false
                    fi
                    return
                    ;;
                2) WORDPRESS_VALUE='latest'; WORDPRESS_PRESERVES_DATA=false; return ;;
                3) break ;;
                *) echo '[mp-dev] Digite 1, 2 ou 3.' ;;
            esac
        else
            case "$CHOICE" in
                1) WORDPRESS_VALUE='latest'; WORDPRESS_PRESERVES_DATA=false; return ;;
                2) break ;;
                *) echo '[mp-dev] Digite 1 ou 2.' ;;
            esac
        fi
    done

    while true; do
        echo ''
        echo 'Digite a versão do WordPress (ex.: 7.1 ou 6.8.2):'
        echo ''
        read_choice
        if [[ "$CHOICE" =~ ^[0-9]+\.[0-9]+(\.[0-9]+)?$ ]]; then
            WORDPRESS_VALUE="$CHOICE"
            if [ -n "$CURRENT_WORDPRESS_SIGNATURE" ] && [ "$WORDPRESS_VALUE" = "$CURRENT_WORDPRESS_SIGNATURE" ]; then
                WORDPRESS_PRESERVES_DATA=true
            else
                WORDPRESS_PRESERVES_DATA=false
            fi
            return
        fi
        echo '[mp-dev] Use o formato X.Y ou X.Y.Z.'
    done
}

store_requires_reset() {
    if [ "$ACTION" = 'reset' ]; then
        return 0
    fi

    if [ "$CURRENT_STORE_MARKER_UNKNOWN" = 'true' ]; then
        return 0
    fi

    if [ -n "$CURRENT_SITE" ] && { [ "$SITE_VALUE" != "$CURRENT_SITE" ] || [ "$PHP_VALUE" != "$CURRENT_PHP" ] || [ "$THEME_VALUE" != "$CURRENT_THEME" ]; }; then
        return 0
    fi

    if [ -n "$CURRENT_SITE" ] && [ "$WORDPRESS_PRESERVES_DATA" = 'false' ]; then
        ACTION='reset'
        return 0
    fi

    return 1
}

choose_php() {
    while true; do
        echo ''
        echo 'PHP:'
        if [ -n "$CURRENT_PHP" ]; then
            echo "  1. Utilizar versão atual: ${CURRENT_PHP}"
            echo '  2. 7.4    3. 8.0    4. 8.1'
            echo '  5. 8.2    6. 8.3    7. 8.4'
        else
            echo '  1. 7.4    2. 8.0    3. 8.1'
            echo '  4. 8.2    5. 8.3    6. 8.4'
        fi
        echo ''
        read_choice
        if [ -n "$CURRENT_PHP" ]; then
            case "$CHOICE" in
                1) PHP_VALUE="$CURRENT_PHP"; return ;; 2) PHP_VALUE='7.4'; return ;;
                3) PHP_VALUE='8.0'; return ;; 4) PHP_VALUE='8.1'; return ;;
                5) PHP_VALUE='8.2'; return ;; 6) PHP_VALUE='8.3'; return ;;
                7) PHP_VALUE='8.4'; return ;; *) echo '[mp-dev] Digite um número de 1 a 7.' ;;
            esac
        else
            case "$CHOICE" in
                1) PHP_VALUE='7.4'; return ;; 2) PHP_VALUE='8.0'; return ;;
                3) PHP_VALUE='8.1'; return ;; 4) PHP_VALUE='8.2'; return ;;
                5) PHP_VALUE='8.3'; return ;; 6) PHP_VALUE='8.4'; return ;;
                *) echo '[mp-dev] Digite um número de 1 a 6.' ;;
            esac
        fi
    done
}

choose_theme() {
    while true; do
        echo ''
        echo 'Tema:'
        echo '  1. Storefront      2. Astra       3. Kadence'
        echo '  4. OceanWP         5. Blocksy     6. GeneratePress'
        echo '  7. Neve            8. Hestia'
        echo ''
        read_choice
        case "$CHOICE" in
            1) THEME_VALUE='storefront'; return ;; 2) THEME_VALUE='astra'; return ;;
            3) THEME_VALUE='kadence'; return ;; 4) THEME_VALUE='oceanwp'; return ;;
            5) THEME_VALUE='blocksy'; return ;; 6) THEME_VALUE='generatepress'; return ;;
            7) THEME_VALUE='neve'; return ;; 8) THEME_VALUE='hestia'; return ;;
            *) echo '[mp-dev] Digite um número de 1 a 8.' ;;
        esac
    done
}

choose_mp_mode() {
    while true; do
        echo ''
        echo 'Modo do Mercado Pago:'
        echo '  1. Teste'
        echo '  2. Produção'
        echo ''
        read_choice
        case "$CHOICE" in
            1) MP_MODE_VALUE='test'; return ;;
            2) MP_MODE_VALUE='production'; return ;;
            *) echo '[mp-dev] Digite 1 ou 2.' ;;
        esac
    done
}

choose_order_pay() {
    echo ''
    echo 'Criar cliente e pedido pendente para Order Pay? [s/N]'
    echo ''
    read_choice
    case "$CHOICE" in
        s|S) CREATE_ORDER_PAY_VALUE='true' ;;
        ''|n|N) CREATE_ORDER_PAY_VALUE='false' ;;
        *) echo '[mp-dev] Resposta inválida; Order Pay não será criado.'; CREATE_ORDER_PAY_VALUE='false' ;;
    esac
}

choose_checkout() {
    while true; do
        echo ''
        echo 'Quantos checkouts deseja disponibilizar?'
        echo '  1. Um checkout'
        echo '  2. Dois checkouts (Blocks e Classic)'
        echo ''
        read_choice
        case "$CHOICE" in
            1)
                echo ''
                echo 'Qual checkout?'
                echo '  1. Blocks'
                echo '  2. Classic'
                echo ''
                read_choice
                case "$CHOICE" in
                    1) CHECKOUT_MODE_VALUE='blocks'; return ;;
                    2) CHECKOUT_MODE_VALUE='classic'; return ;;
                    *) echo '[mp-dev] Digite 1 ou 2.' ;;
                esac
                ;;
            2) CHECKOUT_MODE_VALUE='both'; return ;;
            *) echo '[mp-dev] Digite 1 ou 2.' ;;
        esac
    done
}

choose_action() {
    while true; do
        echo ''
        echo 'Ação:'
        echo '  1. Iniciar preservando os dados (o make up mantém o comportamento atual)'
        echo '  2. Forçar uma loja limpa (apaga banco, pedidos e usuários)'
        echo ''
        read_choice
        case "$CHOICE" in
            1) ACTION='up'; return ;;
            2) ACTION='reset'; return ;;
            *) echo '[mp-dev] Digite 1 ou 2.' ;;
        esac
    done
}

resolve_credentials() {
    local suffix
    suffix=$(site_label "$SITE_VALUE")

    MP_PUBLIC_KEY_TEST_VALUE=$(read_env_value "MP_PUBLIC_KEY_TEST_${suffix}")
    [ -n "$MP_PUBLIC_KEY_TEST_VALUE" ] || MP_PUBLIC_KEY_TEST_VALUE=$(read_env_value MP_PUBLIC_KEY_TEST)
    MP_ACCESS_TOKEN_TEST_VALUE=$(read_env_value "MP_ACCESS_TOKEN_TEST_${suffix}")
    [ -n "$MP_ACCESS_TOKEN_TEST_VALUE" ] || MP_ACCESS_TOKEN_TEST_VALUE=$(read_env_value MP_ACCESS_TOKEN_TEST)
    MP_PUBLIC_KEY_PROD_VALUE=$(read_env_value "MP_PUBLIC_KEY_PROD_${suffix}")
    [ -n "$MP_PUBLIC_KEY_PROD_VALUE" ] || MP_PUBLIC_KEY_PROD_VALUE=$(read_env_value MP_PUBLIC_KEY_PROD)
    MP_ACCESS_TOKEN_PROD_VALUE=$(read_env_value "MP_ACCESS_TOKEN_PROD_${suffix}")
    [ -n "$MP_ACCESS_TOKEN_PROD_VALUE" ] || MP_ACCESS_TOKEN_PROD_VALUE=$(read_env_value MP_ACCESS_TOKEN_PROD)

    if [ "$MP_MODE_VALUE" = 'production' ]; then
        [ -n "$MP_PUBLIC_KEY_PROD_VALUE" ] && [ -n "$MP_ACCESS_TOKEN_PROD_VALUE" ] || {
            echo "[mp-dev] ERROR: configure MP_PUBLIC_KEY_PROD_${suffix}/MP_ACCESS_TOKEN_PROD_${suffix} ou os equivalentes genéricos." >&2
            exit 1
        }
    else
        [ -n "$MP_PUBLIC_KEY_TEST_VALUE" ] && [ -n "$MP_ACCESS_TOKEN_TEST_VALUE" ] || {
            echo "[mp-dev] ERROR: configure MP_PUBLIC_KEY_TEST_${suffix}/MP_ACCESS_TOKEN_TEST_${suffix} ou os equivalentes genéricos." >&2
            exit 1
        }
    fi

    TEST_CUSTOMER_USERNAME_VALUE=$(read_env_value TEST_CUSTOMER_USERNAME)
    TEST_CUSTOMER_EMAIL_VALUE=$(read_env_value TEST_CUSTOMER_EMAIL)
    TEST_CUSTOMER_PASSWORD_VALUE=$(read_env_value TEST_CUSTOMER_PASSWORD)
    if [ "$CREATE_ORDER_PAY_VALUE" = 'true' ]; then
        [ -n "$TEST_CUSTOMER_USERNAME_VALUE" ] && [ -n "$TEST_CUSTOMER_EMAIL_VALUE" ] && [ -n "$TEST_CUSTOMER_PASSWORD_VALUE" ] || {
            echo '[mp-dev] ERROR: configure TEST_CUSTOMER_USERNAME, TEST_CUSTOMER_EMAIL e TEST_CUSTOMER_PASSWORD.' >&2
            exit 1
        }
    fi

    export MP_PUBLIC_KEY_TEST="$MP_PUBLIC_KEY_TEST_VALUE"
    export MP_ACCESS_TOKEN_TEST="$MP_ACCESS_TOKEN_TEST_VALUE"
    export MP_PUBLIC_KEY_PROD="$MP_PUBLIC_KEY_PROD_VALUE"
    export MP_ACCESS_TOKEN_PROD="$MP_ACCESS_TOKEN_PROD_VALUE"
    export TEST_CUSTOMER_USERNAME="$TEST_CUSTOMER_USERNAME_VALUE"
    export TEST_CUSTOMER_EMAIL="$TEST_CUSTOMER_EMAIL_VALUE"
    export TEST_CUSTOMER_PASSWORD="$TEST_CUSTOMER_PASSWORD_VALUE"
    export WORDPRESS_VERSION="$WORDPRESS_VALUE"
    export MP_MODE="$MP_MODE_VALUE"
    export CHECKOUT_MODE="$CHECKOUT_MODE_VALUE"
    export CREATE_ORDER_PAY_TEST_DATA="$CREATE_ORDER_PAY_VALUE"
}

run_with_progress() {
    local log_file
    local pid
    local status
    local progress_label
    local frames=('⠋' '⠙' '⠹' '⠸' '⠼' '⠴' '⠦' '⠧' '⠇' '⠏')
    local index=0

    interrupt_run() {
        kill "$pid" 2>/dev/null || true
        wait "$pid" 2>/dev/null || true
        printf '\n[mp-dev] Operação interrompida.\n'
        exit 130
    }

    get_progress_label() {
        if grep -qF '[mp-dev] Cliente e pedido pendente para Order Pay criados.' "$log_file"; then
            printf '%s' 'Finalizando dados de Order Pay...'
        elif grep -qF '[mp-dev] Checkout configurado:' "$log_file"; then
            printf '%s' 'Configurando checkout...'
        elif grep -qF '[mp-dev] Mercado Pago configurado em modo' "$log_file"; then
            printf '%s' 'Configurando Mercado Pago...'
        elif grep -qF '[mp-dev] Waiting for WordPress to be ready...' "$log_file"; then
            printf '%s' 'Aguardando o WordPress iniciar...'
        else
            printf '%s' 'Preparando containers Docker...'
        fi
    }

    log_file=$(mktemp "${TMPDIR:-/tmp}/mp-dev-interactive.XXXXXX")

    (
        cd "$SCRIPT_DIR"
        make "$ACTION" "${MAKE_ARGS[@]}"
        make configure-interactive-store "${MAKE_ARGS[@]}"
    ) >"$log_file" 2>&1 &
    pid=$!

    trap interrupt_run INT TERM

    echo ''
    while kill -0 "$pid" 2>/dev/null; do
        progress_label=$(get_progress_label)
        printf '\r\033[K  %s %s' "${frames[$((index % ${#frames[@]}))]}" "$progress_label"
        index=$((index + 1))
        sleep 0.2
    done
    if wait "$pid"; then status=0; else status=$?; fi
    trap - INT TERM
    printf '\r\033[K'

    if [ "$status" -ne 0 ]; then
        echo '[mp-dev] ERROR: não foi possível preparar a loja.' >&2
        tail -n 80 "$log_file" >&2
        echo "[mp-dev] Log completo: $log_file" >&2
        return "$status"
    fi
    rm -f "$log_file"
}

show_summary() {
    local order_pay_message='Não criado'

    if [ "$CREATE_ORDER_PAY_VALUE" = 'true' ]; then
        order_pay_message='Execute make order-pay-url para consultar'
    fi

    echo ''
    echo '╭──────────────────────────────────────────────────────────────╮'
    echo '│  ✓ Loja pronta                                               │'
    echo '├──────────────────────────────────────────────────────────────┤'
    printf '│  Loja:      %-49s│\n' "http://localhost:${PORT_VALUE}/shop"
    printf '│  Admin:     %-49s│\n' "http://localhost:${PORT_VALUE}/wp-admin"
    case "$CHECKOUT_MODE_VALUE" in
        blocks)
            printf '│  Checkout Blocks:  %-42s│\n' "http://localhost:${PORT_VALUE}/checkout/"
            ;;
        classic)
            printf '│  Checkout Classic: %-42s│\n' "http://localhost:${PORT_VALUE}/checkout/"
            ;;
        both)
            printf '│  Checkout Blocks:  %-42s│\n' "http://localhost:${PORT_VALUE}/checkout/"
            printf '│  Checkout Classic: %-42s│\n' "http://localhost:${PORT_VALUE}/checkout-classic/"
            ;;
    esac
    echo '├──────────────────────────────────────────────────────────────┤'
    printf '│  %-60s│\n' "$(site_label "$SITE_VALUE") - WordPress ${WORDPRESS_VALUE} - PHP ${PHP_VALUE}"
    printf '│  %-60s│\n' "Tema: ${THEME_VALUE} - Mercado Pago: ${MP_MODE_VALUE}"
    echo '╰──────────────────────────────────────────────────────────────╯'
    echo ''
    echo 'Access-admin: admin / admin'
    echo "Order Pay: ${order_pay_message}"
    show_store_ready_art
}

main() {
    show_banner
    echo '╭──────────────────────────────────────────────╮'
    echo '│  Configuração interativa da loja local      │'
    echo '╰──────────────────────────────────────────────╯'

    require_prerequisites
    detect_current_store
    if [ "$CURRENT_STORE_MARKER_UNKNOWN" = 'true' ]; then
        echo ''
        echo '[mp-dev] WARNING: a configuração local atual não foi reconhecida.'
    fi
    if [ -n "$CURRENT_SITE" ]; then
        local current_wordpress_label="${CURRENT_WORDPRESS:-não detectado}"
        if [ -n "$CURRENT_WORDPRESS_INSTALLED" ] && [ "$CURRENT_WORDPRESS_INSTALLED" != "$CURRENT_WORDPRESS" ]; then
            current_wordpress_label="${CURRENT_WORDPRESS} (instalado: ${CURRENT_WORDPRESS_INSTALLED})"
        fi
        echo ''
        echo "Loja atual: $(site_label "$CURRENT_SITE") · WordPress ${current_wordpress_label} · PHP ${CURRENT_PHP} · ${CURRENT_THEME}"
    fi

    choose_site
    choose_wordpress
    choose_php
    choose_theme
    choose_mp_mode
    choose_order_pay
    choose_checkout
    choose_action
    resolve_credentials

    DESTRUCTIVE=false
    if store_requires_reset; then
        DESTRUCTIVE=true
    fi

    echo ''
    echo 'Configuração escolhida:'
    echo "  País: $(site_label "$SITE_VALUE") · WordPress: ${WORDPRESS_VALUE} · PHP: ${PHP_VALUE} · Tema: ${THEME_VALUE}"
    echo "  Mercado Pago: ${MP_MODE_VALUE} · Checkout: ${CHECKOUT_MODE_VALUE} · Order Pay: ${CREATE_ORDER_PAY_VALUE}"
    if [ "$CURRENT_STORE_MARKER_UNKNOWN" = 'true' ]; then
        echo '⚠ A configuração local atual não foi reconhecida.'
    fi
    if [ "$DESTRUCTIVE" = 'true' ]; then
        echo '⚠ Esta combinação recriará a loja e apagará os dados locais atuais.'
    fi
    if [ "$MP_MODE_VALUE" = 'production' ]; then
        echo '⚠ Serão usadas credenciais de produção do Mercado Pago.'
    fi

    echo ''
    if [ "$DESTRUCTIVE" = 'true' ] || [ "$MP_MODE_VALUE" = 'production' ]; then
        echo 'Digite S para continuar ou N para cancelar.'
    else
        echo 'Executar agora? [S/n]'
    fi
    echo ''
    read_choice
    case "$CHOICE" in
        s|S) ;;
        '')
            if [ "$DESTRUCTIVE" = 'true' ] || [ "$MP_MODE_VALUE" = 'production' ]; then
                echo '[mp-dev] Operação cancelada.'
                exit 0
            fi
            ;;
        *) echo '[mp-dev] Operação cancelada.'; exit 0 ;;
    esac

    MAKE_ARGS=(
        "SITE=${SITE_VALUE}"
        "WORDPRESS=${WORDPRESS_VALUE}"
        "PHP=${PHP_VALUE}"
        "THEME=${THEME_VALUE}"
        "PORT=${PORT_VALUE}"
    )

    run_with_progress
    show_summary
}

if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then
    main "$@"
fi
