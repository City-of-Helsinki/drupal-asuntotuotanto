const fs = require('fs');
const path = require('path');
const archiver = require('archiver');

const packageJson = JSON.parse(fs.readFileSync(path.join(__dirname, '../package.json'), 'utf-8'));
const unsafeZipName = `${packageJson.name}-${packageJson.version}.zip`;
const zipName = unsafeZipName.replace(/[^a-zA-Z0-9._-]/g, '_');
const distPath = path.join(__dirname, '../dist');
const destinationPath = path.join(distPath, zipName);
const reactPath = path.join(distPath, 'react');

const createZip = async () => {
  await fs.promises.rm(destinationPath, { force: true });

  const output = fs.createWriteStream(destinationPath);
  const archive = archiver('zip', { zlib: { level: 9 } });

  await new Promise((resolve, reject) => {
    output.on('close', resolve);
    output.on('error', reject);
    archive.on('error', reject);

    archive.pipe(output);
    archive.directory(reactPath, 'react');
    archive.finalize();
  });

  const stats = fs.statSync(destinationPath);
  console.log(`✓ Created ${zipName} (${stats.size} bytes)`);
};

createZip().catch((error) => {
  console.error('Error creating zip:', error.message);
  process.exit(1);
});
