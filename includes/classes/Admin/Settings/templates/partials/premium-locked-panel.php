<?php
/**
 * Reusable Premium Locked Panel Component.
 *
 * Renders a premium upsell panel with a banner, and a collapsible list of
 * locked items (roles, features, etc.) showing only the first 3 with a
 * "+ N more" toggle link.
 *
 * Expected variables:
 *   $panel_banner_title  (string) - Banner title text.
 *   $panel_banner_desc   (string) - Banner description text.
 *   $panel_banner_cta    (string) - CTA button label.
 *   $panel_banner_url    (string) - CTA button URL.
 *   $panel_items         (array)  - Array of items to display. Each item:
 *                                   ['title' => string, 'context' => string (optional premium dialog context key)]
 *   $panel_visible_count (int)    - Number of items to show before collapsing (default 3).
 *   $panel_id            (string) - Unique panel ID for JS targeting (optional).
 *
 * @package wp-2fa
 * @since   5.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$panel_visible_count = isset( $panel_visible_count ) ? (int) $panel_visible_count : 3;
$panel_id            = isset( $panel_id ) ? $panel_id : 'wp2fa-locked-panel-' . wp_unique_id();
$total_items         = count( $panel_items );
$hidden_count        = max( 0, $total_items - $panel_visible_count );

// Lock icon SVG (same as used in premium badge).
$lock_icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">'
	. '<path d="M4.66699 6.41667V4.08333C4.66699 3.46449 4.91282 2.871 5.35041 2.43342C5.78799 1.99583 6.38149 1.75 7.00033 1.75C7.61916 1.75 8.21266 1.99583 8.65024 2.43342C9.08783 2.871 9.33366 3.46449 9.33366 4.08333V6.41667M2.91699 7.58333C2.91699 7.27391 3.03991 6.97717 3.2587 6.75838C3.47749 6.53958 3.77424 6.41667 4.08366 6.41667H9.91699C10.2264 6.41667 10.5232 6.53958 10.742 6.75838C10.9607 6.97717 11.0837 7.27391 11.0837 7.58333V11.0833C11.0837 11.3928 10.9607 11.6895 10.742 11.9083C10.5232 12.1271 10.2264 12.25 9.91699 12.25H4.08366C3.77424 12.25 3.47749 12.1271 3.2587 11.9083C3.03991 11.6895 2.91699 11.3928 2.91699 11.0833V7.58333ZM6.41699 9.33333C6.41699 9.48804 6.47845 9.63642 6.58785 9.74581C6.69724 9.85521 6.84562 9.91667 7.00033 9.91667C7.15504 9.91667 7.30341 9.85521 7.4128 9.74581C7.5222 9.63642 7.58366 9.48804 7.58366 9.33333C7.58366 9.17862 7.5222 9.03025 7.4128 8.92085C7.30341 8.81146 7.15504 8.75 7.00033 8.75C6.84562 8.75 6.69724 8.81146 6.58785 8.92085C6.47845 9.03025 6.41699 9.17862 6.41699 9.33333Z" stroke="#646970" stroke-width="1.3125" stroke-linecap="round" stroke-linejoin="round"/>'
	. '</svg>';

?>
<div class="wp2fa-premium-locked-panel" id="<?php echo esc_attr( $panel_id ); ?>">
	<div class="wp2fa-premium-locked-panel__card">
		<?php // --- Banner --- ?>
		<div class="wp2fa-premium-locked-panel__banner" role="region" aria-label="<?php echo esc_attr( $panel_banner_title ); ?>">
			<div class="wp2fa-premium-locked-panel__banner-content">
				<span class="wp2fa-premium-locked-panel__banner-icon" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" width="33" height="49" viewBox="0 0 33 49" fill="none">
						<path d="M10.7607 16.2846L18.7944 8.17949L26.9017 0H13.414H0V8.17949V16.2846L2.65332 13.6077L5.38035 10.8564L8.03367 13.6077L10.7607 16.2846Z" fill="#99FFFF"/>
						<path d="M32.2094 48.9278V37.997V27.1406L21.5224 37.997L10.7617 48.7791L21.5224 48.8534L32.2094 48.9278Z" fill="#3E6BFF"/>
						<path d="M10.7607 27.1406L8.03367 24.3893L5.38035 21.7124L2.65332 18.9611L0 16.2841V27.1406V37.997L2.65332 35.2457L5.38035 32.5688L8.03367 35.2457L10.7607 37.997L18.7944 29.8175L26.9017 21.7124L29.555 24.3893L32.2084 27.1406V16.2841V5.42773L21.5214 16.2841L10.7607 27.1406Z" fill="#40D3F0"/>
					</svg>
				</span>
				<div class="wp2fa-premium-locked-panel__banner-text">
					<h3 class="wp2fa-premium-locked-panel__banner-title"><?php echo esc_html( $panel_banner_title ); ?></h3>
					<?php if ( ! empty( $panel_banner_desc ) ) : ?>
						<p class="wp2fa-premium-locked-panel__banner-desc"><?php echo esc_html( $panel_banner_desc ); ?></p>
					<?php endif; ?>
				</div>
				<?php
				$badge_context_attr = ! empty( $panel_banner_badge_context ) ? ' data-wp2fa-premium-context="' . esc_attr( $panel_banner_badge_context ) . '"' : '';
				?>
				<span class="wp2fa-premium-locked-panel__banner-badge wp2fa-premium-badge" role="button" tabindex="0" data-wp2fa-premium-dialog-trigger="1"<?php echo $badge_context_attr; ?> aria-label="<?php echo esc_attr__( 'Learn more about Premium features', 'wp-2fa' ); ?>">
					<?php echo $lock_icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo esc_html__( 'PREMIUM', 'wp-2fa' ); ?>
				</span>
			</div>
		</div>

		<?php // --- Items list --- ?>
		<ul class="wp2fa-premium-locked-panel__list">
		<?php foreach ( $panel_items as $index => $item ) :
			$is_hidden    = $index >= $panel_visible_count;
			$context_attr = ! empty( $item['context'] ) ? ' data-wp2fa-premium-context="' . esc_attr( $item['context'] ) . '"' : '';
			$lock_is_clickable = empty( $panel_lock_icons_disabled );
			?>
			<li class="wp2fa-premium-locked-panel__item<?php echo $is_hidden ? ' wp2fa-premium-locked-panel__item--hidden' : ''; ?>">
				<?php if ( $lock_is_clickable ) : ?>
				<span class="wp2fa-premium-locked-panel__item-lock wp2fa-premium-badge" role="button" tabindex="0" data-wp2fa-premium-dialog-trigger="1"<?php echo $context_attr; ?> aria-label="<?php echo esc_attr__( 'Learn more about Premium features', 'wp-2fa' ); ?>">
					<?php echo $lock_icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<?php else : ?>
				<span class="wp2fa-premium-locked-panel__item-lock" aria-hidden="true">
					<?php echo $lock_icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<?php endif; ?>
				<span class="wp2fa-premium-locked-panel__item-title"><?php echo esc_html( $item['title'] ); ?></span>
				<span class="wp2fa-premium-locked-panel__item-chevron" aria-hidden="true"></span>
			</li>
		<?php endforeach; ?>
	</ul>

		<?php if ( $hidden_count > 0 ) : ?>
			<button type="button" class="wp2fa-premium-locked-panel__toggle" aria-expanded="false" data-panel-id="<?php echo esc_attr( $panel_id ); ?>">
				<?php
				printf(
					/* translators: %d: number of additional hidden roles/items */
					esc_html__( '+ %d more', 'wp-2fa' ),
					$hidden_count
				);
				?>
			</button>
		<?php endif; ?>
	</div>
</div>
<?php if ( $hidden_count > 0 ) : ?>
<script>
(function() {
	'use strict';
	var panelId = <?php echo wp_json_encode( $panel_id ); ?>;
	var panel = document.getElementById(panelId);
	if (!panel) return;
	var toggle = panel.querySelector('.wp2fa-premium-locked-panel__toggle');
	if (!toggle) return;

	toggle.addEventListener('click', function() {
		var expanded = toggle.getAttribute('aria-expanded') === 'true';
		var hiddenItems = panel.querySelectorAll('.wp2fa-premium-locked-panel__item--hidden');
		for (var i = 0; i < hiddenItems.length; i++) {
			if (expanded) {
				hiddenItems[i].classList.remove('wp2fa-premium-locked-panel__item--visible');
			} else {
				hiddenItems[i].classList.add('wp2fa-premium-locked-panel__item--visible');
			}
		}
		toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
		toggle.style.display = expanded ? '' : 'none';
	});
})();
</script>
<?php endif; ?>
