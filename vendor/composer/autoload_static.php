<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit3b06a2dd4916854033f4aa14fb7e620f
{
    public static $files = array (
        'a9ed0d27b5a698798a89181429f162c5' => __DIR__ . '/..' . '/khanamiryan/qrcode-detector-decoder/lib/Common/customFunctions.php',
    );

    public static $prefixLengthsPsr4 = array (
        'Z' => 
        array (
            'Zxing\\' => 6,
        ),
        'W' => 
        array (
            'WP2FA\\Extensions\\' => 17,
            'WP2FA\\' => 6,
        ),
        'S' => 
        array (
            'Symfony\\Component\\PropertyAccess\\' => 33,
            'Symfony\\Component\\Inflector\\' => 28,
        ),
        'M' => 
        array (
            'MyCLabs\\Enum\\' => 13,
        ),
        'F' => 
        array (
            'Firebase\\JWT\\' => 13,
        ),
        'E' => 
        array (
            'Endroid\\QrCode\\' => 15,
        ),
        'D' => 
        array (
            'DASPRiD\\Enum\\' => 13,
        ),
        'B' => 
        array (
            'BaconQrCode\\' => 12,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Zxing\\' => 
        array (
            0 => __DIR__ . '/..' . '/khanamiryan/qrcode-detector-decoder/lib',
        ),
        'WP2FA\\Extensions\\' => 
        array (
            0 => __DIR__ . '/../..' . '/extensions',
        ),
        'WP2FA\\' => 
        array (
            0 => __DIR__ . '/../..' . '/includes/classes',
        ),
        'Symfony\\Component\\PropertyAccess\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/property-access',
        ),
        'Symfony\\Component\\Inflector\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/inflector',
        ),
        'MyCLabs\\Enum\\' => 
        array (
            0 => __DIR__ . '/..' . '/myclabs/php-enum/src',
        ),
        'Firebase\\JWT\\' => 
        array (
            0 => __DIR__ . '/..' . '/firebase/php-jwt/src',
        ),
        'Endroid\\QrCode\\' => 
        array (
            0 => __DIR__ . '/..' . '/endroid/qr-code/src',
        ),
        'DASPRiD\\Enum\\' => 
        array (
            0 => __DIR__ . '/..' . '/dasprid/enum/src',
        ),
        'BaconQrCode\\' => 
        array (
            0 => __DIR__ . '/..' . '/bacon/bacon-qr-code/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'Stringable' => __DIR__ . '/..' . '/myclabs/php-enum/stubs/Stringable.php',
        'WP2FA\\Admin\\Controllers\\Login_Attempts' => __DIR__ . '/../..' . '/includes/classes/Admin/Controllers/class-login-attempts.php',
        'WP2FA\\Admin\\Controllers\\Methods' => __DIR__ . '/../..' . '/includes/classes/Admin/Controllers/class-methods.php',
        'WP2FA\\Admin\\Controllers\\Settings' => __DIR__ . '/../..' . '/includes/classes/Admin/Controllers/class-settings.php',
        'WP2FA\\Admin\\Help_Contact_Us' => __DIR__ . '/../..' . '/includes/classes/Admin/class-help-contact-us.php',
        'WP2FA\\Admin\\Helpers\\User_Helper' => __DIR__ . '/../..' . '/includes/classes/Admin/Helpers/class-user-helper.php',
        'WP2FA\\Admin\\Helpers\\WP_Helper' => __DIR__ . '/../..' . '/includes/classes/Admin/Helpers/class-wp-helper.php',
        'WP2FA\\Admin\\Premium_Features' => __DIR__ . '/../..' . '/includes/classes/Admin/class-premium-features.php',
        'WP2FA\\Admin\\SettingsPage' => __DIR__ . '/../..' . '/includes/classes/Admin/class-settingspage.php',
        'WP2FA\\Admin\\SettingsPages\\Settings_Page_Email' => __DIR__ . '/../..' . '/includes/classes/Admin/SettingsPages/class-settings-page-email.php',
        'WP2FA\\Admin\\SettingsPages\\Settings_Page_General' => __DIR__ . '/../..' . '/includes/classes/Admin/SettingsPages/class-settings-page-general.php',
        'WP2FA\\Admin\\SettingsPages\\Settings_Page_Policies' => __DIR__ . '/../..' . '/includes/classes/Admin/SettingsPages/class-settings-page-policies.php',
        'WP2FA\\Admin\\SettingsPages\\Settings_Page_White_Label' => __DIR__ . '/../..' . '/includes/classes/Admin/SettingsPages/class-settings-page-white-label.php',
        'WP2FA\\Admin\\Settings_Page' => __DIR__ . '/../..' . '/includes/classes/Admin/class-settings-page.php',
        'WP2FA\\Admin\\Setup_Wizard' => __DIR__ . '/../..' . '/includes/classes/Admin/class-setup-wizard.php',
        'WP2FA\\Admin\\User' => __DIR__ . '/../..' . '/includes/classes/Admin/class-user.php',
        'WP2FA\\Admin\\User_Listing' => __DIR__ . '/../..' . '/includes/classes/Admin/class-user-listing.php',
        'WP2FA\\Admin\\User_Notices' => __DIR__ . '/../..' . '/includes/classes/Admin/class-user-notices.php',
        'WP2FA\\Admin\\User_Profile' => __DIR__ . '/../..' . '/includes/classes/Admin/class-user-profile.php',
        'WP2FA\\Admin\\User_Registered' => __DIR__ . '/../..' . '/includes/classes/Admin/class-user-registered.php',
        'WP2FA\\Admin\\Views\\First_Time_Wizard_Steps' => __DIR__ . '/../..' . '/includes/classes/Admin/Views/class-first-time-wizard-steps.php',
        'WP2FA\\Admin\\Views\\Settings_Page_Render' => __DIR__ . '/../..' . '/includes/classes/Admin/Views/class-settings-page-render.php',
        'WP2FA\\Admin\\Views\\Wizard_Steps' => __DIR__ . '/../..' . '/includes/classes/Admin/Views/class-wizard-steps.php',
        'WP2FA\\Authenticator\\Authentication' => __DIR__ . '/../..' . '/includes/classes/Authenticator/class-authentication.php',
        'WP2FA\\Authenticator\\BackupCodes' => __DIR__ . '/../..' . '/includes/classes/Authenticator/class-backupcodes.php',
        'WP2FA\\Authenticator\\Backup_Codes' => __DIR__ . '/../..' . '/includes/classes/Authenticator/class-backup-codes.php',
        'WP2FA\\Authenticator\\Login' => __DIR__ . '/../..' . '/includes/classes/Authenticator/class-login.php',
        'WP2FA\\Authenticator\\Open_SSL' => __DIR__ . '/../..' . '/includes/classes/Authenticator/class-open-ssl.php',
        'WP2FA\\Cron\\Cron_Tasks' => __DIR__ . '/../..' . '/includes/classes/Cron/class-cron-tasks.php',
        'WP2FA\\Email_Template' => __DIR__ . '/../..' . '/includes/classes/class-email-template.php',
        'WP2FA\\Shortcodes\\Shortcodes' => __DIR__ . '/../..' . '/includes/classes/Shortcodes/class-shortcodes.php',
        'WP2FA\\Utils\\Abstract_Migration' => __DIR__ . '/../..' . '/includes/classes/Utils/class-abstract-migration.php',
        'WP2FA\\Utils\\Date_Time_Utils' => __DIR__ . '/../..' . '/includes/classes/Utils/class-date-time-utils.php',
        'WP2FA\\Utils\\Debugging' => __DIR__ . '/../..' . '/includes/classes/Utils/class-debugging.php',
        'WP2FA\\Utils\\Generate_Modal' => __DIR__ . '/../..' . '/includes/classes/Utils/class-generate-modal.php',
        'WP2FA\\Utils\\Migration' => __DIR__ . '/../..' . '/includes/classes/Utils/class-migration.php',
        'WP2FA\\Utils\\Request_Utils' => __DIR__ . '/../..' . '/includes/classes/Utils/class-request-utils.php',
        'WP2FA\\Utils\\Settings_Utils' => __DIR__ . '/../..' . '/includes/classes/Utils/class-settings-utils.php',
        'WP2FA\\Utils\\User_Utils' => __DIR__ . '/../..' . '/includes/classes/Utils/class-user-utils.php',
        'WP2FA\\WP2FA' => __DIR__ . '/../..' . '/includes/classes/class-wp2fa.php',
        'WP_Async_Request' => __DIR__ . '/..' . '/deliciousbrains/wp-background-processing/classes/wp-async-request.php',
        'WP_Background_Process' => __DIR__ . '/..' . '/deliciousbrains/wp-background-processing/classes/wp-background-process.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit3b06a2dd4916854033f4aa14fb7e620f::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit3b06a2dd4916854033f4aa14fb7e620f::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit3b06a2dd4916854033f4aa14fb7e620f::$classMap;

        }, null, ClassLoader::class);
    }
}
