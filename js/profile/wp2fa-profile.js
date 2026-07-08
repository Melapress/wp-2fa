/**
 * WP 2FA - Profile Section JavaScript
 *
 * Vanilla JS for the user profile 2FA section.
 * Handles: TOTP QR toggle, copy-to-clipboard, confirmation dialogs,
 * remove 2FA AJAX, generate backup codes AJAX, auto-open wizard.
 *
 * @package wp2fa
 * @since   4.0.0
 */
(function () {
	'use strict';

	/**
	 * Wait for DOM ready.
	 *
	 * @param {Function} fn Callback.
	 */
	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	ready(function () {
		var container = document.getElementById('wp2fa-profile-section');
		if (!container) {
			return;
		}

		// -----------------------------------------------------------------
		// TOTP QR Code toggle
		// -----------------------------------------------------------------
		var totpToggle = container.querySelector('[data-wp2fa-toggle-totp]');
		if (totpToggle) {
			totpToggle.addEventListener('click', function () {
				var content = document.getElementById('wp2fa-totp-qr-content');
				if (!content) {
					return;
				}
				var isOpen = content.classList.toggle('wp2fa-profile__totp-content--open');
				totpToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
				totpToggle.innerHTML = (isOpen ? '&#9660; ' : '&#9654; ') + totpToggle.textContent.trim().replace(/^[▶▼]\s*/, '');
				// Fix: re-set the text properly.
				var label = isOpen
					? (wp2faProfileData && wp2faProfileData.i18n && wp2faProfileData.i18n.hideQrCode ? wp2faProfileData.i18n.hideQrCode : 'Hide QR code')
					: (wp2faProfileData && wp2faProfileData.i18n && wp2faProfileData.i18n.showQrCode ? wp2faProfileData.i18n.showQrCode : 'Show QR code');
				totpToggle.innerHTML = (isOpen ? '&#9660; ' : '&#9654; ') + label;
			});
		}

		// -----------------------------------------------------------------
		// Copy to clipboard
		// -----------------------------------------------------------------
		container.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-wp2fa-copy]');
			if (!btn) {
				return;
			}
			e.preventDefault();
			var selector = btn.getAttribute('data-wp2fa-copy');
			var input = container.querySelector(selector);
			if (!input) {
				return;
			}
			var text = input.value || input.textContent;
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function () {
					showCopyFeedback(btn);
				});
			} else {
				// Fallback.
				input.select();
				document.execCommand('copy');
				showCopyFeedback(btn);
			}
		});

		function showCopyFeedback(btn) {
			var original = btn.textContent;
			var copiedText = (wp2faProfileData && wp2faProfileData.i18n && wp2faProfileData.i18n.copied) ? wp2faProfileData.i18n.copied : 'Copied!';
			btn.textContent = copiedText;
			setTimeout(function () {
				btn.textContent = original;
			}, 1500);
		}

		// -----------------------------------------------------------------
		// Confirmation dialogs
		// -----------------------------------------------------------------
		container.addEventListener('click', function (e) {
			var trigger = e.target.closest('[data-wp2fa-confirm]');
			if (!trigger) {
				return;
			}
			e.preventDefault();
			var dialogId = 'wp2fa-confirm-' + trigger.getAttribute('data-wp2fa-confirm');
			var overlay = document.getElementById(dialogId);
			if (overlay) {
				overlay.classList.add('wp2fa-profile__confirm--open');
				// Focus the first button inside.
				var firstBtn = overlay.querySelector('.wp2fa-profile__btn');
				if (firstBtn) {
					firstBtn.focus();
				}
			}
		});

		// Close confirmation dialogs.
		document.addEventListener('click', function (e) {
			// Close button inside dialog.
			if (e.target.closest('[data-wp2fa-confirm-close]')) {
				e.preventDefault();
				var overlay = e.target.closest('.wp2fa-profile__confirm-overlay');
				if (overlay) {
					overlay.classList.remove('wp2fa-profile__confirm--open');
				}
				return;
			}
			// Click on overlay background (not dialog itself).
			if (e.target.classList && e.target.classList.contains('wp2fa-profile__confirm-overlay')) {
				e.target.classList.remove('wp2fa-profile__confirm--open');
			}
		});

		// ESC to close dialogs.
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				var openOverlays = document.querySelectorAll('.wp2fa-profile__confirm--open');
				openOverlays.forEach(function (el) {
					el.classList.remove('wp2fa-profile__confirm--open');
				});
			}
		});

		// -----------------------------------------------------------------
		// Remove 2FA via AJAX
		// -----------------------------------------------------------------
		container.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-wp2fa-remove-2fa]');
			if (!btn) {
				return;
			}
			e.preventDefault();
			var userId = btn.getAttribute('data-user-id');
			var nonce = btn.getAttribute('data-nonce');
			var ajaxUrl = wp2faProfileData ? wp2faProfileData.ajaxUrl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');

			var formData = new FormData();
			formData.append('action', 'remove_user_2fa');
			formData.append('user_id', userId);
			formData.append('wp_2fa_nonce', nonce);

			fetch(ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData
			}).then(function (response) {
				return response.json();
			}).then(function (result) {
				if (result.success) {
					window.location.reload();
				} else {
					var errorMsg = (result.data && result.data.error) ? result.data.error : 'An error occurred.';
					alert(errorMsg); // eslint-disable-line no-alert
				}
			}).catch(function () {
				alert('Network error. Please try again.'); // eslint-disable-line no-alert
			});

			// Close the dialog.
			var overlay = btn.closest('.wp2fa-profile__confirm-overlay');
			if (overlay) {
				overlay.classList.remove('wp2fa-profile__confirm--open');
			}
		});

		// -----------------------------------------------------------------
		// -----------------------------------------------------------------
		// Generate backup codes — handled by wp2fa-profile-backup-codes.js
		// -----------------------------------------------------------------

		// -----------------------------------------------------------------
		// Auto-open wizard on page load if required
		// -----------------------------------------------------------------
		if (wp2faProfileData && wp2faProfileData.autoOpenWizard) {
			// Retry with increasing delay to ensure wizard scripts have initialized.
			var attempts = 0;
			var maxAttempts = 10;
			function tryOpenWizard() {
				var wizardBtn = container.querySelector('[data-wp2fa-open-wizard]');
				if (wizardBtn && typeof wp !== 'undefined' && wp.hooks && typeof wp2faWizardData !== 'undefined') {
					wizardBtn.click();
				} else if (++attempts < maxAttempts) {
					setTimeout(tryOpenWizard, 300);
				}
			}
			setTimeout(tryOpenWizard, 300);
		}
	});
})();
