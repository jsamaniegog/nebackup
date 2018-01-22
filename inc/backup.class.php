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

class PluginNebackupBackup extends CommonDBTM {

    static function getTypeName($nb = 0) {
        return __('Backup', 'nebackup');
    }

    /**
     * Executed by cron.
     */
    static function cronNebackup($task) {
        foreach (explode(",", PluginNebackupConfig::SUPPORTED_MANUFACTURERS) as $manufacturer) {
            $task->addVolume(1);
            $task->log(__("Start backup of manufacturer: ", "nebackup") . $manufacturer);
            self::backup($manufacturer);
            $task->log(__("End backup of manufacturer: ", "nebackup") . $manufacturer);
        }

        self::sendErrorsByMail();

        return 1;
    }

    static function sendErrorsByMail() {
        global $DB;

        $plugin = new Plugin();
        $use_fusioninventory = false;
        if ($plugin->isActivated("fusioninventory") and PluginNebackupConfig::getUseFusionInventory() == 1) {
            $use_fusioninventory = true;
        }

        $sql = "SELECT n.name as networkequipment_name, n.entities_id, l.* ";
        $sql .= "FROM glpi_plugin_nebackup_logs l, glpi_networkequipments n, glpi_plugin_nebackup_entities e ";
        if ($use_fusioninventory) {
            $sql .= ", glpi_plugin_nebackup_networkequipments pnn ";
        }
        $sql .= "WHERE l.networkequipments_id = n.id AND l.error is not null AND n.entities_id = e.entities_id";
        $sql .= " AND n.networkequipmenttypes_id = (SELECT c.value FROM glpi_plugin_nebackup_configs c WHERE c.type = 'networkequipmenttype_id' LIMIT 1)";
        $sql .= " AND n.manufacturers_id in (SELECT c.value FROM glpi_plugin_nebackup_configs c WHERE c.type like '%manufacturers_id')";
        if ($use_fusioninventory) {
            $sql .= " AND pnn.networkequipments_id = n.id ";
        }
        $sql .= "ORDER BY n.entities_id ASC";

        $result = $DB->query($sql);

        $buffer = array();

        while ($error = $result->fetch_assoc()) {

            // se envía notificación por entidad
            if (!empty($buffer) and $error['entities_id'] != $previous_entity) {

                self::raiseEventError($buffer, $previous_entity);

                // reset buffer
                $buffer = array();
            }

            $previous_entity = $error['entities_id'];

            $buffer[] = $error;
        }

        self::raiseEventError($buffer, $previous_entity);
    }

    /**
     * 
     * @param type $errors Array of errors from logs table.
     * @param type $entity Entity ID.
     */
    private static function raiseEventError($errors, $entity) {
        NotificationEvent::raiseEvent(
            'errors'
            , new PluginNebackupBackup()
            , array('errors' => $errors, 'entities_id' => $entity)
        );
    }

    /**
     * Backup one network equipment.
     * @param type $manufacturer
     * @param type $networkequipments_id
     */
    static public function backupNetworkEquipment($manufacturer, $networkequipments_id) {
        self::backup($manufacturer, $networkequipments_id);
    }

    /**
     * Backup several network equipments.
     * @param type $ma
     * @param type $item
     * @param type $ids
     */
    static public function backupNetworkEquipmentMassive($ma, $item, $ids) {
        // esto evita realizar múltiples consultas iguales a la base de datos
        $config_data = PluginNebackupConfig::getConfigData();

        foreach ($ids as $id) {
            $ne = new NetworkEquipment();
            $ne->getFromDB($id);
            $manufacturer = PluginNebackupNetworkEquipment::getManufacturerTag(
                    $ne->fields['networkequipmenttypes_id'], $ne->fields['manufacturers_id']
            );

            if ($manufacturer !== false) {
                self::backupNetworkEquipment(
                    $manufacturer, $id
                );
                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
            } else {
                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
            }
        }
    }

