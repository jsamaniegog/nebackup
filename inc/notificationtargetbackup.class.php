<?php

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

/**
 * Description of notificationtargetbackup
 *
 * @author Javier Samaniego García <jsamaniegog@gmail.com>
 */
class PluginNebackupNotificationTargetBackup extends NotificationTarget {

    function getEvents() {
        return array('errors' => __('Errors report', 'nebackup'));
    }

    /**
     * Get all data needed for template processing
     * */
    function getDatasForTemplate($event, $options = array()) {
        global $CFG_GLPI;
        
        switch ($event) {
            case 'errors':

                $this->datas['##nebackup.errors.subject##'] = PluginNebackupConfig::getTypeName(1)
                    . " "
                    . __("Errors", "nebackup")
                    . " - "
                    . date("Y-m-d H:i:s");

                $errors = array();
                foreach ($options['errors'] as $error) {

                    // field names
                    $errors['##lang.nebackup.networkequipment_name##'] = __("Network Equipment Name: ", "nebackup");
                    $errors['##lang.nebackup.error##'] = __("Error: ", "nebackup");
                    $errors['##lang.nebackup.lastcopy##'] = __("Date of last copy: ", "nebackup");

                    // field datas
                    $errors['##nebackup.networkequipment_name##'] = $error['networkequipment_name'];
                    $errors['##nebackup.error##'] = (trim($error['error']) == "") ? __("- Empty -", "nebackup") : $error['error'];
                    $errors['##nebackup.url##'] = $this->formatURL(self::GLPI_USER, "NetworkEquipment_" . $error['networkequipments_id']);
                    $errors['##nebackup.lastcopy##'] = $error['datetime'];

                    $this->datas['nebackup.errors'][] = $errors;
                }
        }
    }
}
