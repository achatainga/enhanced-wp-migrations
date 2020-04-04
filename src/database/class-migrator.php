<?php

namespace EnhancedWPMigrations\Database;

use EnhancedWPMigrations\CLI\Command;

class Migrator {

	/**
	 * @var Migrator
	 */
	private static $instance;

	protected $table_name = 'edbrns_migrations';

	/**
	 * @param string $command_name
	 *
	 * @return Migrator Instance
	 */
	public static function instance( $command_name = 'edbi' ) {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Migrator ) ) {
			self::$instance = new Migrator();
			self::$instance->init( $command_name );
		}

		return self::$instance;
	}

	/**
	 * @param string $command_name
	 */
	public function init( $command_name ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( $command_name . ' migrate', Command::class );
		}
	}

	/**
	 * Set up the table needed for storing the migrations.
	 *
	 * @return bool
	 */
	public function setup() {
		global $wpdb;

		$table = $wpdb->prefix . $this->table_name;

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			return false;
		}

		$collation = ! $wpdb->has_cap( 'collation' ) ? '' : $wpdb->get_charset_collate();

		// Create migrations table
		$sql = 'CREATE TABLE ' . $table . " (
			id bigint(20) NOT NULL auto_increment,
			version varchar(255) NOT NULL,
			date_ran datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id)
			) {$collation};";

		dbDelta( $sql );

		return true;
	}

	/**
	 * Get all the migration files
	 *
	 * @param array       $exclude   Filenames without extension to exclude
	 * @param string|null $migration Single migration class name to only perform the migration for
	 * @param bool        $rollback
	 *
	 * @return array
	 */
	protected function get_migrations( $exclude = [], $migration = null, $rollback = false ) {
		$all_migrations = [];

		$base_path = __FILE__;
		while ( basename( $base_path ) !== 'vendor' ) {
			$base_path = dirname( $base_path );
		}

		$path = apply_filters( 'edbi_wp_migrations_path', dirname( $base_path ) . '/app/migrations' );
		$migrations = glob( trailingslashit( $path ) . '*.php' );

		if ( empty( $migrations ) ) {
			return $all_migrations;
		}

		usort(
			$migrations,
			[ $this, 'sort_migrations' ]
		);

		foreach ( $migrations as $filename ) {
			$version = basename( $filename, '.php' );
			if ( ! $rollback && in_array( $version, $exclude, true ) ) {
				// The migration can't have been run before
				continue;
			}

			if ( $rollback && ! in_array( $version, $exclude, true ) ) {
				// As we are rolling back, it must have been run before
				continue;
			}

			if ( $migration && $version !== $migration ) {
				continue;
			}

			$all_migrations[ $filename ] = $version;
		}

		return $all_migrations;
	}

	/**
	 * Get all the migrations to be run
	 *
	 * @param string|null $migration
	 * @param bool        $rollback
	 *
	 * @return array
	 */
	protected function get_migrations_to_run( $migration = null, $rollback = false ) {
		global $wpdb;
		$table = $wpdb->prefix . $this->table_name;
		$ran_migrations = $wpdb->get_col( $wpdb->prepare( 'SELECT version FROM %s', $table ) );

		$migrations = $this->get_migrations( $ran_migrations, $migration, $rollback );

		return $migrations;
	}

	/**
	 * Run the migrations
	 *
	 * @param string|null $migration
	 * @param bool        $rollback
	 *
	 * @return int
	 */
	public function run( $migration = null, $rollback = false ) {
		global $wpdb;
		$table = $wpdb->prefix . $this->table_name;
		$count      = 0;
		$migrations = $this->get_migrations_to_run( $migration, $rollback );
		if ( empty( $migrations ) ) {
			return $count;
		}

		foreach ( $migrations as $file => $version ) {
			$prev_classes = get_declared_classes();

			include $file;

			$diff = array_diff( get_declared_classes(), $prev_classes );
			$migration_class = reset( $diff );

			if ( false === $migration_class ) {
				continue;
			}

			$migration = new $migration_class();
			$method    = $rollback ? 'rollback' : 'run';
			if ( ! method_exists( $migration, $method ) ) {
				continue;
			}

			$migration->{$method}();
			$count++;

			if ( $rollback ) {
				$wpdb->delete( $table, [ 'version' => $version ] );
				continue;
			}

			$wpdb->insert(
				$table,
				[
					'version'  => $version,
					'date_ran' => gmdate( 'Y-m-d H:i:s' ),
				]
			);
		}

		return $count;
	}

	protected function sort_migrations( $mi1, $mi2 ) {
		$ver1 = str_replace(
			'.php',
			'',
			str_replace(
				trailingslashit( $path ),
				'',
				$mi1
			)
		);

		$ver2 = str_replace(
			'.php',
			'',
			str_replace(
				trailingslashit( $path ),
				'',
				$mi2
			)
		);

		return version_compare( $ver1, $ver2 );
	}

	/**
	 * Protected constructor to prevent creating a new instance of the
	 * class via the `new` operator from outside of this class.
	 */
	protected function __construct() {
	}

	/**
	 * As this class is a singleton it should not be clone-able
	 */
	protected function __clone() {
	}

	/**
	 * As this class is a singleton it should not be able to be unserialized
	 */
	protected function __wakeup() {
	}
}
