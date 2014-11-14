$('document').ready(function() {
    var selectedArchives = [];
    var selectedArchiverFiles = [];

    refreshSpoolList();
    
    $("#archiverTree").fancytree({
        source: {
            url: OC.filePath('aletsch', 'ajax', 'getCloudFiles.php')
        },

        checkbox: true,
        selectMode: 3,
        extensions: ["table"],
        
        table: {
            indentation: 20,      // indent 20px per node level
            nodeColumnIdx: 1,     // render the node title into the 2nd column
            checkboxColumnIdx: 0  // render the checkboxes into the 1st column
        },
        
        renderColumns: function(event, data) {
            var node = data.node,
            tdList = $(node.tr).find(">td");
            tdList.eq(0).addClass('aletsch_resultTable_center');
            tdList.eq(1).addClass('aletsch_resultTable_left');
            tdList.eq(2).text(node.data.mime).addClass('aletsch_resultTable_center');
            tdList.eq(3).text(node.data.size).addClass('aletsch_resultTable_right');
        },
        
        select: function(event, data) {
            var selectedNodes = data.tree.getSelectedNodes();
            var filesNum = 0;
            var folderNum = 0;
            var totSize = 0;
            selectedArchiverFiles = [];
            
            for(i = 0; i < selectedNodes.length; i++) {
                if(selectedNodes[i].data.mime.indexOf('directory') === -1) {
                    filesNum++;
                    totSize += parseInt(selectedNodes[i].data.rawSize);
                    selectedArchiverFiles.push(selectedNodes[i].key);
                } else {
                    folderNum++;
                }
            }
            
            if(filesNum !== 0 || folderNum !== 0) {
                var selectionData = t('aletsch', 'Selected: ') + filesNum.toString() + t('aletsch', ' files, ') + folderNum.toString() + t('aletsch', ' folders. Total size: ') + HRFileSize(totSize);
                $("#aletsch_actualSelection").html(selectionData);
            } else {
                $("#aletsch_actualSelection").html('');
            }
        }
    });
    
    $("#btnSelectAll")
        .button()
        .click(function() {
            $("#archiverTree").fancytree("getTree").visit(function(node){
                node.setSelected(true);
            });
            
            return false;
        });

    $("#btnUnselectAll")
        .button()
        .click(function() {
            $("#archiverTree").fancytree("getTree").visit(function(node){
                node.setSelected(false);
            });

            return false;
        });

    $("#btnBuildArchive")
        .button()
        .click(function() {
            window.alert("Building archive");
        });

    $("#aletsch_vaults").accordion({
        active: false,
        collapsible: true,
        activate: function(event, ui) {
            var selected = ui.newHeader.attr("data-vaultarn");
            
            if(typeof(selected) === "undefined") {
                $("#inventoryContent").hide();
                $("#noInventory").show();

                $("#jobContent").hide();
                $("#noJobs").show();

                return;
            } else {
                $("#inventoryContent").show();
                $("#noInventory").hide();

                $("#jobContent").show();
                $("#noJobs").hide();
            }
            
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

    $("#tabJobList").on("click", ".getArchive", function() {
        var actVault = $("#aletsch_tabs").attr("data-actualarn");
        var jobID = $(this).data('jobid');
        var archiveID = $(this).data('archiveid');

        $.ajax({
            url: OC.filePath('aletsch', 'ajax', 'spoolOps.php'),

            data: {
                op: 'addDownloadOp',
                vaultARN: actVault,
                glacierJobID: jobID,
                archiveID: archiveID
            },

            type: "POST",

            success: function(result) {
                var resultData = jQuery.parseJSON(result);

                if(resultData.opResult === 'OK') {
                    updateStatusBar(t('aletsch', 'Download queued!'));
                } else {
                    updateStatusBar(t('aletsch', 'Download not queued!'));
                }
            },
            error: function( xhr, status ) {
                updateStatusBar(t('aletsch', 'Download not queued! Ajax error'));
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
    
    $("#btnDownloadArchive")
    .button()
    .click(function() {
        $(".archiveSelection").each(function() {
            if($(this).prop("checked")) {
                selectedArchives[selectedArchives.length] = $(this).data('archiveid');
            }
        });

        if(selectedArchives.length > 0) {
            downloadArchivesDlog.dialog("open");
        }
    });

    var downloadArchivesDlog = $("#aletsch_downloadArchivesDlog").dialog({
        autoOpen: false,
        resizable: false,
        height: 150,
        width: 350,
        modal: true,
        buttons: {
            Ok: function() {
                var actVaultARN = $("#aletsch_tabs").attr("data-actualarn");
                retrieveArchives(actVaultARN, selectedArchives);
                $(this).dialog("close");
            },
            Cancel: function() {
                $(this).dialog("close");
            }
        }
    });
    

    
    $("#spoolerContent").on("click", "#aletsch_selectAllSpoolJobs", function(eventData) {
        var selected = eventData.target.checked;
        
        $(".spoolJobSelection").each(function() {
            $(this).prop("checked", selected);
        });
    });
    
    $("#spoolerContent").on("change", ".vaultSelect", function(eventData) {
        $.ajax({
            url: OC.filePath('aletsch', 'ajax', 'spoolOps.php'),

            data: {
                op: 'changeJobAttribute',
                attr: 'vaultarn',
                vaultARN: $(this).val(),
                jobid: $(this).attr("data-jobid")
            },

            type: "POST",

            success: function(result) {
                var resultData = jQuery.parseJSON(result);

                if(resultData.opResult !== 'OK') {
                    updateStatusBar(t('aletsch', 'Unable to change!'));
                }
            },
            error: function( xhr, status ) {
                updateStatusBar(t('aletsch', 'Unable to change! Ajax error!'));
            }
        });
    });

    $("#btnReleaseJob")
        .button()
        .click(function() {
            var selectedJobs = getSelectedJobs();

            if(selectedJobs.length > 0) {
                $.ajax({
                    url: OC.filePath('aletsch', 'ajax', 'spoolOps.php'),

                    data: {
                        op: 'changeJobStatus',
                        status: 'waiting',
                        jobid: JSON.stringify(selectedJobs)
                    },

                    type: "POST",

                    success: function(result) {
                        var resultData = jQuery.parseJSON(result);

                        if(resultData.opResult !== 'OK') {
                            updateStatusBar(t('aletsch', 'Unable to change!'));
                        }
                        
                        refreshSpoolList();
                    },
                    error: function( xhr, status ) {
                        updateStatusBar(t('aletsch', 'Unable to change! Ajax error!'));
                    }
                });
            }
        });
    
    $("#btnResetStatus")
        .button()
        .click(function() {
            var selectedJobs = getSelectedJobs();
    
            if(selectedJobs.length > 0) {
                $.ajax({
                    url: OC.filePath('aletsch', 'ajax', 'spoolOps.php'),

                    data: {
                        op: 'resetJobStatus',
                        jobid: JSON.stringify(selectedJobs)
                    },

                    type: "POST",

                    success: function(result) {
                        var resultData = jQuery.parseJSON(result);

                        if(resultData.opResult !== 'OK') {
                            updateStatusBar(t('aletsch', 'Unable to reset!'));
                        }
                        
                        refreshSpoolList();
                    },
                    error: function( xhr, status ) {
                        updateStatusBar(t('aletsch', 'Unable to reset! Ajax error!'));
                    }
                });
            }
        });
    
    $("#btnDeleteJob")
        .button()
        .click(function() {
            var selectedJobs = getSelectedJobs();

            if(selectedJobs.length > 0) {
                $.ajax({
                    url: OC.filePath('aletsch', 'ajax', 'spoolOps.php'),

                    data: {
                        op: 'removeJob',
                        jobid: JSON.stringify(selectedJobs)
                    },

                    type: "POST",

                    success: function(result) {
                        var resultData = jQuery.parseJSON(result);

                        if(resultData.opResult !== 'OK') {
                            updateStatusBar(t('aletsch', 'Unable to remove!'));
                        }
                        
                        refreshSpoolList();
                    },
                    error: function( xhr, status ) {
                        updateStatusBar(t('aletsch', 'Unable to remove! Ajax error!'));
                    }
                });
            }
        });    
});

setInterval(function() {
    refreshSpoolList();
}, 60000);

function getSelectedJobs() {
    var selectedJobs = [];

    $(".spoolJobSelection").each(function() {
        if($(this).prop("checked")) {
            selectedJobs[selectedJobs.length] = $(this).data('spooljobid');
        }
    });
    
    return selectedJobs;
}

function refreshSpoolList() {
    $.ajax({
        url: OC.filePath('aletsch', 'ajax', 'spoolOps.php'),

        data: {
            op: 'getOps',
            ashtml: 1
        },

        type: "POST",

        success: function(result) {
            var resultData = jQuery.parseJSON(result);

            if(resultData.opResult === 'OK') {
                $('#spoolerContent').html(resultData.opData);
            } else {
                $('#spoolerContent').html(t('aletsch', 'Unable to get spooler content!'));
            }
        },
        error: function( xhr, status ) {
            $('#spoolerContent').html(t('aletsch', 'Unable to get spooler content! Ajax error'));
        }
    });        
}

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

function retrieveArchives(vaultARN, archivesID) {
    $.ajax({
        url: OC.filePath('aletsch', 'ajax', 'vaultOps.php'),

        data: {
            op: 'retrieveArchives',
            vault: vaultARN,
            archives: JSON.stringify(archivesID)
        },

        type: "POST",

        success: function(result) {
            var resultData = jQuery.parseJSON(result);

            if(resultData.opResult === 'OK') {
                updateStatusBar(t('aletsch', 'Archives retrieving queued!'));
            } else {
                updateStatusBar(t('aletsch', 'Unable to queue archives retrieval!'));
            }
        },
        error: function( xhr, status ) {
            updateStatusBar(t('aletsch', 'Unable to queue archives retrieval! Ajax error!'));
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

function HRFileSize(size) {
    var i = Math.floor(Math.log(size) / Math.log(1024));
    return (size / Math.pow(1024, i)).toFixed(2) * 1 + ' ' + ['B', 'kB', 'MB', 'GB', 'TB'][i];
}