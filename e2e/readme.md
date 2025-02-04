# E2E

Utilize o tema padrão Store Front do Woocomerce
Rode os testes a partir do diretório e2e

## Mapping scenarios

Crie uma página nova abaixo do tópico no confluence utilizando o [Template de Cenários](https://mercadolibre.atlassian.net/wiki/spaces/PLU/pages/2493286807/Template+de+cen+rios+E2E)

## Writing tests
[Playwright docs](https://playwright.dev/docs/writing-tests)

## Initializing
`npm install`

## Using Codegen
[Playwright Codegen Docs](https://playwright.dev/docs/codegen-intro)
```npx playwright codegen <url>```

## Running tests
Set `.env` file.
Use `.env.sample` as reference

### Running
```npx playwright test```

### Running with UI
```npx playwright test --ui```

### Running test for specific site id
Tests are organized in per site id folder structure. Ex: tests/mlb, tests/mlm.
Running the follow command executes the test only in specific folder.
`npx playwright test <site_id>`
ex.:
`npx playwright test mlm`

### Running specific tests
To run a single test file, pass in the name of the test file that you want to run.
```npx playwright test landing-page.spec.ts```

To run a set of test files from different directories, pass in the names of the directories that you want to run the tests in.
```npx playwright test tests/todo-page/ tests/landing-page/```

To run files that have landing or login in the file name, simply pass in these keywords to the CLI.
```npx playwright test landing login```

To run a test with a specific title, use the -g flag followed by the title of the test.
```npx playwright test -g "add a todo item"```

### Running on headed to view browser
```npx playwright test --headed```

### View tests reporting
```npx playwright show-report```

### Running in different browsers ignoring config
```npx playwright test --project webkit```

## Debug tests with Playwright inspector
```npx playwright test --debug```
