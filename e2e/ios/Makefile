.DEFAULT_GOAL := help

.PHONY: help install doctor list test test-classic test-blocks test-installments stability report clean

override APPIUM_HOME := $(abspath ..)
override XCUITEST_DRIVER := appium-xcuitest-driver@12.2.1
export APPIUM_HOME XCUITEST_DRIVER

help:
	@echo "Comandos E2E para iOS Simulator:"
	@echo "  make install           Instala dependências e registra o XCUITest pinado"
	@echo "  make doctor            Valida Xcode, Docker, Node e Appium/XCUITest"
	@echo "  make list              Lista os cenários iOS disponíveis"
	@echo "  make test              Executa toda a suíte MLB (Classic + Blocks)"
	@echo "  make test-classic      Executa os cenários do Checkout Classic"
	@echo "  make test-blocks       Executa os cenários do Checkout Blocks"
	@echo "  make test-installments Executa as regressões de parcelas nos dois modos"
	@echo "  make stability         Executa três rodadas limpas da suíte completa"
	@echo "  make report            Abre o último relatório HTML do Playwright"
	@echo "  make clean             Remove runtimes, relatórios e evidências locais"
	@echo ""
	@echo "Variáveis opcionais: IOS_DEVICE_NAME, IOS_RUNTIME_ID, IOS_HTTPS_PORT,"
	@echo "APPIUM_PORT, IOS_ERASE e IOS_KEEP_RUNNING. Consulte README.md."

install:
	@cd .. && npm ci
	@cd .. && npx appium driver list --installed --json \
		| node -e 'let input = ""; process.stdin.setEncoding("utf8"); process.stdin.on("data", (chunk) => input += chunk); process.stdin.on("end", () => { try { const driver = JSON.parse(input).xcuitest; if (driver?.installed && driver.version === "12.2.1") process.exit(0); } catch {} process.exit(1); });' \
		|| npx appium driver install "$${XCUITEST_DRIVER}"

doctor:
	@command -v xcodebuild >/dev/null || { echo "Xcode não encontrado"; exit 1; }
	@command -v xcrun >/dev/null || { echo "xcrun não encontrado"; exit 1; }
	@command -v docker >/dev/null || { echo "Docker não encontrado"; exit 1; }
	@node -e 'const [major, minor] = process.versions.node.split(".").map(Number); if (major < 20 || (major === 20 && minor < 19)) { console.error("Node 20.19+ é obrigatório; encontrado: " + process.versions.node); process.exit(1); }'
	@node --version
	@xcodebuild -version
	@cd .. && npx appium driver list --installed --json \
		| node -e 'let input = ""; process.stdin.setEncoding("utf8"); process.stdin.on("data", (chunk) => input += chunk); process.stdin.on("end", () => { try { const driver = JSON.parse(input).xcuitest; if (driver?.installed && driver.version === "12.2.1") process.exit(0); } catch {} process.exit(1); });' \
		|| { echo "XCUITest 12.2.1 não está registrado em $${APPIUM_HOME}"; exit 1; }
	@cd .. && npx appium driver doctor xcuitest

list:
	@cd .. && npx playwright test --config ios/playwright.config.js --list

test:
	@SITE=mlb bash run/run-e2e.sh

test-classic:
	@SITE=mlb IOS_TEST_GREP='Classic' bash run/run-e2e.sh

test-blocks:
	@SITE=mlb IOS_TEST_GREP='Blocks' bash run/run-e2e.sh

test-installments:
	@SITE=mlb IOS_TEST_GREP='auto-selected installment' bash run/run-e2e.sh

stability:
	@for run in 1 2 3; do \
		echo "[ios-e2e] Rodada de estabilidade $$run/3"; \
		SITE=mlb IOS_ERASE=1 bash run/run-e2e.sh || exit 1; \
	done

report:
	@cd .. && npx playwright show-report ios/playwright-report

clean:
	@rm -rf .runtime playwright-report test-results
	@find evidence -mindepth 1 -maxdepth 1 -type d -exec rm -rf {} +
