<?php

namespace WP2FA_Vendor;

/**
 * @package     Freemius
 * @copyright   Copyright (c) 2015, Freemius, Inc.
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
 * @since       1.2.1.8
 *
 * @var array $VARS
 */
if (!\defined('WP2FA_Vendor\\ABSPATH')) {
    exit;
}
?>
<div class="fs-secure-notice">
	<i class="dashicons dashicons-lock"></i>
	<span><?php 
if (!empty($VARS['message'])) {
    echo \esc_html($VARS['message']);
} else {
    /**
     * @var Freemius $fs
     */
    $fs = \WP2FA_Vendor\freemius($VARS['id']);
    echo \esc_html(\sprintf(
        /* translators: %s: Page name */
        $fs->get_text_inline('Secure HTTPS %s page, running from an external domain', 'secure-x-page-header'),
        $VARS['page']
    )) . ' - ' . \sprintf('<a class="fs-security-proof" href="%s" target="_blank" rel="noopener">%s</a>', 'https://www.mcafeesecure.com/verify?host=' . \WP2FA_Vendor\WP_FS__ROOT_DOMAIN_PRODUCTION, 'Freemius Inc. [US]');
}
?></span>
</div><?php 
