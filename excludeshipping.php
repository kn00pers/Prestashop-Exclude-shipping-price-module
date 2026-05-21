<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class Excludeshipping extends Module
{
    public function __construct()
    {
        $this->name = 'excludeshipping';
        $this->tab = 'shipping_logistics';
        $this->version = '1.2.0';
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

        if (!empty($this->active) && !Configuration::get('EXCLUDESHIPPING_SCHEMA_1_2_0')) {
            if ($this->installDb()) {
                Configuration::updateValue('EXCLUDESHIPPING_SCHEMA_1_2_0', 1);
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
            && Configuration::deleteByName('EXCLUDESHIPPING_SCHEMA_1_1_0')
            && Configuration::deleteByName('EXCLUDESHIPPING_SCHEMA_1_2_0');
    }

    protected function installDb()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'excludeshipping_rules` (
            `id_rule`           int(11) NOT NULL AUTO_INCREMENT,
            `id_product`        int(11) NOT NULL,
            `id_carrier`        int(11) NOT NULL,
            `shipping_cost`     decimal(20,6) NOT NULL,
            `apply_per_quantity` tinyint(1) NOT NULL DEFAULT 0,
            `quantity_interval` int(11) NOT NULL DEFAULT 1,
            `free_threshold`    decimal(20,6) NOT NULL,
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
            Db::getInstance()->execute(
                'ALTER TABLE `' . _DB_PREFIX_ . 'excludeshipping_rules`
                 ADD `apply_per_quantity` tinyint(1) NOT NULL DEFAULT 0 AFTER `shipping_cost`'
            );
        }

        $col2 = Db::getInstance()->executeS(
            'SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'excludeshipping_rules` LIKE "quantity_interval"'
        );
        if (empty($col2)) {
            Db::getInstance()->execute(
                'ALTER TABLE `' . _DB_PREFIX_ . 'excludeshipping_rules`
                 ADD `quantity_interval` int(11) NOT NULL DEFAULT 1 AFTER `apply_per_quantity`'
            );
        }

        $sql2 = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'excludeshipping_groups` (
            `id_group` int(11) NOT NULL AUTO_INCREMENT,
            `name`     varchar(255) NOT NULL,
            PRIMARY KEY (`id_group`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        $sql3 = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'excludeshipping_group_products` (
            `id_group`   int(11) NOT NULL,
            `id_product` int(11) NOT NULL,
            PRIMARY KEY (`id_group`, `id_product`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        $sql4 = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'excludeshipping_group_rules` (
            `id_group_rule`     int(11) NOT NULL AUTO_INCREMENT,
            `id_group`          int(11) NOT NULL,
            `id_carrier`        int(11) NOT NULL DEFAULT 0,
            `shipping_cost`     decimal(20,6) NOT NULL,
            `quantity_interval` int(11) NOT NULL DEFAULT 1,
            `free_threshold`    decimal(20,6) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id_group_rule`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        Db::getInstance()->execute($sql2);
        Db::getInstance()->execute($sql3);
        $ok = Db::getInstance()->execute($sql4);

        return (bool) $ok;
    }

    protected function uninstallDb()
    {
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'excludeshipping_group_rules`');
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'excludeshipping_group_products`');
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'excludeshipping_groups`');
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'excludeshipping_rules`');
    }

    public function getContent()
    {
        $output = '';

        if (Tools::getValue('ajax')) {
            $action = Tools::getValue('action');

            if ($action === 'searchProducts') {
                $q    = trim(Tools::getValue('q'));
                $lang = (int) $this->context->language->id;
                $results = [];
                if (strlen($q) >= 2) {
                    $words = array_filter(explode(' ', $q));
                    $where = implode(' AND ', array_map(
                        fn($w) => "pl.name LIKE '%" . pSQL($w) . "%'",
                        $words
                    ));
                    $rows = Db::getInstance()->executeS(
                        'SELECT p.id_product, pl.name FROM `' . _DB_PREFIX_ . 'product` p
                         LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                            ON (p.id_product = pl.id_product AND pl.id_lang = ' . $lang . ')
                         WHERE ' . $where . ' LIMIT 20'
                    );
                    foreach ($rows as $row) {
                        $results[] = ['id' => (int) $row['id_product'], 'name' => $row['name']];
                    }
                }
                die(json_encode($results));
            }

            if ($action === 'addGroupProduct') {
                $id_group = (int) Tools::getValue('id_group');
                $id_products = Tools::getValue('id_products');
                if ($id_group && !empty($id_products)) {
                    if (!is_array($id_products)) {
                        $id_products = [$id_products];
                    }
                    foreach ($id_products as $id_product) {
                        $id_product = (int) $id_product;
                        if ($id_product > 0) {
                            Db::getInstance()->insert('excludeshipping_group_products', [
                                'id_group'   => $id_group,
                                'id_product' => $id_product,
                            ], false, true, Db::INSERT_IGNORE);
                        }
                    }
                }
                $id_product = (int) Tools::getValue('id_product');
                if ($id_group && $id_product) {
                    Db::getInstance()->insert('excludeshipping_group_products', [
                        'id_group'   => $id_group,
                        'id_product' => $id_product,
                    ], false, true, Db::INSERT_IGNORE);
                }
                die(json_encode(['ok' => true]));
            }

            if ($action === 'removeGroupProduct') {
                $id_group   = (int) Tools::getValue('id_group');
                $id_product = (int) Tools::getValue('id_product');
                if ($id_group && $id_product) {
                    Db::getInstance()->delete(
                        'excludeshipping_group_products',
                        'id_group = ' . $id_group . ' AND id_product = ' . $id_product
                    );
                }
                die(json_encode(['ok' => true]));
            }

            die(json_encode(['error' => 'unknown action']));
        }

        if (Tools::isSubmit('submitExcludeshipping')) {
            $id_product         = (int) Tools::getValue('id_product');
            $id_carrier         = (int) Tools::getValue('id_carrier');
            $shipping_cost      = (float) Tools::getValue('shipping_cost');
            $apply_per_quantity = (int) (bool) Tools::getValue('apply_per_quantity');
            $quantity_interval  = max(1, (int) Tools::getValue('quantity_interval'));
            $free_threshold     = (float) Tools::getValue('free_threshold');

            if ($id_product && $shipping_cost >= 0) {
                Db::getInstance()->insert('excludeshipping_rules', [
                    'id_product'         => $id_product,
                    'id_carrier'         => $id_carrier,
                    'shipping_cost'      => $shipping_cost,
                    'apply_per_quantity' => $apply_per_quantity,
                    'quantity_interval'  => $quantity_interval,
                    'free_threshold'     => $free_threshold,
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

        if (Tools::isSubmit('submitGroupCreate')) {
            $name = trim(Tools::getValue('group_name'));
            if ($name !== '') {
                Db::getInstance()->insert('excludeshipping_groups', ['name' => pSQL($name)]);
                $output .= $this->displayConfirmation($this->l('Group created.'));
            } else {
                $output .= $this->displayError($this->l('Group name cannot be empty.'));
            }
        }

        if (Tools::isSubmit('deleteGroup') && Tools::getValue('id_group')) {
            $id_group = (int) Tools::getValue('id_group');
            Db::getInstance()->delete('excludeshipping_group_rules', 'id_group = ' . $id_group);
            Db::getInstance()->delete('excludeshipping_group_products', 'id_group = ' . $id_group);
            Db::getInstance()->delete('excludeshipping_groups', 'id_group = ' . $id_group);
            $output .= $this->displayConfirmation($this->l('Group deleted.'));
        }

        if (Tools::isSubmit('submitGroupRule')) {
            $id_group          = (int) Tools::getValue('gr_id_group');
            $id_carrier        = (int) Tools::getValue('gr_id_carrier');
            $shipping_cost     = (float) Tools::getValue('gr_shipping_cost');
            $quantity_interval = max(1, (int) Tools::getValue('gr_quantity_interval'));
            $free_threshold    = (float) Tools::getValue('gr_free_threshold');
            if ($id_group && $shipping_cost >= 0) {
                Db::getInstance()->insert('excludeshipping_group_rules', [
                    'id_group'          => $id_group,
                    'id_carrier'        => $id_carrier,
                    'shipping_cost'     => $shipping_cost,
                    'quantity_interval' => $quantity_interval,
                    'free_threshold'    => $free_threshold,
                ]);
                $output .= $this->displayConfirmation($this->l('Group rule saved.'));
            } else {
                $output .= $this->displayError($this->l('Invalid group rule data.'));
            }
        }

        if (Tools::isSubmit('deleteGroupRule') && Tools::getValue('id_group_rule')) {
            $id_group_rule = (int) Tools::getValue('id_group_rule');
            Db::getInstance()->delete('excludeshipping_group_rules', 'id_group_rule = ' . $id_group_rule);
            $output .= $this->displayConfirmation($this->l('Group rule deleted.'));
        }

        return $output . $this->renderForm() . $this->renderList() . $this->renderGroupsSection();
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
                        'type'    => 'select',
                        'label'   => $this->l('Carrier'),
                        'name'    => 'id_carrier',
                        'options' => [
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
                        'type'    => 'switch',
                        'label'   => $this->l('Apply shipping cost per item'),
                        'name'    => 'apply_per_quantity',
                        'is_bool' => true,
                        'values'  => [
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
                        'desc'    => $this->l('If enabled, the shipping cost will be multiplied by quantity (optionally divided by interval below).'),
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Charge every N units'),
                        'name'  => 'quantity_interval',
                        'class' => 'fixed-width-sm',
                        'desc'  => $this->l('Only used when "Apply per item" is enabled. E.g. 5 = one charge per every 5 items (floor division). Default: 1 (charge per each item).'),
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
        $helper->fields_value['quantity_interval']  = 1;

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
            'id_product'         => ['title' => $this->l('Product ID')],
            'product_name'       => ['title' => $this->l('Product name')],
            'carrier_name'       => ['title' => $this->l('Carrier')],
            'shipping_cost'      => ['title' => $this->l('Additional cost'), 'type' => 'price'],
            'apply_per_quantity' => ['title' => $this->l('Per item'), 'type' => 'bool'],
            'quantity_interval'  => ['title' => $this->l('Interval (units)')],
            'free_threshold'     => ['title' => $this->l('Free shipping threshold'), 'type' => 'price'],
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

    public function renderGroupsSection()
    {
        $groups = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'excludeshipping_groups` ORDER BY id_group ASC'
        );

        $carriers = Carrier::getCarriers($this->context->language->id, true, false, false, null, Carrier::ALL_CARRIERS);
        $carriers_options = '<option value="0">' . $this->l('All carriers') . '</option>';
        foreach ($carriers as $c) {
            $carriers_options .= '<option value="' . (int) $c['id_carrier'] . '">'
                . htmlspecialchars($c['name']) . '</option>';
        }

        $base_url = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name
            . '&tab_module=' . $this->tab
            . '&module_name=' . $this->name;
        $token = Tools::getAdminTokenLite('AdminModules');
        $sign  = $this->context->currency->sign;
        $lang  = (int) $this->context->language->id;

        $html  = '<br/>';
        $html .= '<div class="panel"><div class="panel-heading"><i class="icon-tags"></i> '
               . $this->l('Product Groups') . '</div><div class="panel-body">';

        $html .= '<form method="post" action="' . $base_url . '&token=' . $token . '" class="form-horizontal">';
        $html .= '<div class="form-group"><label class="control-label col-lg-3">'
               . $this->l('New group name') . '</label>';
        $html .= '<div class="col-lg-9"><div class="input-group" style="max-width:400px">';
        $html .= '<input type="text" name="group_name" class="form-control" required/>';
        $html .= '<span class="input-group-btn"><button type="submit" name="submitGroupCreate" class="btn btn-default">'
               . $this->l('Create group') . '</button></span>';
        $html .= '</div></div></div></form><hr/>';

        if (empty($groups)) {
            $html .= '<p class="text-muted">' . $this->l('No groups yet.') . '</p>';
            $html .= '</div></div>';
            $html .= $this->renderGroupsJs($base_url, $token);
            return $html;
        }

        foreach ($groups as $group) {
            $id_group = (int) $group['id_group'];

            $html .= '<div class="panel panel-default" style="margin-bottom:10px">';
            $html .= '<div class="panel-heading" style="display:flex;justify-content:space-between;align-items:center">';
            $html .= '<strong>' . htmlspecialchars($group['name']) . '</strong>';
            $html .= '<form method="post" action="' . $base_url . '&token=' . $token . '" style="margin:0">';
            $html .= '<input type="hidden" name="id_group" value="' . $id_group . '"/>';
            $html .= '<button type="submit" name="deleteGroup" class="btn btn-danger btn-xs"'
                   . ' onclick="return confirm(\'' . $this->l('Delete this group and all its rules?') . '\')">'
                   . $this->l('Delete group') . '</button></form></div>';
            $html .= '<div class="panel-body">';

            $products_in_group = Db::getInstance()->executeS(
                'SELECT gp.id_product, pl.name
                 FROM `' . _DB_PREFIX_ . 'excludeshipping_group_products` gp
                 LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                    ON (gp.id_product = pl.id_product AND pl.id_lang = ' . $lang . ')
                 WHERE gp.id_group = ' . $id_group
            );

            $html .= '<h5>' . $this->l('Products in group:') . '</h5>';
            $html .= '<ul class="list-group" id="group-products-' . $id_group . '" style="margin-bottom:8px;max-width:600px">';
            foreach ($products_in_group as $gp) {
                $html .= '<li class="list-group-item" style="display:flex;justify-content:space-between;align-items:center">';
                $html .= '<span>[' . (int) $gp['id_product'] . '] ' . htmlspecialchars($gp['name']) . '</span>';
                $html .= '<button class="btn btn-xs btn-danger es-remove-product"'
                       . ' data-group="' . $id_group . '" data-product="' . (int) $gp['id_product'] . '">'
                       . $this->l('Remove') . '</button>';
                $html .= '</li>';
            }
            $html .= '</ul>';

            $html .= '<div class="es-search-wrap" data-group="' . $id_group . '"'
                   . ' style="position:relative;max-width:400px;margin-bottom:16px">';
            $html .= '<div class="input-group">';
            $html .= '<input type="text" class="form-control es-product-search"'
                   . ' placeholder="' . $this->l('Search product to add...') . '" autocomplete="off"/>';
            $html .= '<span class="input-group-btn">';
            $html .= '<button type="button" class="btn btn-default es-add-selected" disabled>'
                   . $this->l('Add selected') . '</button>';
            $html .= '</span>';
            $html .= '</div>';
            $html .= '<ul class="es-search-results dropdown-menu"'
                   . ' style="display:none;width:100%;position:absolute;z-index:1000;max-height:250px;overflow-y:auto"></ul>';
            $html .= '</div>';

            $group_rules = Db::getInstance()->executeS(
                'SELECT gr.*, c.name as carrier_name
                 FROM `' . _DB_PREFIX_ . 'excludeshipping_group_rules` gr
                 LEFT JOIN `' . _DB_PREFIX_ . 'carrier` c ON (gr.id_carrier = c.id_carrier)
                 WHERE gr.id_group = ' . $id_group
            );

            $html .= '<h5>' . $this->l('Shipping rules for this group:') . '</h5>';
            $html .= '<p class="help-block" style="font-size:12px">'
                   . $this->l('Quantities of ALL products in the group are summed. Cost = floor(total qty ÷ interval) × cost.')
                   . '</p>';

            if (!empty($group_rules)) {
                $html .= '<table class="table table-bordered table-condensed" style="margin-bottom:8px;max-width:700px">';
                $html .= '<thead><tr>'
                       . '<th>' . $this->l('Carrier') . '</th>'
                       . '<th>' . $this->l('Cost') . '</th>'
                       . '<th>' . $this->l('Interval (units)') . '</th>'
                       . '<th>' . $this->l('Free threshold') . '</th>'
                       . '<th></th>'
                       . '</tr></thead><tbody>';
                foreach ($group_rules as $gr) {
                    $carrier_name = $gr['carrier_name'] ?: $this->l('All carriers');
                    $html .= '<tr>';
                    $html .= '<td>' . htmlspecialchars($carrier_name) . '</td>';
                    $html .= '<td>' . $sign . ' ' . number_format((float) $gr['shipping_cost'], 2) . '</td>';
                    $html .= '<td>' . (int) $gr['quantity_interval'] . '</td>';
                    $html .= '<td>' . ((float) $gr['free_threshold'] > 0
                            ? $sign . ' ' . number_format((float) $gr['free_threshold'], 2)
                            : '-') . '</td>';
                    $html .= '<td><form method="post" action="' . $base_url . '&token=' . $token . '" style="margin:0">';
                    $html .= '<input type="hidden" name="id_group_rule" value="' . (int) $gr['id_group_rule'] . '"/>';
                    $html .= '<button type="submit" name="deleteGroupRule" class="btn btn-danger btn-xs">'
                           . $this->l('Delete') . '</button></form></td>';
                    $html .= '</tr>';
                }
                $html .= '</tbody></table>';
            }

            $html .= '<form method="post" action="' . $base_url . '&token=' . $token . '" class="form-inline" style="margin-top:4px;flex-wrap:wrap;gap:4px">';
            $html .= '<input type="hidden" name="gr_id_group" value="' . $id_group . '"/>';
            $html .= '<select name="gr_id_carrier" class="form-control input-sm" style="margin-right:4px">'
                   . $carriers_options . '</select>';
            $html .= '<div class="input-group input-group-sm" style="width:120px;margin-right:4px">'
                   . '<span class="input-group-addon">' . $sign . '</span>'
                   . '<input type="number" step="0.01" min="0" name="gr_shipping_cost" class="form-control"'
                   . ' placeholder="' . $this->l('Cost') . '" required/>'
                   . '</div>';
            $html .= '<div class="input-group input-group-sm" style="width:90px;margin-right:4px">'
                   . '<input type="number" min="1" name="gr_quantity_interval" value="1" class="form-control"'
                   . ' title="' . $this->l('Charge every N units') . '" placeholder="N"/>'
                   . '<span class="input-group-addon" title="' . $this->l('Interval') . '">N</span>'
                   . '</div>';
            $html .= '<div class="input-group input-group-sm" style="width:120px;margin-right:4px">'
                   . '<span class="input-group-addon">' . $sign . '</span>'
                   . '<input type="number" step="0.01" min="0" name="gr_free_threshold" value="0" class="form-control"'
                   . ' placeholder="' . $this->l('Threshold') . '"/>'
                   . '</div>';
            $html .= '<button type="submit" name="submitGroupRule" class="btn btn-success btn-sm">'
                   . $this->l('Add rule') . '</button>';
            $html .= '</form>';

            $html .= '</div></div>';
        }

        $html .= '</div></div>';
        $html .= $this->renderGroupsJs($base_url, $token);
        return $html;
    }

    protected function renderGroupsJs($base_url, $token)
    {
        $ajax_url    = $base_url . '&token=' . $token . '&ajax=1';
        $lbl_remove  = $this->l('Remove');
        $lbl_none    = $this->l('No results');
        $lbl_select_all = $this->l('Select all');
        $lbl_already_added = $this->l('already added');

        return '
<script>
(function($){
    var ajaxUrl = ' . json_encode($ajax_url) . ';
    var lblRemove = ' . json_encode($lbl_remove) . ';
    var lblNone   = ' . json_encode($lbl_none) . ';
    var lblSelectAll = ' . json_encode($lbl_select_all) . ';
    var lblAlreadyAdded = ' . json_encode($lbl_already_added) . ';
    var searchTimer;

    function updateAddButtonState($wrap) {
        var checkedCount = $wrap.find(".es-product-cb:checked").length;
        var $btn = $wrap.find(".es-add-selected");
        if (checkedCount > 0) {
            $btn.prop("disabled", false).addClass("btn-primary").removeClass("btn-default");
        } else {
            $btn.prop("disabled", true).addClass("btn-default").removeClass("btn-primary");
        }
    }

    $(document).on("keydown", ".es-product-search", function(e){
        if (e.key === "Enter") {
            e.preventDefault();
        }
    });

    $(document).on("input", ".es-product-search", function(){
        var $input  = $(this);
        var $wrap   = $input.closest(".es-search-wrap");
        var $list   = $wrap.find(".es-search-results");
        var idGroup = $wrap.data("group");
        clearTimeout(searchTimer);
        var q = $input.val().trim();
        if (q.length < 2) { $list.hide().empty(); updateAddButtonState($wrap); return; }
        searchTimer = setTimeout(function(){
            $.getJSON(ajaxUrl + "&action=searchProducts&q=" + encodeURIComponent(q), function(data){
                $list.empty();
                if (!data.length) {
                    $list.append("<li class=\"dropdown-header\">" + lblNone + "</li>");
                    updateAddButtonState($wrap);
                    $list.show();
                    return;
                }
                
                // Select all row
                var $selectAllLi = $("<li>").addClass("es-select-all-wrapper").css({
                    "padding": "6px 15px",
                    "border-bottom": "1px solid #ddd"
                });
                var $selectAllLabel = $("<label>").css({ "margin": "0", "font-weight": "bold", "cursor": "pointer", "width": "100%" });
                var $selectAllCb = $("<input>").attr("type", "checkbox").addClass("es-select-all-cb").css({ "margin-right": "8px", "vertical-align": "middle" });
                $selectAllLabel.append($selectAllCb).append(" " + lblSelectAll);
                $selectAllLi.append($selectAllLabel);
                $list.append($selectAllLi);

                var $ul = $("#group-products-" + idGroup);
                $.each(data, function(i, p){
                    var $li = $("<li>");
                    var $label = $("<label>").css({
                        "padding": "6px 15px",
                        "display": "block",
                        "margin": "0",
                        "font-weight": "normal",
                        "cursor": "pointer"
                    });
                    var $cb = $("<input>").attr({
                        "type": "checkbox",
                        "value": p.id,
                        "data-name": p.name
                    }).addClass("es-product-cb").css({
                        "margin-right": "8px",
                        "vertical-align": "middle"
                    });
                    
                    if ($ul.find("[data-product=\'" + p.id + "\']").length) {
                        $cb.attr("disabled", true);
                        $label.css("color", "#ccc").append($cb).append("[" + p.id + "] " + p.name + " (" + lblAlreadyAdded + ")");
                    } else {
                        $label.append($cb).append("[" + p.id + "] " + p.name);
                    }
                    
                    $li.append($label);
                    $list.append($li);
                });
                $list.show();
                updateAddButtonState($wrap);
            });
        }, 300);
    });

    $(document).on("change", ".es-select-all-cb", function(){
        var $cb = $(this);
        var $wrap = $cb.closest(".es-search-wrap");
        var $list = $wrap.find(".es-search-results");
        var checked = $cb.is(":checked");
        $list.find(".es-product-cb:not(:disabled)").prop("checked", checked);
        updateAddButtonState($wrap);
    });

    $(document).on("change", ".es-product-cb", function(){
        var $cb = $(this);
        var $wrap = $cb.closest(".es-search-wrap");
        var $list = $wrap.find(".es-search-results");
        var total = $list.find(".es-product-cb:not(:disabled)").length;
        var checked = $list.find(".es-product-cb:not(:disabled):checked").length;
        $list.find(".es-select-all-cb").prop("checked", total > 0 && total === checked);
        updateAddButtonState($wrap);
    });

    $(document).on("click", function(e){
        if (!$(e.target).closest(".es-search-wrap").length) {
            $(".es-search-results").hide();
        }
    });

    $(document).on("click", ".es-add-selected", function(){
        var $btn = $(this);
        var $wrap = $btn.closest(".es-search-wrap");
        var idGroup = $wrap.data("group");
        var $checked = $wrap.find(".es-product-cb:checked");
        var products = [];
        $checked.each(function(){
            products.push({
                id: $(this).val(),
                name: $(this).data("name")
            });
        });

        if (products.length === 0) return;

        var ids = products.map(function(p){ return p.id; });
        var $input = $wrap.find(".es-product-search");
        var $list = $wrap.find(".es-search-results");

        $.post(ajaxUrl + "&action=addGroupProduct", { id_group: idGroup, id_products: ids }, function(){
            var $ul = $("#group-products-" + idGroup);
            $.each(products, function(i, p){
                if ($ul.find("[data-product=\'" + p.id + "\']").length) {
                    return;
                }
                var $li = $("<li>").addClass("list-group-item")
                    .css({ display: "flex", justifyContent: "space-between", alignItems: "center" })
                    .append($("<span>").text("[" + p.id + "] " + p.name))
                    .append(
                        $("<button>").addClass("btn btn-xs btn-danger es-remove-product")
                            .attr({ "data-group": idGroup, "data-product": p.id })
                            .text(lblRemove)
                    );
                $ul.append($li);
            });
            $list.hide().empty();
            $input.val("");
            updateAddButtonState($wrap);
        });
    });

    $(document).on("click", ".es-remove-product", function(){
        var $btn      = $(this);
        var idGroup   = $btn.data("group");
        var idProduct = $btn.data("product");
        $.post(ajaxUrl + "&action=removeGroupProduct", { id_group: idGroup, id_product: idProduct }, function(){
            $btn.closest("li").remove();
        });
    });
})(jQuery);
</script>';
    }

    public function getMaxShippingCostForProducts(array $products, ?int $id_carrier)
    {
        $total_shipping_cost = 0.0;

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
                $byProduct[$product_id] = ['qty' => 0, 'total_wt' => 0.0];
            }

            $byProduct[$product_id]['qty']      += $qty;
            $byProduct[$product_id]['total_wt'] += $line_total_wt;
        }

        // --- Individual Product Rules ---
        $rules = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'excludeshipping_rules`'
        );

        // Group rules by product_id
        $rules_by_product = [];
        if (!empty($rules)) {
            foreach ($rules as $rule) {
                $rules_by_product[(int)$rule['id_product']][] = $rule;
            }
        }

        foreach ($byProduct as $product_id => $cart_data) {
            if (empty($rules_by_product[$product_id])) {
                continue;
            }

            // Find best rule for this product: specific carrier rule or fallback to 0
            $best_rule = null;
            foreach ($rules_by_product[$product_id] as $rule) {
                $rule_carrier = (int)$rule['id_carrier'];
                if ($id_carrier !== null && $rule_carrier === $id_carrier) {
                    $best_rule = $rule;
                    break;
                }
            }

            if ($best_rule === null) {
                foreach ($rules_by_product[$product_id] as $rule) {
                    if ((int)$rule['id_carrier'] === 0) {
                        $best_rule = $rule;
                        break;
                    }
                }
            }

            if ($best_rule !== null) {
                $qty = (int)$cart_data['qty'];
                $total_wt = (float)$cart_data['total_wt'];

                if ((float)$best_rule['free_threshold'] > 0
                    && $total_wt >= (float)$best_rule['free_threshold']) {
                    continue;
                }

                $rule_cost = (float)$best_rule['shipping_cost'];
                $apply_per_quantity = (int)($best_rule['apply_per_quantity'] ?? 0);
                if ($apply_per_quantity === 1) {
                    $interval = max(1, (int)($best_rule['quantity_interval'] ?? 1));
                    $rule_cost *= (int)floor($qty / $interval);
                }

                $total_shipping_cost += $rule_cost;
            }
        }

        // --- Group Rules ---
        $group_products_all = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'excludeshipping_group_products`'
        );
        $group_rules_all = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'excludeshipping_group_rules`'
        );

        if (empty($group_rules_all)) {
            return $total_shipping_cost;
        }

        $group_membership = [];
        if (!empty($group_products_all)) {
            foreach ($group_products_all as $gp) {
                $group_membership[(int)$gp['id_group']][] = (int)$gp['id_product'];
            }
        }

        $group_rules_by_id = [];
        foreach ($group_rules_all as $gr) {
            $group_rules_by_id[(int)$gr['id_group']][] = $gr;
        }

        foreach ($group_membership as $id_group => $product_ids) {
            if (empty($group_rules_by_id[$id_group])) {
                continue;
            }

            $total_qty = 0;
            $total_value = 0.0;
            foreach ($product_ids as $pid) {
                if (isset($byProduct[$pid])) {
                    $total_qty   += $byProduct[$pid]['qty'];
                    $total_value += $byProduct[$pid]['total_wt'];
                }
            }

            if ($total_qty <= 0) {
                continue;
            }

            // Find best rule for this group: specific carrier rule or fallback to 0
            $best_gr = null;
            foreach ($group_rules_by_id[$id_group] as $gr) {
                $gr_carrier = (int)$gr['id_carrier'];
                if ($id_carrier !== null && $gr_carrier === $id_carrier) {
                    $best_gr = $gr;
                    break;
                }
            }

            if ($best_gr === null) {
                foreach ($group_rules_by_id[$id_group] as $gr) {
                    if ((int)$gr['id_carrier'] === 0) {
                        $best_gr = $gr;
                        break;
                    }
                }
            }

            if ($best_gr !== null) {
                if ((float)$best_gr['free_threshold'] > 0
                    && $total_value >= (float)$best_gr['free_threshold']) {
                    continue;
                }

                $interval = max(1, (int)$best_gr['quantity_interval']);
                $rule_cost = (float)$best_gr['shipping_cost'] * (int)floor($total_qty / $interval);

                $total_shipping_cost += $rule_cost;
            }
        }

        return $total_shipping_cost;
    }

    public function hookActionPackageShippingCost($params)
    {
        $shipping_cost = (float) $params['shipping_cost'];
        $products      = $params['products'];
        $carrier       = $params['carrier'];

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
