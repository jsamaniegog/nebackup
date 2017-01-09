<?php

/* 
 * Copyright (C) 2017 Javier Samaniego García <jsamaniegog@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

global $DB, $CFG_GLPI;

include ("../../../inc/includes.php");

if (!Session::haveRight("config", UPDATE)) {
    Session::addMessageAfterRedirect("No permission", false, ERROR);
    HTML::back();
} else {
    echo __("Saving configuration...", 'nebackup');
}

try {
    $config = new PluginNebackupNetworkEquipment();

    $config->setSNMPAuth($_POST['networkequipments_id'], $_POST['plugin_fusioninventory_configsecurities_id']);
    
} catch (Exception $e) {
    Session::addMessageAfterRedirect(__("Error on save", "nebackup"), false, ERROR);
    HTML::back();
}

Session::addMessageAfterRedirect(__("Configuration saved", "nebackup"), false, INFO);

HTML::back();