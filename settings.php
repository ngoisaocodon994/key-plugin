<?php
$log_file = VNREWRITE_PATH . 'log.txt';
$log = file_exists($log_file) ? file_get_contents($log_file) : '';

if ($cmd == 'clear-log' && $log != '') {
    wp_delete_file($log_file);
    echo '<script type="text/javascript">window.location="' . VNREWRITE_ADMIN_PAGE . '&notice=clear-log-success"</script>';
}

if (isset($_GET['notice']) && $_GET['notice'] == 'clear-log-success') {
    echo '<script>window.history.pushState("", "", "' . VNREWRITE_ADMIN_PAGE . '");</script>';
    echo '<div id="setting-error-settings_updated" class="notice notice-success settings-error is-dismissible"><p><strong>Xóa log thành công!</strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
}

$model_json = get_option('vnrewrite_model');
$models = json_decode($model_json, true);
?>
<div class="poststuff" style="<?php if (isset($_GET['tab'])) echo 'display: none;'; ?>">
    <div class="postbox">
        <div class="postbox-header">
            <h2>Cấu hình</h2>
        </div>
        <div class="inside">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="user_key">User key</label></th>
                        <td>
                            <?php
                            $user_key = isset($this->options['user_key']) ? esc_attr($this->options['user_key']) : '';
                            ?>
                            <input name="vnrewrite_option[user_key]" id="user_key" class="regular-text" value="<?php echo $user_key; ?>" type="text" required>
                            <p class="description">
                                <?php if ($user_key == ''): ?>
                                    <span class="red-text">Chưa nhập User key! </span> Đăng nhập vào tài khoản của bạn tại <a target="_blank" href="https://vnrewrite.com">vnrewrite.com</a> để lấy user key
                                <?php else: ?>
                                    <?php
                                    $end_time = get_option('vnrewrite_end_time', '');
                                    $run_u = get_transient('vnrewrite_user_run');
                                    $free = get_option('vnrewrite_free', 0);
                                    if ($run_u === false) {
                                        $run_u = 0;
                                    }

                                    if ($end_time == -1) {
                                        echo '<span class="green-text">Hạn sử dụng:</span> <span class="blinker">Lifetime</span>';
                                    } elseif ($end_time == 1 || $user_key == '') {
                                        echo '<span class="red-text">Chưa nhập User key! </span>';
                                    } elseif ($end_time == 2) {
                                        echo '<span class="red-text">User key không hợp lệ! </span>';
                                    } elseif ($end_time == 3) {
                                        echo '<span class="red-text">Tên miền không hợp lệ! </span>';
                                    } elseif ($end_time == 4) {
                                        echo '<span class="green-text">Miễn phí mỗi ngày (<strong class="orange-text">' . $run_u . '/' . $free . '</strong>) lần rewrite thành công. <a href="https://vnrewrite.com" target="_blank">Nâng cấp tài khoản</a></span>';
                                        set_transient('vnrewrite_mess', '<span class="orange-text">Số lượt miễn phí của ngày hôm nay đã hết. <a href="https://vnrewrite.com" target="_blank">Nâng cấp tài khoản</a></span>', 24 * HOUR_IN_SECONDS);
                                    } elseif ($end_time == 5) {
                                        echo '<span class="green-text">Miễn phí mỗi ngày (<strong class="orange-text">' . $run_u . '/' . $free . '</strong>) lần rewrite thành công. <a href="https://vnrewrite.com" target="_blank">Nâng cấp tài khoản</a></span>';
                                    } elseif ($end_time > 5) {
                                        $end_date = date('d-m-Y', $end_time);
                                        if ($end_time >= time()) {
                                            if (($end_time - time()) <= 7 * 24 * 60 * 60) {
                                                echo '<span class="orange-text">Sắp hết hạn sử dụng:</span> <span class="blinker">' . $end_date . '</span>';
                                            } else {
                                                echo '<span class="green-text">Hạn sử dụng:</span> <span class="blinker">' . $end_date . '</span>';
                                            }
                                        } else {
                                            $mess = '<span class="red-text">Tài khoản đã hết hạn sử dụng! </span><span class="blinker">' . $end_date . '</span>. Gia hạn tại <a target="_blank" href="https://vnrewrite.com">vnrewrite.com</a>';
                                            echo $mess;
                                            set_transient('vnrewrite_mess', $mess, 24 * HOUR_IN_SECONDS);
                                        }
                                    }
                                    ?>
                                <?php endif ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="type_ai">Loại AI</label></th>
                        <td>
                            <select form="vnrewrite-form" name="vnrewrite_option[type_ai]" id="type_ai">
                                <?php
                                $type_ai = isset($this->options['type_ai']) ? $this->options['type_ai'] : 'gemini';
                                ?>
                                <option value="gemini" <?php selected($type_ai, 'gemini'); ?>>Gemini</option>
                                <option value="openai" <?php selected($type_ai, 'openai'); ?>>OpenAI</option>
                                <option value="claude" <?php selected($type_ai, 'claude'); ?>>Claude</option>
                                <option value="deepseek" <?php selected($type_ai, 'deepseek'); ?>>DeepSeek</option>
                                <option value="grok" <?php selected($type_ai, 'grok'); ?>>Grok</option>
                                <option value="qwen" <?php selected($type_ai, 'qwen'); ?>>Qwen</option>
                                <option value="huggingface" <?php selected($type_ai, 'huggingface'); ?>>Hugging Face</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lang">Ngôn ngữ</label></th>
                        <td>
                            Viết lại sang tiếng
                            <select form="vnrewrite-form" name="vnrewrite_option[lang]" id="lang">
                                <?php
                                $lang = isset($this->options['lang']) ? $this->options['lang'] : 'Việt';
                                $lang_arr = ['Ả Rập', 'Bengali', 'Bulgaria', 'Trung', 'Croatia', 'Séc', 'Đan Mạch', 'Hà Lan', 'Anh', 'Estonia', 'Phần Lan', 'Pháp', 'Đức', 'Hy Lạp', 'Do Thái', 'Hindi', 'Hungary', 'Indonesia', 'Ý', 'Nhật', 'Hàn', 'Latvia', 'Lithuania', 'Na Uy', 'Ba Lan', 'Bồ Đào Nha', 'Romania', 'Nga', 'Serbia', 'Slovak', 'Slovenia', 'Tây Ban Nha', 'Swahili', 'Thuỵ Điển', 'Thái', 'Thổ Nhĩ Kỳ', 'Ukraina', 'Việt'];
                                foreach ($lang_arr as $lang_name) {
                                    echo '<option value="' . $lang_name . '" ' . selected($lang, $lang_name, false) . '>' . $lang_name . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rewrite_type">Tự động rewrite</label></th>
                        <td>
                            <div style="margin-bottom: 10px;">
                                <select form="vnrewrite-form" name="vnrewrite_option[rewrite_type]" id="rewrite_type">
                                    <?php
                                    $rewrite_type = isset($this->options['rewrite_type']) ? $this->options['rewrite_type'] : 'keyword';
                                    ?>
                                    <option value="post" <?php selected($rewrite_type, 'post'); ?>>Bài viết</option>
                                    <option value="clone" <?php selected($rewrite_type, 'clone'); ?>>Clone post</option>
                                    <option value="url" <?php selected($rewrite_type, 'url'); ?>>Url</option>
                                    <option value="keyword" <?php selected($rewrite_type, 'keyword'); ?>>Keyword</option>
                                    <option value="video" <?php selected($rewrite_type, 'video'); ?>>Video ID Youtube</option>
                                </select>
                                <span id="show-cron-time">
                                    <label for="rewrite_action_time">Tự động rewrite sau mỗi</label>
                                    <input name="vnrewrite_option[rewrite_action_time]" id="rewrite_action_time" class="small-text" value="<?php echo isset($this->options['rewrite_action_time']) ? esc_attr($this->options['rewrite_action_time']) : 0; ?>" type="number" min="0"> phút.
                                    <code>(Set = 0 sẽ dừng)</code>
                                    <p style="margin-top: 15px;">Batch size <input name="vnrewrite_option[batch_size]" id="batch_size" class="small-text" value="<?php echo isset($this->options['batch_size']) ? esc_attr($this->options['batch_size']) : 15; ?>" type="number" min="15" max="100"><code>Với tk Gemini free chỉ nên set 15. Với Gemini trả phí nếu web server khỏe có thể set max 50 để tối ưu</code></p>
                                </span>
                            </div>

                            <div id="show-cron-post" style="<?php if ($rewrite_type != 'post') echo 'display:none'; ?>">
                                <hr>
                                <label for="max_posts">Rewrite đồng thời mỗi lần tối đa</label>
                                <input name="vnrewrite_option[max_posts]" id="max_posts" class="small-text" value="<?php echo isset($this->options['max_posts']) ? esc_attr($this->options['max_posts']) : '1'; ?>" type="number" min="1"> bài viết
                                <hr>
                                <p style="margin-top: 15px;">
                                    <label for="cron_status">Chỉ rewrite bài viết có trạng thái</label>
                                    <select form="vnrewrite-form" name="vnrewrite_option[cron_status]" id="cron_status">
                                        <?php
                                        $cron_status = isset($this->options['cron_status']) ? $this->options['cron_status'] : '';
                                        ?>
                                        <option value="" <?php selected($cron_status, ''); ?>>All</option>
                                        <option value="draft" <?php selected($cron_status, 'draft'); ?>>Bản nháp</option>
                                        <option value="publish" <?php selected($cron_status, 'publish'); ?>>Đã xuất bản</option>
                                    </select>
                                </p>
                                <hr>
                                <input name="vnrewrite_option[not_rewrite_title]" id="not_rewrite_title" type="checkbox" value="1" <?php checked(isset($this->options['not_rewrite_title']) ? 1 : '', 1); ?>>
                                <label for="not_rewrite_title">Không viết lại tiêu đề</label>
                                <hr>
                                <strong><code>Không rewrite</code></strong> các bài viết thuộc các danh mục sau:
                                <?php
                                $args_cates = array(
                                    'hide_empty' => false,
                                    'orderby'    => 'name',
                                    'order'      => 'ASC'
                                );

                                if (defined('POLYLANG_VERSION')) {
                                    $default_lang = pll_default_language();
                                    $args_cates['lang'] = $default_lang;
                                }

                                $cats = get_categories($args_cates);

                                if (!empty($cats)) {
                                    $exclude_cat_post_arr = isset($this->options['exclude_cat_post']) ? explode(',', $this->options['exclude_cat_post']) : array();
                                    echo '<ul class="category-list">';
                                    foreach ($cats as $exclude_cat_post) {
                                        $post_count = get_term($exclude_cat_post->cat_ID, 'category')->count;
                                        echo '<li><label for="exclude_cat_post-' . $exclude_cat_post->cat_ID . '"><input type="checkbox" name="vnrewrite_option[exclude_cat_post][]" value="' . $exclude_cat_post->cat_ID . '" id="exclude_cat_post-' . $exclude_cat_post->cat_ID . '" ' . (in_array($exclude_cat_post->cat_ID, $exclude_cat_post_arr) ? 'checked' : '') . '>' . $exclude_cat_post->name . ' (' . $post_count . ')</label></li>';
                                    }
                                    echo '</ul>';
                                }
                                ?>
                            </div>

                            <div id="show-cron-clone" style="<?php if ($rewrite_type != 'clone') echo 'display:none'; ?>">
                                <hr>
                                <?php
                                if (defined('POLYLANG_VERSION')) {
                                    $languages_full = ['ar' => 'Ả Rập', 'bn' => 'Bengali', 'bg' => 'Bulgaria', 'zh' => 'Trung', 'hr' => 'Croatia', 'cs' => 'Séc', 'da' => 'Đan Mạch', 'nl' => 'Hà Lan', 'en' => 'Anh', 'et' => 'Estonia', 'fi' => 'Phần Lan', 'fr' => 'Pháp', 'de' => 'Đức', 'el' => 'Hy Lạp', 'he' => 'Do Thái', 'hi' => 'Hindi', 'hu' => 'Hungary', 'id' => 'Indonesia', 'it' => 'Ý', 'ja' => 'Nhật', 'ko' => 'Hàn', 'lv' => 'Latvia', 'lt' => 'Lithuania', 'no' => 'Na Uy', 'pl' => 'Ba Lan', 'pt' => 'Bồ Đào Nha', 'ro' => 'Romania', 'ru' => 'Nga', 'sr' => 'Serbia', 'sk' => 'Slovak', 'sl' => 'Slovenia', 'es' => 'Tây Ban Nha', 'sw' => 'Swahili', 'sv' => 'Thụy Điển', 'th' => 'Thái', 'tr' => 'Thổ Nhĩ Kỳ', 'uk' => 'Ukraina', 'vi' => 'Việt'];

                                    $languages_active = [];
                                    $languages_list = pll_languages_list(array('fields' => array()));

                                    if (!empty($languages_list)) {
                                        foreach ($languages_list as $obj) {
                                            if (is_object($obj) && $obj->active == 1) {
                                                $languages_active[$obj->slug] = array(
                                                    'flag' => $obj->flag,
                                                    'locale' => $obj->locale
                                                );
                                            }
                                        }
                                    }

                                    $lang_use = array();
                                    if (!empty($languages_active)) {
                                        $default_lang = pll_default_language();
                                        if ($default_lang) {
                                            unset($languages_active[$default_lang]);
                                        }

                                        foreach ($languages_active as $key => $lang) {
                                            if (isset($languages_full[$key])) {
                                                $lang_use[$key] = $lang['flag'] . ' ' . $languages_full[$key] . ' (' . $lang['locale'] . ')';
                                            }
                                        }
                                    }

                                    if (!empty($lang_use)) {
                                        echo '<p style="margin: 10px 0;">Đã phát hiện thấy các ngôn ngữ hợp lệ (<strong>đã active</strong>) tạo bởi Polylang: <code>' . implode(', ', $lang_use) . '</code>. Bạn không cần phải chọn ngôn ngữ, hệ thống sẽ tự động clone bài gốc và rewrite thành các bài mới tương ứng với các ngôn ngữ này. <a href="https://www.youtube.com/watch?v=uiW7S6a954g" target="_blank">Video demo</a></p>';
                                    } else {
                                        echo '<p style="margin: 10px 0;">Không tìm thấy ngôn ngữ hợp lệ tạo bởi Polylang. Vui lòng tạo các ngôn ngữ hợp lệ bằng Polylang</p>';
                                    }
                                } else {
                                    echo '<p style="margin: 10px 0;">Website của bạn chưa cài đặt plugin Polylang. Vui lòng đọc kỹ hướng dẫn bên dưới.</p>';
                                }
                                ?>
                                <p style="margin: 10px 0;">
                                    <label for="max_clones">Rewrite clone đồng thời mỗi lần tối đa</label>
                                    <input name="vnrewrite_option[max_clones]" id="max_clones" class="small-text" value="<?php echo isset($this->options['max_clones']) ? esc_attr($this->options['max_clones']) : '1'; ?>" type="number" min="1">
                                    bài viết
                                </p>
                                <p style="margin: 10px 0;">
                                    <input name="vnrewrite_option[slug_clone]" id="slug_clone" type="checkbox" value="1" <?php checked(isset($this->options['slug_clone']) ? 1 : '', 1); ?>>
                                    <label for="slug_clone">Giữ nguyên slug</label>
                                </p>
                                <hr>
                                <p>- Tính năng này hoạt động kết hợp với plugin <a href="https://vnrewrite.com/wp-content/uploads/polylang-pro.3.6.5.zip" download>Polylang</a> để tạo thêm các bài viết đa ngôn ngữ.
                                    Bạn cần cài đặt, setup polylang hoàn chỉnh với các ngôn ngữ và danh mục tương ứng và set ngôn ngữ gốc làm ngôn ngữ mặc định</p>
                                <p>- Mỗi bài viết gốc trên web sẽ được nhân bản và viết lại sang ngôn ngữ bạn chọn và vẫn giữ nguyên các thông tin như post meta, img, custom field...</p>
                            </div>

                            <div id="show-cron-video" style="<?php if ($rewrite_type != 'video') echo 'display:none;'; ?> margin-top: 15px">
                                <hr>
                                <label for="max_videos">Rewrite đồng thời mỗi lần tối đa</label>
                                <input name="vnrewrite_option[max_videos]" id="max_videos" class="small-text" value="<?php echo isset($this->options['max_videos']) ? esc_attr($this->options['max_videos']) : '1'; ?>" type="number" min="1"> video youtube
                                <hr>
                                <p>Tự động cập nhật <strong>youtube id</strong> mới nhất từ <strong>youtube id list</strong> cho các danh mục sau mỗi
                                    <input name="vnrewrite_option[video_list_action_time]" id="video_list_action_time" class="small-text" value="<?php echo isset($this->options['video_list_action_time']) ? esc_attr($this->options['video_list_action_time']) : '0'; ?>" type="number" min="0"> giờ. <code>(Set = 0 sẽ dừng)</code>
                                </p>
                                <ul class="cate-video-id-list">
                                    <?php
                                    $args_cates = array(
                                        'hide_empty' => false,
                                        'hierarchical' => true
                                    );

                                    if (defined('POLYLANG_VERSION')) {
                                        $default_lang = pll_default_language();
                                        $args_cates['lang'] = $default_lang;
                                    }
                                    $categories = get_categories($args_cates);

                                    $str_cate = '';
                                    foreach ($categories as $category) {
                                        $video_id_list_cate = get_term_meta($category->term_id, 'video_id_list', true);
                                        $str_cate .= '<li>';
                                        $str_cate .= '<label for="video_id_list_cate' . $category->term_id . '">' . esc_html($category->name) . '</label>';
                                        $str_cate .= '<textarea name="vnrewrite_option[video_id_list_cate][' . $category->term_id . ']" id="video_id_list_cate' . $category->term_id . '" class="large-text" rows="3">' . $video_id_list_cate . '</textarea>';
                                        $str_cate .= '</li>';
                                    }
                                    echo $str_cate;
                                    ?>

                                </ul>
                                <p class="clear-fix">- Nếu sử dụng nhiều <strong>youtube id list</strong> thì các youtube id list phân tách nhau bởi <code><strong>|</strong></code>. Ví dụ: <code><strong>youtube_id_list1|youtube_id_list2|youtube_id_list3</strong></code></p>
                                <p>- Khi một kênh youtube có video mới được thêm vào video list của kênh thì nó sẽ tự động lấy video mới và tạo thành bài viết</p>
                            </div>

                            <div id="show-cron-keyword" style="<?php if ($rewrite_type != 'keyword') echo 'display:none'; ?>">
                                <hr>
                                <div style="margin-top: 10px;">
                                    <label for="max_keywords">Rewrite đồng thời mỗi lần tối đa</label>
                                    <input name="vnrewrite_option[max_keywords]" id="max_keywords" class="small-text" value="<?php echo isset($this->options['max_keywords']) ? esc_attr($this->options['max_keywords']) : '1'; ?>" type="number" min="1"> keyword
                                    <hr>
                                    <?php $gg_search_api = isset($this->options['gg_search_api']) ? esc_attr($this->options['gg_search_api']) : ''; ?>
                                    <label for="gg_search_api">Custom Search API</label>
                                    <textarea name="vnrewrite_option[gg_search_api]" id="gg_search_api" class="large-text" rows="5"><?php echo $gg_search_api; ?></textarea>
                                    <p>- Để sử dụng tính năng này cần <a target="_blank" href="https://console.cloud.google.com/?hl=vi">bật Google Custom Search API và tạo api</a>. Với mỗi tài khoản Google có thể tạo 12 dự án, mỗi dự án chỉ tạo 1 api. Mỗi api được search miễn phí 100 lần/ngày. <a target="_blank" href="https://www.youtube.com/watch?v=moxTam1iJsw">Xem video hướng dẫn</a></p>
                                    <p>- Nếu sử dụng nhiều api key thì các api sẽ được sử dụng xoay vòng. Các api key phân tách nhau bởi <code><strong>|</strong></code>. Ví dụ: <code><strong>key1|key2|key3</strong></code></p>
                                </div>
                            </div>
                            <div id="show-cron-url" style="<?php if ($rewrite_type != 'url') echo 'display:none'; ?>">
                                <div style="margin-top: 15px;">
                                    <hr>
                                    <label for="re_rewrite_url">Tự động nhập lại các url thất bại và rewrite. Tối đa</label>
                                    <input name="vnrewrite_option[re_rewrite_url]" id="re_rewrite_url" class="small-text" value="<?php echo isset($this->options['re_rewrite_url']) ? esc_attr($this->options['re_rewrite_url']) : 0; ?>" type="number" min="0"> lần
                                    <hr>
                                    <input name="vnrewrite_option[slug_source]" id="slug_source" type="checkbox" value="1" <?php checked(isset($this->options['slug_source']) ? 1 : '', 1); ?>>
                                    <label for="slug_source">Tạo slug giống bài gốc</label>
                                    <hr>
                                    <input name="vnrewrite_option[not_rewrite_title]" id="not_rewrite_title" type="checkbox" value="1" <?php checked(isset($this->options['not_rewrite_title']) ? 1 : '', 1); ?>>
                                    <label for="not_rewrite_title">Không viết lại tiêu đề</label>
                                    <hr>
                                    <label for="max_urls">Rewrite đồng thời mỗi lần tối đa</label>
                                    <input name="vnrewrite_option[max_urls]" id="max_urls" class="small-text" value="<?php echo isset($this->options['max_urls']) ? esc_attr($this->options['max_urls']) : '1'; ?>" type="number" min="1"> url
                                    <hr>
                                    <label for="pre_crawl_url_num">Tự động crawl</label>
                                    <input name="vnrewrite_option[pre_crawl_url_num]" id="pre_crawl_url_num" class="small-text" value="<?php echo isset($this->options['pre_crawl_url_num']) ? esc_attr($this->options['pre_crawl_url_num']) : 1; ?>" type="number" min="1"> url
                                    sau mỗi
                                    <input name="vnrewrite_option[pre_crawl_url_action_time]" id="pre_crawl_url_action_time" class="small-text" value="<?php echo isset($this->options['pre_crawl_url_action_time']) ? esc_attr($this->options['pre_crawl_url_action_time']) : 0; ?>" type="number" min="0"> phút.
                                    <code>(Set = 0 sẽ dừng)</code>
                                    <p>- Tính năng này chạy độc lập với rewrite nhằm mục đích tối ưu hiệu suất</p>
                                    <p>- Các bài viết crawl về sẽ đươc lưu cache và không hiển thị. Khi thực hiện rewrite sẽ lấy trực tiếp các bài này để xử lý</p>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr style="border: 1px solid #ccc; padding: 10px;<?php if (!isset($_GET['show'])) echo 'display: none;'; ?>">
                        <th scope="row"><label for="extra">Extra</label></th>
                        <td>
                            <p><strong>Crawl common:</strong></p>
                            <p>
                                <label for="url_crawl_element_common">url_crawl_element_common</label>
                                <input name="vnrewrite_option[url_crawl_element_common]" id="url_crawl_element_common" class="regular-text" value="<?php echo isset($this->options['url_crawl_element_common']) ? esc_attr($this->options['url_crawl_element_common']) : ''; ?>" type="text">
                            </p>
                            <p>
                                <label for="url_crawl_exclude_slug_common">url_crawl_exclude_slug_common</label>
                                <input name="vnrewrite_option[url_crawl_exclude_slug_common]" id="url_crawl_exclude_slug_common" class="regular-text" value="<?php echo isset($this->options['url_crawl_exclude_slug_common']) ? esc_attr($this->options['url_crawl_exclude_slug_common']) : ''; ?>" type="text">
                            </p>
                            <p>
                                <label for="crawl_element_detail_common">crawl_element_detail_common</label>
                                <input name="vnrewrite_option[crawl_element_detail_common]" id="crawl_element_detail_common" class="regular-text" value="<?php echo isset($this->options['crawl_element_detail_common']) ? esc_attr($this->options['crawl_element_detail_common']) : ''; ?>" type="text">
                            </p>
                            <p>
                                <label for="remove_element_detail_common">remove_element_detail_common</label>
                                <input name="vnrewrite_option[remove_element_detail_common]" id="remove_element_detail_common" class="regular-text" value="<?php echo isset($this->options['remove_element_detail_common']) ? esc_attr($this->options['remove_element_detail_common']) : ''; ?>" type="text">
                            </p>
                            <p>
                                <label for="text_replace_detail_common">text_replace_detail_common</label>
                                <input name="vnrewrite_option[text_replace_detail_common]" id="text_replace_detail_common" class="regular-text" value="<?php echo isset($this->options['text_replace_detail_common']) ? esc_attr($this->options['text_replace_detail_common']) : ''; ?>" type="text">
                            </p>
                            <hr>
                            <p><strong>Download:</strong></p>
                            <p>
                                <input name="vnrewrite_option[download]" id="download" type="checkbox" value="1" <?php checked(isset($this->options['download']) ? 1 : '', 1); ?>>
                                <label for="download">download</label>
                            </p>
                            <p>
                                <label for="download_time">download_time</label>
                                <input name="vnrewrite_option[download_time]" id="download_time" class="small-text" value="<?php echo isset($this->options['download_time']) ? esc_attr($this->options['download_time']) : '10'; ?>" type="number" min="5">
                            </p>
                            <p>
                                <?php
                                $position_btn1 = isset($this->options['position_btn1']) ? $this->options['position_btn1'] : '';
                                $position_btn2 = isset($this->options['position_btn2']) ? $this->options['position_btn2'] : '';
                                ?>
                                <label for="position_btn1">position_btn1</label>
                                <select form="vnrewrite-form" name="vnrewrite_option[position_btn1]" id="position_btn1">
                                    <option value="" <?php selected($position_btn1, ''); ?>>Chọn Vị trí btn 1 (tắt)</option>
                                    <option value="1" <?php selected($position_btn1, '1'); ?>>Đầu bài viết</option>
                                    <option value="2" <?php selected($position_btn1, '2'); ?>>Trước đoạn văn đầu tiên</option>
                                    <option value="3" <?php selected($position_btn1, '3'); ?>>Sau đoạn văn đầu tiên</option>
                                    <option value="4" <?php selected($position_btn1, '4'); ?>>Trước h2 đầu tiên</option>
                                    <option value="5" <?php selected($position_btn1, '5'); ?>>Sau table</option>
                                    <option value="6" <?php selected($position_btn1, '6'); ?>>Trước h2 cuối</option>
                                    <option value="7" <?php selected($position_btn1, '7'); ?>>Sau h2 cuối</option>
                                    <option value="8" <?php selected($position_btn1, '8'); ?>>Cuối bài viết</option>
                                </select>
                                <label for="position_btn2">position_btn2</label>
                                <select form="vnrewrite-form" name="vnrewrite_option[position_btn2]" id="position_btn2">
                                    <option value="" <?php selected($position_btn2, ''); ?>>Chọn Vị trí btn 2 (tắt)</option>
                                    <option value="1" <?php selected($position_btn2, '1'); ?>>Đầu bài viết</option>
                                    <option value="2" <?php selected($position_btn2, '2'); ?>>Trước đoạn văn đầu tiên</option>
                                    <option value="3" <?php selected($position_btn2, '3'); ?>>Sau đoạn văn đầu tiên</option>
                                    <option value="4" <?php selected($position_btn2, '4'); ?>>Trước h2 đầu tiên</option>
                                    <option value="5" <?php selected($position_btn2, '5'); ?>>Sau table</option>
                                    <option value="6" <?php selected($position_btn2, '6'); ?>>Trước h2 cuối</option>
                                    <option value="7" <?php selected($position_btn2, '7'); ?>>Sau h2 cuối</option>
                                    <option value="8" <?php selected($position_btn2, '8'); ?>>Cuối bài viết</option>
                                </select>
                            </p>
                            <p>
                                <?php
                                $format_slug = isset($this->options['format_slug']) ? $this->options['format_slug'] : 'name';
                                ?>
                                <label for="format_slug">format_slug</label>
                                <select form="vnrewrite-form" name="vnrewrite_option[format_slug]" id="format_slug">
                                    <option value="name" <?php selected($format_slug, 'name'); ?>>name</option>
                                    <option value="name_mod" <?php selected($format_slug, 'name_mod'); ?>>name-mod</option>
                                    <option value="name_mod_apk" <?php selected($format_slug, 'name_mod_apk'); ?>>name-mod-apk</option>
                                </select>
                            </p>
                            <p>
                                <label for="explode_title">explode_title</label>
                                <input name="vnrewrite_option[explode_title]" id="explode_title" class="regular-text" value="<?php echo isset($this->options['explode_title']) ? esc_attr($this->options['explode_title']) : ''; ?>" type="text">
                            </p>
                            <p>
                                <label for="get_download_link">get_download_link</label>
                                <input name="vnrewrite_option[get_download_link]" id="get_download_link" placeholder="element|attribute" class="regular-text" value="<?php echo isset($this->options['get_download_link']) ? esc_attr($this->options['get_download_link']) : ''; ?>" type="text">
                            </p>
                            <p>
                                <label for="download_link_ext">download_link_ext</label>
                                <input name="vnrewrite_option[download_link_ext]" id="download_link_ext" class="regular-text" value="<?php echo isset($this->options['download_link_ext']) ? esc_attr($this->options['download_link_ext']) : ''; ?>" type="text">
                            </p>
                            <p>
                                <label for="download_element">download_element</label>
                                <input name="vnrewrite_option[download_element]" id="download_element" placeholder="element|attribute" class="regular-text" value="<?php echo isset($this->options['download_element']) ? esc_attr($this->options['download_element']) : ''; ?>" type="text">
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gg_api_yt">Google API Youtube</label></th>
                        <td>
                            <?php $gg_api_yt = isset($this->options['gg_api_yt']) ? esc_attr($this->options['gg_api_yt']) : ''; ?>
                            <input name="vnrewrite_option[gg_api_yt]" id="gg_api_yt" class="regular-text" value="<?php echo $gg_api_yt; ?>" type="text">
                            <p>- <a target="_blank" href="https://console.cloud.google.com/?hl=vi">Google API Youtube</a> dùng để lấy danh sách video từ list video id youtube. Nếu không thực hiện lấy video từ list video thì không cần nhập. <a target="_blank" href="https://www.youtube.com/watch?v=VRqQmbT32Vg">Xem video hướng dẫn</a></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="publish_action_time">Hẹn giờ publish</label></th>
                        <td>
                            Auto publish sau mỗi
                            <input name="vnrewrite_option[publish_action_time]" id="publish_action_time" class="small-text" value="<?php echo isset($this->options['publish_action_time']) ? esc_attr($this->options['publish_action_time']) : '0'; ?>" type="number" min="0"> phút. <code>(Set = 0 sẽ dừng)</code>
                            <p>- Chỉ tự động xuất bản những bài viết có trạng thái <code><strong>draft</strong></code> và <code><strong>đã rewrite</strong></code></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="draft">User</label></th>
                        <td>
                            User đăng bài
                            <select form="vnrewrite-form" name="vnrewrite_option[user]" id="user">
                                <?php
                                $cur_user = isset($this->options['user']) ? $this->options['user'] : get_current_user_id();
                                $users = get_users(array('fields' => array('ID', 'user_login')));
                                foreach ($users as $user) {
                                    echo '<option value="' . $user->ID . '" ' . selected($cur_user, $user->ID, false) . '>' . $user->user_login . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="draft">Bản nháp</label></th>
                        <td>
                            <input name="vnrewrite_option[draft]" id="draft" type="checkbox" value="1" <?php checked(isset($this->options['draft']) ? 1 : '', 1); ?>>
                            <label for="draft">Bài viết sau khi rewrite sẽ có trạng thái là bản nháp</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="draft">Loại bỏ bài viết</label></th>
                        <td>
                            <label for="min_word">Loại bỏ bài viết nếu độ dài nhỏ hơn</label>
                            <input name="vnrewrite_option[min_word]" id="min_word" class="small-text" value="<?php echo isset($this->options['min_word']) ? esc_attr($this->options['min_word']) : '0'; ?>" type="number" min="0"> từ. <code>(Set = 0 sẽ không loại bỏ)</code>
                            <hr>
                            <input name="vnrewrite_option[not_img]" id="not_img" type="checkbox" value="1" <?php checked(isset($this->options['not_img']) ? 1 : '', 1); ?>>
                            <label for="not_img">Loại bỏ bài viết nếu không có ảnh (Không áp dụng cho rewrite "bài viết")</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="format_img">Hình ảnh</label></th>
                        <td>
                            <?php
                            $format_img = isset($this->options['format_img']) ? $this->options['format_img'] : 'jpg';
                            ?>
                            <label for="format_img">Tạo ảnh</label>
                            <select form="vnrewrite-form" name="vnrewrite_option[format_img]" id="format_img">
                                <option value="" <?php selected($format_img, ''); ?>>Không tạo ảnh</option>
                                <option value="jpg" <?php selected($format_img, 'jpg'); ?>>jpg</option>
                                <option value="png" <?php selected($format_img, 'png'); ?>>png</option>
                                <option value="webp" <?php selected($format_img, 'webp'); ?>>webp</option>
                            </select>
                            <hr>
                            <?php
                            $resize_img = isset($this->options['resize_img']) ? $this->options['resize_img'] : 0;
                            ?>
                            <label for="resize_img">Resize ảnh theo chiều ngang</label>
                            <input name="vnrewrite_option[resize_img]" id="resize_img" class="small-text" value="<?php echo $resize_img; ?>" type="number" min="0">
                            <code>(Set = 0 sẽ không resize)</code>
                            <p>- Nếu bài viết gốc có ảnh thì sẽ được tải về web và thay đổi theo các thông số đã set</p>
                            <hr>
                            <input name="vnrewrite_option[slug_source_img]" id="slug_source_img" type="checkbox" value="1" <?php checked(isset($this->options['slug_source_img']) ? 1 : '', 1); ?>>
                            <label for="slug_source_img">Tạo filename (slug) ảnh giống ảnh gốc</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="link_cur">Liên kết nội bộ</label></th>
                        <td>
                            <p style="margin-bottom:15px">
                                <input name="vnrewrite_option[link_cur]" id="link_cur" type="checkbox" value="1" <?php checked(isset($this->options['link_cur']) ? 1 : '', 1); ?>>
                                <label for="link_cur">Link về chính bài viết với textlink là keyword (Dành riêng cho rewrite keyword)</label>
                            </p>

                            <input name="vnrewrite_option[link_brand]" id="link_brand" type="checkbox" value="1" <?php checked(isset($this->options['link_brand']) ? 1 : '', 1); ?>>
                            <label for="link_brand">Link về home với textlink là brand</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="log">Options</label></th>
                        <td>
                            <p style="margin-bottom: 8px;">
                                <input name="vnrewrite_option[log]" id="log" type="checkbox" value="1" <?php checked(isset($this->options['log']) ? 1 : '', 1); ?>>
                                <label for="log">Bật log để ghi lại lỗi (nếu có)</label>
                            </p>
                            <textarea id="vnrewrite-log" class="large-text" rows="15"><?php echo $log; ?></textarea>
                            <?php if ($log != ''): ?>
                                <p>
                                    <a id="clear-log" class="button red-text" href="<?php echo VNREWRITE_ADMIN_PAGE . '&cmd=clear-log'; ?>">Xóa log</button>
                                        <a style="margin-left:5px" id="download-log" class="button" href="<?php echo VNREWRITE_URL . 'log.txt'; ?>" download>Tải log</a>
                                </p>
                            <?php endif ?>
                            <hr>
                            <a class="button" href="<?php echo add_query_arg(array('cmd' => 'vnrewrite-update-model'), VNREWRITE_ADMIN_PAGE); ?>">Update model</a>
                            <p>Click để cập nhật các model AI, model tạo ảnh AI mới nhất và loại bỏ các model lỗi thời không thể sử dụng</p>
                            <hr>
                            <?php
                            $reset_url = wp_nonce_url(
                                add_query_arg([
                                    'reset_vnrewrite_cron' => 1,
                                    'cron_hook' => 'vnrewrite_rewrite_cron'
                                ]),
                                'vnrewrite_reset_cron'
                            );
                            echo '<a href="' . esc_url($reset_url) . '" class="button red-text">Reset Cron</a>';
                            ?>
                            <p>Nếu bị treo quá 15 phút thì hãy click button này để reset cron. Nếu plugin đang hoạt động bình thường không nên click</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="postbox">
        <div class="postbox-header">
            <h2>AI</h2>
        </div>
        <div class="inside">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="gemini_api_key">Gemini</label></th>
                        <td>
                            <p style="margin-bottom: 8px">API key</p>
                            <?php $gemini_api_key = isset($this->options['gemini_api_key']) ? esc_attr($this->options['gemini_api_key']) : ''; ?>
                            <textarea name="vnrewrite_option[gemini_api_key]" id="gemini_api_key" class="large-text" rows="5"><?php echo $gemini_api_key; ?></textarea>
                            <?php if ($gemini_api_key == ''): ?>
                                <p class="red-text description">Chưa có Gemini API key!</p>
                            <?php endif ?>
                            <p>- Nếu sử dụng nhiều <a target="_blank" href="https://console.cloud.google.com">api key</a> thì các api sẽ được sử dụng xoay vòng. Các api key phân tách nhau bởi <code><strong>|</strong></code>. Ví dụ: <code><strong>key1|key2|key3</strong></code>. <a target="_blank" href="https://www.youtube.com/watch?v=sB5MPyb33Js">Xem video hướng dẫn</a></p>
                            <hr>
                            <?php
                            $gemini_model = isset($this->options['gemini_model']) ? $this->options['gemini_model'] : 'gemini-1.5-pro-latest';
                            $gemini_model_arr = !empty($models['ai']['gemini_model']) ? $models['ai']['gemini_model'] : ['gemini-1.5-pro-latest' => 'gemini-1.5-pro-latest'];
                            ?>
                            Model
                            <select form="vnrewrite-form" name="vnrewrite_option[gemini_model]" id="gemini_model">
                                <?php
                                foreach ($gemini_model_arr as $value => $text) {
                                    echo '<option value="' . $value . '" ' . selected($gemini_model, $value, false) . '>' . $text . '</option>';
                                }
                                ?>
                            </select>
                            <hr>
                            <?php $gemini_proxy = isset($this->options['gemini_proxy']) ? esc_attr($this->options['gemini_proxy']) : ''; ?>
                            <label for="gemini_proxy">Proxy</label>
                            <input name="vnrewrite_option[gemini_proxy]" class="large-text" value="<?php echo $gemini_proxy; ?>" type="text">
                            <p>- Nếu ip (host, vps) web bạn thuộc local mà gemini không support thì có thể sử dụng proxy. Định dạng: <code>ip:port:user:pass</code></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="openai_api_key" class="form-label">OpenAI</label></th>
                        <td>
                            <label for="openai_api_key">API key</label>
                            <?php $openai_api_key = isset($this->options['openai_api_key']) ? esc_attr($this->options['openai_api_key']) : ''; ?>
                            <textarea name="vnrewrite_option[openai_api_key]" id="openai_api_key" class="large-text" rows="5"><?php echo $openai_api_key; ?></textarea>
                            <?php if ($openai_api_key == ''): ?>
                                <p class="red-text">Chưa có Openai API key!</p>
                            <?php endif ?>
                            <p>- Nếu sử dụng nhiều <a target="_blank" href="https://platform.openai.com/api-keys">api key</a> thì các api sẽ được sử dụng xoay vòng. Các api key phân tách nhau bởi <code><strong>|</strong></code>. Ví dụ: <code><strong>key1|key2|key3</strong></code></p>
                            <hr>
                            <label for="openai_endpoint">Endpoint (<code>https://api.openai.com/v1/chat/completions</code>)</label>
                            <?php $openai_endpoint = isset($this->options['openai_endpoint']) ? esc_attr($this->options['openai_endpoint']) : 'https://api.openai.com/v1/chat/completions'; ?>
                            <input name="vnrewrite_option[openai_endpoint]" id="openai_endpoint" class="large-text" value="<?php echo $openai_endpoint; ?>" type="text" placeholder="https://api.openai.com/v1/chat/completions">
                            <p>- Nếu sử dụng API của các bên trung gian thì cần thay thế <code>Endpoint</code> do bên trung gian cung cấp. Lưu ý: Chỉ sử dụng api từ bên trung gian tuân thủ chính xác dữ liệu returns của OpenAI để tránh lỗi hoặc khi gặp lỗi mà không có thông báo rõ ràng, dẫn đến không biết được nguyên nhân gây lỗi</p>
                            <hr>
                            <?php
                            $openai_model = isset($this->options['openai_model']) ? $this->options['openai_model'] : 'chatgpt-4o-latest';
                            $openai_model_arr = !empty($models['ai']['openai_model']) ? $models['ai']['openai_model'] : ['chatgpt-4o-latest' => 'chatgpt-4o-latest (16k)'];
                            ?>
                            <label for="openai_model">Model</label>
                            <select form="vnrewrite-form" name="vnrewrite_option[openai_model]" id="openai_model">
                                <?php
                                foreach ($openai_model_arr as $value => $text) {
                                    echo '<option value="' . $value . '" ' . selected($openai_model, $value, false) . '>' . $text . '</option>';
                                }
                                ?>
                            </select>
                            <p>- Nếu sử dụng API của các bên trung gian thì cần xem API đó có thể dùng được những model nào để lựa chọn cho phù hợp</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="claude_api_key" class="form-label">Claude</label></th>
                        <td>
                            <label for="claude_api_key">API key</label>
                            <?php $claude_api_key = isset($this->options['claude_api_key']) ? esc_attr($this->options['claude_api_key']) : ''; ?>
                            <textarea name="vnrewrite_option[claude_api_key]" id="claude_api_key" class="large-text" rows="5"><?php echo $claude_api_key; ?></textarea>
                            <?php if ($claude_api_key == ''): ?>
                                <p class="red-text">Chưa có Claude API key!</p>
                            <?php endif ?>
                            <p>- Nếu sử dụng nhiều <a target="_blank" href="https://console.anthropic.com/settings/keys">api key</a> thì các api sẽ được sử dụng xoay vòng. Các api key phân tách nhau bởi <code><strong>|</strong></code>. Ví dụ: <code><strong>key1|key2|key3</strong></code></p>
                            <hr>
                            <label for="claude_endpoint">Endpoint (<code>https://api.anthropic.com/v1/messages</code>)</label>
                            <?php $claude_endpoint = isset($this->options['claude_endpoint']) ? esc_attr($this->options['claude_endpoint']) : 'https://api.anthropic.com/v1/messages'; ?>
                            <input name="vnrewrite_option[claude_endpoint]" id="claude_endpoint" class="large-text" value="<?php echo $claude_endpoint; ?>" type="text" placeholder="https://api.anthropic.com/v1/messages">
                            <p>- Nếu sử dụng API của các bên trung gian thì cần thay thế <code>Endpoint</code> do bên trung gian cung cấp. Lưu ý: Chỉ sử dụng api từ bên trung gian tuân thủ chính xác dữ liệu returns của Claude để tránh lỗi hoặc khi gặp lỗi mà không có thông báo rõ ràng, dẫn đến không biết được nguyên nhân gây lỗi</p>
                            <hr>
                            <?php
                            $claude_model = isset($this->options['claude_model']) ? $this->options['claude_model'] : 'claude-3-5-sonnet-20240620';
                            $claude_model_arr = !empty($models['ai']['claude_model']) ? $models['ai']['claude_model'] : ['claude-3-5-sonnet-20240620' => 'claude-3-5-sonnet-20240620 (4k)'];
                            ?>
                            <label for="claude_model">Model</label>
                            <select form="vnrewrite-form" name="vnrewrite_option[claude_model]" id="claude_model">
                                <?php
                                foreach ($claude_model_arr as $value => $text) {
                                    echo '<option value="' . $value . '" ' . selected($claude_model, $value, false) . '>' . $text . '</option>';
                                }
                                ?>
                            </select>
                            <p>- Nếu sử dụng API của các bên trung gian thì cần xem API đó có thể dùng được những model nào để lựa chọn cho phù hợp</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="deepseek_api_key" class="form-label">DeepSeek</label></th>
                        <td>
                            <label for="deepseek_api_key">API key</label>
                            <?php $deepseek_api_key = isset($this->options['deepseek_api_key']) ? esc_attr($this->options['deepseek_api_key']) : ''; ?>
                            <input name="vnrewrite_option[deepseek_api_key]" id="deepseek_api_key" class="large-text" value="<?php echo $deepseek_api_key; ?>" type="text">
                            <?php if ($deepseek_api_key == ''): ?>
                                <p class="red-text">Chưa có DeepSeek API key!</p>
                            <?php endif ?>
                            <hr>
                            <label for="deepseek_endpoint">Endpoint (<code>https://api.deepseek.com/chat/completions</code>)</label>
                            <?php $deepseek_endpoint = isset($this->options['deepseek_endpoint']) ? esc_attr($this->options['deepseek_endpoint']) : 'https://api.deepseek.com/chat/completions'; ?>
                            <input name="vnrewrite_option[deepseek_endpoint]" id="deepseek_endpoint" class="large-text" value="<?php echo $deepseek_endpoint; ?>" type="text" placeholder="https://api.deepseek.com/chat/completions">
                            <p>- Nếu sử dụng API của các bên trung gian thì cần thay thế <code>Endpoint</code> do bên trung gian cung cấp. Lưu ý: Chỉ sử dụng api từ bên trung gian tuân thủ chính xác dữ liệu returns của DeepSeek để tránh lỗi hoặc khi gặp lỗi mà không có thông báo rõ ràng, dẫn đến không biết được nguyên nhân gây lỗi</p>
                            <hr>
                            <?php
                            $deepseek_model = isset($this->options['deepseek_model']) ? $this->options['deepseek_model'] : 'deepseek-chat';
                            $deepseek_model_arr = !empty($models['ai']['deepseek_model']) ? $models['ai']['deepseek_model'] : ['deepseek-chat' => 'deepseek-chat'];
                            ?>
                            <label for="deepseek_model">Model</label>
                            <select form="vnrewrite-form" name="vnrewrite_option[deepseek_model]" id="deepseek_model">
                                <?php
                                foreach ($deepseek_model_arr as $value => $text) {
                                    echo '<option value="' . $value . '" ' . selected($deepseek_model, $value, false) . '>' . $text . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="grok_api_key" class="form-label">Grok</label></th>
                        <td>
                            <label for="grok_api_key">API key</label>
                            <?php $grok_api_key = isset($this->options['grok_api_key']) ? esc_attr($this->options['grok_api_key']) : ''; ?>
                            <input name="vnrewrite_option[grok_api_key]" id="grok_api_key" class="large-text" value="<?php echo $grok_api_key; ?>" type="text">
                            <?php if ($grok_api_key == ''): ?>
                                <p class="red-text">Chưa có Grok API key!</p>
                            <?php endif ?>
                            <hr>
                            <label for="grok_endpoint">Endpoint (<code>https://api.x.ai/v1/chat/completions</code>)</label>
                            <?php $grok_endpoint = isset($this->options['grok_endpoint']) ? esc_attr($this->options['grok_endpoint']) : 'https://api.x.ai/v1/chat/completions'; ?>
                            <input name="vnrewrite_option[grok_endpoint]" id="grok_endpoint" class="large-text" value="<?php echo $grok_endpoint; ?>" type="text" placeholder="https://api.x.ai/v1/chat/completions">
                            <p>- Nếu sử dụng API của các bên trung gian thì cần thay thế <code>Endpoint</code> do bên trung gian cung cấp. Lưu ý: Chỉ sử dụng api từ bên trung gian tuân thủ chính xác dữ liệu returns của grok để tránh lỗi hoặc khi gặp lỗi mà không có thông báo rõ ràng, dẫn đến không biết được nguyên nhân gây lỗi</p>
                            <hr>
                            <?php
                            $grok_model = isset($this->options['grok_model']) ? $this->options['grok_model'] : 'grok-2-latest';
                            $grok_model_arr = !empty($models['ai']['grok_model']) ? $models['ai']['grok_model'] : ['grok-2-latest' => 'grok-2-latest'];
                            ?>
                            <label for="grok_model">Model</label>
                            <select form="vnrewrite-form" name="vnrewrite_option[grok_model]" id="grok_model">
                                <?php
                                foreach ($grok_model_arr as $value => $text) {
                                    echo '<option value="' . $value . '" ' . selected($grok_model, $value, false) . '>' . $text . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="qwen_api_key" class="form-label">Qwen</label></th>
                        <td>
                            <label for="qwen_api_key">API key</label>
                            <?php $qwen_api_key = isset($this->options['qwen_api_key']) ? esc_attr($this->options['qwen_api_key']) : ''; ?>
                            <input name="vnrewrite_option[qwen_api_key]" id="qwen_api_key" class="large-text" value="<?php echo $qwen_api_key; ?>" type="text">
                            <?php if ($qwen_api_key == ''): ?>
                                <p class="red-text">Chưa có Qwen API key!</p>
                            <?php endif ?>
                            <p>- Lấy API key <a href="https://bailian.console.alibabacloud.com/?apiKey=1#/api-key" target="_blank">tại đây</a></p>
                            <hr>
                            <?php
                            $qwen_model = isset($this->options['qwen_model']) ? $this->options['qwen_model'] : 'qwen-plus';
                            $qwen_model_arr = !empty($models['ai']['qwen_model']) ? $models['ai']['qwen_model'] : ['qwen-plus' => 'qwen-plus'];
                            ?>
                            <label for="qwen_model">Model</label>
                            <select form="vnrewrite-form" name="vnrewrite_option[qwen_model]" id="qwen_model">
                                <?php
                                foreach ($qwen_model_arr as $value => $text) {
                                    echo '<option value="' . $value . '" ' . selected($qwen_model, $value, false) . '>' . $text . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="huggingface_api_key" class="form-label">HuggingFace</label></th>
                        <td>
                            <label for="huggingface_api_key">HuggingFace token</label>
                            <?php $huggingface_api_key = isset($this->options['huggingface_api_key']) ? esc_attr($this->options['huggingface_api_key']) : ''; ?>
                            <textarea name="vnrewrite_option[huggingface_api_key]" id="huggingface_api_key" class="large-text" rows="5"><?php echo $huggingface_api_key; ?></textarea>
                            <?php if ($huggingface_api_key == ''): ?>
                                <p class="red-text">Chưa có HuggingFace token!</p>
                            <?php endif ?>
                            <p>- Nếu sử dụng nhiều <a target="_blank" href="https://huggingface.co/settings/tokens">token</a> thì các token sẽ được sử dụng xoay vòng. Các token phân tách nhau bởi <code><strong>|</strong></code>. Ví dụ: <code><strong>token1|token2|token3</strong></code></p>
                            <p>- Xem video hướng dẫn lấy <a target="_blank" href="https://www.youtube.com/watch?v=QOk_x4oSVJQ">HuggingFace Token</a></p>
                            <hr>
                            <?php
                            $huggingface_model = isset($this->options['huggingface_model']) ? $this->options['huggingface_model'] : 'deepseek-ai/DeepSeek-V3|https://huggingface.co/api/inference-proxy/together/v1/chat/completions';
                            $huggingface_model_arr = !empty($models['ai']['huggingface_model']) ? $models['ai']['huggingface_model'] : ['deepseek-ai/DeepSeek-V3|https://huggingface.co/api/inference-proxy/together/v1/chat/completions' => 'DeepSeek-V3'];
                            ?>
                            <label for="huggingface_model">Model</label>
                            <select form="vnrewrite-form" name="vnrewrite_option[huggingface_model]" id="huggingface_model">
                                <?php
                                foreach ($huggingface_model_arr as $value => $text) {
                                    echo '<option value="' . $value . '" ' . selected($huggingface_model, $value, false) . '>' . $text . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>