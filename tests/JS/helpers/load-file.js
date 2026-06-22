const vm = require('vm');
const fs = require('fs');

function loadFile(filePath, classname, context) {
  const fileContent = fs.readFileSync(filePath, 'utf8');
  // Pass the real path as the script filename so the V8 coverage provider can
  // attribute executed lines back to the source file (vm scripts are invisible
  // to coverage without a resolvable filename).
  const script = new vm.Script(fileContent + '\n' + classname + ';', { filename: filePath });
  return script.runInNewContext({
    window: global.window,
    document: global.document,
    console: global.console,
    CustomEvent: global.CustomEvent,
    Event: global.Event,
    ...context,
  });
}

module.exports = { loadFile };