// Active variant: env override (e.g. `SUPER_TOKEN_VERSION=v2.1 npm test` — CI, or an env without
// the PHP file yet) → otherwise derived from the PHP source of truth (PLUGIN_SUPER_TOKEN_VERSION),
// so there is no hardcoded value to keep in sync.
const SUPER_TOKEN_VERSION = process.env.SUPER_TOKEN_VERSION || require('./main.js').getActiveSuperTokenVersion();
const otherSuperTokenVersion = SUPER_TOKEN_VERSION === 'v2.1' ? 'v2' : 'v2.1';

module.exports = {
  // Ambiente de teste (jsdom simula o navegador)
  testEnvironment: 'jsdom',

  // Cobre todos os arquivos de teste — a versão inativa do super-token é excluída abaixo
  testMatch: ['**/tests/JS/**/*.test.js'],

  // Arquivos a serem ignorados
  testPathIgnorePatterns: [
    '/node_modules/',
    '/vendor/',
    '/build/',
    '/e2e/',
    `tests/JS/checkouts/super-token/${otherSuperTokenVersion}/`,
  ],

  // Configuração de cobertura
  collectCoverageFrom: [
    'assets/js/**/*.js',
    'assets/js/**/*.ts',
    'packages/narciso/**/*.js',
    '!assets/js/**/*.min.js',
    '!assets/js/**/__tests__/**',
    '!assets/js/blocks/**',
  ],

  // Configuração de mapeamento de nomes de arquivos
  moduleNameMapper: {
    '^@super-token/(.*)$': '<rootDir>/assets/js/checkouts/super-token/$1',
    '^assets/js/(.*)$': '<rootDir>/assets/js/$1',
    '^packages/narciso/(.*)$': '<rootDir>/packages/narciso/$1',
  },

  // Resolve os módulos ES/TS do super-token (as classes globais legadas seguem
  // sendo carregadas via vm/loadFile, que lê o source cru e ignora o resolver).
  moduleFileExtensions: ['js', 'ts', 'json', 'node'],

  coverageDirectory: 'coverage',

  // V8 native coverage: instruments executed code without a Babel/SWC transform
  // (none is configured), so source loaded via the vm-based loadFile helper is
  // still attributed to its real file (see tests/JS/helpers/load-file.js).
  coverageProvider: 'v8',

  coverageReporters: ['text', 'lcov', 'html', 'json'],

  // Setup de arquivos antes dos testes
  setupFilesAfterEnv: ['<rootDir>/jest.setup.js'],

  // Só os arquivos .ts do super-token passam por Babel (apaga os tipos). Os testes
  // legados em .js continuam sem transform — o coverage v8 os atribui via vm/loadFile.
  // configFile/babelrc: false isola do Babel do wp-scripts (evita clobber no build dos blocks).
  transform: {
    '^.+\\.ts$': ['babel-jest', {
      configFile: false,
      babelrc: false,
      presets: [
        ['@babel/preset-env', { targets: { node: 'current' } }],
        '@babel/preset-typescript',
      ],
    }],
  },

  // Variáveis globais disponíveis nos testes
  globals: {
    window: {},
    SUPER_TOKEN_VERSION,
  },

  // Tempo limite para testes (em ms)
  testTimeout: 10000,

  // Exibir resultados individuais dos testes
  verbose: true,
};


