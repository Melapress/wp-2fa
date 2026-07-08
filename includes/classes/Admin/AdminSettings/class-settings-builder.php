<?php
/**
 * Class: Determine the context in which the plugin is executed.
 *
 * Helper class to determine the proper status of the request.
 *
 * @package wp-2fa
 *
 * @since 4.0.0
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

declare(strict_types=1);

namespace WP2FA\Admin;

use WP2FA\Utils\Settings_Utils;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Upgrade notice class
 */
if ( ! class_exists( '\WP2FA\Admin\Settings_Builder' ) ) {
	/**
	 * Utility class for showing the upgrade notice in the plugins page.
	 *
	 * @package wp-2fa
	 *
	 * @since 4.0.0
	 */
	class Settings_Builder {
		/**
		 * Whether the multi-select-ajax CSS/JS assets have been output.
		 *
		 * @var bool
		 *
		 * @since 4.0.0
		 */
		private static $multi_select_ajax_rendered = false;

		/**
		 * The inner item id of the setting
		 *
		 * @var string
		 *
		 * @since 4.0.0
		 */
		public static $item_id;

		/**
		 * Additional attributes of the item.
		 *
		 * @var string
		 *
		 * @since 4.0.0
		 */
		public static $item_id_attr;

		/**
		 * Item wrapper - the id of the setting + '_item' suffix
		 *
		 * @var string
		 *
		 * @since 4.0.0
		 */
		public static $item_id_wrap;

		/**
		 * The name attribute of the element
		 *
		 * @var string
		 *
		 * @since 4.0.0
		 */
		public static $name_attr;

		/**
		 * Placeholder for the setting
		 *
		 * @var string
		 *
		 * @since 4.0.0
		 */
		public static $placeholder_attr;

		/**
		 * Custom class for the setting
		 *
		 * @var string
		 *
		 * @since 4.0.0
		 */
		public static $custom_class;

		/**
		 * The setting value
		 *
		 * @var mixed
		 *
		 * @since 4.0.0
		 */
		public static $current_value;

		/**
		 * The type of the setting
		 *
		 * @var string
		 *
		 * @since 4.0.0
		 */
		public static $option_type;

		/**
		 * The name of the setting
		 *
		 * @var string
		 *
		 * @since 4.0.0
		 */
		public static $option_name;

		/**
		 * Array with the setting settings
		 *
		 * @var array
		 *
		 * @since 4.0.0
		 */
		public static $settings;

		/**
		 * Array with the data attributes settings
		 *
		 * @var array
		 *
		 * @since 4.0.0
		 */
		public static $data_attrs;

		/**
		 * Integer - minimum value
		 *
		 * @var int
		 *
		 * @since 4.0.0
		 */
		public static $min;

		/**
		 * Integer - maximum value
		 *
		 * @var int
		 *
		 * @since 4.0.0
		 */
		public static $max;

		/**
		 * Integer - step value
		 *
		 * @var int
		 *
		 * @since 4.0.0
		 */
		public static $step;

		/**
		 * Holds the type of the edit HTML field
		 *
		 * @var string
		 *
		 * @since 4.0.0
		 */
		public static $edit_type;

		/**
		 * Holds the validation pattern for the text field
		 *
		 * @var string
		 *
		 * @since 4.0.0
		 */
		public static $validate_pattern;

		/**
		 * Holds the maximum characters for the text field
		 *
		 * @var string
		 *
		 * @since 4.0.0
		 */
		public static $max_chars;

		/**
		 * Holds the title attribute for the text field
		 *
		 * @var string
		 *
		 * @since 4.0.0
		 */
		public static $title_attr;

		/**
		 * The given field is required
		 *
		 * @var string
		 *
		 * @since 4.0.0
		 */
		public static $required;

		/**
		 * If the value is not a boolean, for checkboxes.
		 *
		 * @var string
		 *
		 * @since 4.0.0
		 */
		public static $not_bool;

		/**
		 * Builds the element
		 *
		 * @param array $settings - Array with settings.
		 * @param mixed $data - The data to show.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function create( $settings, $data ) {

			self::$item_id          = null;
			self::$item_id_attr     = null;
			self::$item_id_wrap     = null;
			self::$name_attr        = null;
			self::$placeholder_attr = null;
			self::$custom_class     = null;
			self::$current_value    = null;
			self::$option_type      = null;
			self::$option_name      = null;
			self::$settings         = null;
			self::$edit_type        = null;
			self::$validate_pattern = null;
			self::$max_chars        = null;
			self::$min              = null;
			self::$max              = null;
			self::$step             = null;
			self::$data_attrs       = null;
			self::$not_bool         = null;

			self::prepare_data( $settings, $data );

			if ( empty( self::$option_type ) ) {
				return;
			}

			// Options Without Labels.
			$with_label = false;

			switch ( self::$option_type ) {
				case 'simple-text':
					self::simple_text();
					break;

				case 'number':
					self::number();
					break;

				case 'text':
					self::text();
					break;

				case 'secret':
					self::secret();
					break;

				case 'button':
					self::button();
					break;

				case 'tab-title':
					self::tab_title();
					break;

				case 'section-title':
					self::section_title();
					break;

				case 'section-sub-title':
					self::section_sub_title();
					break;

				case 'list':
					self::list();
					break;

				case 'radio':
					self::radio();
					break;

				case 'group-title':
					self::group_title();
					break;

				case 'description':
					self::description();
					break;

				case 'checkbox':
					self::checkbox();
					break;

				case 'settings-label':
					self::settings_label();
					break;

				case 'tip':
					self::tip();
					break;

				case 'editor':
					self::editor();
					break;

				case 'textarea':
					self::textarea();
					break;

				case 'color':
					self::color();
					break;

				case 'image-select':
					self::image_select();
					break;

				case 'font-select':
					self::font_select();
					break;

				case 'tabbed-navigation':
					self::tabbed_navigation();
					break;

				case 'toggle-checkbox':
					self::toggle_checkbox();
					break;

				case 'user-roles':
					self::user_roles();
					break;

				case 'stat-cards':
					self::stat_cards();
					break;

				case 'data-table':
					self::data_table();
					break;

				case 'multi-select-ajax':
					self::multi_select_ajax();
					break;

				case 'sortable-list':
					self::sortable_list();
					break;

				case 'select':
					self::select();
					break;

				case 'url-prefixed-text':
					self::url_prefixed_text();
					break;

				case 'breadcrumb':
					self::breadcrumb();
					break;
			}

			self::hint();
		}

		/**
		 * Textarea
		 *
		 * @since 4.0.0
		 */
		private static function textarea() {
			?>
			<div class="body-editor-inner">
				<div class="editor-area">
					<?php
					$textarea_value = self::$current_value;
					if ( \is_array( $textarea_value ) ) {
						$textarea_value = \implode( "\n", $textarea_value );
					}
					?>
					<textarea style="width: 100%;" <?php echo self::$item_id_attr; ?> <?php echo self::$name_attr; ?>
					rows="5"><?php echo \esc_textarea( (string) $textarea_value ); ?></textarea>
				</div><!-- /.editor-area -->
			<?php
			if ( ! empty( self::$settings['legend'] ) ) {
				?>
				<div class="token-list" aria-label="Available tokens">
					<div class="token-row">
					<?php
					foreach ( self::$settings['legend'] as $item ) {
						?>
						<span class="token-badge"><?php echo esc_html( $item ); ?></span>
						<?php
					}
					?>
					</div>
				</div>
				<?php
			}
			?>
			</div>
			<?php
		}

		/**
		 * Tabbed Navigation
		 *
		 * @since 4.0.0
		 */
		private static function tabbed_navigation() {
			$styles = '';
			$labels = '';
			$radios = '';

			$lock_icon = '<svg class="wp2fa-tab-lock-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">'
				. '<path d="M4.66699 6.41667V4.08333C4.66699 3.46449 4.91282 2.871 5.35041 2.43342C5.78799 1.99583 6.38149 1.75 7.00033 1.75C7.61916 1.75 8.21266 1.99583 8.65024 2.43342C9.08783 2.871 9.33366 3.46449 9.33366 4.08333V6.41667M2.91699 7.58333C2.91699 7.27391 3.03991 6.97717 3.2587 6.75838C3.47749 6.53958 3.77424 6.41667 4.08366 6.41667H9.91699C10.2264 6.41667 10.5232 6.53958 10.742 6.75838C10.9607 6.97717 11.0837 7.27391 11.0837 7.58333V11.0833C11.0837 11.3928 10.9607 11.6895 10.742 11.9083C10.5232 12.1271 10.2264 12.25 9.91699 12.25H4.08366C3.77424 12.25 3.47749 12.1271 3.2587 11.9083C3.03991 11.6895 2.91699 11.3928 2.91699 11.0833V7.58333ZM6.41699 9.33333C6.41699 9.48804 6.47845 9.63642 6.58785 9.74581C6.69724 9.85521 6.84562 9.91667 7.00033 9.91667C7.15504 9.91667 7.30341 9.85521 7.4128 9.74581C7.5222 9.63642 7.58366 9.48804 7.58366 9.33333C7.58366 9.17862 7.5222 9.03025 7.4128 8.92085C7.30341 8.81146 7.15504 8.75 7.00033 8.75C6.84562 8.75 6.69724 8.81146 6.58785 8.92085C6.47845 9.03025 6.41699 9.17862 6.41699 9.33333Z" stroke="#646970" stroke-width="1.3125" stroke-linecap="round" stroke-linejoin="round"/>'
				. '</svg>';

			$i = 0;
			foreach ( self::$current_value as $value ) {
				$is_locked = ! empty( $value['locked'] );

				if ( $is_locked ) {
					$context_attr = '';
					if ( ! empty( $value['premium_context'] ) ) {
						$context_attr = ' data-wp2fa-premium-context="' . \esc_attr( $value['premium_context'] ) . '"';
					}
					$labels .= '<span class="tab-label tab-label--locked wp2fa-premium-badge" role="button" tabindex="0"' . $context_attr . '>' . \esc_html( $value['title'] ) . ' ' . $lock_icon . '</span>';
				} else {
					++$i;
					$checked = ( 1 === $i ) ? ' checked' : '';
					$styles .= '#tab-' . $value['id'] . ':checked ~ .tab-panel-' . $value['id'] . ' { display: block; } ';
					$styles .= '#tab-' . $value['id'] . ':checked ~ .tab-nav .tab-label[for="tab-' . $value['id'] . '"] { color: var(--blue-color); border-bottom-color: var(--blue-color); } ';
					$labels .= '<label class="tab-label" for="tab-' . $value['id'] . '">' . \esc_html( $value['title'] ) . '</label>';
					$radios .= '<input class="tab-radio" type="radio" id="tab-' . $value['id'] . '" name="' . self::$option_name . '"' . $checked . '>';
				}
			}
			?>
			<style>
				<?php echo $styles; ?>
				.tab-label--locked {
					cursor: pointer;
					opacity: 0.7;
					display: inline-flex;
					align-items: center;
					gap: 4px;
				}
				.wp2fa-tab-lock-icon {
					flex-shrink: 0;
				}
			</style>
			<?php echo $radios; ?>
			<nav class="tab-nav" aria-label="Template sections">
				<?php echo $labels; ?>
			</nav>

			<?php
		}

		/**
		 * Editor
		 *
		 * @since 4.0.0
		 */
		private static function editor() {
			?>
			<div class="body-editor-inner">
			<?php

			// Settings.
			$settings                  = ! empty( self::$settings['editor'] ) ? self::$settings['editor'] : array(
				'editor_height' => '320',
				'media_buttons' => false,
			);
			$settings['textarea_name'] = self::$option_name;

			self::$current_value = ! empty( self::$settings['kses'] ) ? wp_kses_stripslashes( stripslashes( self::$current_value ) ) : self::$current_value;

			if ( null === self::$current_value ) {
				self::$current_value = '';
			}

			?>
				<!-- Editor (Visual / Code) -->
				<div class="editor-area">
					<?php

					\wp_editor(
						self::$current_value,
						self::$item_id,
						$settings
					);
					?>
				</div><!-- /.editor-area -->
			<?php
			if ( ! empty( self::$settings['legend'] ) ) {
				?>
				<div class="token-list" aria-label="Available tokens">
					<div class="token-row">
					<?php
					foreach ( self::$settings['legend'] as $item ) {
						?>
						<span class="token-badge"><?php echo esc_html( $item ); ?></span>
						<?php
					}
					?>
					</div>
				</div>
				<?php
			}
			?>
			</div>
			<?php
		}

		/**
		 * Tip Message
		 *
		 * @since 4.0.0
		 */
		private static function tip() {
			if ( ! empty( self::$settings['text'] ) ) {
				?>
				<div class="notice-tip">
					<div class="notice-tip-body">
						<strong><?php echo \esc_html__( 'Tip', 'wp-2fa' ); ?></strong>
					<?php echo self::$settings['text']; ?>
					</div>
				</div>
				<?php
			}
		}

		/**
		 * Setting Description
		 *
		 * @since 4.0.0
		 */
		private static function hint() {

			if ( ! empty( self::$settings['hint'] ) ) {
				?>
				<span class="extra-text">
				<?php echo self::$settings['hint']; ?>
				</span>
				<?php
			}
		}

		/**
		 * Button
		 *
		 * @since 4.0.0
		 */
		private static function button() {
			$type_attr = 'type="button"';
			$pattern   = '';
			$step      = '';
			$max_chars = '';
			if ( ! empty( self::$edit_type ) ) {
				$type_attr = ' type="' . self::$edit_type . '"';
			}
			if ( ! empty( self::$validate_pattern ) ) {
				$pattern = ' pattern="' . self::$validate_pattern . '"';
			}
			if ( ! empty( self::$step ) ) {
				$step = ' step="' . self::$step . '"';
			}
			if ( ! empty( self::$max_chars ) ) {
				$max_chars = ' maxlength="' . self::$max_chars . '"';
			}

			if ( self::$data_attrs && is_array( self::$data_attrs ) && ! empty( self::$data_attrs ) ) {
				foreach ( self::$data_attrs as $data_attr_key => $data_attr_value ) {
					$type_attr .= ' data-' . $data_attr_key . '="' . \esc_attr( $data_attr_value ) . '"';
				}
			}
			?>
			<input <?php echo self::$item_id_attr; ?> class="<?php echo self::$custom_class; ?>" <?php echo self::$name_attr; ?> <?php echo $type_attr; ?> value="<?php echo \esc_attr( self::$current_value ); ?>" <?php echo self::$placeholder_attr; ?><?php echo $pattern; ?><?php echo $max_chars; ?><?php echo ( ( self::$required ) ? ' required' : '' ); ?><?php echo $step; ?>>
			<?php
		}

		/**
		 * Radio
		 *
		 * @since 4.0.0
		 */
		private static function radio() {

			?>
			<div class="settings-control <?php echo self::$custom_class; ?>" <?php echo self::$item_id_wrap; ?>>
			<?php
			$i = 0;
			foreach ( self::$settings['options'] as $option_key => $option ) {
				++$i;

				$checked = '';
				if ( ( ! empty( self::$current_value ) && self::$current_value === $option_key ) || ( empty( self::$current_value ) && 1 === $i ) ) {
					$checked = 'checked="checked"';
				}

				?>
				<label class="radio-option">
					<input <?php echo self::$name_attr; ?> <?php echo $checked; ?> type="radio" value="<?php echo $option_key; ?>">
				<?php echo $option; ?>
				</label>
				<?php
			}

			?>
			</div>
			<div class="clear"></div>

			<?php
			if ( empty( self::$settings['toggle'] ) ) {
				return;
			}
			?>

			<script>
				document.addEventListener('DOMContentLoaded', function() {
					// Hide all option elements
					if ( document.querySelectorAll('.<?php echo esc_js( self::$item_id ); ?>-options') ) {
						document.querySelectorAll('.<?php echo esc_js( self::$item_id ); ?>-options').forEach(function(el) {
							el.style.display = 'none';
						});
					}

					<?php
					if ( isset( self::$settings['toggle'][ self::$current_value ] ) ) {
						if ( ! empty( self::$settings['toggle'][ self::$current_value ] ) ) {
							?>
							document.querySelector('<?php echo esc_js( self::$settings['toggle'][ self::$current_value ] ); ?>').style.display = 'block';
							<?php
						}
					} elseif ( is_array( self::$settings['toggle'] ) ) {
						$first_elem = reset( self::$settings['toggle'] );
						?>
						if ( '<?php echo esc_js( $first_elem ); ?>'.trim() && document.querySelector('<?php echo esc_js( $first_elem ); ?>') ) {
							document.querySelector('<?php echo esc_js( $first_elem ); ?>').style.display = 'block';
						}
						<?php
					}
					?>

					document.querySelectorAll("input[name='<?php echo esc_js( self::$option_name ); ?>']").forEach(function(radio) {
						radio.addEventListener('change', function() {
							var selected_val = this.value;
							if ( document.querySelectorAll('.<?php echo esc_js( self::$item_id ); ?>-options') ) {
								document.querySelectorAll('.<?php echo esc_js( self::$item_id ); ?>-options').forEach(function(el) {
									el.style.display = 'none';
								});
							}

							<?php
							foreach ( self::$settings['toggle'] as $tg_item_name => $tg_item_id ) {
								if ( ! empty( $tg_item_id ) ) {
									?>
									if (selected_val === '<?php echo esc_js( $tg_item_name ); ?>') {
										document.querySelector('<?php echo esc_js( $tg_item_id ); ?>').style.display = 'block';
									} else {
										document.querySelector('<?php echo esc_js( $tg_item_id ); ?>').style.display = 'none';
									}
									<?php
								}
							}
							?>
						});
					});
				});
			</script>
			<?php
		}

		/**
		 * Section Title
		 *
		 * @since 4.0.0
		 */
		private static function section_sub_title() {
			?>
			<h4 class="section-sub-title <?php echo self::$custom_class; ?>" <?php echo self::$item_id_attr; ?>><?php echo \esc_html( self::$settings['title'] ); ?></h4>
			<?php
		}

		/**
		 * Section Title
		 *
		 * @since 4.0.0
		 */
		private static function section_title() {
			?>
			<h3 class="section-title <?php echo self::$custom_class; ?>" <?php echo self::$item_id_attr; ?>><?php echo \esc_html( self::$settings['title'] ); ?></h3>
			<?php
		}

		/**
		 * Settings Label
		 *
		 * @since 4.0.0
		 */
		private static function settings_label() {
			?>
			<div class="settings-label"><?php echo self::$settings['text']; ?></div>
			<?php
		}

		/**
		 * Checkbox
		 *
		 * @since 4.0.0
		 */
		private static function checkbox() {

			/**
			 * Old logic is messy
			 * The 'not_bool' setting is used when the value of the checkbox is not a boolean, but a specific value (e.g. 'yes' or 'enabled'), and the checkbox should be checked if the current value matches that specific value. In this case, the 'value' setting specifies the value that should be submitted when the checkbox is checked.
			 */
			if ( self::$not_bool ) {
				/**
				 * For what is worse - some of them have values == element ids, but someones do not.
				 * This solves the case - once not_bool is set, if the 'not_id' is present, it decides the state comparing it to the value, if not - to the element ID.
				 */
				if ( isset( self::$settings['not_id'] ) && self::$settings['not_id'] ) {
					$checked = \checked( self::$current_value, ( self::$settings['value'] ) ? self::$settings['value'] : '', false );
				} else {
					$checked = \checked( self::$current_value, self::$item_id, false );
				}

				$value = self::$settings['value'] ?? '';

				if ( $value ) {
					self::$name_attr .= ' value="' . $value . '"';
				}
			} else {

				$checked = \checked( Settings_Utils::string_to_bool( self::$current_value ), true, false );
			}
			?>

			<?php $disabled_attr = ! empty( self::$settings['disabled'] ) ? ' disabled' : ''; ?>
			<label class="checkbox-option <?php echo self::$custom_class; ?><?php echo $disabled_attr; ?>"><input type="checkbox"  <?php echo self::$item_id_attr; ?> <?php echo self::$name_attr; ?> <?php echo $checked; ?>><?php echo self::$settings['text']; ?></label>

			<?php
		}

		/**
		 * Simple Text
		 *
		 * @since 4.0.0
		 */
		private static function simple_text() {
			?>
			<span <?php echo self::$item_id_wrap; ?> class="<?php echo self::$custom_class; ?>">
				<?php echo self::$settings['text']; ?>
			</span>
			<?php
		}

		/**
		 * Text
		 *
		 * @since 4.0.0
		 */
		private static function text() {
			$type_attr  = 'type="text"';
			$pattern    = '';
			$step       = '';
			$max_chars  = '';
			$title_attr = '';

			if ( ! empty( self::$edit_type ) ) {
				$type_attr = ' type="' . self::$edit_type . '"';
			}
			if ( ! empty( self::$title_attr ) ) {
				$title_attr = ' title="' . self::$title_attr . '"';
			}
			if ( ! empty( self::$validate_pattern ) ) {
				$pattern = ' pattern="' . self::$validate_pattern . '"';
			}
			if ( ! empty( self::$step ) ) {
				$step = ' step="' . self::$step . '"';
			}
			if ( ! empty( self::$max_chars ) ) {
				$max_chars = ' maxlength="' . self::$max_chars . '"';
			}

			if ( 'password' === self::$edit_type ) {
				$type_attr .= ' autocomplete="new-password"';
			}

			if ( self::$data_attrs && is_array( self::$data_attrs ) && ! empty( self::$data_attrs ) ) {
				foreach ( self::$data_attrs as $data_attr_key => $data_attr_value ) {
					$type_attr .= ' data-' . $data_attr_key . '="' . \esc_attr( $data_attr_value ) . '"';
				}
			}
			?>
			<input <?php echo self::$item_id_attr; ?> class="<?php echo self::$custom_class; ?>" <?php echo $title_attr; ?> <?php echo self::$name_attr; ?> <?php echo $type_attr; ?> value="<?php echo \esc_attr( self::$current_value ); ?>" <?php echo self::$placeholder_attr; ?><?php echo $pattern; ?><?php echo $max_chars; ?><?php echo ( ( self::$required ) ? ' required' : '' ); ?><?php echo $step; ?>>
			<?php
		}

		/**
		 * Secret - masked text input with eye toggle
		 *
		 * @since 3.2.1
		 */
		private static function secret() {
			$pattern   = '';
			$step      = '';
			$max_chars = '';

			if ( ! empty( self::$validate_pattern ) ) {
				$pattern = ' pattern="' . self::$validate_pattern . '"';
			}
			if ( ! empty( self::$step ) ) {
				$step = ' step="' . self::$step . '"';
			}
			if ( ! empty( self::$max_chars ) ) {
				$max_chars = ' maxlength="' . self::$max_chars . '"';
			}

			$data_attr_string = '';
			if ( self::$data_attrs && is_array( self::$data_attrs ) && ! empty( self::$data_attrs ) ) {
				foreach ( self::$data_attrs as $data_attr_key => $data_attr_value ) {
					$data_attr_string .= ' data-' . $data_attr_key . '="' . \esc_attr( $data_attr_value ) . '"';
				}
			}
			?>
			<span class="wp2fa-secret-field-wrap">
				<input <?php echo self::$item_id_attr; ?> class="<?php echo self::$custom_class; ?> wp2fa-secret-field" <?php echo self::$name_attr; ?> type="text" autocomplete="off" value="<?php echo \esc_attr( self::$current_value ); ?>" <?php echo self::$placeholder_attr; ?><?php echo $pattern; ?><?php echo $max_chars; ?><?php echo ( ( self::$required ) ? ' required' : '' ); ?><?php echo $step; ?><?php echo $data_attr_string; ?>>
				<button type="button" class="wp2fa-secret-toggle" aria-label="<?php \esc_attr_e( 'Show', 'wp-2fa' ); ?>">
					<svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zm0 12.5c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
				</button>
			</span>
			<?php
		}

		/**
		 * Description Message
		 *
		 * @since 4.0.0
		 */
		private static function description() {
			?>
			<p <?php echo self::$item_id_wrap; ?> class="description <?php echo self::$custom_class; ?>">
				<?php echo self::$settings['text']; ?>
			</p>
			<?php
		}

		/**
		 * Color Picker - uses WP core wp-color-picker
		 *
		 * @since 4.0.0
		 */
		private static function color() {
			?>
			<div class="wp2fa-color-picker-wrap">
				<input <?php echo self::$item_id_attr; ?> <?php echo self::$name_attr; ?>
					type="text"
					class="wp2fa-color-picker<?php echo self::$custom_class; ?>"
					value="<?php echo \esc_attr( self::$current_value ); ?>">
			</div>
			<?php
		}

		/**
		 * Image / Logo selector - uses WP core media library
		 *
		 * Expected settings keys:
		 *   'button_text'  (string) - Label on the select button.
		 *   'description'  (string) - Helper text shown next to the button.
		 *
		 * @since 4.0.0
		 */
		private static function image_select() {
			$button_text = ! empty( self::$settings['button_text'] ) ? self::$settings['button_text'] : \esc_html__( 'Select image', 'wp-2fa' );
			$description = ! empty( self::$settings['description'] ) ? self::$settings['description'] : '';
			$has_image   = ! empty( self::$current_value );
			?>
			<div class="wp2fa-image-select-wrap">
				<div class="wp2fa-image-select-controls">
					<button type="button"
						class="button wp2fa-media-upload<?php echo self::$custom_class; ?>"
						data-target="<?php echo \esc_attr( self::$item_id ); ?>">
						<?php echo \esc_html( $button_text ); ?>
					</button>
					<?php if ( $description ) : ?>
						<span class="wp2fa-image-desc"><?php echo \esc_html( $description ); ?></span>
					<?php endif; ?>
				</div>
				<input <?php echo self::$item_id_attr; ?> <?php echo self::$name_attr; ?>
					type="hidden"
					value="<?php echo \esc_attr( self::$current_value ); ?>">
				<div class="wp2fa-image-preview<?php echo $has_image ? '' : ' hidden'; ?>">
					<img src="<?php echo $has_image ? \esc_url( self::$current_value ) : ''; ?>" alt="">
					<button type="button" class="wp2fa-media-remove"
						title="<?php \esc_attr_e( 'Remove', 'wp-2fa' ); ?>">&#x2715;</button>
				</div>
			</div>
			<?php
		}

		/**
		 * Font selector - a styled <select> with common web-safe font stacks.
		 * The stored value is the full CSS font-family string (e.g. "Arial, sans-serif").
		 *
		 * @since 4.0.0
		 */
		private static function font_select() {
			$font_families = array(
				'Arial, Helvetica, sans-serif'          => 'Arial',
				"'Arial Black', Gadget, sans-serif"     => 'Arial Black',
				"'Bookman Old Style', serif"            => 'Bookman Old Style',
				"'Comic Sans MS', cursive"              => 'Comic Sans MS',
				'Courier, monospace'                    => 'Courier',
				'Garamond, serif'                       => 'Garamond',
				'Georgia, serif'                        => 'Georgia',
				'Impact, Charcoal, sans-serif'          => 'Impact',
				"'Lucida Console', Monaco, monospace"   => 'Lucida Console',
				"'Lucida Sans Unicode', 'Lucida Grande', sans-serif" => 'Lucida Sans',
				"'MS Sans Serif', Geneva, sans-serif"   => 'MS Sans Serif',
				"'MS Serif', 'New York', sans-serif"    => 'MS Serif',
				"'Palatino Linotype', 'Book Antiqua', Palatino, serif" => 'Palatino Linotype',
				'Tahoma, Geneva, sans-serif'            => 'Tahoma',
				"'Times New Roman', Times, serif"       => 'Times New Roman',
				"'Trebuchet MS', Helvetica, sans-serif" => 'Trebuchet MS',
				'Verdana, Geneva, sans-serif'           => 'Verdana',
			);
			?>
			<div class="wp2fa-font-select-wrap">
				<select <?php echo self::$item_id_attr; ?> <?php echo self::$name_attr; ?>
					class="wp2fa-font-select<?php echo self::$custom_class; ?>">
					<?php
					foreach ( $font_families as $value => $label ) {
						$selected = \selected( self::$current_value, $value, false );
						?>
						<option value="<?php echo \esc_attr( $value ); ?>" <?php echo $selected; ?>
							style="font-family: <?php echo \esc_attr( $value ); ?>;">
							<?php echo \esc_html( $label ); ?>
						</option>
						<?php
					}
					?>
				</select>
			</div>
			<?php
		}

		/**
		 * Toggle Checkbox - renders a toggle switch that behaves like a checkbox.
		 *
		 * @since 4.0.0
		 */
		private static function toggle_checkbox() {

			if ( self::$not_bool ) {
				if ( isset( self::$settings['not_id'] ) && self::$settings['not_id'] ) {
					$checked = \checked( self::$current_value, ( isset( self::$settings['value'] ) ? self::$settings['value'] : '' ), false );
				} else {
					$checked = \checked( self::$current_value, self::$item_id, false );
				}
				$value = self::$settings['value'] ?? '';

				if ( $value ) {
					self::$name_attr .= ' value="' . $value . '"';
				}
			} else {
				$checked = \checked( Settings_Utils::string_to_bool( self::$current_value ), true, false );
			}

			$extra_attrs = '';
			if ( self::$data_attrs && is_array( self::$data_attrs ) && ! empty( self::$data_attrs ) ) {
				foreach ( self::$data_attrs as $data_attr_key => $data_attr_value ) {
					$extra_attrs .= ' data-' . \esc_attr( $data_attr_key ) . '="' . \esc_attr( $data_attr_value ) . '"';
				}
			}
			?>
			<label class="toggle-switch <?php echo self::$custom_class; ?>">
				<input type="checkbox" <?php echo self::$item_id_attr; ?> <?php echo self::$name_attr; ?> <?php echo $checked; ?><?php echo $extra_attrs; ?>>
				<span class="toggle-slider"></span>
				<?php if ( ! empty( self::$settings['text'] ) ) { ?>
					<span class="toggle-label"><?php echo self::$settings['text']; ?></span>
				<?php } ?>
			</label>
			<?php
		}

		/**
		 * User Roles - renders a multi-column grid of checkboxes for user roles.
		 *
		 * Expected settings keys:
		 *   'roles'       (array)  - Associative array of role_slug => display_name.
		 *   'checked'     (array)  - Array of role slugs that are currently checked.
		 *   'name_prefix' (string) - The name attribute prefix for each checkbox.
		 *
		 * @since 4.0.0
		 */
		private static function user_roles() {
			$roles       = ! empty( self::$settings['roles'] ) ? self::$settings['roles'] : array();
			$checked     = ! empty( self::$settings['checked'] ) ? self::$settings['checked'] : array();
			$name_prefix = ! empty( self::$settings['name_prefix'] ) ? self::$settings['name_prefix'] : '';
			?>
			<div <?php echo self::$item_id_wrap; ?> class="user-roles-grid <?php echo self::$custom_class; ?>">
				<?php foreach ( $roles as $role_slug => $role_name ) { ?>
					<label class="checkbox-option user-role-item" for="role-<?php echo \esc_attr( $role_slug ); ?>">
						<input type="checkbox"
							id="role-<?php echo \esc_attr( $role_slug ); ?>"
							name="<?php echo \esc_attr( $name_prefix ); ?>[<?php echo \esc_attr( $role_slug ); ?>]"
							value="yes"
							<?php \checked( in_array( $role_slug, $checked, true ) ); ?>>
						<?php echo \esc_html( $role_name ); ?>
					</label>
				<?php } ?>
			</div>
			<?php
		}

		/**
		 * Stat Cards - renders a row of statistic summary cards.
		 *
		 * Expected settings keys:
		 *   'cards' (array) - Array of arrays with 'value' and 'label' keys.
		 *
		 * @since 4.0.0
		 */
		private static function stat_cards() {
			$cards = ! empty( self::$settings['cards'] ) ? self::$settings['cards'] : array();
			?>
			<div <?php echo self::$item_id_wrap; ?> class="stat-cards-row <?php echo self::$custom_class; ?>">
				<?php foreach ( $cards as $card ) { ?>
					<div class="stat-card">
						<span class="stat-value"><?php echo \esc_html( (string) $card['value'] ); ?></span>
						<span class="stat-label"><?php echo \esc_html( $card['label'] ); ?></span>
					</div>
				<?php } ?>
			</div>
			<?php
		}

		/**
		 * Data Table - renders a data table with header and rows.
		 *
		 * Expected settings keys:
		 *   'header'   (array)  - Array of column header strings.
		 *   'rows'     (array)  - Array of row arrays. Each row value can be a string or
		 *                         an associative array with 'url' and 'label' (renders as a link).
		 *   'table_id' (string) - Optional HTML id for the table element.
		 *
		 * @since 4.0.0
		 */
		private static function data_table() {
			$header   = ! empty( self::$settings['header'] ) ? self::$settings['header'] : array();
			$rows     = ! empty( self::$settings['rows'] ) ? self::$settings['rows'] : array();
			$table_id = ! empty( self::$settings['table_id'] ) ? self::$settings['table_id'] : '';
			$id_attr  = $table_id ? ' id="' . \esc_attr( $table_id ) . '"' : '';
			?>
			<div class="report-table-wrap <?php echo self::$custom_class; ?>">
				<table class="report-data-table"<?php echo $id_attr; ?>>
					<?php if ( ! empty( $header ) ) { ?>
					<thead>
						<tr>
							<?php foreach ( $header as $cell ) { ?>
								<th><?php echo \esc_html( $cell ); ?></th>
							<?php } ?>
						</tr>
					</thead>
					<?php } ?>
					<tbody>
						<?php foreach ( $rows as $row ) { ?>
						<tr>
							<?php foreach ( $row as $cell ) { ?>
								<td>
									<?php
									if ( \is_array( $cell ) && isset( $cell['url'], $cell['label'] ) ) {
										echo '<a href="' . \esc_url( $cell['url'] ) . '">' . \esc_html( $cell['label'] ) . '</a>';
									} else {
										echo \esc_html( (string) $cell );
									}
									?>
								</td>
							<?php } ?>
						</tr>
						<?php } ?>
					</tbody>
				</table>
			</div>
			<?php
		}

		/**
		 * Multi-select with AJAX autocomplete search (select2-like, vanilla JS).
		 *
		 * Expected settings keys:
		 *   'items'       (array)  - Array of [ 'id' => ..., 'text' => ... ] for pre-selected items.
		 *   'placeholder' (string) - Placeholder text for the search input.
		 *   'data_attrs'  (array)  - Must contain 'search-type' ('users' or 'roles').
		 *                            Optional 'min-chars' (default '2').
		 *
		 * The hidden input stores a comma-separated list of selected IDs for form submission.
		 *
		 * @since 4.0.0
		 */
		private static function multi_select_ajax() {
			$items       = ! empty( self::$settings['items'] ) ? self::$settings['items'] : array();
			$search_type = '';
			$min_chars   = '2';

			if ( ! empty( self::$data_attrs ) && is_array( self::$data_attrs ) ) {
				$search_type = isset( self::$data_attrs['search-type'] ) ? self::$data_attrs['search-type'] : '';
				$min_chars   = isset( self::$data_attrs['min-chars'] ) ? self::$data_attrs['min-chars'] : '2';
			}

			$placeholder = ! empty( self::$settings['placeholder'] ) ? self::$settings['placeholder'] : '';

			if ( ! self::$multi_select_ajax_rendered ) {
				self::multi_select_ajax_assets();
				self::$multi_select_ajax_rendered = true;
			}

			?>
			<div class="wp2fa-msa-wrap<?php echo self::$custom_class; ?>"
				<?php echo self::$item_id_wrap; ?>
				data-search-type="<?php echo \esc_attr( $search_type ); ?>"
				data-min-chars="<?php echo \esc_attr( $min_chars ); ?>">
				<div class="wp2fa-msa-tags-input">
					<?php foreach ( $items as $item ) { ?>
						<span class="wp2fa-msa-tag" data-id="<?php echo \esc_attr( $item['id'] ); ?>">
							<?php echo \esc_html( $item['text'] ); ?>
							<button type="button" class="wp2fa-msa-remove" aria-label="<?php \esc_attr_e( 'Remove', 'wp-2fa' ); ?>">&times;</button>
						</span>
					<?php } ?>
					<input type="text"
						class="wp2fa-msa-input"
						placeholder="<?php echo \esc_attr( $placeholder ); ?>"
						autocomplete="off">
				</div>
				<div class="wp2fa-msa-dropdown"></div>
				<input type="hidden"
					class="wp2fa-msa-value"
					<?php echo self::$name_attr; ?>
					value="<?php echo \esc_attr( self::$current_value ); ?>">
			</div>
			<?php
		}

		/**
		 * Output the shared CSS and JS assets for the multi-select-ajax component.
		 * Called once per page load via the static flag.
		 *
		 * @since 4.0.0
		 */
		private static function multi_select_ajax_assets() {
			?>
			<style>
				.wp2fa-msa-wrap{position:relative;max-width:500px}
				.wp2fa-msa-tags-input{display:flex;flex-wrap:wrap;align-items:center;gap:4px;padding:4px 8px;border:1px solid #8c8f94;border-radius:4px;background:#fff;cursor:text;min-height:36px}
				.wp2fa-msa-tags-input:focus-within{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}
				.wp2fa-msa-tag{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;background:#f0f0f1;border:1px solid #c3c4c7;border-radius:3px;font-size:13px;line-height:1.4;white-space:nowrap}
				.wp2fa-msa-remove{background:none;border:none;padding:0 2px;cursor:pointer;font-size:16px;line-height:1;color:#787c82}
				.wp2fa-msa-remove:hover{color:#d63638}
				.wp2fa-msa-input{border:none !important;outline:none !important;box-shadow:none !important;padding:2px 4px !important;min-width:120px;flex:1;font-size:13px;background:transparent !important}
				.wp2fa-msa-dropdown{display:none;position:absolute;top:100%;left:0;right:0;z-index:1000;background:#fff;border:1px solid #8c8f94;border-top:none;border-radius:0 0 4px 4px;max-height:200px;overflow-y:auto;box-shadow:0 2px 4px rgba(0,0,0,.1)}
				.wp2fa-msa-option{padding:8px 12px;cursor:pointer;font-size:13px}
				.wp2fa-msa-option:hover,.wp2fa-msa-option.wp2fa-msa-active{background:#2271b1;color:#fff}
				.wp2fa-msa-no-results{padding:8px 12px;color:#787c82;font-size:13px;font-style:italic}
			</style>
			<script>
			(function(){
				'use strict';
				if(window.wp2faMSAInit)return;
				window.wp2faMSAInit=true;

				/* Mapping of opposite selector wrapper IDs. */
				var oppositePairs={
					'enforced-roles-item':'excluded-roles-item',
					'excluded-roles-item':'enforced-roles-item',
					'enforced-users-item':'excluded-users-item',
					'excluded-users-item':'enforced-users-item'
				};

				/**
				 * Return the IDs currently selected in the opposite selector,
				 * or an empty array if there is no opposite.
				 */
				function getOppositeIds(wrap){
					var wrapId=wrap.getAttribute('id')||'';
					var oppositeId=oppositePairs[wrapId];
					if(!oppositeId)return[];
					var opp=document.getElementById(oppositeId);
					if(!opp)return[];
					var h=opp.querySelector('.wp2fa-msa-value');
					if(!h||!h.value.trim())return[];
					return h.value.trim().split(',').map(function(s){return s.trim()}).filter(Boolean);
				}

				/**
				 * Remove a single ID from the opposite selector
				 * (both the hidden value and the visible tag).
				 */
				function removeFromOpposite(wrap,id){
					var wrapId=wrap.getAttribute('id')||'';
					var oppositeId=oppositePairs[wrapId];
					if(!oppositeId)return;
					var opp=document.getElementById(oppositeId);
					if(!opp)return;
					var h=opp.querySelector('.wp2fa-msa-value');
					if(!h)return;
					var ids=h.value.trim()?h.value.trim().split(',').map(function(s){return s.trim()}).filter(Boolean):[];
					if(ids.indexOf(id)===-1)return;
					ids=ids.filter(function(i){return i!==id});
					h.value=ids.join(',');
					var tag=opp.querySelector('.wp2fa-msa-tag[data-id="'+id+'"]');
					if(tag)tag.parentNode.removeChild(tag);
				}

				function initMSA(wrap,cfg){
					var tagsInput=wrap.querySelector('.wp2fa-msa-tags-input');
					var input=wrap.querySelector('.wp2fa-msa-input');
					var dropdown=wrap.querySelector('.wp2fa-msa-dropdown');
					var hiddenInput=wrap.querySelector('.wp2fa-msa-value');
					var searchType=wrap.getAttribute('data-search-type')||'';
					var minChars=parseInt(wrap.getAttribute('data-min-chars')||'2',10);
					var debounceTimer=null;
					var currentXhr=null;
					var activeIndex=-1;

					function getIds(){
						var v=hiddenInput.value.trim();
						if(!v)return[];
						return v.split(',').map(function(s){return s.trim()}).filter(Boolean);
					}

					function setIds(ids){
						hiddenInput.value=ids.join(',');
					}

					function addTag(id,text){
						var ids=getIds();
						if(ids.indexOf(id)!==-1)return;

						/* Remove from the opposite selector first. */
						removeFromOpposite(wrap,id);

						ids.push(id);
						setIds(ids);

						var tag=document.createElement('span');
						tag.className='wp2fa-msa-tag';
						tag.setAttribute('data-id',id);

						var label=document.createTextNode(text+' ');
						tag.appendChild(label);

						var btn=document.createElement('button');
						btn.type='button';
						btn.className='wp2fa-msa-remove';
						btn.setAttribute('aria-label','Remove');
						btn.innerHTML='&times;';
						btn.addEventListener('click',function(e){
							e.stopPropagation();
							removeTag(id);
						});
						tag.appendChild(btn);

						tagsInput.insertBefore(tag,input);
					}

					function removeTag(id){
						var ids=getIds().filter(function(i){return i!==id});
						setIds(ids);
						var tag=wrap.querySelector('.wp2fa-msa-tag[data-id="'+id+'"]');
						if(tag)tag.parentNode.removeChild(tag);
					}

					function hideDropdown(){
						dropdown.style.display='none';
						dropdown.innerHTML='';
						activeIndex=-1;
					}

					function showDropdown(results){
						dropdown.innerHTML='';
						activeIndex=-1;
						var ids=getIds();
						var oppIds=getOppositeIds(wrap);
						var filtered=results.filter(function(r){return ids.indexOf(r.id)===-1&&oppIds.indexOf(r.id)===-1});

						if(!filtered.length){
							var noRes=document.createElement('div');
							noRes.className='wp2fa-msa-no-results';
							noRes.textContent='No results found';
							dropdown.appendChild(noRes);
							dropdown.style.display='block';
							return;
						}

						filtered.forEach(function(item){
							var opt=document.createElement('div');
							opt.className='wp2fa-msa-option';
							opt.textContent=item.text;
							opt.setAttribute('data-id',item.id);
							opt.addEventListener('mousedown',function(e){
								e.preventDefault();
								addTag(item.id,item.text);
								input.value='';
								hideDropdown();
							});
							dropdown.appendChild(opt);
						});
						dropdown.style.display='block';
					}

					function setActive(idx){
						var opts=dropdown.querySelectorAll('.wp2fa-msa-option');
						for(var i=0;i<opts.length;i++){opts[i].classList.remove('wp2fa-msa-active')}
						if(idx>=0&&idx<opts.length){
							opts[idx].classList.add('wp2fa-msa-active');
							opts[idx].scrollIntoView({block:'nearest'});
						}
						activeIndex=idx;
					}

					function doSearch(term){
						if(currentXhr){currentXhr.abort();currentXhr=null}

						var xhr=new XMLHttpRequest();
						currentXhr=xhr;

						var url=cfg.ajaxUrl
							+'?action='+encodeURIComponent(cfg.searchAction)
							+'&nonce='+encodeURIComponent(cfg.nonce)
							+'&type='+encodeURIComponent(searchType)
							+'&term='+encodeURIComponent(term);

						xhr.open('GET',url,true);
						xhr.onreadystatechange=function(){
							if(xhr.readyState!==4)return;
							currentXhr=null;
							if(xhr.status>=200&&xhr.status<300){
								try{
									var resp=JSON.parse(xhr.responseText);
									if(resp.success&&Array.isArray(resp.data)){
										showDropdown(resp.data);
									}
								}catch(e){hideDropdown()}
							}
						};
						xhr.send();
					}

					input.addEventListener('input',function(){
						var term=input.value.trim();
						if(debounceTimer)clearTimeout(debounceTimer);
						if(term.length<minChars){hideDropdown();return}
						debounceTimer=setTimeout(function(){doSearch(term)},250);
					});

					input.addEventListener('keydown',function(e){
						var opts=dropdown.querySelectorAll('.wp2fa-msa-option');
						if(e.key==='ArrowDown'){
							e.preventDefault();
							if(activeIndex<opts.length-1)setActive(activeIndex+1);
						}else if(e.key==='ArrowUp'){
							e.preventDefault();
							if(activeIndex>0)setActive(activeIndex-1);
						}else if(e.key==='Enter'){
							e.preventDefault();
							if(activeIndex>=0&&opts[activeIndex]){
								var id=opts[activeIndex].getAttribute('data-id');
								var text=opts[activeIndex].textContent;
								addTag(id,text);
								input.value='';
								hideDropdown();
							}
						}else if(e.key==='Escape'){
							hideDropdown();
						}else if(e.key==='Backspace'&&!input.value){
							var tags=wrap.querySelectorAll('.wp2fa-msa-tag');
							if(tags.length){
								var last=tags[tags.length-1];
								removeTag(last.getAttribute('data-id'));
							}
						}
					});

					input.addEventListener('blur',function(){
						setTimeout(hideDropdown,200);
					});

					tagsInput.addEventListener('click',function(e){
						if(e.target===input)return;
						input.focus();
					});

					/* Attach remove handlers for server-rendered tags. */
					var existingBtns=wrap.querySelectorAll('.wp2fa-msa-remove');
					for(var i=0;i<existingBtns.length;i++){
						(function(btn){
							btn.addEventListener('click',function(e){
								e.stopPropagation();
								var tag=btn.parentNode;
								if(tag&&tag.classList.contains('wp2fa-msa-tag')){
									removeTag(tag.getAttribute('data-id'));
								}
							});
						})(existingBtns[i]);
					}
				}

				function boot(){
					var cfg=window.wp2faSavePoliciesNew;
					if(!cfg||!cfg.searchAction)return;
					var wraps=document.querySelectorAll('.wp2fa-msa-wrap');
					for(var i=0;i<wraps.length;i++){initMSA(wraps[i],cfg)}
				}

				if(document.readyState==='loading'){
					document.addEventListener('DOMContentLoaded',boot);
				}else{
					boot();
				}
			})();
			</script>
			<?php
		}

		/**
		 * Tab Title
		 *
		 * @since 4.0.0
		 */
		private static function tab_title() {
			?>
				<h2>
					<?php

					echo \esc_html( self::$settings['title'] );
					?>
				</h2>
			<?php
		}

		/**
		 * Group Title Builder
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		private static function group_title() {
			?>
			<div <?php echo self::$item_id_attr; ?> class="group-title <?php echo self::$custom_class; ?>"><?php echo \esc_html( self::$current_value ); ?></div>
			<?php
		}

		/**
		 * List Builder
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		private static function list() {
			echo '<ul ' . self::$item_id_wrap . ' class="wp-2fa-list-options' . self::$custom_class . '">';

			foreach ( self::$current_value as $value ) {
				?>
				<li tabindex="0">
					<span class="option-value" id="<?php echo \esc_attr( $value['id'] ); ?>"><?php echo \esc_html( $value['title'] ); ?></span>
					<?php if ( ! empty( self::$settings['values'][ $value['id'] ]['badge'] ) ) : ?>
						<span class="wp-2fa-badge"><?php echo \esc_html( self::$settings['values'][ $value['id'] ]['badge'] ); ?></span>
					<?php endif; ?>
				</li>
				<?php
			}

			echo '</ul>';
		}

		/**
		 * Number
		 *
		 * @since 5.0.0
		 */
		private static function number() {

			$min = ! empty( self::$min ) ? self::$min : -1000;
			$max = ! empty( self::$max ) ? self::$max : 1000000;

			?>
			<div class="number-input-wrap">
				<input style="width:100px" min="<?php echo $min; ?>" max="<?php echo $max; ?>" <?php echo self::$item_id_attr; ?> <?php echo self::$name_attr; ?> type="number" value="<?php echo \esc_attr( self::$current_value ); ?>" <?php echo self::$placeholder_attr; ?>>
				<?php
				if ( self::$settings['label'] ?? false ) {
					echo '<span class="number-label">' . \esc_html( self::$settings['label'] ) . '</span>';
				}
				?>
			</div>
			<?php
		}

		/**
		 * Select dropdown.
		 *
		 * Expected settings keys:
		 *   'options' (array) - Associative array of value => label.
		 *
		 * @since 5.0.0
		 */
		private static function select() {
			?>
			<select <?php echo self::$item_id_attr; ?> <?php echo self::$name_attr; ?> class="<?php echo self::$custom_class; ?>">
				<?php
				if ( ! empty( self::$settings['options'] ) && \is_array( self::$settings['options'] ) ) {
					foreach ( self::$settings['options'] as $option_key => $option ) {
						$selected = \selected( self::$current_value, $option_key, false );
						?>
						<option value="<?php echo \esc_attr( $option_key ); ?>" <?php echo $selected; ?>><?php echo \esc_html( $option ); ?></option>
						<?php
					}
				}
				?>
			</select>
			<?php
		}

		/**
		 * Renders a breadcrumb navigation bar.
		 *
		 * @param string $parent_label     - The escaped parent page label.
		 * @param string $parent_back_class - The CSS class used for the back navigation.
		 * @param string $current_label    - The escaped current page label.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function breadcrumb() {
			?>
			<nav class="breadcrumbs">
				<a href="#" class="back-button back-click" aria-label="<?php echo \esc_attr__( 'Go back', 'wp-2fa' ); ?>"><svg xmlns="http://www.w3.org/2000/svg" width="7" height="12" viewBox="0 0 7 12" fill="none" aria-hidden="true"><path d="M5.9375 0.9375L0.9375 5.9375L5.9375 10.9375" stroke="white" stroke-width="1.875" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
				<ul>
					<li><a href="#" class="back-click <?php echo \esc_attr( self::$custom_class ); ?>"><?php echo \esc_html( self::$settings['parent'] ); ?></a></li>
					<li><?php echo \esc_html( self::$current_value ); ?></li>
				</ul>
			</nav>
			<?php
		}

		/**
		 * Text input with a URL prefix displayed before it.
		 *
		 * Expected settings keys:
		 *   'url_prefix' (string) - The URL prefix to display (e.g. site URL).
		 *
		 * @since 5.0.0
		 */
		private static function url_prefixed_text() {
			$prefix = ! empty( self::$settings['url_prefix'] ) ? self::$settings['url_prefix'] : '';
			?>
			<div class="url-prefix-wrap">
				<span class="url-prefix-text"><?php echo \esc_html( $prefix ); ?></span>
				<input <?php echo self::$item_id_attr; ?> <?php echo self::$name_attr; ?> type="text" value="<?php echo \esc_attr( self::$current_value ); ?>" class="regular-text<?php echo self::$custom_class; ?>" <?php echo self::$placeholder_attr; ?>>
			</div>
			<?php
		}

		/**
		 * Prepare Data
		 *
		 * @param array $settings - Array with settings.
		 * @param mixed $data - The data to show.
		 *
		 * @since 4.0.0
		 */
		private static function prepare_data( $settings, $data ) {

			// Default Settings.
			$settings = \wp_parse_args(
				$settings,
				array(
					'id'    => '',
					'class' => '',
				)
			);

			self::$settings = $settings;

			extract( $settings ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

			self::$option_type = ! empty( $type ) ? $type : false;
			self::$required    = ! empty( $required ) ? $required : false;

			self::$min = ! empty( $min ) ? $min : null;
			self::$max = ! empty( $max ) ? $max : null;

			self::$step = ! empty( $step ) ? $step : null;

			self::$data_attrs = ! empty( $data_attrs ) ? $data_attrs : array();

			if ( 'text' === self::$option_type || 'secret' === self::$option_type ) {
				self::$edit_type        = ! empty( $validate ) ? $validate : false;
				self::$validate_pattern = ! empty( $pattern ) ? $pattern : false;
				self::$max_chars        = ! empty( $max_chars ) ? $max_chars : false;
				self::$title_attr       = ! empty( $title_attr ) ? $title_attr : false;
			}

			self::$not_bool = ! empty( $not_bool ) ? true : null;

			// ID.
			self::$item_id .= ! empty( $prefix ) ? $prefix . '-' : '';
			self::$item_id .= ! empty( $id ) ? $id : '';

			self::$item_id = \esc_attr( self::$item_id );

			if ( ! empty( self::$item_id ) && ' ' !== self::$item_id ) {

				self::$item_id = ( 'arrayText' === $type ) ? self::$item_id . '-' . $key : self::$item_id;

				self::$item_id_attr = 'id="' . self::$item_id . '"';
				self::$item_id_wrap = 'id="' . self::$item_id . '-item"';
			}

			// Class.
			self::$custom_class = ! empty( $class ) ? ' ' . $class : '';

			if ( isset( $option_name ) ) {
				$option_name = \esc_attr( $option_name );
			}

			// Name.
			self::$name_attr   = 'name="' . ( ( $option_name ) ?? '' ) . '"';
			self::$option_name = ! empty( $option_name ) ? $option_name : null;

			// Placeholder.
			self::$placeholder_attr = ! empty( $placeholder ) ? 'placeholder="' . $placeholder . '"' : '';

			// Get the option stored data.
			if ( ! \is_null( $data ) ) {
				self::$current_value = $data;
			} elseif ( isset( $default ) ) {
				self::$current_value = $default;
			}

			if ( empty( self::$current_value ) && ( 'list' === self::$option_type || 'tabbed-navigation' === self::$option_type ) ) {
				self::$current_value = array_map(
					function ( $value ) {
						$item = array(
							'title' => ( $value['title'] ) ? (string) \esc_html( $value['title'] ) : 'Not Set',
							'id'    => ( $value['id'] ) ? (string) ( $value['id'] ) : '',
						);
						if ( ! empty( $value['locked'] ) ) {
							$item['locked'] = true;
						}
						if ( ! empty( $value['premium_context'] ) ) {
							$item['premium_context'] = (string) $value['premium_context'];
						}
						return $item;
					},
					$settings['values']
				);
			}
		}

		/**
		 * Whether the sortable-list CSS/JS assets have been output.
		 *
		 * @var bool
		 *
		 * @since 3.1.2
		 */
		private static $sortable_list_rendered = false;

		/**
		 * Sortable List – renders an ordered list of items that can be reordered via drag-and-drop.
		 *
		 * Expected settings keys:
		 *   'items'       (array)  - Ordered array of arrays with 'id', 'title', and optionally 'enabled' (bool), 'description' (string).
		 *   'name_prefix' (string) - The hidden input name prefix for the order, e.g. 'wp_2fa_policy[methods_order]'.
		 *                            Each item will produce: <input name="{name_prefix}[]" value="{id}">.
		 *
		 * @since 3.1.2
		 */
		private static function sortable_list() {
			$items       = ! empty( self::$settings['items'] ) ? self::$settings['items'] : array();
			$name_prefix = ! empty( self::$settings['name_prefix'] ) ? self::$settings['name_prefix'] : '';

			if ( ! self::$sortable_list_rendered ) {
				self::sortable_list_assets();
				self::$sortable_list_rendered = true;
			}
			?>
			<div <?php echo self::$item_id_wrap; ?> class="wp2fa-sortable-list-wrap <?php echo self::$custom_class; ?>">
				<ul class="wp2fa-sortable-list" data-name-prefix="<?php echo \esc_attr( $name_prefix ); ?>">
					<?php
					foreach ( $items as $item ) {
						$has_extra       = ! empty( $item['extra_settings'] );
						$has_integration = ! empty( $item['integration_settings'] );
						$is_configured   = ! empty( $item['is_configured'] );
						$needs_setup     = $has_integration && ! $is_configured;
						$li_class        = 'wp2fa-sortable-item';
						?>
						<li class="<?php echo \esc_attr( $li_class ); ?>" data-id="<?php echo \esc_attr( $item['id'] ); ?>" <?php echo $needs_setup ? 'data-needs-setup="1"' : ''; ?>>
							<input type="hidden" name="<?php echo \esc_attr( $name_prefix ); ?>[]" value="<?php echo \esc_attr( ( $item['order_id'] ) ? $item['order_id'] : $item['id'] ); ?>">
							<span class="wp2fa-sortable-handle" aria-label="<?php \esc_attr_e( 'Drag to reorder', 'wp-2fa' ); ?>">&#9776;</span>
							<?php if ( ! empty( $item['checkbox_name'] ) ) { ?>
								<label class="wp2fa-sortable-checkbox toggle-switch">
									<input type="checkbox"
										name="<?php echo \esc_attr( $item['checkbox_name'] ); ?>"
										value="<?php echo \esc_attr( ! empty( $item['checkbox_value'] ) ? $item['checkbox_value'] : $item['id'] ); ?>"
										<?php \checked( ! empty( $item['checked'] ) ); ?>
										<?php echo $has_extra ? 'data-has-extra="1"' : ''; ?>>
									<span class="toggle-slider"></span>
										<span class="wp2fa-sortable-title"> <?php echo \esc_html( $item['title'] ); ?></span>
										<?php if ( ! empty( $item['title_hint'] ) ) { ?>
											<span class="wp2fa-sortable-title-hint"><?php echo $item['title_hint']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped HTML with links. ?></span>
										<?php } ?>
								</label>
							<?php } else { ?>
							<span class="wp2fa-sortable-title"><?php echo \esc_html( $item['title'] ); ?></span>
							<?php } ?>
							<?php if ( ! empty( $item['setup_url'] ) ) { ?>
								<a href="<?php echo \esc_url( $item['setup_url'] ); ?>" class="wp2fa-sortable-setup-link" style="display:none;"><?php \esc_html_e( 'Setup method', 'wp-2fa' ); ?></a>
							<?php } ?>
							<?php if ( ! empty( $item['description'] ) ) { ?>
								<span class="wp2fa-sortable-desc"><?php echo $item['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped HTML with links. ?></span>
							<?php } ?>
							<?php if ( $has_extra ) { ?>
								<div class="wp2fa-method-settings-toggle-wrap" style="<?php echo empty( $item['checked'] ) ? 'display:none;' : ''; ?>">
									<button type="button" class="wp2fa-collapsible-toggle" aria-expanded="false" data-label-show="<?php \esc_attr_e( 'Show method settings', 'wp-2fa' ); ?>" data-label-hide="<?php \esc_attr_e( 'Hide method settings', 'wp-2fa' ); ?>">
										<span class="wp2fa-collapsible-chevron" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="7" viewBox="0 0 12 7" fill="none"><path d="M1 1L6 6L11 1" stroke="currentColor" stroke-width="1.875" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
										<span class="wp2fa-collapsible-text"><?php \esc_html_e( 'Show method settings', 'wp-2fa' ); ?></span>
									</button>
								</div>
								<div class="wp2fa-sortable-extra-settings wp2fa-collapsible-content" style="display:none;">
									<?php echo $item['extra_settings']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-built HTML from Settings_Builder. ?>
								</div>
							<?php } ?>
							<?php if ( $has_integration ) { ?>
								<?php if ( ! $is_configured ) { ?>
									<div class="wp2fa-integration-setup-notice" style="display:none;">
										<p><?php \esc_html_e( 'Complete configuration to enable this method', 'wp-2fa' ); ?></p>
									</div>
								<?php } ?>
								<div class="wp2fa-integration-settings-toggle-wrap" style="<?php echo ( empty( $item['checked'] ) && ! $needs_setup ) ? 'display:none;' : ''; ?>">
									<button type="button" class="wp2fa-collapsible-toggle" aria-expanded="false" data-label-show="<?php \esc_attr_e( 'Show integration settings', 'wp-2fa' ); ?>" data-label-hide="<?php \esc_attr_e( 'Hide integration settings', 'wp-2fa' ); ?>">
										<span class="wp2fa-collapsible-chevron" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="7" viewBox="0 0 12 7" fill="none"><path d="M1 1L6 6L11 1" stroke="currentColor" stroke-width="1.875" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
										<span class="wp2fa-collapsible-text"><?php \esc_html_e( 'Show integration settings', 'wp-2fa' ); ?></span>
									</button>
									<span class="wp2fa-integration-status-dot <?php echo $is_configured ? 'wp2fa-status-configured' : 'wp2fa-status-not-configured'; ?>" title="<?php echo $is_configured ? \esc_attr__( 'Configured', 'wp-2fa' ) : \esc_attr__( 'Not configured', 'wp-2fa' ); ?>"></span>
								</div>
								<div class="wp2fa-sortable-integration-settings wp2fa-collapsible-content" style="display:none;">
									<?php if ( ! $is_configured ) { ?>
										<div class="wp2fa-integration-info-notice">
											<p><?php \esc_html_e( 'Configure this method below and hit save to enable it.', 'wp-2fa' ); ?></p>
										</div>
									<?php } ?>
									<?php echo $item['integration_settings']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-built HTML from Settings_Builder. ?>
								</div>
							<?php } ?>
						</li>
					<?php } ?>
				</ul>
			</div>
			<?php
		}

		/**
		 * Output inline CSS + JS for the sortable-list component.
		 *
		 * Uses vanilla JavaScript – no third-party libraries.
		 *
		 * @since 3.1.2
		 */
		private static function sortable_list_assets() {
			?>
			<style>
			/* .wp2fa-sortable-list-wrap { max-width: 600px; } */
			.wp2fa-sortable-list { list-style: none; margin: 0; padding: 0; }
			.wp2fa-sortable-item {
				display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
				background: #fff; border: 1px solid #e2e2e4; border-radius: 5px;
				padding: 12px 16px; margin-bottom: 8px; cursor: default;
				transition: box-shadow .15s, border-color .15s;
				user-select: none;
			}
			.wp2fa-sortable-item.wp2fa-sortable-dragging {
				opacity: .55; box-shadow: 0 4px 12px rgba(0,0,0,.15); border-color: #2271b1;
			}
			.wp2fa-sortable-item.wp2fa-sortable-over {
				border-top: 2px solid #2271b1; margin-top: -2px;
			}
			.wp2fa-sortable-handle {
				cursor: grab; font-size: 18px; color: #787c82; line-height: 1; flex-shrink: 0;
			}
			.wp2fa-sortable-handle:active { cursor: grabbing; }
			.wp2fa-sortable-checkbox { flex-shrink: 0; display: inline-flex; align-items: center; gap: 12px; }
			.wp2fa-sortable-checkbox input[type="checkbox"] { position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none; }
			.wp2fa-sortable-title { font-weight: 500; font-size: 14px; flex: 1; margin-left: 10px; }
			.wp2fa-sortable-desc { font-size: 12px; color: #646970; }
			.wp2fa-sortable-extra-settings {
				width: 100%; padding: 10px 0 4px 91px; border-top: 1px solid #f0f0f1;
				margin-top: 4px; display: flex; flex-direction: column; gap: 8px;
			}
			.wp2fa-sortable-extra-settings .number-input-wrap,
			.wp2fa-sortable-extra-settings .settings-control { margin: 0; }
			.wp2fa-sortable-extra-settings .number-input-wrap input[type="number"] { width: 70px; }
			.wp2fa-sortable-extra-settings .simple-text { font-size: 13px; margin: 0 0 4px; }
			.wp2fa-extra-inline { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
			.wp2fa-extra-inline .settings-control { display: flex; align-items: center; gap: 6px; }
			.wp2fa-extra-inline .settings-control .radio-option { margin: 0; }
			.wp2fa-sortable-item-disabled {
				background: #f9f9f9;
			}
			.wp2fa-sortable-item-disabled .wp2fa-sortable-handle,
			.wp2fa-sortable-item-disabled .wp2fa-sortable-checkbox,
			.wp2fa-sortable-item-disabled .wp2fa-sortable-title { opacity: 0.5; pointer-events: none; }
			.wp2fa-sortable-setup-link {
				display: none;
				flex-shrink: 0; margin-left: auto; font-size: 13px; color: #2271b1; text-decoration: none;
				font-weight: 500; white-space: nowrap;
			}
			.wp2fa-sortable-setup-link:hover { text-decoration: underline; color: #135e96; }

			/* Collapsible toggle (Show/Hide method/integration settings) */
			.wp2fa-method-settings-toggle-wrap,
			.wp2fa-integration-settings-toggle-wrap {
				width: 100%; padding: 8px 0 0 91px; display: flex; align-items: center; gap: 8px;
			}
			.wp2fa-collapsible-toggle {
				background: none; border: none; cursor: pointer; padding: 0;
				display: inline-flex; align-items: center; gap: 6px;
				font-size: 12px; font-weight: 600; text-transform: uppercase;
				color: #2271b1; letter-spacing: 0.3px;
			}
			.wp2fa-collapsible-toggle:hover { color: #135e96; }
			.wp2fa-collapsible-chevron {
				display: inline-flex; align-items: center; transition: transform 0.2s ease;
			}
			.wp2fa-collapsible-chevron svg { width: 12px; height: 7px; }
			.wp2fa-collapsible-toggle[aria-expanded="true"] .wp2fa-collapsible-chevron {
				transform: rotate(180deg);
			}
			.wp2fa-integration-status-dot {
				display: inline-block; width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
			}
			.wp2fa-status-configured { background-color: #00a32a; }
			.wp2fa-status-not-configured { background-color: #a7aaad; }
			.wp2fa-sortable-integration-settings {
				width: 100%; padding: 10px 0 4px 91px; border-top: 1px solid #f0f0f1;
				margin-top: 4px; display: flex; flex-direction: column; gap: 8px;
			}
			.wp2fa-sortable-integration-settings .number-input-wrap,
			.wp2fa-sortable-integration-settings .settings-control { margin: 0; }
			.wp2fa-integration-setup-notice {
				width: 100%; padding: 8px 0 0 91px;
			}
			.wp2fa-integration-setup-notice p {
				background: #fcf0f1; border: 1px solid #f0c6c8; border-radius: 4px;
				padding: 12px 16px; color: #8b1a1f; font-size: 13px; line-height: 1.5; margin: 0;
			}
			.wp2fa-integration-info-notice {
				background: #fef3cd; border: 1px solid #ffc107; border-radius: 4px;
				padding: 14px 18px; color: #856404; font-size: 13px; line-height: 1.6;
			}
			.wp2fa-integration-info-notice p { margin: 0; }
			.wp2fa-sortable-desc {
				width: 100%; padding: 0 0 0 91px; font-size: 13px; color: #646970; line-height: 1.5;
			}
			.wp2fa-sortable-desc a { color: #2271b1; text-decoration: underline; }
			.wp2fa-sortable-desc a:hover { color: #135e96; }
			</style>
			<script>
			document.addEventListener('DOMContentLoaded', function() {
				/* Prevent enabling unconfigured integration methods – keep unchecked and show/hide integration settings */
				document.querySelectorAll('.wp2fa-sortable-item[data-needs-setup="1"] input[type="checkbox"]').forEach(function(cb) {
					cb.addEventListener('change', function(e) {
						var li = this.closest('.wp2fa-sortable-item');
						/* Only block if still needs setup (attribute may be removed after verification) */
						if (this.checked && li.hasAttribute('data-needs-setup')) {
							/* Block the check */
							this.checked = false;

							var setupNotice = li.querySelector('.wp2fa-integration-setup-notice');
							var integrationWrap = li.querySelector('.wp2fa-integration-settings-toggle-wrap');
							var integrationContent = li.querySelector('.wp2fa-sortable-integration-settings');
							var integrationToggle = integrationWrap ? integrationWrap.querySelector('.wp2fa-collapsible-toggle') : null;

							/* If already expanded, collapse and return to original state */
							if (integrationContent && integrationContent.style.display !== 'none') {
								if (setupNotice) { setupNotice.style.display = 'none'; }
								if (integrationWrap) { integrationWrap.style.display = 'none'; }
								integrationContent.style.display = 'none';
								if (integrationToggle) {
									integrationToggle.setAttribute('aria-expanded', 'false');
									integrationToggle.querySelector('.wp2fa-collapsible-text').textContent = integrationToggle.dataset.labelShow;
								}
							} else {
								/* Show the setup notice */
								if (setupNotice) { setupNotice.style.display = ''; }
								/* Show the integration settings toggle and expand it */
								if (integrationWrap) { integrationWrap.style.display = ''; }
								if (integrationContent && integrationToggle) {
									integrationContent.style.display = '';
									integrationToggle.setAttribute('aria-expanded', 'true');
									integrationToggle.querySelector('.wp2fa-collapsible-text').textContent = integrationToggle.dataset.labelHide;
								}
							}
						}
					});
				});

				/* Toggle extra/integration settings visibility on checkbox change */
				document.querySelectorAll('.wp2fa-sortable-item input[type="checkbox"][data-has-extra]').forEach(function(cb) {
					cb.addEventListener('change', function() {
						var li = this.closest('.wp2fa-sortable-item');
						var toggleWrap = li.querySelector('.wp2fa-method-settings-toggle-wrap');
						if (toggleWrap) { toggleWrap.style.display = this.checked ? '' : 'none'; }
						var integrationWrap = li.querySelector('.wp2fa-integration-settings-toggle-wrap');
						if (integrationWrap) { integrationWrap.style.display = this.checked ? '' : 'none'; }
						/* Hide the collapsible content when method is unchecked */
						if (!this.checked) {
							var setupNotice = li.querySelector('.wp2fa-integration-setup-notice');
							if (setupNotice) { setupNotice.style.display = 'none'; }
							li.querySelectorAll('.wp2fa-collapsible-content').forEach(function(content) {
								content.style.display = 'none';
							});
							li.querySelectorAll('.wp2fa-collapsible-toggle').forEach(function(toggle) {
								toggle.setAttribute('aria-expanded', 'false');
								toggle.querySelector('.wp2fa-collapsible-text').textContent = toggle.dataset.labelShow;
							});
						}
					});
				});

				/* Also handle integration toggle visibility on checkbox change (methods without extra_settings) */
				document.querySelectorAll('.wp2fa-sortable-item:not([data-needs-setup]) input[type="checkbox"]').forEach(function(cb) {
					if (cb.dataset.hasExtra) return; /* Already handled above */
					cb.addEventListener('change', function() {
						var li = this.closest('.wp2fa-sortable-item');
						var integrationWrap = li.querySelector('.wp2fa-integration-settings-toggle-wrap');
						if (integrationWrap) { integrationWrap.style.display = this.checked ? '' : 'none'; }
						if (!this.checked) {
							var setupNotice = li.querySelector('.wp2fa-integration-setup-notice');
							if (setupNotice) { setupNotice.style.display = 'none'; }
							li.querySelectorAll('.wp2fa-collapsible-content').forEach(function(content) {
								content.style.display = 'none';
							});
							li.querySelectorAll('.wp2fa-collapsible-toggle').forEach(function(toggle) {
								toggle.setAttribute('aria-expanded', 'false');
								toggle.querySelector('.wp2fa-collapsible-text').textContent = toggle.dataset.labelShow;
							});
						}
					});
				});

				/* Collapsible toggle click handler */
				document.querySelectorAll('.wp2fa-collapsible-toggle').forEach(function(btn) {
					btn.addEventListener('click', function(e) {
						e.preventDefault();
						var li = this.closest('.wp2fa-sortable-item');
						var isIntegration = this.closest('.wp2fa-integration-settings-toggle-wrap') !== null;
						var content = isIntegration
							? li.querySelector('.wp2fa-sortable-integration-settings')
							: li.querySelector('.wp2fa-sortable-extra-settings');
						if (!content) return;
						var isExpanded = this.getAttribute('aria-expanded') === 'true';
						if (isExpanded) {
							content.style.display = 'none';
							this.setAttribute('aria-expanded', 'false');
							this.querySelector('.wp2fa-collapsible-text').textContent = this.dataset.labelShow;
							if (isIntegration) {
								var setupNotice = li.querySelector('.wp2fa-integration-setup-notice');
								if (setupNotice) { setupNotice.style.display = 'none'; }
							}
						} else {
							content.style.display = '';
							this.setAttribute('aria-expanded', 'true');
							this.querySelector('.wp2fa-collapsible-text').textContent = this.dataset.labelHide;
							if (isIntegration) {
								var setupNotice = li.querySelector('.wp2fa-integration-setup-notice');
								if (setupNotice) { setupNotice.style.display = ''; }
							}
						}
					});
				});

				document.querySelectorAll('.wp2fa-sortable-list').forEach(function(list) {
					var dragItem = null;

					list.querySelectorAll('.wp2fa-sortable-item').forEach(function(item) {
						var handle = item.querySelector('.wp2fa-sortable-handle');
						if (!handle) return;

						handle.addEventListener('mousedown', function() {
							item.setAttribute('draggable', 'true');
						});
						handle.addEventListener('mouseup', function() {
							item.removeAttribute('draggable');
						});

						item.addEventListener('dragstart', function(e) {
							dragItem = item;
							item.classList.add('wp2fa-sortable-dragging');
							e.dataTransfer.effectAllowed = 'move';
							e.dataTransfer.setData('text/plain', '');
						});

						item.addEventListener('dragend', function() {
							item.classList.remove('wp2fa-sortable-dragging');
							item.removeAttribute('draggable');
							dragItem = null;
							list.querySelectorAll('.wp2fa-sortable-item').forEach(function(el) {
								el.classList.remove('wp2fa-sortable-over');
							});
							/* Rebuild hidden inputs order */
							var prefix = list.getAttribute('data-name-prefix');
							list.querySelectorAll('.wp2fa-sortable-item').forEach(function(li) {
								var inp = li.querySelector('input[type="hidden"]');
								if (inp) inp.setAttribute('name', prefix + '[]');
							});
						});

						item.addEventListener('dragover', function(e) {
							e.preventDefault();
							e.dataTransfer.dropEffect = 'move';
							if (item !== dragItem) {
								item.classList.add('wp2fa-sortable-over');
							}
						});

						item.addEventListener('dragleave', function() {
							item.classList.remove('wp2fa-sortable-over');
						});

						item.addEventListener('drop', function(e) {
							e.preventDefault();
							item.classList.remove('wp2fa-sortable-over');
							if (dragItem && dragItem !== item) {
								var allItems = Array.from(list.children);
								var dragIdx = allItems.indexOf(dragItem);
								var dropIdx = allItems.indexOf(item);
								if (dragIdx < dropIdx) {
									list.insertBefore(dragItem, item.nextSibling);
								} else {
									list.insertBefore(dragItem, item);
								}
							}
						});

						/* Touch support for mobile */
						var touchStartY = 0;
						var touchClone = null;
						var touchCurrentOver = null;

						handle.addEventListener('touchstart', function(e) {
							dragItem = item;
							touchStartY = e.touches[0].clientY;
							item.classList.add('wp2fa-sortable-dragging');
							e.preventDefault();
						}, { passive: false });

						handle.addEventListener('touchmove', function(e) {
							if (!dragItem) return;
							e.preventDefault();
							var touch = e.touches[0];
							var overEl = document.elementFromPoint(touch.clientX, touch.clientY);
							if (overEl) {
								var overItem = overEl.closest('.wp2fa-sortable-item');
								if (touchCurrentOver && touchCurrentOver !== overItem) {
									touchCurrentOver.classList.remove('wp2fa-sortable-over');
								}
								if (overItem && overItem !== dragItem && overItem.parentNode === list) {
									overItem.classList.add('wp2fa-sortable-over');
									touchCurrentOver = overItem;
								}
							}
						}, { passive: false });

						handle.addEventListener('touchend', function(e) {
							if (!dragItem) return;
							dragItem.classList.remove('wp2fa-sortable-dragging');
							if (touchCurrentOver) {
								touchCurrentOver.classList.remove('wp2fa-sortable-over');
								var allItems = Array.from(list.children);
								var dragIdx = allItems.indexOf(dragItem);
								var dropIdx = allItems.indexOf(touchCurrentOver);
								if (dragIdx < dropIdx) {
									list.insertBefore(dragItem, touchCurrentOver.nextSibling);
								} else {
									list.insertBefore(dragItem, touchCurrentOver);
								}
								/* Rebuild hidden inputs */
								var prefix = list.getAttribute('data-name-prefix');
								list.querySelectorAll('.wp2fa-sortable-item').forEach(function(li) {
									var inp = li.querySelector('input[type="hidden"]');
									if (inp) inp.setAttribute('name', prefix + '[]');
								});
							}
							dragItem = null;
							touchCurrentOver = null;
						});
					});
				});
			});
			</script>
			<?php
		}

		/**
		 * Creates an option and draws it
		 *
		 * @param array $value - The array with option data.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function build_option( array $value ) {
			$data = null;

			if ( empty( $value['id'] ) ) {
				$value['id'] = ' ';
			}

			self::create( $value, $data );
		}

		/**
		 * Renders the premium badge (lock icon + "PREMIUM" label).
		 *
		 * Use this inline next to feature labels that require a premium license.
		 *
		 * @return void
		 *
		 * @since 4.1.0
		 */
		public static function premium_badge() {
			echo self::get_premium_badge_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		/**
		 * Returns the premium badge HTML markup (lock icon + "PREMIUM" label).
		 *
		 * Useful for embedding in other element attributes like checkbox text.
		 *
		 * @return string
		 *
		 * @since 4.1.0
		 */
		public static function get_premium_badge_html( string $context = '' ): string {
			return self::get_plan_badge_html( \esc_html__( 'PREMIUM', 'wp-2fa' ), $context );
		}

		/**
		 * Renders the enterprise badge (lock icon + "ENTERPRISE" label).
		 *
		 * Use this inline next to feature labels that require an enterprise license.
		 *
		 * @return void
		 *
		 * @since 4.1.0
		 */
		public static function enterprise_badge() {
			echo self::get_enterprise_badge_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		/**
		 * Returns the enterprise badge HTML markup (lock icon + "ENTERPRISE" label).
		 *
		 * Useful for embedding in other element attributes like checkbox text.
		 *
		 * @return string
		 *
		 * @since 4.1.0
		 */
		public static function get_enterprise_badge_html( string $context = '' ): string {
			return self::get_plan_badge_html( \esc_html__( 'ENTERPRISE', 'wp-2fa' ), $context );
		}

		/**
		 * Returns a badge HTML markup for a license plan label.
		 *
		 * @param string $label Badge text label.
		 *
		 * @return string
		 *
		 * @since 4.1.0
		 */
		private static function get_plan_badge_html( string $label, string $context = '' ): string {
			$aria_label = sprintf(
				/* translators: %s: plan label. */
				esc_html__( 'Learn more about %s features', 'wp-2fa' ),
				wp_strip_all_tags( $label )
			);

			$context_attr = '' !== $context ? ' data-wp2fa-premium-context="' . esc_attr( $context ) . '"' : '';

			return '<span class="wp2fa-premium-badge" role="button" tabindex="0" data-wp2fa-premium-dialog-trigger="1"' . $context_attr . ' aria-label="' . esc_attr( $aria_label ) . '">'
				. '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">'
				. '<path d="M4.66699 6.41667V4.08333C4.66699 3.46449 4.91282 2.871 5.35041 2.43342C5.78799 1.99583 6.38149 1.75 7.00033 1.75C7.61916 1.75 8.21266 1.99583 8.65024 2.43342C9.08783 2.871 9.33366 3.46449 9.33366 4.08333V6.41667M2.91699 7.58333C2.91699 7.27391 3.03991 6.97717 3.2587 6.75838C3.47749 6.53958 3.77424 6.41667 4.08366 6.41667H9.91699C10.2264 6.41667 10.5232 6.53958 10.742 6.75838C10.9607 6.97717 11.0837 7.27391 11.0837 7.58333V11.0833C11.0837 11.3928 10.9607 11.6895 10.742 11.9083C10.5232 12.1271 10.2264 12.25 9.91699 12.25H4.08366C3.77424 12.25 3.47749 12.1271 3.2587 11.9083C3.03991 11.6895 2.91699 11.3928 2.91699 11.0833V7.58333ZM6.41699 9.33333C6.41699 9.48804 6.47845 9.63642 6.58785 9.74581C6.69724 9.85521 6.84562 9.91667 7.00033 9.91667C7.15504 9.91667 7.30341 9.85521 7.4128 9.74581C7.5222 9.63642 7.58366 9.48804 7.58366 9.33333C7.58366 9.17862 7.5222 9.03025 7.4128 8.92085C7.30341 8.81146 7.15504 8.75 7.00033 8.75C6.84562 8.75 6.69724 8.81146 6.58785 8.92085C6.47845 9.03025 6.41699 9.17862 6.41699 9.33333Z" stroke="#646970" stroke-width="1.3125" stroke-linecap="round" stroke-linejoin="round"/>'
				. '</svg> '
				. esc_html( $label )
				. '</span>';
		}

		/**
		 * Opens a premium-gated wrapper.
		 *
		 * When $is_premium is false the wrapper disables all contained inputs
		 * and applies premium styling. Call premium_gate_close() to end the block.
		 *
		 * @param bool $is_premium Whether the current install has a valid premium license.
		 *
		 * @return void
		 *
		 * @since 4.1.0
		 */
		public static function premium_gate_open( bool $is_premium ) {
			$class = $is_premium ? '' : ' wp2fa-premium-gate-locked';
			?>
			<div class="wp2fa-premium-gate<?php echo \esc_attr( $class ); ?>">
			<?php
		}

		/**
		 * Closes a premium-gated wrapper.
		 *
		 * @return void
		 *
		 * @since 4.1.0
		 */
		public static function premium_gate_close() {
			?>
			</div>
			<?php
		}
	}
}
