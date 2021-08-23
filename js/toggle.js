$(function() {
	$('#periodical').parent().children('label').toggleWithLegend(
		$('#periodical'),
		{
			user_pref: 'dcx_post_periodical',
			legend_click: true
		}
	);
});