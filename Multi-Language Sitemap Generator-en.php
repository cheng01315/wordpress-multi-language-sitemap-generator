<?php
/**
 * Plugin Name:       Multi-Language Sitemap Generator
 * Plugin URI:        https://www.meowtool.com/
 * Description:        Reads the WordPress source sitemap (wp-sitemap.xml by default), inserts a language prefix (such as /en/, /fr/) into every URL, and generates per-language sitemap files (e.g. en-sitemap.xml, fr-sitemap.xml) in the site root. Supports a custom source sitemap URL, full multi-language management (add/remove), manual generation and deletion, and works great with TranslatePress.
 * Version:           2.1.0
 * Author:            MeowTool
 * Author URI:        https://www.meowtool.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       en-sitemap-generator
 * Domain Path:       /languages
 *
 * @package EnSitemapGenerator
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants.
define( 'ESG_VERSION', '2.1.0' );
define( 'ESG_PLUGIN_FILE', __FILE__ );
define( 'ESG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ESG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
// File name pattern: {lang}-sitemap.xml
define( 'ESG_FILENAME_PATTERN', '%s-sitemap.xml' );
// Default language list.
define( 'ESG_DEFAULT_LANGUAGES', 'en' );

/**
 * Get the source sitemap URL (with saved value support).
 *
 * @return string
 */
function esg_get_source_sitemap() {
    $default = trailingslashit( home_url() ) . 'wp-sitemap.xml';
    $saved = get_option( 'esg_source_sitemap', '' );
    return $saved ? $saved : $default;
}

/**
 * Get the list of configured languages.
 *
 * @return array
 */
function esg_get_languages() {
    $saved = get_option( 'esg_languages', '' );
    if ( empty( $saved ) ) {
        return array( 'en' );
    }
    $langs = explode( ',', $saved );
    $langs = array_map( 'trim', $langs );
    $langs = array_filter( $langs );
    return $langs;
}

/**
 * Save the language list.
 *
 * @param array $langs Array of language codes.
 * @return bool
 */
function esg_save_languages( $langs ) {
    $langs = array_unique( array_filter( $langs ) );
    sort( $langs );
    return update_option( 'esg_languages', implode( ',', $langs ) );
}

/**
 * Validate a language code (2-8 lowercase letters, optional single hyphen, e.g. en, fr, pt-br).
 *
 * @param string $code Language code.
 * @return bool
 */
function esg_validate_language_code( $code ) {
    $code = strtolower( trim( $code ) );
    return (bool) preg_match( '/^[a-z]{2,8}(-[a-z]{2,8})?$/', $code );
}

/**
 * Get the sitemap file name for a given language.
 *
 * @param string $lang Language code.
 * @return string
 */
function esg_get_filename( $lang ) {
    return sprintf( ESG_FILENAME_PATTERN, $lang );
}

/**
 * Get the absolute path of the sitemap file for a given language.
 *
 * @param string $lang Language code.
 * @return string
 */
function esg_get_filepath( $lang ) {
    return trailingslashit( ABSPATH ) . esg_get_filename( $lang );
}

/**
 * Get the public URL of the sitemap file for a given language.
 *
 * @param string $lang Language code.
 * @return string
 */
function esg_get_file_url( $lang ) {
    return trailingslashit( home_url() ) . esg_get_filename( $lang );
}

/**
 * Get the URL prefix path for a given language (e.g. /en, /fr).
 *
 * @param string $lang Language code.
 * @return string
 */
function esg_get_lang_prefix( $lang ) {
    return '/' . $lang;
}

/**
 * Register the admin page under the "Tools" menu.
 */
function esg_add_admin_menu() {
    add_management_page(
        __( 'Multi-Language Sitemap Generator', 'en-sitemap-generator' ),
        __( 'Multi-Language Sitemap', 'en-sitemap-generator' ),
        'manage_options',
        'en-sitemap-generator',
        'esg_render_admin_page'
    );
}
add_action( 'admin_menu', 'esg_add_admin_menu' );

/**
 * Enqueue admin styles in the head.
 */
