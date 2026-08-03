<?php
/**
 * Plugin Name:       多语言站点地图生成器 (Multi-Language Sitemap Generator)
 * Plugin URI:        https://www.meowtool.com/
 * Description:        读取 WordPress 源站点地图（默认 wp-sitemap.xml），将所有 URL 加上语言前缀（如 /en/、/fr/），生成多语言版本站点地图（如 en-sitemap.xml、fr-sitemap.xml）并写入网站根目录。支持自定义源 sitemap 地址、多语言管理（添加/移除）、手动点击生成与删除，原生适配 TranslatePress 等基于 URL 前缀的多语言插件。本文件为中文界面版本，如需英文界面请使用 en-sitemap-generator.php。
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

// 禁止直接访问
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 插件常量
define( 'ESG_VERSION', '2.1.0' );
define( 'ESG_PLUGIN_FILE', __FILE__ );
define( 'ESG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ESG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
// 文件名前缀格式：{lang}-sitemap.xml
define( 'ESG_FILENAME_PATTERN', '%s-sitemap.xml' );
// 默认语言列表
define( 'ESG_DEFAULT_LANGUAGES', 'en' );

/**
 * 获取源 sitemap 地址（带保存功能）
 *
 * @return string
 */
function esg_get_source_sitemap() {
    $default = trailingslashit( home_url() ) . 'wp-sitemap.xml';
    $saved = get_option( 'esg_source_sitemap', '' );
    return $saved ? $saved : $default;
}

/**
 * 获取已配置语言列表
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
 * 保存语言列表
 *
 * @param array $langs 语言代码数组
 * @return bool
 */
function esg_save_languages( $langs ) {
    $langs = array_unique( array_filter( $langs ) );
    sort( $langs );
    return update_option( 'esg_languages', implode( ',', $langs ) );
}

/**
 * 校验语言代码格式（仅允许 2-8 位小写字母，如 en, fr, es, de, ja, zh, pt-br）
 *
 * @param string $code 语言代码
 * @return bool
 */
function esg_validate_language_code( $code ) {
    $code = strtolower( trim( $code ) );
    return (bool) preg_match( '/^[a-z]{2,8}(-[a-z]{2,8})?$/', $code );
}

/**
 * 获取指定语言的 sitemap 文件名
 *
 * @param string $lang 语言代码
 * @return string
 */
function esg_get_filename( $lang ) {
    return sprintf( ESG_FILENAME_PATTERN, $lang );
}

/**
 * 获取指定语言的 sitemap 文件绝对路径
 *
 * @param string $lang 语言代码
 * @return string
 */
function esg_get_filepath( $lang ) {
    return trailingslashit( ABSPATH ) . esg_get_filename( $lang );
}

/**
 * 获取指定语言的 sitemap 访问 URL
 *
 * @param string $lang 语言代码
 * @return string
 */
function esg_get_file_url( $lang ) {
    return trailingslashit( home_url() ) . esg_get_filename( $lang );
}

/**
 * 获取指定语言的 URL 前缀路径（如 /en, /fr）
 *
 * @param string $lang 语言代码
 * @return string
 */
function esg_get_lang_prefix( $lang ) {
    return '/' . $lang;
}

/**
 * 添加后台管理菜单（在"工具"菜单下）
 */
function esg_add_admin_menu() {
    add_management_page(
        __( '多语言站点地图生成器', 'en-sitemap-generator' ),
        __( '多语言 Sitemap 生成', 'en-sitemap-generator' ),
        'manage_options',
        'en-sitemap-generator',
        'esg_render_admin_page'
    );
}
add_action( 'admin_menu', 'esg_add_admin_menu' );

/**
 * 在后台头部添加样式
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
 * 渲染后台管理页面
 */
