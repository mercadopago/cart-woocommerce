import globals from "globals";
import pluginJs from "@eslint/js";
import woocommercePlugin from "@woocommerce/eslint-plugin";

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
    rules: {
      ...woocommercePlugin.configs.recommended.rules,
    },
    ...pluginJs.configs.recommended,
  },
];
