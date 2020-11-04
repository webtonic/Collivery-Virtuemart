<?php

defined('_JEXEC') or die('Restricted access');

/**
 * Installation script for the plugin
 *
 * @copyright Copyright (C) 2020 MDS Collivery
 * @license GNU/GPL version 3 or later: http://www.gnu.org/copyleft/gpl.html
 */
class plgVmShipmentMds_ShippingInstallerScript {

    var $db;
    var $towns;
    var $services;
    var $location_types;
    var $extension_id;
    var $app_name;
    var $app_info;
    var $suburbs;
    var $password;
    var $username;

    /**
     * Constructor
     *
     * @param JAdapterInstance $adapter The object responsible for running this script
     */
    public function __construct(JAdapterInstance $adapter) {
        // We have to check what php version we have before anything is installed.
        if (version_compare(PHP_VERSION, '7.3.0') < 0) {
            die('Your PHP version is not able to run this plugin, update to the latest version before instaling this plugin. <a href="' . JURI::base() . '">Return</a>');
        }

        // Load the database and execute our sql file
        $this->db = JFactory::getDBO();
        // Cant load any more here because our plugin is not yet installed so we have to do the rest from within install() and uninstall()
    }

    protected function init() {
        // Get information of our plugin so we can pass it on to MDS Collivery for Logs
        $sel_query = "SELECT * FROM `#__extensions` where type = 'plugin' and element = 'mds_shipping' and folder = 'vmshipment';";
        $this->db->setQuery($sel_query);
        $this->db->query();
        $this->extension_id = $this->db->loadObjectList()[0]->extension_id;
        $this->app_name = $this->db->loadObjectList()[0]->extension_id;
        $this->app_info = json_decode($this->db->loadObjectList()[0]->manifest_cache);

        // Get our config
        $sel_query = "SELECT * FROM `#__mds_collivery_config` where id=1;";
        $this->db->setQuery($sel_query);
        $this->db->query();
        $this->password = $this->db->loadObjectList()[0]->password;
        $this->username = $this->db->loadObjectList()[0]->username;

        $version = new JVersion();

        $config = array(
            'app_name' => $this->app_info->name, // Application Name
            'app_version' => $this->app_info->version, // Application Version
 //           'app_host' => 'Joomla: ' . $version->getShortVersion() . ' - Virtuemart: ' . VmConfig::getInstalledVersion(), // Framework/CMS name and version, eg 'Wordpress 3.8.1 WooCommerce 2.0.20' / ''
            'app_url' => JURI::base(), // URL your site is hosted on
            'user_email' => $this->username,
            'user_password' => $this->password
        );

        // Use the MDS API Files
        require_once 'Mds/Cache.php';
        require_once 'Mds/Collivery.php';
        $collivery = new Mds\Collivery($config);

        // Get some information from the API
        $this->towns = $collivery->getTowns();
        $this->services = $collivery->getServices();
        $this->location_types = $collivery->getLocationTypes();
        $this->suburbs = $collivery->getSuburbs("");
    }

