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
	function saveData() {
		var serverLocation = $("#aletsch_serverLocation").val();
		var username = $("#aletsch_username").val();
		var password = $("#aletsch_password").val();

		if(serverLocation !== '' && username !== '' && password) {
			$.ajax({
				url: OC.filePath('aletsch', 'ajax', 'savePersonalSettings.php'),
				async: false,
				timeout: 2000,

				data: {
					serverLocation: serverLocation,
					username: username,
					password: password
				},

				type: "POST",

				success: function( result ) {
					if(result !== 'OK') {
						window.alert(t('aletsch', 'Settings not saved! Data base error!'))
					}
				},

				error: function( xhr, status ) {
					window.alert(t('aletsch', 'Settings not saved! Communication error!'))
				}                            
			});
		}
	}

    $("#aletsch_serverLocation").change(function() {
		saveData();
    });

    $("#aletsch_username").focusout(function() {
		saveData();
    });

    $("#aletsch_password").focusout(function() {
		saveData();
    });
});