<?php

class Cart extends CartCore
{
    public function getPackageShippingCost(
        $id_carrier = null,
        $use_tax = true,
        ?Country $default_country = null,
        $product_list = null,
        $id_zone = null,
        bool $keepOrderPrices = false
    ) {
        $base_cost = parent::getPackageShippingCost(
            $id_carrier,
            $use_tax,
            $default_country,
            $product_list,
            $id_zone,
            $keepOrderPrices
        );

        $module = \Module::getInstanceByName('excludeshipping');
        if (!$module || !method_exists($module, 'getMaxShippingCostForProducts')) {
            return $base_cost;
        }

        $products = $product_list ?: $this->getProducts();

        $max_product_cost = $module->getMaxShippingCostForProducts(
            $products,
            $id_carrier !== null ? (int) $id_carrier : null
        );

        return max((float) $base_cost, (float) $max_product_cost);
    }
}
