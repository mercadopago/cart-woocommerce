const fs = require("fs");
const path = require("path");

const TUNNEL_FILE = path.resolve(__dirname, "..", "..", "..", "docker-flexible-environment", ".tunnel-url");

// URL da loja: SHOP_URL do ambiente, ou o túnel que o `make store` gravou.
// connectOverCDP reusa o contexto existente do Chrome, então o baseURL do Playwright não
// se aplica — os flows navegam com URL absoluta a partir daqui.
function shopUrl() {
  if (process.env.SHOP_URL) return process.env.SHOP_URL;
  try {
    return fs.readFileSync(TUNNEL_FILE, "utf-8").trim();
  } catch {
    // Sem host, os flows navegariam para `/?add-to-cart=14` e falhariam com erros enganosos
    // ("Target closed"/"Navigation timeout"). O aviso aponta a causa real.
    console.warn("[super-token] Shop URL não encontrada — rode: make store SITE=<país>");
    return "";
  }
}

module.exports = { shopUrl };
