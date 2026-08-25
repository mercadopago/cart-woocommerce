import globals from "globals";
import pluginJs from "@eslint/js";
import woocommercePlugin from "@woocommerce/eslint-plugin";
import pluginReact from "eslint-plugin-react";
import pluginJsdoc from "eslint-plugin-jsdoc";
import wordpressPlugin from "@wordpress/eslint-plugin";
import typescriptPlugin from "@typescript-eslint/eslint-plugin";
import typescriptParser from "@typescript-eslint/parser";
import pluginImport from "eslint-plugin-import";
import pluginJest from "eslint-plugin-jest";

/** @type {import('eslint').Linter.Config[]} */
export default [
  {
    files: ["assets/**/*.js"], // Restrict ESLint to assets folder
    languageOptions: {
      sourceType: "commonjs",
      globals: globals.browser,
    },
    plugins: {
      "@woocommerce": woocommercePlugin,
      jsdoc: pluginJsdoc,
      "@wordpress": wordpressPlugin,
      "@typescript-eslint": typescriptPlugin,
      import: pluginImport,
      jest: pluginJest,
      react: pluginReact,
    },
    ...pluginJs.configs.recommended,
    rules: {
      ...pluginJs.configs.recommended.rules,
      ...(woocommercePlugin.configs?.recommended?.rules ?? {}),
      "no-case-declarations": "off",
    },
  },
  {
    // Super Token ES module + TypeScript foundation (hexagonal architecture).
    files: ["assets/js/checkouts/super-token/**/*.ts"],
    languageOptions: {
      parser: typescriptParser,
      sourceType: "module",
      ecmaVersion: 2020,
      globals: globals.browser,
    },
    plugins: {
      "@typescript-eslint": typescriptPlugin,
    },
    rules: {
      ...(typescriptPlugin.configs?.recommended?.rules ?? {}),
      // Type resolution is TypeScript's job (tsc --noEmit gate), not ESLint's.
      "no-undef": "off",
      "no-unused-vars": "off",
    },
  },
  {
    files: ["assets/js/blocks/**/*.js"],
    languageOptions: {
      sourceType: "module",
      globals: globals.browser,
      ecmaVersion: 2020,
      parserOptions: {
        ecmaFeatures: {
          jsx: true,
        },
      },
    },
    plugins: {
      react: pluginReact,
    },
    rules: {
      "react/jsx-uses-react": "error",
      "react/jsx-uses-vars": "error",
      "react/react-in-jsx-scope": "off",
      "react/prop-types": "off",
    },
    settings: {
      react: {
        version: "detect",
      },
    },
  },
];
