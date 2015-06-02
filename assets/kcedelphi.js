$( document ).tooltip();
function showDialog(){


   return false;
}

$(document).on('shown','#manualmodal', function () {
	console.log($("#manualmodal .modal-body").height());
	$("#manualframe").height($("#manualmodal .modal-body").height());
	//$(".modal-body").css('overflow
})

$(document).on("click",'a[rel="external"]',function(event){
event.preventDefault();

//var title="";
//if($(this).attr('title') && $(this).attr('title')!=""){
//title='<h3>'+$(this).attr('title')+'</h3>'
//}else if($(this).attr('oldtitle')){
//title='<h3>'+$(this).attr('oldtitle')+'</h3>'
//}
//console.log($(this).attr('title'));

htmlelement='<div id="manualmodal" class="modal modal-lg" tabindex="-1" role="dialog">'
			+'<button type="button" class="close" data-dismiss="modal">×</button>'
			+'<div class="modal-body">'
			+'<iframe id="manualframe" name="manualfrale" src="'+$(this).attr('href')+'" frameborder="0" width="99.6%"></iframe>'
			+'</div>'
			+'</div>'
	$(htmlelement).modal({show:true})

//	//return false;
});
