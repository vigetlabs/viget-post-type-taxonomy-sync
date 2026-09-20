/**
 * Syncs the version from package.json into the plugin header/constant and readme.txt.
 *
 * Run automatically by npm's "version" lifecycle (see the "version" script in
 * package.json), which fires after `npm version <patch|minor|major>` bumps
 * package.json but before it commits and tags. This keeps every version string
 * in the repo in lockstep with a single `npm run release -- <bump>` command.
 *
 * Refuses to run when readme.txt has no changelog heading for the new version.
 * 2.0.1 shipped with a Stable tag and no entry because this script only ever
 * rewrote version strings, so the changelog drifted silently.
 *
 * Everything is validated before anything is written, so a failed run leaves the
 * working tree alone apart from the bump `npm version` already made.
 */

const fs = require( 'fs' );
const path = require( 'path' );

const rootDir = path.resolve( __dirname, '..' );
const pkg = require( path.join( rootDir, 'package.json' ) );
const version = pkg.version;

const pluginFile = path.join( rootDir, 'viget-post-type-taxonomy-sync.php' );
const readmeFile = path.join( rootDir, 'readme.txt' );

const PLUGIN_HEADER = /(\* Version:\s*)([^\r\n]+)/;
const PLUGIN_CONSTANT = /(define\(\s*'VGPTTS_PLUGIN_VERSION',\s*')([^']+)(')/;
const STABLE_TAG = /(Stable tag:\s*)([^\r\n]+)/;

function assertMatch( content, pattern, file ) {
	if ( ! pattern.test( content ) ) {
		throw new Error( `Could not find pattern ${ pattern } in ${ file }` );
	}
}

let pluginContents = fs.readFileSync( pluginFile, 'utf8' );
let readmeContents = fs.readFileSync( readmeFile, 'utf8' );

assertMatch( pluginContents, PLUGIN_HEADER, pluginFile );
assertMatch( pluginContents, PLUGIN_CONSTANT, pluginFile );
assertMatch( readmeContents, STABLE_TAG, readmeFile );

// A release with no changelog entry is a release nobody can read.
const changelogHeading = new RegExp(
	`^=\\s*${ version.replace( /\./g, '\\.' ) }\\s*=\\s*$`,
	'm'
);

if ( ! changelogHeading.test( readmeContents ) ) {
	throw new Error(
		`readme.txt has no changelog entry for ${ version }.\n` +
			`Add a "= ${ version } =" section under "== Changelog ==" describing this release, then re-run.`
	);
}

pluginContents = pluginContents
	.replace( PLUGIN_HEADER, `$1${ version }` )
	.replace( PLUGIN_CONSTANT, `$1${ version }$3` );
readmeContents = readmeContents.replace( STABLE_TAG, `$1${ version }` );

fs.writeFileSync( pluginFile, pluginContents );
fs.writeFileSync( readmeFile, readmeContents );

// eslint-disable-next-line no-console
console.log( `Synced version ${ version } into plugin header and readme.txt` );
