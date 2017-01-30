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

// Hook called on profile change
// Good place to evaluate the user right on this plugin
// And to save it in the session
function plugin_change_profile_nebackup() {
    // For example : same right of computer
    if (Session::haveRight('networkequipment', 'w')) {
        $_SESSION["glpi_plugin_nebackup_profile"] = array('nebackup' => 'w');
    } else if (Session::haveRight('networkequipment', 'r')) {
        $_SESSION["glpi_plugin_nebackup_profile"] = array('nebackup' => 'r');
    } else {
        unset($_SESSION["glpi_plugin_nebackup_profile"]);
    }
}

/**
 * Fonction d'installation du plugin
 * @return boolean
 */
function plugin_nebackup_install() {
    global $DB;

    // actualización si es la version 1.0.0
    if (TableExists("glpi_plugin_nebackup_config")) {
        $DB->runFile(GLPI_ROOT . "/plugins/nebackup/sql/update-1.0.0.sql");
    }

    // actualización version => 2.0.0
    if (TableExists("glpi_plugin_nebackup_configs")) {
        $DB->runFile(GLPI_ROOT . "/plugins/nebackup/sql/update-2.0.0.sql");
    }

    // actualización version => 2.1.0
    if (TableExists("glpi_plugin_nebackup_logs")) {
        $DB->runFile(GLPI_ROOT . "/plugins/nebackup/sql/update-2.1.0.sql");

        $n_template = new NotificationTemplate();
        if (!$n_template->find("name = 'NEBackup errors'")) {
            $n_template->add(array(
                'name' => 'NEBackup errors',
                'itemtype' => 'PluginNebackupBackup'
            ));
            $n_template = array_values($n_template->find("name = 'NEBackup Errors'"));
            $n_templatetranslations = new NotificationTemplateTranslation();
            $n_templatetranslations->add(array(
                'notificationtemplates_id' => $n_template[0]['id'],
                'subject' => PluginNebackupNotificationTargetBackup::getTemplateSubject("errors"),
                'content_text' => PluginNebackupNotificationTargetBackup::getTemplateContent("errors"),
                'content_html' => PluginNebackupNotificationTargetBackup::getTemplateContent("errors", true)
            ));
        }

        $notification = new Notification();
        if (!$notification->find("name = 'NEBackup errors'")) {
            $notification->add(array(
                'name' => 'NEBackup errors',
                'entities_id' => '0',
                'itemtype' => 'PluginNebackupBackup',
                'event' => 'errors',
                'mode' => 'mail',
                'notificationtemplates_id' => $n_template[0]['id'],
                'comment' => '',
                'is_recursive' => 1,
                'is_active' => 1
            ));
        }
    }

    // Création de la table uniquement lors de la première installation
    /* if (!TableExists("glpi_plugin_nebackup_profiles")) {

      // requête de création de la table
      $query = "CREATE TABLE `glpi_plugin_nebackup_profiles` (
      `id` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_profiles (id)',
      `right` char(1) collate utf8_unicode_ci default NULL,
      PRIMARY KEY  (`id`)
      ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

      $DB->query($query) or die($DB->error());

      //création du premier accès nécessaire lors de l'installation du plugin
      $id = $_SESSION['glpiactiveprofile']['id'];
      $query = "INSERT INTO glpi_plugin_nebackup_profiles VALUES ('$id','w')";
      $DB->query($query) or die($DB->error());
      } */

    if (!TableExists("glpi_plugin_nebackup_entities")) {
        // Création de la table config
        $query = "CREATE TABLE `glpi_plugin_nebackup_entities` (
        `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
        `entities_id` int(11) NOT NULL UNIQUE,
        `tftp_server` char(32) NOT NULL default '',
        `tftp_passwd` char(32) NOT NULL default '',
        `telnet_passwd` char(32) NOT NULL default '',
        `is_recursive` tinyint(1) NOT NULL default 0
        )ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
        $DB->query($query) or die($DB->error());
    }

    /* $query = "INSERT INTO glpi_plugin_nebackup_entities(entities_id, tftp_server, tftp_passwd, is_recursive) ";
      $query .= "VALUES (0, '".getHostByName(getHostName())."', '', 1)";
      $res = $DB->query($query) or die($DB->error()); */

    // Création de la table uniquement lors de la première installation
    if (!TableExists("glpi_plugin_nebackup_configs")) {
        // Création de la table config
        $query = "CREATE TABLE `glpi_plugin_nebackup_configs` (
        `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
        `type` varchar(32) NOT NULL default '' UNIQUE,
        `value` varchar(32) NOT NULL default ''
        )ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
        $DB->query($query) or die($DB->error());

        // ruta de configuración predeterminada para version >= 2.0.0
        $DB->runFile(GLPI_ROOT . "/plugins/nebackup/sql/update-2.0.0.sql");

        // Insertamos el ID de cisco en la configuración si es que existe algún registro.
        $query = "SELECT id FROM `glpi_manufacturers` WHERE name like 'cisco%' ORDER BY id LIMIT 1";
        $query2 = "SELECT id FROM `glpi_networkequipmenttypes` WHERE name like 'switch' ORDER BY id LIMIT 1";

        if ($result = $DB->query($query) and $result2 = $DB->query($query2)) {

            $row = $result->fetch_assoc();
            $row2 = $result2->fetch_assoc();

            $query = "INSERT INTO glpi_plugin_nebackup_configs(type, value) ";
            $query .= "VALUES ('cisco_manufacturers_id', '" . $row['id'] . "')";
            $query .= ", ('networkequipmenttype_id', '" . $row2['id'] . "')";
            $res = $DB->query($query) or die($DB->error());

            // add task for backup
            if ($res) {
                // need this include for version 0.90
                include_once 'inc/config.class.php';

                PluginNebackupConfig::setCronTask();
            }
        }
    } else {
        $cron = new CronTask();
        if ($cron->getFromDBbyName("PluginNebackupBackup", "nebackup")) {
            $cron->fields['mode'] = CronTask::MODE_EXTERNAL;
            $cron->update($cron->fields);
        }
    }

    return true;
}

