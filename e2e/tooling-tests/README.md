# Testes unitários da automação E2E

Esta pasta não contém cenários de checkout. Ela valida rapidamente, com `node:test`, o tooling que
orquestra os ambientes compartilhados antes de abrir o Playwright:

- `environment-config.test.js`: matriz dos sete países, container exato, redirects limitados,
  allowlist de URLs e bloqueio de SSRF;
- `check-shared-domains.test.js`: descoberta da instância via `smooth`, matriz dos 14 domínios,
  comparação de IPv4 e remediação somente por `smooth add-domain`;
- `run-dual-report.test.js`: interface restrita, versões obrigatórias e sinais repetidos do runner;
- `run-dual-matrix.test.js`: versões obrigatórias, concorrência e ordem da matriz;
- `publish-candidate.test.js`: validação do ZIP e proteção contra publicação acidental em homol;
- `fetch-production-artifact.test.js`: URL fixa, versão e destino do ZIP oficial de produção;
- `prepare-release-comparison.test.js`: mapeamento imutável RC → staging e produção → homol;
- `run-release-comparison.test.js`: lease único entre publicação, Classic e Blocks;
- `lease-heartbeat.test.js`: renovação do lease e invalidação imediata ao perder ownership;
- `remote-lock-script.test.js`: publicação/renovação/release atômicos e recuperação de lock incompleto;
- `ssh-options.test.js`: `known_hosts` validado, host pinado e chave individual restrita;
- `bootstrap-shared-artifacts.test.js`: pré-condição e argumentos do seed inicial das duas lanes;
- `fetch-site-id.test.js`: identidade no checkout externo, allowlist e redirects same-origin;
- `dual-report.test.js`: classificação comparativa, instabilidade por lane e limitações de cobertura;
- `site-guard.test.js`: isolamento entre shared staging/homol e lojas pessoais/Super Token;
- `capability-guard.test.js`: skips explícitos para capabilities ausentes nos sellers compartilhados;
- `shared-caddy.test.js`: bloqueio de login/XML-RPC/debug log, incluindo variantes com `PATH_INFO`;
- `shared-runtime-security.test.js`: debug desativado e WP-CLI sem root no ambiente compartilhado;
- `progress-reporter.test.js`: sanitização e confinamento dos eventos emitidos pelo Playwright;
- `dual-progress.test.js`: painel vivo das duas lanes e classificação incremental.

Execute pela raiz com `make e2e-shared-validate` ou diretamente com
`npm --prefix e2e run test:tooling`.
