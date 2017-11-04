<?php

class PdpIntegrationPdpModuleFrontController extends ModuleFrontController {

    const pdp_print_type = 'pdpoptions_pdp_print_type';
    const pdp_options = 'pdpoptions';
    const pdp_design = 'pdp_design';
    const pdp_data = 'pdpData';

    protected $array_type_select;

    public function __construct() {
        parent::__construct();
        $this->array_type_select = array('drop_down', 'radio', 'checkbox', 'multiple', 'hidden');
        $this->context = Context::getContext();
        $this->id_product = (int) Tools::getValue('id_product', null);
        $this->id_product_attribute = (int) Tools::getValue('id_product_attribute', Tools::getValue('ipa'));
        $this->customization_id = (int) Tools::getValue('id_customization');
        $this->qty = abs(Tools::getValue('qty', 1));
        $this->id_address_delivery = (int) Tools::getValue('id_address_delivery');
    }

    // <editor-fold defaultstate="collapsed" desc="frontend customer">
    public function initContent() {
        parent::initContent();
        $orders = Order::getCustomerOrders($this->context->customer->id);
        $enabled_products = $this->getEnabledProducts();
        if ($orders) {
            $data = array();
            foreach ($orders as &$ord) {
                $order = new Order((int) $ord['id_order']);
                $history = $order->getHistory($this->context->language->id, false, true);
                $product = $order->getProducts();
                $customizedData = Product::getAllCustomizedDatas((int) $order->id_cart);
                Product::addCustomizationPrice($product, $customizedData);
                OrderReturn::addReturnedQuantity($product, $order->id);
                $checkPdp = false;
                foreach ($product as &$pro) {
                    if ($enabled_products[$pro['product_id']]) {
                        $checkPdp = true;
                    }
                }
                $new = array(
                    'products' => $product,
                    'history' => $history,
                );
                if ($checkPdp) {
                    array_push($data, $new);
                }
            }
        }

        $guestDesign = $this->getDesignByCustomerId($this->context->customer->id);
        if (!empty($guestDesign)) {
            $data_item_value = unserialize($guestDesign['item_value']);
            if (is_array($data_item_value)) {
                $guestdesigns = array();
                foreach ($data_item_value as $__item) {
                    $link_edit_design = '#';
                    if (isset($__item['design_id']) && $__item['design_id'] && isset($__item['product_id']) && $__item['product_id']) {
                        $design_json = $this->getDesignByDesignId($__item['design_id']);
                        if (!empty($design_json)) {
                            $url_tool = Tools::getValue('URL_PDP', Configuration::get('URL_PDP'));
                            if ($design_json['side_thumb']) {
                                $side_thubms = unserialize($design_json['side_thumb']);
                                $content_html = '<strong style="margin-bottom:5px;display:block;">' . $this->module->l('Customized Design:') . '</strong>';
                                $content_html .= '<ul class="items">';
                                $i = 0;
                                foreach ($side_thubms as $side_thumb) {
                                    if ($side_thumb['thumb']) {
                                        $i++;
                                        $last = $i % 2 == 0 ? 'last' : '';
                                        $content_html .= '<li style="display:inline-block;margin-right:5px;" class="item ' . $last . '"><a href="' . $url_tool . '/' . $side_thumb['thumb'] . '" target="_blank"><img style="border:1px solid #C1C1C1;" width="66" src="' . $url_tool . '/' . $side_thumb['thumb'] . '" /></a></li>';
                                    }
                                }
                                $content_html .= '</ul>';
                                $product = $this->getProductById((int) $__item['product_id']);
                                if ($product) {
                                    $link_edit_design = $this->getLinkEditDesignFrontend($__item['design_id'], $product['reference'], $url_tool);
                                    if ($enabled_products[$__item['product_id']]) {
                                        $guestdesigns[$__item['product_id']] = array(
                                            'product' => $product,
                                            'link_edit_design' => $link_edit_design,
                                            'content_html' => $content_html,
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        $this->context->smarty->assign(array(
            'orders' => $data,
            'guestdesigns' => $guestdesigns,
            'CUSTOMIZE_TEXTFIELD' => Product::CUSTOMIZE_TEXTFIELD,
            'CUSTOMIZE_FILE' => Product::CUSTOMIZE_FILE,
            'PDPEdit' => 'PDPEdit',
        ));
        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
            $this->setTemplate('module:pdpintegration/views/templates/front/PS17_designAll.tpl');
        } else {
            $this->setTemplate('designAll.tpl');
        }
    }

    private function getEnabledProducts() {
        $enable = Tools::getValue('ENABLE_API_CART', Configuration::get('ENABLE_API_CART'));
        if (!$enable) {
            return [];
        }
        $product_ids = [];

        //select product status=3(live) in Pdp pushed to presashop
        $sql = 'SELECT a.id_product, b.type_id FROM `' . _DB_PREFIX_ . 'product` a,pdp_product_type b WHERE a.reference=b.sku and a.active=1 and b.status=3;';

        if ($results = Db::getInstance()->ExecuteS($sql)) {
            foreach ($results as $row) {
                $product_ids[$row['id_product']] = array(
                    'id_product' => $row['id_product'],
                    'pdp_id_product' => $row['type_id'],
                );
            }

            return $product_ids;
        } else {
            return [];
        }
    }

// </editor-fold>
    public function postProcess() {
        if (Tools::getValue('addGuestDesign')) {
            $this->ajaxProcessAddGuestRequest();
        } else if (Tools::getValue('addPdpToCart')) {
            $this->ajaxProcessAddPdpToCartRequest();
        }
    }

    // <editor-fold defaultstate="collapsed" desc="add to cart from pdp">
    public function ajaxProcessAddPdpToCartRequest() {
        $validationErrors = [];
        $status_api = Configuration::get('ENABLE_API_CART');
        $data = array();
        if ($status_api) {
            $_request = json_decode(file_get_contents('php://input'), true);
            if (isset($_request['pdpItem'])) {
                $request = $_request['pdpItem'];
                if (isset($request['entity_id']) && isset($request['sku']) && isset($request['design_id'])) {
                    $product_sku = $request['sku'];
                    $productLst = $this->getProductIdByReference($product_sku);
                    if (count($productLst) > 0) {
                        $product_id = $productLst['id_product'];
                        $this->id_product = $product_id;
                        if (isset($request['qty']) && $request['qty']) {
                            $quantity = $request['qty'];
                        } else {
                            $quantity = 1;
                        }
                        $this->qty = $quantity;
                        $cart_item_data = $this->prepareCartItemData($request, $product_id);
                        if (isset($request['item_id'])) {
                            $this->context->cart->deleteProduct($product_id, null, (int) $request['item_id']);
                        }
                        $this->addToCart($product_id, $quantity, $cart_item_data);
                        if (!$this->errors) {
                            $url=$this->context->link->getPageLink('cart');
                            if (version_compare(_PS_VERSION_, '1.7', '>=')) {
                                $url.='?action=show';
                            }
                            $arr = array(
                                'status' => true,
                                'message' => $this->module->l('Add product to cart Prestashop success!'),
                                'url' => $url
                            );
                        } else {
                            $validationErrors[] = $this->module->l('Can not add product to cart');
                            $arr = array(
                                'result' => 'error',
                                'status' => 'false',
                                'errors' => $validationErrors,
                            );
                        }
                    } else {
                        $validationErrors[] = $this->module->l('Can not add product to cart. Product not exists');
                        $arr = array(
                            'result' => 'error',
                            'status' => 'false',
                            'errors' => $validationErrors,
                        );
                    }
                } else {
                    if (!isset($request['design_id'])) {
                        $validationErrors[] = $this->module->l('Can not add product to cart. Design not exists');
                    } else {
                        $validationErrors[] = $this->module->l('Can not add product to cart. Product not exists');
                    }
                    $arr = array(
                        'result' => 'error',
                        'status' => 'false',
                        'errors' => $validationErrors,
                    );
                }
            } else {
                $validationErrors[] = $this->module->l('can not add product to cart. Something when wrong');
                $arr = array(
                    'result' => 'error',
                    'status' => 'false',
                    'errors' => $validationErrors,
                );
            }
        } else {
            $validationErrors[] = $this->module->l('Can not add product to cart. Please enable api ajax cart');
            $arr = array(
                'result' => 'error',
                'status' => 'false',
                'errors' => $validationErrors,
            );
        }
        $json = Tools::jsonEncode($arr);
        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
            parent::ajaxDie($json);
        } else {
            die($json);
        }
    }

    // </editor-fold>
    // <editor-fold defaultstate="collapsed" desc="create custom option">


    private function _createLabel($languages, $type, $product_id, $label, $value) {
        // Label insertion
        if (!Db::getInstance()->execute('
			INSERT INTO `' . _DB_PREFIX_ . 'customization_field` (`id_product`, `type`, `required`)
			VALUES (' . (int) $product_id . ', ' . (int) $type . ', 0)') ||
                !$id_customization_field = (int) Db::getInstance()->Insert_ID()) {
            return false;
        }

        // Multilingual label name creation
        $values = '';

        foreach ($languages as $language) {
            foreach (Shop::getContextListShopID() as $id_shop) {
                $values .= '(' . (int) $id_customization_field . ', ' . (int) $language['id_lang'] . ', ' . $id_shop . ',\'' . $label . '\'), ';
            }
        }

        $values = rtrim($values, ', ');
        if (!Db::getInstance()->execute('
			INSERT INTO `' . _DB_PREFIX_ . 'customization_field_lang` (`id_customization_field`, `id_lang`, `id_shop`, `name`)
			VALUES ' . $values)) {
            return false;
        }

        // Set cache of feature detachable to true
        Configuration::updateGlobalValue('PS_CUSTOMIZATION_FEATURE_ACTIVE', '1');
        if (!ObjectModel::updateMultishopTable('product', array('customizable' => 2), 'a.id_product = ' . (int) $product_id)) {
            return false;
        }
        if ($type == Product::CUSTOMIZE_TEXTFIELD) {
            $this->context->cart->addTextFieldToProduct($product_id, $id_customization_field, Product::CUSTOMIZE_TEXTFIELD, $value);
        } else {
            $this->moveUploadDesign($product_id, $id_customization_field, $value);
        }
        return true;
    }

    private function prepareCustomDataNew($cart_item_data, $product_id) {
        $languages = Language::getLanguages();
        $countTxt = 0;
        $countImg = 0;
        foreach ($cart_item_data as $key => $customData) {
            $data = unserialize($customData);
            if ($key == self::pdp_print_type) {
                $countTxt++;
                if (!$this->_createLabel($languages, Product::CUSTOMIZE_TEXTFIELD, $product_id, $data['label'], $data['value'])) {
                    return false;
                }
            } else if ($key == self::pdp_options) {
                foreach ($data as $option) {
                    if ($option['label']) {
                        $countTxt++;
                        if (!$this->_createLabel($languages, Product::CUSTOMIZE_TEXTFIELD, $product_id, $option['label'], $option['value'])) {
                            return false;
                        }
                    }
                }
            } else if ($key == self::pdp_design) {
                $design = $this->getDesignByDesignId((int) $customData);
                if (!empty($design)) {
                    if ($design['side_thumb']) {
                        $side_thubms = unserialize($design['side_thumb']);
                        foreach ($side_thubms as $side_thumb) {
                            if ($side_thumb['thumb']) {
                                $countImg++;
                                if (!$this->_createLabel($languages, Product::CUSTOMIZE_FILE, $product_id, 'Customize Designs', $side_thumb['thumb'])) {
                                    return false;
                                }
                            }
                        }
                    }
                }
            } else if ($key == self::pdp_data) {
                if ($data['design_url']) {
                    $countTxt++;
                    $values = $data['design_url'];
                    if (!$this->_createLabel($languages, Product::CUSTOMIZE_TEXTFIELD, $product_id, 'PDPEdit', $values)) {
                        return false;
                    }
                }
                if ($data['custom_price'] && $data['custom_price'] > 0) {
                    $countTxt++;
                    $values = $data['custom_price'];
                    if (!$this->_createLabel($languages, Product::CUSTOMIZE_TEXTFIELD, $product_id, 'Custom Cost', $values)) {
                        return false;
                    }
                }
            }
        }
        $product = new Product($product_id);
        //change the product properties
        $product->customizable = $countTxt > 0 ? 1 : 0;
        $product->text_fields = $countTxt;
        $product->uploadable_files = $countImg;
        //save the changes
        $product->save();
    }

    private function moveUploadDesign($product_id, $id_customization_field, $urlFile) {
        $thumbContent = file_get_contents(Configuration::get('URL_PDP') . '/' . $urlFile);
        $file_name = md5(uniqid(rand(), true));
        $product_picture_width = (int) Configuration::get('PS_PRODUCT_PICTURE_WIDTH');
        $product_picture_height = (int) Configuration::get('PS_PRODUCT_PICTURE_HEIGHT');
        $tmp_name = tempnam(_PS_TMP_IMG_DIR_, 'PS');
        if (!$tmp_name || !file_put_contents($tmp_name, $thumbContent)) {
            return false;
        }
        /* Original file */
        if (!ImageManager::resize($tmp_name, _PS_UPLOAD_DIR_ . $file_name)) {
            $this->errors[] = Tools::displayError('An error occurred during the image upload process.');
        }
        /* A smaller one */ elseif (!ImageManager::resize($tmp_name, _PS_UPLOAD_DIR_ . $file_name . '_small', $product_picture_width, $product_picture_height)) {
            $this->errors[] = Tools::displayError('An error occurred during the image upload process.');
        } elseif (!chmod(_PS_UPLOAD_DIR_ . $file_name, 0777) || !chmod(_PS_UPLOAD_DIR_ . $file_name . '_small', 0777)) {
            $this->errors[] = Tools::displayError('An error occurred during the image upload process.');
        } else {
            $this->context->cart->addPictureToProduct($product_id, $id_customization_field, Product::CUSTOMIZE_FILE, $file_name);
        }
        unlink($tmp_name);
        return true;
    }

    // </editor-fold>
    // <editor-fold defaultstate="collapsed" desc="prepare custom option">
    private function prepareCartItemData($request, $product_id) {
        $cart_item_data = array();
        if (!empty($request)) {
            $new_value = array();
            $pdpData = array();
            if (isset($request['design_url'])) {
                $pdpData['design_url'] = $request['design_url'];
            }
            $custom_price = 0;
            if (isset($request['pdp_print_type'])) {
                $print_type = $request['pdp_print_type'];
                if (isset($print_type['value']) && isset($print_type['title']) && $print_type['value'] && $print_type['title']) {
                    $new_value['pdpoptions_pdp_print_type'] = serialize(array('label' => $this->module->l('Print type'), 'value' => $this->module->l($print_type['title'])));
                }
                if (isset($print_type['price'])) {
                    $custom_price += $print_type['price'];
                }
            }

            if (isset($request['product_color'])) {
                $productColor = $request['product_color'];
                $new_value['pdpoptions_product_color'] = serialize(array('label' => $this->module->l('Color'), 'value' => $this->module->l($productColor['color_name'])));
                if (isset($productColor['color_price']) && $productColor['color_price']) {
                    $custom_price += $productColor['color_price'];
                }
            }

            if (isset($request['pdp_options'])) {
                $_pdpOptSelect = $this->get_options_select($request['pdp_options']);

                $pdpOptSelect = $_pdpOptSelect['options'];
                $infoRequest = $this->get_optinfor_request($pdpOptSelect);
                $additionalOptions = $this->get_addition_option($pdpOptSelect);
                if (isset($infoRequest['pdp_price'])) {
                    $custom_price += $infoRequest['pdp_price'];
                }
                $new_value['pdpoptions'] = serialize($additionalOptions);
            }

            $pdpData['custom_price'] = $custom_price;
            if (isset($request['design_id'])) {
                $pdpData['design_id'] = $request['design_id'];
                $new_value['pdpData'] = serialize($pdpData);
                $new_value['pdp_design'] = $pdpData['design_id'];
            }
            return array_merge($cart_item_data, $new_value);
        }
        return $cart_item_data;
    }

    // </editor-fold>
    // <editor-fold defaultstate="collapsed" desc="ajaxProcessAddGuestRequest">
    public function ajaxProcessAddGuestRequest() {
        session_start();
        $validationErrors = [];
        $_request = json_decode(file_get_contents('php://input'), true);
        if (!isset($_request['pdpDesignItem'])) {
            $validationErrors[] = $this->module->l('pdpDesignItem Required');
        }
        $request = $_request['pdpDesignItem'];
        if (!isset($request['design_id'])) {
            $validationErrors[] = $this->module->l('Design Id Required');
        }
        if (!isset($request['product_id'])) {
            $validationErrors[] = $this->module->l('Product Id Required');
        }
        if (!isset($request['product_sku'])) {
            $validationErrors[] = $this->module->l('Product Sku Required');
        }
        if (!count($validationErrors)) {
            $designId = $request['design_id'];
            $productIdPdp = $request['product_id'];
            $productSku = $request['product_sku'];

            $id_product = $this->getProductIdByReference($productSku);
            if (count($id_product) > 0) {
                $item_value = array(
                    'product_id' => (int) $id_product['id_product'],
                    'pdp_product_id' => $productIdPdp,
                    'design_id' => $designId
                );
                $isLogger = $this->context->customer->isLogged();
                if ($isLogger) {
                    $user_id = $this->context->customer->id;
                    $pdp_guest_design_id = isset($_SESSION['pdp_integration_guest_design']) ? $_SESSION['pdp_integration_guest_design'] : null;
                    $dataguestdesign = array('user_id' => $user_id);
                    if (is_null($pdp_guest_design_id)) {
                        $data_guest_design = $this->getDesignByCustomerId($user_id);
                        if (!empty($data_guest_design)) {
                            if ($data_guest_design['entity_id']) {
                                $data_item_value = unserialize($data_guest_design['item_value']);
                                if (is_array($data_item_value)) {
                                    $update = false;
                                    foreach ($data_item_value as $__item) {
                                        if ($__item['product_id'] == $item_value['product_id'] && $__item['pdp_product_id'] == $item_value['pdp_product_id'] && $__item['design_id'] == $item_value['design_id']) {
                                            $update = true;
                                            break;
                                        }
                                    }
                                    if (!$update) {
                                        $data_item_value[] = $item_value;
                                    }
                                    $dataguestdesign['item_value'] = serialize($data_item_value);
                                } else {
                                    $dataguestdesign['item_value'] = serialize([$item_value]);
                                }
                                $this->updateGuestDesignByUserId($dataguestdesign, $user_id);
                            }
                        } else {
                            $dataguestdesign['customer_is_guest'] = 0;
                            $dataguestdesign['item_value'] = serialize([$item_value]);
                            $this->insertGuestDesign($dataguestdesign);
                        }
                    } else {
                        session_unset('pdp_integration_guest_design');
                    }
                } else {
                    $pdp_guest_design_id = isset($_SESSION['pdp_integration_guest_design']) ? $_SESSION['pdp_integration_guest_design'] : null;
                    if (is_null($pdp_guest_design_id)) {
                        $dataguestdesign = array(
                            'customer_is_guest' => 1,
                            'item_value' => serialize([$item_value]),
                        );
                        $guest_design_id = $this->insertGuestDesign($dataguestdesign);
                        $_SESSION['pdp_integration_guest_design'] = $guest_design_id;
                    } else {
                        $data_guest_design = $this->getGuestDesignById($pdp_guest_design_id);
                        $dataguestdesign = array(
                            'entity_id' => $pdp_guest_design_id,
                            'customer_is_guest' => 1,
                        );
                        if (!empty($data_guest_design)) {
                            if ($data_guest_design['entity_id']) {
                                $data_item_value = unserialize($data_guest_design['item_value']);
                                if (is_array($data_item_value)) {
                                    $update = false;
                                    foreach ($data_item_value as $__item) {
                                        if ($__item['product_id'] == $item_value['product_id'] && $__item['pdp_product_id'] == $item_value['pdp_product_id'] && $__item['design_id'] == $item_value['design_id']) {
                                            $update = true;
                                            break;
                                        }
                                    }
                                    if (!$update) {
                                        $data_item_value[] = $item_value;
                                    }
                                    $dataguestdesign['item_value'] = serialize($data_item_value);
                                } else {
                                    $dataguestdesign['item_value'] = serialize([$item_value]);
                                }
                                $this->updateGuestDesignByEntityId($dataguestdesign, $pdp_guest_design_id);
                            }
                        }
                    }
                }
                $json = Tools::jsonEncode(array(
                            'result' => 'success',
                            'status' => 'true',
                            'response' => $this->module->l('Add guest design success!.'),
                ));
                if (version_compare(_PS_VERSION_, '1.7', '>=')) {
                    parent::ajaxDie($json);
                } else {
                    die($json);
                }
            } else {
                $validationErrors[] = $this->module->l('Can not add product to cart. Product not exists');
                $arr = array(
                    'result' => 'error',
                    'status' => 'false',
                    'errors' => $validationErrors,
                );
                $json = Tools::jsonEncode($arr);
                if (version_compare(_PS_VERSION_, '1.7', '>=')) {
                    parent::ajaxDie($json);
                } else {
                    die($json);
                }
            }
        } else {
            $validationErrors[] = $this->module->l('Required field missing.');
            $arr = array(
                'result' => 'error',
                'status' => 'false',
                'errors' => $validationErrors,
            );
            $json = Tools::jsonEncode($arr);
            if (version_compare(_PS_VERSION_, '1.7', '>=')) {
                parent::ajaxDie($json);
            } else {
                die($json);
            }
        }
    }

// </editor-fold>
    // <editor-fold defaultstate="collapsed" desc="custom option data">

    private function get_options_select(array $options) {
        $_result = array('multiSize' => false, 'multiSizeOpt' => array(), 'options' => array());
        $result = array();
        $_key = 0;
        foreach ($options as $key => $val) {
            if (!$val['disabled']) {
                if (in_array($val['type'], $this->array_type_select)) {
                    $_result['multiSize'] = $this->check_multiple_size($val);
                    $flag = false;
                    $optVal = array();
                    foreach ($val['values'] as $opt_key => $opt_val) {
                        if (intval($opt_val['checked']) && $opt_val['selected'] && !$opt_val['disabled']) {
                            $optVal[] = $opt_val;
                            $flag = true;
                        }
                    }
                    if ($flag) {
                        if ($_result['multiSize']) {
                            $_result['multiSizeOpt'] = $val;
                            $_result['multiSizeOpt']['values'] = $optVal;
                        } else {
                            $result[$_key] = $val;
                            $result[$_key]['values'] = $optVal;
                            $_key++;
                        }
                    } else {
                        $_result['multiSize'] = false;
                    }
                } elseif ($val['type'] == 'field' || $val['type'] == 'area') {
                    if ($val['default_text']) {
                        $result[$_key] = $val;
                        $_key++;
                    }
                } elseif ($val['type'] == 'file') {
                    
                }
            }
        }
        $_result['options'] = $result;
        return $_result;
    }

    private function check_multiple_size(array $value) {
        if (isset($value['qnty_input']) && $value['qnty_input']) {
            if (isset($value['type']) && $value['type'] == 'checkbox') {
                return true;
            }
        }
        return false;
    }

    private function get_optinfor_request(array $options) {
        $infoRequest = array(
            'pdp_options' => array(),
            'pdp_price' => 0
        );
        $pdpPrice = 0;
        foreach ($options as $key => $val) {
            $optId = $val['option_id'];
            if (in_array($val['type'], $this->array_type_select)) {
                $qty_input = false;
                $value = array();
                if ($val['qnty_input']) {
                    $qty_input = true;
                }
                foreach ($val['values'] as $_key => $_val) {
                    if (intval($_val['checked']) && $_val['selected'] && !$_val['disabled']) {
                        $value[] = $_val['option_type_id'];
                        if (intval($_val['qty']) > 1 && $qty_input) {
                            $pdpPrice = $pdpPrice + floatval($_val['price']) * intval($_val['qty']);
                        } else {
                            $pdpPrice += floatval($_val['price']);
                        }
                    }
                }
                $infoRequest['pdp_options'][$optId] = implode(",", $value);
            } elseif ($val['type'] == 'field' || $val['type'] == 'area') {
                if ($val['default_text']) {
                    $infoRequest['pdp_options'][$optId] = $val['default_text'];
                    $pdpPrice += floatval($val['price']);
                }
            } elseif ($val['type'] == 'file') {
                
            }
            $infoRequest['pdp_price'] = $pdpPrice;
        }
        return $infoRequest;
    }

    private function get_addition_option(array $options) {
        $additionalOptions = array();
        foreach ($options as $key => $val) {
            $item = array(
                'id' => $val['option_id'],
                'label' => $this->module->l($val['title']),
                'value' => ''
            );
            if (in_array($val['type'], $this->array_type_select)) {
                $value = array();
                foreach ($val['values'] as $_key => $_val) {
                    if (intval($_val['checked']) && $_val['selected'] && !$_val['disabled']) {
                        $value[] = $this->module->l($_val['title']);
                    }
                }
                $item['value'] = implode(",", $value);
            } elseif ($val['type'] == 'field' || $val['type'] == 'area') {
                if ($val['default_text']) {
                    $item['value'] = $val['default_text'];
                }
            } elseif ($val['type'] == 'file') {
                
            }
            $additionalOptions[] = $item;
        }
        return $additionalOptions;
    }

    // </editor-fold>
    // <editor-fold defaultstate="collapsed" desc="add to cart">
    private function addToCart($product_id, $quantity, $cart_item_data) {
        $product = new Product($product_id, true, $this->context->language->id);
        if (!$product->id || !$product->active || !$product->checkAccess($this->context->cart->id_customer)) {
            $this->errors[] = Tools::displayError('This product is no longer available.', !Tools::getValue('ajax'));
            return;
        }
        $qty_to_check = $quantity;
        $cart_products = $this->context->cart->getProducts();
        if (is_array($cart_products)) {
            foreach ($cart_products as $cart_product) {
                if ((!isset($this->id_product_attribute) || $cart_product['id_product_attribute'] == $this->id_product_attribute) &&
                        (isset($this->id_product) && $cart_product['id_product'] == $this->id_product)) {
                    $qty_to_check = $cart_product['cart_quantity'];
                    if (Tools::getValue('op', 'up') == 'down') {
                        $qty_to_check -= $this->qty;
                    } else {
                        $qty_to_check += $this->qty;
                    }
                    break;
                }
            }
        }
        // Check product quantity availability
        if ($this->id_product_attribute) {
            if (!Product::isAvailableWhenOutOfStock($product->out_of_stock) && !Attribute::checkAttributeQty($this->id_product_attribute, $qty_to_check)) {
                $this->errors[] = Tools::displayError('There isn\'t enough product in stock.', !Tools::getValue('ajax'));
            }
        } elseif ($product->hasAttributes()) {
            $minimumQuantity = ($product->out_of_stock == 2) ? !Configuration::get('PS_ORDER_OUT_OF_STOCK') : !$product->out_of_stock;
            $this->id_product_attribute = Product::getDefaultAttribute($product->id, $minimumQuantity);
            // @todo do something better than a redirect admin !!
            if (!$this->id_product_attribute) {
                Tools::redirectAdmin($this->context->link->getProductLink($product));
            } elseif (!Product::isAvailableWhenOutOfStock($product->out_of_stock) && !Attribute::checkAttributeQty($this->id_product_attribute, $qty_to_check)) {
                $this->errors[] = Tools::displayError('There isn\'t enough product in stock.', !Tools::getValue('ajax'));
            }
        } elseif (!$product->checkQty($qty_to_check)) {
            $this->errors[] = Tools::displayError('There isn\'t enough product in stock.', !Tools::getValue('ajax'));
        }

        // Add cart if no cart found
        if (!$this->context->cart->id) {
            if (Context::getContext()->cookie->id_guest) {
                $guest = new Guest(Context::getContext()->cookie->id_guest);
                $this->context->cart->mobile_theme = $guest->mobile_theme;
            }
            $this->context->cart->add();
            if ($this->context->cart->id) {
                $this->context->cookie->id_cart = (int) $this->context->cart->id;
            }
        }

        $this->prepareCustomDataNew($cart_item_data, $product_id);
        
        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
            $result = Db::getInstance()->executeS(
                    'SELECT `id_customization`
                            FROM `' . _DB_PREFIX_ . 'customization`
                            WHERE `id_cart` = ' . (int) $this->context->cart->id . '
                            AND `id_product` = ' . (int) $product_id . '
                            AND `in_cart` = 0 order by id_customization desc');
            if($result && count($result)>0){
                $this->customization_id=$result[0]['id_customization'];
            }
        }
        // Check customizable fields
        if (!$product->hasAllRequiredCustomizableFields() && !$this->customization_id) {
            $this->errors[] = Tools::displayError('Please fill in all of the required fields, and then save your customizations.', !Tools::getValue('ajax'));
        }
        if (!$this->errors) {
            $cart_rules = $this->context->cart->getCartRules();
            $available_cart_rules = CartRule::getCustomerCartRules($this->context->language->id, (isset($this->context->customer->id) ? $this->context->customer->id : 0), true, true, true, $this->context->cart, false, true);
            $update_quantity = $this->context->cart->updateQty($this->qty, $this->id_product, $this->id_product_attribute, $this->customization_id, Tools::getValue('op', 'up'), $this->id_address_delivery);
            if ($update_quantity < 0) {
                // If product has attribute, minimal quantity is set with minimal quantity of attribute
                $minimal_quantity = ($this->id_product_attribute) ? Attribute::getAttributeMinimalQty($this->id_product_attribute) : $product->minimal_quantity;
                $this->errors[] = sprintf(Tools::displayError('You must add %d minimum quantity', !Tools::getValue('ajax')), $minimal_quantity);
            } elseif (!$update_quantity) {
                $this->errors[] = Tools::displayError('You already have the maximum quantity available for this product.', !Tools::getValue('ajax'));
            } elseif ((int) Tools::getValue('allow_refresh')) {
                // If the cart rules has changed, we need to refresh the whole cart
                $cart_rules2 = $this->context->cart->getCartRules();
                if (count($cart_rules2) != count($cart_rules)) {
                    $this->ajax_refresh = true;
                } elseif (count($cart_rules2)) {
                    $rule_list = array();
                    foreach ($cart_rules2 as $rule) {
                        $rule_list[] = $rule['id_cart_rule'];
                    }
                    foreach ($cart_rules as $rule) {
                        if (!in_array($rule['id_cart_rule'], $rule_list)) {
                            $this->ajax_refresh = true;
                            break;
                        }
                    }
                } else {
                    $available_cart_rules2 = CartRule::getCustomerCartRules($this->context->language->id, (isset($this->context->customer->id) ? $this->context->customer->id : 0), true, true, true, $this->context->cart, false, true);
                    if (count($available_cart_rules2) != count($available_cart_rules)) {
                        $this->ajax_refresh = true;
                    } elseif (count($available_cart_rules2)) {
                        $rule_list = array();
                        foreach ($available_cart_rules2 as $rule) {
                            $rule_list[] = $rule['id_cart_rule'];
                        }
                        foreach ($cart_rules2 as $rule) {
                            if (!in_array($rule['id_cart_rule'], $rule_list)) {
                                $this->ajax_refresh = true;
                                break;
                            }
                        }
                    }
                }
            }
        }
    }

    // </editor-fold>
// <editor-fold defaultstate="collapsed" desc="database">
    private function getProductIdByReference($sku) {
        $result = [];
        $sql = 'SELECT id_product FROM `' . _DB_PREFIX_ . 'product` WHERE active=1 and reference="' . $sku . '"';
        if ($results = Db::getInstance()->ExecuteS($sql)) {
            foreach ($results as $row) {
                $result = $row;
            }
            return $result;
        } else {
            return [];
        }
    }

    private function getProductById($id) {
        $result = [];
        $sql = 'SELECT a.*,b.name FROM `' . _DB_PREFIX_ . 'product` a,`' . _DB_PREFIX_ . 'product_lang` b WHERE a.id_product=b.id_product and a.active=1 and a.id_product=' . $id;
        if ($results = Db::getInstance()->ExecuteS($sql)) {
            foreach ($results as $row) {
                $result = $row;
            }
            return $result;
        } else {
            return [];
        }
    }

    private function getDesignByCustomerId($customerId) {
        $result = [];
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'pdp_guest_design` WHERE user_id="' . $customerId . '"';
        if ($results = Db::getInstance()->ExecuteS($sql)) {
            foreach ($results as $row) {
                $result = $row;
            }
            return $result;
        } else {
            return [];
        }
    }

    private function getDesignByDesignId($designId) {
        $result = [];
        $sql = 'SELECT * FROM `pdp_design_json` WHERE design_id="' . $designId . '"';
        if ($results = Db::getInstance()->ExecuteS($sql)) {
            foreach ($results as $row) {
                $result = $row;
            }
            return $result;
        } else {
            return [];
        }
    }

    private function getGuestDesignById($entityId) {
        $result = [];
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'pdp_guest_design` WHERE entity_id="' . $entityId . '"';
        if ($results = Db::getInstance()->ExecuteS($sql)) {
            foreach ($results as $row) {
                $result = $row;
            }
            return $result;
        } else {
            return [];
        }
    }

    private function insertGuestDesign($data) {
        Db::getInstance()->insert(
                'pdp_guest_design', array(
            $data
                )
        );
        return Db::getInstance()->Insert_ID();
    }

    private function createCustomizationField($data) {

        $languages = Language::getLanguages();
        // Label insertion
        if (!Db::getInstance()->execute('
			INSERT INTO `' . _DB_PREFIX_ . 'customization_field` (`id_product`, `type`, `required`)
			VALUES (' . (int) $data['id_product'] . ', ' . (int) $data['type'] . ', 0)') ||
                !$id_customization_field = (int) Db::getInstance()->Insert_ID()) {
            return false;
        }

        // Multilingual label name creation
        $values = '';

        foreach ($languages as $language) {
            foreach (Shop::getContextListShopID() as $id_shop) {
                $values .= '(' . (int) $id_customization_field . ', ' . (int) $language['id_lang'] . ', ' . $id_shop . ',\'' . $data['name'] . '\'), ';
            }
        }

        $values = rtrim($values, ', ');
        if (!Db::getInstance()->execute('
			INSERT INTO `' . _DB_PREFIX_ . 'customization_field_lang` (`id_customization_field`, `id_lang`, `id_shop`, `name`)
			VALUES ' . $values)) {
            return false;
        }

        $this->context->cart->addTextFieldToProduct($data['id_product'], $id_customization_field, Product::CUSTOMIZE_TEXTFIELD, $data['value']);

        // Set cache of feature detachable to true
        Configuration::updateGlobalValue('PS_CUSTOMIZATION_FEATURE_ACTIVE', '1');

        return true;
    }

    private function updateGuestDesignByEntityId($data, $entityId) {
        if (isset($data['entity_id'])) {
            unset($data['entity_id']);
        }
        Db::getInstance()->update(
                'pdp_guest_design', $data
                , 'entity_id="' . $entityId . '"'
        );
    }

    private function updateGuestDesignByUserId($data, $userId) {
        if (isset($data['user_id'])) {
            unset($data['user_id']);
        }
        Db::getInstance()->update(
                'pdp_guest_design', $data
                , 'user_id="' . $userId . '"'
        );
    }

    private function getPdpProductTypeBySku($sku) {
        $result = [];
        $sql = 'SELECT * FROM `pdp_product_type` WHERE sku="' . $sku . '"';
        if ($results = Db::getInstance()->ExecuteS($sql)) {
            foreach ($results as $row) {
                $result = $row;
            }
            return $result;
        } else {
            return [];
        }
    }

    private function getLinkEditDesignFrontend($design_id, $sku, $urlPdp) {
        $product_type = $this->getPdpProductTypeBySku($sku);
        $pdp_product_id = $product_type['type_id'];
        $param = '';
        if (!$pdp_product_id || !$design_id) {
            return $urlPdp;
        }
        if ($pdp_product_id) {
            $param .= '?pid=' . $pdp_product_id;
        }
        if ($design_id) {
            $param .= '&tid=' . $design_id;
        }
        if (substr($urlPdp, -1) == '/') {
            $urlPdp .= $param;
        } else {
            $urlPdp .= '/' . $param;
        }
        return $urlPdp;
    }

// </editor-fold>
}
