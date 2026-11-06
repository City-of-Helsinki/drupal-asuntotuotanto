const fs = require('fs');
const path = require('path');

const buildDir = path.join(__dirname, '../build/assets/js');

const runtimeStub = `// Runtime stub - all include to main.js
(function() { 'use strict'; })();`;

const vendorStub = `// Vendor stub - all include to main.js
(function() { 'use strict'; })();`;

fs.writeFileSync(path.join(buildDir, 'runtime-main.temp.js'), runtimeStub);
fs.writeFileSync(path.join(buildDir, 'vendor.temp.chunk.js'), vendorStub);

console.log('✓ Created stub files for Drupal compatibility (all code is in main.js)');
