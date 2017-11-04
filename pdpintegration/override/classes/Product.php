<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Product extends ProductCore {

    const CUSTOMCOST = "Custom Cost";
    const PDP_EDIT = "PDPEdit";
    const NAME_CONTROLLER_ADMIN = "AdminOrders";
    const PDP_CONTROLLER = "pdp";
    const ORDER_CONTROLLER = "order";

    public static function getAllCustomizedDatas($id_cart, $id_lang = null, $only_in_cart = true, $id_shop = null) {

        $data = parent::getAllCustomizedDatas($id_cart, $id_lang, $only_in_cart, $id_shop);
        $controller_name = Tools::getValue('controller');
        $currency = new Currency(Context::getContext()->cookie->id_currency);
        $my_currency_iso_code = $currency->sign;
        if (!$result = Db::getInstance()->executeS(
                'SELECT `id_product`, `id_product_attribute`, `id_customization`, `id_address_delivery`, `quantity`, `quantity_refunded`, `quantity_returned`
			FROM `' . _DB_PREFIX_ . 'customization`
			WHERE `id_cart` = ' . (int) $id_cart . ($only_in_cart ? '
			AND `in_cart` = 1' : ''))) {
            return false;
        }
        foreach ($result as $row) {
            $customData = $data[(int) $row['id_product']][(int) $row['id_product_attribute']][(int) $row['id_address_delivery']][(int) $row['id_customization']]['datas'][parent::CUSTOMIZE_TEXTFIELD];
            foreach ($customData as $key => $row) {
                if (self::PDP_EDIT == $row['name']) {
                    if (self::NAME_CONTROLLER_ADMIN == $controller_name) {
                        $parts = parse_url($row['value']);
                        parse_str($parts['query'], $query);
                        $pid = $query['pid'] ? $query['pid'] : 0;
                        $tid = $query['tid'] ? $query['tid'] : 0;
                        $urlPdp = Configuration::get('URL_PDP');
                        $hrefOpen = Configuration::get('URL_PDP');
                        $param = '';
                        $paramdown = '';
                        if ($tid) {
                            $param.= '?export-design=' . $tid;
                            $paramdown .= 'rest/design-download?id=' . $tid . '&zip=1';
                        }
                        if ($pid) {
                            $param .= '&pid=' . $pid;
                        }
                        if (substr($hrefOpen, -1) == '/') {
                            $hrefOpen .= $param;
                            $urlPdp.= $paramdown; 
                        } else {
                            $hrefOpen .= '/' . $param;
                            $urlPdp .= '/' . $paramdown;
                        }
                        $update = '<a id="pdp_download" class="label label-success" href="' . $urlPdp . '">Download</a>&#09;&#09;&#09;&#09;';
                        $update .= '<a id="pdp_reload_download" class="label label-success" href="' . $hrefOpen . '">Open Editor</a>';
                        $data[(int) $row['id_product']][(int) $row['id_product_attribute']][(int) $row['id_address_delivery']][(int) $row['id_customization']]['datas'][parent::CUSTOMIZE_TEXTFIELD][$key]['value'] = $update;
                    } else if (self::PDP_CONTROLLER == $controller_name) {
                        $update = '<a class="btn btn-primary" href="' . $row['value'] . '">Edit Design</a>';
                        $data[(int) $row['id_product']][(int) $row['id_product_attribute']][(int) $row['id_address_delivery']][(int) $row['id_customization']]['datas'][parent::CUSTOMIZE_TEXTFIELD][$key]['value'] = $update;
                    } else {
                        $param = $row['value'] . '&itemid=' . (int) $row['id_customization'];
                        $update = '<a class="btn btn-primary" href="' . $param . '">Edit Design</a>';
                        $data[(int) $row['id_product']][(int) $row['id_product_attribute']][(int) $row['id_address_delivery']][(int) $row['id_customization']]['datas'][parent::CUSTOMIZE_TEXTFIELD][$key]['value'] = $update;
                        $data[(int) $row['id_product']][(int) $row['id_product_attribute']][(int) $row['id_address_delivery']][(int) $row['id_customization']]['datas'][parent::CUSTOMIZE_TEXTFIELD][$key]['id_module'] = 1;
                    }
                }
                if (self::CUSTOMCOST == $row['name']) {
                    $update = $my_currency_iso_code . $row['value'];
                    $data[(int) $row['id_product']][(int) $row['id_product_attribute']][(int) $row['id_address_delivery']][(int) $row['id_customization']]['datas'][parent::CUSTOMIZE_TEXTFIELD][$key]['value'] = $update;
                }
            }
        }
        return $data;
    }

}
