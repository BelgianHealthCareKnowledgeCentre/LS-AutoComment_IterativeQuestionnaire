$(".kce-content").closest("[id^='question']").addClass("kce-question");
$(".kce-content").parent().addClass("kce-accordion");

$(".kce-question .question-wrapper").addClass("kce-block");
$(".kce-question .questiontext").addClass("kce-title");
$(".kce-block").addClass("ui-widget").addClass("ui-widget-content").addClass("ui-corner-all");
$(".kce-question .kce-accordion").hide();
$(".kce-question .kce-accordion").find("img:first").remove();
$(".kce-accordion" ).addClass("ui-widget-content");

$(".kce-title" ).addClass("ui-header").addClass("ui-widget-header").addClass('ui-state-default');
$(".kce-title" ).append('<span class="kce-button"><span class="ui-icon ui-icon-triangle-1-e"></span></span>');

//~ $(".kce-accordion > div" ).hide();
//~ $(".kce-accordion .kce-title" ).addClass('kce-button').addClass('ui-corner-top').addClass('ui-state-default').addClass('seemore');
//~ $(".kce-accordion .kce-title" ).append('<span class="ui-icon ui-icon-triangle-1-e"></span>');
$(document).on('click','.kce-title',function(){
    $(this).closest('.kce-block').find('.kce-accordion').slideToggle();
    $(this).find('.ui-icon').toggleClass('ui-icon-triangle-1-e','ui-icon-triangle-1-s');
    $(this).toggleClass('seemore','seeless');
    $(this).toggleClass('ui-state-default','ui-state-active');
});
