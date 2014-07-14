jQuery(function($) {
    var container = $('#aletsch_content');

    function relayout() {
        container.layout({resize: false});
    }
    relayout();

    $(window).resize(relayout);

    $('#navPane').resizable({
        handles: 'e',
        stop: relayout
    });
});