<?php
namespace WP2FA\Utils;

use WP2FA\Utils\SettingsUtils as SettingsUtils;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly

/**
 * Abstract AMigration class
 */
if ( ! class_exists( '\WP2FA\Utils\AbstractMigration' ) ) {

    /**
     * Utility class to ease the migration process.
     *
     * Every migration must go in its own method
     * The naming convention is migrateUpTo_XXX where XXX is the number of the version,
     * format is numbers only.
     * Example: migration for version upto 1.4 must be in migrateUpTo_14 method
     *
     * The numbers in the names of the methods must have exact numbers count as in the selected
     * version in use, even if there are silent numbers for some of the major versions as 1, 2, 3 etc. (the .0.0 is skipped / silent)
     * Example:
     *  - if X.X.X is selected for version number, then for version 1.1 method must have "...migrateUpTo_110..." in its name
     *  - if X.X is selected for version number, then for version 1, method must have "...migrateUpTo_10..." in its name
     *
     * Note: you can add prefix to the migration method, if that is necessary, but "migrateUpTo_" is a must -
     * the name must contain that @see getAllMigrationMethodsAsNumbers of that class.
     * For version extraction the number following the last '_' will be used
     * TODO: the mandatory part of the method name can be a setting in the class, but is that a good idea?
     *
     * Note: order of the methods is not preserved - version numbers will be used for ordering
     *
     * @package WP2FA\Utils
     * @since 1.6
     */
    class AbstractMigration {

        /**
         * Extracted version from the DB (WP option)
         */
        protected static $storedVersion = '';

        /**
         * The name of the option from which we should extact version
         * Note: version is expected in version format - 1.0.0; 1; 1.0; 1.0.0.0
         * Note: only numbers will be processed
         */
        protected static $versionOptionName = '';

        /**
         * The constant name where the plugin version is stored
         * Note: version is expected in version format - 1.0.0; 1; 1.0; 1.0.0.0
         * Note: only numbers will be processed
         */
        protected static $constNameOfPluginVersion = '';

        /**
         * Used for adding proper pads for the missing numbers
         * Version number format used here depends on selection for how many numbers will be used for representing version
         *
         * for X.X     use 2;
         * for X.X.X   use 3;
         * for X.X.X.X use 4;
         *
         * etc.
         *
         * Example: if selected version format is X.X.X that means that 3 digits are used for versioning.
         * And current version is stored as 2 (no suffix 0.0) that means that it will be normalized as 200.
         */
        protected static $padLength = 3;

        /**
         * Collects all the migration methods which needs to be executed in order and executes them
         *
         * @return void
         */
        public static function migrate() {

            if ( version_compare( static::getStoredVersion(), \constant( static::$constNameOfPluginVersion ), '<' ) ) {

                $storedVersionAsNumber  = static::normalizeVersion( static::getStoredVersion() );
                $targetVersionAsNumber  = static::normalizeVersion( \constant( static::$constNameOfPluginVersion ) );
                $methodAsVersionNumbers = static::getAllMigrationMethodsAsNumbers();

                $migrateMethods = array_filter(
                    $methodAsVersionNumbers,
                    function( $method, $key ) use ( &$storedVersionAsNumber, &$targetVersionAsNumber ) {
                        if ( $targetVersionAsNumber > $storedVersionAsNumber ) {
                            return ( in_array( $key, range( $storedVersionAsNumber, $targetVersionAsNumber ) ) );
                        }

                        return false;
                    },
                    ARRAY_FILTER_USE_BOTH
                );

                if ( ! empty( $migrateMethods ) ) {
                    \ksort( $migrateMethods );
                    foreach ( $migrateMethods as $method ) {
                        static::{$method}();
                    }
                }

                self::storeUpdatedVersion();
            }
        }

        /**
         * Extracts currently stored version from the DB
         *
         * @return string
         */
        private static function getStoredVersion() {

            if ( '' === trim( static::$storedVersion ) ) {
                static::$storedVersion = SettingsUtils::get_option( static::$versionOptionName, '0.0.0' );
            }

            return static::$storedVersion;
        }

        /**
         * Stores the version to which we migrated
         *
         * @return void
         */
        private static function storeUpdatedVersion() {
            SettingsUtils::update_option( static::$versionOptionName, \constant( static::$constNameOfPluginVersion ) );
        }

        /**
         * Normalized the version numbers to numbers
         *
         * Version format is expected to be as follows:
         * X.X.X
         *
         * All non numeric values will be removed from the version string
         *
         * Note: version is expected in version format - 1.0.0; 1; 1.0; 1.0.0.0
         * Note: only numbers will be processed
         *
         * @param string $version
         *
         * @return string
         */
        private static function normalizeVersion( string $version ) {
            $versionAsNumber = (int) filter_var( $version, FILTER_SANITIZE_NUMBER_INT );

            if ( self::$padLength > strlen( $versionAsNumber ) ) {
                $versionAsNumber = str_pad( $versionAsNumber, static::$padLength, '0', STR_PAD_RIGHT );
            }

            return $versionAsNumber;
        }

        /**
         * Collects all the migration methods from the class and stores them in the array
         * Array is in following format:
         * key - number of the version
         * value - name of the method
         *
         * @return array
         */
        private static function getAllMigrationMethodsAsNumbers() {
            $classMethods = \get_class_methods( get_called_class() );

            $methodAsVersionNumbers = [];
            foreach ( $classMethods as $method ) {
                if ( false !== \strpos( $method, 'migrateUpTo_' ) ) {
                    $ver                            = \substr( $method, \strrpos( $method, '_' ) + 1, \strlen( $method ) );
                    $methodAsVersionNumbers[ $ver ] = $method;
                }
            }

            return $methodAsVersionNumbers;
        }
    }
}
