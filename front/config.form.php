<?php
/* 
 * Copyright (C) 2016 Javier Samaniego García <jsamaniegog@gmail.com>
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

/**
 * Gestion du formulaire de configuration plugin nebackup
 * Reçoit les informations depuis un formulaire de configuration
 * Renvoie sur la page de l'item traité
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
    $config = new PluginNebackupConfig();

    // sets the backup interval in the automatic task
    if (is_numeric($_POST['backup_interval'])) {
        $config->setCronTask($_POST['backup_interval']);
        
    } else {
        Session::addMessageAfterRedirect(__("Backup interval must be a number.", 'nebackup'), false, ERROR);
    }
    
    // set the type of network equipment that we can backup (only switches)
    $config->setNetworkEquipmentTypeId($_POST['networkequipmenttype_id']);
    
    // save the manufacturer id for each suppoerted manufacturer
    foreach (explode(",", PluginNebackupConfig::SUPPORTED_MANUFACTURERS) as $v) {
        if (isset($_POST[$v . '_manufacturers_id']) and is_numeric($_POST[$v . '_manufacturers_id'])) {
            $config->setManufacturerId($v, $_POST[$v . '_manufacturers_id']);
        }
    }
    
} catch (Exception $e) {
    Session::addMessageAfterRedirect(__("Error on save", "nebackup"), false, ERROR);
    HTML::back();
}

Session::addMessageAfterRedirect(__("Configuration saved", "nebackup"), false, INFO);

HTML::back();