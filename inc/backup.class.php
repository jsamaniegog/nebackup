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

class PluginNebackupBackup {

    public function getTypeName() {
        return "nebackup";
    }

    /**
     * 
     */
    static function cronNebackup() {
        foreach (explode(",", PluginNebackupConfig::SUPPORTED_MANUFACTURERS) as $manufacturer) {
            self::backup($manufacturer);
        }
    }

    static private function backup($manufacturer) {
        $ne_to_backup = PluginNebackupNetworkEquipment::getNetworkEquipmentsToBackup($manufacturer);

        foreach ($ne_to_backup as $reg) {

            if (PluginNebackupConfig::DEBUG_NEBACKUP)
                Toolbox::logInFile("nebackup", "Start copy: " . print_r($reg, true));

            // datos de conexión al servidor tftp (es el mismo para todos los registros)
            $tftp_server = $reg['tftp_server'];

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

            // si el plugin fusioninventory está activado tomamos los datos de 
            // sus tablas para la comunidad snmp
            if (isset($reg['community'])) {
                $reg['tftp_passwd'] = $reg['community'];

                $tftp_passwd = escapeshellcmd($reg['tftp_passwd']);

                if (!self::checkTftpServerAlive($tftp_server)) {
                    continue;
                }
            } else {
                // todo: quitar esta particularidad cuando se encuentre un modo mejor que el telnet
                if ($manufacturer == 'hpprocurve') {
                    $tftp_passwd = escapeshellcmd($reg['telnet_passwd']);
                } else {
                    $tftp_passwd = escapeshellcmd($reg['tftp_passwd']);
                }


                if (!self::checkTftpServerAlive($tftp_server)) {
                    return;
                }
            }

            if ($tftp_passwd == '') {
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
            // nombre de la entidad (esto sirve para la ruta donde se guarda del TFTP)
            $entitie_name = $reg['entitie_name'];

            // esta variable controla el número del script, hay 3 scripts: 
            // uno inicia la copia, el segundo la comprueba a ver si ha 
            // acabado y el tercero la cierra
            $num_script = 1;

            $error = ""; // inicializamos $error

            do {
                if ($num_script == 1) {
                    // hacemos un ping para ver si está viva la ip
                    // PING con 1 paquete, 1 segundo de time out
                    $comando = `/bin/ping $ip -c1 -W1`;

                    // Si encuentra esta cadena es que no ha recibido paquetes
                    if (preg_match('/0 received/', $comando) != 0) {
                        break;
                    }
                }

                if ($num_script == 2) {
                    // Wait x seconds to ask if it's over
                    sleep(PluginNebackupConfig::SECONDS_TO_WAIT_FOR_FINISH);
                }

                $command_result = self::executeCopyScript($num_script, $host, $ip, $rannum, $tftp_server, $tftp_passwd, $manufacturer, $entitie_name);

                switch ($manufacturer) {
                    case 'cisco':
                        // si el resultado no es el esperado continuamos con el 
                        // siguiente ya que ha habido un error
                        // los estados para el script 2 son: 1:waiting, 2:running, 3:successful, 4:failed;
                        if (preg_match('/' . $rannum . ' = INTEGER/', $command_result) == 0
                            or ( $num_script == 2
                            and preg_match('/' . $rannum . ' = INTEGER: 4/', $command_result) != 0)) {

                            $num_script = 3;

                            $error = __("the switch returned status failed", "nebackup");

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
                                    self::executeCopyScript($num_script, $host, $ip, $rannum, $tftp_server, $tftp_passwd, $manufacturer, $entitie_name);
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
                    $error = __("timeout expired", "nebackup");
                    // ejecutamos el script número 3
                    self::executeCopyScript($num_script, $host, $ip, $rannum, $tftp_server, $tftp_passwd, $manufacturer, $entitie_name);
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
     * @param type $num_script script number: 1 => init, 2 => ask for finish, 3 => close
     * @param type $host Hostname
     * @param type $ip IP address
     * @param type $rannum A random number
     * @param type $tftp_server IP o DNS name of the tftp server
     * @param type $tftp_passwd Password of the tftp server
     * @return type
     */
    static private function executeCopyScript($num_script, $host, $ip, $rannum, $tftp_server, $tftp_passwd, $manufacturer, $entitie_name) {
        if (PluginNebackupConfig::DEBUG_NEBACKUP)
            Toolbox::logInFile("nebackup", "Script: " . "nebackup_" . $manufacturer . "_$num_script.sh\r");

        $host = self::escapeNameToTftp($host);
        $host = PluginNebackupConfig::getBackupPath(true, $manufacturer, $entitie_name) . '/' . $host;
        $tftp_server = gethostbyname($tftp_server);

        $comando = "sh " . GLPI_ROOT . "/plugins/nebackup/commands/nebackup_" . $manufacturer . "_$num_script.sh $host $ip $rannum $tftp_server $tftp_passwd";
        $resultado = `$comando`;

        return $resultado;
    }

    /**
     * Check if tftp server is alive.
     * @param type $tftp_server
     * @param type $tftp_passwd
     */
    static private
        function checkTftpServerAlive($tftp_server) {
        if (!$tftp_server)
            return false;

        $command = `tftp $tftp_server -c get TeSt_FiLe_NEBackup`;
        if (!preg_match('/Transfer timed out/', $command))
            return true;

        return false;
    }

    static public
        function escapeNameToTftp($name) {
        return str_replace(" ", "", escapeshellcmd($name));
    }

}
