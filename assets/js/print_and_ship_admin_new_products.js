jQuery(document).on('click', '#search-submit', function (e) {
	e.preventDefault();
	e.stopPropagation();

	jQuery('#table-form').attr('method', 'get');
	jQuery('#table-form').submit();
});