function esg_admin_styles() {
    $screen = get_current_screen();
    if ( ! $screen || 'tools_page_en-sitemap-generator' !== $screen->id ) {
        return;
    }
    ?>
    <style>
        .esg-wrap { max-width: 960px; margin: 20px 0; }
        .esg-card {
            background: #fff;
            border: 1px solid #c3c4c7;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            padding: 20px 24px;
            margin-bottom: 20px;
        }
        .esg-card h2 { margin-top: 0; }
        .esg-btn {
            border: 1px solid transparent;
            color: #fff;
            font-size: 13px;
            padding: 6px 14px;
            border-radius: 3px;
            cursor: pointer;
            line-height: 1.5;
            margin-right: 6px;
        }
        .esg-btn:disabled { opacity: 0.5; cursor: default; }
        .esg-btn-primary { background: #2271b1; border-color: #2271b1; }
        .esg-btn-primary:hover:not(:disabled) { background: #135e96; }
        .esg-btn-success { background: #00a32a; border-color: #00a32a; }
        .esg-btn-success:hover:not(:disabled) { background: #008a20; }
        .esg-btn-danger { background: #d63638; border-color: #d63638; }
        .esg-btn-danger:hover:not(:disabled) { background: #b32d2e; }
        .esg-btn-secondary { background: #f0f0f1; color: #2c3338; border-color: #c3c4c7; }
        .esg-btn-secondary:hover:not(:disabled) { background: #dcdcde; }
        .esg-input {
            padding: 6px 10px;
            border: 1px solid #dcdcde;
            border-radius: 3px;
            font-size: 13px;
            min-width: 280px;
        }
        .esg-input:focus { border-color: #2271b1; outline: none; box-shadow: 0 0 0 1px #2271b1; }
        .esg-input-small { min-width: 120px; }
        .esg-status {
            margin-top: 12px;
            padding: 10px 14px;
            border-radius: 3px;
            display: none;
        }
        .esg-status.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; display: block; }
        .esg-status.error   { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; display: block; }
        .esg-status.info    { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; display: block; }
        .esg-status.loading { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; display: block; }
        .esg-code {
            background: #f6f7f7;
            border: 1px solid #dcdcde;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: Consolas, Monaco, monospace;
            font-size: 13px;
        }
        .esg-lang-list { margin-top: 12px; }
        .esg-lang-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            background: #f6f7f7;
            border: 1px solid #dcdcde;
            border-radius: 3px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        .esg-lang-code {
            font-weight: 600;
            font-size: 15px;
            color: #2271b1;
            min-width: 50px;
            text-transform: uppercase;
        }
        .esg-lang-meta { font-size: 12px; color: #666; flex: 1; min-width: 200px; }
        .esg-lang-actions { display: flex; gap: 6px; flex-wrap: wrap; }
        .esg-lang-status-pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }
        .esg-pill-exists { background: #d4edda; color: #155724; }
        .esg-pill-missing { background: #f8d7da; color: #721c24; }
        .esg-spinner { vertical-align: middle; }
        .esg-form-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
        .esg-help { color: #666; font-size: 12px; margin-top: 4px; }
        .esg-section-title { font-weight: 600; margin-bottom: 8px; }
    </style>
    <?php
}
add_action( 'admin_head', 'esg_admin_styles' );

/**
 * Render the admin page.
 */
function esg_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'en-sitemap-generator' ) );
    }

    $home_url        = home_url();
    $source_sitemap  = esg_get_source_sitemap();
    $languages       = esg_get_languages();
    $nonce           = wp_create_nonce( 'esg_admin_nonce' );
    ?>
    <div class="wrap esg-wrap">
        <h1>
            <?php esc_html_e( 'Multi-Language Sitemap Generator', 'en-sitemap-generator' ); ?>
            <span style="font-size:13px;color:#666;font-weight:normal;">v<?php echo esc_html( ESG_VERSION ); ?></span>
        </h1>

        <div class="esg-card">
            <h2><?php esc_html_e( 'About this plugin', 'en-sitemap-generator' ); ?></h2>
            <p>
                <?php
                printf(
                    /* translators: 1: example url prefix, 2: example url prefix, 3: example file name, 4: example file name */
                    esc_html__( 'This plugin reads the source sitemap, walks through all of its sub-sitemaps, and inserts a language prefix (such as %1$s or %2$s) right after the domain for every URL. It then writes a separate sitemap file (e.g. %3$s, %4$s) for each language into the site root.', 'en-sitemap-generator' ),
                    '<span class="esg-code">/en/</span>',
                    '<span class="esg-code">/fr/</span>',
                    '<span class="esg-code">en-sitemap.xml</span>',
                    '<span class="esg-code">fr-sitemap.xml</span>'
                );
                ?>
            </p>
            <p><strong><?php esc_html_e( 'Transformation examples:', 'en-sitemap-generator' ); ?></strong></p>
            <ul style="list-style:disc;padding-left:24px;">
                <li><span class="esg-code"><?php echo esc_html( $home_url ); ?>/about/</span> + <span class="esg-code">en</span> &rarr; <span class="esg-code"><?php echo esc_html( $home_url ); ?>/en/about/</span></li>
                <li><span class="esg-code"><?php echo esc_html( $home_url ); ?>/about/</span> + <span class="esg-code">fr</span> &rarr; <span class="esg-code"><?php echo esc_html( $home_url ); ?>/fr/about/</span></li>
                <li><span class="esg-code"><?php echo esc_html( $home_url ); ?>/</span> + <span class="esg-code">es</span> &rarr; <span class="esg-code"><?php echo esc_html( $home_url ); ?>/es/</span></li>
            </ul>
        </div>

        <div class="esg-card">
            <h2><?php esc_html_e( 'Source sitemap configuration', 'en-sitemap-generator' ); ?></h2>
            <p><?php esc_html_e( 'Specify the source sitemap to read. Defaults to the native WordPress sitemap at wp-sitemap.xml.', 'en-sitemap-generator' ); ?></p>
            <div class="esg-form-row">
                <input type="text" id="esg-source-url" class="esg-input" value="<?php echo esc_attr( $source_sitemap ); ?>" placeholder="https://www.example.com/wp-sitemap.xml">
                <button type="button" id="esg-save-source-btn" class="esg-btn esg-btn-secondary"><?php esc_html_e( 'Save', 'en-sitemap-generator' ); ?></button>
            </div>
            <p class="esg-help">
                <?php
                printf(
                    /* translators: 1: example sitemap index file */
                    esc_html__( 'Tip: usually no change is needed. If you use Yoast SEO, Rank Math or another plugin to generate the sitemap, enter its address here (e.g. %1$s).', 'en-sitemap-generator' ),
                    '<span class="esg-code">sitemap_index.xml</span>'
                );
                ?>
            </p>
            <div id="esg-source-status" class="esg-status"></div>
        </div>

        <div class="esg-card">
            <h2><?php esc_html_e( 'Language management', 'en-sitemap-generator' ); ?></h2>
            <p><?php esc_html_e( 'Add or remove languages, then generate the sitemap file for each one.', 'en-sitemap-generator' ); ?></p>

            <div class="esg-section-title"><?php esc_html_e( 'Add a new language', 'en-sitemap-generator' ); ?></div>
            <div class="esg-form-row">
                <input type="text" id="esg-new-lang" class="esg-input esg-input-small" placeholder="fr, es, de, ja" maxlength="17">
                <button type="button" id="esg-add-lang-btn" class="esg-btn esg-btn-primary"><?php esc_html_e( 'Add language', 'en-sitemap-generator' ); ?></button>
                <span class="esg-help"><?php esc_html_e( 'Only 2-8 lowercase letters, with an optional single hyphen (e.g. pt-br).', 'en-sitemap-generator' ); ?></span>
            </div>
            <div id="esg-lang-status" class="esg-status"></div>

            <div class="esg-section-title" style="margin-top:20px;"><?php esc_html_e( 'Configured languages', 'en-sitemap-generator' ); ?></div>
            <div class="esg-lang-list" id="esg-lang-list">
                <?php foreach ( $languages as $lang ) : ?>
                    <?php esg_render_lang_row( $lang ); ?>
                <?php endforeach; ?>
            </div>
            <?php if ( empty( $languages ) ) : ?>
                <p style="color:#666;font-style:italic;"><?php esc_html_e( 'No languages configured yet. Add one above.', 'en-sitemap-generator' ); ?></p>
            <?php endif; ?>
        </div>

        <div class="esg-card">
            <h2><?php esc_html_e( 'Usage tips', 'en-sitemap-generator' ); ?></h2>
            <ul style="list-style:disc;padding-left:24px;">
                <li><?php esc_html_e( 'After adding a language, click its "Generate" button to create the sitemap file.', 'en-sitemap-generator' ); ?></li>
                <li><?php esc_html_e( '"Delete file" only removes the sitemap file in the site root; the language configuration is kept.', 'en-sitemap-generator' ); ?></li>
                <li><?php esc_html_e( '"Remove language" deletes the sitemap file and removes the language from the configuration.', 'en-sitemap-generator' ); ?></li>
                <li><?php esc_html_e( 'After publishing new content, click "Generate" again to refresh the sitemap.', 'en-sitemap-generator' ); ?></li>
                <li>
                    <?php
                    printf(
                        /* translators: 1: nginx snippet */
                        esc_html__( 'If Nginx rewrites intercept static XML files, add an exception with %1$s for each sitemap file.', 'en-sitemap-generator' ),
                        '<span class="esg-code">try_files $uri =404;</span>'
                    );
                    ?>
                </li>
            </ul>
        </div>
    </div>

    <script>
    (function() {
        var nonce = '<?php echo esc_js( $nonce ); ?>';

        function setStatus(el, type, message) {
            el.className = 'esg-status ' + type;
            el.innerHTML = message;
        }

        function ajaxRequest(action, data, callback) {
            var params = new URLSearchParams();
            params.append('action', action);
            params.append('nonce', nonce);
            for (var key in data) {
                if (data.hasOwnProperty(key)) {
                    params.append(key, data[key]);
                }
            }
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(function(resp) { return resp.json(); })
            .then(function(json) { callback(json); })
            .catch(function(err) {
                callback({ success: false, data: { message: '<?php echo esc_js( __( 'Request failed: ', 'en-sitemap-generator' ) ); ?>' + err.message } });
            });
        }

        // Save the source sitemap URL.
        var saveSourceBtn = document.getElementById('esg-save-source-btn');
        var sourceInput = document.getElementById('esg-source-url');
        var sourceStatus = document.getElementById('esg-source-status');
        saveSourceBtn.addEventListener('click', function() {
            var url = sourceInput.value.trim();
            if (!url) { setStatus(sourceStatus, 'error', '<strong><?php echo esc_js( __( 'Error:', 'en-sitemap-generator' ) ); ?></strong> <?php echo esc_js( __( 'URL cannot be empty.', 'en-sitemap-generator' ) ); ?>'); return; }
            saveSourceBtn.disabled = true;
            setStatus(sourceStatus, 'loading', '<?php echo esc_js( __( 'Saving...', 'en-sitemap-generator' ) ); ?>');
            ajaxRequest('esg_save_source', { source_url: url }, function(json) {
                saveSourceBtn.disabled = false;
                if (json.success) {
                    setStatus(sourceStatus, 'success', '<strong><?php echo esc_js( __( 'Saved!', 'en-sitemap-generator' ) ); ?></strong> <?php echo esc_js( __( 'The source sitemap URL has been updated.', 'en-sitemap-generator' ) ); ?>');
                } else {
                    setStatus(sourceStatus, 'error', '<strong><?php echo esc_js( __( 'Save failed:', 'en-sitemap-generator' ) ); ?></strong> ' + (json.data && json.data.message ? json.data.message : '<?php echo esc_js( __( 'Unknown error.', 'en-sitemap-generator' ) ); ?>'));
                }
            });
        });

        // Add a language.
        var addLangBtn = document.getElementById('esg-add-lang-btn');
        var newLangInput = document.getElementById('esg-new-lang');
        var langStatus = document.getElementById('esg-lang-status');
        addLangBtn.addEventListener('click', function() {
            var code = newLangInput.value.trim().toLowerCase();
            if (!code) { setStatus(langStatus, 'error', '<strong><?php echo esc_js( __( 'Error:', 'en-sitemap-generator' ) ); ?></strong> <?php echo esc_js( __( 'Please enter a language code.', 'en-sitemap-generator' ) ); ?>'); return; }
            addLangBtn.disabled = true;
            setStatus(langStatus, 'loading', '<?php echo esc_js( __( 'Adding...', 'en-sitemap-generator' ) ); ?>');
            ajaxRequest('esg_add_language', { lang: code }, function(json) {
                addLangBtn.disabled = false;
                if (json.success) {
                    setStatus(langStatus, 'success', '<strong><?php echo esc_js( __( 'Added!', 'en-sitemap-generator' ) ); ?></strong> <?php echo esc_js( __( 'Language', 'en-sitemap-generator' ) ); ?> ' + code + ' <?php echo esc_js( __( 'has been added.', 'en-sitemap-generator' ) ); ?>');
                    newLangInput.value = '';
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    setStatus(langStatus, 'error', '<strong><?php echo esc_js( __( 'Add failed:', 'en-sitemap-generator' ) ); ?></strong> ' + (json.data && json.data.message ? json.data.message : '<?php echo esc_js( __( 'Unknown error.', 'en-sitemap-generator' ) ); ?>'));
                }
            });
        });

        // Per-language row actions (event delegation).
        var langList = document.getElementById('esg-lang-list');
        langList.addEventListener('click', function(e) {
            var btn = e.target.closest('button.esg-lang-action');
            if (!btn) return;
            var lang = btn.getAttribute('data-lang');
            var action = btn.getAttribute('data-action');

            if (action === 'generate') {
                if (!confirm('<?php echo esc_js( __( 'Generate the sitemap for', 'en-sitemap-generator' ) ); ?> ' + lang + '?')) return;
                var statusEl = btn.closest('.esg-lang-row').querySelector('.esg-lang-meta-status');
                btn.disabled = true;
                if (statusEl) setStatus(statusEl, 'loading', '<?php echo esc_js( __( 'Generating...', 'en-sitemap-generator' ) ); ?>');
                ajaxRequest('esg_generate_sitemap', { lang: lang }, function(json) {
                    btn.disabled = false;
                    if (json.success) {
                        if (statusEl) {
                            setStatus(statusEl, 'success', '<strong><?php echo esc_js( __( 'Generated!', 'en-sitemap-generator' ) ); ?></strong> ' + json.data.total_urls + ' <?php echo esc_js( __( 'URLs, file size', 'en-sitemap-generator' ) ); ?> ' + json.data.file_size + '.<br><?php echo esc_js( __( 'View:', 'en-sitemap-generator' ) ); ?> <a href="' + json.data.url + '" target="_blank">' + json.data.url + '</a>');
                        }
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        if (statusEl) setStatus(statusEl, 'error', '<strong><?php echo esc_js( __( 'Failed:', 'en-sitemap-generator' ) ); ?></strong> ' + (json.data && json.data.message ? json.data.message : '<?php echo esc_js( __( 'Unknown error.', 'en-sitemap-generator' ) ); ?>'));
                    }
                });
            } else if (action === 'delete_file') {
                if (!confirm('<?php echo esc_js( __( 'Delete the sitemap file for', 'en-sitemap-generator' ) ); ?> ' + lang + '? <?php echo esc_js( __( 'This cannot be undone.', 'en-sitemap-generator' ) ); ?>')) return;
                btn.disabled = true;
                ajaxRequest('esg_delete_file', { lang: lang }, function(json) {
                    btn.disabled = false;
                    if (json.success) {
                        alert('<?php echo esc_js( __( 'File deleted.', 'en-sitemap-generator' ) ); ?>');
                        location.reload();
                    } else {
                        alert('<?php echo esc_js( __( 'Delete failed:', 'en-sitemap-generator' ) ); ?> ' + (json.data && json.data.message ? json.data.message : '<?php echo esc_js( __( 'Unknown error.', 'en-sitemap-generator' ) ); ?>'));
                    }
                });
            } else if (action === 'remove_lang') {
                if (!confirm('<?php echo esc_js( __( 'Remove language', 'en-sitemap-generator' ) ); ?> ' + lang + '? <?php echo esc_js( __( 'This also deletes the sitemap file and the language configuration.', 'en-sitemap-generator' ) ); ?>')) return;
                btn.disabled = true;
                ajaxRequest('esg_remove_language', { lang: lang }, function(json) {
                    btn.disabled = false;
                    if (json.success) {
                        alert('<?php echo esc_js( __( 'Language removed.', 'en-sitemap-generator' ) ); ?>');
                        location.reload();
                    } else {
                        alert('<?php echo esc_js( __( 'Remove failed:', 'en-sitemap-generator' ) ); ?> ' + (json.data && json.data.message ? json.data.message : '<?php echo esc_js( __( 'Unknown error.', 'en-sitemap-generator' ) ); ?>'));
                    }
                });
            }
        });
    })();
    </script>
    <?php
}

/**
 * Render a single language row.
 *
 * @param string $lang Language code.
 */
function esg_render_lang_row( $lang ) {
    $filepath = esg_get_filepath( $lang );
    $file_url = esg_get_file_url( $lang );
    $exists   = file_exists( $filepath );
    $size     = $exists ? size_format( filesize( $filepath ) ) : '-';
    $modified = $exists ? get_date_from_gmt( date( 'Y-m-d H:i:s', filemtime( $filepath ) ), 'Y-m-d H:i:s' ) : '-';
    ?>
    <div class="esg-lang-row">
        <div class="esg-lang-code"><?php echo esc_html( $lang ); ?></div>
        <div class="esg-lang-meta">
            <div>
                <span class="esg-lang-status-pill <?php echo $exists ? 'esg-pill-exists' : 'esg-pill-missing'; ?>">
                    <?php echo $exists ? esc_html__( 'Generated', 'en-sitemap-generator' ) : esc_html__( 'Not generated', 'en-sitemap-generator' ); ?>
                </span>
                <span style="margin-left:8px;"><?php esc_html_e( 'File:', 'en-sitemap-generator' ); ?> <span class="esg-code"><?php echo esc_html( esg_get_filename( $lang ) ); ?></span></span>
            </div>
            <div style="margin-top:4px;">
                <?php esc_html_e( 'Size:', 'en-sitemap-generator' ); ?> <?php echo esc_html( $size ); ?>
                <?php if ( $exists ) : ?> | <?php esc_html_e( 'Last generated:', 'en-sitemap-generator' ); ?> <?php echo esc_html( $modified ); ?> | <a href="<?php echo esc_url( $file_url ); ?>" target="_blank"><?php esc_html_e( 'View', 'en-sitemap-generator' ); ?></a><?php endif; ?>
            </div>
            <div class="esg-lang-meta-status esg-status"></div>
        </div>
        <div class="esg-lang-actions">
            <button type="button" class="esg-btn esg-btn-primary esg-lang-action" data-lang="<?php echo esc_attr( $lang ); ?>" data-action="generate"><?php esc_html_e( 'Generate', 'en-sitemap-generator' ); ?></button>
            <button type="button" class="esg-btn esg-btn-danger esg-lang-action" data-lang="<?php echo esc_attr( $lang ); ?>" data-action="delete_file" <?php echo $exists ? '' : 'disabled'; ?>><?php esc_html_e( 'Delete file', 'en-sitemap-generator' ); ?></button>
            <button type="button" class="esg-btn esg-btn-secondary esg-lang-action" data-lang="<?php echo esc_attr( $lang ); ?>" data-action="remove_lang"><?php esc_html_e( 'Remove language', 'en-sitemap-generator' ); ?></button>
        </div>
    </div>
    <?php
}

/**
 * AJAX: save the source sitemap URL.
 */
function esg_ajax_save_source() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'en-sitemap-generator' ) ), 403 );
    }
    if ( ! check_ajax_referer( 'esg_admin_nonce', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'en-sitemap-generator' ) ), 403 );
    }
    $url = isset( $_POST['source_url'] ) ? esc_url_raw( wp_unslash( $_POST['source_url'] ) ) : '';
    if ( empty( $url ) ) {
        wp_send_json_error( array( 'message' => __( 'The source sitemap URL cannot be empty.', 'en-sitemap-generator' ) ) );
    }
    update_option( 'esg_source_sitemap', $url );
    wp_send_json_success( array( 'message' => __( 'Saved.', 'en-sitemap-generator' ), 'url' => $url ) );
}
add_action( 'wp_ajax_esg_save_source', 'esg_ajax_save_source' );

