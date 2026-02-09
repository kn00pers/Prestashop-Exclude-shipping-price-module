# Exclude Products from Free Shipping (excludeshipping)

A lightweight and powerful PrestaShop module that allows you to assign individual shipping costs to specific products. This module ensures that "heavy" or "oversized" items maintain their shipping price even when the rest of the cart qualifies for free shipping.

## ### Why use this module?

Standard PrestaShop settings often make it difficult to exclude specific products from global free shipping rules (e.g., free shipping over $100). This module fixes that by allowing you to:

* **Override** free shipping for specific Product IDs.
* **Set custom costs** per product or per item quantity.
* **Define thresholds** where even the custom cost becomes free.

---

## ## Features

* **Product-Specific Rules:** Assign additional shipping costs to individual products by ID.
* **Carrier Specificity:** Apply rules to all carriers or a specific one.
* **Quantity-Based Calculation:** Choose between a flat fee or a fee multiplied by the number of items in the cart.
* **Individual Thresholds:** Set a "Free shipping from" amount specifically for that product.
* **Automatic Cart Override:** Seamlessly integrates with the PrestaShop checkout process using a `Cart.php` override.
* **Bootstrap UI:** Clean and native-looking configuration interface in the Back Office.

---

## ## Installation

### ### System Requirements

* **PrestaShop:** 8.0.0 or higher.
* **PHP:** 7.4 or higher.

### ### Manual Installation

1. **Download** the module folder and name it `excludeshipping`.
2. **Upload** the folder to your PrestaShop `/modules/` directory.
3. **Install** the module through the PrestaShop Back Office (Modules > Module Manager).
4. The module will automatically create a database table and apply a necessary override to the `Cart` class.

> **Note:** Upon installation, the module clears the class index cache to ensure the `Cart.php` override is active immediately.

---

## ## Configuration

After installation, go to the module configuration page to manage your shipping rules:

1. **Product ID:** The ID of the product you want to control.
2. **Carrier:** Select "All carriers" or limit the rule to a specific shipping method.
3. **Shipping Cost:** The minimum shipping price for this product.
4. **Apply per item:** * *No:* Flat fee regardless of quantity.
* *Yes:* Cost is multiplied by quantity (e.g., 2 items × $15.00 = $30.00).


5. **Free shipping threshold:** If the total value of *this specific product* in the cart exceeds this amount, the custom shipping cost is waived.

---

## ## How it works

The module calculates the shipping cost using the following logic:

1. It retrieves the standard shipping cost calculated by PrestaShop.
2. It checks all items in the cart against your custom rules.
3. If a product rule applies, it calculates that specific product's required shipping cost.
4. It then uses the **higher** value between the standard PrestaShop cost and your custom rule cost.

---

## ## Developer Info & License

* **Author:** [Astrodesign.pl](https://astrodesign.pl)
* **Version:** 1.1.1
* **License:** Free and Open Source.

> **Disclaimer:** This module is provided "as is" for the community. PrestaShop modules are often expensive; this project aims to provide a free, high-quality alternative for shipping management. **Not for resale.**
