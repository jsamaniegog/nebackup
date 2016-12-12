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
 * Class générale du plugin
 */
class PluginNebackupNebackup extends CommonDBTM {
    /**
    * Return the localized name of the current Type
    * @param int $nb Select singular(0), plural(1)
    * @return string
    **/
   public static function getTypeName($nb = 0) {
      return _n('NEBackup', 'NEBackups', $nb, 'nebackup');
   }
   
   /**
    * Have I the global right to "create" the Object
    *
    * @return boolean
    **/
   /*public static function canCreate() {
      return Session::haveRight('networkequipment', UPDATE);
   }*/
   
   /**
    * Have I the global right to "view" the Object
    * Default is true and check entity if the objet is entity assign
    * 
    * @return boolean
    **/
   /*public static function canView() {
      return Session::haveRight('networkequipment', READ);
   }*/
   
    /**
    * Get the Search options for the given Type
    *
    * @return an array of search options
    * More information on https://forge.indepnet.net/wiki/glpi/SearchEngine
    **/
    /*public function getSearchOptions() {

       $tab                       = array();

       $tab['common']             = self::getTypeName(2);

       $tab[1]['table']           = $this->getTable();
       $tab[1]['field']           = 'name';
       $tab[1]['name']            = __('Name');
       $tab[1]['datatype']        = 'itemlink';
       $tab[1]['itemlink_type']   = $this->getType();
       if ($_SESSION['glpiactiveprofile']['interface'] != 'central')
          $tab[1]['searchtype']   = 'contains';

       $tab[2]['table']           = 'glpi_plugin_accounts_accounttypes';
       $tab[2]['field']           = 'name';
       $tab[2]['name']            = __('Type');
       if ($_SESSION['glpiactiveprofile']['interface'] != 'central')
          $tab[2]['searchtype']   = 'contains';
       $tab[2]['datatype']        = 'dropdown';
       
       $tab[30]['table']          = $this->getTable();
       $tab[30]['field']          = 'id';
       $tab[30]['name']           = __('ID');
       $tab[30]['datatype']       = 'number';
       
       return $tab;
    }*/
    
    /**
    * Print the acccount form
    *
    * @param $ID        integer ID of the item
    * @param $options   array
    *     - target for the Form
    *     - withtemplate template or basic computer
    *
    * @return Nothing (display)
    **/
    /*public function showForm($ID, $options = array()) {
        if (!$this->canView()) return false;
        
        $this->check($ID, 'r');
        
        $this->showTabs($options);
        $options["formoptions"] = "id = 'nebackup_form'";
        $this->showFormHeader($options);
       
        //html formulario
        
        Html::closeForm();

        $this->addDivForTabs();

        return true;
    }*/
}
?>