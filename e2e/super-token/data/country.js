const fs = require("fs");
const path = require("path");

const CONFIG_PATH = path.resolve(__dirname, "..", "config", "countries.json");

// Dados do comprador por país, lidos de config/countries.json (gitignored).
// Configurar um buyerEmail (não-placeholder) pressupõe um comprador apto ao Super Token;
// `email` vazio (ausente ou TODO_) faz os cenários que dependem dele darem skip.
function buyerFor(site = "mlb") {
  const countries = JSON.parse(fs.readFileSync(CONFIG_PATH, "utf-8"));
  const country = countries[site.toLowerCase()] || {};
  const email = country.buyerEmail || "";
  return {
    site: site.toLowerCase(),
    email: email.startsWith("TODO") ? "" : email,
    productId: country.productId || 14,
    fingerId: Number(country.fingerId) || 1,
  };
}

module.exports = { buyerFor };
