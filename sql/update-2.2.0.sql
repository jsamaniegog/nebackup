/* 
 * Copyright (C) 2018 Javier Samaniego García <jsamaniegog@gmail.com>
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
ALTER TABLE `glpi_plugin_nebackup_entities` ADD `protocol` TINYINT(1) NOT NULL DEFAULT '1' AFTER `tftp_passwd`;
ALTER TABLE `glpi_plugin_nebackup_entities` ADD `username` CHAR(32) NULL DEFAULT NULL AFTER `protocol`;
ALTER TABLE `glpi_plugin_nebackup_entities` ADD `password` VARCHAR(128) NULL DEFAULT NULL AFTER `username`;
ALTER TABLE `glpi_plugin_nebackup_entities` ADD `telnet_username` CHAR(32) NULL DEFAULT NULL AFTER `telnet_passwd`;
ALTER TABLE `glpi_plugin_nebackup_entities` CHANGE `tftp_server` `server` VARCHAR(128) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '';
ALTER TABLE `glpi_plugin_nebackup_entities` CHANGE `tftp_passwd` `community` CHAR(32) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '';
ALTER TABLE `glpi_plugin_nebackup_entities` CHANGE `telnet_passwd` `telnet_password` CHAR(128) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '';

ALTER TABLE `glpi_plugin_nebackup_networkequipments` ADD `telnet_username` CHAR(32) NULL DEFAULT NULL AFTER `plugin_fusioninventory_configsecurities_id`;
ALTER TABLE `glpi_plugin_nebackup_networkequipments` ADD `telnet_password` CHAR(128) NULL DEFAULT NULL AFTER `telnet_username`;

UPDATE glpi_plugin_nebackup_networkequipments nn
INNER JOIN glpi_plugin_fusioninventory_configsecurities fc ON nn.plugin_fusioninventory_configsecurities_id = fc.id
SET telnet_username = 'admin', telnet_password = fc.community
WHERE nn.networkequipments_id in 
(SELECT id FROM glpi_networkequipments WHERE manufacturers_id = 
(SELECT value FROM glpi_plugin_nebackup_configs WHERE type = 'hpprocurve_manufacturers_id'));