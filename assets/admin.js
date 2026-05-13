(function ($) {
	'use strict';

	function getContent() {
		if (window.tinyMCE) {
			var ed = tinyMCE.get('content');
			if (ed && !ed.isHidden()) {
				return ed.getContent({ format: 'text' });
			}
		}
		return $('#content').val() || '';
	}

	function setBusy($link, busy) {
		var $wrap = $link.closest('.skai-link-wrap');
		$wrap.find('.skai-spinner').css('visibility', busy ? 'visible' : 'hidden');
		if (busy) {
			$link.addClass('skai-disabled').attr('aria-busy', 'true');
		} else {
			$link.removeClass('skai-disabled').removeAttr('aria-busy');
		}
	}

	function showMessage($link, text, isError) {
		var $msg = $link.closest('.skai-link-wrap').find('.skai-message');
		$msg.text(text || '').toggleClass('skai-error', !!isError);
	}

	function appendTags(tags) {
		if (!tags || !tags.length) { return; }
		var $newTag = $('#new-tag-post_tag');
		if (!$newTag.length) { return; }
		$newTag.val(tags.join(', '));
		// WP's tagBox handles dedupe + UI refresh.
		$('#tagsdiv-post_tag input.tagadd').trigger('click');
	}

	$(document).on('click', '.skai-generate-link', function (e) {
		e.preventDefault();
		var $link = $(this);
		if ($link.hasClass('skai-disabled')) { return; }

		var content = getContent();
		if (!content || !content.replace(/\s+/g, '').length) {
			showMessage($link, (window.SKAI && SKAI.i18n.emptyContent) || 'Article content is empty.', true);
			return;
		}

		showMessage($link, (window.SKAI && SKAI.i18n.loading) || 'Generating…', false);
		setBusy($link, true);

		$.post(SKAI.ajaxurl, {
			action: 'skai_generate_tags',
			nonce: SKAI.nonce,
			post_id: SKAI.post_id,
			content: content
		}).done(function (resp) {
			if (resp && resp.success && resp.data && resp.data.tags) {
				appendTags(resp.data.tags);
				showMessage($link, '', false);
			} else {
				var msg = (resp && resp.data && resp.data.message) || ((window.SKAI && SKAI.i18n.error) || 'Generation failed.');
				showMessage($link, msg, true);
			}
		}).fail(function (xhr) {
			var msg = ((window.SKAI && SKAI.i18n.error) || 'Generation failed.') + ' (' + (xhr && xhr.status) + ')';
			showMessage($link, msg, true);
		}).always(function () {
			setBusy($link, false);
		});
	});
})(jQuery);
