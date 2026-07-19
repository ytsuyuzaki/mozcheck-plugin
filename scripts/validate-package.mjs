import { resolve } from 'node:path';
import { existsSync, statSync } from 'node:fs';
import AdmZip from 'adm-zip';

const archivePath = resolve( process.argv[ 2 ] ?? 'dist/mozcheck.zip' );
if ( ! existsSync( archivePath ) || ! statSync( archivePath ).isFile() ) {
	throw new Error(
		`Release ZIP does not exist: ${ archivePath }. Run npm run build:zip first.`
	);
}

const entries = new AdmZip( archivePath )
	.getEntries()
	.map( ( entry ) => entry.entryName );
const required = [
	'mozcheck/mozcheck.php',
	'mozcheck/includes/class-mozcheck-plugin.php',
	'mozcheck/languages/mozcheck.pot',
	'mozcheck/uninstall.php',
	'mozcheck/readme.txt',
];

for ( const path of required ) {
	if ( ! entries.includes( path ) ) {
		throw new Error( `Release ZIP is missing ${ path }` );
	}
}

for ( const path of entries ) {
	if ( ! path.startsWith( 'mozcheck/' ) ) {
		throw new Error( `Invalid ZIP root entry: ${ path }` );
	}
	if (
		/^mozcheck\/(node_modules|vendor|tests|\.git|\.github)\//.test( path )
	) {
		throw new Error( `Development file found in ZIP: ${ path }` );
	}
}

process.stdout.write( `Release ZIP is valid: ${ archivePath }\n` );
