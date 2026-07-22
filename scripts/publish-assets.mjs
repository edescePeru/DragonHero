import { cpSync, existsSync, mkdirSync, statSync, utimesSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const generatedAssets = resolve(projectRoot, 'dist', 'assets');
const generatedBundle = resolve(generatedAssets, 'js', 'main.js');
const publicAssets = resolve(projectRoot, 'public', 'assets');
const publicBundle = resolve(publicAssets, 'js', 'main.js');

if (!existsSync(generatedAssets) || !existsSync(generatedBundle) || !statSync(generatedBundle).isFile()) {
  throw new Error('Vite output dist/assets/js/main.js does not exist. Run the complete build.');
}

mkdirSync(publicAssets, { recursive: true });
cpSync(generatedAssets, publicAssets, { recursive: true, force: true });

if (!existsSync(publicBundle) || !statSync(publicBundle).isFile()) {
  throw new Error('The public JavaScript bundle could not be published.');
}

const now = new Date();
utimesSync(publicBundle, now, now);
process.stdout.write('Published dist/assets to public/assets without deleting unrelated public files.\n');