function esg_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( '您没有权限访问此页面。', 'en-sitemap-generator' ) );
    }

    $home_url        = home_url();
    $source_sitemap  = esg_get_source_sitemap();
    $languages       = esg_get_languages();
    $nonce           = wp_create_nonce( 'esg_admin_nonce' );
    ?>
    <div class="wrap esg-wrap">
        <h1>
            <?php esc_html_e( '多语言站点地图生成器', 'en-sitemap-generator' ); ?>
            <span style="font-size:13px;color:#666;font-weight:normal;">v<?php echo esc_html( ESG_VERSION ); ?></span>
            <span style="font-size:12px;color:#999;font-weight:normal;margin-left:8px;">（中文版 / Chinese edition）</span>
        </h1>

        <div class="esg-card">
            <h2>插件说明</h2>
            <p>本插件读取源站点地图，遍历所有子地图，并将每个 URL 在域名后插入语言前缀（如 <span class="esg-code">/en/</span>、<span class="esg-code">/fr/</span>），
            为每种语言生成独立的 sitemap 文件（如 <span class="esg-code">en-sitemap.xml</span>、<span class="esg-code">fr-sitemap.xml</span>），写入网站根目录。原生适配 TranslatePress（默认「URL 中语言代码」模式）。</p>
            <p><strong>转换规则示例：</strong></p>
            <ul style="list-style:disc;padding-left:24px;">
                <li><span class="esg-code"><?php echo esc_html( $home_url ); ?>/about/</span> + 语言 <span class="esg-code">en</span> &rarr; <span class="esg-code"><?php echo esc_html( $home_url ); ?>/en/about/</span></li>
                <li><span class="esg-code"><?php echo esc_html( $home_url ); ?>/about/</span> + 语言 <span class="esg-code">fr</span> &rarr; <span class="esg-code"><?php echo esc_html( $home_url ); ?>/fr/about/</span></li>
                <li><span class="esg-code"><?php echo esc_html( $home_url ); ?>/</span> + 语言 <span class="esg-code">es</span> &rarr; <span class="esg-code"><?php echo esc_html( $home_url ); ?>/es/</span></li>
            </ul>
        </div>

        <div class="esg-card">
            <h2>源站点地图配置</h2>
            <p>指定要读取的源 sitemap 地址，默认为 WordPress 原生的 <span class="esg-code">wp-sitemap.xml</span>。</p>
            <div class="esg-form-row">
                <input type="text" id="esg-source-url" class="esg-input" value="<?php echo esc_attr( $source_sitemap ); ?>" placeholder="https://www.example.com/wp-sitemap.xml">
                <button type="button" id="esg-save-source-btn" class="esg-btn esg-btn-secondary">保存配置</button>
            </div>
            <p class="esg-help">提示：通常无需修改。如使用 Yoast SEO、Rank Math 等插件生成的 sitemap，可在此填入对应地址（如 <span class="esg-code">sitemap_index.xml</span>）。</p>
            <div id="esg-source-status" class="esg-status"></div>
        </div>

        <div class="esg-card">
            <h2>语言管理</h2>
            <p>添加、移除语言，并为每种语言生成对应的 sitemap 文件。</p>

            <div class="esg-section-title">添加新语言</div>
            <div class="esg-form-row">
                <input type="text" id="esg-new-lang" class="esg-input esg-input-small" placeholder="如 fr, es, de, ja" maxlength="17">
                <button type="button" id="esg-add-lang-btn" class="esg-btn esg-btn-primary">添加语言</button>
                <span class="esg-help">仅支持 2-8 位小写字母，可含一个连字符（如 pt-br）。</span>
            </div>
            <div id="esg-lang-status" class="esg-status"></div>

            <div class="esg-section-title" style="margin-top:20px;">已配置语言列表</div>
            <div class="esg-lang-list" id="esg-lang-list">
                <?php foreach ( $languages as $lang ) : ?>
                    <?php esg_render_lang_row( $lang ); ?>
                <?php endforeach; ?>
            </div>
            <?php if ( empty( $languages ) ) : ?>
                <p style="color:#666;font-style:italic;">尚未配置任何语言，请先添加。</p>
            <?php endif; ?>
        </div>

        <div class="esg-card">
            <h2>使用提示</h2>
            <ul style="list-style:disc;padding-left:24px;">
                <li>添加语言后，点击该语言行的「生成」按钮即可创建对应的 sitemap 文件</li>
                <li>「删除文件」仅删除根目录下的 sitemap 文件，不会移除语言配置</li>
                <li>「移除语言」会同时删除该语言的 sitemap 文件并从配置中移除</li>
                <li>添加新内容后，需重新点击「生成」按钮更新 sitemap</li>
                <li>Nginx 若配置了伪静态拦截，需为每个 sitemap 文件添加 <span class="esg-code">try_files $uri =404;</span> 例外</li>
                <li>适配 TranslatePress：在 TranslatePress 中保持默认「URL 中语言代码」模式，并使用与本插件相同的语言代码</li>
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
                callback({ success: false, data: { message: '<?php echo esc_js( __( '请求失败：', 'en-sitemap-generator' ) ); ?>' + err.message } });
            });
        }

        // 保存源 sitemap 地址
        var saveSourceBtn = document.getElementById('esg-save-source-btn');
        var sourceInput = document.getElementById('esg-source-url');
        var sourceStatus = document.getElementById('esg-source-status');
        saveSourceBtn.addEventListener('click', function() {
            var url = sourceInput.value.trim();
            if (!url) { setStatus(sourceStatus, 'error', '<strong><?php echo esc_js( __( '错误：', 'en-sitemap-generator' ) ); ?></strong> <?php echo esc_js( __( '地址不能为空。', 'en-sitemap-generator' ) ); ?>'); return; }
            saveSourceBtn.disabled = true;
            setStatus(sourceStatus, 'loading', '<?php echo esc_js( __( '保存中...', 'en-sitemap-generator' ) ); ?>');
            ajaxRequest('esg_save_source', { source_url: url }, function(json) {
                saveSourceBtn.disabled = false;
                if (json.success) {
                    setStatus(sourceStatus, 'success', '<strong><?php echo esc_js( __( '保存成功！', 'en-sitemap-generator' ) ); ?></strong> <?php echo esc_js( __( '源 sitemap 地址已更新。', 'en-sitemap-generator' ) ); ?>');
                } else {
                    setStatus(sourceStatus, 'error', '<strong><?php echo esc_js( __( '保存失败：', 'en-sitemap-generator' ) ); ?></strong> ' + (json.data && json.data.message ? json.data.message : '<?php echo esc_js( __( '未知错误。', 'en-sitemap-generator' ) ); ?>'));
                }
            });
        });

        // 添加语言
        var addLangBtn = document.getElementById('esg-add-lang-btn');
        var newLangInput = document.getElementById('esg-new-lang');
        var langStatus = document.getElementById('esg-lang-status');
        addLangBtn.addEventListener('click', function() {
            var code = newLangInput.value.trim().toLowerCase();
            if (!code) { setStatus(langStatus, 'error', '<strong><?php echo esc_js( __( '错误：', 'en-sitemap-generator' ) ); ?></strong> <?php echo esc_js( __( '请输入语言代码。', 'en-sitemap-generator' ) ); ?>'); return; }
            addLangBtn.disabled = true;
            setStatus(langStatus, 'loading', '<?php echo esc_js( __( '添加中...', 'en-sitemap-generator' ) ); ?>');
            ajaxRequest('esg_add_language', { lang: code }, function(json) {
                addLangBtn.disabled = false;
                if (json.success) {
                    setStatus(langStatus, 'success', '<strong><?php echo esc_js( __( '添加成功！', 'en-sitemap-generator' ) ); ?></strong> <?php echo esc_js( __( '语言', 'en-sitemap-generator' ) ); ?> ' + code + ' <?php echo esc_js( __( '已添加。', 'en-sitemap-generator' ) ); ?>');
                    newLangInput.value = '';
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    setStatus(langStatus, 'error', '<strong><?php echo esc_js( __( '添加失败：', 'en-sitemap-generator' ) ); ?></strong> ' + (json.data && json.data.message ? json.data.message : '<?php echo esc_js( __( '未知错误。', 'en-sitemap-generator' ) ); ?>'));
                }
            });
        });

        // 语言行操作（事件委托）
        var langList = document.getElementById('esg-lang-list');
        langList.addEventListener('click', function(e) {
            var btn = e.target.closest('button.esg-lang-action');
            if (!btn) return;
            var lang = btn.getAttribute('data-lang');
            var action = btn.getAttribute('data-action');

            if (action === 'generate') {
                if (!confirm('<?php echo esc_js( __( '确定为', 'en-sitemap-generator' ) ); ?> ' + lang + ' <?php echo esc_js( __( '生成 sitemap 吗？', 'en-sitemap-generator' ) ); ?>')) return;
                var statusEl = btn.closest('.esg-lang-row').querySelector('.esg-lang-meta-status');
                btn.disabled = true;
                if (statusEl) setStatus(statusEl, 'loading', '<?php echo esc_js( __( '生成中...', 'en-sitemap-generator' ) ); ?>');
                ajaxRequest('esg_generate_sitemap', { lang: lang }, function(json) {
                    btn.disabled = false;
                    if (json.success) {
                        if (statusEl) {
                            setStatus(statusEl, 'success', '<strong><?php echo esc_js( __( '生成成功！', 'en-sitemap-generator' ) ); ?></strong> <?php echo esc_js( __( '共', 'en-sitemap-generator' ) ); ?> ' + json.data.total_urls + ' <?php echo esc_js( __( '个 URL，文件大小', 'en-sitemap-generator' ) ); ?> ' + json.data.file_size + '。<br><?php echo esc_js( __( '访问：', 'en-sitemap-generator' ) ); ?><a href="' + json.data.url + '" target="_blank">' + json.data.url + '</a>');
                        }
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        if (statusEl) setStatus(statusEl, 'error', '<strong><?php echo esc_js( __( '失败：', 'en-sitemap-generator' ) ); ?></strong> ' + (json.data && json.data.message ? json.data.message : '<?php echo esc_js( __( '未知错误。', 'en-sitemap-generator' ) ); ?>'));
                    }
                });
            } else if (action === 'delete_file') {
                if (!confirm('<?php echo esc_js( __( '确定删除', 'en-sitemap-generator' ) ); ?> ' + lang + ' <?php echo esc_js( __( '的 sitemap 文件吗？此操作不可恢复。', 'en-sitemap-generator' ) ); ?>')) return;
                btn.disabled = true;
                ajaxRequest('esg_delete_file', { lang: lang }, function(json) {
                    btn.disabled = false;
                    if (json.success) {
                        alert('<?php echo esc_js( __( '文件已删除', 'en-sitemap-generator' ) ); ?>');
                        location.reload();
                    } else {
                        alert('<?php echo esc_js( __( '删除失败：', 'en-sitemap-generator' ) ); ?> ' + (json.data && json.data.message ? json.data.message : '<?php echo esc_js( __( '未知错误。', 'en-sitemap-generator' ) ); ?>'));
                    }
                });
            } else if (action === 'remove_lang') {
                if (!confirm('<?php echo esc_js( __( '确定移除语言', 'en-sitemap-generator' ) ); ?> ' + lang + ' <?php echo esc_js( __( '吗？将同时删除对应的 sitemap 文件和语言配置。', 'en-sitemap-generator' ) ); ?>')) return;
                btn.disabled = true;
                ajaxRequest('esg_remove_language', { lang: lang }, function(json) {
                    btn.disabled = false;
                    if (json.success) {
                        alert('<?php echo esc_js( __( '语言已移除', 'en-sitemap-generator' ) ); ?>');
                        location.reload();
                    } else {
                        alert('<?php echo esc_js( __( '移除失败：', 'en-sitemap-generator' ) ); ?> ' + (json.data && json.data.message ? json.data.message : '<?php echo esc_js( __( '未知错误。', 'en-sitemap-generator' ) ); ?>'));
                    }
                });
            }
        });
    })();
    </script>
    <?php
}

