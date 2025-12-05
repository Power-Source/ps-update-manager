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
		
	});
	
})(jQuery);
