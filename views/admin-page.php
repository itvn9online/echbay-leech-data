<?php
if (!defined('ABSPATH')) {
	exit;
}
/** @var array $settings */
/** @var array $profiles */
/** @var string $saved_list */
/** @var array $post_types */
/** @var string $default_pt */
/** @var string $taxonomy_value */
/** @var array $initial_terms */
?>
<div class="wrap eld-wrap">
	<h1>EchBay Leech Data <small style="font-weight:400;font-size:13px;color:#646970;">v<?php echo esc_html(ELD_VERSION); ?></small></h1>
	<p class="description">Import nội dung HTML từ website khác vào WordPress. Chỉ dùng với nội dung bạn có quyền sử dụng. Tôn trọng robots.txt / ToS site nguồn.</p>

	<div class="eld-toolbar">
		<button type="button" class="button" id="eld-clear-cache">Xóa cache HTML</button>
		<button type="button" class="button" id="eld-save-profile">Lưu profile domain hiện tại</button>
	</div>

	<div class="eld-grid">
		<section class="eld-card">
			<h2>1. URL danh mục / chi tiết</h2>
			<textarea id="eld-categories-url" rows="4" class="large-text" placeholder="https://example.com/category/&#10;Có thể nhiều dòng. Dòng 2 (tuỳ chọn): category-slug để auto chọn nhóm"></textarea>
			<p>
				<label><input type="checkbox" id="eld-is-detail-url" /> Đây là URL trang chi tiết (bỏ qua bước list)</label>
			</p>
			<p>
				<label>Selector list URL sản phẩm</label>
				<input type="text" id="eld-list-selector" class="large-text" placeholder="#products li a.thumb" />
			</p>
			<p>
				<label><input type="checkbox" id="eld-full-head" /> Lấy cả HEAD (mặc định chỉ BODY)</label>
				<label><input type="checkbox" id="eld-convert-encoding" /> Convert encoding (GBK…)</label>
				<label><input type="checkbox" id="eld-bypass-cache" /> Bỏ qua cache</label>
			</p>
			<p>
				<button type="button" class="button button-primary" id="eld-fetch-list">Bắt đầu lấy danh sách</button>
			</p>
			<ol id="eld-url-queue" class="eld-url-list"></ol>
			<ol id="eld-url-done" class="eld-url-list eld-done"></ol>
		</section>

		<section class="eld-card">
			<h2>2. Cấu hình &amp; nhóm</h2>
			<p>
				<label>Post type</label>
				<select id="eld-post-type">
					<?php foreach ($post_types as $pt) : ?>
						<option value="<?php echo esc_attr($pt->name); ?>" <?php selected($default_pt, $pt->name); ?>>
							<?php echo esc_html($pt->labels->singular_name . ' (' . $pt->name . ')'); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label>Taxonomy</label>
				<input type="text" id="eld-taxonomy" value="<?php echo esc_attr($taxonomy_value); ?>" readonly />
				<span class="description">Tự theo Post type: <code>post</code> → <code>category</code>, <code>product</code> → <code>product_cat</code></span>
			</p>
			<p>
				<label>Danh mục (cấp 1)</label>
				<select id="eld-term-id">
					<option value="0">— không chọn —</option>
					<?php foreach ($initial_terms as $term) : ?>
						<option value="<?php echo esc_attr($term['id']); ?>"><?php echo esc_html($term['name']); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label><input type="checkbox" id="eld-auto-next" /> Tự động tiếp tục (chi tiết + đăng)</label>
				<label>Giãn cách (giây) <input type="number" id="eld-delay" value="3" min="0" style="width:70px" /></label>
			</p>
			<p>
				<label><input type="checkbox" id="eld-download-images" /> Tải ảnh đại diện về Media Library</label>
			</p>
			<p>
				<label><input type="checkbox" id="eld-download-content-images" /> Tải ảnh trong nội dung về Media Library</label>
				<span class="description">Bỏ qua emoji/icon: URL kiểu emoji, SVG, hoặc ảnh &lt; 120px / &lt; 8KB.</span>
			</p>
			<p>
				<label>Min title length</label>
				<input type="number" id="eld-min-title" value="16" min="1" style="width:80px" />
			</p>
			<p>
				<label>Remove HTML trước khi parse (CSS, cách nhau bởi dấu phẩy)</label>
				<input type="text" id="eld-remove-html" class="large-text" placeholder=".ads, #banner" />
			</p>
			<p>
				<label>Profiles đã lưu</label>
				<select id="eld-profile-select">
					<option value="">— chọn —</option>
					<?php foreach ($profiles as $pid => $p) : ?>
						<option value="<?php echo esc_attr($pid); ?>"><?php echo esc_html(isset($p['domain']) ? $p['domain'] : $pid); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="button" id="eld-load-profile">Nạp</button>
				<button type="button" class="button" id="eld-delete-profile">Xóa</button>
			</p>
		</section>
	</div>

	<section class="eld-card">
		<h2>3. Selectors chi tiết (jQuery / CSS)</h2>
		<p class="description">Hỗ trợ <code>&&</code> (ghép), <code>,</code> (ưu tiên), <code>:each</code> / <code>:eachn</code>, và <code>img[data-src]</code> cho attr tùy chỉnh.</p>
		<table class="widefat eld-fields">
			<tbody>
				<tr>
					<th>Title</th>
					<td><input type="text" id="sel-title" class="large-text" value="h1" placeholder="h1.title" /></td>
				</tr>
				<tr>
					<th>Slug từ URL</th>
					<td>
						<label><input type="checkbox" id="eld-slug-from-url" checked /> Lấy segment cuối URL làm slug</label>
						<label><input type="checkbox" id="eld-strip-html-ext" checked /> Bỏ .html</label>
					</td>
				</tr>
				<tr>
					<th>Ngày đăng</th>
					<td><input type="text" id="sel-date" class="large-text" placeholder=".date, time" /></td>
				</tr>
				<tr>
					<th>Ảnh đại diện</th>
					<td><input type="text" id="sel-image" class="large-text" placeholder=".thumb img, img.wp-post-image" /></td>
				</tr>
				<tr>
					<th>YouTube / iframe</th>
					<td><input type="text" id="sel-youtube" class="large-text" placeholder="eb-iframe[src], iframe" /></td>
				</tr>
				<tr>
					<th>Giá cũ</th>
					<td><input type="text" id="sel-price" class="large-text" placeholder=".old-price" /></td>
				</tr>
				<tr>
					<th>Giá mới</th>
					<td><input type="text" id="sel-sale-price" class="large-text" placeholder=".price" /></td>
				</tr>
				<tr>
					<th>Mã SP</th>
					<td><input type="text" id="sel-product-sku" class="large-text" placeholder=".sku" /></td>
				</tr>
				<tr>
					<th>Mô tả ngắn</th>
					<td><input type="text" id="sel-excerpt" class="large-text" placeholder=".short-desc" /></td>
				</tr>
				<tr>
					<th>Nội dung</th>
					<td><input type="text" id="sel-content" class="large-text" placeholder=".content, article:each" /></td>
				</tr>
				<tr>
					<th>Điểm nổi bật</th>
					<td><input type="text" id="sel-highlights" class="large-text" placeholder=".highlights li:eachn" /></td>
				</tr>
				<tr>
					<th>Gallery</th>
					<td><input type="text" id="sel-gallery" class="large-text" placeholder=".gallery img" /></td>
				</tr>
				<tr>
					<th>Tên nhóm (auto create)</th>
					<td><input type="text" id="sel-category" class="large-text" placeholder=".breadcrumb a:last" /></td>
				</tr>
				<tr>
					<th>Tên nhóm cấp 2</th>
					<td><input type="text" id="sel-category-2" class="large-text" /></td>
				</tr>
			</tbody>
		</table>
		<p>
			<button type="button" class="button button-primary" id="eld-fetch-detail">Lấy chi tiết URL đầu tiên trong queue</button>
			<button type="button" class="button button-hero" id="eld-import-current">Đăng / Import bài hiện tại</button>
		</p>
	</section>

	<section class="eld-card">
		<h2>4. Preview / dữ liệu đã parse</h2>
		<form id="eld-item-form">
			<table class="form-table">
				<tr>
					<th>Source URL</th>
					<td><input type="url" name="source_url" id="f-source" class="large-text" /></td>
				</tr>
				<tr>
					<th>SKU leech</th>
					<td><input type="text" name="sku" id="f-sku" class="large-text" /></td>
				</tr>
				<tr>
					<th>Title</th>
					<td><input type="text" name="title" id="f-title" class="large-text" /></td>
				</tr>
				<tr>
					<th>Slug</th>
					<td><input type="text" name="slug" id="f-slug" class="large-text" /></td>
				</tr>
				<tr>
					<th>Date</th>
					<td><input type="text" name="post_date" id="f-date" class="regular-text" /></td>
				</tr>
				<tr>
					<th>Image URL</th>
					<td><input type="url" name="image_url" id="f-image" class="large-text" /></td>
				</tr>
				<tr>
					<th>YouTube</th>
					<td><input type="url" name="youtube_url" id="f-youtube" class="large-text" /></td>
				</tr>
				<tr>
					<th>Price / Sale</th>
					<td>
						<input type="text" name="price" id="f-price" class="regular-text" placeholder="Giá cũ" />
						<input type="text" name="sale_price" id="f-sale-price" class="regular-text" placeholder="Giá mới" />
						<input type="text" name="product_sku" id="f-product-sku" class="regular-text" placeholder="Mã SP" />
					</td>
				</tr>
				<tr>
					<th>Excerpt</th>
					<td><textarea name="excerpt" id="f-excerpt" rows="3" class="large-text"></textarea></td>
				</tr>
				<tr>
					<th>Content</th>
					<td><textarea name="content" id="f-content" rows="12" class="large-text"></textarea></td>
				</tr>
				<tr>
					<th>Highlights</th>
					<td><textarea name="highlights" id="f-highlights" rows="4" class="large-text"></textarea></td>
				</tr>
				<tr>
					<th>Gallery</th>
					<td><textarea name="gallery" id="f-gallery" rows="3" class="large-text"></textarea></td>
				</tr>
				<tr>
					<th>New category</th>
					<td>
						<input type="text" name="new_category" id="f-new-category" class="regular-text" />
						<input type="text" name="new_category_2" id="f-new-category-2" class="regular-text" placeholder="Cấp 2" />
					</td>
				</tr>
			</table>
		</form>
		<div id="eld-log" class="eld-log"></div>
	</section>

	<section class="eld-card">
		<h2>5. Danh sách URL đã lưu</h2>
		<textarea id="eld-saved-list" rows="20" class="large-text"><?php echo esc_textarea($saved_list); ?></textarea>
		<p>
			<button type="button" class="button" id="eld-save-list">Lưu danh sách</button>
			<button type="button" class="button" id="eld-use-saved-list">Đưa vào queue</button>
		</p>
	</section>

	<section class="eld-card">
		<h2>6. Plugin Updates</h2>
		<div id="eld-update-section">
			<p>
				<button type="button" id="eld-check-update" class="button">Check for Updates</button>
				<span id="eld-update-status" style="margin-left: 10px;"></span>
			</p>
			<div id="eld-update-info" style="display: none; margin-top: 10px; padding: 10px; border: 1px solid #ddd; background: #f9f9f9;">
				<p><strong>Current Version:</strong> <?php echo esc_html(ELD_VERSION); ?></p>
				<div id="eld-update-details"></div>
			</div>
		</div>
	</section>

	<div id="eld-html-sandbox" class="eld-sandbox" aria-hidden="true"></div>
	<div id="eld-status" class="eld-status"></div>
</div>