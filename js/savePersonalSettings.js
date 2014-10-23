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

$(function() {
    $("#aletsch_saveCredentials")
            .button()
            .click(function(event) {
                event.preventDefault();
                saveData();
            });
	
    function saveData() {
        var serverLocation = $("#aletsch_serverLocation").val();
        var username = $("#aletsch_username").val();
        var password = $("#aletsch_password").val();		
        var credID = $("#aletsch_username").attr("data-credid");

        if(serverLocation !== '' && username !== '' && password !== '') {
            $.ajax({
                url: OC.filePath('aletsch', 'ajax', 'savePersonalSettings.php'),
                async: false,
                timeout: 2000,

                data: {
                    credid: credID,
                    serverLocation: serverLocation,
                    username: username,
                    password: password
                },

                type: "POST",

                success: function(result) {
                    if(result === 'OK') {
                        updateStatusBar(t('aletsch', 'Settings saved correctly.'));
                    } else {
                        updateStatusBar(t('aletsch', 'Settings not saved! Data base error!'));
                    }
                },

                error: function(xhr, status) {
                    updateStatusBar(t('aletsch', 'Settings not saved! Communication error!'));
                }
            });
        }
    }
    
    function updateStatusBar(t) {
        $('#notification').html(t);
        $('#notification').slideDown();
        window.setTimeout(function(){
            $('#notification').slideUp();
        }, 5000);
    }
});