/**
 * 渲染单个语言行
 *
 * @param string $lang 语言代码
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
                    <?php echo $exists ? esc_html__( '已生成', 'en-sitemap-generator' ) : esc_html__( '未生成', 'en-sitemap-generator' ); ?>
                </span>
                <span style="margin-left:8px;"><?php esc_html_e( '文件：', 'en-sitemap-generator' ); ?> <span class="esg-code"><?php echo esc_html( esg_get_filename( $lang ) ); ?></span></span>
            </div>
            <div style="margin-top:4px;">
                <?php esc_html_e( '大小：', 'en-sitemap-generator' ); ?> <?php echo esc_html( $size ); ?>
                <?php if ( $exists ) : ?> | <?php esc_html_e( '最后生成：', 'en-sitemap-generator' ); ?> <?php echo esc_html( $modified ); ?> | <a href="<?php echo esc_url( $file_url ); ?>" target="_blank"><?php esc_html_e( '访问', 'en-sitemap-generator' ); ?></a><?php endif; ?>
            </div>
            <div class="esg-lang-meta-status esg-status"></div>
        </div>
        <div class="esg-lang-actions">
            <button type="button" class="esg-btn esg-btn-primary esg-lang-action" data-lang="<?php echo esc_attr( $lang ); ?>" data-action="generate"><?php esc_html_e( '生成', 'en-sitemap-generator' ); ?></button>
            <button type="button" class="esg-btn esg-btn-danger esg-lang-action" data-lang="<?php echo esc_attr( $lang ); ?>" data-action="delete_file" <?php echo $exists ? '' : 'disabled'; ?>><?php esc_html_e( '删除文件', 'en-sitemap-generator' ); ?></button>
            <button type="button" class="esg-btn esg-btn-secondary esg-lang-action" data-lang="<?php echo esc_attr( $lang ); ?>" data-action="remove_lang"><?php esc_html_e( '移除语言', 'en-sitemap-generator' ); ?></button>
        </div>
    </div>
    <?php
}

/**
 * AJAX：保存源 sitemap 地址
 */
