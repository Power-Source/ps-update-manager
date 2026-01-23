/**
 * PSOURCE Katalog - Tab-basiertes AJAX-System
 */
(function($) {
	'use strict';

	const PSCatalog = {
		activeTab: 'plugins',
		currentPage: 1,
		filters: {
			search: '',
			category: 'all',
			status: 'all'
		},

		init() {
			this.bindEvents();
			this.loadProducts();
		},

		bindEvents() {
			// Tab-Wechsel
			$(document).on('click', '.ps-tab-link', (e) => {
				e.preventDefault();
				const tab = $(e.currentTarget).data('tab');
				this.switchTab(tab);
			});

			// Filter-Formular
			$(document).on('submit', '#ps-catalog-filters', (e) => {
				e.preventDefault();
				this.applyFilters();
			});

			// Pagination
			$(document).on('click', '.ps-pagination-link', (e) => {
				e.preventDefault();
				const page = $(e.currentTarget).data('page');
				this.goToPage(page);
			});

			// Filter Reset
			$(document).on('click', '#ps-reset-filters', (e) => {
				e.preventDefault();
				this.resetFilters();
			});

			// Plugin-Navigation über Compatibility/Extends Links
			$(document).on('click', '.ps-extends-link, .ps-compatibility-pill:not(.ps-extends-link)', (e) => {
				const $el = $(e.currentTarget);
				
				// Prüfe ob es ein Link ist (extends) oder nur ein Badge
				if ( $el.hasClass('ps-extends-link') ) {
					e.preventDefault();
					const slug = $el.data('slug');
					this.scrollToProduct(slug);
				}
			});

			// Installation
			$(document).on('click', '.ps-install-product', (e) => {
				e.preventDefault();
				const $btn = $(e.currentTarget);
				const slug = $btn.data('slug');
				const repo = $btn.data('repo');
				const type = $btn.data('type');
				this.installProduct(slug, repo, type, $btn);
			});

			// Update
			$(document).on('click', '.ps-update-product', (e) => {
				e.preventDefault();
				const $btn = $(e.currentTarget);
				const slug = $btn.data('slug');
				const basename = $btn.data('basename');
				const type = $btn.data('type');
				this.updateProduct(slug, basename, type, $btn);
			});

			// Aktivierung
			$(document).on('click', '.ps-activate-plugin', (e) => {
				e.preventDefault();
				const $btn = $(e.currentTarget);
				const slug = $btn.data('slug');
				const basename = $btn.data('basename');
				const network = $btn.data('network');
				this.activatePlugin(slug, basename, network, $btn);
			});

			// Deaktivierung
			$(document).on('click', '.ps-deactivate-plugin', (e) => {
				e.preventDefault();
				const $btn = $(e.currentTarget);
				const slug = $btn.data('slug');
				const basename = $btn.data('basename');
				const type = $btn.data('type');
				this.deactivatePlugin(slug, basename, type, $btn);
			});
		},

		scrollToProduct(slug) {
			// Suche die Card mit dem Slug
			const $card = $(`.ps-store-card[data-slug="${slug}"]`);
			
			if ( $card.length ) {
				// Scroll zur Card mit sanfter Animation
				$('html, body').animate({
					scrollTop: $card.offset().top - 100
				}, 500);

				// Kurze Highlight-Animation
				$card.css({
					boxShadow: '0 0 0 3px rgba(34, 113, 177, 0.3)',
					transition: 'box-shadow 0.3s ease'
				});

				setTimeout(() => {
					$card.css({ boxShadow: '' });
				}, 2000);
			}
		},

		switchTab(tab) {
			this.activeTab = tab;
			this.currentPage = 1;
			this.resetFilters();
			
			// Tab-UI aktualisieren
			$('.ps-tab-link').removeClass('nav-tab-active');
			$(`.ps-tab-link[data-tab="${tab}"]`).addClass('nav-tab-active');
			
			// Kategorien für den Tab laden
			this.loadCategories(tab);
			
			this.loadProducts();
		},

		loadCategories(tab) {
			// Kategorien per AJAX laden und Dropdown aktualisieren
			$.ajax({
				url: PSUpdateManager.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ps_get_categories',
					nonce: PSUpdateManager.nonce,
					tab: tab
				},
				success: (response) => {
					if (response.success && response.data.categories) {
						const $select = $('#ps-category');
						$select.empty();
						$select.append('<option value="all">Alle Kategorien</option>');
						$.each(response.data.categories, (key, label) => {
							$select.append(`<option value="${key}">${label}</option>`);
						});
					}
				}
			});
		},

		applyFilters() {
			this.filters.search = $('#ps-search').val();
			this.filters.category = $('#ps-category').val();
			this.filters.status = $('#ps-status').val();
			this.currentPage = 1;
			this.loadProducts();
		},

		resetFilters() {
			this.filters = { search: '', category: 'all', status: 'all' };
			$('#ps-search').val('');
			$('#ps-category').val('all');
			$('#ps-status').val('all');
			this.currentPage = 1;
			this.loadProducts();
		},

		goToPage(page) {
			this.currentPage = parseInt(page);
			this.loadProducts();
		},

		loadProducts() {
			const $grid = $('#ps-products-grid');
			const $pagination = $('#ps-pagination');
			
			// Loading-State
			$grid.html('<div class="ps-loading"><span class="spinner is-active"></span> Lade Produkte...</div>');
			$pagination.empty();

			$.ajax({
				url: PSUpdateManager.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ps_load_products',
					nonce: PSUpdateManager.nonce,
					tab: this.activeTab,
					page: this.currentPage,
					search: this.filters.search,
					category: this.filters.category,
					status: this.filters.status
				},
				success: (response) => {
					if (response.success) {
						$grid.html(response.data.html);
						if (response.data.pagination) {
							$pagination.html(response.data.pagination);
						}
					} else {
						$grid.html(`<div class="notice notice-error"><p>${response.data.message || 'Fehler beim Laden'}</p></div>`);
					}
				},
				error: () => {
					$grid.html('<div class="notice notice-error"><p>Verbindungsfehler beim Laden der Produkte.</p></div>');
				}
			});
		},

		installProduct(slug, repo, type, $btn) {
			// Keine Bestätigungsabfrage mehr
			const originalText = $btn.html();
			$btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0"></span> Installiere...');

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
				success: (response) => {
					if (response.success) {
						this.loadProducts(); // Neu laden
					} else {
						this.showNotice('error', '✗ Fehler: ' + (response.data.message || 'Installation fehlgeschlagen'));
						$btn.prop('disabled', false).html(originalText);
					}
				},
				error: () => {
					this.showNotice('error', '✗ Verbindungsfehler bei der Installation');
					$btn.prop('disabled', false).html(originalText);
				}
			});
		},

		updateProduct(slug, basename, type, $btn) {
			const originalText = $btn.html();
			$btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0"></span> Aktualisiere...');

			$.ajax({
				url: PSUpdateManager.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ps_update_product',
					nonce: PSUpdateManager.nonce,
					slug: slug,
					basename: basename,
					type: type
				},
				success: (response) => {
					if (response.success) {
						this.showNotice('success', '✓ Update erfolgreich installiert');
						this.loadProducts(); // Grid neu laden
					} else {
						this.showNotice('error', '✗ Fehler: ' + (response.data.message || 'Update fehlgeschlagen'));
						$btn.prop('disabled', false).html(originalText);
					}
				},
				error: () => {
					this.showNotice('error', '✗ Verbindungsfehler beim Update');
					$btn.prop('disabled', false).html(originalText);
				}
			});
		},

		activatePlugin(slug, basename, network, $btn) {
			const originalText = $btn.html();
			$btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0"></span> Aktiviere...');

			$.ajax({
				url: PSUpdateManager.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ps_activate_plugin',
					nonce: PSUpdateManager.nonce,
					slug: slug,
					basename: basename,
					network: network
				},
				success: (response) => {
					if (response.success) {
						this.showNotice('success', '✓ Plugin wurde aktiviert');
						this.loadProducts(); // Grid neu laden
					} else {
						this.showNotice('error', '✗ Fehler: ' + (response.data.message || 'Aktivierung fehlgeschlagen'));
						$btn.prop('disabled', false).html(originalText);
					}
				},
				error: () => {
					this.showNotice('error', '✗ Verbindungsfehler bei der Aktivierung');
					$btn.prop('disabled', false).html(originalText);
				}
			});
		},

		deactivatePlugin(slug, basename, type, $btn) {
			const originalText = $btn.html();
			$btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0"></span> Deaktiviere...');

			$.ajax({
				url: PSUpdateManager.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ps_deactivate_plugin',
					nonce: PSUpdateManager.nonce,
					slug: slug,
					basename: basename
				},
				success: (response) => {
					if (response.success) {
						this.showNotice('success', '✓ Plugin wurde deaktiviert');
						this.loadProducts(); // Grid neu laden
					} else {
						this.showNotice('error', '✗ Fehler: ' + (response.data.message || 'Deaktivierung fehlgeschlagen'));
						$btn.prop('disabled', false).html(originalText);
					}
				},
				error: () => {
					this.showNotice('error', '✗ Verbindungsfehler bei der Deaktivierung');
					$btn.prop('disabled', false).html(originalText);
				}
			});
		},

		showNotice(type, message) {
			// type: 'success' oder 'error'
			let $notice = $(`#ps-catalog-notice`);
			if (!$notice.length) {
				$notice = $('<div id="ps-catalog-notice"></div>').prependTo('body');
			}
			$notice
				.removeClass('ps-catalog-success ps-catalog-error')
				.addClass(type === 'success' ? 'ps-catalog-success' : 'ps-catalog-error')
				.html(`<div style="padding:10px 20px;font-size:16px;">${message}</div>`)
				.fadeIn(200);
			setTimeout(() => { $notice.fadeOut(400); }, 2500);
		}
	}

	// Init beim Laden
	$(document).ready(() => {
		if ($('.ps-update-manager-psources').length) {
			PSCatalog.init();
			// Initial Kategorien für Plugins laden
			PSCatalog.loadCategories('plugins');
		}
	});

})(jQuery);
