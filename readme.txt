=== WP 2FA - Two-factor authentication for WordPress ===
Contributors: Melapress, robert681
Plugin URI: https://melapress.com/wordpress-2fa/
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl.html
Tags: 2FA, two-factor authentication, 2-factor authentication, WordPress authentication, Google Authenticator
Requires at least: 5.5
Tested up to: 6.8.3
Stable tag: 3.0.0
Requires PHP: 7.4.0

Get better WordPress login security; add two-factor authentication (2FA) for all your users with this easy-to-use plugin.

== Description ==

### A free and easy-to-use two-factor authentication plugin for WordPress

Add an extra layer of security to your WordPress website login and protect your users. Enable two-factor authentication (2FA), the best protection against password leaks, automated password guessing, and brute force attacks.

Use the WP 2FA plugin to enable two-factor authentication for your WordPress administrator, enforce 2FA for all your website users, or for users with specific roles. This plugin is very easy to use; everything can be configured via wizards with clear instructions, so even non-technical users can set up 2FA without requiring technical assistance.

[youtube https://www.youtube.com/watch?v=vRlX_NNGeFo]

[Features](https://melapress.com/wordpress-2fa/features/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wp2fa) | [Getting Started](https://melapress.com/support/kb/wp-2fa-plugin-getting-started/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wp2fa) | [Get the Premium!](https://melapress.com/wordpress-2fa/pricing/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wp2fa)


### 🔒 WP 2FA key plugin features and capabilities
- **Passkeys support** for passwordless logins   
- **Free two-factor authentication (2FA)** for all users  
- **Multiple 2FA methods** supported, including authenticator app (TOTP) and code over email  
- **Developer API** to integrate any alternative 2FA method (WhatsApp, OTP Token, etc.)  
- **Universal 2FA app support** – works with Google Authenticator, Authy, and any TOTP-compatible app  
- **Backup codes** (16 digits) for recovery access  
- **Wizard-driven setup** – no technical knowledge required  
- **2FA policies** to enforce setup with grace periods or instant activation  
- **REST API endpoints** for custom integrations and headless WordPress setups  
- **Dashboard-free setup** – users can configure 2FA without WP admin access  
- **Editable email templates** for full customization  
- **Much more!**
 
### 💎 Upgrade to WP 2FA Premium and get even more benefits

The premium version of WP 2FA comes bundled with even more features to take your WordPress website login security to the next level.

With the premium edition of WP 2FA, you get more 2FA methods, 1-click integration with WooCommerce, trusted devices feature, extensive white labeling capabilities, and much more!

[Check out WP 2FA Premium!](https://melapress.com/wordpress-2fa/pricing/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wp2fa)

### Premium features list

- **Everything in the free version**
- **Full white labeling capabilities** to change all text and visuals in the wizards, emails, SMS, and 2FA pages
- **Support for multiple passkeys per user** for flexible passwordless logins
- **Zero-setup email 2FA** that automatically enrolls users without manual configuration
- **YubiKey hardware key support** for enterprise-grade security
- **Additional 2FA methods** such as SMS, email link, and more
- **Trusted devices** so users can log in without 2FA for a configured period
- **Require 2FA on password reset** to strengthen account protection
- **Allow next user login without 2FA** to help recover accounts locked out of authentication
- **One-click WooCommerce integration** to enable 2FA for customers and store admins
- **And much more!**

Refer to the [WP 2FA plugin features and benefits page](https://melapress.com/wordpress-2fa/features/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wp2fa) to learn more about the benefits of upgrading to WP 2FA Premium.

## 🛠️ Free and premium support

Support for the free edition of WP 2FA is free on the [WordPress support forums](https://wordpress.org/support/plugin/wp-2fa/). Premium world-class support via one-to-one email is available to the Premium users - [upgrade to premium](https://melapress.com/wordpress-2fa/pricing/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wp2fa) to benefit from email support.

For any other queries, feedback, or if you simply want to get in touch with us, please use our [contact form](https://melapress.com/contact/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wp2fa).

#### MAINTAINED & SUPPORTED BY MELAPRESS

Melapress develops high-quality WordPress management and security plugins, such as Melapress Login Security, Melapress Role Editor, and WP Activity Log; the #1 user-rated activity log plugin for WordPress.

Browse our list of [WordPress security and administration plugins](https://melapress.com/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wp2fa) to see how our plugins can help you better manage and improve the security and administration of your WordPress websites and users.
    
== Installing WP 2FA ==

###From within WordPress

1.  Navigate to ‘Plugins' > 'Add New’
2.  Search for ‘WP 2FA’
3.  Install & activate WP 2FA from your Plugins page
  
###Manually

1.  Download the plugin from the WordPress plugins repository
2.  Unzip the zip file and upload the folder to the '/wp-content/plugins/ directory'
3.  Activate the WP 2FA plugin through the ‘Plugins’ menu in WordPress

## As featured on:

- [WP Beginner](https://www.wpbeginner.com/plugins/how-to-add-two-factor-authentication-for-wordpress/)
- [IsitWP](https://www.isitwp.com/best-wordpress-security-authentication-plugins/)
- [WP Astra](https://wpastra.com/two-factor-authentication-wordpress/)
- [MainWP](https://mainwp.com/how-to-use-the-wp-2fa-plugin-on-your-child-sites/)
- [FixRunner](https://www.fixrunner.com/wordpress-two-factor-authentication/)
- [Inmotion Hosting](https://www.inmotionhosting.com/support/edu/wordpress/plugins/wp-2fa/)
- [WP Marmite](https://wpmarmite.com/en/wordpress-two-factor-authentication/)

== Frequently Asked Questions ==

= Does the plugin send any data to Melapress? =
No, the plugin does not send any data to us whatsoever. The only data we receive is license data from the premium edition of the plugin.

= What 2FA methods are available with the plugin? =
The free edition of WP 2FA includes the following 2FA methods: Authenticator app 2FA and code over email. This allows you to use Google Authenticator OTP The premium edition adds YubiKey, one-click email link, SMS 2FA, and Authy push notifications. 

= How can I integrate two-factor authentication (2FA) into my custom login process or AJAX-based form? =
WP 2FA includes a REST API that allows developers to enable and verify 2FA during custom authentication flows, such as AJAX-based login forms, mobile apps, or headless WordPress websites. Refer to the [REST API in WP 2FA documentation](https://melapress.com/support/kb/wp-2fa-rest-api/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wp2fa) for more information.

= How can I ensure I do not get locked out? =
WP 2FA includes backup authentication methods so that if the primary authentication method fails, you and your users can still log in. The free version of the plugin includes backup codes, which can be configured during 2FA configuration or at any point after that from the profile page. The premium edition adds 2FA backup codes over email.

= What happens if I get locked out? =
In the unlikely event that you are unable to supply your 2FA code, there are several steps you can take to gain access to your WordPress dashboard. First, check if there is another administrator who can reset your 2FA. If this is not possible, manually deactivate the plugin, log in without 2FA, re-activate the plugin, and then reconfigure your 2FA. 

=  Does WP 2FA support multi-site networks? = 
Yes, WP 2FA is multisite compatible. The plugin can be activated at the network level. 2FA policies can be enforced on all users, a subsection of users, or per site on the network. It also supports network setups with different domains.

= Does the plugin receive updates? =
We update the plugin fairly regularly to ensure the plugin continues to run in tip-top shape while adding new features from time to time.

= Does the plugin support Google Authenticator? =
Yes, WP 2FA fully supports Google Authenticator on WordPress. [WP 2FA also supports many other 2FA authenticator apps](https://melapress.com/support/kb/wp-2fa-configuring-2fa-apps/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wp2fa).

= Can I get support if I get stuck? =
Support for the free edition of the plugin is provided only via the WordPress.org support forums. You can also refer to our [support pages](https://melapress.com/support/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wp2fa) for all the technical and product documentation.

If you are using the Premium edition, you get direct access to our support team via one-to-one [email support](https://melapress.com/support/submit-ticket/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=mls).

= How can I report security bugs? =
You can report security bugs through the Patchstack Vulnerability Disclosure Program. Please use this [form](https://patchstack.com/database/vdp/wp-2fa). For more details, please refer to our [Melapress plugins security program](https://melapress.com/plugins-security-program/).

== Screenshots ==

1. The first-time install wizard allows you to set up 2FA on your website and for your users within seconds.
2. The wizards make setting up 2FA very easy, so even non-technical users can set up 2FA without requiring help.
3. You can require users to enable 2FA and also give them a grace period to do so.
4. Users can also use one-time codes via email as a two-factor authentication method.
5. You can use policies to require users to instantly set up and use 2FA, so the next time they log in, they will be prompted with this.
6. You can give users a grace period until they configure 2FA. You can also specify what the plugin should do once the grace period is over.
7. It is recommended for all users to also generate backup codes, in case they cannot access the primary device.
8. In the user profile, users only have a few 2FA options, so it is not confusing for them, and everything is self-explanatory.

== Changelog ==

= 3.0.0 (2025-09-23) =

 * **New features**
	 * Zero-setup email 2FA method - automatically enroll users with 2FA without requiring any user setup or intervention .
	 * Added an option to enable/disable automatic email notifications when a user logs in but cannot configure 2FA .

 * **Plugin & functionality improvements**
	 * Backup codes are now 16 digits long for improved security.
	 * Extended the maximum allowed grace period to 90 days.
	 * Updated the plugin logo and artwork.
	 * Replaced the php-jwt library with in-house developed solution for improved security and performance.
	 * Added a new upgrade banner notification.
	 * Accessibility improvements across all plugin wizards; users can now configure 2FA using only the keyboard.
	 * Plugin no longer redirects to the Policies page after updating, improving the upgrade flow and avoiding unwanted redirection loops.
	 * Added the plugin’s branding signature to all Free edition email templates.
	 * Updated the default “From” name and email address used when sending emails.
	 * Improved the method selection step in the setup wizard by reducing the number of displayed methods for a lighter, cleaner look and feel.
	 * Improved the build process to better separate Free and Premium editions, in line with WordPress coding standards.
	 * Improved help texts in several areas of the plugin’s Settings page.
	 * Added a check to handle missing parameters on the lost password page, preventing a fatal error and displaying a proper message instead.

 * **Bug fixes**
	 * Fixed a user-reported PHP error which occurs in certain circumstances; “Uncaught TypeError: call_user_func_array(): Argument #1 ($callback) 'wp_2fa_action_doing_it_wrong_run' not found.”
	 * Fixed typos in the email template shown when a user logs in but cannot configure 2FA due to setup misconfiguration.
	 * Fixed a fatal error on multisite installations when users without the `manage_options` capability attempted access.
	 * Fixed a bug preventing backup codes from being enabled when Yubico was the only available method.
	 * Fixed a bug in the “log out user after 2FA configuration” feature which caused users to be logged out without finalizing 2FA configuration in some cases.
	 * Fixed an issue with the Twilio integration that caused alphanumeric IDs to be rejected by the plugin.
	 * Fixed an issue on multisite where users removed from an excluded subsite were not prompted to configure 2FA when still enforced on another subsite.
	 * Fixed several other user-reported PHP warnings that could occur under certain conditions.
	
Refer to the complete [plugin changelog](https://melapress.com/support/kb/wp-2fa-plugin-changelog/?utm_source=wordpress.org&utm_medium=referral&utm_campaign=WP2FA&utm_content=plugin+repos+description) for more detailed information about what was new, improved and fixed in previous version updates of WP 2FA.