    /**
     * Called on installation
     *
     * @param JAdapterInstance $adapter The object responsible for running this script
     * @return  boolean  true on success
     */
    public function install(JAdapterInstance $adapter) {
        $this->init(); // Load MDS Collivery API
        // Wish Joomla had better documentation and support so functions like this are not necessary.
        // This is here to copy all files and folders to their final resting place
        foreach (array('components', 'administrator', 'mds_validation') as $folder) {
            if ($folder == 'components') {
                // this installs our ajax controller for ajax calls
                $source = preg_replace('|com_installer|i', "", JPATH_PLUGINS) . '/vmshipment/mds_shipping/components/com_virtuemart';
                $destination = preg_replace('|com_installer|i', "", JPATH_COMPONENT_SITE) . 'com_virtuemart';
                $this->recurse_copy($source, $destination);
        
            } elseif ($folder == 'administrator') {
                // This installs our administration section
                $source = preg_replace('|com_installer|i', "", JPATH_PLUGINS) . '/vmshipment/mds_shipping/administrator/components/com_virtuemart';
                $destination = preg_replace('|com_installer|i', "", JPATH_COMPONENT_ADMINISTRATOR) . 'com_virtuemart';
                $this->recurse_copy($source, $destination);
            }
        }

        // Lets replace the virtuemart jQuery file for a newer version with migration included
        $jquery_destination = preg_replace('|com_installer|i', "", JPATH_COMPONENT_SITE) . 'com_virtuemart/assets/js/jquery.min.js';
        if (file_exists($jquery_destination)) {
            unlink($jquery_destination);
        }
        $jquery_source = preg_replace('|com_installer|i', "", JPATH_PLUGINS) . '/vmshipment/mds_shipping/jquery.min.js';
        copy($jquery_source, $jquery_destination);

        // Get order module ID so we can add our menu below order in the admin section
        $sel_query = "SELECT * FROM `#__virtuemart_modules` WHERE `module_name`='order';";
        $this->db->setQuery($sel_query);
        $this->db->query();
        $order_module_id = $this->db->loadObjectList()[0]->module_id;

        // Get configuration module ID so we can add our menu below order in the admin section
        $sel_query = "SELECT * FROM `#__virtuemart_modules` WHERE `module_name`='configuration';";
        $this->db->setQuery($sel_query);
        $this->db->query();
        $configuration_module_id = $this->db->loadObjectList()[0]->module_id;

        // Insert our menu extensions
        $adminmenuentries = "(" . $order_module_id . ", 'MDS Confirm Collivery', '', 'vmicon vmicon-16-lorry', 2, 'mds', 'awaiting_dispatch'),";
        $adminmenuentries .= "(" . $order_module_id . ", 'MDS Already Confirmed', '', 'vmicon vmicon-16-lorry', 2, 'mds', 'index'),";
        $adminmenuentries .= "(" . $configuration_module_id . ", 'MDS Collivery Config', '', 'vmicon vmicon-16-config', 1, 'mds', 'config');";
        $adminmenuentries_insert_query = "INSERT INTO `#__virtuemart_adminmenuentries` (`module_id`, `name`, `link`, `icon_class`, `ordering`, `view`, `task`) VALUES " . $adminmenuentries;
        $this->db->setQuery($adminmenuentries_insert_query);
        $this->db->query();

        // Insert our new mds module
        $module_value = "('mds', 'Process shipping: Edit shipping details, add instructions.', 'admin,storeadmin', 1, 1, 3);";
        $module_insert_query = "INSERT INTO `#__virtuemart_modules` (`module_name`, `module_description`, `module_perms`, `published`, `is_admin`, `ordering`) VALUES " . $module_value;
        $this->db->setQuery($module_insert_query);
        $this->db->query();

        // Get South Africa ID
        $sel_query = "SELECT * FROM `#__virtuemart_countries` WHERE `country_name`='South Africa';";
        $this->db->setQuery($sel_query);
        $this->db->query();
        $virtuemart_country_id = $this->db->loadObjectList()[0]->virtuemart_country_id;

        // Create insert values for services
        foreach ($this->services as $service_key => $service_value) {
            $service_insert = "(" . $this->extension_id . ", 'mds_shipping', 'markup=10');";

            // Insert all Services
            $states_insert_query = "INSERT INTO `#__virtuemart_shipmentmethods` (`shipment_jplugin_id`, `shipment_element`, `shipment_params`) VALUES " . $service_insert;
            $this->db->setQuery($states_insert_query);
            $this->db->query();
            $new_virtuemart_shipmentmethod_id = $this->db->insertid();

            // Insert the descriptions
            $service_description_insert = "(" . $new_virtuemart_shipmentmethod_id . ", '" . addslashes($service_value) . "', 'MDS Collivery: " . addslashes($service_value) . "', " . $service_key . ")";
            $states_insert_query = "INSERT INTO `#__virtuemart_shipmentmethods_en_gb` (`virtuemart_shipmentmethod_id`, `shipment_name`, `shipment_desc`, `slug`) VALUES " . $service_description_insert;
            $this->db->setQuery($states_insert_query);
            $this->db->query();
        }

        // Delete all towns under South Africa
        $delete_query = "DELETE FROM `#__virtuemart_states` WHERE `virtuemart_country_id`=" . $virtuemart_country_id . ";";
        $this->db->setQuery($delete_query);
        $this->db->query();

        // Lets create the insert values from the result of getTowns()
        $town_sql = '';
        foreach ($this->towns as $towns_key => $towns_value) {
            $town_sql .= "(" . $virtuemart_country_id . ", '" . addslashes($towns_value) . "'),";
        }
        $town_sql = substr($town_sql, 0, -1) . ';'; // add ; to the end.
        // Insert all the towns
        $states_insert_query = "INSERT INTO `#__virtuemart_states` (`virtuemart_country_id`, `state_name`) VALUES " . $town_sql;
        $this->db->setQuery($states_insert_query);
        $this->db->query();

        // Lets check we have some of the old fields from the old plugin and lets unpublish them
        foreach (array('VM_SUBURB', 'VM_CITY', 'vm_suburb', 'vm_city', 'city') as $old_fields) {
            $this->db->setQuery("SELECT * FROM `#__virtuemart_userfields` WHERE `name`='" . $old_fields . "';");
            $this->db->query();
            if (isset($this->db->loadObjectList()[0])) {
                $this->db->setQuery('UPDATE `#__virtuemart_userfields` SET `published`=0 WHERE `virtuemart_userfield_id` = ' . $this->db->loadObjectList()[0]->virtuemart_userfield_id . ';');
                $this->db->query();
            }
        }

        // Lets check if we have a virtuemart_state_id which our plugin uses
        $this->db->setQuery("SELECT * FROM `#__virtuemart_userfields` WHERE `name`='virtuemart_state_id';");
        $this->db->query();
        if (isset($this->db->loadObjectList()[0])) {
            // If the field exists then lets change the wording
            $this->db->setQuery('UPDATE `#__virtuemart_userfields` SET `title`="City" WHERE `virtuemart_userfield_id` = ' . $this->db->loadObjectList()[0]->virtuemart_userfield_id . ';');
            $this->db->query();
            $fields_array = array('mds_suburb_id', 'mds_building_details', 'mds_location_type');
        } else {
            $fields_array = array('virtuemart_state_id', 'mds_suburb_id', 'mds_building_details', 'mds_location_type');
            $userfields['virtuemart_state_id'] = "('virtuemart_state_id', 'City', '', 'select', '1', '1', '1', '1', '23');";
        }

        // Create some fields needed to get prices from the API, these fields are in shipment address and billing address
        $userfields['mds_suburb_id'] = "('mds_suburb_id', 'Suburb', 'Suburb in town', 'select', '1', '1', '1', '1', '23');";
        $userfields['mds_location_type'] = "('mds_location_type', 'Location Type', 'The type of location', 'select', '1', '1', '1', '1', '17');";
        $userfields['mds_building_details'] = "('mds_building_details', 'Building Details', 'Describe the building', 'text', '0', '1', '1', '1', '17');";
        foreach ($fields_array as $field) {
            $insert_query = "INSERT INTO `#__virtuemart_userfields` (`name`, `title`, `description`, `type`, `required`, `registration`, `shipment`, `account`, `ordering`) VALUES " . $userfields[$field];
            $this->db->setQuery($insert_query);
            $this->db->query();
            $virtuemart_userfield_id = $this->db->insertid();

            if ($field == 'mds_location_type') {
                // If this is location type then lets add the select options
                foreach ($this->location_types as $key => $value) {
                    // Empty value check getLocationTypes() seems to return a blank value
                    if ($value != "") {
                        $insert_values = "(" . $virtuemart_userfield_id . ", '" . addslashes($value) . "', '" . $key . "');";
                        $inser_value_query = "INSERT INTO `#__virtuemart_userfield_values` (`virtuemart_userfield_id`, `fieldtitle`, `fieldvalue`) VALUES " . $insert_values;
                        $this->db->setQuery($inser_value_query);
                        $this->db->query();
                    }
                }
            } elseif ($field == 'mds_suburb_id') {
                // Add all suburbs
                foreach ($this->suburbs as $suburb_key => $suburb_value) {
                    $insert_values = "(" . $virtuemart_userfield_id . ", '" . addslashes($suburb_value) . "', " . $suburb_key . ");";
                    $inser_value_query = "INSERT INTO `#__virtuemart_userfield_values` (`virtuemart_userfield_id`, `fieldtitle`, `fieldvalue`) VALUES " . $insert_values;
                    $this->db->setQuery($inser_value_query);
                    $this->db->query();
                }
            }
        }

        // Get address_2 order number and then try and reorder our address fields
        $this->db->setQuery("SELECT * FROM `#__virtuemart_userfields` WHERE `name`='address_2';");
        $this->db->query();
        if (isset($this->db->loadObjectList()[0])) {
            $order = $this->db->loadObjectList()[0]->ordering;
            foreach (array('virtuemart_country_id', 'virtuemart_state_id', 'mds_suburb_id', 'mds_location_type', 'mds_building_details') as $the_field) {
                $order++;

                // If we have another field with our chosen order number then lets move it down one
                $this->db->setQuery("SELECT * FROM `#__virtuemart_userfields` WHERE `ordering`=" . $order . ";");
                $this->db->query();
                if (isset($this->db->loadObjectList()[0])) {
                    $field_to_move = $this->db->loadObjectList()[0]->virtuemart_userfield_id;
                    $this->db->setQuery('UPDATE `#__virtuemart_userfields` SET `ordering`=' . ($order + 2) . ' WHERE `virtuemart_userfield_id` = ' . $field_to_move . ';');
                    $this->db->query();
                }

                $this->db->setQuery('UPDATE `#__virtuemart_userfields` SET `ordering`=' . $order . ' WHERE `name`="' . $the_field . '";');
                $this->db->query();
            }
        }

        // Enable validation for cell phone.
        $this->db->setQuery('UPDATE `#__virtuemart_userfields` SET `registration` = 1, `shipment` = 1, `account` = 1, `required` = 1 WHERE `name` = "phone_2";');
        $this->db->query();

        // Enable our shipping plugin
        $this->db->setQuery('update #__extensions set enabled = 1 where type = "plugin" and element = "mds_shipping" and folder = "vmshipment"');
        $this->db->query();

        // Get the id of our menu link
        $this->db->setQuery("SELECT * FROM `#__menu` WHERE `alias`='mds-tracking';");
        $this->db->query();
        $menu_id = $this->db->loadObjectList()[0]->id;

        // Set our params aliasoptions to our menu id so there are no clashes
        $params = '{"aliasoptions":"482","menu-anchor_title":"","menu-anchor_css":"","menu_image":""}';
        $this->db->setQuery('UPDATE `#__menu` SET `params` = \'' . addslashes($params) . '\' WHERE `id` = ' . $menu_id . ';');
        $this->db->query();

        return true;
    }