/**
 * Fonction de désinstallation du plugin
 * @return boolean
 */
function plugin_nebackup_uninstall() {
    global $DB;

    //$tables = array("glpi_plugin_nebackup_profiles", "glpi_plugin_nebackup_configs", "glpi_plugin_nebackup_entities");
    $tables = array(
        "glpi_plugin_nebackup_configs",
        "glpi_plugin_nebackup_entities",
        "glpi_plugin_nebackup_networkequipments",
        "glpi_plugin_nebackup_logs"
    );

    foreach ($tables as $table) {
        $DB->query("DROP TABLE IF EXISTS `$table`;");
    }
    return true;
}

/**
 * Action when an item is purged.
 * @param type $params
 */
function plugin_item_purge_nebackup($params) {
    switch ($params::$rightname) {
        // delete the entity configuration and sub entities
        case 'entity':
            $config = new PluginNebackupEntity();
            $data = array_values($config->find("entities_id = " . $params->getID()));  // search plugin entities id
            $config->setEntityData(array(
                'id' => $data[0]['id'],
                'purge' => true
            ));
            break;
    }
}

/**
 * Add massive actions to GLPI itemtypes
 *
 * @param string $type
 * @return array
 */
function plugin_nebackup_MassiveActions($type) {
    $ma = array();

    switch ($type) {
        case "NetworkEquipment":
            if (Session::haveRight('config', UPDATE)) {
                $ma["PluginNebackupNetworkequipment" . MassiveAction::CLASS_ACTION_SEPARATOR . "assignAuth"] = __('NEBackup - SNMP auth (R/W)', 'nebackup');
                $ma["PluginNebackupNetworkequipment" . MassiveAction::CLASS_ACTION_SEPARATOR . "backup"] = __('NEBackup - Backup', 'nebackup');
            }

            break;
    }

    return $ma;
}

?>
