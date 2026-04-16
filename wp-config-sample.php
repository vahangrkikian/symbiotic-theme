<?php
/**
 * Symbiotic Theme — WordPress Configuration Sample
 *
 * SETUP INSTRUCTIONS:
 * 1. Copy this file to "wp-config.php"
 * 2. Create a MySQL database named "symbiotic_db"
 * 3. Import the database dump:  mysql -u root symbiotic_db < symbiotic_db.sql
 * 4. Update DB_NAME, DB_USER, DB_PASSWORD below
 * 5. Generate fresh salts at: https://api.wordpress.org/secret-key/1.1/salt/
 *
 * @package WordPress
 */

// ** Database settings — update these for your environment ** //
define( 'DB_NAME', 'symbiotic_db' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

/**
 * Authentication unique keys and salts.
 *
 * IMPORTANT: Replace every line below with output from:
 * https://api.wordpress.org/secret-key/1.1/salt/
 */
define( 'AUTH_KEY',         'put your unique phrase here' );
define( 'SECURE_AUTH_KEY',  'put your unique phrase here' );
define( 'LOGGED_IN_KEY',    'put your unique phrase here' );
define( 'NONCE_KEY',        'put your unique phrase here' );
define( 'AUTH_SALT',        'put your unique phrase here' );
define( 'SECURE_AUTH_SALT', 'put your unique phrase here' );
define( 'LOGGED_IN_SALT',   'put your unique phrase here' );
define( 'NONCE_SALT',       'put your unique phrase here' );

/**
 * WordPress database table prefix.
 * MUST be "sym_" to match the included database dump.
 */
$table_prefix = 'sym_';

/* Add any custom values between this line and the "stop editing" line. */

define( 'WP_DEBUG', false );
define( 'WP_MEMORY_LIMIT', '256M' );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
