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

/**
 * Manage the part of nebackup in network equipments.
 * 
 * @author Javier Samaniego García <jsamaniegog@gmail.com>
 */
class PluginNebackupNetworkEquipment extends CommonDBTM {

    static $rightname = 'networkequipment';

    /**
     * Get name of this type
     *
     * @return text name of this type by language of the user connected
     *
     * */
    static function getTypeName($nb = 0) {
        return __('Network Equipment', 'nebackup');
    }

    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

        $array_ret = array();
        if ($item->getID() > -1) {
            if (Session::haveRight("networking", READ)) {
                $array_ret[0] = self::createTabEntry('NEBackup');
            }
        }
        return $array_ret;
    }

    /**
     * Display the content of the tab
     *
     * @param object $item
     * @param integer $tabnum number of the tab to display
     * @param integer $withtemplate 1 if is a template form
     * @return boolean
     */
    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

        if ($item->getID() > -1) {
            $pmEntity = new PluginNebackupNetworkEquipment();
            $pmEntity->showForm($item);
        }
        return true;
    }

    /**
     * Devuelve el tag del fabricante. Consultar constante 
     * PluginNebackupConfig::SUPPORTED_MANUFACTURERS.
     * @global type $DB
     * @param int $networkequipmenttypes_id
     * @param int $manufacturers_id
     * @param array $config_data Si se le pasa este argumento se evita buscar en
     * la base de datos dicha información.
     * @return boolean|nebackup_tag Devuelve false si no existe el TAG de
     * ese fabricante y tipo.
     */
    public static function getManufacturerTag($networkequipmenttypes_id, $manufacturers_id, $config_data = null
    ) {
        if ($config_data == null) {
            $config_data = PluginNebackupConfig::getConfigData();
        }

        $manufacturer = "";
        $type = false;

        foreach ($config_data as $key => $value) {
            // si coincide el tipo
            if ($value['type'] == 'networkequipmenttype_id'
                and in_array($networkequipmenttypes_id, explode(",", $value['value']))
            ) {
                $type = true;
            }

            // si coincide el fabricante como uno de los soportados
            if (strstr($value['type'], "_manufacturers_id")
                and $manufacturers_id == $value['value']
            ) {
                $manufacturer = str_replace("_manufacturers_id", "", $value['type']);
            }
        }

        if ($type == true and $manufacturer != "") {
            return $manufacturer;
        } else {
            return false;
        }
    }

    /**
     * Display form for service configuration
     *
     * @param $items_id integer ID
     * @param $options array
     *
     * @return bool true if form is ok
     *
     * */
    function showForm(CommonGLPI $datos, $options = array()) {
        global $DB;

        // get the manufacturer tag, if no exists the networkequipment is not supported or configured
        $manufacturer = self::getManufacturerTag(
                $datos->fields['networkequipmenttypes_id'], $datos->fields['manufacturers_id']
        );

        // init table
        echo '<table class="tab_cadre_fixe" width="100%">';
        echo '<tr>';
        echo '<th>' . __('Backup', 'nebackup') . '</th>';
        echo '</tr>';
        echo '<tr class="tab_bg_1">';
        echo '<td>';

        if ($manufacturer === false) {
            echo '<b style="color:red;">' . __('No backup configured or supported for the manufacturer assigned to this asset, it currently only supports these: ', 'nebackup') . str_replace(",", ", ", PluginNebackupConfig::SUPPORTED_MANUFACTURERS) . '.</b>';
            echo '<br><br><b style="color:red;">' . __('Check that the selected type and manufacturer of this asset corresponds to the setted in the', 'nebackup') . " " . Html::link(__('NEBackup configuration', 'nebackup'), PluginNebackupConfig::getSearchURL()) . ', ' . __('type and manufacturer must be setted', 'nebackup') . '.</b>';
            echo '</td></tr></table>';
            return false;
        }

        $plugin = new Plugin();
        
        $use_fusioninventory = ($plugin->isActivated("fusioninventory") 
            and PluginNebackupConfig::getUseFusionInventory() == 1) ?
            true :
            false;
        
        // first check if we have a record and if type and manufacturer match
        $query = "SELECT nee.username, nee.password, nee.protocol, nee.server, e.name entity_name, ";
        $query .= "(SELECT REPLACE(type, '_manufacturers_id', '')";
        
        $query .= " FROM glpi_plugin_nebackup_configs";
        $query .= " WHERE type like '%_manufacturers_id' AND value = " . $datos->fields['manufacturers_id'] . ") as manufacturer ";
        $query .= "FROM glpi_plugin_nebackup_entities nee, glpi_entities e ";
        
        $query .= "WHERE nee.entities_id = e.id AND nee.entities_id = " . $datos->fields['entities_id'];
        
        if ($result = $DB->query($query)) {
            $result = $result->fetch_assoc();

            if (!$result) {
                echo '<b style="color:red;">' . __('No backup configured for this entity.', 'nebackup') . '</b>';
                return false;
            }
        } else {
            echo '<b style="color:red;">' . __('Database Error', 'nebackup') . '</b>';
            return false;
        }

        // form SNMP auth if FusionInventory is installed and actived
        if ($use_fusioninventory and $manufacturer !== 'hpprocurve') {
            $this->showFormSNMPAuth($datos);
            
//            $query = "SELECT pfc.username as fi_username, pfc.auth_passphrase as fi_password ";
//            $query .= "FROM glpi_networkequipments n, glpi_plugin_nebackup_networkequipments pnn,";
//            $query .= " glpi_plugin_fusioninventory_configsecurities pfc ";
//            $query .= "WHERE n.id = " . $datos->fields['id'] . " AND n.id = pnn.networkequipments_id";
//            $query .= " AND pnn.plugin_fusioninventory_configsecurities_id = pfc.id ";
//            if ($result_fi = $DB->query($query)) {
//                $result_fi = $result_fi->fetch_assoc();
//
//                if ($result_fi) {
//                    $result['username'] = $result_fi['fi_username'];
//                    $result['password'] = $result_fi['fi_password'];
//                }
//            }
        }

        if ($manufacturer === 'hpprocurve') {
            $this->showFormAuthTelnet($datos);
        }
        
        // local path to temporal file
        $tmp_file = GLPI_ROOT . "/files/_cache/nebackup_" . $datos->fields['name'] . ".tmp";

        // remove the temporal file if exists
        unlink($tmp_file);

        // check if server is online
        if (!PluginNebackupUtil::ping($result['server'])) {
            echo '<b style="color:red;">' . __('Server ', 'nebackup') . $result['server'] . __(' is not alive</b>', 'nebackup');
        
        } else {

            // get the file from tftp
            $remote_path = PluginNebackupConfig::getBackupPath(
                true, 
                $result['manufacturer'], 
                $result['entity_name']) . '/' . PluginNebackupBackup::escapeName($datos->fields['name']
            );
            
            $command_result = self::getFileFromServer(
                $result['server'], 
                $remote_path, 
                $result['protocol'],
                $result['username'],
                $result['password']
            );
            
            // inicio tabla copia de seguridad (botón para realizar copias de seguridad del switch)
            echo '<table>';
            echo '<tr><td colspan=2><h3>' . __("Backup", 'nebackup') . '</h3></td></tr>';
            echo '<tr><td>' . __('File: ', 'nebackup') . '</td>';

            if (!$command_result) {
                // move file to files directory
                $command = "mv " . $datos->fields['name'] . " " . $tmp_file;
                `$command`;

                // link to download the file
                if (file_exists($tmp_file)) {
                    echo '<td><i>';
                    echo Html::link(
                        $datos->fields['name'], PluginNebackupDownload::getFormURL() . "?name=" . PluginNebackupBackup::escapeName($datos->fields['name'])
                    );
                    echo '</i></td></tr>';

                } else {
                    echo '<td><b style="color:orange;">' . __('Install ', 'nebackup') 
                        . PluginNebackupConfig::getProtocols()[$result['protocol']] 
                        . __(' client on GLPI server to view the backup file', 'nebackup') 
                        . "</b></td></tr>";
                }
                
            } else {
                // todo: fix to test all protocols to control possible errors
                if (preg_match("/Transfer timed out/", $command_result)) {
                    echo '<td><b style="color:red;">' 
                        . __('Transfer timed out, check if your TFTP server is up.', 'nebackup') 
                        . '</b></td></tr>';
                    
                } elseif (preg_match("/No such file or directory/", $command_result) 
                    or preg_match("/Error code 2/", $command_result)) {
                    echo '<td><b style="color:red;">' 
                        . __('Backup file not found on the server.', 'nebackup') 
                        . '</b></td></tr>';
                    
                } else {
                    echo '<td><b style="color:red;">' . $command_result . '</b></td></tr>';
                }
                
                
            }
            
            // For: last run of cron task, need before for style color
            $cron = new CronTask();
            $cron->getFromDBbyName("PluginNebackupBackup", "nebackup");

            // datetime of last good backup
            echo '<tr><td>' . __('File Date: ', 'nebackup') . '</td><td>';
            $logs = new PluginNebackupLogs();
            if ($logs->getFromDB("WHERE networkequipments_id = " . $datos->fields['id'])) {

                $t1 = strtotime($cron->fields['lastrun']);
                $t2 = strtotime($logs->fields['datetime']);
                $warn = (strtotime($cron->fields['lastrun']) > strtotime($logs->fields['datetime'])) ? __("(Warning: File not copied at last run)", "nebackup") : "";

                echo "<i>" . $logs->fields['datetime'] . '</i> ' . $warn;
            }
            echo '</td></tr>';

            // last run of cron task
            echo '<tr><td>' . __('Last cron execution: ', 'nebackup') . '</td><td>';
            if ($cron->fields['lastrun'] != "") {
                echo '<i>' . $cron->fields['lastrun'] . '</i></td></tr>';
            } else {
                echo '<i>' . __('No cron date', 'nebackup') . '</i></td></tr>';
            }

            // if error
            echo '<tr><td>' . __('Error: ', 'nebackup') . '</td><td>';
            if ($logs->fields['error'] != '') {
                echo "<b style='color:red;'>" . $logs->fields['error'] . "</b>";
            } else {
                echo '<i>' . __("No error", "nebackup") . '</i>';
            }
            echo '</td></tr>';

            // server
            echo '<tr><td>' . __('Server: ', 'nebackup') . '</td><td>';
            echo $result['server'];
            echo '</td></tr>';

            // server path
            echo '<tr><td>' . __('Server path: ', 'nebackup') . '</td><td>';
            echo $remote_path;
            echo '</td></tr>';

            echo '</table>';

            echo "</td></tr><tr><td align=center>";
            echo $this->showFormBackup($datos, $manufacturer);
        }

        // finish table
        echo '</td></tr></table>';
    }

    /**
     * Return an array of network equipments configured to backup.
     * @global type $DB
     * @param nebackup_tag $manufacturer An supported network equipment. 
     * See: PluginNebackupConfig::SUPPORTED_MANUFACTURERS.
     * @param int $networkequipment_id ID of the networkequipment_id.
     */
    static function getNetworkEquipmentsToBackup($manufacturer, $networkequipment_id = null) {
        global $DB;

        $toreturn = array();

        foreach ($DB->request(self::getSqlNetworkEquipmentsToBakcup($manufacturer, $networkequipment_id)) as $data) {
            $toreturn[] = $data;
        }

        return $toreturn;
    }

    static function getSqlNetworkEquipmentsToBakcup($manufacturer, $networkequipment_id = null) {
        $use_fusioninventory = PluginNebackupConfig::getUseFusionInventory();
        
        $plugin = new Plugin();

        $sql = "SELECT n.id, n.name, ip.name as ip, nee.protocol, nee.server, "
            . "nee.username, nee.password, nee.community, "
            . "nee.telnet_password, nee.telnet_username, "
            . "e.name entitie_name ";
        if ($manufacturer !== 'hpprocurve' 
            and $plugin->isActivated("fusioninventory") 
            and $use_fusioninventory == 1
        ) {
            $sql .= ", pfc.community as fi_community, pfc.snmpversion ";
        }

        $sql .= "FROM glpi_manufacturers m, glpi_networkports np, ";
        $sql .= "glpi_networknames nn, glpi_ipaddresses ip, ";
        $sql .= "glpi_plugin_nebackup_entities nee, glpi_entities e, ";
        $sql .= "glpi_networkequipments n ";
        if ($manufacturer !== 'hpprocurve' 
            and $plugin->isActivated("fusioninventory") 
            and $use_fusioninventory == 1
        ) {
            $sql .= ", glpi_plugin_nebackup_networkequipments pnn, glpi_plugin_fusioninventory_configsecurities pfc ";
            
        } elseif ($manufacturer === 'hpprocurve') {
            $sql .= "LEFT JOIN glpi_plugin_nebackup_networkequipments pnn ON n.id = pnn.networkequipments_id ";
        }

        $sql .= "WHERE n.manufacturers_id = m.id AND m.id = (select value from glpi_plugin_nebackup_configs where type = '" . $manufacturer . "_manufacturers_id') ";

        // filtro por id
        $sql = (isset($networkequipment_id)) ? $sql . "AND n.id = " . $networkequipment_id . " " : $sql;

        // filtro por estado y tipo
        // null value alwais is done
        $config = new PluginNebackupConfig();
        $states_id = $config->getStatesId();
        if ($states_id === false or empty($states_id) or $states_id[0] == "") {
            $states_id = array("null", 0);
        } else {
            $states_id = array_merge(array("null", 0), $states_id);
        }
        
        // no null value for types
        $types_id = $config->getTypesId();
        if ($types_id === false or empty($types_id) or $types_id[0] == "") {
            $types_id = array();
        }
        
        $sql .= "AND np.itemtype = 'NetworkEquipment' AND n.id = np.items_id "
            . "AND np.instantiation_type = 'NetworkPortAggregate' ";
        $sql .= "AND nn.itemtype = 'NetworkPort' AND np.id = nn.items_id ";
        $sql .= "AND ip.itemtype = 'NetworkName' AND nn.id = ip.items_id ";

        $sql .= "AND n.states_id in (" . implode(",", $states_id) . ") ";
        $sql .= "AND n.networkequipmenttypes_id in (" . implode(",", $types_id) . ") ";
        $sql .= "AND n.entities_id = nee.entities_id AND nee.entities_id = e.id ";

        if ($manufacturer !== 'hpprocurve' 
            and $plugin->isActivated("fusioninventory") 
            and $use_fusioninventory == 1
        ) {
            $sql .= "AND n.id = pnn.networkequipments_id AND pnn.plugin_fusioninventory_configsecurities_id = pfc.id ";
        }

        $sql .= "GROUP BY n.name";
        
        return $sql;
    }
    
    /**
     * Show the form to save snmp authentication. Only if FusionInventory plugin
     * is actived.
     */
    private function showFormAuthTelnet(CommonGLPI $item) {
        global $CFG_GLPI,$DB;

        $sql = "SELECT telnet_username, telnet_password ";
        $sql .= "FROM glpi_plugin_nebackup_networkequipments ";
        $sql .= "WHERE networkequipments_id = " . $item->fields['id'];

        if ($result = $DB->query($sql)) {
            $result = $DB->fetch_assoc($result);
        }
        
        echo '<h3>' . __("Configuration of authentication", 'nebackup') . '</h3>';
        echo "<h4>"
            . __('Complete only if this equipment has individual configuration for telnet conections.', 'nebackup') 
            . "</h4>";

        echo "<form name='form' method='post' action='" . $CFG_GLPI['root_doc'] . "/plugins/nebackup/front/networkequipment.form.php" . "'>";

        echo "<table><tr>";
        echo "<td align='center'>" . __('Telnet username (only for HP Procurve):', 'nebackup') . "</td>";
        echo "<td>";
        echo Html::hidden('networkequipments_id', array('value' => $item->fields['id']));
        echo Html::input("telnet_username", array('value' => $result['telnet_username']));
        echo "</td>";
        echo "<td align='center'>" . __('Telnet password (only for HP Procurve):', 'nebackup') . "</td>";
        echo "<td>";
        echo str_replace('type="text"', 'type="password"', Html::input("telnet_password", array('value' => $result['telnet_password'])));
        echo "</td>";
        
        echo "</td></tr>";
        echo "<tr><td align='center' colspan='2'>";
        echo "<input type='submit' name='update' value=\"" . __('Update') . "\" class='submit' >";
        echo "</td></tr>";
        echo "</table>";

        Html::closeForm();
    }
    
    /**
     * Show the form to save snmp authentication. Only if FusionInventory plugin
     * is actived.
     */
    private function showFormSNMPAuth(CommonGLPI $item) {
        global $CFG_GLPI;

        echo '<h3>' . __("Configuration of SNMP authentication", 'nebackup') . '</h3>';

        echo "<form name='form' method='post' action='" . $CFG_GLPI['root_doc'] . "/plugins/nebackup/front/networkequipment.form.php" . "'>";

        $snmpauth = $this->getSNMPAuth($item->fields['id']);
        
        echo "<table><tr>";
        if (!$snmpauth) {
            echo "<tr>"
                . "<td colspan='2' align='center' style='color:orange;'><strong>" 
                . __('The backup will not be done unless you set up a credential.', 'nebackup') 
                . "</strong></td>"
                . "</tr>";
        }
        echo "<td align='center'>" . __('SNMP authentication (READ/WRITE community): ', 'nebackup') . "</td>";
        echo "<td align='center'>";
        echo Html::hidden('networkequipments_id', array('value' => $item->fields['id']));

        if (strstr(Plugin::getInfo('fusioninventory', 'version'), '0.90')) {
            PluginFusioninventoryConfigSecurity::auth_dropdown($snmpauth);
        } else {
            PluginFusioninventoryConfigSecurity::authDropdown($snmpauth);
        }
        
        echo "</td></tr>";
        echo "<tr><td align='center' colspan='2'>";
        echo "<input type='submit' name='update' value=\"" . __('Update') . "\" class='submit' >";
        echo "</td></tr>";
        echo "</table>";

        Html::closeForm();
    }

    /**
     * Show the form to backup the switch
     * is actived.
     */
    private function showFormBackup(CommonGLPI $item, $manufacturer) {
        global $CFG_GLPI;

        echo "<form name='form' method='post' action='" . $CFG_GLPI['root_doc'] . "/plugins/nebackup/front/networkequipment.form.php" . "'>";
        echo Html::hidden('networkequipments_id', array('value' => $item->fields['id']));
        echo Html::hidden('manufacturer', array('value' => $manufacturer));
        echo "<input type='submit' name='backup' value=\"" . __('Backup', 'nebackup') . "\" class='submit' >";
        Html::closeForm();
    }

    private function getSNMPAuth($networkequipments_id) {
        global $DB;

        $sql = "SELECT plugin_fusioninventory_configsecurities_id FROM glpi_plugin_nebackup_networkequipments ";
        $sql .= "WHERE networkequipments_id = $networkequipments_id ";

        if ($result = $DB->query($sql)) {
            $result = $DB->fetch_assoc($result);
            return $result['plugin_fusioninventory_configsecurities_id'];
        }
    }

    private function existsRecord($networkequipments_id) {
        global $DB;
        
        $sql = "SELECT count(*) cuenta FROM glpi_plugin_nebackup_networkequipments ";
        $sql .= "WHERE networkequipments_id = $networkequipments_id ";

        if ($result = $DB->query($sql)) {
            $result = $DB->fetch_assoc($result);
            if ($result['cuenta'] != 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Set SNMP authentication. Only if FusionInventory plugin is actived.
     * @param type $plugin_fusioninventory_configsecurities_id
     */
    public function setSNMPAuth($networkequipments_id, $plugin_fusioninventory_configsecurities_id) {
        global $DB;

        if ($plugin_fusioninventory_configsecurities_id == 0) {
            $plugin_fusioninventory_configsecurities_id = 'NULL';
        }
        
        if ($this->existsRecord($networkequipments_id)) {
            $sql = "UPDATE glpi_plugin_nebackup_networkequipments ";
            $sql .= "SET plugin_fusioninventory_configsecurities_id = $plugin_fusioninventory_configsecurities_id ";
            $sql .= "WHERE networkequipments_id = $networkequipments_id";
        } else {
            $sql = "INSERT INTO glpi_plugin_nebackup_networkequipments(networkequipments_id, plugin_fusioninventory_configsecurities_id) ";
            $sql .= "VALUES($networkequipments_id, $plugin_fusioninventory_configsecurities_id)";
        }
        
        return $DB->query($sql);
    }

    /**
     * Set massive SNMP authentication.
     * @param array $ids All ids to update
     * @param int $plugin_fusioninventory_configsecurities_id ID of fusioninventory auth
     */
    static private function setSNMPAuthMassive($ma, $item, $ids, $plugin_fusioninventory_configsecurities_id) {
        $pnne = new PluginNebackupNetworkEquipment();

        foreach ($ids as $id) {
            try {
                if (!$pnne->setSNMPAuth($id, $plugin_fusioninventory_configsecurities_id)) {
                    $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                }
            } catch (Exception $e) {
                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
            }

            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
        }
    }
    
    /**
     * Sets authentication for one network equipment.
     * @param type $networkequipments_id
     * @param type $username
     * @param type $password
     * @param type $telnet_username
     * @param type $telnet_password
     */
    public function setAuthTelnet($networkequipments_id, $telnet_username, 
        $telnet_password
    ) {
        global $DB;
        
        if ($this->existsRecord($networkequipments_id)) {
            $sql = "UPDATE glpi_plugin_nebackup_networkequipments ";
            $sql .= "SET telnet_username = '" . $telnet_username . "', ";
            $sql .= "telnet_password = '" . $telnet_password . "' ";
            $sql .= "WHERE networkequipments_id = " . $networkequipments_id;
            
        } else {
            $sql = "INSERT INTO glpi_plugin_nebackup_networkequipments";
            $sql .= "(networkequipments_id, telnet_username, telnet_password) ";
            $sql .= "VALUES($networkequipments_id, '$telnet_username', '$telnet_password')";
        }
        
        return $DB->query($sql);
    }

    /**
     * Return user an password for telnet.
     * @global type $DB
     * @param type $networkequipments_id
     * @return boolean|array Associative array or false.
     */
    static public function getAuthTelnet($networkequipments_id) {
        global $DB;
        
        $sql = "SELECT telnet_username, telnet_password ";
        $sql .= "FROM glpi_plugin_nebackup_networkequipments ";
        $sql .= "WHERE networkequipments_id = " . $networkequipments_id;
            
        if ($result = $DB->query($sql)) {
            return $DB->fetch_assoc($result);
        }
        
        return false;
    }
    
    /**
     * Display form related to the massive action selected
     *
     * @param object $ma MassiveAction instance
     * @return boolean
     */
    static function showMassiveActionsSubForm(MassiveAction $ma) {
        $plugin = new Plugin();

        if ($ma->getAction() == 'assignAuth') {
            if (!$plugin->isActivated("fusioninventory")
                and PluginNebackupConfig::getUseFusionInventory() != 1) {

                echo __("You must activate the option", "nebackup") . " '" . __('Use FusionInventory SNMP authentication: ', 'nebackup') . "'";
            }
            if (strstr(Plugin::getInfo('fusioninventory', 'version'), '0.90')) {
                PluginFusioninventoryConfigSecurity::auth_dropdown();
            } else {
                PluginFusioninventoryConfigSecurity::authDropdown();
            }
            echo Html::submit(_x('button', 'Post'), array('name' => 'massiveaction'));
        }

        if ($ma->getAction() == 'backup') {
            echo Html::submit(_x('button', 'Post'), array('name' => 'massiveaction'));
        }

        return true;
    }

    /**
     * @since version 0.85
     *
     * @see CommonDBTM::processMassiveActionsForOneItemtype()
     * */
    static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids) {

        $itemtype = $item->getType();
        switch ($itemtype) {
            case 'NetworkEquipment':
                switch ($ma->getAction()) {
                    case "assignAuth":
                        self::setSNMPAuthMassive($ma, $item, $ids, $_POST['plugin_fusioninventory_configsecurities_id']);
                        break;

                    case "backup":
                        PluginNebackupBackup::backupNetworkEquipmentMassive($ma, $item, $ids);
                        break;

                    default: break;
                }

            default: break;
        }
    }

    /**
     * Exec local command to get the file from server.
     * @param string $remote_path
     * @param int $protocol 
     * #1. tftp
     * #2. ftp
     * #3. rcp
     * #4. scp
     * #5. sftp
     */
    public static function getFileFromServer($server, $remote_path, $protocol, $username = "admin", $password = "admin") {
        switch ($protocol) {
            // tftp
            case 1: 
                $command = 'tftp ' . $server . ' -c get "' . $remote_path . '"';
                break;
            
            case 2: 
                $file = substr($remote_path, strrpos($remote_path, "/") + 1);
                
                $command = 'ftp -n ' . $server . ' << EOF';
                $command .= "\nuser " . $username . ' ' . $password . '';
                $command .= "\ncd " . str_replace("/$file", "", $remote_path) . '';
                $command .= "\nget " . $file . '';
                $command .= "\nEOF";
                break;
            
            case 3: 
                $command = 'rcp ' . $username . '@' . $server . ':' . $remote_path . ' ./ 2>&1';
                break;
                
            case 4: 
                $command = 'scp ' . $username . '@' . $server . ':' . $remote_path . ' ./ 2>&1';
                break;
                
            case 5: 
                $command = 'sftp -q ' . $username . '@' . $server . ':' . $remote_path . ' ./ 2>&1';
                break;
                
            default: throw new Exception(__("Unknown protocol", "nebackup"));
        }
        
        $command_result = `$command`;
        
        // hack for ftp result. todo: fixit
        if (strstr($command_result, "WARNING")) {
            $command_result = null;
        }
        
        return $command_result;
    }

}
