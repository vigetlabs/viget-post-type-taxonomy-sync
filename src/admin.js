/* global vgpttsMappings */
( function () {
	const table = document.getElementById( 'vgptts-mappings-table' );
	if ( ! table ) {
		return;
	}

	const tbody = table.querySelector( 'tbody' );
	const templateRow = document.getElementById( 'vgptts-row-template' );
	const addButton = document.getElementById( 'vgptts-add-row' );
	const AUTO_VALUE = '__vgptts_auto__';

	// The Attach To checkboxes only mean anything for an auto-created taxonomy.
	function syncAttachVisibility( row ) {
		const select = row.querySelector( '.vgptts-taxonomy-select' );
		const cell = row.querySelector( '.vgptts-attach-cell' );
		if ( ! select || ! cell ) {
			return;
		}
		cell.hidden = select.value !== AUTO_VALUE;
	}

	// Menu visibility is only ours to decide for an auto-created post type the editor
	// does not manage.
	function syncMenuToggleVisibility( row ) {
		const source = row.querySelector( '.vgptts-source-select' );
		const toggle = row.querySelector( '.vgptts-menu-toggle' );
		if ( ! source || ! toggle ) {
			return;
		}
		const postType = row.querySelector( '.vgptts-post-type-select' );
		const postTypeIsAuto = postType
			? postType.value === AUTO_VALUE
			: row.dataset.postTypeAuto === '1';
		toggle.hidden = ! ( postTypeIsAuto && source.value === 'taxonomy' );
	}

	function syncRow( row ) {
		syncAttachVisibility( row );
		syncMenuToggleVisibility( row );
	}

	function getDataRows() {
		return Array.prototype.filter.call(
			tbody.querySelectorAll( 'tr' ),
			function ( row ) {
				return ! row.classList.contains( 'vgptts-row-template' );
			}
		);
	}

	function getNextIndex() {
		return getDataRows().length;
	}

	function addRow() {
		if ( ! templateRow ) {
			return;
		}
		const index = getNextIndex();
		const newRow = templateRow.cloneNode( true );
		newRow.id = '';
		newRow.removeAttribute( 'style' );
		newRow.classList.remove( 'vgptts-row-template' );
		newRow.classList.add( 'vgptts-row-unsaved' );

		newRow
			.querySelectorAll( '[data-name-template]' )
			.forEach( function ( el ) {
				const template = el.getAttribute( 'data-name-template' );
				if ( template ) {
					el.name = template.replace( /__INDEX__/g, String( index ) );
				}
			} );
		newRow.querySelectorAll( 'select' ).forEach( function ( select ) {
			select.value = '';
		} );
		newRow
			.querySelectorAll( 'input[type="checkbox"]' )
			.forEach( function ( input ) {
				input.checked = false;
			} );
		syncRow( newRow );

		tbody.insertBefore( newRow, templateRow );
	}

	function handleTableClick( event ) {
		if (
			event.target &&
			event.target.classList.contains( 'vgptts-remove-row' )
		) {
			const row = event.target.closest( 'tr' );
			const dataRows = getDataRows();
			if ( dataRows.length > 1 ) {
				row.remove();
			} else {
				row.querySelectorAll( 'select' ).forEach( function ( select ) {
					select.value = '';
				} );
			}
			return;
		}

		if (
			event.target &&
			( event.target.classList.contains( 'vgptts-sync-row' ) ||
				event.target.closest( '.vgptts-sync-row' ) )
		) {
			const btn = event.target.classList.contains( 'vgptts-sync-row' )
				? event.target
				: event.target.closest( '.vgptts-sync-row' );
			const postType = btn.getAttribute( 'data-post-type' );
			const taxonomy = btn.getAttribute( 'data-taxonomy' );
			if (
				! postType ||
				! taxonomy ||
				typeof vgpttsMappings === 'undefined'
			) {
				return;
			}

			const labelEl = btn.querySelector( '.vgptts-sync-label' );
			const spinnerEl = btn.querySelector( '.vgptts-sync-spinner' );
			if ( labelEl ) {
				labelEl.style.display = 'none';
			}
			if ( spinnerEl ) {
				spinnerEl.classList.add( 'vgptts-sync-spinner--active' );
				spinnerEl.style.display = 'inline-block';
			}
			btn.disabled = true;

			const formData = new FormData();
			formData.append( 'action', 'vgptts_sync_mapping' );
			formData.append( 'nonce', vgpttsMappings.nonce );
			formData.append( 'post_type', postType );
			formData.append( 'taxonomy', taxonomy );

			fetch( vgpttsMappings.ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
			} )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( data ) {
					if ( data.success && data.data && data.data.message ) {
						// Optional: show brief success message.
					}
				} )
				.catch( function () {} )
				.finally( function () {
					if ( labelEl ) {
						labelEl.style.display = '';
					}
					if ( spinnerEl ) {
						spinnerEl.classList.remove(
							'vgptts-sync-spinner--active'
						);
						spinnerEl.style.display = 'none';
					}
					btn.disabled = false;
				} );
		}
	}

	if ( addButton ) {
		addButton.addEventListener( 'click', addRow );
	}

	table.addEventListener( 'click', handleTableClick );
	table.addEventListener( 'change', function ( event ) {
		if ( ! event.target || ! event.target.closest ) {
			return;
		}
		if (
			event.target.classList.contains( 'vgptts-taxonomy-select' ) ||
			event.target.classList.contains( 'vgptts-post-type-select' ) ||
			event.target.classList.contains( 'vgptts-source-select' )
		) {
			syncRow( event.target.closest( 'tr' ) );
		}
	} );

	getDataRows().forEach( syncRow );
} )();
