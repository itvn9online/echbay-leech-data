# EchBay Leech Data

Plugin WordPress độc lập để **import / crawl nội dung HTML** từ website khác vào WordPress (post, page, CPT, hoặc WooCommerce product).

Tách từ chức năng **Leech data** trong EchBay (`echbaydotcom` → tab `eb-coder&tab=leech_data`), viết lại theo chuẩn plugin: AJAX + nonce, không phụ thuộc theme.

|                   |                           |
| ----------------- | ------------------------- |
| **Version**       | 0.1.0                     |
| **Requires**      | WordPress 5.8+, PHP 7.4+  |
| **Slug / folder** | `echbay-leech-data`       |
| **Admin**         | WP Admin → **Leech Data** |
| **Capability**    | `manage_options`          |

---

## Cài đặt

1. Copy thư mục `echbay-leech-data` vào `wp-content/plugins/`.
2. Vào **Plugins → Activate “EchBay Leech Data”**.
3. Mở menu **Leech Data** trên sidebar admin.

> Hiện plugin đang nằm tạm trong repo `echbaydotcom`. Khi tách project: cut nguyên folder này sang `wp-content/plugins/echbay-leech-data`.

---

## Cách dùng nhanh

1. Nhập **URL danh mục** (hoặc tick “Đây là URL trang chi tiết”).
2. Điền **selector list URL** (ví dụ `#products li a.thumb`).
3. Bấm **Bắt đầu lấy danh sách** → queue URL xuất hiện.
4. Cấu hình selectors chi tiết (title, content, image…).
5. Bấm **Lấy chi tiết…** hoặc tick **Tự động tiếp tục** + **Đăng / Import**.
6. (Tuỳ chọn) **Lưu profile domain** để lần sau khỏi nhập lại selectors.

### Cú pháp selector (giống bản EchBay)

| Cú pháp         | Ý nghĩa                         |
| --------------- | ------------------------------- |
| `#id .class`    | jQuery/CSS selector thường      |
| `a, b, c`       | Lấy cái đầu tiên có nội dung    |
| `a && b`        | Ghép nội dung nhiều khối        |
| `:each`         | Lặp tất cả node, ghép bằng tag  |
| `:eachn`        | Lặp, ghép bằng xuống dòng       |
| `img[data-src]` | Lấy attribute tùy chỉnh của ảnh |

---

## Kiến trúc

```
echbay-leech-data/
├── echbay-leech-data.php     # bootstrap
├── README.md                 # file này
├── includes/
│   ├── Plugin.php
│   ├── Admin/                # menu + assets
│   ├── Ajax/                 # fetch / import / profile / term / image / cache
│   ├── Services/             # HttpClient, HtmlCache, Deduper, ImageImporter…
│   ├── Adapters/             # NativePost, WooProduct
│   └── Support/              # Capabilities, Options
├── assets/css|js/
└── views/admin-page.php
```

### Luồng

```
Admin UI
  → wp_ajax_eld_fetch_html     (HttpClient + file cache)
  → JS parse selectors         (assets/js/admin.js)
  → wp_ajax_eld_import_item    (Deduper + Adapter→wp_insert/update_post)
```

### AJAX actions

| Action                                                          | Việc                           |
| --------------------------------------------------------------- | ------------------------------ |
| `eld_fetch_html`                                                | Tải HTML remote (+ cache)      |
| `eld_import_item`                                               | Insert/update post             |
| `eld_save_profile` / `eld_list_profiles` / `eld_delete_profile` | Profile theo domain            |
| `eld_save_list_urls`                                            | Lưu danh sách URL              |
| `eld_create_term`                                               | Tạo category/term              |
| `eld_import_image`                                              | Sideload 1 ảnh vào Media       |
| `eld_clear_cache`                                               | Xóa `uploads/eld-cache/*.html` |

### Meta keys

| Key                                  | Ý nghĩa                      |
| ------------------------------------ | ---------------------------- |
| `_eld_source_sku`                    | SKU dedupe (`hostname-path`) |
| `_eld_source_url`                    | URL nguồn                    |
| `_eld_imported_at`                   | Timestamp                    |
| `_eld_price` / `_eld_sale_price` / … | Field phụ                    |

Tuỳ chọn ghi thêm meta EchBay (`_eb_product_leech_sku`, …) nếu bật checkbox trên UI.

Cache HTML: `wp-content/uploads/eld-cache/`.

---

## Adapters

1. **Native post / CPT** — mặc định; chọn post type + taxonomy trên form.
2. **WooCommerce product** — nếu Woo đang active: ghi `_regular_price`, `_sale_price`, `_sku`, `product_cat`.
3. **EchBay meta bridge** — checkbox “Ghi thêm meta EchBay” khi import vào site vẫn dùng EchBay.

---

## Nguồn gốc (EchBay)

| File cũ                                          | Thay bằng                                  |
| ------------------------------------------------ | ------------------------------------------ |
| `echbay/leech_data.php` + `html/leech_data.html` | `views/admin-page.php`                     |
| `echbay/js/leech_data.js`                        | `assets/js/admin.js` (port lõi selector)   |
| `global/temp/leech_data.php`                     | `Ajax\FetchRemote` + `Services\HttpClient` |
| `echbay/func/leech_data.php`                     | `Ajax\SavePost` + `Adapters\*`             |
| `echbay/func/leech_data_save.php`                | `Ajax\SaveProfile`                         |
| `process?set_module=` / `temp?set_module=`       | `admin-ajax.php` + nonce                   |

---

## Bảo mật & pháp lý

- Chỉ import nội dung bạn **có quyền** sử dụng.
- Tôn trọng `robots.txt` / ToS; dùng **giãn cách** giữa các request.
- Endpoint AJAX: `check_ajax_referer` + `manage_options`.
- Chống SSRF cơ bản (chặn localhost / IP private).
- HTML lưu vào post qua `wp_kses`.

---

## Roadmap / chưa port hết

MVP 0.1 đã có: fetch + cache, parse selector, queue, import, dedupe, profile, Woo adapter, EchBay meta option.

Chưa đầy đủ so với bản EchBay cũ (có thể bổ sung sau):

- [ ] Iframe mode cho list load bằng AJAX phía nguồn
- [ ] Download hàng loạt ảnh **trong** `post_content` (rewrite URL)
- [ ] Export / Import file profile JSON
- [ ] Log import chi tiết (CPT / table)
- [ ] Cron sync định kỳ
- [ ] Headless browser cho SPA

---

## Ghi chú cho maintainer / AI khi sửa code

Plugin này là **công cụ import HTML → WordPress**, không phải theme. Khi chỉnh sửa:

- **Prefix:** PHP namespace `ELD\`, AJAX `eld_*`, options `eld_*`, meta `_eld_*`, cache dir `eld-cache`.
- **UI + selector engine** nằm chủ yếu ở `assets/js/admin.js` (port từ `leech_data.js` EchBay).
- **Ghi bài** qua `Adapters\NativePostAdapter` / `WooProductAdapter` — không dùng SQL raw / helper `_eb_*`.
- **Fetch remote** chỉ qua `Services\HttpClient` (có check SSRF); không gọi `file_get_contents` URL trực tiếp từ AJAX.
- Giữ UX selector quen thuộc; tính năng mới nên thêm adapter/option thay vì hard-code meta EchBay.
- Không phụ thuộc `echbaydotcom` khi đã tách repo.

## Ghi chú tách project

Cut nguyên folder này sang:

```text
wp-content/plugins/echbay-leech-data/
```

Không cần phụ thuộc `echbaydotcom`. File README này là tài liệu chính của plugin.
