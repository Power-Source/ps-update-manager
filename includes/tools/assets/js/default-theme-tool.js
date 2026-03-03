/**
 * Default Theme Tool - JavaScript
 * Handles installation of recommended themes (PS Padma, PS Padma Child)
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		
		/**
		 * Handle theme installation button clicks
		 */
		$('.ps-install-btn').on('click', function(e) {
			e.preventDefault();
			
			var $btn = $(this);
			var slug = $btn.data('slug');
			var repo = $btn.data('repo');
			var $card = $btn.closest('.ps-theme-card');
			
			// Prevent double-clicks
			if ($btn.prop('disabled')) {
				return;
			}
			
			// Update button state
			$btn.prop('disabled', true).addClass('loading');
			$btn.html('<span class="icon">⏳</span> Installiere...');
			
			// Make AJAX request
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'ps_install_product',
					slug: slug,
					repo: repo,
					type: 'theme',
					nonce: PSUpdateManager.nonce
				},
				success: function(response) {
					if (response.success) {
						// Update card to show installed state
						$card.addClass('installed');
						$btn.html('<span class="icon">✓</span> Installiert');
						$btn.removeClass('loading');
						
						// Add status badge if not present
						if ($card.find('.ps-status-badge').length === 0) {
							$card.find('h4').append('<span class="ps-status-badge">✓ Installiert</span>');
						}
						
						// Reload page after short delay to update theme dropdown
						setTimeout(function() {
							location.reload();
						}, 1500);
					} else {
						// Show error
						var errorMsg = response.data && response.data.message 
							? response.data.message 
							: 'Installation fehlgeschlagen';
						
						$btn.html('<span class="icon">⚠</span> Fehler');
						$btn.removeClass('loading');
						
						alert('Fehler bei Installation: ' + errorMsg);
						
						// Re-enable button after error
						setTimeout(function() {
							$btn.prop('disabled', false);
							$btn.html('<span class="icon">⬇</span> Jetzt installieren');
						}, 2000);
					}
				},
				error: function(xhr, status, error) {
					$btn.html('<span class="icon">⚠</span> Fehler');
					$btn.removeClass('loading');
					
					alert('AJAX-Fehler: ' + error);
					
					// Re-enable button after error
					setTimeout(function() {
						$btn.prop('disabled', false);
						$btn.html('<span class="icon">⬇</span> Jetzt installieren');
					}, 2000);
				}
			});
		});
		
	});

})(jQuery);
