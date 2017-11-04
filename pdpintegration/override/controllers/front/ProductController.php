<?php

class ProductController extends ProductControllerCore {
    
    public function displayAjaxQuickview() {
        $enabled_products = $this->getEnabledProducts();
        $currentProducts=$enabled_products[(int) Tools::getValue('id_product')];
        if ($currentProducts) {
            $product_for_template = $this->getTemplateVarProduct();
            $labelpdp = Tools::getValue('LABEL_BUTTON', Configuration::get('LABEL_BUTTON'));
            $urlpdp = Tools::getValue('URL_PDP', Configuration::get('URL_PDP'));
            $scope = $this->context->smarty->createData(
                    $this->context->smarty
            );
            $scope->assign(array(
                'btnlabel' => $labelpdp,
                'btnlink' => $urlpdp.'?pid='.$currentProducts['pdp_id_product']
            ));

            $tpl = $this->context->smarty->createTemplate(
                    _PS_MODULE_DIR_ . 'pdpintegration/views/templates/front/quickview.tpl',
                    $scope
            );
            ob_end_clean();
            header('Content-Type: application/json');
            $this->ajaxDie(Tools::jsonEncode(array(
                'quickview_html' => $tpl->fetch(),
                'product' => $product_for_template,
            )));
        }else {
            parent::displayAjaxQuickview();
        }
    }

    public function displayAjaxRefresh() {
        $enabled_products = $this->getEnabledProducts();
        $currentProducts=$enabled_products[(int) Tools::getValue('id_product')];
        if ($currentProducts) {
            $product = $this->getTemplateVarProduct();
            $labelpdp = Tools::getValue('LABEL_BUTTON', Configuration::get('LABEL_BUTTON'));
            $urlpdp = Tools::getValue('URL_PDP', Configuration::get('URL_PDP'));
            $scope = $this->context->smarty->createData(
                    $this->context->smarty
            );
            $scope->assign(array(
                'btnlabel' => $labelpdp,
                'btnlink' => $urlpdp.'?pid='.$currentProducts['pdp_id_product']
            ));

            $tpl = $this->context->smarty->createTemplate(
                    _PS_MODULE_DIR_ . 'pdpintegration/views/templates/front/product-add-to-cart.tpl',
                    $scope
            );
            $minimalProductQuantity = $this->getMinimalProductOrDeclinationQuantity($product);
            $isPreview = ('1' === Tools::getValue('preview'));
            ob_end_clean();
            header('Content-Type: application/json');
            $this->ajaxDie(Tools::jsonEncode(array(
                        'product_prices' => $this->render('catalog/_partials/product-prices'),
                        'product_cover_thumbnails' => $this->render('catalog/_partials/product-cover-thumbnails'),
                        'product_details' => $this->render('catalog/_partials/product-details'),
                        'product_variants' => $this->render('catalog/_partials/product-variants'),
                        'product_discounts' => $this->render('catalog/_partials/product-discounts'),
                        //override button add to cart start
                        'product_add_to_cart' => $tpl->fetch(),
                        //override button add to cart end
                        'product_additional_info' => $this->render('catalog/_partials/product-additional-info'),
                        'product_images_modal' => $this->render('catalog/_partials/product-images-modal'),
                        'product_url' => $this->context->link->getProductLink(
                                $product['id_product'], null, null, null, $this->context->language->id, null, $product['id_product_attribute'], false, false, true, $isPreview ? array('preview' => '1') : array()
                        ),
                        'product_minimal_quantity' => $minimalProductQuantity,
                        'product_has_combinations' => !empty($this->combinations),
                        'id_product_attribute' => $product['id_product_attribute'],
            )));
        } else {
            parent::displayAjaxRefresh();
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

}
