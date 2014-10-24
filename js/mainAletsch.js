$('document').ready(function() {
    $( "#aletsch_vaults" ).accordion({
        activate: function(event, ui) {
            var selected = ui.newHeader.attr("data-vaultarn");
            window.alert("Activated: " + selected);
        }
    });
});