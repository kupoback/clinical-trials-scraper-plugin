# Merck Scraper Plugin

This plugin executes a scraper for a weekly sync from the clinicaltrials.gov website. The scraper runs on Thursday at midnight (00:00).

This plugin uses laravel.mix to compile assets used for the backend of the site, such as field styles, and the Option pages which are built in Vue. Please use `npm install` at the root of the plugin to install the node_modules, and you can use any of the scripts in the `package.json` to compile the assets as needed. Before pushing to production, please ensure to run the `production` script defined in the `package.json`.

---

#### `merck-scraper.php`

This is the primary file that registers the plugin for the WP-Admin to pick up. This file registers the vendor file, sets up the `DotEnv` loader, and defines the plugin version constant and the API error logs constant with it's dir path. It also contains the activation and deactivation hook registration, which currently just flush the rewrite rules for the custom post type and taxonomies.

## Folders

### Admin

The files located here are executed for the wp-admin.

#### MSAdmin

This is the primary file to registering scripts, styles, the ACF options pages and the save JSON file location and reading.

#### MSCustomPT

This file registers the custom post types for the plugin

#### MSCustomTax

This file registers the custom taxonomies for the plugin.

#### MSMetaBox

This file is not currently in use.

----

### Frontend

The files located here are executed for the frontend of the website.

#### MSAPI

This file registers any frontfacing API endpoints.

#### MSPublic

This file registers the scripts and styles for the frontend.

---

### Includes

#### MSMainClass

This file is the main loader for the `admin`, and `frontend` files and registers their hooks and actions here. It also loads the i18n translation file here, if it's ever populated.

#### MSActivator

This file executes on plugin activation.

#### MSDeactivator

This file executes on plugin deactivation.

#### MSI18n

This file registers the i18n textdomain loader.

#### MSLoader

This file registers the `addAction` and `addFilter` hooks needed to register `admin` and `frontend` methods.

---

### Traits 