/**
 * AJAX: add a language.
 */
function esg_ajax_add_language() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'en-sitemap-generator' ) ), 403 );
    }
    if ( ! check_ajax_referer( 'esg_admin_nonce', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'en-sitemap-generator' ) ), 403 );
    }
    $lang = isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : '';
    if ( ! esg_validate_language_code( $lang ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid language code. Only 2-8 lowercase letters are allowed (with an optional single hyphen, e.g. pt-br).', 'en-sitemap-generator' ) ) );
    }
    $langs = esg_get_languages();
    if ( in_array( $lang, $langs, true ) ) {
        wp_send_json_error( array( 'message' => sprintf( __( 'Language %s already exists.', 'en-sitemap-generator' ), $lang ) ) );
    }
    $langs[] = $lang;
    esg_save_languages( $langs );
    wp_send_json_success( array( 'message' => __( 'Added.', 'en-sitemap-generator' ), 'lang' => $lang ) );
}
add_action( 'wp_ajax_esg_add_language', 'esg_ajax_add_language' );

/**
 * AJAX: remove a language (and delete its sitemap file).
 */
function esg_ajax_remove_language() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'en-sitemap-generator' ) ), 403 );
    }
    if ( ! check_ajax_referer( 'esg_admin_nonce', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'en-sitemap-generator' ) ), 403 );
    }
    $lang = isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : '';
    if ( ! esg_validate_language_code( $lang ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid language code.', 'en-sitemap-generator' ) ) );
    }
    // Delete the file.
    $filepath = esg_get_filepath( $lang );
    if ( file_exists( $filepath ) ) {
        @unlink( $filepath );
    }
    // Remove from the configuration.
    $langs = esg_get_languages();
    $langs = array_diff( $langs, array( $lang ) );
    esg_save_languages( $langs );
    wp_send_json_success( array( 'message' => __( 'Removed.', 'en-sitemap-generator' ), 'lang' => $lang ) );
}
add_action( 'wp_ajax_esg_remove_language', 'esg_ajax_remove_language' );

