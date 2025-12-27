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

			// Installation
			$(document).on('click', '.ps-install-product', (e) => {
				e.preventDefault();
				const $btn = $(e.currentTarget);
				const slug = $btn.data('slug');
				const repo = $btn.data('repo');
				const type = $btn.data('type');
				this.installProduct(slug, repo, type, $btn);
			});
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
