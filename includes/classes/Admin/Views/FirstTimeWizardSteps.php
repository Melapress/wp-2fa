<?php

namespace WP2FA\Admin\Views;

use WP2FA\WP2FA;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly

/**
 * WP2FA First Wizard Settings view controller
 *
 * @since 1.7
 */
class FirstTimeWizardSteps {

    /**
     * Select method step
     *
     * @since 1.7.0
     *
     * @param boolean $setupWizard
     *
     * @return void
     */
    public static function selectMethod( $setupWizard = false ) {
        ?>
        <h3><?php esc_html_e( 'Which two-factor authentication methods can your users use?', 'wp-2fa' ); ?></h3>
        <p class="description">
            <?php esc_html_e( 'When you disable one of the below 2FA methods none of your users can use it.', 'wp-2fa' ); ?>
        </p>
        <?php
        if ( ! $setupWizard ) {
            ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th><label for="2fa-method"><?php esc_html_e( 'Select the methods', 'wp-2fa' ); ?></label></th>
                    <td>
        <?php } ?>
                        <fieldset id="2fa-method-select">
                        <div><em><?php esc_html_e( 'Primary 2FA methods:', 'wp-2fa')?></em></div>
                        <br>
                        <label for="totp">
                            <input type="checkbox" id="totp" name="wp_2fa_settings[enable_totp]" value="enable_totp"
                            <?php checked( 'enable_totp', WP2FA::get_wp2fa_setting( 'enable_totp' ), true ); ?>
                            >
                            <?php esc_html_e( 'One-time code via 2FA App (TOTP) - ', 'wp-2fa' ); ?><a href="https://www.wpwhitesecurity.com/support/kb/configuring-2fa-apps/?utm_source=plugin&utm_medium=referral&utm_campaign=WP2FA&utm_content=settings+pages" target="_blank" rel=noopener><?php esc_html_e( 'complete list of supported 2FA apps.', 'wp-2fa' ); ?></a>
                        </label>
                        <?php
                        if ( $setupWizard ) {
                            echo '<p class="description">';
                            printf(
                                /* translators: link to the knowledge base website */
                                esc_html__( 'Refer to the %s for more information on how to setup these apps and which apps are supported.', 'wp-2fa' ),
                                '<a href="https://www.wpwhitesecurity.com/support/kb/configuring-2fa-apps/" target="_blank">' . esc_html__( '2FA apps article on our knowledge base', 'wp-2fa' ) . '</a>'
                            );
                            echo '</p>';
                        }
                        ?>
                        <br/>
                        <label for="hotp">
                            <input type="checkbox" id="hotp" name="wp_2fa_settings[enable_email]" value="enable_email"
                            <?php checked( WP2FA::get_wp2fa_setting( 'enable_email' ), 'enable_email' ); ?>
                            >
                            <?php esc_html_e( 'One-time code via email (HOTP)', 'wp-2fa' ); ?>
                            <?php
                            if ( $setupWizard ) {
                                echo '<p class="description">';
                            } else {
                                echo ' - ';
                            }
                            printf( '%1$s <a href="https://wordpress.org/plugins/wp-mail-smtp/" target="_blank">%2$s</a>.', esc_html__( 'Email reliability and deliverability is important when using this method, otherwise you might have problems logging in. To ensure emails are always delivered we recommend using the free plugin', 'wp-2fa' ), esc_html__( 'WP Mail SMTP', 'wp-2fa' ) );
                            if ( $setupWizard ) {
                                echo '</p>';
                            }
                            ?>
                        </label>
                        <br />
                        <?php
                        $class = '';

                        if ( false === WP2FA::get_wp2fa_setting( 'enable_totp' ) && false === WP2FA::get_wp2fa_setting( 'enable_email' ) ) {
                            $class = ' class="disabled"';
                        }
                        ?>
                        <div><em><?php esc_html_e( 'Secondary 2FA methods:', 'wp-2fa')?></em></div>
                        <br>
                        <label for="backup-codes" <?php echo $class; ?>>
                            <input <?php echo $class; ?> type="checkbox" id="backup-codes" name="wp_2fa_settings[backup_codes_enabled]" value="yes"
                            <?php checked( WP2FA::get_wp2fa_setting( 'backup_codes_enabled' ), 'yes' ); ?>
                            >
                            <?php
                            esc_html_e( 'Backup codes', 'wp-2fa' );
                            if ( $setupWizard ) {
                                echo '<p class="description">Note: ';
                            } else {
                                echo ' - ';
                            }
                            esc_html_e( 'Backup codes are a secondary method which you can use to log in to the website in case the primary 2FA method is unavailable. Therefore they can\'t be enabled and used as a primary method.', 'wp-2fa' );
                            if ( $setupWizard ) {
                                echo '</p>';
                            }
                            ?>
                        </label>
                        <br />
                        </fieldset>
                        <?php
                        if ( ! $setupWizard ) {
                            ?>
                    </td>
                </tr>
            </tbody>
        </table>
                        <?php } ?>
        <?php
    }