    /**
     * Backups network equipments.
     * @param nebackup_tag $manufacturer
     * @param int $networkequipments_id ID of network equipment.
     */
    static private function backup($manufacturer, $networkequipments_id = null) {
        $ne_to_backup = PluginNebackupNetworkEquipment::getNetworkEquipmentsToBackup($manufacturer, $networkequipments_id);

        foreach ($ne_to_backup as $reg) {

            if (PluginNebackupConfig::DEBUG_NEBACKUP)
                Toolbox::logInFile("nebackup", "Start copy: " . print_r($reg, true));

            // datos de conexión al servidor (es el mismo para todos los registros)
            $server = $reg['server'];

            // only snmp v2c
            if (isset($reg['snmpversion']) and $reg['snmpversion'] != '2') {
                $logs = new PluginNebackupLogs();
                $error = __("Only SNMP v2c is supported", "nebackup");
                if ($logs->getFromDBByQuery("WHERE networkequipments_id = " . $reg['id'])) {
                    $logs->fields['error'] = $error;
                    $logs->updateInDB(array('datetime', 'error'));
                } else {
                    $logs->add(array(
                        'networkequipments_id' => $reg['id'],
                        'error' => $error
                    ));
                }
                continue;
            }

            // todo: quitar esta particularidad cuando se encuentre un modo mejor que el telnet
            if ($manufacturer == 'hpprocurve') {
                // check if there is individual user/password
                $telnetAuth = PluginNebackupNetworkEquipment::getAuthTelnet($reg['id']);
                
                // individual or general configuration for telnet auth.
                if (trim($telnetAuth['telnet_username']) != '') {
                    $reg['username'] = $telnetAuth['telnet_username'];
                    $reg['password'] = $telnetAuth['telnet_password'];
                } else {
                    $reg['username'] = $reg['telnet_username'];
                    $reg['password'] = $reg['telnet_password'];
                }
            }
            
            // si el plugin fusioninventory está activado tomamos los datos de 
            // sus tablas para la comunidad snmp
            if (isset($reg['fi_community'])) {
                $reg['community'] = $reg['fi_community'];
            }

            $community = escapeshellcmd($reg['community']);
            $username = escapeshellcmd($reg['username']);
            $password = escapeshellcmd($reg['password']);

            // hack para evitar que falle el script
            if ($username == '') {
                $username = '-';
            }
            if ($password == '') {
                $password = '-';
            }
            
            if (!self::checkServerAlive($server, $reg['protocol'])) {
                return;
            }
            
            if ($community == '') {
                continue;
            }

            // to control the timeout
            $start_time = time();
            $timeout = PluginNebackupConfig::getTimeout();

            // hostname
            $host = $reg['name'];
            // ip of the switch
            $ip = $reg['ip'];
            // número aleatorio, será el minuto actual, varía de 1 a 60
            $rannum = (int) date("i") + 1;
            // nombre de la entidad (esto sirve para la ruta donde se guarda del servidor)
            $entitie_name = $reg['entitie_name'];
            // protocolo
            $protocol = $reg['protocol'];

            // esta variable controla el número del script, hay 3 scripts: 
            // uno inicia la copia, el segundo la comprueba a ver si ha 
            // acabado y el tercero la cierra
            $num_script = 1;

            $error = ""; // inicializamos $error

            do {
                if ($num_script == 1 and !PluginNebackupUtil::ping($ip)) {
                        $error = __("The network equipment does not respond to the ping", "nebackup") . ". IP: " . $ip;
                        break;
                }

                if ($num_script == 2) {
                    // Wait x seconds to ask if it's over
                    sleep(PluginNebackupConfig::SECONDS_TO_WAIT_FOR_FINISH);
                }

                $command_result = self::executeCopyScript(
                    $num_script, 
                    $host, 
                    $ip, 
                    $rannum, 
                    $server, 
                    $community, 
                    $manufacturer, 
                    $entitie_name, 
                    $protocol, 
                    $username, 
                    $password
                );

                switch ($manufacturer) {
                    case 'cisco':
                        // si el resultado no es el esperado continuamos con el 
                        // siguiente ya que ha habido un error
                        // los estados para el script 2 son: 1:waiting, 2:running, 3:successful, 4:failed;
                        if (preg_match('/' . $rannum . ' = INTEGER/', $command_result) == 0
                            or ( $num_script == 2
                            and preg_match('/' . $rannum . ' = INTEGER: 4/', $command_result) != 0)) {

                            $num_script = 3;

                            $error = __("The network equipment returned status "
                                . "failed. Make sure the switch allows the "
                                . "protocol configured.", "nebackup");

                            // debug
                            if (PluginNebackupConfig::DEBUG_NEBACKUP)
                                Toolbox::logInFile("nebackup", "Error: $error\r" . print_r($reg, true));

                            break;
                        }

                        switch ($num_script) {
                            case 1:
                                $num_script = 2;
                                break;

                            case 2:
                                // si la copia ha terminado
                                if (preg_match('/INTEGER: 3/', $command_result) != 0) {
                                    $num_script = 3;
                                    // ejecutamos el script número 3
                                    self::executeCopyScript(
                                        $num_script, 
                                        $host, 
                                        $ip, 
                                        $rannum, 
                                        $server, 
                                        $community, 
                                        $manufacturer, 
                                        $entitie_name, 
                                        $protocol, 
                                        $username, 
                                        $password
                                    );
                                } else {
                                    $num_script = 2;
                                }
                                break;
                        }
                        break;

                    case 'hpprocurve':
                        $num_script = 3; // alwais because we can't control the finish with telnet script
                        if (!preg_match("/#/", $command_result)) {  // "#" indica que hemos entrado en modo privilegiado
                            if (preg_match("/Invalid password/", $command_result)) {
                                $error = __("Invalid password", "nebackup");
                            } else {
                                $error = __("Unknown error", "nebackup");
                            }
                        }
                        break;

                    default: $num_script = 3;
                }

                // timeout control
                if ($num_script != 3 and ( time() - $start_time) > $timeout) {
                    $num_script = 3;
                    $error = __("Timeout expired", "nebackup");
                    // ejecutamos el script número 3
                    self::executeCopyScript(
                        $num_script, 
                        $host, 
                        $ip, 
                        $rannum, 
                        $server, 
                        $community, 
                        $manufacturer, 
                        $entitie_name, 
                        $protocol, 
                        $username, 
                        $password
                    );
                }
            } while ($num_script != 3);

            // add datetime to log
            $logs = new PluginNebackupLogs();
            if ($error == "") {
                if ($logs->getFromDBByQuery("WHERE networkequipments_id = " . $reg['id'])) {
                    $logs->fields['datetime'] = date("Y-m-d H:i:s");
                    $logs->fields['error'] = "NULL";
                    $logs->updateInDB(array('datetime', 'error'));
                } else {
                    $logs->add(array(
                        'datetime' => date("Y-m-d H:i:s"),
                        'networkequipments_id' => $reg['id'],
                        'error' => "NULL"
                    ));
                }
            } else {
                // add error log
                if ($logs->getFromDBByQuery("WHERE networkequipments_id = " . $reg['id'])) {
                    $logs->fields['error'] = $error;
                    $logs->updateInDB(array('datetime', 'error'));
                } else {
                    $logs->add(array(
                        'error' => $error,
                        'networkequipments_id' => $reg['id']
                    ));
                }
            }

            // debug
            if (PluginNebackupConfig::DEBUG_NEBACKUP)
                Toolbox::logInFile("nebackup", "Finish copy: " . print_r($reg, true));
        }
    }