function esg_ajax_save_source() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( '权限不足。', 'en-sitemap-generator' ) ), 403 );
    }
    if ( ! check_ajax_referer( 'esg_admin_nonce', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => __( '安全校验失败。', 'en-sitemap-generator' ) ), 403 );
    }
    $url = isset( $_POST['source_url'] ) ? esc_url_raw( wp_unslash( $_POST['source_url'] ) ) : '';
    if ( empty( $url ) ) {
        wp_send_json_error( array( 'message' => __( '源 sitemap 地址不能为空。', 'en-sitemap-generator' ) ) );
    }
    update_option( 'esg_source_sitemap', $url );
    wp_send_json_success( array( 'message' => __( '已保存', 'en-sitemap-generator' ), 'url' => $url ) );
}
add_action( 'wp_ajax_esg_save_source', 'esg_ajax_save_source' );

/**
 * AJAX：添加语言
 */
function esg_ajax_add_language() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( '权限不足。', 'en-sitemap-generator' ) ), 403 );
    }
    if ( ! check_ajax_referer( 'esg_admin_nonce', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => __( '安全校验失败。', 'en-sitemap-generator' ) ), 403 );
    }
    $lang = isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : '';
    if ( ! esg_validate_language_code( $lang ) ) {
        wp_send_json_error( array( 'message' => __( '语言代码格式不正确，仅支持 2-8 位小写字母（可含一个连字符，如 pt-br）。', 'en-sitemap-generator' ) ) );
    }
    $langs = esg_get_languages();
    if ( in_array( $lang, $langs, true ) ) {
        wp_send_json_error( array( 'message' => sprintf( __( '语言 %s 已存在。', 'en-sitemap-generator' ), $lang ) ) );
    }
    $langs[] = $lang;
    esg_save_languages( $langs );
    wp_send_json_success( array( 'message' => __( '已添加', 'en-sitemap-generator' ), 'lang' => $lang ) );
}
add_action( 'wp_ajax_esg_add_language', 'esg_ajax_add_language' );

