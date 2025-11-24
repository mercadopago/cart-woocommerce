import globals from "globals";
import pluginJs from "@eslint/js";
import woocommercePlugin from "@woocommerce/eslint-plugin";
import pluginReact from "eslint-plugin-react";

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
    },
    ...pluginJs.configs.recommended,
    rules: {
      ...pluginJs.configs.recommended.rules,
      ...woocommercePlugin.configs.recommended.rules,
      "no-case-declarations": "off",
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
