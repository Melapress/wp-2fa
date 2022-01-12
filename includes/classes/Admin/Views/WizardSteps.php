<?php

namespace WP2FA\Admin\Views;

use WP2FA\WP2FA;
use WP2FA\Admin\User;
use WP2FA\Utils\UserUtils;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Authenticator\Authentication;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * WP2FA Wizard Settings view controller
 *
 * @since 1.7
 */
class WizardSteps {

    /**
     * Holds the current user
     *
     * @since 1.7
     *
     * @var \WP2FA\Admin\User
     */
    private static $user = null;

    /**
     * Is the totp method enabled
     *
     * @since 1.7
     *
     * @var bool
     */
    private static $totpEnabled = null;

    /**
     * Is the mail enabled
     *
     * @since 1.7
     *
     * @var bool
     */
    private static $emailEnabled = null;

    /**
     * Holds the nonce for json calls
     *
     * @since 1.7
     *
     * @var string
     */
    private static $jsonNonce = null;

    /**
     * Holds the url to which to redirect the user after the setup is finished
     *
     * @var string
     *
     * @since 2.0.0
     */
    private static $redirect_url = null;

    /**
     * Introduction step form
     *
     * @since 1.7
     *
     * @return void
     */
    public static function introductionStep() {
        ?>
        <form method="post" class="wp2fa-setup-form">
            <?php wp_nonce_field( 'wp2fa-step-addon' ); ?>
            <h3><?php esc_html_e( 'You are required to configure 2FA.', 'wp-2fa' ); ?></h3>
            <p><?php esc_html_e( 'In order to keep this site - and your details secure, this website’s administrator requires you to enable 2FA authentication to continue.', 'wp-2fa' ); ?></p>
            <p><?php esc_html_e( 'Two factor authentication ensures only you have access to your account by creating an added layer of security when logging in -', 'wp-2fa' ); ?> <a href="https://www.wpwhitesecurity.com/two-factor-authentication-wordpress/" target="_blank" rel="noopener"><?php esc_html_e( 'Learn more', 'wp-2fa' ); ?></a></p>

            <div class="wp2fa-setup-actions">
                <button class="button button-primary"
                type="submit"
                name="save_step"
                value="<?php esc_attr_e( 'Next', 'wp-2fa' ); ?>">
                <?php esc_html_e( 'Next', 'wp-2fa' ); ?>
                </button>
            </div>
        </form>
        <?php
    }

