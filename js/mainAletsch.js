$('document').ready(function() {
    $("#aletsch_vaults").accordion({
        activate: function(event, ui) {
            var selected = ui.newHeader.attr("data-vaultarn");
            $("#aletsch_tabs").attr("data-actualarn", selected);
            
            $.ajax({
                url: OC.filePath('aletsch', 'ajax', 'vaultOps.php'),

                data: {
                    op: 'getJobsList',
                    vault: selected
                },

                type: "POST",

                success: function(result) {
                    var resultData = jQuery.parseJSON(result);

                    if(resultData.opResult === 'OK') {
                        $('#tabJobList').html(resultData.opData);
                    } else {
                        updateStatusBar(t('aletsch', 'Unable to get jobs list!'));
                    }
                },
                error: function( xhr, status ) {
                    updateStatusBar(t('aletsch', 'Unable to get jobs list! Ajax error!'));
                }
            });            
        }
    });
    
    $("#aletsch_tabs").tabs();
    
    $( "#btnRefrInventory" )
        .button()
        .click(function() {
            var actVault = $("#aletsch_tabs").attr("data-actualarn");
    
            $.ajax({
                url: OC.filePath('aletsch', 'ajax', 'vaultOps.php'),

                data: {
                    op: 'refreshInventory',
                    vault: actVault
                },

                type: "POST",

                success: function(result) {
                    var resultData = jQuery.parseJSON(result);

                    if(resultData.opResult === 'OK') {
                        updateStatusBar(t('aletsch', 'Job begun!'));
                    } else {
                        updateStatusBar(t('aletsch', 'Job not begun!'));
                    }
                },
                error: function( xhr, status ) {
                    updateStatusBar(t('aletsch', 'Job not begun! Ajax error!'));
                }
            });            
        });

});

function updateStatusBar(t) {
    $('#notification').html(t);
    $('#notification').slideDown();
    window.setTimeout(function(){
        $('#notification').slideUp();
    }, 5000);
}
