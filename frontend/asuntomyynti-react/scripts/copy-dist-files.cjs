const fs = require('fs');
const path = require('path');

const variant = process.argv[2];

if (!variant) {
  console.error('Usage: node scripts/copy-dist-files.cjs <variant>');
  process.exit(1);
}

const rootDir = path.join(__dirname, '..');
const buildJsDir = path.join(rootDir, 'build', 'assets', 'js');
const buildCssDir = path.join(rootDir, 'build', 'assets', 'css');
const targetDir = path.join(rootDir, 'dist', 'react', variant);

if (!fs.existsSync(buildJsDir) || !fs.existsSync(buildCssDir)) {
  console.error('Build output not found. Run npm run build first.');
  process.exit(1);
}

fs.mkdirSync(targetDir, { recursive: true });

const findSingleFile = (dir, matcher, label) => {
  const matches = fs.readdirSync(dir).filter(matcher);
  if (matches.length === 0) {
    console.error(`Could not find ${label} in ${dir}`);
    process.exit(1);
  }
  if (matches.length > 1) {
    console.error(`Found multiple ${label} files in ${dir}: ${matches.join(', ')}`);
    process.exit(1);
  }
  return path.join(dir, matches[0]);
};

const mainJs = findSingleFile(
  buildJsDir,
  (name) => name.startsWith('main.') && name.endsWith('.chunk.js'),
  'main chunk'
);

const mainCss = findSingleFile(
  buildCssDir,
  (name) => name.startsWith('main.') && name.endsWith('.css'),
  'main css'
);

const vendorJs = path.join(buildJsDir, 'vendor.temp.chunk.js');
const runtimeJs = path.join(buildJsDir, 'runtime-main.temp.js');

if (!fs.existsSync(vendorJs) || !fs.existsSync(runtimeJs)) {
  console.error('Stub JS files not found. Ensure build runs scripts/create-iife-wrapper.cjs.');
  process.exit(1);
}

fs.copyFileSync(mainJs, path.join(targetDir, 'asu_react_main.js'));
fs.copyFileSync(vendorJs, path.join(targetDir, 'asu_react_vendors.js'));
fs.copyFileSync(runtimeJs, path.join(targetDir, 'asu_react_runtime-main.js'));
fs.copyFileSync(mainCss, path.join(targetDir, 'asu_react_main.css'));

console.log(`Copied dist files to dist/react/${variant}`);
