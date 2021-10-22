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