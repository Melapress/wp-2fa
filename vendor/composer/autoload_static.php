<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit521d961869cacc4be022ce6ad81b1d4c
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
        'WP2FA\\Admin\\Controllers\\Login_Attempts' => __DIR__ . '/../..' . '/includes/classes/Admin/Controllers/class-login-attempts.php',
        'WP2FA\\Admin\\Controllers\\Settings' => __DIR__ . '/../..' . '/includes/classes/Admin/Controllers/class-settings.php',
        'WP2FA\\Admin\\HelpContactUs' => __DIR__ . '/../..' . '/includes/classes/Admin/HelpContactUs.php',
        'WP2FA\\Admin\\PremiumFeatures' => __DIR__ . '/../..' . '/includes/classes/Admin/PremiumFeatures.php',
        'WP2FA\\Admin\\SettingsPage' => __DIR__ . '/../..' . '/includes/classes/Admin/SettingsPage.php',
        'WP2FA\\Admin\\SettingsPages\\Settings_Page_Email' => __DIR__ . '/../..' . '/includes/classes/Admin/SettingsPages/class-settings-page-email.php',
        'WP2FA\\Admin\\SettingsPages\\Settings_Page_General' => __DIR__ . '/../..' . '/includes/classes/Admin/SettingsPages/class-settings-page-general.php',
        'WP2FA\\Admin\\SettingsPages\\Settings_Page_Policies' => __DIR__ . '/../..' . '/includes/classes/Admin/SettingsPages/class-settings-page-policies.php',
        'WP2FA\\Admin\\SettingsPages\\Settings_Page_White_Label' => __DIR__ . '/../..' . '/includes/classes/Admin/SettingsPages/class-settings-page-white-label.php',
        'WP2FA\\Admin\\SetupWizard' => __DIR__ . '/../..' . '/includes/classes/Admin/SetupWizard.php',
        'WP2FA\\Admin\\User' => __DIR__ . '/../..' . '/includes/classes/Admin/User.php',
        'WP2FA\\Admin\\UserListing' => __DIR__ . '/../..' . '/includes/classes/Admin/UserListing.php',
        'WP2FA\\Admin\\UserNotices' => __DIR__ . '/../..' . '/includes/classes/Admin/UserNotices.php',
        'WP2FA\\Admin\\UserProfile' => __DIR__ . '/../..' . '/includes/classes/Admin/UserProfile.php',
        'WP2FA\\Admin\\UserRegistered' => __DIR__ . '/../..' . '/includes/classes/Admin/UserRegistered.php',
        'WP2FA\\Admin\\Views\\FirstTimeWizardSteps' => __DIR__ . '/../..' . '/includes/classes/Admin/Views/FirstTimeWizardSteps.php',
        'WP2FA\\Admin\\Views\\Settings_Page_Render' => __DIR__ . '/../..' . '/includes/classes/Admin/Views/class-settings-page-render.php',
        'WP2FA\\Admin\\Views\\WizardSteps' => __DIR__ . '/../..' . '/includes/classes/Admin/Views/WizardSteps.php',
        'WP2FA\\Authenticator\\Authentication' => __DIR__ . '/../..' . '/includes/classes/Authenticator/Authentication.php',
        'WP2FA\\Authenticator\\BackupCodes' => __DIR__ . '/../..' . '/includes/classes/Authenticator/BackupCodes.php',
        'WP2FA\\Authenticator\\Login' => __DIR__ . '/../..' . '/includes/classes/Authenticator/Login.php',
        'WP2FA\\Authenticator\\Open_SSL' => __DIR__ . '/../..' . '/includes/classes/Authenticator/class-open-ssl.php',
        'WP2FA\\Cron\\CronTasks' => __DIR__ . '/../..' . '/includes/classes/Cron/CronTasks.php',
        'WP2FA\\EmailTemplate' => __DIR__ . '/../..' . '/includes/classes/EmailTemplate.php',
        'WP2FA\\Shortcodes\\Shortcodes' => __DIR__ . '/../..' . '/includes/classes/Shortcodes/Shortcodes.php',
        'WP2FA\\Utils\\AbstractMigration' => __DIR__ . '/../..' . '/includes/classes/Utils/AbstractMigration.php',
        'WP2FA\\Utils\\DateTimeUtils' => __DIR__ . '/../..' . '/includes/classes/Utils/DateTimeUtils.php',
        'WP2FA\\Utils\\Debugging' => __DIR__ . '/../..' . '/includes/classes/Utils/Debugging.php',
        'WP2FA\\Utils\\GenerateModal' => __DIR__ . '/../..' . '/includes/classes/Utils/GenerateModal.php',
        'WP2FA\\Utils\\Migration' => __DIR__ . '/../..' . '/includes/classes/Utils/Migration.php',
        'WP2FA\\Utils\\RequestUtils' => __DIR__ . '/../..' . '/includes/classes/Utils/RequestUtils.php',
        'WP2FA\\Utils\\SettingsUtils' => __DIR__ . '/../..' . '/includes/classes/Utils/SettingsUtils.php',
        'WP2FA\\Utils\\UserUtils' => __DIR__ . '/../..' . '/includes/classes/Utils/UserUtils.php',
        'WP2FA\\WP2FA' => __DIR__ . '/../..' . '/includes/classes/WP2FA.php',
        'WP_Async_Request' => __DIR__ . '/..' . '/deliciousbrains/wp-background-processing/classes/wp-async-request.php',
        'WP_Background_Process' => __DIR__ . '/..' . '/deliciousbrains/wp-background-processing/classes/wp-background-process.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit521d961869cacc4be022ce6ad81b1d4c::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit521d961869cacc4be022ce6ad81b1d4c::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit521d961869cacc4be022ce6ad81b1d4c::$classMap;

        }, null, ClassLoader::class);
    }
}
