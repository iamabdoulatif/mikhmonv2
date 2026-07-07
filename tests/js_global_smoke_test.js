const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const phpFiles = [];

function collectPhpFiles(dir) {
  fs.readdirSync(dir, { withFileTypes: true }).forEach((entry) => {
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (entry.name !== '.git' && entry.name !== 'vendor') {
        collectPhpFiles(fullPath);
      }
      return;
    }

    if (entry.isFile() && entry.name.endsWith('.php')) {
      phpFiles.push(fullPath);
    }
  });
}

function chainableJqueryStub() {
  const chain = {
    append() { return chain; },
    fadeIn() { return chain; },
    find() { return chain; },
    focus() { return chain; },
    hide() { return chain; },
    html() { return chain; },
    load() { return chain; },
    show() { return chain; },
    text() { return ''; },
  };

  return () => chain;
}

function makeLoginPageContext() {
  const body = {
    className: '',
    innerHTML: '',
    style: {},
  };
  const $ = chainableJqueryStub();
  const window = {
    innerWidth: 1024,
    location: {
      href: 'http://localhost:8888/mikhmonv2/admin.php?id=login',
    },
  };

  const document = {
    body,
    getElementById() {
      return null;
    },
    getElementsByClassName() {
      return [];
    },
    getElementsByTagName(name) {
      return String(name).toUpperCase() === 'BODY' ? [body] : [];
    },
  };

  window.document = document;
  window.$ = $;

  return {
    $,
    clearInterval,
    clearTimeout,
    console,
    document,
    jQuery: $,
    setInterval,
    setTimeout,
    window,
  };
}

function runScript(file) {
  const source = fs.readFileSync(path.join(root, file), 'utf8');
  vm.runInNewContext(source, makeLoginPageContext(), { filename: file });
}

[
  'js/mikhmon-ui.blue.min.js',
  'js/mikhmon-ui.dark.min.js',
  'js/mikhmon-ui.green.min.js',
  'js/mikhmon-ui.light.min.js',
  'js/mikhmon-ui.pink.min.js',
  'js/mikhmon.js',
].forEach(runScript);

[
  'admin.php',
  'index.php',
].forEach((file) => {
  const source = fs.readFileSync(path.join(root, file), 'utf8');
  if (!/mikhmon-ui\.<\?= \$theme; \?>\.min\.js\?t=/.test(source)) {
    throw new Error(`${file} must cache-bust the themed UI script`);
  }
});

collectPhpFiles(root);
phpFiles.forEach((file) => {
  const source = fs.readFileSync(file, 'utf8');
  const includePattern = /\b(?:include|include_once|require|require_once)\s*\(?\s*['"]([^'"]+)['"]\s*\)?/g;
  let match;

  while ((match = includePattern.exec(source)) !== null) {
    const includePath = match[1];
    if (includePath.includes('$')) {
      continue;
    }

    const candidates = [
      path.join(root, includePath.replace(/^\.\//, '')),
      path.resolve(path.dirname(file), includePath),
    ];

    if (!candidates.some((candidate) => fs.existsSync(candidate))) {
      throw new Error(`${path.relative(root, file)} includes missing file ${includePath}`);
    }
  }
});

console.log('JS globals tolerate pages without dashboard-only elements.');
