# Changelog

All notable changes to this project will be documented in this file.

## [1.0.5] - 2026-10-01
### Fixed
- Free tier limit message now lists the features Pro actually adds (unlimited groups, bulk product assignment, percentage pricing, customer-group visibility).
- Typing in a text option no longer resets the product gallery to the base image chosen by an earlier drop-down selection.
- Removed leftover debug `console.log` calls from the option image switcher.
- Coding standard warnings: `array_merge` no longer called inside the option save loop; gallery fallback script is rendered through `SecureHtmlRenderer` (CSP-safe) instead of a raw `text/javascript` tag.

### Removed
- Unused legacy admin template `view/adminhtml/templates/group/edit.phtml`.

## [1.0.1] - 2026-07-28
### Added
- Dynamic grid buttons: "Add New Group" automatically hides when the Free tier limit is reached.
- Proactive upsell UX: Injected a persistent "⭐ Upgrade to Pro" button and dynamic warning notices when limits are hit.
- Master `db_schema.xml` to explicitly lock in core tables and protect against accidental overwrites during declarative schema merges.
- Native support for percentage-based price types directly from the database during frontend rendering.

### Changed
- Refactored `Save.php` and `DataProvider.php` to dynamically map UI and Database fields. The core module is now fully extensible for future add-ons.
- Extracted the 6-group limit check into a public `isLimitReached()` method across classes to enable clean plugin interception.
- Fortified the Group resource model by adding `getAssignedGroupIds()`, `getAssignedProductIds()`, and `assignProductsToGroup()` to handle advanced product relations.
- Enhanced backend grid framework (UI registry JSON injection, decoupled checkbox listeners, secure SSR rendering) to support advanced mass-assignment components.

### Fixed
- Critical save bug where updating an existing option group caused rows to be silently wiped from the database due to primary key conflicts.
- "Frontend Black Hole" issue: Deleted legacy layout overrides to allow native Magento rendering alongside the frontend option injector.
- Backend `ProductSavePlugin` to accurately detect when all Shared Option Groups are manually deselected, successfully purging the DB relationships.
- SQL 1452 Foreign Key constraint crashes by adding aggressive integer casting in the Group Resource Model's save method.
- Removed legacy debug console entries from frontend JS.

## [1.0.0] - 2026-07-01
### Added
- Initial release of BluePrint3D Shared Product Options (Free Edition).
- Ability to create and manage reusable custom option groups.
- Free tier allowance of up to 6 shared option groups per Magento instance.
- Frontend injection system to seamlessly merge shared options into native product pages.
- Backend admin interfaces for managing option structures (dropdowns, multiple choice, text inputs).
- Support for fixed price modifiers on individual option values.
- Assignment tab on the Product Edit page to link shared option groups to individual products.