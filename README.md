# Exclude Products from Free Shipping (excludeshipping)

A lightweight and powerful PrestaShop module that allows you to assign individual shipping costs to specific products and groups of products. This module ensures that "heavy", "oversized", or "fragile" items maintain their shipping price even when the rest of the cart qualifies for free shipping.

### Why use this module?

Standard PrestaShop settings make it difficult to exclude specific products from global free shipping rules (e.g., free shipping over $100). This module solves this by allowing you to:

* **Override** free shipping for specific Product IDs.
* **Set custom costs** per product, per item quantity, or per quantity interval.
* **Group products** together to calculate combined shipping costs.
* **Accumulate shipping costs** dynamically by summing costs from different groups/products.
* **Define thresholds** where even the custom cost becomes free.

---

## Features

* **Product-Specific Rules:** Assign additional shipping costs to individual products by ID.
* **Product Groups:** Combine multiple products into groups and apply rules to their collective quantity in the cart.
* **Bulk Product Management:** Easily search and add multiple products to a group at once using checkboxes and a "Select all" tool.
* **Cumulative Shipping Costs:** Sums up the additional shipping costs of all active individual rules and groups instead of only taking the single highest rule cost.
* **Quantity-Based & Interval Pricing:** Choose between a flat fee, a fee multiplied by quantity, or a fee added for every N units (interval).
* **Carrier Specificity:** Apply rules to all carriers or limit them to specific carriers.
* **Individual Thresholds:** Set a "Free shipping from" amount specifically for that product or group.
* **Automatic Cart Override:** Integrates with PrestaShop's checkout using a `Cart.php` override.
* **Bootstrap UI:** Clean, responsive, and native-looking PrestaShop Back Office configuration.

---

## Installation

### System Requirements

* **PrestaShop:** 8.0.0 or higher.
* **PHP:** 7.4 or higher.

### Manual Installation

1. **Download** the module folder and name it `excludeshipping`.
2. **Upload** the folder to your PrestaShop `/modules/` directory.
3. **Install** the module through the PrestaShop Back Office (Modules > Module Manager).
4. The module will automatically create database tables and apply a necessary override to the `Cart` class.

> **Note:** Upon installation, the module clears the class index cache to ensure the `Cart.php` override is active immediately.

---

## Configuration

### 1. Individual Product Rules
Manage rules for specific products:
* **Product ID:** The ID of the product you want to control.
* **Carrier:** Select "All carriers" or limit the rule to a specific shipping carrier.
* **Shipping Cost:** The additional shipping price for this product.
* **Apply per item:** 
  * *No:* Flat fee regardless of quantity.
  * *Yes:* Cost is multiplied by quantity (e.g., 2 items × $15.00 = $30.00).
* **Charge every N units:** Only when "Apply per item" is enabled. E.g., 5 = one charge added for every 5 items.
* **Free shipping threshold:** If the total value of *this specific product* in the cart exceeds this amount, the custom shipping cost is waived.

### 2. Product Groups & Group Rules
Manage rules for groups of products (e.g., Heavy Goods, Pallets):
* **Groups Management:** Create groups and add products. Search products and use checkboxes to add multiple products at once.
* **Collective Quantity Rules:** Quantities of all products in the group are summed in the cart. Cost is calculated as `floor(total_qty / interval) * cost`.
* **Free shipping threshold:** If the total value of *all products in the group* in the cart exceeds this amount, the group's custom shipping cost is waived.

---

## How it works

The module calculates shipping using the following logic:

1. It retrieves the standard shipping cost calculated by PrestaShop.
2. It checks all items in the cart against active individual product rules and group rules.
3. For each matching product or group in the cart, it calculates its additional shipping cost.
4. It **sums up** the additional shipping costs of all active rules (individual and group) to get the total additional shipping cost.
5. It uses the **higher** value between PrestaShop's standard shipping cost and the total additional shipping cost.

---

## Developer Info & License

* **Author:** [Astrodesign.pl](https://astrodesign.pl)
* **Version:** 1.2.0
* **License:** Free and Open Source.

> **Disclaimer:** This module is provided "as is" for the community. PrestaShop modules are often expensive; this project aims to provide a free, high-quality alternative for shipping management. **Not for resale.**
