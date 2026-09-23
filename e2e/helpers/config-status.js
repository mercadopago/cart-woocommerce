const path = require('node:path');

const dotenv = require('dotenv');

const SUPPORTED_SITES = new Set(['MLA', 'MLB', 'MLC', 'MCO', 'MLM', 'MLU', 'MPE']);

const site = String(process.argv[2] || '').toUpperCase();
if (!SUPPORTED_SITES.has(site)) {
  process.stderr.write('[E2E] Site inválido. Use um site suportado.\n');
  process.exit(2);
}

dotenv.config({ path: path.resolve(__dirname, '..', '.env') });

const configured = (...names) => (names.some((name) => process.env[name]) ? 'configured' : 'missing');
const accessToken = configured(`MP_ACCESS_TOKEN_TEST_${site}`, 'MP_ACCESS_TOKEN_TEST');
const publicKey = configured(`MP_PUBLIC_KEY_TEST_${site}`, 'MP_PUBLIC_KEY_TEST');
const guestUser = configured('GUEST_EMAIL');

process.stdout.write(`accessToken=${accessToken}\n`);
process.stdout.write(`publicKey=${publicKey}\n`);
process.stdout.write(`guestUser=${guestUser}\n`);

if ([accessToken, publicKey, guestUser].includes('missing')) process.exitCode = 1;