/**
 * AJAX：移除语言（同时删除对应文件）
 */
function esg_ajax_remove_language() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( '权限不足。', 'en-sitemap-generator' ) ), 403 );
    }
    if ( ! check_ajax_referer( 'esg_admin_nonce', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => __( '安全校验失败。', 'en-sitemap-generator' ) ), 403 );
    }
    $lang = isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : '';
    if ( ! esg_validate_language_code( $lang ) ) {
        wp_send_json_error( array( 'message' => __( '语言代码无效。', 'en-sitemap-generator' ) ) );
    }
    // 删除文件
    $filepath = esg_get_filepath( $lang );
    if ( file_exists( $filepath ) ) {
        @unlink( $filepath );
    }
    // 从配置中移除
    $langs = esg_get_languages();
    $langs = array_diff( $langs, array( $lang ) );
    esg_save_languages( $langs );
    wp_send_json_success( array( 'message' => __( '已移除', 'en-sitemap-generator' ), 'lang' => $lang ) );
}
add_action( 'wp_ajax_esg_remove_language', 'esg_ajax_remove_language' );

/**
 * AJAX：删除指定语言的 sitemap 文件（保留语言配置）
 */
function esg_ajax_delete_file() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( '权限不足。', 'en-sitemap-generator' ) ), 403 );
    }
    if ( ! check_ajax_referer( 'esg_admin_nonce', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => __( '安全校验失败。', 'en-sitemap-generator' ) ), 403 );
    }
    $lang = isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : '';
    if ( ! esg_validate_language_code( $lang ) ) {
        wp_send_json_error( array( 'message' => __( '语言代码无效。', 'en-sitemap-generator' ) ) );
    }
    $filepath = esg_get_filepath( $lang );
    if ( ! file_exists( $filepath ) ) {
        wp_send_json_error( array( 'message' => __( '文件不存在。', 'en-sitemap-generator' ) ) );
    }
    if ( ! @unlink( $filepath ) ) {
        wp_send_json_error( array( 'message' => __( '删除失败，请检查文件权限。', 'en-sitemap-generator' ) ) );
    }
    wp_send_json_success( array( 'message' => __( '已删除', 'en-sitemap-generator' ), 'lang' => $lang ) );
}
add_action( 'wp_ajax_esg_delete_file', 'esg_ajax_delete_file' );

