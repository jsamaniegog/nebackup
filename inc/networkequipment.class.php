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

    static function showInfo($datos) {
        global $DB;
        
        $config = new PluginNebackupConfig();
        $config_data = array_values($config->find());
        
        $manufacturer = false;
        $type = false;
        
        foreach ($config_data as $key => $value) {
            // si coincide el tipo
            if ($value['type'] == 'networkequipmenttype_id' 
                and $datos->fields['networkequipmenttypes_id'] == $value['value']) 
            {
                $type = true;
            }
            
            // si coincide el fabricante como uno de los soportados
            if (strstr($value['type'], "_manufacturers_id") and $datos->fields['manufacturers_id'] == $value['value']) {
                $manufacturer = true;
            }
        }
        
        if ($manufacturer == false or $type == false) {
            return false;
        }
        
        // first check if we have a record and if type and manufacturer match
        $query = "SELECT nee.tftp_server, e.name entity_name ";
        $query .= "FROM glpi_plugin_nebackup_entities nee, glpi_entities e ";
        $query .= "WHERE nee.entities_id = e.id AND nee.entities_id = " . $datos->fields['entities_id'];
        if ($result = $DB->query($query)) {
            $result = $result->fetch_assoc();
            
            if (!$result) {
                return false;
            }
            
        } else {
            echo __('Database Error', 'nebackup');
            return false;
        }
        
        // init table
        echo '<table class="tab_glpi" width="100%">';
        echo '<tr>';
        echo '<th>' . __('Backup Information (from NEBackup plugin)', 'nebackup') . '</th>';
        echo '</tr>';
        echo '<tr class="tab_bg_1">';
        echo '<td>';
        
        // local path to temporal file
        $tmp_file = GLPI_ROOT . "/files/_cache/nebackup.tmp";
        
        // remove the temporal file if exists
        unlink($tmp_file);
        
        // check if tftp server is online
        if (!PluginNebackupUtil::ping($result['tftp_server'])) {
            echo '<b style="color:red;">' . __('TFTP server ' . $result['tftp_server'] . ' is not alive</b>', 'nebackup');
            
        } else {
        
            // get the file from tftp
            $remote_path = PluginNebackupConfig::BACKUP_PATH . '/' . $result['entity_name'] . '/' . PluginNebackupBackup::escapeNameToTftp($datos->fields['name']);
            $command = 'tftp '.$result['tftp_server'].' -c get "' . $remote_path . '"';
            $command_result = `$command`;

            if (!$command_result) {
                // move file to files directory
                $command = "mv " . $datos->fields['name'] . " " . GLPI_ROOT . "/files/_cache/nebackup.tmp";
                `$command`;

                if (file_exists($tmp_file)) {
                    // link to download the file
                    echo Html::link(
                        __("Download backup", 'nebackup') . ": " . $datos->fields['name'], 
                        PluginNebackupDownload::getFormURL() . "?name=" . PluginNebackupBackup::escapeNameToTftp($datos->fields['name'])
                    );

                    $cron = new CronTask();
                    $cron->getFromDBbyName("PluginNebackupBackup", "nebackup");
                    echo '<tr><td>' . __('Last run: ', 'nebackup') . $cron->fields['lastrun'] . '</td></tr>';

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
     * 
     * @global type $DB
     * @param type $type An supported network equipment. See: PluginNebackupConfig::SUPPORTED_MANUFACTURERS.
     */
    static function getNetworkEquipmentsToBackup($manufacturer) {
        global $DB;
        
        $toreturn = array();

        $sql = "SELECT n.id, n.name, ip.name as ip, nee.tftp_server, nee.tftp_passwd, e.name entitie_name ";

        $sql .= "FROM glpi_networkequipments n, glpi_manufacturers m, glpi_networkports np, glpi_networknames nn, glpi_ipaddresses ip, glpi_plugin_nebackup_entities nee, glpi_entities e ";

        $sql .= "WHERE n.manufacturers_id = m.id AND m.id = (select value from glpi_plugin_nebackup_configs where type = '" . $manufacturer . "_manufacturers_id') ";

        $sql .= "AND np.itemtype = 'NetworkEquipment' AND n.id = np.items_id AND np.instantiation_type = 'NetworkPortAggregate' ";
        $sql .= "AND nn.itemtype = 'NetworkPort' AND np.id = nn.items_id ";
        $sql .= "AND ip.itemtype = 'NetworkName' AND nn.id = ip.items_id ";

        $sql .= "AND n.networkequipmenttypes_id = (select value from glpi_plugin_nebackup_configs where type = 'networkequipmenttype_id') ";
        $sql .= "AND n.entities_id = nee.entities_id AND nee.entities_id = e.id ";

        $sql .= "GROUP BY n.name";

        foreach ($DB->request($sql) as $data) {
            $toreturn[] = $data;
        }
        
        return $toreturn;
    }

}
