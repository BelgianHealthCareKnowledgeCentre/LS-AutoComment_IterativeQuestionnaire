$( document ).tooltip(
	{
	items: "[data-kcetitle], [title]",
      content: function() {
        var element = $( this );
        if ( element.is( "[data-kcetitle]" ) ) {
			return $(element).prev(".kcetitle").html();
        }
        if ( element.is( "[title]" ) ) {
          return element.attr( "title" );
        }
      }
 });

function showDialog(){
   return false;
}

$(document).on('shown','#manualmodal', function () {
	$("#manualframe").height($("#manualmodal .modal-body").height());
})

$(document).on("click",'a[rel="external"]',function(event){
	event.preventDefault();
	htmlelement='<div id="manualmodal" class="modal modal-lg" tabindex="-1" role="dialog">'
			+'<button type="button" class="close" data-dismiss="modal">×</button>'
			+'<div class="modal-body">'
			+'<iframe id="manualframe" name="manualfrale" src="'+$(this).attr('href')+'" frameborder="0" width="99.6%"></iframe>'
			+'</div>'
			+'</div>'
	$(htmlelement).modal({show:true})

//	//return false;
});
