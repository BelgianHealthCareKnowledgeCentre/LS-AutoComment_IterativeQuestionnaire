//$( document ).tooltip(
//    {
//        items: "[data-kcetitle], [title]",
//        tooltipClass: "kce-tooltip",
//        content: function() {
//        var element = $( this );
//        if ( element.is( "[data-kcetitle]" ) ) {
//            return $(element).prev(".kcetitle").html();
//        }
//        if ( element.is( "[title]" ) ) {
//            return element.attr( "title" );
//        }
//      }
// });

function showDialog(){
    return false;
}

$(document).on('shown','#manualmodal', function () {
	$("#manualframe").height($("#manualmodal .modal-body").height());
})
$(document).on("keyup change",'.delphiinput',function(){
    $(".delphi-link").addClass("to-confirm");
});
$(document).on("click",'.delphi-link.to-confirm',function(){
    if(confirm("Are you sure? Because you update some settings"))
        return true;
    return false;
});
//$(document).on('[id=^plugin]',"keyup",function(){
//    console.log("toto");
//    $("#plugin[kceDelphi][launch]").remove();
//});
$(document).on("click",'a[rel="external"]',function(event){
    event.preventDefault();
    htmlelement='<div id="manualmodal" class="modal modal-lg" tabindex="-1" role="dialog">'
            +'<button type="button" class="close" data-dismiss="modal">×</button>'
            +'<div class="modal-body">'
            +'<iframe id="manualframe" name="manualfrale" src="'+$(this).attr('href')+'" frameborder="0" width="99.6%"></iframe>'
            +'</div>'
            +'</div>'
    $(htmlelement).modal({show:true})
});

// Tooltip click
$(document).on("click", "[data-kcetitle]", function() {
    $(this).tooltip(
        { 
            items: "[data-kcetitle]",
            tooltipClass: "kce-tooltip",
            content: function(){
                return $(this).prev(".kcetitle").html();
            }, 
            close: function( event, ui ) {
                var me = this;
                ui.tooltip.hover(
                    function () {
                        $(this).stop(true).fadeTo(400, 1); 
                    },
                    function () {
                        $(this).fadeOut("400", function(){
                            $(this).remove();
                        });
                    }
                );
                ui.tooltip.on("remove", function(){
                    $(me).tooltip("destroy");
                });
          },
        }
    );
    $(this).tooltip("open");
//    $(this).on("mouseleave", function (e) {
//        e.stopImmediatePropagation();
//    });
});
