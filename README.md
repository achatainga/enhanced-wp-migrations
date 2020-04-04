# Enhanced WordPress Migrations

A WordPress library for managing database table schema upgrades and data seeding.

Ever had to create a custom table for some plugin or custom code to use? To keep the site updated with the latest version of that table you need to keep track of what version the table is at. This can get overly complex for lots of tables.

This package is forked from [deliciousbrains/wp-migrations](https://github.com/deliciousbrains/wp-migrations) with some improvements.

This package is inspired by [Laravel's database migrations](https://laravel.com/docs/5.8/migrations). You create a new migration PHP file, add your schema update code, and optionally include a rollback method to reverse the change.

Simply run `wp dbi migrate` on the command line using WP CLI and any migrations not already run will be executed.

The great thing about making database schema and data updates with migrations, is that the changes are file-based and therefore can be stored in version control, giving you better control when working across different branches.

### Requirements

This package is designed to be used on a WordPress site project, not for a plugin or theme.

It needs to be running PHP 5.4 or higher.

You need to have access to run WP CLI on the server. Typically `wp dbi migrate` will be run as a last stage build step in your deployment process.

### Key Differences from wp-migrations
- Removed support for multiple migration directories. I've made assumption that all of your migration files should be located at one place only. So that the package can follow [SemVer](https://semver.org/) in the migration names and filenames (Similar to [Flyway](https://flywaydb.org/)).
- Individual migrations will be identified with their respective semver number. E.g., `1`, `1.0.1`, `1.1` etc.
- `wp edbm migrate` command will use the version number of the migration instead of the class name. E.g., `wp edbm migrate 2.0.1`
- Migration file name conventions is `<version-number>.php`. E.g., `1.php`, `1.0.1.php`, `2.1.php` etc. Usually, you can start your migrations from `0.0.1.php`.
- Make sure you put only one class in one migration file.

### Installation

- `composer require desaiuditd/enhanced-wp-migrations`
- Bootstrap the package by adding `\EnhancedWPMigrations\Database\Migrator::instance();` to an mu-plugin.
- Run `wp edbm migrate --setup` on the server.

### Migrations

By default, the command will look for migration files in `/app/migrations` directory alongside the vendor folder. This can be altered with the filter `edbm_wp_migrations_path`.

An example migration to create a table would look like:

```
<!-- 0.0.1.php -->
<?php

use EnhancedWPMigrations\Database\AbstractMigration;

class AddCustomTable extends AbstractMigration {

    public function run() {
        global $wpdb;

        $sql = "
            CREATE TABLE " . $wpdb->prefix . "my_table (
            id bigint(20) NOT NULL auto_increment,
            some_column varchar(50) NOT NULL,
            PRIMARY KEY (id)
            ) {$this->get_collation()};
        ";

        dbDelta( $sql );
    }

    public function rollback() {
        global $wpdb;
        $wpdb->query( 'DROP TABLE ' . $wpdb->prefix . 'my_table');
    }
}
```

We are also using the migrations to deploy development data changes at deployment time. Instead of trying to merge the development database into the production one.

For example, to add a new page:

```
<!-- 0.0.1.php -->
<?php

use EnhancedWPMigrations\Database\AbstractMigration;

class AddPricingPage extends AbstractMigration {

    public function run() {
        $pricing_page_id = wp_insert_post( array(
            'post_title'  => 'Pricing',
            'post_status' => 'publish',
            'post_type'   => 'page',
        ) );
        update_post_meta( $pricing_page_id, '_wp_page_template', 'page-pricing.php' );
    }
}
```

### Use

You can run specific migrations using the filename as an argument, eg. `wp edbm migrate 2.1.1`.

To rollback all migrations you can run `wp edbm migrate --rollback`, or just a specific migration `wp edbm migrate 2.1.1 --rollback`.