/**
 * AJAX: delete the sitemap file for a given language (keeping the language configuration).
 */
function esg_ajax_delete_file() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'en-sitemap-generator' ) ), 403 );
    }
    if ( ! check_ajax_referer( 'esg_admin_nonce', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'en-sitemap-generator' ) ), 403 );
    }
    $lang = isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : '';
    if ( ! esg_validate_language_code( $lang ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid language code.', 'en-sitemap-generator' ) ) );
    }
    $filepath = esg_get_filepath( $lang );
    if ( ! file_exists( $filepath ) ) {
        wp_send_json_error( array( 'message' => __( 'File does not exist.', 'en-sitemap-generator' ) ) );
    }
    if ( ! @unlink( $filepath ) ) {
        wp_send_json_error( array( 'message' => __( 'Delete failed. Please check the file permissions.', 'en-sitemap-generator' ) ) );
    }
    wp_send_json_success( array( 'message' => __( 'Deleted.', 'en-sitemap-generator' ), 'lang' => $lang ) );
}
add_action( 'wp_ajax_esg_delete_file', 'esg_ajax_delete_file' );

/**
 * AJAX: generate the sitemap for a given language.
 */
function esg_ajax_generate_sitemap() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'en-sitemap-generator' ) ), 403 );
    }
    if ( ! check_ajax_referer( 'esg_admin_nonce', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'en-sitemap-generator' ) ), 403 );
    }
    $lang = isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : '';
    if ( ! esg_validate_language_code( $lang ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid language code.', 'en-sitemap-generator' ) ) );
    }

    $source_url = esg_get_source_sitemap();

    // 1. Fetch the master sitemap index.
    $master_response = wp_remote_get( $source_url, array(
        'timeout'    => 30,
        'user-agent' => 'MultiLangSitemapGenerator/' . ESG_VERSION,
    ) );
    if ( is_wp_error( $master_response ) ) {
        wp_send_json_error( array( 'message' => __( 'Failed to fetch the source sitemap: ', 'en-sitemap-generator' ) . $master_response->get_error_message() ) );
    }
    $master_code = wp_remote_retrieve_response_code( $master_response );
    if ( 200 !== (int) $master_code ) {
        wp_send_json_error( array( 'message' => sprintf( __( 'Failed to fetch the source sitemap, HTTP status code: %s', 'en-sitemap-generator' ), $master_code ) ) );
    }
    $master_body = wp_remote_retrieve_body( $master_response );
    if ( empty( $master_body ) ) {
        wp_send_json_error( array( 'message' => __( 'The source sitemap content is empty.', 'en-sitemap-generator' ) ) );
    }

    // 2. Parse the master sitemap.
    $sub_sitemaps = esg_parse_sitemap_index( $master_body );
    if ( empty( $sub_sitemaps ) ) {
        // Might be a urlset rather than a sitemapindex, treat it as a urlset directly.
        $urls = esg_parse_url_set( $master_body );
        if ( empty( $urls ) ) {
            wp_send_json_error( array( 'message' => __( 'The source sitemap format is unrecognized (neither a sitemapindex nor a urlset).', 'en-sitemap-generator' ) ) );
        }
    } else {
        // 3. Walk through sub-sitemaps.
        $urls = array();
        foreach ( $sub_sitemaps as $sub ) {
            $sub_response = wp_remote_get( $sub['loc'], array(
                'timeout'    => 30,
                'user-agent' => 'MultiLangSitemapGenerator/' . ESG_VERSION,
            ) );
            if ( is_wp_error( $sub_response ) ) {
                continue;
            }
            $sub_body = wp_remote_retrieve_body( $sub_response );
            $sub_urls = esg_parse_url_set( $sub_body );
            foreach ( $sub_urls as $u ) {
                $urls[] = $u;
            }
        }
    }

    if ( empty( $urls ) ) {
        wp_send_json_error( array( 'message' => __( 'No URLs were retrieved.', 'en-sitemap-generator' ) ) );
    }

    // 4. Transform URLs.
    foreach ( $urls as &$u ) {
        $u['lang_url'] = esg_transform_url( $u['loc'], $lang );
    }
    unset( $u );

    // 5. Build the XML and write it.
    $xml_content = esg_build_url_set_xml( $urls, $lang );
    $output_path = esg_get_filepath( $lang );

    $written = false;
    if ( is_writable( ABSPATH ) ) {
        $written = file_put_contents( $output_path, $xml_content );
    }
    if ( false === $written ) {
        WP_Filesystem();
        global $wp_filesystem;
        if ( $wp_filesystem && $wp_filesystem->put_contents( $output_path, $xml_content, 0644 ) ) {
            $written = true;
        }
    }
    if ( false === $written ) {
        wp_send_json_error( array( 'message' => __( 'Failed to write the file. Please check write permissions on the site root: ', 'en-sitemap-generator' ) . $output_path ) );
    }

    wp_send_json_success( array(
        'total_urls' => count( $urls ),
        'filename'   => esg_get_filename( $lang ),
        'file_size'  => size_format( strlen( $xml_content ) ),
        'url'        => esg_get_file_url( $lang ),
        'lang'       => $lang,
    ) );
}
add_action( 'wp_ajax_esg_generate_sitemap', 'esg_ajax_generate_sitemap' );

