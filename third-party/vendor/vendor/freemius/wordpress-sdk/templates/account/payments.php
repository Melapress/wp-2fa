<?php

namespace WP2FA_Vendor;

/**
 * @package     Freemius
 * @copyright   Copyright (c) 2016, Freemius, Inc.
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
 * @since       1.2.0
 */
if (!\defined('WP2FA_Vendor\\ABSPATH')) {
    exit;
}
/**
 * @var array $VARS
 * @var Freemius $fs
 */
$fs = \WP2FA_Vendor\freemius($VARS['id']);
$slug = $fs->get_slug();
$payments = $fs->_fetch_payments();
$show_payments = \is_array($payments) && 0 < \count($payments);
if ($show_payments) {
    ?>
<div class="postbox">
	<div id="fs_payments">
		<h3><span class="dashicons dashicons-paperclip"></span> <?php 
    \WP2FA_Vendor\fs_esc_html_echo_inline('Payments', 'payments', $slug);
    ?></h3>

		<div class="inside">
			<table class="widefat">
				<thead>
				<tr>
					<th><?php 
    \WP2FA_Vendor\fs_esc_html_echo_inline('ID', 'id', $slug);
    ?></th>
					<th><?php 
    \WP2FA_Vendor\fs_esc_html_echo_inline('Date', 'date', $slug);
    ?></th>
					<th><?php 
    \WP2FA_Vendor\fs_esc_html_echo_inline('Amount', 'amount', $slug);
    ?></th>
					<th><?php 
    \WP2FA_Vendor\fs_esc_html_echo_inline('Invoice', 'invoice', $slug);
    ?></th>
				</tr>
				</thead>
				<tbody>
				<?php 
    $odd = \true;
    ?>
				<?php 
    foreach ($payments as $payment) {
        ?>
					<tr<?php 
        echo $odd ? ' class="alternate"' : '';
        ?>>
						<td><?php 
        echo $payment->id;
        ?></td>
						<td><?php 
        echo \date('M j, Y', \strtotime($payment->created));
        ?></td>
						<td><?php 
        echo $payment->formatted_gross();
        ?></td>
						<td><?php 
        if (!$payment->is_migrated()) {
            ?><a href="<?php 
            echo $fs->_get_invoice_api_url($payment->id);
            ?>"
						       class="button button-small"
						       target="_blank" rel="noopener"><?php 
            \WP2FA_Vendor\fs_esc_html_echo_inline('Invoice', 'invoice', $slug);
            ?></a><?php 
        }
        ?></td>
					</tr>
					<?php 
        $odd = !$odd;
    }
    ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
<?php 
}
