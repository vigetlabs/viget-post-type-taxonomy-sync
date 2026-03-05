(function() {
	const table = document.getElementById('ptts-mappings-table');
	if (!table) {
		return;
	}

	const tbody = table.querySelector('tbody');
	const templateRow = document.getElementById('ptts-row-template');
	const addButton = document.getElementById('ptts-add-row');

	function getDataRows() {
		return Array.prototype.filter.call(tbody.querySelectorAll('tr'), function(row) {
			return !row.classList.contains('ptts-row-template');
		});
	}

	function getNextIndex() {
		return getDataRows().length;
	}

	function addRow() {
		if (!templateRow) {
			return;
		}
		const index = getNextIndex();
		const newRow = templateRow.cloneNode(true);
		newRow.id = '';
		newRow.removeAttribute('style');
		newRow.classList.remove('ptts-row-template');
		newRow.classList.add('ptts-row-unsaved');

		newRow.querySelectorAll('[data-name-template]').forEach(function(el) {
			const template = el.getAttribute('data-name-template');
			if (template) {
				el.name = template.replace(/__INDEX__/g, String(index));
			}
		});
		newRow.querySelectorAll('select').forEach(function(select) {
			select.value = '';
		});

		tbody.insertBefore(newRow, templateRow);
	}

	function handleTableClick(event) {
		if (event.target && event.target.classList.contains('ptts-remove-row')) {
			const row = event.target.closest('tr');
			const dataRows = getDataRows();
			if (dataRows.length > 1) {
				row.remove();
			} else {
				row.querySelectorAll('select').forEach(function(select) {
					select.value = '';
				});
			}
			return;
		}

		if (event.target && (event.target.classList.contains('ptts-sync-row') || event.target.closest('.ptts-sync-row'))) {
			const btn = event.target.classList.contains('ptts-sync-row') ? event.target : event.target.closest('.ptts-sync-row');
			const postType = btn.getAttribute('data-post-type');
			const taxonomy = btn.getAttribute('data-taxonomy');
			if (!postType || !taxonomy || typeof pttsMappings === 'undefined') {
				return;
			}

			const labelEl = btn.querySelector('.ptts-sync-label');
			const spinnerEl = btn.querySelector('.ptts-sync-spinner');
			if (labelEl) labelEl.style.display = 'none';
			if (spinnerEl) {
				spinnerEl.classList.add('ptts-sync-spinner--active');
				spinnerEl.style.display = 'inline-block';
			}
			btn.disabled = true;

			const formData = new FormData();
			formData.append('action', 'ptts_sync_mapping');
			formData.append('nonce', pttsMappings.nonce);
			formData.append('post_type', postType);
			formData.append('taxonomy', taxonomy);

			fetch(pttsMappings.ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			})
				.then(function(res) { return res.json(); })
				.then(function(data) {
					if (data.success && data.data && data.data.message) {
						// Optional: show brief success message.
					}
				})
				.catch(function() {})
				.finally(function() {
					if (labelEl) labelEl.style.display = '';
					if (spinnerEl) {
						spinnerEl.classList.remove('ptts-sync-spinner--active');
						spinnerEl.style.display = 'none';
					}
					btn.disabled = false;
				});
		}
	}

	if (addButton) {
		addButton.addEventListener('click', addRow);
	}

	table.addEventListener('click', handleTableClick);
})();
