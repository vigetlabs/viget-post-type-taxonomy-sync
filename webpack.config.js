/**
 * External dependencies
 */
const RemoveEmptyScriptsPlugin = require( 'webpack-remove-empty-scripts' );
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,

	entry: {
		admin: path.resolve( process.cwd(), 'src/admin.js' ),
		'admin-style': path.resolve( process.cwd(), 'src/admin.css' ),
	},

	plugins: [ ...defaultConfig.plugins, new RemoveEmptyScriptsPlugin() ],
};
