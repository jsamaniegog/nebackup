/* 
 * Copyright (C) 2017 Javier Samaniego García <jsamaniegog@gmail.com>
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

INSERT INTO `glpi`.`glpi_plugin_nebackup_configs`(type, value) VALUES ('backup_path', 'backup/{entity}');
INSERT INTO `glpi`.`glpi_plugin_nebackup_configs`(type, value) VALUES ('use_fusioninventory', '0');
INSERT INTO `glpi`.`glpi_plugin_nebackup_configs`(type, value) VALUES ('timeout', '60');
CREATE TABLE `glpi`.`glpi_plugin_nebackup_networkequipments` (
        `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
        `networkequipments_id` int(11) NOT NULL UNIQUE,
        `plugin_fusioninventory_configsecurities_id` int(11) NOT NULL default 0
    )ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `glpi`.`glpi_plugin_nebackup_logs` (
        `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
        `networkequipments_id` int(11) NOT NULL UNIQUE,
        `datetime` datetime,
        `error` char(64)
    )ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `glpi_plugin_nebackup_entities` ADD `telnet_passwd` CHAR(32) NOT NULL AFTER `tftp_passwd`;