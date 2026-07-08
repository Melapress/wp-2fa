<?php
/**
 * Reports Dashboard Template
 *
 * @package wp-2fa
 */

use WP2FA\Admin\Settings_Builder;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Extensions\Reporting\Abstract_Report;
use WP2FA\Extensions\Reporting\User_Status_Report;
use WP2FA\Extensions\Reporting\Methods_Report;

// Variables $user_status_data, $methods_data, and $stat_totals
// are expected to be set by the calling render method.

$csv_nonce = \wp_create_nonce( 'wp2fa_export_report_csv' );
?>

<div class="reports-settings-main-wrapper">
<?php
Settings_Builder::build_option(
	array(
		'title' => \esc_html__( 'Reports', 'wp-2fa' ),
		'id'    => 'reports-page-title',
		'type'  => 'tab-title',
	)
);
?>

<div class="settings-card">
	<?php
	Settings_Builder::build_option(
		array(
			'text'  => \esc_html__( 'In this page you will find a number of reports which allow you to get a better overview of the current state of 2FA on your website.', 'wp-2fa' ),
			'class' => 'description-settings-card',
			'id'    => 'reports-description',
			'type'  => 'description',
		)
	);

	Settings_Builder::build_option(
		array(
			'id'    => 'reports-stat-cards',
			'type'  => 'stat-cards',
			'cards' => $stat_totals,
		)
	);
	?>
</div>

<?php
// --- Users 2FA setup table(s) ---
$sites = WP_Helper::get_multi_sites();
if ( empty( $sites ) ) {
	$sites = array( 1 );
}

foreach ( $sites as $site_id ) {
	if ( is_a( $site_id, '\stdClass' ) || is_a( $site_id, '\WP_Site' ) ) {
		$site_id = $site_id->blog_id;
	}

	$report_data = User_Status_Report::get_data( $site_id );
	$rows        = array();
	if ( isset( $report_data['rows'] ) ) {
		foreach ( $report_data['rows'] as $key => $row ) {
			if ( '' !== trim( $row['role_name'] ) ) {
				$rows[ $key ] = $row;
			}
		}
		$report_data['rows'] = array_values( $rows );
	}

	$table_title = \esc_html__( 'Users 2FA setup', 'wp-2fa' );
	if ( WP_Helper::is_multisite() ) {
		$table_title .= ' - ' . \esc_html( \get_blog_option( $site_id, 'blogname' ) );
	}

	$table_id = 'report-users-2fa-' . (int) $site_id;
	?>
	<div class="settings-card">
		<div class="report-table-header">
			<?php
			Settings_Builder::build_option(
				array(
					'title' => $table_title,
					'id'    => 'users-2fa-setup-title-' . (int) $site_id,
					'type'  => 'section-title',
				)
			);
			?>
			<button type="button" class="button btn-outline js-export-csv" data-table-id="<?php echo \esc_attr( $table_id ); ?>" data-nonce="<?php echo \esc_attr( $csv_nonce ); ?>" data-report-type="user_status">
				<?php \esc_html_e( 'Download as CSV', 'wp-2fa' ); ?>
			</button>
		</div>
		<?php
		Settings_Builder::build_option(
			array(
				'id'       => 'users-2fa-table-' . (int) $site_id,
				'type'     => 'data-table',
				'header'   => ! empty( $report_data['header'] ) ? $report_data['header'] : array(),
				'rows'     => ! empty( $report_data['rows'] ) ? $report_data['rows'] : array(),
				'table_id' => $table_id,
			)
		);
		?>
	</div>
	<?php
}
?>

<?php
// --- 2FA methods and backup codes table ---
$methods_report_data = Methods_Report::get_data();
$rows                = array();
if ( isset( $methods_report_data['rows'] ) ) {
	foreach ( $methods_report_data['rows'] as $key => $row ) {
		if ( '' !== trim( $row['role_name'] ) ) {
			$rows[ $key ] = $row;
		}
	}
	$methods_report_data['rows'] = array_values( $rows );
}
?>

<div class="settings-card">
	<div class="report-table-header">
		<?php
		Settings_Builder::build_option(
			array(
				'title' => \esc_html__( '2FA methods and backup codes', 'wp-2fa' ),
				'id'    => 'methods-report-title',
				'type'  => 'section-title',
			)
		);
		?>
		<button type="button" class="button btn-outline js-export-csv" data-table-id="report-methods" data-nonce="<?php echo \esc_attr( $csv_nonce ); ?>" data-report-type="methods">
			<?php \esc_html_e( 'Download as CSV', 'wp-2fa' ); ?>
		</button>
	</div>
	<?php
	Settings_Builder::build_option(
		array(
			'id'       => 'methods-report-table',
			'type'     => 'data-table',
			'header'   => ! empty( $methods_report_data['header'] ) ? $methods_report_data['header'] : array(),
			'rows'     => ! empty( $methods_report_data['rows'] ) ? $methods_report_data['rows'] : array(),
			'table_id' => 'report-methods',
		)
	);
	?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	document.querySelectorAll('.js-export-csv').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var tableId = this.getAttribute('data-table-id');
			var nonce   = this.getAttribute('data-nonce');
			var table   = document.getElementById(tableId);
			if (!table) return;

			// Collect table data as arrays.
			var data = [];
			var headerRow = table.querySelector('thead tr');
			if (headerRow) {
				var headerCells = [];
				headerRow.querySelectorAll('th').forEach(function(th) {
					headerCells.push(th.textContent.trim());
				});
				data.push(headerCells);
			}

			table.querySelectorAll('tbody tr').forEach(function(tr) {
				var rowCells = [];
				tr.querySelectorAll('td').forEach(function(td) {
					rowCells.push(td.textContent.trim());
				});
				data.push(rowCells);
			});

			// Post to the AJAX endpoint.
			var formData = new FormData();
			formData.append('action', 'wp2fa_export_report_csv');
			formData.append('_wpnonce', nonce);
			formData.append('table_data', JSON.stringify(data));

			var xhr = new XMLHttpRequest();
			xhr.open('POST', ajaxurl, true);
			xhr.responseType = 'blob';

			xhr.onload = function() {
				if (xhr.status === 200) {
					var blob = xhr.response;
					var url  = window.URL.createObjectURL(blob);
					var a    = document.createElement('a');
					a.href     = url;
					a.download = tableId + '.csv';
					document.body.appendChild(a);
					a.click();
					document.body.removeChild(a);
					window.URL.revokeObjectURL(url);
				}
			};

			xhr.send(formData);
		});
	});
});
</script>
</div>
