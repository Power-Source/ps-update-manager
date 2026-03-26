/**
 * PS Update Manager - Admin JavaScript
 */
(function($) {
	'use strict';
	
	// Debug: Prüfe ob jQuery geladen ist
	console.log('PS Update Manager Admin JS geladen. jQuery verfügbar:', typeof $ !== 'undefined');
	
	function initForceCheck() {
		var $button = $('#ps-force-check');
		
		if ($button.length === 0) {
			console.warn('Button #ps-force-check nicht gefunden auf dieser Seite');
			return;
		}
		
		console.log('Button #ps-force-check gefunden, registriere Click-Handler');
		
		$button.on('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			console.log('Button clicked, starte Force-Check');
			
			var originalText = $button.html();
			
			// Button State
			$button.prop('disabled', true)
				.addClass('checking')
				.html('<span class="dashicons dashicons-update"></span> ' + PSUpdateManager.strings.checking);
			
			console.log('AJAX Request wird gesendet an:', PSUpdateManager.ajaxUrl);
			
			// AJAX Request
			$.ajax({
				url: PSUpdateManager.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ps_force_update_check',
					nonce: PSUpdateManager.nonce
				},
				success: function(response) {
					console.log('Force-Check Response:', response);
					
					if (response.success) {
						$button.html('<span class="dashicons dashicons-yes"></span> ' + PSUpdateManager.strings.success);
						
						// Seite nach 1 Sekunde neu laden
						setTimeout(function() {
							console.log('Laden Seite neu...');
							location.reload();
						}, 1000);
					} else {
						var errorMsg = PSUpdateManager.strings.error;
						if (response.data && response.data.message) {
							errorMsg = response.data.message;
						}
						console.error('Force-Check Fehler:', errorMsg);
						showError(errorMsg);
						resetButton();
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX Fehler:', status, error, xhr);
					showError(PSUpdateManager.strings.error + ': ' + error);
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
	}
	
	// Starte sofort wenn DOM ready
	if (document.readyState === 'loading') {
		$(document).ready(function() {
			initForceCheck();
		});
	} else {
		// DOM ist bereits ready
		initForceCheck();
	}
	
	$(document).ready(function() {
		
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
			// Bestätigungsabfrage entfernt
			
			// Button State
			$button.prop('disabled', true)
				.html('<span class="dashicons dashicons-update spin"></span> Installiere...');
			
			$.ajax({
				url: PSUpdateManager.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ps_install_product',
					nonce: PSUpdateManager.nonce,
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
