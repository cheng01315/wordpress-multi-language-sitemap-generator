# 多语言站点地图生成器

> **语言 / Languages:** [简体中文](README.zh_CN.md) · [English](README.md)

适用于 **TranslatePress**（以及其他基于 URL 前缀的多语言插件）的辅助插件。

- **版本：** 2.1.0
- **作者：** MeowTool — https://www.meowtool.com/
- **许可证：** GPL-2.0-or-later

---

## 插件简介

本插件是 **TranslatePress** 的辅助工具。TranslatePress 通过在 URL 中插入语言前缀（如 `/en/`、`/fr/`）来实现多语言站点，但 WordPress 原生的 `wp-sitemap.xml` 只包含默认语言的链接，搜索引擎无法发现其他语言版本。

本插件会读取源站点地图（默认为 `wp-sitemap.xml`），遍历其中所有的子 sitemap，为每一条 URL 在域名后插入语言前缀，然后为每种语言生成一份独立的站点地图文件（如 `en-sitemap.xml`、`fr-sitemap.xml`），直接写入网站根目录，方便提交给 Google Search Console / Bing Webmaster 等搜索引擎。

**URL 转换示例：**

| 原始 URL | 语言 | 生成后的 URL |
| --- | --- | --- |
| `https://www.example.com/about/` | `en` | `https://www.example.com/en/about/` |
| `https://www.example.com/about/` | `fr` | `https://www.example.com/fr/about/` |
| `https://www.example.com/` | `es` | `https://www.example.com/es/` |

## 主要功能

- ✅ 读取 WordPress 原生 `wp-sitemap.xml`，或自定义源 sitemap 地址（兼容 Yoast SEO、Rank Math 等）
- ✅ 多语言管理：添加 / 移除语言（支持 `en`、`fr`、`pt-br` 等代码，2-8 位小写字母）
- ✅ 每种语言单独生成 `xx-sitemap.xml` 文件，写入网站根目录
- ✅ 手动生成、删除文件，与 TranslatePress 的 URL 前缀结构完美匹配
- ✅ 提供中文界面版本与英文界面版本

## 安装方法

本插件提供 **两个语言界面版本** 的 PHP 文件，请根据后台语言选择其中一个：

- `Multi-Language Sitemap Generator-zh_CN.php` —— 中文后台界面
- `Multi-Language Sitemap Generator-en.php` —— 英文后台界面

> ⚠️ 两个文件功能完全一致，仅后台界面语言不同，**选择其中一个**安装即可，请勿同时启用。

### 方法一：后台压缩上传（推荐新手）

1. 在本地选中要安装的 PHP 文件（例如 `Multi-Language Sitemap Generator-zh_CN.php`），将其压缩为 **zip** 格式。
   - ⚠️ 请直接对该 PHP 文件进行压缩，**不要**在外层再包一层文件夹，否则 WordPress 可能无法识别插件头。
   - 压缩后得到的文件例如 `Multi-Language Sitemap Generator-zh_CN.zip`。
2. 登录 WordPress 后台，进入 **插件 → 安装插件 → 上传插件**。
3. 选择刚才生成的 zip 文件，点击 **现在安装**。
4. 安装完成后点击 **启用插件**。

### 方法二：通过 FTP / 主机面板上传到插件目录

1. 使用 FTP 工具（如 FileZilla）或主机服务商提供的文件管理器，连接到服务器。
2. 将选好的 PHP 文件（例如 `Multi-Language Sitemap Generator-en.php`）上传到 WordPress 的插件目录：

   ```
   /wp-content/plugins/
   ```

3. 为了规范管理，建议先在该目录下新建一个文件夹（文件夹名不能与已有插件冲突），例如：

   ```
   /wp-content/plugins/multi-language-sitemap-generator/
   ```

   然后将 PHP 文件放入该文件夹中。最终路径示例：

   ```
   /wp-content/plugins/multi-language-sitemap-generator/Multi-Language Sitemap Generator-en.php
   ```

4. 登录 WordPress 后台，进入 **插件 → 已安装的插件**，找到 **Multi-Language Sitemap Generator**，点击 **启用**。

## 使用说明

1. 启用插件后，在后台左侧菜单进入 **工具 → Multi-Language Sitemap**。
2. **源 sitemap 配置**：默认使用 `wp-sitemap.xml`，如使用 Yoast SEO / Rank Math 等其他插件生成 sitemap，请在输入框中填入对应地址（如 `sitemap_index.xml`）并保存。
3. **添加语言**：在「添加新语言」处输入语言代码（如 `en`、`fr`、`de`、`ja`、`pt-br`），点击「添加语言」。
4. **生成站点地图**：在语言列表中点击对应语言的「Generate」按钮，即可在网站根目录生成 `xx-sitemap.xml` 文件。
5. **删除文件 / 移除语言**：
   - 「删除文件」—— 仅删除根目录中的 sitemap 文件，保留语言配置。
   - 「移除语言」—— 同时删除 sitemap 文件与语言配置。
6. 发布新内容后，请再次点击「Generate」刷新对应语言的 sitemap。

## 提交到搜索引擎

生成完成后，可访问如下地址查看：

```
https://你的域名/en-sitemap.xml
https://你的域名/fr-sitemap.xml
```

将上述地址分别提交到 Google Search Console、Bing Webmaster Tools 即可。

## 常见问题

**Q：Nginx 把静态 XML 文件拦截了，访问 404 怎么办？**
A：在 Nginx 配置中对每个 sitemap 文件添加例外，例如：

```nginx
location ~* /(en|fr|es)-sitemap\.xml$ {
    try_files $uri =404;
}
```

**Q：生成时报「写入失败」？**
A：请确认网站根目录（`ABSPATH`）对 PHP 进程可写，权限通常设为 `755`（目录）和 `644`（文件）。

**Q：插件删除后会清理文件吗？**
A：停用插件不会删除任何文件或配置；卸载插件仅清除数据库中的配置项，已生成的 sitemap 文件保留在根目录，需手动删除。

## 文件结构

```
en-sitemap-generator/
├── Multi-Language Sitemap Generator-en.php       # 英文后台界面
├── Multi-Language Sitemap Generator-zh_CN.php    # 中文后台界面
├── README.md                                      # 英文文档 (English)
└── README.zh_CN.md                                # 中文文档
```

---

© MeowTool — https://www.meowtool.com/