/**
 * AJAX：生成指定语言的 sitemap
 */
function esg_ajax_generate_sitemap() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( '权限不足。', 'en-sitemap-generator' ) ), 403 );
    }
    if ( ! check_ajax_referer( 'esg_admin_nonce', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => __( '安全校验失败。', 'en-sitemap-generator' ) ), 403 );
    }
    $lang = isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : '';
    if ( ! esg_validate_language_code( $lang ) ) {
        wp_send_json_error( array( 'message' => __( '语言代码无效。', 'en-sitemap-generator' ) ) );
    }

    $source_url = esg_get_source_sitemap();

    // 1. 获取主地图索引
    $master_response = wp_remote_get( $source_url, array(
        'timeout'    => 30,
        'user-agent' => 'MultiLangSitemapGenerator/' . ESG_VERSION,
    ) );
    if ( is_wp_error( $master_response ) ) {
        wp_send_json_error( array( 'message' => __( '获取源 sitemap 失败：', 'en-sitemap-generator' ) . $master_response->get_error_message() ) );
    }
    $master_code = wp_remote_retrieve_response_code( $master_response );
    if ( 200 !== (int) $master_code ) {
        wp_send_json_error( array( 'message' => sprintf( __( '获取源 sitemap 失败，HTTP 状态码：%s', 'en-sitemap-generator' ), $master_code ) ) );
    }
    $master_body = wp_remote_retrieve_body( $master_response );
    if ( empty( $master_body ) ) {
        wp_send_json_error( array( 'message' => __( '源 sitemap 内容为空。', 'en-sitemap-generator' ) ) );
    }

    // 2. 解析主地图
    $sub_sitemaps = esg_parse_sitemap_index( $master_body );
    if ( empty( $sub_sitemaps ) ) {
        // 可能是 urlset 而非 sitemapindex，直接当作 urlset 处理
        $urls = esg_parse_url_set( $master_body );
        if ( empty( $urls ) ) {
            wp_send_json_error( array( 'message' => __( '源 sitemap 格式无法识别（既非 sitemapindex 也非 urlset）。', 'en-sitemap-generator' ) ) );
        }
    } else {
        // 3. 遍历子地图
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
        wp_send_json_error( array( 'message' => __( '未获取到任何 URL。', 'en-sitemap-generator' ) ) );
    }

    // 4. 转换 URL
    foreach ( $urls as &$u ) {
        $u['lang_url'] = esg_transform_url( $u['loc'], $lang );
    }
    unset( $u );

    // 5. 构造 XML 并写入
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
        wp_send_json_error( array( 'message' => __( '写入文件失败，请检查根目录写入权限：', 'en-sitemap-generator' ) . $output_path ) );
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
 * 解析 sitemap index XML
 *
 * @param string $xml_content XML 内容
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
 * 解析 urlset XML
 *
 * @param string $xml_content XML 内容
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
 * 将原 URL 转换为指定语言版本
 *
 * @param string $original_url 原 URL
 * @param string $lang 语言代码
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
 * 构造 urlset XML
 *
 * @param array  $urls URL 列表（含 lang_url 字段）
 * @param string $lang 语言代码
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
 * 插件激活
 */
function esg_activate() {
    // 初始化默认语言
    if ( ! get_option( 'esg_languages' ) ) {
        add_option( 'esg_languages', ESG_DEFAULT_LANGUAGES );
    }
    if ( ! get_option( 'esg_activated_at' ) ) {
        add_option( 'esg_activated_at', current_time( 'mysql' ) );
    }
}
register_activation_hook( __FILE__, 'esg_activate' );

/**
 * 插件停用
 */
function esg_deactivate() {
    // 不删除文件与配置
}
register_deactivation_hook( __FILE__, 'esg_deactivate' );

/**
 * 卸载（仅清理选项，不删除已生成的 sitemap 文件）
 */
function esg_uninstall() {
    delete_option( 'esg_source_sitemap' );
    delete_option( 'esg_languages' );
    delete_option( 'esg_activated_at' );
}
register_uninstall_hook( __FILE__, 'esg_uninstall' );
