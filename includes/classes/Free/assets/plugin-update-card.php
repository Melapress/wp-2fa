<?php
/**
 * Free version update component
 *
 * @since 2.9.2
 * @package wp-2fa
 */

?>

<style>
	/* General Reset */
	* {
		margin: 0;
		padding: 0;
		box-sizing: border-box;
	}
	
	/* Styles - START */
	
	/* Melapress brand font 'Quicksand' — There maybe be a preferable way to add this but this seemed the most discrete. */
	@font-face {
		font-family: 'Quicksand';
		src: url('<?php echo \esc_url( WP_2FA_URL ); ?>includes/classes/Free/assets/fonts/Quicksand-VariableFont_wght.woff2') 
		font-weight: 100 900; /* This indicates that the variable font supports weights from 100 to 900 */
		font-style: normal;
	}
	
	
	.wp-2fa-plugin-update {
		background-color: #1A3060;
		border-radius: 7px;
		color: #fff;
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 1.66rem;
		position: relative;
		overflow: hidden;
		transition: all 0.2s ease-in-out;
		margin: 20px 20px 20px 0; /* Added to fix spacing */
	}
	
	.wp-2fa-plugin-update-content {
		max-width: 45%;
	}
	
	.wp-2fa-plugin-update-title {
		margin: 0;
		font-size: 20px;
		font-weight: bold;
		font-family: Quicksand, sans-serif;
		line-height: 1.44rem;
		color: #fff; /* Added to fix contrast */
	}

	
	.wp-2fa-plugin-update-text {
		margin: .25rem 0 0;
		font-size: 0.875rem;
		line-height: 1.3125rem;
	}
	
	.wp-2fa-plugin-update-text a:link {
		color: #FF8977;
	}
	
	.wp-2fa-cta-link {
		border-radius: 0.25rem;
		background: #FF8977;
		color: #0000EE;
		font-weight: bold;
		text-decoration: none;
		font-size: 0.875rem;
		padding: 0.675rem 1.3rem .7rem 1.3rem;
		transition: all 0.2s ease-in-out;
		display: inline-block;
		margin: .5rem auto;
	}
	
	.wp-2fa-cta-link:hover {
		background: #0000EE;
		color: #FF8977;
	}
	
	.wp-2fa-plugin-update-close {
		background-image: url('<?php echo \esc_url( WP_2FA_URL ); ?>includes/classes/Free/assets/images/close-icon-rev.svg'); /* Path to your close icon */
		background-size: cover;
		width: 18px;
		height: 18px;
		border: none;
		cursor: pointer;
		position: absolute;
		top: 20px;
		right: 20px;
		background-color: transparent;
	}
	
	.wp-2fa-plugin-update::before {
		content: '';
		background-image: url('<?php echo \esc_url( WP_2FA_URL ); ?>includes/classes/Free/assets/images/wp-2fa-updated-bg.png'); /* Background image only displayed on desktop */
		background-size: 100%;
		background-repeat: no-repeat;
		background-position: 100% 51%;
		position: absolute;
		top: 0;
		right: 0;
		bottom: 0;
		left: 0;
		z-index: 0;
	}
	
	.wp-2fa-plugin-update-content, .wp-2fa-plugin-update-close {
		z-index: 1;
	}
	
	@media (max-width: 1200px) {
		.wp-2fa-plugin-update::before {
			display: none;
		}
	
		.wp-2fa-plugin-update-content {
			max-width: 100%;
		}
	}
	
	/* Styles - END */
</style>

<!-- Copy START -->
<div class="wp-2fa-plugin-update wp-2fa-notice" data-dismiss-action="wp2fa_dismiss_upgrade_notice" data-nonce="<?php echo \esc_attr( \wp_create_nonce( 'dismiss_upgrade_notice' ) ); ?>">
	<div class="wp-2fa-plugin-update-content">
		<h2 class="wp-2fa-plugin-update-title"><?php echo esc_html__( 'WP 2FA has been updated to version ', 'wp-2fa' ) . \esc_attr( WP_2FA_VERSION ); ?></h2>
		<p class="wp-2fa-plugin-update-text">
			<?php echo \esc_html__( 'You are now running the latest version of WP 2FA. To see what\'s been included in this update, refer to the plugin\'s release notes and change log where we list all new features, updates, and bug fixes.', 'wp-2fa' ); ?>
		</p>
		<a href="https://melapress.com/wordpress-2fa/releases/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=update_banner" target="_blank" class="wp-2fa-cta-link"><?php echo esc_html__( 'Read the release notes', 'wp-2fa' ); ?></a>
	</div>
	<button aria-label="Close button" class="wp-2fa-plugin-update-close"></button>
</div>
<!-- Copy END -->
