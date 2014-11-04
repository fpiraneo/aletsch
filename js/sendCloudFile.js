/*
 * Copyright 2014 by Francesco PIRANEO G. (fpiraneo@gmail.com)
 * 
 * This file is part of aletsch.
 * 
 * aletsch is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * aletsch is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with aletsch.  If not, see <http://www.gnu.org/licenses/>.
 */

$(document).ready(function() {
    if(typeof FileActions !== 'undefined') {
        var infoIconPath = OC.imagePath('aletsch','upload.svg');

        FileActions.register('file', 'Send to glacier', OC.PERMISSION_UPDATE, infoIconPath, function(fileName) {
            // Action to perform when clicked
            if(scanFiles.scanning) { return; } // Workaround to prevent additional http request block scanning feedback

            var directory = $('#dir').val();
            directory = (directory === "/") ? directory : directory + "/";

            var filePath = directory + fileName;    

            $.ajax({
                url: OC.filePath('aletsch', 'ajax', 'spoolOps.php'),

                data: {
                    op: 'addUploadOp',
                    filePath: filePath
                },

                type: "POST",

                success: function(result) {
                    var resultData = jQuery.parseJSON(result);

                    if(resultData.opResult === 'OK') {
                        updateStatusBar(t('aletsch', 'Queued!'));
                    } else {
                        updateStatusBar(t('aletsch', 'File not queued!'));
                    }
                },
                error: function( xhr, status ) {
                    updateStatusBar(t('aletsch', 'File not queued! Ajax error'));
                }
            });
        });
    }
});

function updateStatusBar(t) {
    $('#notification').html(t);
    $('#notification').slideDown();
    window.setTimeout(function(){
        $('#notification').slideUp();
    }, 5000);
}