    /**
     * Called on uninstallation
     *
     * @param JAdapterInstance $adapter The object responsible for running this script
     */
    public function uninstall(JAdapterInstance $adapter) {
        $this->init(); // Load MDS Collivery API
        // This is here to delte all files and folders from their resting place
        foreach (array('components', 'administrator', 'mds_validation') as $folder) {
            if ($folder == 'components') {
                // Lets remove the files in the controller folder
                $source = preg_replace('|com_installer|i', "", JPATH_PLUGINS) . '/vmshipment/mds_shipping/components/com_virtuemart';
                $destination = preg_replace('|com_installer|i', "", JPATH_COMPONENT_SITE) . 'com_virtuemart';
                $this->delete_files($source, true, 0, $destination);
            } else {
                // Lets remove the administration folders and files we installed
                $source = preg_replace('|com_installer|i', "", JPATH_PLUGINS) . '/vmshipment/mds_shipping/administrator/components/com_virtuemart';
                $destination = preg_replace('|com_installer|i', "", JPATH_COMPONENT_ADMINISTRATOR) . 'com_virtuemart';
                $this->delete_files($source, true, 0, $destination);
                @rmdir($destination . '/views/mds');
            }
        }

        // Remove our menu extensions
        foreach (array('MDS Confirm Collivery', 'MDS Collivery Config', 'MDS Already Confirmed') as $field) {
            $adminmenuentries_delete_query = "DELETE FROM `#__virtuemart_adminmenuentries` WHERE `name`='" . $field . "';";
            $this->db->setQuery($adminmenuentries_delete_query);
            $this->db->query();
        }

        // Remove our shipping module
        $module_delete_query = "DELETE FROM `#__virtuemart_modules` WHERE `module_name`='mds';";
        $this->db->setQuery($module_delete_query);
        $this->db->query();

        // Get South Africa ID
        $sel_query = "SELECT * FROM `#__virtuemart_countries` WHERE `country_name`='South Africa';";
        $this->db->setQuery($sel_query);
        $this->db->query();
        $virtuemart_country_id = $this->db->loadObjectList()[0]->virtuemart_country_id;

        // Delete all states in south africa with our virtuemart_country_id
        // Might not want to delete these, could cause some issues for the clients if they uninstall.
        $del_query = "DELETE FROM `#__virtuemart_states` WHERE `virtuemart_country_id`=" . $virtuemart_country_id . ";";
        $this->db->setQuery($del_query);
        $this->db->query();

        // Delete all services with our extension_id
        $del_query = "DELETE FROM `#__virtuemart_shipmentmethods` WHERE `shipment_jplugin_id`=" . $this->extension_id . ";";
        $this->db->setQuery($del_query);
        $this->db->query();

        // Delete all services from virtuemart_shipmentmethods_en_gb
        foreach ($this->services as $service_key => $service_value) {
            $del_query = "DELETE FROM `#__virtuemart_shipmentmethods_en_gb` WHERE `shipment_name`='" . $service_value . "';";
            $this->db->setQuery($del_query);
            $this->db->query();
        }

        // Create some fields needed to get prices from the API, these fields are in shipment address and billing address
        foreach (array('mds_suburb_id', 'mds_building_details', 'mds_location_type') as $field) {
            // Get Userfield ID so we can delete all instances
            $sel_query = "SELECT * FROM `#__virtuemart_userfields` WHERE `name`='" . $field . "';";
            $this->db->setQuery($sel_query);
            $this->db->query();
            $virtuemart_userfield_id = $this->db->loadObjectList()[0]->virtuemart_userfield_id;

            $del_query = "DELETE FROM `#__virtuemart_userfields` WHERE `name`='" . $field . "';";
            $this->db->setQuery($del_query);
            $this->db->query();

            if ($field != 'mds_building_details') {
                $del_query = "DELETE FROM `#__virtuemart_userfield_values` WHERE `virtuemart_userfield_id`='" . $virtuemart_userfield_id . "';";
                $this->db->setQuery($del_query);
                $this->db->query();
            }
        }

  

        // Delete our tracking menu item
        $this->db->setQuery("DELETE FROM `#__menu` WHERE `alias`='mds-tracking';");
        $this->db->query();

        // Remove config table
        $this->db->setQuery('DROP TABLE `#__mds_collivery_config`;');
        $this->db->query();

        // Remove our collivery table
        $this->db->setQuery('DROP TABLE `#__mds_collivery_processed`;');
        $this->db->query();

        // Remove our plugin table
        $this->db->setQuery('DROP TABLE `#__virtuemart_shipment_plg_mds_shipping`;');
        $this->db->query();

        // Remove altertered colums
        $this->db->setQuery('ALTER TABLE `#__virtuemart_order_userinfos` DROP `mds_suburb_id`, DROP `mds_building`, DROP `mds_location_type`;');
        $this->db->query();

        // Remove altertered colums
        $this->db->setQuery('ALTER TABLE `#__virtuemart_userinfos` DROP `mds_suburb_id`, DROP `mds_building`, DROP `mds_location_type`;');
        $this->db->query();
        return true;   


 
    }

