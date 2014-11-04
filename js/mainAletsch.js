$('document').ready(function() {
    var selectedArchives = [];

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
                        var outdated = (resultData.opData.outdated === true) ? t('aletsch', 'Outdated') : '';
                        $('#aletsch_inventoryDate').html(date);
                        $('#aletsch_inventoryOutdated').html(outdated);
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
                    var date = (resultData.opData.date === null) ? t('aletsch', 'Not available') : resultData.opData.date;
                    $('#aletsch_inventoryDate').html(date);
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
    
    $("#btnNewVault")
        .button()
        .click(function(){
            newVaultDlog.dialog("open");
        });

    var newVaultDlog = $("#aletsch_newVaultDlog").dialog({
        autoOpen: false,
        height: 150,
        width: 350,
        modal: true,
        buttons: {
            Ok: function() {
                var newVaultName = $("#aletsch_vaultName").val();
                createVault(newVaultName);
                newVaultDlog.dialog("close");
            },
            Cancel: function() {
                newVaultDlog.dialog("close");
            }
        },
        close: function() {
            $("#aletsch_vaultName").val("");
        }
    });
    
    $("#btnDeleteVault")
        .button()
        .click(function(){
            var delVaultName = $("#aletsch_tabs").attr("data-actualarn").split("/");
            $("#vaultNameToDelete").html(delVaultName[1]);
            delVaultDlog.dialog("open");
        });

    var delVaultDlog = $("#aletsch_deleteVaultDlog").dialog({
        autoOpen: false,
        resizable: false,
        height: 200,
        width: 350,
        modal: true,
        buttons: {
            Ok: function() {
                var delVaultName = $("#aletsch_tabs").attr("data-actualarn");
                $("#vaultNameToDelete").val("");
                deleteVault(delVaultName)
                $(this).dialog("close");
            },
            Cancel: function() {
                $(this).dialog("close");
            }
        }
    });
    
    $("#btnDeleteArchive")
    .button()
    .click(function() {
        $(".archiveSelection").each(function() {
            if($(this).prop("checked")) {
                selectedArchives[selectedArchives.length] = $(this).data('archiveid');
            }
        });

        if(selectedArchives.length > 0) {
            delArchivesDlog.dialog("open");
        }
    });
    
    var delArchivesDlog = $("#aletsch_deleteArchivesDlog").dialog({
        autoOpen: false,
        resizable: false,
        height: 150,
        width: 350,
        modal: true,
        buttons: {
            Ok: function() {
                var actVaultARN = $("#aletsch_tabs").attr("data-actualarn");
                removeArchives(actVaultARN, selectedArchives);
                $(this).dialog("close");
            },
            Cancel: function() {
                $(this).dialog("close");
            }
        }
    });
});

function createVault(vaultName) {
    $.ajax({
        url: OC.filePath('aletsch', 'ajax', 'vaultOps.php'),

        data: {
            op: 'createVault',
            newVaultName: vaultName
        },

        type: "POST",

        success: function(result) {
            var resultData = jQuery.parseJSON(result);

            if(resultData.opResult === 'OK') {
                updateStatusBar(t('aletsch', 'Vault created!'));
                location.reload(true);
            } else {
                updateStatusBar(t('aletsch', 'Unable to create vault!'));
            }
        },
        error: function( xhr, status ) {
            updateStatusBar(t('aletsch', 'Unable to create vault! Ajax error!'));
        }
    });
}

function deleteVault(vaultName) {
    $.ajax({
        url: OC.filePath('aletsch', 'ajax', 'vaultOps.php'),

        data: {
            op: 'deleteVault',
            vault: vaultName
        },

        type: "POST",

        success: function(result) {
            var resultData = jQuery.parseJSON(result);

            if(resultData.opResult === 'OK') {
                updateStatusBar(t('aletsch', 'Vault deleted!'));
                location.reload(true);
            } else {
                updateStatusBar(t('aletsch', 'Unable to delete vault!'));
            }
        },
        error: function( xhr, status ) {
            updateStatusBar(t('aletsch', 'Unable to delete vault! Ajax error!'));
        }
    });
}

function removeArchives(vaultARN, archivesID) {
    $.ajax({
        url: OC.filePath('aletsch', 'ajax', 'vaultOps.php'),

        data: {
            op: 'deleteArchives',
            vault: vaultARN,
            archives: JSON.stringify(archivesID)
        },

        type: "POST",

        success: function(result) {
            var resultData = jQuery.parseJSON(result);

            if(resultData.opResult === 'OK') {
                updateStatusBar(t('aletsch', 'Archives deleted!'));
            } else {
                updateStatusBar(t('aletsch', 'Unable to delete archives!'));
            }
        },
        error: function( xhr, status ) {
            updateStatusBar(t('aletsch', 'Unable to delete archives! Ajax error!'));
        }
    });
}

function updateStatusBar(t) {
    $('#notification').html(t);
    $('#notification').slideDown();
    window.setTimeout(function(){
        $('#notification').slideUp();
    }, 5000);
}
