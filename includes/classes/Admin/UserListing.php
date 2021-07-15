<?php

namespace WP2FA\Admin;

use WP2FA\Utils\UserUtils;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly

/**
 * UserListing class with user listing filters
 */
if ( ! class_exists( '\WP2FA\Admin\UserListing' ) ) {

    /**
     * UserListing - Shows extra column in user table wit WP2FA status forevery user
     */
    class UserListing {

        /**
         * The users table column name
         *
         * @var string
         */
        private static $columnName = '2fa-status';

        /**
         * Inits all the hooks used for showing the extra user data in the users column
         *
         * @return void
         */
        public static function init() {
            add_filter( 'manage_users_columns', [ __CLASS__, 'addWP2FAColumn' ] );
            add_filter( 'wpmu_users_columns', [ __CLASS__, 'addWP2FAColumn' ] );
            add_filter( 'manage_users_custom_column', [ __CLASS__, 'showColumnData' ], 10, 3 );
        }

        /**
         * Sets the column in the admin users table
         *
         * @param array $columns
         *
         * @return array
         */
        public static function addWP2FAColumn( array $columns ): array {
            $columns[ self::$columnName ] = __( '2FA Status', 'wp-2fa' );
            return $columns;
        }

        /**
         * Shows the user WP 2FA status data in the users table
         *
         * @param [type] $value
         * @param string $columnName
         * @param [type] $userId
         *
         * @return mixed
         */
        public static function showColumnData( $value, string $columnName, $userId ) {

	        switch ( $columnName ) {
		        case self::$columnName:
			        return self::getUser2faStatus( $userId );
		        default:
	        }

            return $value;
        }

	    /**
	     * Retrieves the translated 2FA status label for given user.
	     *
	     * This is performance optimized version that bypasses the User class on purpose. It loads the 2FA status meta
	     * field directly and turns it into a label.
	     *
	     * There is also some temporary code to figure out the 2FA status meta field if it doesn't exist. This will be
	     * removed in future versions and exist purely so we don't end up with no values in the column after migration
	     * to version 1.7.0 when this was introduced.
	     *
	     * @param int $userId
	     *
	     * @return string
	     * @see WP2FA\Admin\User
	     * @since 1.7.0
	     */
	    private static function getUser2faStatus( $userId ) {
		    //  try to get the user status "id" from user's meta data
		    $status_meta_value = get_user_meta( $userId, WP_2FA_PREFIX . '2fa_status', true );
		    if ( ! empty( $status_meta_value ) ) {
			    //  the status id is available, grab the label to display
			    $status_data = UserUtils::extractStatuses( [ $status_meta_value ] );
			    if ( ! empty( $status_data ) ) {
				    return $status_data['label'];
			    }
		    }

		    //  If the user status is not saved in user meta (this can be the case prior to version 1.7.0), we figure it
		    //  out and store it against the user in DB. This is not ideal in terms of performance and this is only
		    //  a temporary solution.
		    //  @todo remove this in future versions
		    return User::setUserStatus( new \WP_User( $userId ) );
	    }

        /**
         * Returns the users table column name
         *
         * @return string
         */
        public static function getColumnName(): string {
            return self::$columnName;
        }
    }
}
