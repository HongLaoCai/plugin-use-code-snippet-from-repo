/**
 * Repo Code Snippets — admin scripts
 */
(function () {
	'use strict';

	var nameInput = document.getElementById('rcs-name');
	var slugInput = document.getElementById('rcs-slug');
	if (!nameInput || !slugInput) {
		return;
	}

	var slugTouched = slugInput.value.length > 0;

	slugInput.addEventListener('input', function () {
		slugTouched = true;
	});

	nameInput.addEventListener('input', function () {
		if (slugTouched && slugInput.value) {
			return;
		}
		slugInput.value = nameInput.value
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/^-+|-+$/g, '');
	});
})();