    /**
     * Called on instalation to move all our files to their final resting place
     */
     protected function recurse_copy($src, $dst) {
        $dir = opendir($src);
        if (!is_dir($dst)) {
            @mkdir($dst, 0755);
        }
        while (false !== ( $file = readdir($dir) )) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if (is_dir($src . '/' . $file)) {
                    $this->recurse_copy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }
    /**
     * Called on uninstallation to remove all our files and folders from their resting place
     */
    protected function delete_files($path, $del_dir = false, $level = 0, $dst = false) {
        // Trim the trailing slash
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        if ($dst) {
            $dst = rtrim($dst, DIRECTORY_SEPARATOR);
        }

        if (!$current_dir = @opendir($path)) {
            return false;
        }

        while (false !== ( $filename = @readdir($current_dir) )) {
            if ($filename != "." and $filename != "..") {
                if (is_dir($path . DIRECTORY_SEPARATOR . $filename)) {
                    // Ignore empty folders
                    if (substr($filename, 0, 1) != '.') {
                        if ($dst) {
                            $this->delete_files($path . DIRECTORY_SEPARATOR . $filename, $del_dir, $level + 1, $dst . DIRECTORY_SEPARATOR . $filename);
                        } else {
                            $this->delete_files($path . DIRECTORY_SEPARATOR . $filename, $del_dir, $level + 1);
                        }
                    }
                } else {
                    if ($dst) {
                        unlink($dst . DIRECTORY_SEPARATOR . $filename);
                    } else {
                        unlink($path . DIRECTORY_SEPARATOR . $filename);
                    }
                }
            }
        }
        @closedir($current_dir);

        if ($del_dir == true and $level > 0) {
            if ($dst) {
                return @rmdir($dst);
            } else {
                return @rmdir($path);
            }
        }

        return true;
    }

}