    /**
     * Enforcement policy step
     *
     * @since 1.7.0
     *
     * @param boolean $setupWizard
     *
     * @return void
     */
    public static function enforcementPolicy( $setupWizard = false ) {
        ?>
        <h3><?php esc_html_e( 'Do you want to enforce 2FA for some, or all the users? ', 'wp-2fa' ); ?></h3>
        <p class="description">
            <?php esc_html_e( 'When you enforce 2FA the users will be prompted to configure 2FA the next time they login. Users have a grace period for configuring 2FA. You can configure the grace period and also exclude user(s) or role(s) in this settings page. ', 'wp-2fa' ); ?> <a href="https://www.wpwhitesecurity.com/support/kb/configure-2fa-policies-enforce/?utm_source=plugin&utm_medium=referral&utm_campaign=WP2FA&utm_content=settings+pages" target="_blank" rel=noopener><?php esc_html_e( 'Learn more.', 'wp-2fa' ); ?></a>
        </p>
            <?php
            if ( ! $setupWizard ) {
                ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th><label for="enforcement-policy"><?php esc_html_e( 'Enforce 2FA on', 'wp-2fa' ); ?></label></th>
                    <td>
            <?php } ?>
                        <fieldset class="contains-hidden-inputs">
                            <label for="all-users">
                                <input type="radio" name="wp_2fa_settings[enforcement-policy]" id="all-users" value="all-users"
                                <?php checked( WP2FA::get_wp2fa_setting( 'enforcement-policy' ), 'all-users' ); ?>
                                >
                            <span><?php esc_html_e( 'All users', 'wp-2fa' ); ?></span>
                            </label>
                            <br/>

                            <?php if ( WP2FA::is_this_multisite() ) : ?>
                                <label for="superadmins-only">
                                    <input type="radio" name="wp_2fa_settings[enforcement-policy]" id="superadmins-only" value="superadmins-only"
                                            <?php checked( WP2FA::get_wp2fa_setting( 'enforcement-policy' ), 'superadmins-only' ); ?> />
                                    <span><?php esc_html_e( 'Only super admins', 'wp-2fa' ); ?></span>
                                </label>
                                <br/>
                                <label for="superadmins-siteadmins-only">
                                    <input type="radio" name="wp_2fa_settings[enforcement-policy]" id="superadmins-siteadmins-only" value="superadmins-siteadmins-only"
                                            <?php checked( WP2FA::get_wp2fa_setting( 'enforcement-policy' ), 'superadmins-siteadmins-only' ); ?> />
                                    <span><?php esc_html_e( 'Only super admins and site admins', 'wp-2fa' ); ?></span>
                                </label>
                                <br/>
                            <?php endif; ?>

                            <label for="certain-roles-only">
                                <?php $checked = in_array( WP2FA::get_wp2fa_setting( 'enforcement-policy' ), [ 'certain-roles-only', 'certain-users-only' ] ); ?>
                                <input type="radio" name="wp_2fa_settings[enforcement-policy]" id="certain-roles-only" value="certain-roles-only"
                                <?php ( $setupWizard ) ? checked( WP2FA::get_wp2fa_setting( 'enforcement-policy' ), 'certain-roles-only' ) : checked( $checked ); ?>
                                data-unhide-when-checked=".certain-roles-only-inputs, .certain-users-only-inputs">
                                <span><?php esc_html_e( 'Only for specific users and roles', 'wp-2fa' ); ?></span>
                            </label>
                            <fieldset class="hidden certain-users-only-inputs">
                                <div>
                                    <p>
                                        <label for="enforced_users-multi-select"><?php esc_html_e( 'Users :', 'wp-2fa' ); ?></label> <select multiple="multiple" id="enforced_users-multi-select" name="wp_2fa_settings[enforced_users][]" style=" display:none;width:<?php echo ( $setupWizard ) ? '100' : '50'; ?>%">
                                        <?php
                                        $excludedUsers = WP2FA::get_wp2fa_setting( 'enforced_users' );
                                        foreach ( $excludedUsers as $user ) {
                                            ?>
                                                        <option selected="selected" value="<?php echo $user; ?>"><?php echo $user; ?></option>
                                                <?php
                                        }
                                        ?>
                                        </select>
                                    </p>
                                </div>
                                <br/>
                            </fieldset>
                            <fieldset class="hidden certain-roles-only-inputs">
                                <div>
                                    <p style="margin-top: 0;">
                                        <label for="enforced-roles-multi-select"><?php esc_html_e( 'Roles :', 'wp-2fa' ); ?></label> 
                                        <select multiple="multiple" id="enforced-roles-multi-select" name="wp_2fa_settings[enforced_roles][]" style=" display:none;width:<?php echo ( $setupWizard ) ? '100' : '50'; ?>%">
                                        <?php
                                        $allRoles      = \WP2FA\WP2FA::wp_2fa_get_roles();
                                        $enforcedRoles = WP2FA::get_wp2fa_setting( 'enforced_roles' );
                                        foreach ( $allRoles as $role => $roleName ) {
                                            $selected = '';
                                            if ( in_array( $role, $enforcedRoles ) ) {
                                                $selected = 'selected="selected"';
                                            }
                                            ?>
                                                        <option <?php echo $selected; ?> value="<?php echo strtolower( $role ); ?>"><?php echo $roleName; ?></option>
                                                <?php
                                        }
                                        ?>
                                        </select>
                                    </p>
                                </div>
                                    <?php if ( WP2FA::is_this_multisite() ) { ?>
                                <div style="margin-left: 70px">
                                    <input type="checkbox" name="wp_2fa_settings[superadmins-role-add]" id="superadmins-role-add" value="yes"
                                            <?php checked( WP2FA::get_wp2fa_setting( 'superadmins-role-add' ), 'yes' ); ?> />
                                    <label for="superadmins-role-add"><?php esc_html_e( 'Also enforce 2FA on network users with super admin privileges', 'wp-2fa' ); ?></label>
                                </div>
                                <?php } ?>
                            </fieldset>
                            <br/>
                    <?php if ( WP2FA::is_this_multisite() ) { ?>
                            <div>
                                <label for="enforce-on-multisite">
                                    <input type="radio" name="wp_2fa_settings[enforcement-policy]" id="enforce-on-multisite" value="enforce-on-multisite"
                                        <?php checked( WP2FA::get_wp2fa_setting( 'enforcement-policy' ), 'enforce-on-multisite' ); ?>
                                    data-unhide-when-checked=".all-sites">
                                    <span><?php esc_html_e( 'These sub-sites', 'wp-2fa' ); ?></span>
                                </label>
                                <fieldset class="hidden all-sites">
                                    <p>
                                        <label for="slim-multi-select"><?php esc_html_e( 'Sites :', 'wp-2fa' ); ?></label> <select multiple="multiple" id="slim-multi-select" name="wp_2fa_settings[included_sites][]" style="display:none; width:<?php echo ( $setupWizard ) ? '100' : '50'; ?>%">
                                            <?php
                                            $selectedSites = WP2FA::get_wp2fa_setting( 'included_sites' );
                                            foreach ( WP2FA::getMultiSites() as $site ) {
                                                $args = [
                                                    'blog_id' => $site->blog_id,
                                                ];

                                                $currentBlogDetails = get_blog_details( $args );
                                                $selected           = '';
                                                if ( in_array( $site->blog_id, $selectedSites ) ) {
                                                    $selected = 'selected="selected"';
                                                }
                                                ?>
                                                <option <?php echo $selected; ?> value="<?php echo $site->blog_id; ?>"><?php echo $currentBlogDetails->blogname; ?></option>
                                                <?php
                                            }
                                            ?>
                                        </select>
                                    </p>
                                </fieldset>
                            </div>
                    <?php } ?>
                            <div>
                                <label for="do-not-enforce">
                                    <input type="radio" name="wp_2fa_settings[enforcement-policy]" id="do-not-enforce" value="do-not-enforce"
                                    <?php checked( WP2FA::get_wp2fa_setting( 'enforcement-policy' ), 'do-not-enforce' ); ?>
                                    >
                                    <span><?php esc_html_e( 'Do not enforce on any users', 'wp-2fa' ); ?></span>
                                </label>
                            </div>
                            <br/>
                        </fieldset>
                        <?php
                        if ( ! $setupWizard ) {
                            ?>
                    </td>
                </tr>
            </tbody>
        </table>
                            <?php
                        }
    }