    /**
     * Ejecuta el script de copia y retorna el resultado.
     * @param int $num_script script number: 1 => init, 2 => ask for finish, 3 => close
     * @param string $host Hostname
     * @param string $ip IP address
     * @param int $rannum A random number
     * @param string $server IP o DNS name of the server
     * @param string $community SNMP community.
     * @param string $manufacturer Manufacturer string.
     * @param string $entitie_name Name of the entity.
     * @param int $protocol Can be:
     * #1. tftp
     * #2. ftp
     * #3. rcp
     * #4. scp
     * #5. sftp
     * @return type
     */
    static private function executeCopyScript($num_script, $host, $ip, $rannum, 
        $server, $community, $manufacturer, $entitie_name, $protocol = 1, 
        $username = "admin", $password = "admin"
    ) {
        if (PluginNebackupConfig::DEBUG_NEBACKUP)
            Toolbox::logInFile("nebackup", "Script: " . "nebackup_" . $manufacturer . "_$num_script.sh\r");

        $host = self::escapeName($host);
        $host = PluginNebackupConfig::getBackupPath(true, $manufacturer, $entitie_name) . '/' . $host;
        $server = gethostbyname($server);

        $comando = "sh " . GLPI_ROOT . "/plugins/nebackup/commands/nebackup_" . $manufacturer . "_$num_script.sh $host $ip $rannum $server $community $protocol $username $password";
        if (PluginNebackupConfig::DEBUG_NEBACKUP)
            Toolbox::logInFile("nebackup", "Comando ejecutado: $comando\r" . print_r($reg, true));
        $resultado = `$comando`;

        return $resultado;
    }

    /**
     * Checks if a server responds.
     * @param type $server
     * @param type $protocol
     * @return type
     */
    static private function checkServerAlive($server, $protocol) {
        switch ($protocol) {
            case '1':
                if (PluginNebackupUtil::ping($server)) {
                    $toReturn = self::checkTftpServerAlive($server);
                } else {
                    $toReturn = false;
                }
                break;

            default: $toReturn = PluginNebackupUtil::ping($server);
        }

        return $toReturn;
    }

    /**
     * Check if tftp server is alive.
     * @param type $tftp_server IP or hostname.
     * @return bool
     */
    static private function checkTftpServerAlive($tftp_server) {
        if (!$tftp_server)
            return false;

        $command = `tftp $tftp_server -c get TeSt_FiLe_NEBackup 2>&1`;
        if (!preg_match('/Transfer timed out/', $command)) {
            return true;
        }

        return false;
    }

    /**
     * Escapes the name for especial characteres using escapeshellcmd command
     * and deleting spaces.
     * @param string $name String to escape.
     * @return string Escaped string.
     */
    static public function escapeName($name) {
        return str_replace(" ", "", escapeshellcmd($name));
    }

}
