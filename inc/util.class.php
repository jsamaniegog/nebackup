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
 * Utils for NEBackup plugin.
 *
 * @author Javier Samaniego García <jsamaniegog@gmail.com>
 */
class PluginNebackupUtil {

    /**
     * Check if a host is alive on the port specified.
     * @param string $host
     * @param int $port
     * @param int $waitTimeoutInSeconds
     * @param string $protocol "tcp" or "udp"
     * @return boolean
     */
    static function ping($host, $waitTimeoutInSeconds = 1) {
        // hacemos un ping para ver si está viva la ip
        // PING con 1 paquete, 1 segundo de time out
        $comando = `/bin/ping $host -c1 -W$waitTimeoutInSeconds`;

        // Si encuentra esta cadena es que no ha recibido paquetes
        if (preg_match('/0 received/', $comando) != 0) {
            return false;
        }
        
        return true;
    }

}
