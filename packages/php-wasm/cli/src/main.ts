/**
 * A CLI script that runs PHP CLI via the WebAssembly build.
 */
import { writeFileSync, existsSync, readdirSync, lstatSync } from 'fs';
import { rootCertificates } from 'tls';

import { PHP, loadPHPRuntime, getPHPLoaderModule } from '@php-wasm/node';

let args = process.argv.slice(2);
if (!args.length) {
	args = ['--help'];
}

// Write the ca-bundle.crt file to disk so that PHP can find it.
const caBundlePath = new URL('ca-bundle.crt', (import.meta || {}).url).pathname;
if (!existsSync(caBundlePath)) {
	writeFileSync(caBundlePath, rootCertificates.join('\n'));
}

async function main() {
	// @ts-ignore
	const defaultPhpIniPath = await import('./php.ini');

	const phpVersion = process.env['PHP'] || '8.2';

	// This dynamic import only works after the build step
	// when the PHP files are present in the same directory
	// as this script.
	const phpLoaderModule = await getPHPLoaderModule(phpVersion);
	const loaderId = await loadPHPRuntime(phpLoaderModule, {
		ENV: {
			...process.env,
			TERM: 'xterm',
		},
	});
	const hasMinusCOption = args.some((arg) => arg.startsWith('-c'));
	if (!hasMinusCOption) {
		args.unshift('-c', defaultPhpIniPath);
	}
	const php = new PHP(loaderId);

	// Mount all the root directories
	const dirs = readdirSync('/')
		.map((file) => `/${file}`)
		.filter((file) => lstatSync(file).isDirectory());
	for (const dir of dirs) {
		if (!php.fileExists(dir)) {
			php.mkdirTree(dir);
		}
		php.mount({ root: dir }, dir);
	}
	php.chdir(process.cwd());

	php.writeFile(caBundlePath, rootCertificates.join('\n'));
	args.unshift('-d', `openssl.cafile=${caBundlePath}`);
	php.cli(['php', ...args]).catch((result) => {
		if (result.name === 'ExitStatus') {
			process.exit(result.status === undefined ? 1 : result.status);
		}
		throw result;
	});
}

main();
