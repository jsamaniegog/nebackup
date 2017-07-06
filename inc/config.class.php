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

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginNebackupConfig extends CommonDBTM {

    /**
     * SUPPORTED MANUFACTURERS by the plugin
     */
    const SUPPORTED_MANUFACTURERS = "cisco,hpprocurve";

    /**
     * Time that the scripts wait
     */
    const SECONDS_TO_WAIT_FOR_FINISH = 2;

    /**
     * Timeout to copy a network equipment by default
     */
    const DEFAULT_SECONDS_TO_TIMEOUT = 60;

    /**
     * Default tftp port 
     */
    const DEFAULT_PORT = 69;

    /**
     * For debug
     */
    const DEBUG_NEBACKUP = false;

    static function getTypeName($nb = 0) {
        return __("NEBackup", "nebackup");
    }

    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        if (!$withtemplate) {
            if ($item->getType() == 'Config') {
                return __('NEBackup plugin', 'nebackup');
            }
        }
        return '';
    }

    static function configUpdate($input) {
        $input['configuration'] = 1 - $input['configuration'];
        return $input;
    }

    function setCronTask($time_in_seconds = null) {

        $time_in_seconds = ($time_in_seconds == null) ? "86400" : $time_in_seconds;

        $res = CronTask::Register(
                "PluginNebackupBackup", "nebackup", $time_in_seconds, array(
                'comment' => __('Backup of network equipments configuration', 'nebackup'),
                'mode' => CronTask::MODE_EXTERNAL
                )
        );

        // si ya existe
        if ($res == false) {
            $cron = new CronTask();
            if ($cron->getFromDBbyName("PluginNebackupBackup", "nebackup")) {
                $cron->fields['frequency'] = $time_in_seconds;
                $cron->update($cron->fields);
            }
        }
    }

    function showFormNebackup() {
        global $CFG_GLPI;
        if (!Session::haveRight("config", UPDATE)) {
            return false;
        }

        echo "<form name='form' action=\"" . Toolbox::getItemTypeFormURL('PluginNebackupConfig') . "\" method='post'>";
        echo "<div class='center' id='tabsbody'>";
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr><th colspan='4'>" . __('NEBackup Setup', 'nebackup') . "</th></tr>";
        echo "<tr class='tab_bg_2'>";
        echo "<td>" . __('Backup interval: ', 'nebackup') . "</td>";
        echo "<td colspan='3'>";
        $cron = new CronTask();
        $cron->dropdownFrequency('backup_interval', self::getBackupInterval());
        echo "</td></tr>";
        echo "<tr><td>" . __('Root path in TFTP server: ', 'nebackup');
        echo "<br>" . __('(Default: "backup/{entity}")', 'nebackup');
        echo "<br>" . __('(Tags: "{entity}": the name of the entity, "{manufacturer}": manufacturer tag like cisco, hpprocurve, etc.)', 'nebackup') . "</td>";
        echo "<td>" . HTML::input('backup_path', array('value' => $this->getBackupPath())) . "</td></tr>";

        echo "<tr><td>" . __('Timeout to backup a network equipment (in seconds): ', 'nebackup');
        echo "<td>" . HTML::input('timeout', array('value' => $this->getTimeout())) . "</td></tr>";

        $plugin = new Plugin();
        if ($plugin->isActivated("fusioninventory")) {
            echo "<tr class='tab_bg_2'><td>" . __('Use FusionInventory SNMP authentication: ', 'nebackup') . "</td>";
            echo "<td>";
            Dropdown::showYesNo("use_fusioninventory", self::getUseFusionInventory());
            echo "</td></tr>";
        }

        echo "<tr class='tab_bg_2'>";
        echo "<td>" . __('Select type to switch backup: ', 'nebackup') . "</td>";
        echo "<td colspan='3'>";
        Dropdown::show(
            "networkequipmenttype", array(
            'name' => 'networkequipmenttype_id',
            'value' => $this->getNetworkEquipmentTypeId()
        ));
        echo "</td></tr>";
        
        echo "<tr class='tab_bg_2'>";
        echo "<td>" . __('Select the different states to backup (empty value "', 'nebackup') . Dropdown::EMPTY_VALUE . __('" is alwais backed up): ', 'nebackup') . "</td>";
        echo "<td colspan='3'>";
        $state = new State();
        $states = $state->find();
        foreach ($states as $key => $state) {
            $states[$key] = $state['name'];
        }
        Dropdown::showFromArray(
            'states_id', $states, array(
            'values' => $this->getStatesId(),
            'multiple' => true
            )
        );
        echo "</td></tr>";
        
        echo "<tr><td colspan=4><br><h3>" . __('Manufacturers', 'nebackup') . "</h2><td><tr>";
        foreach (PluginNebackupConfig::getSupportedManufacturerArray() as $i => $v) {
            echo "<tr class='tab_bg_2'>";
            echo "<td>" . __('Select manufacturer for ', "nebackup") . "'$v'" . __(' network equipments: ', 'nebackup') . "</td>";
            echo "<td colspan='3'>";
            /* array of possible options:
             * - name : string / name of the select (default is depending itemtype)     
             * - value : integer / preselected value (default -1)     
             * - comments : boolean / is the comments displayed near the dropdown (default true)     
             * - toadd : array / array of specific values to add at the begining     
             * - entity : integer or array / restrict to a defined entity or array of entities (default -1 : no restriction)     
             * - entity_sons : boolean / if entity restrict specified auto select its sons only available if entity is a single value not an array (default false)     
             * - toupdate : array / Update a specific item on select change on dropdown (need value_fieldname, to_update, url (see Ajax::updateItemOnSelectEvent for information) and may have moreparams)     
             * - used : array / Already used items ID: not to display in dropdown (default empty)     
             * - on_change : string / value to transmit to "onChange"     
             * - rand : integer / already computed rand value     
             * - condition : string / aditional SQL condition to limit display     
             * - displaywith : array / array of field to display with request     
             * - emptylabel : Empty choice's label (default self::EMPTY_VALUE)     
             * - display_emptychoice : Display emptychoice ? (default true)     
             * - display : boolean / display or get string (default true)     
             * - width : specific width needed (default auto adaptive)     
             * - permit_select_parent : boolean / for tree dropdown permit to see parent items not available by default (default false)     
             * - specific_tags : array of HTML5 tags to add the the field */
            Dropdown::show(
                "manufacturer", array(
                'name' => $v . '_manufacturers_id',
                'value' => $this->getManufacturerId($v)
            ));
            echo "</td></tr>";
        }
        echo "<tr class='tab_bg_2'>";
        echo "<td colspan='4' class='center'>";
        echo "<input type='submit' name='update' class='submit' value=\"" . _sx('button', 'Save') . "\">";
        echo "</td></tr>";
        echo '<tr><td colspan="2" class="center b"><br>';
        echo __('To activate the backup go to the entity configuration and select the NEBackup tab, click here to go: ', 'nebackup') . Html::link(__('Entities', 'nebackup'), '/glpi/front/entity.php');
        echo "</td></tr>";
        echo "</table></div>";
        Html::closeForm();
    }

    /**
     * Return array of supported manufacturers.
     * @return type
     */
    static function getSupportedManufacturerArray() {
        return explode(",", self::SUPPORTED_MANUFACTURERS);
    }

    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        if ($item->getType() == 'Config') {
            $config = new self();
            $config->showFormNebackup();
        }
    }

    /**
     * Return id of network equipment type id.
     * @global type $DB
     * @param type $manufacturer For example: cisco, hp...
     * @return boolean|int Return id of manufacturer. If don't exist return false.
     */
    public function getNetworkEquipmentTypeId() {
        global $DB;

        $query = "SELECT value FROM `glpi_plugin_nebackup_configs` WHERE type = 'networkequipmenttype_id'";

        if ($result = $DB->query($query)) {
            $row = $result->fetch_assoc(); // cogemos el primero
            return $row['value'];
        }

        return false;
    }

    /**
     * Set network equipment type.
     * @global type $DB
     * @param int $networkequipmenttype_id ID of networkequipment type
     * @return boolean
     */
    public function setNetworkEquipmentTypeId($networkequipmenttype_id) {
        global $DB;

        $query = "UPDATE `glpi_plugin_nebackup_configs` ";
        $query .= "SET value = '" . $networkequipmenttype_id . "' ";
        $query .= "WHERE type = 'networkequipmenttype_id'";

        return $DB->query($query);
    }

    /**
     * Set backup path
     * @param type $backup_path Path without initial slash "/"
     */
    public function setBackupPath($backup_path) {
        global $DB;

        $backup_path = str_replace("&lt;", "", $backup_path);
        $backup_path = str_replace("&gt;", "", $backup_path);
        $backup_path = preg_replace("/:|\*|\?|\\\\'|\\\\\"|\|/", "", $backup_path); // caracteres prohibidos
        $backup_path = preg_replace("/\\\\/", "/", $backup_path); // eliminamos backslash
        $backup_path = preg_replace("/\/{2,}/", "/", $backup_path); // dos o más slash seguidos
        $backup_path = preg_replace("/^\/|\/$/", "", $backup_path); // la cadena empieza o acaba por slash

        $query = "UPDATE `glpi_plugin_nebackup_configs` ";
        $query .= "SET value = '" . $DB->escape($backup_path) . "' ";
        $query .= "WHERE type = 'backup_path'";

        return $DB->query($query);
    }

    /**
     * Sets the states to backup
     * @global type $DB
     * @param array $states_id Array of IDs of states.
     * @return type
     */
    public function setStatesId($states_id) {
        global $DB;

        $query = "UPDATE `glpi_plugin_nebackup_configs` ";
        $query .= "SET value = '" . implode(",", $states_id) . "' ";
        $query .= "WHERE type = 'states_id'";

        return $DB->query($query);
    }
    
    /**
     * Return id of manufacturer.
     * @global type $DB
     * @param type $manufacturer For example: cisco, hp...
     * @return boolean|int Return id of manufacturer. If don't exist return false.
     */
    public function getManufacturerId($manufacturer) {
        global $DB;

        $query = "SELECT value FROM `glpi_plugin_nebackup_configs` WHERE type = '" . $manufacturer . "_manufacturers_id'";

        if ($result = $DB->query($query)) {
            $row = $result->fetch_assoc(); // cogemos el primero
            return $row['value'];
        }

        return false;
    }
    
    /**
     * Return id of the selected states (status field of GLPI).
     * @global type $DB
     * @return boolean|array Return an array of id's with the states. If don't exist return false.
     */
    public function getStatesId() {
        global $DB;

        $query = "SELECT value FROM `glpi_plugin_nebackup_configs` WHERE type = 'states_id'";

        if ($result = $DB->query($query)) {
            $row = $result->fetch_assoc(); // cogemos el primero
            return explode(",", $row['value']);
        }

        return false;
    }

    /**
     * Return bool.
     * @global type $DB
     * @param type $manufacturer For example: cisco, hp...
     * @return boolean 
     */
    static public function getUseFusionInventory() {
        global $DB;

        $query = "SELECT value FROM `glpi_plugin_nebackup_configs` WHERE type = 'use_fusioninventory'";

        if ($result = $DB->query($query)) {
            $row = $result->fetch_assoc(); // cogemos el primero
            return ($row) ? $row['value'] : 0;
        }

        return false;
    }

    /**
     * If use or not fusioninventory plugin.
     * @param bool $use_fusioninventory
     */
    public function setUseFusionInventory($use_fusioninventory) {
        global $DB;

        $query = "UPDATE `glpi_plugin_nebackup_configs` ";
        $query .= "SET value = '" . $use_fusioninventory . "' ";
        $query .= "WHERE type = 'use_fusioninventory'";

        return $DB->query($query);
    }

    /**
     * Set manufacturer.
     * @global type $DB
     * @param type $manufacturer For example: cisco, hp...
     * @param int $manufacturer_id ID of the manufacturer
     * @return boolean|int Return id of manufacturer. If don't exist return false.
     */
    public function setManufacturerId($manufacturer, $manufacturer_id) {
        global $DB;

        $query = "SELECT id FROM glpi_plugin_nebackup_configs WHERE type = '" . $manufacturer . "_manufacturers_id'";
        if ($result = $DB->query($query)) {
            if ($DB->fetch_assoc($result)) {
                $query = "UPDATE `glpi_plugin_nebackup_configs` ";
                $query .= "SET value = '" . $manufacturer_id . "' ";
                $query .= "WHERE type = '" . $manufacturer . "_manufacturers_id'";
            } else {
                $query = "INSERT INTO glpi_plugin_nebackup_configs(type, value) ";
                $query .= "VALUES('" . $manufacturer . "_manufacturers_id', $manufacturer_id)";
            }
        }

        return $DB->query($query);
    }

    /**
     * Return id of manufacturer.
     * @global type $DB
     * @param type $manufacturer For example: cisco, hp...
     * @return boolean|int Return the frequency in seconds. If don't exist return false.
     */
    static public function getBackupInterval() {
        $cron = new CronTask();
        if ($cron->getFromDBbyName("PluginNebackupBackup", "nebackup")) {
            return $cron->getField("frequency");
        }

        return false;
    }

    /**
     * Return backup path.
     * @global type $DB
     * @param boolean $format return configured path if false, else is formatted 
     * with $manufacturer and $entity params
     * @param String $manufacturer
     * @param String $entity
     * @return boolean|int Return id of manufacturer. If don't exist return false.
     */
    static public function getBackupPath($format = false, $manufacturer = null, $entity = null) {
        global $DB;

        $query = "SELECT value FROM `glpi_plugin_nebackup_configs` WHERE type = 'backup_path'";

        if ($result = $DB->query($query)) {
            $row = $result->fetch_assoc(); // cogemos el primero

            if ($format and $manufacturer and $entity) {
                $row['value'] = str_replace("{manufacturer}", $manufacturer, $row['value']);
                $row['value'] = str_replace("{entity}", $entity, $row['value']);
            }

            return $row['value'];
        }

        return false;
    }

    /**
     * Return timeout.
     * @global type $DB
     * @return boolean|int Return timeout. If it is not found in database return 
     * DEFAULT_SECONDS_TO_TIMEOUT constant
     */
    static public function getTimeout() {
        global $DB;

        $query = "SELECT value FROM `glpi_plugin_nebackup_configs` WHERE type = 'timeout'";

        if ($result = $DB->query($query)) {
            $row = $result->fetch_assoc(); // cogemos el primero
            return ($row['value'] == "") ? self::DEFAULT_SECONDS_TO_TIMEOUT : $row['value'];
        }

        return false;
    }

    /**
     * Set manufacturer.
     * @global type $DB
     * @param type $manufacturer For example: cisco, hp...
     * @param int $manufacturer_id ID of the manufacturer
     * @return boolean|int Return id of manufacturer. If don't exist return false.
     */
    public function setTimeout($timeout) {
        global $DB;

        if (!is_numeric($timeout) or $timeout < 5) {
            $timeout = self::DEFAULT_SECONDS_TO_TIMEOUT;
        }

        $query = "SELECT id FROM glpi_plugin_nebackup_configs WHERE type = 'timeout'";
        if ($result = $DB->query($query)) {
            if ($DB->fetch_assoc($result)) {
                $query = "UPDATE `glpi_plugin_nebackup_configs` ";
                $query .= "SET value = '" . $timeout . "' ";
                $query .= "WHERE type = 'timeout'";
            } else {
                $query = "INSERT INTO glpi_plugin_nebackup_configs(type, value) ";
                $query .= "VALUES('timeout', $timeout)";
            }
        }

        return $DB->query($query);
    }

    /**
     * Returns an array with the config.
     * @global type $DB
     */
    public static function getConfigData() {
        global $DB;
        $config = new PluginNebackupConfig();
        $config_data = array_values($config->find());
        return $config_data;
    }

}

?>