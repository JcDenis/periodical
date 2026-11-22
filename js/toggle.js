$(function() {
	const periodical = document.getElementById('periodical');
	const label = periodical?.parentNode.querySelector(':scope > label');
	if (label) {
		dotclear.toggleWithLegend(label, [periodical], {
			user_pref: 'dcx_post_periodical',
			legend_click: true,
		});
	}
});