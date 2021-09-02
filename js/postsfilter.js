/* -- BEGIN LICENSE BLOCK ----------------------------------
 *
 * This file is part of periodical, a plugin for Dotclear 2.
 * 
 * Copyright (c) 2009-2013 Jean-Christian Denis and contributors
 * contact@jcdenis.fr http://jcd.lv
 * 
 * Licensed under the GPL version 2.0 license.
 * A copy of this license is available in LICENSE file or at
 * http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * -- END LICENSE BLOCK ------------------------------------*/

$(function(){
	$('.checkboxes-helpers').each(function(){dotclear.checkboxesHelpers(this);});

	$filtersform = $('#filters-form');
	$filtersform.before('<p><a id="filter-control" class="form-control" href="plugin.php?p=periodical&amp;part=period&amp;period_id='+$('#filters-form input[name=period_id]').val()+'#posts" style="display:inline">'+dotclear.msg.filter_posts_list+'</a></p>')

	if( dotclear.msg.show_filters == 'false' ) {
		$filtersform.hide();
	} else {
		$('#filter-control')
			.addClass('open')
			.text(dotclear.msg.cancel_the_filter);
	}

	$('#filter-control').click(function() {
		if( $(this).hasClass('open') ) {
			if( dotclear.msg.show_filters == 'true' ) {
				return true;
			} else {
				$filtersform.hide();
				$(this).removeClass('open')
					   .text(dotclear.msg.filter_posts_list);
			}
		} else {
			$filtersform.show();
			$(this).addClass('open')
				   .text(dotclear.msg.cancel_the_filter);
		}
		return false;
	});
});