/**
 * Parse a sitemap index XML document.
 *
 * @param string $xml_content XML content.
 * @return array
 */
function esg_parse_sitemap_index( $xml_content ) {
    $sub_sitemaps = array();
    $previous = libxml_use_internal_errors( true );
    $doc = new DOMDocument();
    $loaded = $doc->loadXML( $xml_content );
    libxml_clear_errors();
    libxml_use_internal_errors( $previous );
    if ( ! $loaded ) {
        return $sub_sitemaps;
    }
    $xpath = new DOMXPath( $doc );
    $nodes = $xpath->query( '//*[local-name()="sitemap"]' );
    foreach ( $nodes as $node ) {
        $loc     = '';
        $lastmod = '';
        foreach ( $node->childNodes as $child ) {
            $local = $child->localName;
            if ( 'loc' === $local ) {
                $loc = trim( $child->textContent );
            } elseif ( 'lastmod' === $local ) {
                $lastmod = trim( $child->textContent );
            }
        }
        if ( $loc ) {
            $sub_sitemaps[] = array( 'loc' => $loc, 'lastmod' => $lastmod );
        }
    }
    return $sub_sitemaps;
}

/**
 * Parse a urlset XML document.
 *
 * @param string $xml_content XML content.
 * @return array
 */
function esg_parse_url_set( $xml_content ) {
    $urls = array();
    $previous = libxml_use_internal_errors( true );
    $doc = new DOMDocument();
    $loaded = $doc->loadXML( $xml_content );
    libxml_clear_errors();
    libxml_use_internal_errors( $previous );
    if ( ! $loaded ) {
        return $urls;
    }
    $xpath = new DOMXPath( $doc );
    $nodes = $xpath->query( '//*[local-name()="url"]' );
    foreach ( $nodes as $node ) {
        $entry = array( 'loc' => '', 'lastmod' => '', 'changefreq' => '', 'priority' => '' );
        foreach ( $node->childNodes as $child ) {
            $local = $child->localName;
            if ( array_key_exists( $local, $entry ) ) {
                $entry[ $local ] = trim( $child->textContent );
            }
        }
        if ( $entry['loc'] ) {
            $urls[] = $entry;
        }
    }
    return $urls;
}

