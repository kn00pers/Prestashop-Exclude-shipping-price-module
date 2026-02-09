<?php

//Astrodesign.pl - Free to use and edit. Don't sell it. It is supposed to be free and open source. Prestashop modules are hella expensive.

if (!defined('_PS_VERSION_')) {
    exit;
}

class Excludeshipping extends Module
{
    public function __construct()
    {
        $this->name = 'excludeshipping';
        $this->tab = 'Astrodesign.pl - excludeshipping';
        $this->version = '1.1.1';
        $this->author = 'astrodesign.pl';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Exclude products from free shipping');
        $this->description = $this->l('Allows assigning individual shipping costs to products and excluding them from free shipping.');

        if (!empty($this->active) && !Configuration::get('EXCLUDESHIPPING_SCHEMA_1_1_0')) {
            if ($this->installDb()) {
                Configuration::updateValue('EXCLUDESHIPPING_SCHEMA_1_1_0', 1);
            }
        }
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('actionPackageShippingCost')
            && $this->installDb();
    }

    public function uninstall()
    {
        return parent::uninstall()
            && $this->uninstallDb()
            && Configuration::deleteByName('EXCLUDESHIPPING_SCHEMA_1_1_0');
    }

    protected function installDb()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'excludeshipping_rules` (
            `id_rule` int(11) NOT NULL AUTO_INCREMENT,
            `id_product` int(11) NOT NULL,
            `id_carrier` int(11) NOT NULL,
            `shipping_cost` decimal(20,6) NOT NULL,
            `apply_per_quantity` tinyint(1) NOT NULL DEFAULT 0,
            `free_threshold` decimal(20,6) NOT NULL,
            PRIMARY KEY (`id_rule`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        $ok = Db::getInstance()->execute($sql);
        if (!$ok) {
            return false;
        }

        $col = Db::getInstance()->executeS(
            'SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'excludeshipping_rules` LIKE "apply_per_quantity"'
        );
        if (empty($col)) {
            $ok = Db::getInstance()->execute(
                'ALTER TABLE `' . _DB_PREFIX_ . 'excludeshipping_rules`
                 ADD `apply_per_quantity` tinyint(1) NOT NULL DEFAULT 0 AFTER `shipping_cost`'
            );
        }

        return (bool) $ok;
    }

    protected function uninstallDb()
    {
        $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'excludeshipping_rules`';
        return Db::getInstance()->execute($sql);
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitExcludeshipping')) {
            $id_product = (int) Tools::getValue('id_product');
            $id_carrier = (int) Tools::getValue('id_carrier');
            $shipping_cost = (float) Tools::getValue('shipping_cost');
            $apply_per_quantity = (int) (bool) Tools::getValue('apply_per_quantity');
            $free_threshold = (float) Tools::getValue('free_threshold');

            if ($id_product && $shipping_cost >= 0) {
                Db::getInstance()->insert('excludeshipping_rules', [
                    'id_product'    => $id_product,
                    'id_carrier'    => $id_carrier,
                    'shipping_cost' => $shipping_cost,
                    'apply_per_quantity' => $apply_per_quantity,
                    'free_threshold'=> $free_threshold,
                ]);
                $output .= $this->displayConfirmation($this->l('Rule saved.'));
            } else {
                $output .= $this->displayError($this->l('Invalid data.'));
            }
        } elseif (Tools::isSubmit('deleteexcludeshipping') && Tools::getValue('id_rule')) {
            $id_rule = (int) Tools::getValue('id_rule');
            Db::getInstance()->delete('excludeshipping_rules', 'id_rule = ' . $id_rule);
            $output .= $this->displayConfirmation($this->l('Rule deleted.'));
        }

        return $output . $this->renderForm() . $this->renderList();
    }

