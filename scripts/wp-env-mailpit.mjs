import { execFileSync } from 'node:child_process';

const containerName = 'mozcheck-mailpit';
const action = process.argv[ 2 ];

function run( command, args, options = {} ) {
	return execFileSync( command, args, {
		encoding: 'utf8',
		...options,
	} );
}

function containerExists() {
	try {
		run( 'docker', [ 'inspect', containerName ], { stdio: 'ignore' } );
		return true;
	} catch {
		return false;
	}
}

function wpEnvStatus() {
	return JSON.parse( run( 'npx', [ 'wp-env', 'status', '--json' ] ) );
}

function startMailpit() {
	const status = wpEnvStatus();
	const project = status.installPath.split( '/' ).filter( Boolean ).at( -1 );
	const network = `${ project }_default`;

	// wp-env recreates its network between starts. Recreate only the disposable
	// container; the named volume preserves captured messages.
	if ( containerExists() ) {
		run( 'docker', [ 'rm', '--force', containerName ], {
			stdio: 'ignore',
		} );
	}

	run(
		'docker',
		[
			'run',
			'--detach',
			'--name',
			containerName,
			'--network',
			network,
			'--network-alias',
			'mailpit',
			'--publish',
			'1025:1025',
			'--publish',
			'8025:8025',
			'--volume',
			'mozcheck-mailpit-data:/data',
			'--env',
			'MP_DATABASE=/data/mailpit.db',
			'axllent/mailpit:latest',
		],
		{ stdio: 'inherit' }
	);

	process.stdout.write( 'Mailpit is available at http://localhost:8025\n' );
}

function stopMailpit() {
	if ( containerExists() ) {
		run( 'docker', [ 'stop', containerName ], { stdio: 'ignore' } );
	}
}

if ( 'start' === action ) {
	run( 'npx', [ 'wp-env', 'start' ], { stdio: 'inherit' } );
	if ( ! process.env.CI ) {
		startMailpit();
	}
} else if ( 'stop' === action ) {
	if ( ! process.env.CI ) {
		stopMailpit();
	}
	run( 'npx', [ 'wp-env', 'stop' ], { stdio: 'inherit' } );
} else if ( 'send' === action ) {
	const php =
		'$result = Mozcheck_Runner::run_manual(); echo wp_json_encode( $result["delivery"] ?? $result );';
	run( 'npx', [ 'wp-env', 'run', 'cli', 'wp', 'eval', php ], {
		stdio: 'inherit',
	} );
	process.stdout.write(
		'Open http://localhost:8025 to inspect the email.\n'
	);
} else {
	throw new Error(
		'Usage: node scripts/wp-env-mailpit.mjs <start|stop|send>'
	);
}