/**
 * Transform an original URL into its localized version.
 *
 * @param string $original_url Original URL.
 * @param string $lang         Language code.
 * @return string
 */
function esg_transform_url( $original_url, $lang ) {
    $parsed = wp_parse_url( $original_url );
    if ( ! isset( $parsed['scheme'] ) || ! isset( $parsed['host'] ) ) {
        return $original_url;
    }
    $path     = isset( $parsed['path'] ) ? $parsed['path'] : '';
    $query    = isset( $parsed['query'] ) ? $parsed['query'] : '';
    $fragment = isset( $parsed['fragment'] ) ? $parsed['fragment'] : '';
    $prefix   = esg_get_lang_prefix( $lang );

    if ( '' === $path || '/' === $path ) {
        $new_path = $prefix . '/';
    } else {
        $new_path = $prefix . '/' . ltrim( $path, '/' );
    }

    $new_url = $parsed['scheme'] . '://' . $parsed['host'];
    if ( isset( $parsed['port'] ) && $parsed['port'] ) {
        $new_url .= ':' . $parsed['port'];
    }
    $new_url .= $new_path;
    if ( $query ) {
        $new_url .= '?' . $query;
    }
    if ( $fragment ) {
        $new_url .= '#' . $fragment;
    }
    return $new_url;
}