    /**
     * Exclude users and groups
     *
     * @since 1.7.0
     *
     * @param boolean $setupWizard
     *
     * @return void
     */
    public static function excludeUsers( $setupWizard = false ) {
        ?>
        <h3><?php esc_html_e( 'Do you want to exclude any users or roles from 2FA? ', 'wp-2fa' ); ?></h3>
        <p class="description">
            <?php esc_html_e( 'If you are enforcing 2FA on all users but for some reason you would like to exclude individual user(s) or users with a specific role, you can exclude them below', 'wp-2fa' ); ?>
        </p>
        <?php
        if ( ! $setupWizard ) {
            ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th><label for="enforcement-policy"><?php esc_html_e( 'Exclude the following users', 'wp-2fa' ); ?></label></th>
                    <td>
        <?php } else { ?>
                    <label for="excluded-users-multi-select"><?php esc_html_e( 'Exclude the following users', 'wp-2fa' ); ?>
        <?php } ?>
                        <fieldset>
                            <div>
                                <select multiple="multiple" id="excluded-users-multi-select" name="wp_2fa_settings[excluded_users][]" style=" display:none;width:<?php echo ( $setupWizard ) ? '100' : '50'; ?>%">
                                <?php
                                $excludedUsers = WP2FA::get_wp2fa_setting( 'excluded_users' );
                                foreach ( $excludedUsers as $user ) {
                                    ?>
                                    <option selected="selected" value="<?php echo $user; ?>"><?php echo $user; ?></option>
                                    <?php
                                }
                                ?>
                                </select>
                            </div>
        <?php
        if ( ! $setupWizard ) {
            ?>

                            </td>
                    </tr>
                    <tr>
                        <th><label for="enforcement-policy"><?php esc_html_e( 'Exclude the following roles', 'wp-2fa' ); ?></label></th>
                        <td>
                            <p>
                        <?php } else { ?>
                            <br>
                                <label for="enforcement-policy"><?php esc_html_e( 'Exclude the following roles', 'wp-2fa' ); ?></label>
                            <?php } ?>
                                    <select multiple="multiple" id="excluded-roles-multi-select" name="wp_2fa_settings[excluded_roles][]" style=" display:none;width:<?php echo ( $setupWizard ) ? '100' : '50'; ?>%">
                                    <?php
                                    $allRoles      = \WP2FA\WP2FA::wp_2fa_get_roles();
                                    $excludedRoles = WP2FA::get_wp2fa_setting( 'excluded_roles' );
                                    foreach ( $allRoles as $role => $roleName ) {
                                        $selected = '';
                                        if ( in_array( strtolower( $role ), $excludedRoles ) ) {
                                            $selected = 'selected="selected"';
                                        }
                                        ?>
                                            <option <?php echo $selected; ?> value="<?php echo strtolower( $role ); ?>"><?php echo $roleName; ?></option>
                                            <?php
                                    }
                                    ?>
                                    </select>
                            <br>
                            <?php if ( WP2FA::is_this_multisite() ) { ?>
                            <div style="margin-left: 70px">
                                <input type="checkbox" name="wp_2fa_settings[superadmins-role-exclude]" id="superadmins-role-exclude" value="yes"
                                    <?php checked( WP2FA::get_wp2fa_setting( 'superadmins-role-exclude' ), 'yes' ); ?> />
                                <label for="superadmins-role-exclude"><?php esc_html_e( 'Also exclude users with super admin privilege', 'wp-2fa' ); ?></label>
                            </div>
                            <?php } ?>
                        </fieldset>
                        <?php
                        if ( ! $setupWizard ) {
                            ?>
                    </td>
                </tr>
            </tbody>
        </table>
                            <?php } ?>
        <?php
    }

