$(".kce-accordion > div" ).hide();
$(".kce-accordion .kce-title" ).addClass('kce-button').addClass('ui-corner-top').addClass('ui-state-default').addClass('seemore');
$(".kce-accordion .kce-title" ).append('<span class="ui-icon ui-icon-triangle-1-e"></span>');
$(document).on('click','.kce-title',function(){
    $(this).parent('.kce-accordion').children('div').slideToggle();
    $(this).find('.ui-icon').toggleClass('ui-icon-triangle-1-e','ui-icon-triangle-1-s');
    $(this).toggleClass('seemore','seeless');
    $(this).toggleClass('ui-state-default','ui-state-active');
});