/**
 * Build a urlset XML document.
 *
 * @param array  $urls URL list (each item contains a lang_url field).
 * @param string $lang Language code.
 * @return string
 */
function esg_build_url_set_xml( $urls, $lang ) {
    $lines = array();
    $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ( $urls as $u ) {
        $lines[] = '  <url>';
        $lines[] = '    <loc>' . esc_xml( $u['lang_url'] ) . '</loc>';
        if ( ! empty( $u['lastmod'] ) ) {
            $lines[] = '    <lastmod>' . esc_xml( $u['lastmod'] ) . '</lastmod>';
        }
        if ( ! empty( $u['changefreq'] ) ) {
            $lines[] = '    <changefreq>' . esc_xml( $u['changefreq'] ) . '</changefreq>';
        }
        if ( ! empty( $u['priority'] ) ) {
            $lines[] = '    <priority>' . esc_xml( $u['priority'] ) . '</priority>';
        }
        $lines[] = '  </url>';
    }
    $lines[] = '</urlset>';
    $lines[] = '';
    return implode( "\n", $lines );
}

/**
 * Plugin activation.
 */
function esg_activate() {
    // Initialize the default language.
    if ( ! get_option( 'esg_languages' ) ) {
        add_option( 'esg_languages', ESG_DEFAULT_LANGUAGES );
    }
    if ( ! get_option( 'esg_activated_at' ) ) {
        add_option( 'esg_activated_at', current_time( 'mysql' ) );
    }
}
register_activation_hook( __FILE__, 'esg_activate' );

/**
 * Plugin deactivation.
 */
function esg_deactivate() {
    // Do not delete files or options.
}
register_deactivation_hook( __FILE__, 'esg_deactivate' );

/**
 * Uninstall (only clears options; generated sitemap files are left untouched).
 */
function esg_uninstall() {
    delete_option( 'esg_source_sitemap' );
    delete_option( 'esg_languages' );
    delete_option( 'esg_activated_at' );
}
register_uninstall_hook( __FILE__, 'esg_uninstall' );
