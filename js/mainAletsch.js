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
            
            $.ajax({
                url: OC.filePath('aletsch', 'ajax', 'vaultOps.php'),

                data: {
                    op: 'getInventory',
                    vault: selected
                },

                type: "POST",

                success: function(result) {
                    var resultData = jQuery.parseJSON(result);

                    if(resultData.opResult === 'OK') {
                        var date = (resultData.opData.date === null) ? t('aletsch', 'Not available') : resultData.opData.date;
                        $('#aletsch_inventoryDate').html(date);
                        $('#aletsch_archives').html(resultData.opData.archiveList);
                    } else {
                        updateStatusBar(t('aletsch', 'Unable to get inventory!'));
                    }
                },
                error: function( xhr, status ) {
                    updateStatusBar(t('aletsch', 'Unable to get inventory! Ajax error!'));
                }
            });
        }
    });
    
    $("#aletsch_tabs").tabs();
    
    $("#btnRefrInventory")
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

    $("#tabJobList").on("click", ".getInventory", function() {
        var actVault = $("#aletsch_tabs").attr("data-actualarn");
        var jobID = $(this).data('jobid');

        $.ajax({
            url: OC.filePath('aletsch', 'ajax', 'vaultOps.php'),

            data: {
                op: 'getInventoryResult',
                vault: actVault,
                jobid: jobID
            },

            type: "POST",

            success: function(result) {
                var resultData = jQuery.parseJSON(result);

                if(resultData.opResult === 'OK') {
                    $('#aletsch_inventoryDate').html(resultData.opData.date);
                    $('#aletsch_archives').html(resultData.opData.archiveList);

                    updateStatusBar(t('aletsch', 'Got inventory!'));
                } else {
                    updateStatusBar(t('aletsch', 'Inventory not get!'));
                }
            },
            error: function( xhr, status ) {
                updateStatusBar(t('aletsch', 'Inventory not get! Ajax error!'));
            }
        });            
    });
    
    $("#aletsch_archives").on("click", "#aletsch_selectAllArchives", function(eventData) {
        var selected = eventData.target.checked;
        
        $(".archiveSelection").each(function() {
            $(this).prop("checked", selected);
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
