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

class PluginNebackupEntity extends CommonDBTM {
    
    static $rightname = 'entity';

    /**
     * Get name of this type
     *
     * @return text name of this type by language of the user connected
     *
     * */
    static function getTypeName($nb = 0) {
        return __('Entity', 'nebackup');
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

    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

        if ($item->getID() > -1) {
            $pmEntity = new PluginNebackupEntity();
            $pmEntity->showForm($item->fields['id']);
        }
        return true;
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
    function showForm($entities_id, $options = array()) {
        global $DB;
        
        // get plugin entity data
        $row = self::getEntityData($entities_id);
        if ($row) {
            $this->fields['id'] = $row['id'];
            $this->fields["is_recursive"] = $row['is_recursive'];
        }

        //$this->initForm($ID, $options);
        $this->showFormHeader($options);
        

        
        // HTML
        // fields
        echo "<tr><td colspan='2'>";
        
        // hidden entity id
        echo Html::hidden("entity_id_edited", array('value' => $_GET['id']));
        
        // tftp field
        echo __('TFTP server', 'nebackup') . "</td><td colspan='2'>";
        echo Html::input("tftp_server", array('value' => $row['tftp_server']));
        
        echo "</td></tr><tr><td colspan='2'>";
        
        // snmp field
        echo __('SNMP Community', 'nebackup') . "</td><td colspan='2'>";
        $plugin = new Plugin();
        if (!$plugin->isActivated("fusioninventory") or PluginNebackupConfig::getUseFusionInventory() == 0) {
            echo Html::input("tftp_passwd", array('value' => $row['tftp_passwd']));
        } else {
            echo "<b style='color:red;'>"
                . __('FusionInventory Plugin is installed and active, it is not necesary', 'nebackup')
                . "</b>";
        }
        
        // telnet password field
        echo "</td></tr><tr><td colspan='2'>";
        echo __('Telnet password (only for HP Procurve)', 'nebackup') . "</td><td colspan='2'>";
        $plugin = new Plugin();
        if (!$plugin->isActivated("fusioninventory") or PluginNebackupConfig::getUseFusionInventory() == 0) {
            echo Html::input("telnet_passwd", array('value' => $row['telnet_passwd']));
        } else {
            echo "<b style='color:red;'>"
                . __('FusionInventory Plugin is installed and active, it is not necesary', 'nebackup')
                . "</b>";
        }
        
        echo "</td></tr>";
        
        
        // info
        $cron = new CronTask();
        $cron->getFromDBbyName("PluginNebackupBackup", "nebackup");
        echo '<tr><td colspan="4" class="center b"><br>';
        echo __('Once you have configured the entity you can go to automatic actions to run for the first time: ', 'nebackup') . Html::link(__('NEBackup Task', 'nebackup'), CronTask::getFormURLWithID($cron->fields['id']));
        echo "</td></tr>";
        
        
        $this->showFormButtons($options);

        return true;
    }

    static function getEntityData($entities_id) {
        global $DB;

        $query = "SELECT * FROM glpi_plugin_nebackup_entities WHERE entities_id = $entities_id";

        if ($result = $DB->query($query)) {
            return $result->fetch_assoc();
        }

        return false;
    }

    public function setEntityData($data) {
        global $DB;

        $data['tftp_server'] = str_replace(' ', '', $data['tftp_server']);
        if (isset($data['tftp_passwd'])) {
            $data['tftp_passwd'] = str_replace(' ', '', $data['tftp_passwd']);
        }
        
        if (isset($data['telnet_passwd'])) {
            $data['telnet_passwd'] = str_replace(' ', '', $data['telnet_passwd']);
        }

        // purge
        if (isset($data['purge']) or $data['tftp_server'] == '') {

            /*if (self::hasEntityParent($data['id'])) {
                Session::addMessageAfterRedirect(__("You must delete the configuration in the parent entity to delete this configuration.", 'nebackup'), false, ERROR);
                return false;
            }*/

            // sub entities to purge (-1 is to concatenate but doesn't exist)
            $sub_entities_ids = "-1";
            // this gets if it's recursive and the sons of this entity
            if (!$sub_entities_ids .= self::getSonsOfEntity($data['id'])) {
                return false;
            }

            $query = "DELETE FROM `glpi_plugin_nebackup_entities` ";
            $query .= "WHERE id = " . $data['id'] . " OR entities_id in (" . $sub_entities_ids . ")";
        } else {

            // update
            if (isset($data['id'])) {
                // sub entities to purge (-1 is to concatenate but doesn't exist)
                $sub_entities_ids = "-1";
                // this gets if it's recursive and the sons of this entity
                $sub_entities_ids .= self::getSonsOfEntity($data['id']);

                $query = "UPDATE `glpi_plugin_nebackup_entities` ";
                $query .= "SET tftp_server = '" . $data['tftp_server'] . "' ";
                if (isset($data['tftp_passwd']))
                    $query .= ", tftp_passwd = '" . $data['tftp_passwd'] . "' ";
                if (isset($data['telnet_passwd']))
                    $query .= ", telnet_passwd = '" . $data['telnet_passwd'] . "' ";
                $query .= "WHERE id = " . $data['id'] . " OR entities_id in (" . $sub_entities_ids . ")";

                // insert
            } else {
                $data['tftp_passwd'] = (isset($data['tftp_passwd'])) ? $data['tftp_passwd'] : '' ;
                $data['telnet_passwd'] = (isset($data['telnet_passwd'])) ? $data['telnet_passwd'] : '' ;
                
                $query = "INSERT INTO glpi_plugin_nebackup_entities";
                $query .= "(entities_id, tftp_server, tftp_passwd, telnet_passwd, is_recursive) VALUES ";
                $query .= "(" . $data['entity_id_edited'] . ", ";
                $query .= "'" . $data['tftp_server'] . "', ";
                $query .= "'" . $data['tftp_passwd'] . "', ";
                $query .= "'" . $data['telnet_passwd'] . "', ";
                $query .= "" . $data['is_recursive'] . ")";

                if ($data['is_recursive'] = 1) {
                    $sql = "SELECT id FROM glpi_entities ";
                    $sql .= "WHERE ancestors_cache like '%\"" . $data['entity_id_edited'] . "\"%'";
                    $sql .= " AND id != " . $data['entity_id_edited'] . "";
                    $sql .= " AND id not in (select entities_id from glpi_plugin_nebackup_entities)";

                    if ($DB->query($sql)) {
                        // add an insert value for each son entity
                        foreach ($DB->request($sql) as $data2) {
                            $query .= ",(" . $data2['id'] . ", ";
                            $query .= "'" . $data['tftp_server'] . "', ";
                            $query .= "'" . $data['tftp_passwd'] . "', ";
                            $query .= "'" . $data['telnet_passwd'] . "', ";
                            $query .= "" . $data['is_recursive'] . ") ";
                        }
                    }
                }
            }
        }

        return $DB->query($query);
    }

    /**
     * Returns the ids of the sons.
     * @param $entity_id The ID of the entity that we are editing.
     * @return string The IDs with comma separated 
     */
    static private function getSonsOfEntity($entity_id) {
        global $DB;

        // string to return
        $sub_entities_ids = "";

        $query = "SELECT nee.is_recursive, e.sons_cache, e.entities_id parent ";
        $query .= "FROM glpi_plugin_nebackup_entities nee, glpi_entities e ";
        $query .= "WHERE nee.id = " . $entity_id . " and nee.entities_id = e.id ";

        if ($result = $DB->query($query)) {

            $result = $result->fetch_assoc();

            // if si recursive return the sons ids
            if ($result['is_recursive'] == 1) {
                // get sons_cache and generate an array
                $result = json_decode($result['sons_cache'], true);

                foreach ($result as $v) {
                    $sub_entities_ids .= ",$v";
                }
            }
        }

        return $sub_entities_ids;
    }
}
