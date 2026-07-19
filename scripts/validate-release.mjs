import { readFileSync } from 'node:fs';

const pkg = JSON.parse( readFileSync( 'package.json', 'utf8' ) );
const plugin = readFileSync( 'mozcheck.php', 'utf8' );
const readme = readFileSync( 'readme.txt', 'utf8' );
const versions = [
	pkg.version,
	plugin.match( /^ \* Version:\s*(\S+)/m )?.[ 1 ],
	plugin.match( /define\( 'MOZCHECK_VERSION', '([^']+)' \)/ )?.[ 1 ],
	readme.match( /^Stable tag:\s*(\S+)/m )?.[ 1 ],
];

if (
	! /^\d+\.\d+\.\d+$/.test( pkg.version ) ||
	versions.some( ( version ) => version !== pkg.version )
) {
	throw new Error(
		`Release versions do not match: ${ versions.join( ', ' ) }`
	);
}

const tagIndex = process.argv.indexOf( '--tag' );
if ( tagIndex !== -1 && process.argv[ tagIndex + 1 ] !== `v${ pkg.version }` ) {
	throw new Error( `Tag must be v${ pkg.version }` );
}

process.stdout.write( `Release version is valid: v${ pkg.version }\n` );
