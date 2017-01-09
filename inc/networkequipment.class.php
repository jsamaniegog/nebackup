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
            if (Session::haveRight("config", READ)) {
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
     * Show this content into principal tab
     */
    //static function showInfo($datos) {
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

        $config = new PluginNebackupConfig();
        $config_data = array_values($config->find());

        $manufacturer = false;
        $type = false;

        foreach ($config_data as $key => $value) {
            // si coincide el tipo
            if ($value['type'] == 'networkequipmenttype_id'
                and $datos->fields['networkequipmenttypes_id'] == $value['value']) {
                $type = true;
            }

            // si coincide el fabricante como uno de los soportados
            if (strstr($value['type'], "_manufacturers_id") and $datos->fields['manufacturers_id'] == $value['value']) {
                $manufacturer = true;
            }
        }

        // init table
        echo '<table class="tab_cadre_fixe" width="100%">';
        echo '<tr>';
        echo '<th>' . __('Backup', 'nebackup') . '</th>';
        echo '</tr>';
        echo '<tr class="tab_bg_1">';
        echo '<td>';

        if ($manufacturer == false or $type == false) {
            echo '<b style="color:red;">' . __('No backup for this manufacturer, currently only support these: ', 'nebackup') . PluginNebackupConfig::SUPPORTED_MANUFACTURERS . '</b>';
            echo '</td></tr></table>';
            return false;
        }

        // first check if we have a record and if type and manufacturer match
        $query = "SELECT nee.tftp_server, e.name entity_name ";
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
        $plugin = new Plugin();
        if ($plugin->isActivated("fusioninventory")) {
            $this->showFormSNMPAuth($datos);
        }

        // local path to temporal file
        $tmp_file = GLPI_ROOT . "/files/_cache/nebackup.tmp";

        // remove the temporal file if exists
        unlink($tmp_file);

        // check if tftp server is online
        if (!PluginNebackupUtil::ping($result['tftp_server'])) {
            echo '<b style="color:red;">' . __('TFTP server ' . $result['tftp_server'] . ' is not alive</b>', 'nebackup');
        } else {

            // get the file from tftp
            $remote_path = PluginNebackupConfig::getBackupPath() . '/' . $result['entity_name'] . '/' . PluginNebackupBackup::escapeNameToTftp($datos->fields['name']);
            $command = 'tftp ' . $result['tftp_server'] . ' -c get "' . $remote_path . '"';
            $command_result = `$command`;

            if (!$command_result) {
                // move file to files directory
                $command = "mv " . $datos->fields['name'] . " " . GLPI_ROOT . "/files/_cache/nebackup.tmp";
                `$command`;

                if (file_exists($tmp_file)) {
                    echo '<tr><td><h3>' . __("Backup", 'nebackup') . '</h3>';

                    // link to download the file
                    echo __('File: ', 'nebackup');
                    echo '<i>';
                    echo Html::link(
                        $datos->fields['name'], PluginNebackupDownload::getFormURL() . "?name=" . PluginNebackupBackup::escapeNameToTftp($datos->fields['name'])
                    );
                    echo '</i>';

                    $cron = new CronTask();
                    $cron->getFromDBbyName("PluginNebackupBackup", "nebackup");
                    echo '<tr><td>' . __('Last run: ', 'nebackup') . '<i>' . $cron->fields['lastrun'] . '</i></td></tr>';
                } else {
                    echo '<b style="color:red;">' . __('Install TFTP client on server to view the backup file', 'nebackup') . "</b>";
                }
            } else {
                if (preg_match("/Transfer timed out/", $command_result)) {
                    echo '<b style="color:red;">' . __('Transfer timed out, check if your TFTP server is up.', 'nebackup') . '</b>';
                }

                if (preg_match("/Error code 2/", $command_result)) {
                    echo '<b style="color:red;">' . __('Backup file not found.', 'nebackup') . '</b>';
                }
            }
        }

        echo '</td>';
        echo '</tr>';
        echo '</table>';
    }

    /**
     * Return an array of network equipments configured to backup.
     * @global type $DB
     * @param type $type An supported network equipment. See: PluginNebackupConfig::SUPPORTED_MANUFACTURERS.
     */
    static function getNetworkEquipmentsToBackup($manufacturer) {
        global $DB;

        $plugin = new Plugin();

        $toreturn = array();

        $sql = "SELECT n.id, n.name, ip.name as ip, nee.tftp_server, nee.tftp_passwd, e.name entitie_name ";
        if ($plugin->isActivated("fusioninventory")) {
            $sql .= ", pfc.community, pfc.snmpversion ";
        }

        $sql .= "FROM glpi_networkequipments n, glpi_manufacturers m, glpi_networkports np, glpi_networknames nn, glpi_ipaddresses ip, glpi_plugin_nebackup_entities nee, glpi_entities e ";
        if ($plugin->isActivated("fusioninventory")) {
            $sql .= ", glpi_plugin_nebackup_networkequipments pnn, glpi_plugin_fusioninventory_configsecurities pfc ";
        }

        $sql .= "WHERE n.manufacturers_id = m.id AND m.id = (select value from glpi_plugin_nebackup_configs where type = '" . $manufacturer . "_manufacturers_id') ";

        $sql .= "AND np.itemtype = 'NetworkEquipment' AND n.id = np.items_id AND np.instantiation_type = 'NetworkPortAggregate' ";
        $sql .= "AND nn.itemtype = 'NetworkPort' AND np.id = nn.items_id ";
        $sql .= "AND ip.itemtype = 'NetworkName' AND nn.id = ip.items_id ";

        $sql .= "AND n.networkequipmenttypes_id = (select value from glpi_plugin_nebackup_configs where type = 'networkequipmenttype_id') ";
        $sql .= "AND n.entities_id = nee.entities_id AND nee.entities_id = e.id ";

        if ($plugin->isActivated("fusioninventory")) {
            $sql .= "AND n.id = pnn.networkequipments_id AND pnn.plugin_fusioninventory_configsecurities_id = pfc.id ";
        }

        $sql .= "GROUP BY n.name";

        foreach ($DB->request($sql) as $data) {
            $toreturn[] = $data;
        }

        return $toreturn;
    }

    /**
     * Show the form to save snmp authentication. Only if FusionInventory plugin
     * is actived.
     */
    private function showFormSNMPAuth(CommonGLPI $item) {
        global $CFG_GLPI;
        
        echo '<h3>' . __("Configuration of the SNMP authentication", 'nebackup') . '</h3>';
        
        echo "<form name='form' method='post' action='" . $CFG_GLPI['root_doc'] . "/plugins/nebackup/front/networkequipment.form.php" . "'>";

        echo "<table><tr>";
        echo "<td align='center'>" . __('SNMP authentication (READ/WRITE community): ', 'nebackup') . "</td>";
        echo "<td align='center'>";
        echo Html::hidden('networkequipments_id', array('value' => $item->fields['id']));
        PluginFusioninventoryConfigSecurity::authDropdown(
            $this->getSNMPAuth($item->fields['id'])
        );
        echo "</td></tr>";
        echo "<tr><td align='center' colspan='2'>";
        echo "<input type='submit' name='update' value=\"" . __('Update') . "\" class='submit' >";
        echo "</td></tr>";
        echo "</table>";

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
    
    /**
     * Set SNMP authentication. Only if FusionInventory plugin is actived.
     * @param type $plugin_fusioninventory_configsecurities_id
     */
    public function setSNMPAuth($networkequipments_id, $plugin_fusioninventory_configsecurities_id) {
        global $DB;

        if ($plugin_fusioninventory_configsecurities_id == 0) {
            $sql = "DELETE FROM glpi_plugin_nebackup_networkequipments ";
            $sql .= "WHERE networkequipments_id = $networkequipments_id";
            
        } else {
            $sql = "SELECT count(*) cuenta FROM glpi_plugin_nebackup_networkequipments ";
            $sql .= "WHERE networkequipments_id = $networkequipments_id ";

            if ($result = $DB->query($sql)) {
                $result = $DB->fetch_assoc($result);
                if ($result['cuenta'] != 0) {
                    $sql = "UPDATE glpi_plugin_nebackup_networkequipments ";
                    $sql .= "SET plugin_fusioninventory_configsecurities_id = $plugin_fusioninventory_configsecurities_id ";
                    $sql .= "WHERE networkequipments_id = $networkequipments_id";
                } else {
                    $sql = "INSERT INTO glpi_plugin_nebackup_networkequipments(networkequipments_id, plugin_fusioninventory_configsecurities_id) ";
                    $sql .= "VALUES($networkequipments_id, $plugin_fusioninventory_configsecurities_id)";
                }
            }
        }

        return $DB->query($sql);
    }

}
