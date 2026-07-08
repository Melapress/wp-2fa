<?php
/**
 * WP 2FA Wizard Template: Method Selection
 *
 * Contains two template IDs:
 *   - wp2fa-wizard-method-item   — one radio item in the method list.
 *   - wp2fa-wizard-select-footer — footer buttons for the selection step.
 *
 * tmpl-wp2fa-wizard-method-item data:
 *   - id          {string}  Method identifier (e.g. 'totp').
 *   - name        {string}  Display name.
 *   - description {string}  Optional description text.
 *   - hint        {string}  Optional HTML hint shown via info icon toggle.
 *   - checked     {bool}    Whether this radio is pre-selected.
 *
 * tmpl-wp2fa-wizard-select-footer data:
 *   - goBackLabel    {string} "Go back" button text.
 *   - continueLabel  {string} "Continue" button text.
 *   - disabled       {bool}   Whether continue is disabled.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<script type="text/html" id="tmpl-wp2fa-wizard-method-item">
	<li class="wp2fa-wizard-method-item<# if ( data.unavailable ) { #> wp2fa-wizard-method-unavailable<# } #>" id="wp2fa-wizard-method-item-{{ data.id }}">
		<label class="wp2fa-wizard-method-label" data-method-id="{{ data.id }}" id="wp2fa-wizard-method-label-{{ data.id }}">
			<input type="radio"
				name="wp2fa_wizard_method"
				id="wp2fa-wizard-method-{{ data.id }}"
				value="{{ data.id }}"
				class="wp2fa-wizard-method-radio"
				data-method-id="{{ data.id }}"
				<# if ( data.checked ) { #>checked<# } #>
				<# if ( data.unavailable ) { #>disabled<# } #>
			/>
			<span class="wp2fa-wizard-method-info">
				<# if ( ! data.description ) { #>
					<span class="wp2fa-wizard-method-name">{{ data.name }}<# if ( data.hint ) { #><button type="button" class="wp2fa-wizard-hint-toggle" aria-label="<?php \esc_attr_e( 'Toggle help text', 'wp-2fa' ); ?>" data-hint-for="{{ data.id }}"><span class="wp2fa-wizard-hint-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><g opacity="0.5"><path d="M12 9H12.01M11 12H12V16H13M3 12C3 13.1819 3.23279 14.3522 3.68508 15.4442C4.13738 16.5361 4.80031 17.5282 5.63604 18.364C6.47177 19.1997 7.46392 19.8626 8.55585 20.3149C9.64778 20.7672 10.8181 21 12 21C13.1819 21 14.3522 20.7672 15.4442 20.3149C16.5361 19.8626 17.5282 19.1997 18.364 18.364C19.1997 17.5282 19.8626 16.5361 20.3149 15.4442C20.7672 14.3522 21 13.1819 21 12C21 9.61305 20.0518 7.32387 18.364 5.63604C16.6761 3.94821 14.3869 3 12 3C9.61305 3 7.32387 3.94821 5.63604 5.63604C3.94821 7.32387 3 9.61305 3 12Z" stroke="#3C434A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></g></svg></span></button><# } #></span>
				<# } #>
				<# if ( data.hint ) { #>
					<span class="wp2fa-wizard-method-hint" id="wp2fa-wizard-hint-{{ data.id }}" style="display:none;">{{{ data.hint }}}</span>
				<# } #>
				<# if ( data.description ) { #>
					<span class="wp2fa-wizard-method-description">{{{ data.description }}}</span>
				<# } #>
			</span>
		</label>
	</li>
</script>

<script type="text/html" id="tmpl-wp2fa-wizard-select-footer">
	<button class="wp2fa-wizard-btn wp2fa-wizard-btn-secondary wp-2fa-button-secondary" id="wp2fa-wizard-btn-goback" type="button">{{ data.goBackLabel }}</button>
	<button class="wp2fa-wizard-btn wp2fa-wizard-btn-primary wp-2fa-button-primary" id="wp2fa-wizard-btn-continue" type="button" <# if ( data.disabled ) { #>disabled<# } #>>{{ data.continueLabel }}</button>
</script>
