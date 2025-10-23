const vm = require('vm');
const fs = require('fs');

function loadFile(filePath, classname, context) {
  const fileContent = fs.readFileSync(filePath, 'utf8');
  const script = new vm.Script(fileContent + '\n' + classname + ';');
  return script.runInNewContext({
    window: global.window,
    document: global.document,
    console: global.console,
    ...context,
  });
}

module.exports = { loadFile };