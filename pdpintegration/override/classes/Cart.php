<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Cart extends CartCore{
    
	const CUSTOM_COST="Custom Cost";
	
    public function getOrderTotal($with_taxes = true, $type = Cart::BOTH, $products = null, $id_carrier = null, $use_cache = true)
    {
        $data = parent::getOrderTotal($with_taxes, $type, $products, $id_carrier,$use_cache);
		if($type==parent::ONLY_PRODUCTS || $type==parent::BOTH){
			$id_lang = Context::getContext()->language->id;
			if (Shop::isFeatureActive()) {
				$id_shop = (int)Context::getContext()->shop->id;
			}
			$id_cart=(int)Context::getContext()->cart->id;
			$only_in_cart = true;
			if (!$result = Db::getInstance()->executeS('
				SELECT cd.`id_customization`, c.`id_address_delivery`, c.`id_product`, cfl.`id_customization_field`, c.`id_product_attribute`,
					cd.`type`, cd.`index`, cd.`value`, cfl.`name`, c.`quantity`
				FROM `'._DB_PREFIX_.'customized_data` cd
				NATURAL JOIN `'._DB_PREFIX_.'customization` c
				LEFT JOIN `'._DB_PREFIX_.'customization_field_lang` cfl ON (cfl.id_customization_field = cd.`index` AND id_lang = '.(int)$id_lang.
					($id_shop ? ' AND cfl.`id_shop` = '.$id_shop : '').')
				WHERE c.`id_cart` = '.$id_cart.
				($only_in_cart ? ' AND c.`in_cart` = 1' : '').'
				ORDER BY `id_product`, `id_product_attribute`, `type`, `index`')) {
				return false;
			}
			foreach ($result as $row) {
				if(self::CUSTOM_COST==$row['name']){
					$data+=$row['value']*$row['quantity'];
				}
			}
		}
        return $data;
    }
}