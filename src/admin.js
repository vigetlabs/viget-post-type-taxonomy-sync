/* global vgpttsMappings */
( function () {
	const table = document.getElementById( 'vgptts-mappings-table' );
	if ( ! table ) {
		return;
	}

	const tbody = table.querySelector( 'tbody' );
	const templateRow = document.getElementById( 'vgptts-row-template' );
	const addButton = document.getElementById( 'vgptts-add-row' );

	// Editable rows only. Registered rows are locked and never submitted.
	function getDataRows() {
		return Array.prototype.filter.call(
			tbody.querySelectorAll( 'tr' ),
			function ( row ) {
				return (
					! row.classList.contains( 'vgptts-row-template' ) &&
					! row.classList.contains( 'vgptts-row-registered' )
				);
			}
		);
	}

	function getRowSelects( row ) {
		return {
			postType: row.querySelector( 'select[name$="[post_type]"]' ),
			taxonomy: row.querySelector( 'select[name$="[taxonomy]"]' ),
		};
	}

	// Unsaved rows read their selects; saved and registered rows carry their pair as data attributes.
	function getRowPair( row ) {
		const selects = getRowSelects( row );
		return {
			postType: selects.postType
				? selects.postType.value
				: row.getAttribute( 'data-post-type' ),
			taxonomy: selects.taxonomy
				? selects.taxonomy.value
				: row.getAttribute( 'data-taxonomy' ),
		};
	}

	// Taxonomies used by every other row. Flagged rows don't sync, so they don't count.
	function getTaken( exceptRow ) {
		const taken = [];
		tbody.querySelectorAll( 'tr' ).forEach( function ( row ) {
			if (
				row === exceptRow ||
				row.classList.contains( 'vgptts-row-template' ) ||
				row.classList.contains( 'vgptts-row-flagged' )
			) {
				return;
			}
			const pair = getRowPair( row );
			if ( pair.taxonomy ) {
				taken.push( pair.taxonomy );
			}
		} );
		return taken;
	}

	function toggleOptions( select, takenValues ) {
		Array.prototype.forEach.call( select.options, function ( option ) {
			const taken =
				option.value !== '' &&
				takenValues.indexOf( option.value ) !== -1;
			option.disabled = taken;
			option.hidden = taken;
		} );
	}

	// A taxonomy syncs to one post type, so hide any another row already uses. Post types can repeat.
	function filterPendingRows() {
		tbody.querySelectorAll( 'tr' ).forEach( function ( row ) {
			const selects = getRowSelects( row );
			if (
				row.classList.contains( 'vgptts-row-template' ) ||
				! selects.postType ||
				! selects.taxonomy
			) {
				return;
			}
			toggleOptions( selects.taxonomy, getTaken( row ) );
		} );
	}

	// Only ever increases, so a removed row's index is never handed out twice.
	let nextIndex = getDataRows().length;

	function getNextIndex() {
		return nextIndex++;
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

		tbody.insertBefore( newRow, templateRow );
		filterPendingRows();
	}

	function removeRow( row ) {
		row.remove();
		// Keep a blank row, so the form still posts the field on save.
		if ( ! getDataRows().length ) {
			addRow();
		}
		filterPendingRows();
	}

	// Same markup as core's dismissible notices. Focus moves to it, since the clicked button is gone.
	function showNotice( message ) {
		const container = document.getElementById( 'vgptts-notices' );
		if ( ! container || ! message ) {
			return;
		}

		const notice = document.createElement( 'div' );
		notice.className = 'notice notice-success is-dismissible';
		notice.setAttribute( 'tabindex', '-1' );

		const text = document.createElement( 'p' );
		text.textContent = message;
		notice.appendChild( text );

		const dismiss = document.createElement( 'button' );
		dismiss.type = 'button';
		dismiss.className = 'notice-dismiss';
		const label = document.createElement( 'span' );
		label.className = 'screen-reader-text';
		label.textContent = vgpttsMappings.dismissNotice;
		dismiss.appendChild( label );
		dismiss.addEventListener( 'click', function () {
			notice.remove();
		} );
		notice.appendChild( dismiss );

		container.replaceChildren( notice );
		notice.focus();
	}

	// Saved mappings are removed on the server right away, so there's nothing left to save.
	function removeSavedRow( button ) {
		if (
			typeof vgpttsMappings === 'undefined' ||
			! window.confirm( vgpttsMappings.confirmRemove ) // eslint-disable-line no-alert
		) {
			return;
		}

		const row = button.closest( 'tr' );
		const previousError = row.querySelector( '.vgptts-remove-error' );
		if ( previousError ) {
			previousError.remove();
		}
		button.disabled = true;

		const formData = new FormData();
		formData.append( 'action', 'vgptts_remove_mapping' );
		formData.append( 'nonce', vgpttsMappings.removeNonce );
		formData.append( 'post_type', row.getAttribute( 'data-post-type' ) );
		formData.append( 'taxonomy', row.getAttribute( 'data-taxonomy' ) );

		fetch( vgpttsMappings.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
		} )
			.then( function ( res ) {
				return res.json();
			} )
			.then( function ( data ) {
				if ( ! data.success ) {
					throw new Error();
				}
				removeRow( row );
				showNotice( data.data.message );
			} )
			.catch( function () {
				button.disabled = false;
				const error = document.createElement( 'span' );
				error.className = 'vgptts-row-note vgptts-remove-error';
				error.setAttribute( 'role', 'alert' );
				error.textContent = vgpttsMappings.removeFailed;
				row.querySelector( 'td' ).appendChild( error );
			} );
	}

	function handleTableClick( event ) {
		const discardButton =
			event.target && event.target.closest( '.vgptts-discard-row' );
		if ( discardButton ) {
			removeRow( discardButton.closest( 'tr' ) );
			return;
		}

		if (
			event.target &&
			event.target.classList.contains( 'vgptts-remove-row' )
		) {
			removeSavedRow( event.target );
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
		if ( event.target && event.target.tagName === 'SELECT' ) {
			filterPendingRows();
		}
	} );

	filterPendingRows();
} )();
