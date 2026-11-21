# BluePrint3D – Shared Product Options for Magento 2 📦

![Magento 2.4.x](https://img.shields.io/badge/Magento-2.4.x-orange.svg)
![PHP 8.1–8.5](https://img.shields.io/badge/PHP-8.1--8.5-blue.svg)
![Licence](https://img.shields.io/badge/Licence-Free_EULA-lightgrey.svg)

A central custom options manager for Magento 2. Build product options once, group them, and assign the group to as many products as you like. Change a price or add a dropdown to the group and every assigned product updates.

---

## 🛑 The Problem
Magento makes you recreate the same custom options ("Gift Wrapping", "Custom Engraving", "Extended Warranty") on every product by hand. When the gift-wrap price changes, you're stuck editing product after product.

## 🛠️ The Solution
**Shared Option Groups.** Build option sets in their own admin screen, with dropdowns, text fields, price modifiers and image switchers, then pick the group on any product from a searchable multi-select. The options render on the storefront and carry through to the cart, checkout and order.

## ✨ Features
- **Central management:** update an option's price once and it changes on every assigned product.
- **Simple assignment:** a searchable multi-select on the product edit form.
- **Price and image sync:** frontend scripts handle live price updates (`shared-options-price.js`) and gallery image switching (`option-image-switcher.js`).
- **Native checkout flow:** shared options are added to quote and order items like normal custom options, so fulfilment works as usual.
- **Zero core overrides:** built with plugins, ViewModels and UI DataProviders.

## 🚀 Free vs Pro
This free edition allows up to **6 groups**. [Shared Product Options Pro](https://www.blueprint3d.dev/modules/shared-product-options-pro) removes the cap and adds bulk product assignment and customer-group targeting.

---

## 📦 Installation

```bash
composer require blueprint3d/module-shared-product-options
bin/magento module:enable BluePrint3D_SharedProductOptions
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

## ⚙️ Configuration
1. Open **Catalog > Shared Product Options** in the admin.
2. Create a group (e.g. "Standard Add-ons") and add its options, values and price modifiers.
3. Edit any product in **Catalog > Products**, open the **BluePrint3D Shared Options** section, tick the groups to assign and **Save**.

---

## 📖 Documentation
Full setup guide: [`Shared Product Options Setup Guide.pdf`](Shared%20Product%20Options%20Setup%20Guide.pdf). Release history in [`CHANGELOG.md`](CHANGELOG.md).

## 📜 Licence & Support
**Copyright © 2026 BluePrint3D Ltd. All rights reserved.**

Free to use on personal and commercial stores. Resale, redistribution or sublicensing of this source code, modified or unmodified, for direct financial gain is prohibited. Full terms in [`LICENSE.txt`](LICENSE.txt).

- **Website:** [blueprint3d.dev](https://www.blueprint3d.dev/modules/shared-product-options)
- **Support:** [support@blueprint3d.dev](mailto:support@blueprint3d.dev)
- **Company:** BluePrint3D Ltd, Company No. 13473806