    /**
     * Welcome step of the wizard
     *
     * @since 1.7
     *
     * @param string $nextStep - url of the next step.
     *
     * @return void
     */
    public static function welcomeStep( $nextStep ) {
        $redirect = Settings::get_settings_page_link();

        ?>
        <h3><?php esc_html_e( 'Let us help you get started', 'wp-2fa' ); ?></h3>
        <p><?php esc_html_e( 'Thank you for installing the WP 2FA plugin. This quick wizard will assist you with configuring the plugin and the two-factor authentication (2FA) settings for your user and the users on this website.', 'wp-2fa' ); ?></p>

        <div class="wp2fa-setup-actions">
            <a class="button button-primary"
                href="<?php echo esc_url( $nextStep ); ?>">
                <?php esc_html_e( 'Let’s get started!', 'wp-2fa' ); ?>
            </a>
            <a class="button button-secondary first-time-wizard"
                href="<?php echo esc_url( $redirect ); ?>">
                <?php esc_html_e( 'Skip Wizard - I know how to do this', 'wp-2fa' ); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Shows the initial totp setup options based on enabled methods
     *
     * @since 1.7
     *
     * @return void
     */
    public static function totpOption() {
        if ( self::isTotpEnabled() ) {
            ?>
            <div class="option-pill">
                <label for="basic">
                    <input id="basic" name="wp_2fa_enabled_methods" type="radio" value="totp" checked>
                        <?php esc_html_e( 'One-time code generated with your app of choice (most reliable and secure)', 'wp-2fa' ); ?>
                </label>
                <?php
                    echo '<p class="description">';
                    printf(
                        /* translators: link to the knowledge base website */
                        esc_html__( 'Refer to the %s for more information on how to setup these apps and which apps are supported.', 'wp-2fa' ),
                        '<a href="https://www.wpwhitesecurity.com/support/kb/configuring-2fa-apps/" target="_blank">' . esc_html__( '2FA apps article on our knowledge base', 'wp-2fa' ) . '</a>'
                    );
                    echo '</p>';
                ?>
            </div>
            <?php
        }
    }

    /**
     * Shows the initial email setup option based on enabled methods
     *
     * @since 1.7
     *
     * @return void
     */
    public static function emailOption() {
        if ( self::isMailEnabled() ) {
            ?>
            <div class="option-pill">
                <label for="geek">
                    <input id="geek" name="wp_2fa_enabled_methods" type="radio" value="email">
                <?php esc_html_e( 'One-time code sent to you over email', 'wp-2fa' ); ?>
                </label>
            <?php
            if ( current_user_can( 'administrator' ) ) {
                echo '<p class="description">' . WP2FA::print_email_deliverability_message() . '</p>'; // @codingStandardsIgnoreLine
            }
            ?>
            </div>
            <?php
        }
    }


    /**
     * Shows the option to reconfigure email (if applicable)
     *
     * @since 1.7
     *
     * @return void
     */
    public static function totpReConfigure() {

        if ( ! self::isTotpEnabled() ) {
            return;
        }

        $nonce = self::jsonNonce();

        ?>
        <div class="option-pill">
            <h3>
                <?php esc_html_e( 'Reconfigure the 2FA App', 'wp-2fa' ); ?>
            </h3>
            <p>
                <?php esc_html_e( 'Click the below button to reconfigure the current 2FA method. Note that once reset you will have to re-scan the QR code on all devices you want this to work on because the previous codes will stop working.', 'wp-2fa' ); ?>
            </p>
            <div class="wp2fa-setup-actions">
                <a href="#" class="button button-primary" data-name="next_step_setting_modal_wizard" data-trigger-reset-key data-nonce="<?php echo esc_attr( $nonce ); ?>" data-user-id="<?php echo esc_attr( self::getUser()->getUser()->ID ); ?>" data-next-step="2fa-wizard-totp"><?php esc_html_e( 'Reset Key', 'wp-2fa' ); ?></a>
            </div>
        </div>
        <?php
    }

    /**
     * Shows the option for email method reconfiguring (if applicable)
     *
     * @since 1.7
     *
     * @return void
     */
    public static function emailReConfigure() {

        if ( ! self::isMailEnabled() ) {
            return;
        }

        $setupnonce = wp_create_nonce( 'wp-2fa-send-setup-email' );
        ?>
            <div class="option-pill">
                <h3><?php esc_html_e( 'Reconfigure one-time code over email method', 'wp-2fa' ); ?></h3>
                <p>
                <?php esc_html_e( 'Please select the email address where the one-time code should be sent:', 'wp-2fa' ); ?>
                </p>
                <div class="wp2fa-setup-actions">
                    <a class="button button-primary" data-name="next_step_setting_modal_wizard" value="<?php esc_attr_e( 'I\'m Ready', 'wp-2fa' ); ?>" data-user-id="<?php echo esc_attr( self::getUser()->getUser()->ID ); ?>" data-nonce="<?php echo esc_attr( $setupnonce ); ?>" data-next-step="2fa-wizard-email"><?php esc_html_e( 'Change email address', 'wp-2fa' ); ?></a>
                </div>
            </div>
        <?php
    }

    /**
     * Reconfigures the totp form
     *
     * @since 1.7
     *
     * @return void
     */
    public static function totpConfigure() {

        if ( ! self::isTotpEnabled() ) {
            return;
        }
        /**
         * Active on modal, additional attribute is required on standard HTML (check below)
         */
        $addStepAttributes = 'active';

        /**
         * Closing div for extra modal wrappers see lines above
         */
        $closeDiv = '';

        $qrCode        = '<img class="qr-code" src="' . ( self::getQRCode() ) . '" id="wp-2fa-totp-qrcode" />';
        $open30Wrapper = '
        <div class="mb-30 clear-both">
        ';
        $open60Wrapper = '
            <div class="modal-60">
        ';
        $open40Wrapper = '
            <div class="modal-40">
        ';
        $closeDiv      = '
        </div>
        ';
        $validateNonce = wp_create_nonce( 'wp-2fa-validate-authcode' );

        ?>
        <div class="step-setting-wrapper <?php echo $addStepAttributes; ?>">
            <h3><?php esc_html_e( 'Setup the 2FA method', 'wp-2fa' ); ?></h3>
            <?php echo $open30Wrapper . $open60Wrapper; ?>
            <div class="option-pill">
                <ol>
                    <li><?php esc_html_e( 'Download and start the application of your choice (for detailed steps on setting it up click on the application icon of our choice below)', 'wp-2fa' ); ?></li>
                    <li><?php esc_html_e( 'From within the application scan the QR code provided on the right. Otherwise, enter the following code manually in the application:', 'wp-2fa' ); ?>
                        <div><code class="app-key"><?php echo esc_html( self::getUser()->get_totp_decrypted() ); ?></code></div>
                    </li>
                    <li><?php esc_html_e( 'Click the "I\'m ready" button below when you complete the application setup process to proceed with the wizard.', 'wp-2fa' ); ?></li>
                </ol>
            </div>
            <?php
            echo $closeDiv; // closes 50 wrapper
            echo $open40Wrapper;
            ?>
            <div class="qr-code-wrapper">
            <?php echo $qrCode; ?>
            </div>
            <?php
            echo $closeDiv; // closes 50 wrapper
            echo $closeDiv; // closes 30 wrapper
            ?>
            <h4><?php esc_html_e( 'For detailed guides for your desired app, click below.', 'wp-2fa' ); ?></h4>
            <div class="apps-wrapper">
            <?php foreach ( Authentication::get_apps() as $app ) { ?>
            <a href="https://www.wpwhitesecurity.com/support/kb/configuring-2fa-apps/#<?php echo $app['hash']; ?>" target="_blank" class="app-logo"><img src="<?php echo esc_url( WP_2FA_URL . 'dist/images/' . $app['logo'] ); ?>"></a>
            <?php } ?>
            </div>
            <div class="wp2fa-setup-actions">
                <button class="button button-primary" name="next_step_setting" value="<?php esc_attr_e( 'I\'m Ready', 'wp-2fa' ); ?>" type="button"><?php esc_html_e( 'I\'m Ready', 'wp-2fa' ); ?></button>
            </div>
        </div>
        <div class="step-setting-wrapper" data-step-title="<?php esc_html_e( 'Verify configuration', 'wp-2fa' ); ?>">
            <h3><?php esc_html_e( 'Almost there…', 'wp-2fa' ); ?></h3>
            <p><?php esc_html_e( 'Please type in the one-time code from your Google Authenticator app to finalize the setup.', 'wp-2fa' ); ?></p>
            <fieldset>
                <label for="2fa-totp-authcode">
                    <input type="tel" name="wp-2fa-totp-authcode" id="wp-2fa-totp-authcode" class="input" value="" size="20" pattern="[0-9]*" placeholder="<?php esc_html_e( 'Authentication Code', 'wp-2fa' ); ?>"/>
                </label>
                <div class="verification-response"></div>
            </fieldset>
            <input type="hidden" name="wp-2fa-totp-key" value="<?php echo esc_attr( self::getUser()->get_totp_decrypted() ); ?>" />
            <br>
            <a href="#" class="modal__btn button button-primary" data-validate-authcode-ajax data-nonce="<?php echo esc_attr( $validateNonce ); ?>"><?php esc_html_e( 'Validate & Save Configuration', 'wp-2fa' ); ?></a>
            <button class="modal__btn button" data-close-2fa-modal aria-label="Close this dialog window"><?php esc_html_e( 'Cancel', 'wp-2fa' ); ?></button>
        </div>

        <?php
    }

    /**
     * Reconfigures email form
     *
     * @since 1.7
     *
     * @return void
     */
    public static function emailConfigure() {

        if ( ! self::isMailEnabled() ) {
            return;
        }

        $setupnonce = wp_create_nonce( 'wp-2fa-send-setup-email' );

        $validateNonce = wp_create_nonce( 'wp-2fa-validate-authcode' );
        ?>
        <div class="step-setting-wrapper active">
            <h3><?php esc_html_e( 'Setup the 2FA method', 'wp-2fa' ); ?></h3>
            <p>
            <?php esc_html_e( 'Please select the email address where the one-time code should be sent:', 'wp-2fa' ); ?>
            </p>
            <fieldset>
            <div class="option-pill">
                <label for="use_wp_email">
                    <input type="radio" name="wp_2fa_email_address" id="use_wp_email" value="<?php echo esc_attr( self::getUser()->getUser()->user_email ); ?>" checked>
                    <span><?php esc_html_e( 'Use my user email (', 'wp-2fa' ); ?><small><?php echo esc_attr( self::getUser()->getUser()->user_email ); ?></small><?php esc_html_e( ')', 'wp-2fa' ); ?></span>
                </label>
            </div>
            <?php
			if ( Settings::get_role_or_default_setting( 'specify-email_hotp', self::getUser()->getUser() ) ) {
			    ?>
            <div class="option-pill">
                <label for="use_custom_email">
                    <input type="radio" name="wp_2fa_email_address" id="use_custom_email" value="use_custom_email">
                    <span><?php esc_html_e( 'Use a different email address:', 'wp-2fa' ); ?></span>
                    <input type="email" name="custom-email-address" id="custom-email-address" class="input" value="" placeholder="<?php esc_html_e( 'Email address', 'wp-2fa' ); ?>"/>
                </label>
            </div>
                <?php
			}
			?>
            </fieldset>
            <p class="description"><?php esc_html_e( 'Note: you should be able to access the mailbox of the email address to complete the following step.', 'wp-2fa' ); ?></p>
            <div class="wp2fa-setup-actions">
                <button class="button button-primary" name="next_step_setting" value="<?php esc_attr_e( 'I\'m Ready', 'wp-2fa' ); ?>" data-trigger-setup-email data-user-id="<?php echo esc_attr( self::getUser()->getUser()->ID ); ?>" data-nonce="<?php echo esc_attr( $setupnonce ); ?>" type="button"><?php esc_html_e( 'I\'m Ready', 'wp-2fa' ); ?></button>

            </div>
        </div>

        <div class="step-setting-wrapper" data-step-title="<?php esc_html_e( 'Verify configuration', 'wp-2fa' ); ?>" id="2fa-wizard-email">
            <h3><?php esc_html_e( 'Almost there…', 'wp-2fa' ); ?></h3>
            <p><?php esc_html_e( 'Please type in the one-time code sent to your email address to finalize the setup.', 'wp-2fa' ); ?></p>
            <fieldset>
                <label for="2fa-email-authcode">
                    <input type="tel" name="wp-2fa-email-authcode" id="wp-2fa-email-authcode" class="input" value="" size="20" pattern="[0-9]*" placeholder="<?php esc_html_e( 'Authentication Code', 'wp-2fa' ); ?>"/>
                </label>
                <div class="verification-response"></div>
            </fieldset>
            <br />
            <a href="#" class="modal__btn modal__btn-primary button button-primary" data-validate-authcode-ajax data-nonce="<?php echo esc_attr( $validateNonce ); ?>"><?php esc_html_e( 'Validate & Save Configuration', 'wp-2fa' ); ?></a>
            <a href="#" class="modal__btn button button-secondary resend-email-code" data-trigger-setup-email data-user-id="<?php echo esc_attr( self::getUser()->getUser()->ID ); ?>" data-nonce="<?php echo esc_attr( $setupnonce ); ?>">
                <span class="resend-inner"><?php esc_html_e( 'Send me another code', 'wp-2fa' ); ?></span>
            </a>
            <button class="modal__btn button" data-close-2fa-modal aria-label="Close this dialog window"><?php esc_html_e( 'Cancel', 'wp-2fa' ); ?></button>
        </div>
        <?php
    }

    /**
     * Configure backup codes step
     *
     * @since 1.7
     *
     * @return void
     */
    public static function backup_codes_configure() {

        $user_type = UserUtils::determine_user_2fa_status( self::getUser()->getUser() );

        $redirect = self::determine_redirect_url();

        $nonce = self::jsonNonce();
        ?>
        <div class="step-setting-wrapper active">
        <h3><?php esc_html_e( 'Your login just got more secure', 'wp-2fa' ); ?></h3>
        <p><?php esc_html_e( 'Congratulations! You have enabled two-factor authentication for your user. You’ve just helped towards making this website more secure!', 'wp-2fa' ); ?></p>
        <?php
        if ( in_array( 'user_needs_to_setup_backup_codes', $user_type, true ) ) {
            ?>
            <p><?php esc_html_e( 'You can exit this wizard now or continue to create backup codes.', 'wp-2fa' ); ?></p>
        <?php } ?>
            <div class="wp2fa-setup-actions">
            <?php if ( in_array( 'user_needs_to_setup_backup_codes', $user_type, true ) ) { ?>
                <button class="button button-primary" name="next_step_setting" value="<?php esc_attr_e( 'Generate backup codes', 'wp-2fa' ); ?>" data-trigger-generate-backup-codes data-nonce="<?php echo esc_attr( $nonce ); ?>">
                    <?php esc_html_e( 'Generate list of backup codes', 'wp-2fa' ); ?>
                </button>
                <a href="#" class="button button-secondary" data-close-2fa-modal value="<?php esc_attr_e( 'I’ll generate them later', 'wp-2fa' ); ?>">
                    <?php esc_html_e( 'I’ll generate them later', 'wp-2fa' ); ?>
                </a>
            <?php } else { ?>
                <?php
                if ( ! empty( $redirect ) ) {
                    ?>
                    <a href="<?php echo esc_url( $redirect ); ?>" class="button button-secondary close-first-time-wizard">
                    <?php esc_html_e( 'Close wizard', 'wp-2fa' ); ?>
                    </a>
                    <?php
                } else {
                    ?>
                <a href="#" class="button button-secondary" data-reload>
                    <?php esc_html_e( 'Close wizard', 'wp-2fa' ); ?>
                </a>
                <?php } ?>
            <?php } ?>
            </div>
            </div>
            <?php
    }

    /**
     * Generate backup codes step
     *
     * @since 1.7
     *
     * @return void
     */
    public static function generateBackupCodes() {
        $nonce = self::jsonNonce();

        ?>
        <div class="step-setting-wrapper active" data-step-title="<?php esc_html_e( 'Generate codes', 'wp-2fa' ); ?>">
            <h3><?php esc_html_e( 'Generate backup codes', 'wp-2fa' ); ?></h3>
            <p><?php esc_html_e( 'It is recommended to generate and print some backup codes in case you lose access to your primary 2FA method. ', 'wp-2fa' ); ?></p>
            <div class="wp2fa-setup-actions">
                <button class="button button-primary" name="next_step_setting" value="<?php esc_attr_e( 'Generate backup codes', 'wp-2fa' ); ?>" data-trigger-generate-backup-codes data-nonce="<?php echo esc_attr( $nonce ); ?>">
                    <?php esc_html_e( 'Generate list of backup codes', 'wp-2fa' ); ?>
                </button>
                <a href="#" class="button button-secondary" value="<?php esc_attr_e( 'I’ll generate them later', 'wp-2fa' ); ?>" data-close-2fa-modal="">
                    <?php esc_html_e( 'I’ll generate them later', 'wp-2fa' ); ?>
                </a>
            </div>
        </div>

        <?php
    }

    /**
     * Creates link for generating the backup codes
     *
     * @since 1.7
     *
     * @return string
     */
    public static function getGenerateCodesLink() {
        $nonce = self::jsonNonce();

        $label = __( 'Backup 2FA methods:', 'wp-2fa' );

        return $label . '</th><td><a href="#" class="button button-primary remove-2fa" data-trigger-generate-backup-codes  data-nonce="' . esc_attr( $nonce ) . '" onclick="MicroModal.show( \'configure-2fa-backup-codes\' );">' . __( 'Generate list of backup codes', 'wp-2fa' ) . '</a>';
    }

    /**
     * Shows the wrapper where backup code are generated and showed to the user
     *
     * @param boolean $backup_only - If we want to show backup window only - sets the class of the div to active.
     *
     * @since 1.7
     *
     * @return void
     */
    public static function generated_backup_codes( $backup_only = false ) {
        $nonce = self::jsonNonce();

        $redirect = self::determine_redirect_url();

        ?>
        <div class="step-setting-wrapper align-center<?php echo ( $backup_only ) ? ' active' : ''; ?>" data-step-title="<?php esc_html_e( 'Your backup codes', 'wp-2fa' ); ?>">
            <h3><?php esc_html_e( 'Backup codes generated', 'wp-2fa' ); ?></h3>
            <p><?php esc_html_e( 'Here are your backup codes:', 'wp-2fa' ); ?></p>
            <code id="backup-codes-wrapper"></code>
            <div class="wp2fa-setup-actions">
                <button class="button button-primary" type="submit" value="<?php esc_attr_e( 'Download', 'wp-2fa' ); ?>" data-trigger-backup-code-download data-user="<?php echo esc_attr( self::getUser()->getUser()->display_name ); ?>" data-website-url="<?php echo esc_attr( get_home_url() ); ?>">
                    <?php esc_html_e( 'Download', 'wp-2fa' ); ?>
                </button>
                <button class="button button-secondary" type="submit" value="<?php esc_attr_e( 'Print', 'wp-2fa' ); ?>" data-trigger-print data-nonce="<?php echo esc_attr( $nonce ); ?>" data-user-id="<?php echo esc_attr( self::getUser()->getUser()->display_name ); ?>" data-website-url="<?php echo esc_attr( get_home_url() ); ?>">
                    <?php esc_html_e( 'Print', 'wp-2fa' ); ?>
                </button>
                <?php
                if ( ! empty( $redirect ) ) {
                    ?>
                    <a href="<?php echo esc_url( $redirect ); ?>" class="button button-secondary close-first-time-wizard">
                    <?php esc_html_e( 'I\'m ready, close the wizard', 'wp-2fa' ); ?>
                    </a>
                    <?php
                } else {
                    ?>
                <button class="button button-secondary" type="submit" data-close-2fa-modal-and-refresh>
                    <?php esc_html_e( 'I\'m ready, close the wizard', 'wp-2fa' ); ?>
                </button>
                <?php } ?>
            </div>
        </div>
        <?php
    }

    /**
     * Final step for congratulating the user
     *
     * @since 1.7
     *
     * @param boolean $setup_wizard - Is that a call from setup wizard or not.
     *
     * @return void
     */
    public static function congratulations_step( $setup_wizard = false ) {

        if ( $setup_wizard ) {
            self::congratulations_step_plugin_wizard();
            return;
        }
        ?>

        <div class="step-setting-wrapper active">
        <h3><?php esc_html_e( 'Congratulations! You are all set.', 'wp-2fa' ); ?></h3>
        <div class="wp2fa-setup-actions">
            <button class="modal__btn button" data-close-2fa-modal aria-label="Close this dialog window"><?php esc_html_e( 'Close wizard', 'wp-2fa' ); ?></button>
        </div>
        </div>
        <?php
    }

    /**
     * Final step for congratulating the user
     *
     * @since 1.7
     *
     * @return void
     */
    public static function congratulations_step_plugin_wizard() {
        $redirect    = ( '' !== self::determine_redirect_url() ) ? self::determine_redirect_url() : get_edit_profile_url( self::getUser()->getUser()->ID );
        $slide_title = ( User::is_excluded( self::getUser()->getUser()->ID ) ) ? esc_html__( 'Congratulations.', 'wp-2fa' ) : esc_html__( 'Congratulations, you\'re almost there...', 'wp-2fa' );
        ?>
        <h3><?php echo $slide_title; ?></h3>
        <p><?php esc_html_e( 'Great job, the plugin and 2FA policies are now configured. You can always change the plugin settings and 2FA policies at a later stage from the WP 2FA entry in the WordPress menu.', 'wp-2fa' ); ?></p>

            <?php
            if ( User::is_excluded( self::getUser()->getUser()->ID ) ) {
                ?>
        <div class="wp2fa-setup-actions">
            <a href="<?php echo esc_url( $redirect ); ?>" class="button button-secondary close-first-time-wizard">
                    <?php esc_html_e( 'Close wizard', 'wp-2fa' ); ?>
            </a>
        </div>
                <?php
            } else {
                ?>
        <p><?php esc_html_e( 'Now you need to configure 2FA for your own user account. You can do this now (recommended) or later.', 'wp-2fa' ); ?></p>
        <div class="wp2fa-setup-actions">
            <a href="<?php echo esc_url( Settings::get_setup_page_link() ); ?>" class="button button-secondary">
                <?php esc_html_e( 'Configure 2FA now', 'wp-2fa' ); ?>
            </a>
            <a href="<?php echo esc_url( Settings::get_settings_page_link() ); ?>" class="button button-secondary close-first-time-wizard">
                    <?php esc_html_e( 'Close wizard & configure 2FA later', 'wp-2fa' ); ?>
            </a>
        </div>
            <?php } ?>
        <?php
    }

    /**
     * Shows the methods in the modal wizard, so the user can choose from the available ones
     *
     * @return void
     */
    public static function showModalMethods() {
        if ( self::isTotpEnabled() ) {
            ?>
            <div class="wizard-step" id="2fa-wizard-totp">
                <fieldset>
                    <?php self::totpConfigure(); ?>
                </fieldset>
            </div>
            <?php
        }
        if ( self::isMailEnabled() ) {
            ?>
            <div class="wizard-step" id="2fa-wizard-email">
                <fieldset>
                    <?php self::emailConfigure(); ?>
                </fieldset>
            </div>
            <?php
        }

        /**
         * Add an option for external providers to add their own modal methods options.
         *
         * @since 2.0.0
         */
        do_action( 'wp_2fa_modal_methods' );
    }

    /**
     * Gets the current user
     *
     * @since 1.7
     *
     * @return \WP2FA\Admin\User
     */
    public static function getUser() {
        if ( null === self::$user ) {
            self::$user = User::get_instance();
        }

        return self::$user;
    }

    /**
     * Choosing backup method step
     * When there are more than one backup method - give the user ability to choose one
     *
     * @return void
     *
     * @since 2.0.0
     */
    public static function choose_backup_method() {
        $redirect = get_edit_profile_url( self::getUser()->getUser()->ID );
        ?>
        <div class="wizard-step" id="2fa-wizard-backup-methods">
            <div class="option-pill">
                <h3><?php esc_html_e( 'Your login just got more secure', 'wp-2fa' ); ?></h3>
                <p><?php esc_html_e( 'It is recommended to have a backup 2FA method in case you cannot generate a code from your 2FA app and you need to log in. You can configure any of the below. You can always configure any or both from your user profile page later.', 'wp-2fa' ); ?></p>
            </div>
        <?php
        $backup_methods = Settings::get_backup_methods();

        $i = 0;
        foreach ( $backup_methods as $method_name => $method ) {
            $checked = '';
            if ( ! $i ) {
                $checked = ' checked="checked"';
            }
            $i = 1;
            ?>
            <div><label for="<?php echo \esc_attr( $method_name ); ?>"><input name="backup_method_select" data-step="<?php echo \esc_attr( $method['wizard-step'] ); ?>" type="radio" id="<?php echo \esc_attr( $method_name ); ?>" <?php echo $checked; ?>><?php echo $method['button_name']; // @codingStandardsIgnoreLine ?></label><br /></div>
            <?php
        }
        ?>
            <div class="wp2fa-setup-actions">
                <a id="select-backup-method" href="<?php echo esc_url( Settings::get_setup_page_link() ); ?>" class="button button-secondary">
                    <?php esc_html_e( 'Configure backup 2FA method', 'wp-2fa' ); ?>
                </a>
                <a href="<?php echo esc_url( $redirect ); ?>" class="button button-secondary close-first-time-wizard">
                        <?php esc_html_e( 'Close wizard & configure 2FA later', 'wp-2fa' ); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Generates nonce for JSON calls
     *
     * @since 1.7
     *
     * @return string
     */
    protected static function jsonNonce() {
        if ( null === self::$jsonNonce ) {
            self::$jsonNonce = wp_create_nonce( 'wp-2fa-backup-codes-generate-json-' . self::getUser()->getUser()->ID );
        }

        return self::$jsonNonce;
    }

    /**
     * Returns the status of the totp method (enabled | disabled)
     *
     * @since 1.7
     *
     * @return boolean
     */
    private static function isTotpEnabled(): bool {
        if ( null === self::$totpEnabled ) {
            self::$totpEnabled = empty( Settings::get_role_or_default_setting( 'enable_totp', 'current' ) ) ? false : true;
        }

        return self::$totpEnabled;
    }

    /**
     * Returns the status of the mail method (enabled | disabled)
     *
     * @since 1.7
     *
     * @return boolean
     */
    private static function isMailEnabled(): bool {
        if ( null === self::$emailEnabled ) {
            self::$emailEnabled = empty( Settings::get_role_or_default_setting( 'enable_email', 'current' ) ) ? false : true;
        }

        return self::$emailEnabled;
    }

    /**
     * Retrieves the QR code
     *
     * @since 1.7
     *
     * @return string
     */
    private static function getQRCode(): string {

        // Setup site information, used when generating our QR code.
        $siteName  = get_bloginfo( 'name', 'display' );
        $totpTitle = apply_filters(
            'wp_2fa_totp_title',
            $siteName . ':' . self::getUser()->getUser()->user_login,
            self::getUser()->getUser()
        );

        return Authentication::get_google_qr_code( $totpTitle, self::getUser()->getTotpKey(), $siteName );
    }

    /**
     * Determines the redirect url for the user
     *
     * @return string
     *
     * @since 2.0.0
     */
    private static function determine_redirect_url(): string {
        if ( null === self::$redirect_url ) {
            $redirect_page = Settings::get_role_or_default_setting( 'redirect-user-custom-page-global', self::getUser()->getUser() );
            self::$redirect_url = ( '' !== trim( $redirect_page ) ) ? \trailingslashit( get_site_url() ) . $redirect_page : '';

            if (
                'yes' === Settings::get_role_or_default_setting( 'create-custom-user-page', self::getUser()->getUser() ) ||
                'yes' === Settings::get_role_or_default_setting( 'create-custom-user-page' ) ) {
                if (
                    '' !== trim( Settings::get_role_or_default_setting( 'redirect-user-custom-page', self::getUser()->getUser() ) ) ||
                    '' !== trim( Settings::get_role_or_default_setting( 'redirect-user-custom-page' ) ) ) {
                    if ( 'yes' === Settings::get_role_or_default_setting( 'create-custom-user-page', self::getUser()->getUser() ) ) {
                        self::$redirect_url = trailingslashit( get_site_url() ) . Settings::get_role_or_default_setting( 'redirect-user-custom-page', self::getUser()->getUser() );
                    } else {
                        self::$redirect_url = trailingslashit( get_site_url() ) . Settings::get_role_or_default_setting( 'redirect-user-custom-page' );
                    }
                }
            }
        }

        return self::$redirect_url;
    }
}
