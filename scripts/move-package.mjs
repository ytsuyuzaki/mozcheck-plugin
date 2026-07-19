import { mkdirSync, renameSync } from 'node:fs';

mkdirSync( 'dist', { recursive: true } );
renameSync( 'mozcheck.zip', 'dist/mozcheck.zip' );
