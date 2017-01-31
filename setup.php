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

/**
 * Init the hooks of the plugins -Needed
 * @global array $PLUGIN_HOOKS
 * @glpbal array $CFG_GLPI
 */
function plugin_init_nebackup() {
    global $PLUGIN_HOOKS, $CFG_GLPI;

    // Params : plugin name - string type - ID - Array of attributes
    // No specific information passed so not needed
    // Plugin::registerClass('PluginExampleExample',
    //                      array('classname'              => 'PluginExampleExample',
    //                        ));

    Plugin::registerClass('PluginNebackupNetworkEquipment', array('addtabon' => array('NetworkEquipment')));
    //Plugin::registerClass('PluginNebackupProfile',  array('addtabon' => 'Profile'));
    Plugin::registerClass('PluginNebackupConfig', array('addtabon' => 'Config'));
    Plugin::registerClass('PluginNebackupEntity', array('addtabon' => array('Entity')));

    // declare this plugin as an import plugin for NetworkEquipment itemtype
    //$PLUGIN_HOOKS['import_item']['nebackup'] = array('NetworkEquipment' => array('Plugin'));
    // este hook muestra información en la pestaña de datos del switch
    $PLUGIN_HOOKS['autoinventory_information']['nebackup'] = array(
        'NetworkEquipment' => array('PluginNebackupNetworkEquipment', 'showInfo')
    );

    Plugin::registerClass('PluginNebackupBackup', array ('notificationtemplates_types'  => true));
    
    // Display a menu entry ?
    /* $_SESSION["glpi_plugin_nebackup_profile"]['nebackup'] = 'w';
      if (isset($_SESSION["glpi_plugin_nebackup_profile"])) {
      $PLUGIN_HOOKS['menu_toadd']['nebackup'] =
      array(
      'plugins' => 'PluginNebackupNebackup',
      'tools'   => 'PluginNebackupNebackup'
      );
      } */

    // Config page (muestra el acceso en el menu superior, en la parte de configuración)
    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['config_page']['nebackup'] = 'front/config.php';
        $PLUGIN_HOOKS['menu_toadd']['nebackup'] = array(
            'config' => 'PluginNebackupConfig'
        );
    }


    // Init session
    //$PLUGIN_HOOKS['init_session']['example'] = 'plugin_init_session_example';
    // Change profile
    //$PLUGIN_HOOKS['change_profile']['nebackup'] = 'plugin_change_profile_nebackup';
    // Change entity
    //$PLUGIN_HOOKS['change_entity']['example'] = 'plugin_change_entity_example';
    // hook para cuando una entidad es mandada a la papelera
    // (las entidades son eliminadas directamente)
    /* $PLUGIN_HOOKS['item_delete']['nebackup']= 
      array(
      'Entity' => 'plugin_item_delete_nebackup'
      ); */

    // hook para cuando una entidad es suprimida permanentemente
    $PLUGIN_HOOKS['item_purge']['nebackup'] = array(
            'Entity' => 'plugin_item_purge_nebackup'
    );

    // hook para cuando una entidad es restaurada de la papelera
    // (las entidades son eliminadas directamente)
    /* $PLUGIN_HOOKS['item_restore']['nebackup']= 
      array(
      'Entity' => 'plugin_item_restore_nebackup'
      ); */

    // Massive Action definition
    $PLUGIN_HOOKS['use_massive_action']['nebackup'] = 1;
    //$PLUGIN_HOOKS['assign_to_ticket']['nebackup'] = 1;
    // Add specific files to add to the header : javascript or css
    //$PLUGIN_HOOKS['add_javascript']['nebackup'] = 'nebackup.js';
    //$PLUGIN_HOOKS['add_css']['nebackup']        = 'nebackup.css';
    // request more attributes from ldap
    //$PLUGIN_HOOKS['retrieve_more_field_from_ldap']['nebackup']="plugin_retrieve_more_field_from_ldap_nebackup";
    // Retrieve others datas from LDAP
    //$PLUGIN_HOOKS['retrieve_more_data_from_ldap']['nebackup']="plugin_retrieve_more_data_from_ldap_nebackup";
    // CSRF compliance : All actions must be done via POST and forms closed by Html::closeForm();
    $PLUGIN_HOOKS['csrf_compliant']['nebackup'] = true;
}

/**
 * Fonction de définition de la version du plugin
 * @return type
 */
function plugin_version_nebackup() {
    return array('name' => 'nebackup',
        'version' => '2.1.1',
        'author' => 'Javier Samaniego',
        'license' => 'AGPLv3+',
        'homepage' => 'https://github.com/jsamaniegog/nebackup',
        'minGlpiVersion' => '0.90');
}

/**
 * Fonction de vérification des prérequis
 * @return boolean
 */
function plugin_nebackup_check_prerequisites() {
    if (version_compare(GLPI_VERSION, '0.90', 'lt')) {
        _e('This plugin requires GLPI >= 0.90', 'nebackup');
        return false;
    }

    return true;
}

/**
 * Fonction de vérification de la configuration initiale
 * Uninstall process for plugin : need to return true if succeeded
 * may display messages or add to message after redirect.
 * @param type $verbose
 * @return boolean
 */
function plugin_nebackup_check_config($verbose = false) {
    // check here
    if (true) {
        return true;
    }

    if ($verbose) {
        _e('Installed / not configured', 'nebackup');
    }

    return false;
}

?>
