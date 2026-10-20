/**
 * Creative Hub settings: media-library picker for the curated-kit image.
 */
jQuery(function ($) {
	'use strict';

	var frame;
	var $id = $('#ch-kit-id');
	var $url = $('#ch-kit-url');
	var $preview = $('#ch-kit-preview');

	function showPreview(src) {
		if (src) {
			$preview.attr('src', src).show();
		} else {
			$preview.hide().attr('src', '');
		}
	}

	$('#ch-kit-select').on('click', function (e) {
		e.preventDefault();

		if (!frame) {
			frame = wp.media({
				title: chAdmin.title,
				button: { text: chAdmin.button },
				library: { type: 'image' },
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				$id.val(attachment.id);
				$url.val(attachment.url);
				showPreview(attachment.url);
			});
		}

		frame.open();
	});

	// Typing a URL by hand means the picked attachment no longer applies.
	$url.on('input change', function () {
		$id.val('');
		showPreview($.trim($url.val()));
	});

	$('#ch-kit-remove').on('click', function (e) {
		e.preventDefault();
		$id.val('');
		$url.val('');
		showPreview('');
	});
});
