# periodical

Notes for AI assistants working on the Dotclear plugin periodical.

**Read first** the shared conventions of the author's Dotclear modules:
https://raw.githubusercontent.com/JcDenis/gh2da/master/CONVENTIONS.md
(if a web tool refuses the address, use `curl -s` on it from the shell).
The rules below take precedence for this module.

## Module

Manage and publish automatically lists of posts at regular periods. For exemple, you just need to add posts to a period and they will be publish ones after the others every week.

Admin page. Public side: behaviors.

Required Dotclear and PHP versions: see `_define.php`.

## Structure

* `src/Backend.php`: admin side: menu entry, favorites, behaviors registration.
* `src/BackendBehaviors.php`: admin behaviors.
* `src/Dater.php`: tools to manipulate period date.
* `src/Frontend.php`: public side: behaviors, template tags and URL handlers registration.
* `src/Install.php`: install and update, database structure (a change needs a migration here).
* `src/Manage.php`: admin page of the module.
* `src/ManageList.php`: admin page (list).
* `src/ManagePeriod.php`: admin page (period).
* `src/ManageVars.php`: admin page (vars).
* `src/My.php`: module helpers (`My::id()`, settings, permissions).
* `src/Uninstall.php`: cleaners for the plugin Uninstaller.
* `src/Utils.php`: helpers.
* `src/ClaudetteMcp/`: tools for an AI assistant connected through ClaudetteMcp, registered in `src/Frontend.php` with the behavior `ClaudetteMcpAddTool`.
* `js/`: `checkbox.js`, `dates.js`, `toggle.js`.
* Database table `periodical`.
* Behaviors used: `ClaudetteMcpAddTool`, `publicBeforeDocumentV2`, `adminBlogPreferencesFormV2`, `adminBeforeBlogSettingsUpdate`, `adminFiltersListsV2`, `adminColumnsListsV2`, `adminPostListHeaderV2`, `adminPostListValueV2`, `adminBeforePostDelete`, `adminDashboardFavoritesV2`, `adminPostHeaders`, `adminPostsActions`, `adminPostFormItems`, `adminAfterPostUpdate`, `adminAfterPostCreate`.

## Pitfalls

* Its ClaudetteMcp tools in `src/ClaudetteMcp/` are the example followed by the author's other plugins: keep them simple and consistent with it.
* Table structure changes need a migration in `src/Install.php` (version compare), and must work on MySQL, PostgreSQL and SQLite.
* `Frontend::init()` must not be limited to some URL types, or the ClaudetteMcp tools are never registered. Never add ClaudetteMcp to `requires`.

## Testing

* Check the admin page, the admin help, in English and French.
* CI browses the common admin and public pages; extra pages can be listed in `.github/dotclear-test.txt`.
* Tools: through a ClaudetteMcp connector on a test blog (the connector must be reconnected to validate new tools).
* The user guide `docs/fr/utilisation.md` describes the features: update it, and its screenshots, when the interface changes.