    public function renderForm()
    {
        $carriers = Carrier::getCarriers($this->context->language->id, true, false, false, null, Carrier::ALL_CARRIERS);
        $carriers_list = [['id' => 0, 'name' => $this->l('All carriers')]];
        foreach ($carriers as $carrier) {
            $carriers_list[] = ['id' => $carrier['id_carrier'], 'name' => $carrier['name']];
        }

        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Add shipping cost rule'),
                    'icon'  => 'icon-cogs',
                ],
                'input'  => [
                    [
                        'type'     => 'text',
                        'label'    => $this->l('Product ID'),
                        'name'     => 'id_product',
                        'required' => true,
                        'desc'     => $this->l('Enter the product ID from the catalog.'),
                    ],
                    [
                        'type'   => 'select',
                        'label'  => $this->l('Carrier'),
                        'name'   => 'id_carrier',
                        'options'=> [
                            'query' => $carriers_list,
                            'id'    => 'id',
                            'name'  => 'name',
                        ],
                    ],
                    [
                        'type'     => 'text',
                        'label'    => $this->l('Shipping cost for this product'),
                        'name'     => 'shipping_cost',
                        'prefix'   => $this->context->currency->sign,
                        'required' => true,
                    ],
                    [
                        'type'   => 'switch',
                        'label'  => $this->l('Apply shipping cost per item'),
                        'name'   => 'apply_per_quantity',
                        'is_bool'=> true,
                        'values' => [
                            [
                                'id'    => 'apply_per_quantity_on',
                                'value' => 1,
                                'label' => $this->l('Yes (for each item)'),
                            ],
                            [
                                'id'    => 'apply_per_quantity_off',
                                'value' => 0,
                                'label' => $this->l('No (once for all items)'),
                            ],
                        ],
                        'desc'   => $this->l('If enabled, the shipping cost from the rule will be multiplied by the quantity in the cart (e.g. 2 × 16.99 = 33.98).'),
                    ],
                    [
                        'type'   => 'text',
                        'label'  => $this->l('Free shipping from amount (for this product)'),
                        'name'   => 'free_threshold',
                        'prefix' => $this->context->currency->sign,
                        'desc'   => $this->l('If the value of this product in the cart exceeds this amount, the additional cost will not be applied. Enter 0 to disable.'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitExcludeshipping';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name
            . '&tab_module=' . $this->tab
            . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->fields_value['apply_per_quantity'] = 0;

        return $helper->generateForm([$fields_form]);
    }

    public function renderList()
    {
        $sql = 'SELECT r.*, pl.name as product_name, c.name as carrier_name 
                FROM `' . _DB_PREFIX_ . 'excludeshipping_rules` r
                LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl 
                    ON (r.id_product = pl.id_product AND pl.id_lang = ' . (int) $this->context->language->id . ')
                LEFT JOIN `' . _DB_PREFIX_ . 'carrier` c 
                    ON (r.id_carrier = c.id_carrier)';
        
        $rules = Db::getInstance()->executeS($sql);

        $fields_list = [
            'id_product'    => ['title' => $this->l('Product ID')],
            'product_name'  => ['title' => $this->l('Product name')],
            'carrier_name'  => ['title' => $this->l('Carrier')],
            'shipping_cost' => ['title' => $this->l('Additional cost'), 'type' => 'price'],
            'apply_per_quantity' => ['title' => $this->l('Per item'), 'type' => 'bool'],
            'free_threshold'=> ['title' => $this->l('Free shipping threshold'), 'type' => 'price'],
        ];

        $helper = new HelperList();
        $helper->shopLinkType = '';
        $helper->simple_header = true;
        $helper->actions = ['delete'];
        $helper->identifier = 'id_rule';
        $helper->show_toolbar = false;
        $helper->title = $this->l('Rules list');
        $helper->table = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name
            . '&tab_module=' . $this->tab
            . '&module_name=' . $this->name;

        return $helper->generateList($rules, $fields_list);
    }

    public function getMaxShippingCostForProducts(array $products, ?int $id_carrier)
    {
        $rules = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'excludeshipping_rules`'
        );

        if (empty($rules)) {
            return 0.0;
        }

        $max_product_cost = 0.0;

        $byProduct = [];
        foreach ($products as $product) {
            $product_id = (int) ($product['id_product'] ?? 0);
            if (!$product_id) {
                continue;
            }

            $qty = (int) ($product['cart_quantity'] ?? 1);
            if ($qty < 1) {
                $qty = 1;
            }

            $unit_price_wt = (float) ($product['price_wt'] ?? 0.0);
            $line_total_wt = isset($product['total_wt'])
                ? (float) $product['total_wt']
                : $unit_price_wt * $qty;

            if (!isset($byProduct[$product_id])) {
                $byProduct[$product_id] = [
                    'qty' => 0,
                    'total_wt' => 0.0,
                ];
            }

            $byProduct[$product_id]['qty'] += $qty;
            $byProduct[$product_id]['total_wt'] += $line_total_wt;
        }

        foreach ($rules as $rule) {
            $product_id = (int) ($rule['id_product'] ?? 0);
            if (!$product_id || empty($byProduct[$product_id])) {
                continue;
            }

            if ((int) $rule['id_carrier'] != 0
                && $id_carrier !== null
                && (int) $rule['id_carrier'] !== (int) $id_carrier) {
                continue;
            }

            $qty = (int) ($byProduct[$product_id]['qty'] ?? 1);
            $total_wt = (float) ($byProduct[$product_id]['total_wt'] ?? 0.0);

            if ((float) $rule['free_threshold'] > 0
                && $total_wt >= (float) $rule['free_threshold']) {
                continue;
            }

            $rule_cost = (float) $rule['shipping_cost'];
            $apply_per_quantity = (int) ($rule['apply_per_quantity'] ?? 0);
            if ($apply_per_quantity === 1) {
                $rule_cost *= $qty;
            }

            if ($rule_cost > $max_product_cost) {
                $max_product_cost = $rule_cost;
            }
        }

        return $max_product_cost;
    }

    public function hookActionPackageShippingCost($params)
    {
        $shipping_cost = (float) $params['shipping_cost'];
        $products = $params['products'];
        $carrier = $params['carrier'];

        if (!$products || empty($products)) {
            return $shipping_cost;
        }

        $max_product_cost = $this->getMaxShippingCostForProducts(
            $products,
            isset($carrier->id) ? (int) $carrier->id : null
        );

        return max($shipping_cost, $max_product_cost);
    }
}