    /**
     * Which network sites to exclude (for multisite instal)
     *
     * @since 1.7.0
     *
     * @param boolean $setupWizard
     *
     * @return void
     */
    public static function excludedNetworkSites( $setupWizard = false ) {
        ?>
        <h3><?php esc_html_e( 'Do you want to exclude all the users of a site from 2FA? ', 'wp-2fa' ); ?></h3>
        <p class="description">
            <?php esc_html_e( 'If you are enforcing 2FA on all users but for some reason you do not want to enforce it on a specific sub site, specify the sub site name below:', 'wp-2fa' ); ?>
        </p>
        <?php
        if ( ! $setupWizard ) {
            ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th><label for="excluded-sites-multi-select"><?php esc_html_e( 'Exclude the following sites', 'wp-2fa' ); ?></label></th>
                    <td>
        <?php } ?>
                        <fieldset>
                        <?php
                        if ( $setupWizard ) {
                            ?>

                        <div class="option-pill">
                            <label for="excluded_sites_search"><?php esc_html_e( 'Exclude the following sites', 'wp-2fa' ); ?>
                        <?php } ?>
                                <select multiple="multiple" id="excluded-sites-multi-select" name="wp_2fa_settings[excluded_sites][]" style=" display:none;width:<?php echo ( $setupWizard ) ? '100' : '50'; ?>%">
                                <?php
                                    $excludedSites = WP2FA::get_wp2fa_setting( 'excluded_sites' );
                                if ( ! empty( $excludedSites ) ) {
                                    foreach ( $excludedSites as $siteId ) {
                                        $site = get_blog_details( $siteId )->blogname;
                                        ?>
                                                <option selected="selected" value="<?php echo esc_html( $siteId ); ?>"><?php echo $site; ?></option>
                                            <?php
                                    }
                                }
                                ?>
                                </select>
                                <?php
                                if ( $setupWizard ) {
                                    ?>
                            </label>
                        </div>
                        <?php } ?>
                        </fieldset>
                        <?php
                        if ( ! $setupWizard ) {
                            ?>
                    </td>
                </tr>
            </tbody>
        </table>
                        <?php } ?>
        <?php
    }

