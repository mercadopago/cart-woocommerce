# Evidências dos E2E iOS

Cada execução cria uma pasta `<execution-id>/<scenario>/` com screenshots e manifests seguros.
Os artefatos são locais/efêmeros e ficam ignorados pelo Git. As screenshots podem mostrar somente
os dados sintéticos de comprador e cartão definidos nas fixtures da suíte. Nunca execute a suíte
com dados reais nem registre credenciais, tokens, senhas ou respostas internas nesta pasta.
