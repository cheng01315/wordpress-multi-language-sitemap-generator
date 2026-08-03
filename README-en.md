# Multi-Language Sitemap Generator

> **Languages / 语言:** [English](README.md) · [简体中文](README.zh_CN.md)

A companion helper plugin for **TranslatePress** (and other URL-prefix-based multilingual plugins).

- **Version:** 2.1.0
- **Author:** MeowTool — https://www.meowtool.com/
- **License:** GPL-2.0-or-later

---

## About this plugin

This plugin is a companion helper for **TranslatePress**. TranslatePress implements multilingual sites by inserting a language prefix (e.g. `/en/`, `/fr/`) into every URL, but the native WordPress `wp-sitemap.xml` only contains links in the default language, so search engines cannot discover the other language versions.

This plugin reads the source sitemap (defaults to `wp-sitemap.xml`), walks through all of its sub-sitemaps, inserts a language prefix right after the domain for every URL, and then writes a separate sitemap file (e.g. `en-sitemap.xml`, `fr-sitemap.xml`) for each language into the site root, ready to be submitted to Google Search Console / Bing Webmaster Tools.

**Transformation examples:**

| Original URL | Language | Generated URL |
| --- | --- | --- |
| `https://www.example.com/about/` | `en` | `https://www.example.com/en/about/` |
| `https://www.example.com/about/` | `fr` | `https://www.example.com/fr/about/` |
| `https://www.example.com/` | `es` | `https://www.example.com/es/` |

## Features

- ✅ Reads the native WordPress `wp-sitemap.xml`, or a custom source sitemap URL (compatible with Yoast SEO, Rank Math, etc.)
- ✅ Multilingual management: add / remove languages (supports codes like `en`, `fr`, `pt-br` — 2-8 lowercase letters)
- ✅ Generates a separate `xx-sitemap.xml` file per language in the site root
- ✅ Manual generation and deletion, perfectly matching TranslatePress's URL-prefix structure
- ✅ Ships in both a Chinese-interface and an English-interface version

## Installation

The plugin is provided as **two language-interface versions** of the PHP file. Choose the one that matches your dashboard language:

- `Multi-Language Sitemap Generator-zh_CN.php` — Chinese admin interface
- `Multi-Language Sitemap Generator-en.php` — English admin interface

> ⚠️ The two files are functionally identical and differ only in the admin-interface language. Install **only one of them**; do not activate both at the same time.

### Method 1: Zip and upload via the dashboard (recommended for beginners)

1. Locally, select the PHP file you want to install (e.g. `Multi-Language Sitemap Generator-en.php`) and compress it into a **zip** archive.
   - ⚠️ Compress the PHP file directly. Do **not** wrap it in an extra folder, otherwise WordPress may fail to detect the plugin header.
   - The resulting archive will be something like `Multi-Language Sitemap Generator-en.zip`.
2. Log in to your WordPress dashboard and go to **Plugins → Add New → Upload Plugin**.
3. Choose the zip file you just created and click **Install Now**.
4. After installation completes, click **Activate**.

### Method 2: Upload to the plugin folder via FTP / hosting panel

1. Connect to your server using an FTP client (e.g. FileZilla) or your hosting provider's file manager.
2. Upload the chosen PHP file (e.g. `Multi-Language Sitemap Generator-en.php`) to the WordPress plugins directory:

   ```
   /wp-content/plugins/
   ```

3. For cleaner organization, it is recommended to first create a new folder under that directory (the folder name must not conflict with any existing plugin), for example:

   ```
   /wp-content/plugins/multi-language-sitemap-generator/
   ```

   Then place the PHP file inside that folder. The final path should look like:

   ```
   /wp-content/plugins/multi-language-sitemap-generator/Multi-Language Sitemap Generator-en.php
   ```

4. Log in to your WordPress dashboard, go to **Plugins → Installed Plugins**, find **Multi-Language Sitemap Generator**, and click **Activate**.

## Usage

1. After activation, go to **Tools → Multi-Language Sitemap** in the dashboard sidebar.
2. **Source sitemap configuration**: the default is `wp-sitemap.xml`. If you use Yoast SEO, Rank Math, or another plugin to generate the sitemap, enter its address in the input field (e.g. `sitemap_index.xml`) and save.
3. **Add a language**: under "Add a new language", enter a language code (e.g. `en`, `fr`, `de`, `ja`, `pt-br`) and click "Add language".
4. **Generate the sitemap**: in the language list, click the "Generate" button for the corresponding language to create the `xx-sitemap.xml` file in the site root.
5. **Delete file / Remove language**:
   - "Delete file" — only removes the sitemap file in the site root; the language configuration is kept.
   - "Remove language" — deletes both the sitemap file and the language configuration.
6. After publishing new content, click "Generate" again to refresh the sitemap for the relevant language.

## Submit to search engines

Once generated, the sitemaps are available at:

```
https://your-domain/en-sitemap.xml
https://your-domain/fr-sitemap.xml
```

Submit these URLs to Google Search Console and Bing Webmaster Tools.

## Frequently Asked Questions

**Q: Nginx intercepts static XML files and returns 404. What should I do?**
A: Add an exception in your Nginx config for each sitemap file, for example:

```nginx
location ~* /(en|fr|es)-sitemap\.xml$ {
    try_files $uri =404;
}
```

**Q: Generation fails with "Failed to write the file"?**
A: Make sure the site root directory (`ABSPATH`) is writable by the PHP process. Permissions are typically `755` for directories and `644` for files.

**Q: Will the plugin delete files when uninstalled?**
A: Deactivating the plugin does not delete any files or configuration. Uninstalling only clears the options in the database; the generated sitemap files remain in the site root and must be removed manually.

## File structure

```
en-sitemap-generator/
├── Multi-Language Sitemap Generator-en.php       # English admin interface
├── Multi-Language Sitemap Generator-zh_CN.php    # Chinese admin interface
├── README.md                                      # English documentation
└── README.zh_CN.md                                # Chinese documentation (简体中文)
```

---

© MeowTool — https://www.meowtool.com/