    /**
     * Set the grace period
     *
     * @since 1.7.0
     *
     * @param boolean $setupWizard
     *
     * @return void
     */
    public static function gracePeriod( $setupWizard = false ) {
        $gracePeriod = (int) WP2FA::get_wp2fa_setting( 'grace-period' );
        $testing     = apply_filters( 'wp_2fa_allow_grace_period_in_seconds', false );
        if ( $testing ) {
            $graceMax = 600;
        } else {
            $graceMax = 10;
        } get_terms()
        ?>
        <fieldset class="contains-hidden-inputs">
            <label for="no-grace-period">
                <input type="radio" name="wp_2fa_settings[grace-policy]" id="no-grace-period" value="no-grace-period"
                <?php checked( WP2FA::get_wp2fa_setting( 'grace-policy' ), 'no-grace-period' ); ?>
                >
            <span><?php esc_html_e( 'Users have to configure 2FA straight away.', 'wp-2fa' ); ?></span>
            </label>

            <br/>
            <label for="use-grace-period">
                <input type="radio" name="wp_2fa_settings[grace-policy]" id="use-grace-period" value="use-grace-period"
                <?php checked( WP2FA::get_wp2fa_setting( 'grace-policy' ), 'use-grace-period' ); ?>
                data-unhide-when-checked=".grace-period-inputs">
                <span><?php esc_html_e( 'Give users a grace period to configure 2FA', 'wp-2fa' ); ?></span>
            </label>
            <fieldset class="hidden grace-period-inputs">
                <br/>
                <input type="number" id="grace-period" name="wp_2fa_settings[grace-period]" value="<?php echo esc_attr( $gracePeriod ); ?>" min="1" max="<?php echo esc_attr( $graceMax ); ?>">
                <label class="radio-inline">
                    <input class="js-nested" type="radio" name="wp_2fa_settings[grace-period-denominator]" value="hours"
                    <?php checked( WP2FA::get_wp2fa_setting( 'grace-period-denominator' ), 'hours' ); ?>
                    >
                    <?php esc_html_e( 'Hours', 'wp-2fa' ); ?>
                </label>
                <label class="radio-inline">
                    <input class="js-nested" type="radio" name="wp_2fa_settings[grace-period-denominator]" value="days"
                    <?php checked( WP2FA::get_wp2fa_setting( 'grace-period-denominator' ), 'days' ); ?>
                    >
                    <?php esc_html_e( 'Days', 'wp-2fa' ); ?>
                </label>
                <?php
                $testing = apply_filters( 'wp_2fa_allow_grace_period_in_seconds', false );
                if ( $testing ) {
                    ?>
                    <label class="radio-inline">
                        <input class="js-nested" type="radio" name="wp_2fa_settings[grace-period-denominator]" value="seconds"
                        <?php checked( WP2FA::get_wp2fa_setting( 'grace-period-denominator' ), 'seconds' ); ?>
                        >
                        <?php esc_html_e( 'Seconds', 'wp-2fa' ); ?>
                    </label>
                    <?php
                }

                if ( $setupWizard ) {
                    $user                     = wp_get_current_user();
                    $lastUserToUpdateSettings = $user->ID;

                    ?>
                <input type="hidden" id="2fa_main_user" name="wp_2fa_settings[2fa_settings_last_updated_by]" value="<?php echo esc_attr( $lastUserToUpdateSettings ); ?>">
                <?php } else { ?>
                    <p><?php esc_html_e( 'Note: If users do not configure it within the configured stipulated time, their account will be locked and have to be unlocked manually.', 'wp-2fa' ); ?></p>
                <?php } ?>
            </fieldset>
            <br/>
        </fieldset>
        <?php
    }
}
