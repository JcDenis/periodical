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
	var periodicalstart=document.getElementById('period_curdt');
	if(periodicalstart!=undefined){
		var periodicalstart_dtPick=new datePicker(periodicalstart);
		periodicalstart_dtPick.img_top='1.5em';
		periodicalstart_dtPick.draw();
	}
	var periodicalend=document.getElementById('period_enddt');
	if(periodicalend!=undefined){
		var periodicalend_dtPick=new datePicker(periodicalend);
		periodicalend_dtPick.img_top='1.5em';
		periodicalend_dtPick.draw();
	}
});