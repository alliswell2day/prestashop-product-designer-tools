<?php

/**
 * 2007-2017 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2017 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class PdpIntegration extends Module{

    private $_html = '';
    private $_postErrors = array();

    public function __construct() {
        $this->name = 'pdpintegration';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'www.magebay.com';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('pdpIntegration');
        $this->description = $this->l('=== PrestaShop PDP Integration ===');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall my module?');
    }

    
    public function install() {
            if (!parent::install() 
                || !$this->installDb()
                || !$this->registerHook('displayHeader') 
                || !$this->registerHook('displayCustomerAccount')
                || !$this->registerHook('moduleRoutes')
                || !$this->registerHook('actionAdminControllerSetMedia')
                || !$this->registerHook('displayProductButtons')) {
                return false;
            }
        return true;
    }
    
    
    
    public function uninstall() {

        if (!$this->uninstallDb()
                || !parent::uninstall()) {
            return false;
        }
        return true;
    }
    
    private function installDb()
    {
        $return = true;
        try {
            $sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'pdp_guest_design` (
              `entity_id` BIGINT UNSIGNED NOT NULL auto_increment,
              `user_id` bigint(20) unsigned NULL,
                `is_active` int(1) NOT NULL DEFAULT 1,
                `customer_is_guest` int(1) NOT NULL DEFAULT 0,
                `item_value` longtext NULL,
                PRIMARY KEY  (entity_id),
                KEY user_id (user_id)
            ) ENGINE=`'._MYSQL_ENGINE_.'` DEFAULT CHARSET=UTF8;';
            $return = (bool) Db::getInstance()->execute($sql);
            return $return;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function uninstallDb()
    {
        try {
                return  Db::getInstance()->execute('DROP TABLE IF EXISTS`'._DB_PREFIX_.'pdp_guest_design`');
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function clearCache()
    {
        parent::_clearCache('displayHeader.tpl', 'pdpintegration-header');
        parent::_clearCache('displayProductButtons.tpl', 'pdpintegration-product-buttons');
        parent::_clearCache('displayShoppingCart.tpl', 'pdpintegration-shopping-cart');
    }

    private function _postValidation() {
        if (Tools::isSubmit('btnSubmit')) {
            
        }
    }
    // <editor-fold defaultstate="collapsed" desc="admin config">
       private function _postProcess() {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('ENABLE_API_CART', Tools::getValue('ENABLE_API_CART'));
            Configuration::updateValue('URL_PDP', Tools::getValue('URL_PDP'));
            Configuration::updateValue('LABEL_BUTTON', Tools::getValue('LABEL_BUTTON'));
        }
        $this->_html .= $this->displayConfirmation($this->l('Settings updated'));
    }

    public function getContent() {
        $this->_html = '';

        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        }

        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    public function renderForm() {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('PrestaShop PDP Settings'),
                    'icon' => 'icon-envelope'
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enabled Api Add Cart '),
                        'name' => 'ENABLE_API_CART',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ),
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Url P+'),
                        'name' => 'URL_PDP',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Label Button Custom'),
                        'name' => 'LABEL_BUTTON',
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save Changes'),
                )
            ),
        );

        $helper = new HelperForm();
        $helper->field_values = array('LABEL_BUTTON' => 'Customize It');
        $helper->show_toolbar = false;
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
        );

        $this->fields_form = array();

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues() {
        return array(
            'ENABLE_API_CART' => Tools::getValue('ENABLE_API_CART', Configuration::get('ENABLE_API_CART')),
            'URL_PDP' => Tools::getValue('URL_PDP', Configuration::get('URL_PDP')),
            'LABEL_BUTTON' => Tools::getValue('LABEL_BUTTON', Configuration::get('LABEL_BUTTON')),
        );
    }
    // </editor-fold>

    public function getEnabledProducts()
    {
        $enable=Tools::getValue('ENABLE_API_CART', Configuration::get('ENABLE_API_CART'));
        if(!$enable){
            return [];
        }
        $product_ids = [];
        
        //select product status=3(live) in Pdp pushed to presashop
        $sql = 'SELECT a.id_product, b.type_id FROM `'._DB_PREFIX_.'product` a,pdp_product_type b WHERE a.reference=b.sku and a.active=1 and b.status=3;';

        if ($results = Db::getInstance()->ExecuteS($sql)) {
            foreach ($results as $row) {
                $product_ids[$row['id_product']] = array(
                    'id_product'=>$row['id_product'],
                    'pdp_id_product'=>$row['type_id'],
                );
            }

            return $product_ids;
        } else {
            return [];
        }
    }
    
    public function hookActionAdminControllerSetMedia($params)
    {

        $this->context->controller->addJS($this->_path . 'views/js/pdpintegrationadmin.js');
    }
    
     public function hookDisplayHeader($params){
        if (!isset($this->context->controller->php_self)) {
            return;
        }
        $valueConfig=$this->getConfigFieldsValues();
        $enabled_products = $this->getEnabledProducts();
        if (Configuration::get('PS_CATALOG_MODE')) {
            if (version_compare(_PS_VERSION_, '1.7', '>=')) {
                $this->context->controller->addJS($this->_path.'views/js/17_pdpintegration_catalogue.js');
            }else{
                $this->context->controller->addJS($this->_path.'views/js/pdpintegration_catalogue.js');
            }
        } else if(count($enabled_products)) {
            if (version_compare(_PS_VERSION_, '1.7', '>=')) {
                 $this->context->controller->addJS($this->_path.'views/js/17_pdpintegration.js');
            }else{
                $this->context->controller->addJS($this->_path.'views/js/pdpintegration.js');
            }
        }
        $this->context->controller->addCSS($this->_path.'views/css/pdpintegration.css', 'all');

        $controller_url = $this->context->link->getModuleLink(
            'pdpintegration',
            'pdp',
            array(),
            true
        );
        $this->smarty->assign(
            array(
                'pdpintegration_enabled_products' => Tools::jsonEncode($enabled_products),
                'pdpintegration_controller' => $controller_url,
                'pdpintegration_button_label' => $valueConfig['LABEL_BUTTON'],
                'pdpintegration_url_pdp' => $valueConfig['URL_PDP'],
            )
        );
        return $this->display(__FILE__, 'displayHeader.tpl', $this->getCacheId('pdpintegration-header'));
    }
    
   
    public function hookDisplayProductButtons($params){
        if (Configuration::get('PS_CATALOG_MODE')) {
            $html = '';
            if (!isset($this->context->controller->php_self)) {
                return;
            }
            $valueConfig=$this->getConfigFieldsValues();
            $enabled_products = $this->getEnabledProducts();
            $id_product = $params['product']->id;
            if (in_array($id_product, $enabled_products)) {
                $this->smarty->assign(array(
                     'pdpintegration_button_label' => $valueConfig['LABEL_BUTTON'],
                ));

                $html .= $this->display(__FILE__, 'displayProductButtons.tpl', $this->getCacheId('pdpintegration-product-buttons'));
            }
            return $html;
        }
    }
    
    public function hookModuleRoutes() {
        return array(
            'module-pdpintegration-pdp' => array(
                'controller' => 'pdp',
                'rule' => 'pdp',
                'keywords' => array(),
                'params' => array(
                    'fc' => 'module',
                    'module' => 'pdpintegration',
                ),
            ),
        );
    }

    
    public function hookDisplayCustomerAccount($params)
    {
        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
            return $this->display(__FILE__, 'PS17_account.tpl');
        } else {
            return $this->display(__FILE__, 'account.tpl');
        }
    }
}
