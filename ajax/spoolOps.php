<?php
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

OCP\JSON::checkAppEnabled('aletsch');
OCP\User::checkLoggedIn();

$OCUserName = \OCP\User::getUser();
$op = filter_input(INPUT_POST, 'op', FILTER_SANITIZE_STRING);

// Prepare result structure
$result = array(
    'opResult' => 'KO',
    'opData' => array(),
    'errData' => array(
        'exCode' => '',
        'exMessage' => ''
    )
);

// Check if operation has been set
if(!isset($op)) {
    $result = array(
        'opResult' => 'KO',
        'opData' => array(),
        'errData' => array(
            'exCode' => 'AletschParamError',
            'exMessage' => 'Operation code not set'
        )
    );

    \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'], 0);
    
    die($result);
}

//
// Action switcher
//
switch($op) {
    // Get spool contents for given user
    case 'getOps': {
        $spoolerHandler = new \OCA\aletsch\spoolerHandler($OCUserName);
        $spool = $spoolerHandler->getOperations();
        
        $result['opResult'] = 'OK';
        $result['opData'] = $spool;

        die(json_encode($result));
        
        break;
    }
    
    // Unrecognised operation fallback
    default: {
        $result['opResult'] = 'KO';
        $result['errData']['exCode'] = 'AletschParamError';
        $result['errData']['exMessage'] = 'Unrecognized operation';

        \OCP\Util::writeLog('aletsch', $result['errData']['exCode'] . ' - ' . $result['errData']['exMessage'] . ': ' . $op, 0);
    
        die(json_encode($result));

        break;
    }
}

