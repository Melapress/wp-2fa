# WP 2FA by WP White Security

Easy to use two-factor authentication for your WordPress logins.

https://wordpress.org/plugins/wp-2fa/

# Repository documentation & information

## Dependencies

The project utilizes [Laravel Mix](https://laravel-mix.com/) to manage front-end assets (this is installed using npm).

- [Node >= 12.14.0 & NPM](https://www.npmjs.com/get-npm) - Build packages and front-end 3rd party dependencies are managed through NPM, so you will need that installed globally.
- [bash](https://en.wikipedia.org/wiki/Bash_(Unix_shell)) - Some build and release released tasks are done using unix shell (bash) scripts.
- [composer](https://getcomposer.org/) - Back-end third party dependencies are managed through composer, so you will need to install that globally of download compose.phar executable.

## Getting Started
Follow the steps below to acquire a development version of the WP 2FA Premium plugin.

- Clone the repository
- `cd` into the plugin folder
- run `npm install` to install necessary npm dependencies

## npm commands (high level)
These are high level commands useful during plugin development and whenever you need a copy of the plugin in a zip file.

- `npm run development` - builds development version of front-end assets (CSS, JS, images etc.)
- `npm run dev` - alias for `npm run development`
- `npm run watch` - the same as `npm run dev`, but then it stays active and "watches" for updates to your .css and .js files. If it detects a change, it'll re-build the browser-friendly file and do a live reload of the web page without a refresh.
- `npm run hot` - the same as `npm run watch`, but also supports hot module replacement/reload
- `npm run production` - builds production version of front-end assets (CSS, JS, images etc.)
- `npm run prod` - alias for `npm run production`
- `npm run release` - Creates a ZIP file of the premium plugin ready for release.Careful! This process can change the codebase as it does a number of things, such as removal of some code annotations, chunks of code that belong to free plugin only etc.

## gulp tasks (low level)
These commands and tasks are used by the high level npm commands and also by the free repo sync GitHub workflow.

- `gulp translate` - Regenerates the POT translation file.
- `gulp zip` - Builds a ZIP file for release. This task only creates the ZIP archive. Other tasks need to be executed to prepare the code. See `npm run release`.
- `gulp remove-annotations` - Removes the code annotations marking free-only or premium-only parts of code.
- `gulp remove-premium-only-code` - Removes the premium only code from files - does not remove files not needed in free version, that happens in an automated GitHub workflow.
- `gulp remove-free-only-code` - Removes the free only code from files.
- `gulp replace-latest-version-numbers` - Replaces the plugin version number placeholders (`@since latest`) with the actual version number from `package.json`.
- `gulp replace-plugin-name` - Replaces the plugin name (removes the "Premium" part) in the main plugin file and in the readme.
- `gulp convert-to-free-edition` - Converts the plugin to a free edition.

## Settings

The plugin's settings are stored in the `wp_options` table under `wp_2fa_settings` and `wp_2fa_email_settings`

### Default settings array

For reference, here are all options available together with default values if applicable.

````
$default_settings = array(
  'enable_totp'                  => 'enable_totp',
  'enable_email'                 => 'enable_email',
  'enforcement-policy'            => 'do-not-enforce',
  'excluded_users'               => '',
  'excluded_roles'               => '',
  'enforced_users'               => '',
  'enforced_roles'               => '',
  'grace-period'                 => 3,
  'grace-period-denominator'     => 'days',
  'enable_grace_cron'            => '',
  'enable_destroy_session'       => '',
  'limit_access'                 => '',
  '2fa_settings_last_updated_by' => '',
  '2fa_main_user'                => '',
  'grace-period-expiry-time'     => '',
  'plugin_version'               => WP_2FA_VERSION,
  'delete_data_upon_uninstall'   => '',
  'excluded_sites'               => '',
  'create-custom-user-page'      => 'no',
  'custom-user-page-url'         => '',
  'custom-user-page-id'          => '',
  'hide_remove_button'           => '',
  'grace-policy'                 => 'use-grace-period',
);
````

### Default email settings array

For reference, here are all the email settings together with default values if applicable.

````
$default_settings = array(
  'email_from_setting'                  => 'use-defaults',
  'custom_from_email_address'           => '',
  'custom_from_display_name'            => '',
  'enforced_email_subject'              => $enable_2fa_subject,
  'enforced_email_body'                 => $enable_2fa_body,
  'login_code_email_subject'            => $login_code_subject,
  'login_code_email_body'               => $login_code_body,
  'user_account_locked_email_subject'   => $user_locked_subject,
  'user_account_locked_email_body'      => $user_locked_body,
  'user_account_unlocked_email_subject' => $user_unlocked_subject,
  'user_account_unlocked_email_body'    => $user_unlocked_body,
  'send_enforced_email'                 => 'enable_enforced_email',
  'send_account_locked_email'           => 'enable_account_locked_email',
  'send_account_unlocked_email'         => 'enable_account_unlocked_email',
);
````

### How we use Background Processing

Since 1.5, when saving settings & where applicable we gather users who are eligable for 2FA via direct SQL queries (for speed) and we convert any user objects into a simple ID integer or array consisting of ID and user_login depending on the context.

## Filters

In WP 2FA we have a number of filters which can be used to externally alter certain areas of the plugin. We expect more to be added over time as the plugin grows.

### Determining "user type"

In the UserUtils class, we have a handy function `determine_user_2fa_status` which scans the user object passed to it and determines certain aspect of the user, such as is the user needs to setup 2fa, if they are excluded and many more. Using the below filter, we can add additional items to the `$user_type` array based on whatever checks you wish to do againt the `$user` object.

````
$user_type = apply_filters( 'wp_2fa_additional_user_types', $user_type, $user );
````

### Append content to user profile form

Should anyone wish to modify or add there own content to the user profile form (found under `wp-admin/profile.php` or, user facing using the shortcode or custom profile form), the following filter allows the content to be adjusted with ease.

````
$form_content = apply_filters( 'wp_2fa_append_to_profile_form_content', $form_content );
````

### Filter available 2FA Methods

Note - this is yet to be fully implemented (see https://github.com/WPWhiteSecurity/wp-2fa/issues/349) - this filter allows someone to append there own methods to the array of possible methods.

````
$available_methods = apply_filters( 'wp_2fa_available_2fa_methods', $available_methods );
````


## Shortcodes

WP 2FA offers two shortcodes currently, which are used when an admin wants to give the users the ability to setup 2FA, but without handing over access to the WP Dashboard.

`[wp-2fa-setup-form]` - This shortcode displays the 2FA setup form and accepts the following args.

- `show_preamble` - Can be true/false, if true (false) - no explainer text is shown (the user can add there own and just the form buttons are shown)

`[wp-2fa-setup-notice]` - This shortcode shown a "you must configure 2FA is X" notice and accepts the following argument.

- `configure_2fa_url` - This specifies the URL to the page which contains the actual 2FA form.

## Filters for changing the default behavior

In some cases, an admin may wish to change certain parameters regarding how 2FA works under the hood.
### Override background processing batch size

Typically we process users in groups of 1000 - however you can set your own value.

In order to do that you have to use filter 'wp_2fa_batch_size', the expected return value is the number of users to be processed in a group.

### Allow grace period to be in seconds

When testing you may wish to set the grace period to expire in seconds to speed things up.

In order to do add you have to use filter 'wp_2fa_allow_grace_period_in_seconds', the expected return value is 1.

## Debugging 2FA

To enable logging in WP 2FA, you can add filter "wp_2fa_logging_enabled" that returns true. Recommended option is an mu-plugin.

```
add_filter( 'wp_2fa_logging_enabled', function ( $enabled ) {
	return true;
} );
```

### How do we log events?

Currently we only log during settings updates (as this is where we have seen the most contention in terms of memory usage) - this is done via the following class/code.

````
Debugging::log( $msg );
````

Simply pass your message to the log file containing any info you wish if you want to add more log entry point.

````
// Log the scan start time and part.
$msg .= __( 'Setting are being saved', 'wp-2fa' ) . ' ';
$msg .= "\n";
Debugging::log( $msg );
````

## WP 2FA uses compsoer version 1

Currently, to avoid issues when activating the plugin we are using composer version 1 only. Please do not run composer update (though you can freely update to the latest v1 branch). If you have done so and need to revert back to the latest v1 branch you may use the following command.

````
sudo composer self-update --1
````

## Non-reviewed documentation

Any newly added documentation should be added here.
