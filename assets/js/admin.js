/**
 * PS Update Manager - Admin JavaScript
 */
(function($) {
	'use strict';
	
	$(document).ready(function() {
		
		/**
		 * Force Update Check Button
		 */
		$('#ps-force-check').on('click', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var originalText = $button.html();
			
			// Button State
			$button.prop('disabled', true)
				.addClass('checking')
				.html('<span class="dashicons dashicons-update"></span> ' + psUpdateManager.strings.checking);
			
			// AJAX Request
			$.ajax({
				url: psUpdateManager.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ps_force_update_check',
					nonce: psUpdateManager.nonce
				},
				success: function(response) {
					if (response.success) {
						$button.html('<span class="dashicons dashicons-yes"></span> ' + psUpdateManager.strings.success);
						
						// Seite nach 1 Sekunde neu laden
						setTimeout(function() {
							location.reload();
						}, 1000);
					} else {
						showError(response.data.message || psUpdateManager.strings.error);
						resetButton();
					}
				},
				error: function() {
					showError(psUpdateManager.strings.error);
					resetButton();
				}
			});
			
			function resetButton() {
				$button.prop('disabled', false)
					.removeClass('checking')
					.html(originalText);
			}
			
			function showError(message) {
				var $notice = $('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>');
				$('.wrap h1').after($notice);
				
				// Dismissible Notice
				$(document).on('click', '.notice-dismiss', function() {
					$(this).parent().fadeOut();
				});
			}
		});
		
		/**
		 * Product Card Hover Effects
		 */
		$('.ps-product-card').on('mouseenter', function() {
			$(this).find('.ps-product-links').css('opacity', '1');
		});
		
		/**
		 * External Links in neuen Tabs öffnen
		 */
		$('.ps-product-links a[target="_blank"]').on('click', function(e) {
			// Nichts tun, nur sicherstellen dass target="_blank" funktioniert
		});
		
		/**
		 * AJAX Installation von Produkten
		 */
		$(document).on('click', '.ps-install-product', function(e) {
			e.preventDefault();
			var $button = $(this);
			var slug = $button.data('slug');
			var repo = $button.data('repo');
			var type = $button.data('type');
			var $card = $button.closest('.ps-store-card');
			
			// Bestätigung
			if (!confirm('Möchten Sie "' + slug + '" von GitHub installieren?')) {
				return;
			}
			
			// Button State
			$button.prop('disabled', true)
				.html('<span class="dashicons dashicons-update spin"></span> Installiere...');
			
			$.ajax({
				url: psUpdateManager.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ps_install_product',
					nonce: psUpdateManager.nonce,
					slug: slug,
					repo: repo,
					type: type
				},
				success: function(response) {
					if (response.success) {
						// Erfolgs-Badge zeigen
						$card.find('.ps-store-status').html('<span class="ps-badge ps-badge-active">✓ Installiert</span>');
						$button.html('<span class="dashicons dashicons-yes-alt"></span> Erfolgreich installiert!');
						
						// Nach 2 Sekunden Seite neu laden
						setTimeout(function() {
							location.reload();
						}, 2000);
					} else {
						// Fehler-Nachricht aus response.data holen (kann String oder Objekt sein)
						var errorMsg = response.data;
						if (typeof response.data === 'object' && response.data.message) {
							errorMsg = response.data.message;
						}
						alert('Installation fehlgeschlagen: ' + errorMsg);
						$button.prop('disabled', false)
							.html('<span class="dashicons dashicons-download"></span> Installieren');
					}
				},
				error: function(xhr, status, error) {
					// Versuche Fehler aus Response zu extrahieren
					var errorMsg = error || 'Unbekannter Fehler';
					try {
						var response = JSON.parse(xhr.responseText);
						if (response.data) {
							errorMsg = typeof response.data === 'string' ? response.data : response.data.message || error;
						}
					} catch(e) {
						// JSON Parse Error - nutze default error
					}
					alert('Ein Fehler ist aufgetreten: ' + errorMsg);
					$button.prop('disabled', false)
						.html('<span class="dashicons dashicons-download"></span> Installieren');
				}
			});
		});
		
		// Spin-Animation für Ladezeichen
		if (!$('style#ps-spin-animation').length) {
			$('<style id="ps-spin-animation">.spin { animation: spin 1s linear infinite; } @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>').appendTo('head');
		}
		
	});
	
})(jQuery);
