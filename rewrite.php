<?php
class VnRewrite
{
    private $options;
    private $crawl;

    public function __construct()
    {
        $this->options = get_option('vnrewrite_option');
        require_once VNREWRITE_PATH . 'admin/crawl.php';
        $this->crawl = new VnRewriteCrawl();
    }

    public function rewrite($post_ids = [], $url = false, $keyword = false, $video = false, $pll = [])
    {
        set_time_limit(0);
        $in = array(
            'token' => $this->options['user_key'],
            'lang' => $this->options['lang'],
            'keyword' => ($keyword !== false) ? 'ok' : '',
            'rewrite_type' => $this->options['rewrite_type']
        );

        $out = $this->vnrewrite_user($in);

        if (!empty($out)) {
            if (isset($out['end_time'])) {
                $current_time = time();
                update_option('vnrewrite_end_time', $out['end_time']);
                set_transient('vnrewrite_mess_time', $current_time, 30 * MINUTE_IN_SECONDS);

                if (isset($out['run_last'])) {
                    $run_last = date('Y-m-d', strtotime($out['run_last']));
                    $current_date = date('Y-m-d', $current_time);

                    if (isset($out['user_run'])) {
                        set_transient('vnrewrite_user_run', $out['user_run'], 24 * HOUR_IN_SECONDS);
                    }

                    if ($out['end_time'] == 4 && $current_date === $run_last) {
                        return;
                    }

                    if ($out['end_time'] == 5 && $current_date !== $run_last) {
                        set_transient('vnrewrite_user_run', 0, 24 * HOUR_IN_SECONDS);
                    }
                }
            }

            if (isset($out['warning'])) {
                update_option('vnrewrite_warning', $out['warning']);
            }

            if (isset($out['free'])) {
                update_option('vnrewrite_free', $out['free']);
            }

            if (isset($out['p']) && $out['p'] != '') {
                if (!empty($post_ids)) {
                    $mess = '<span class="orange-text">Đang lấy danh sách bài viết...</span>';
                    $this->log_mess($mess, false, true, 0, true);
                    $this->rewrite_post($this->options['type_ai'], $out['p'], $out['pl'], $out['p2'], $out['pi'], $post_ids);
                } elseif ($url !== false) {
                    $this->rewrite_url($this->options['type_ai'], $out['p'], $out['p2'], $out['pi']);
                } elseif ($keyword !== false) {
                    $mess = '<span class="orange-text">Đang đọc danh sách keyword...</span>';
                    $this->log_mess($mess, false, true, 0, true);
                    $this->rewrite_keyword($this->options['type_ai'], $out['p'], $out['pl'], $out['p2'], $out['pi']);
                } elseif ($video !== false) {
                    $mess = '<span class="orange-text">Đang đọc danh sách video id youtube...</span>';
                    $this->log_mess($mess, false, true, 0, true);
                    $this->rewrite_video($this->options['type_ai'], $out['p'], $out['p2']);
                } elseif (!empty($pll) && !empty($out['pc'])) {
                    /* foreach ($pll as $lang_code => $lang) {
                        $mess = '<span class="orange-text">Đang rewrite clone ' . count($lang['post_ids']) . ' bài viết sang tiếng ' . $lang['name'] . '...</span>';
                        $this->log_mess($mess, false, true, 0, true);
                        $this->rewrite_clone($this->options['type_ai'], $lang_code, $lang, $out['pc']);
                    } */

                    $random_key = array_rand($pll);
                    $lang_code = $random_key;
                    $lang = $pll[$random_key];

                    $mess = '<span class="orange-text">Đang rewrite clone ' . count($lang['post_ids']) . ' bài viết sang tiếng ' . $lang['name'] . '...</span>';
                    $this->log_mess($mess, false, true, 0, true);
                    $this->rewrite_clone($this->options['type_ai'], $lang_code, $lang, $out['pc']);
                }
            }
        }
    }

    private function rewrite_post($type_ai, $p, $pl, $p2, $pi, $post_ids)
    {
        $api_str = $this->options[$type_ai . '_api_key'];
        if (empty($api_str)) {
            $this->log_mess("$type_ai không có api key");
            return false;
        }

        $ai_requests = array();
        $lock_timeout = 60;

        foreach ($post_ids as $post_id) {
            $title = get_post_field('post_title', $post_id);
            $content = get_post_field('post_content', $post_id);
            $keyword = get_post_meta($post_id, 'keyword');
            $rankmath_keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
            $keyword = !empty($keyword) ? $keyword : (!empty($rankmath_keyword) ? explode(',', $rankmath_keyword)[0] : '');
            $user_id = isset($this->options['user']) ? intval($this->options['user']) : get_current_user_id();
            $cate_id = get_the_category($post_id)[0]->cat_ID;
            $permalink = get_permalink($post_id);

            if (empty($title) || empty($content)) {
                $this->log_mess("Bài viết ID: $post_id tiêu đề hoạc nội dung trống");
                continue;
            }

            try {
                $content_m = $this->html2m($content);
            } catch (Exception $e) {
                $this->log_mess('Lỗi khi xử lý nội dung bài viết gốc ' . $permalink . ': ' . $e->getMessage());
                continue;
            }

            if (empty($content_m)) {
                $this->log_mess('Nội dung bài viết ' . $permalink . ' rỗng!');
                continue;
            }

            $ai_as = $this->get_ai_as_prompt_cate($cate_id, 'vnrewrite_ai_as', 'vnrewrite_ai_as_common');

            $prompt_cate = $this->get_ai_as_prompt_cate($cate_id, 'vnrewrite_prompt_cate', 'vnrewrite_prompt_common');
            if (!empty($keyword)) {
                $list_post_keyword = $this->list_post_keyword($keyword, $cate_id);

                if (!empty($list_post_keyword)) {
                    $pl = str_replace('[links]', $list_post_keyword, $pl);
                    if (preg_match('/-*\s*\[internal_links\]/', $prompt_cate)) {
                        $user_prompt = preg_replace('/-*\s*\[internal_links\]/', $pl, $prompt_cate);
                    } else {
                        $user_prompt = $prompt_cate;
                    }
                } else {
                    $user_prompt = $prompt_cate;
                }
            } else {
                $user_prompt = $prompt_cate;
            }

            if (empty($this->options['format_img'])) {
                $pi = '';
            }
            $prompt = $ai_as . $p . $user_prompt . $pi . $p2 . $content_m;
            if (str_starts_with($ai_as, 'cuttom:') && str_starts_with($user_prompt, 'cuttom:')) {
                $prompt = array(
                    'ai_as' => substr($ai_as, 7),
                    'prompt' => substr($user_prompt, 7) . "\n" . $content_m
                );
            }

            $lock_key = 'clone_post_lock_' . $post_id;
            set_transient($lock_key, true, $lock_timeout);
            $ai_requests[] = array(
                'post_id' => $post_id,
                'prompt' => $prompt,
                'lock_key' => $lock_key,
                'type_ai' => $type_ai
            );
        }

        if (empty($ai_requests)) {
            $this->log_mess('<span class="red-text">Lượt chạy này không có bài viết nào hợp lệ!</span>', false, true, 0);
            return false;
        }

        if ($type_ai == 'gemini' && !empty($this->options['gemini_proxy'])) {
            $proxy = $this->balance_items($this->options['gemini_proxy'], $ai_requests);
            foreach ($ai_requests as $key => $ai_request) {
                $ai_requests[$key]['proxy'] = $proxy[$key];
            }
        }

        $api = $this->balance_items($api_str, $ai_requests, $type_ai . '_api_last');
        foreach ($ai_requests as $key => $ai_request) {
            $ai_requests[$key]['api'] = $api[$key];
        }

        $responses = $this->process_concurrent_ai_requests($ai_requests);
        if (empty($responses)) {
            $this->log_mess('Responses rỗng');
            return false;
        }
        $posts_data = [];
        $user_id = isset($this->options['user']) ? intval($this->options['user']) : get_current_user_id();

        foreach ($responses as $i => $response) {
            $post_id = $ai_requests[$i]['post_id'];
            $lock_key = $ai_requests[$i]['lock_key'];

            if (empty($response)) {
                $this->log_mess('Response rỗng');
                delete_transient($lock_key);
                continue;
            }

            if (isset($response['error']) && isset($response['api'])) {
                $this->log_mess($response['error'] . ' - API: ' . $response['api']);
                delete_transient($lock_key);
                continue;
            }

            try {
                $content_m_arr = $this->check_content_m($response);
            } catch (Exception $e) {
                $this->log_mess('Lỗi khi xử lý nội dung bài viết (m): ' . $e->getMessage());
                delete_transient($lock_key);
                continue;
            }

            if (empty($content_m_arr['title']) || empty($content_m_arr['content'])) {
                update_post_meta($post_id, 'rewrite', 'error');
                $this->log_mess('Nội dung không hợp lệ!');
                delete_transient($lock_key);
                continue;
            }

            try {
                $content_img_tag = $this->content_img_tag($content_m_arr['content']);
            } catch (Exception $e) {
                $this->log_mess('Lỗi khi xử lý nội dung bài viết (html): ' . $e->getMessage());
                delete_transient($lock_key);
                continue;
            }

            $posts_data[] = [
                'post' => [
                    'ID'             => $post_id,
                    'post_title'     => $content_m_arr['title'],
                    'post_content'   => $content_img_tag,
                    'post_status'    => isset($this->options['draft']) ? 'draft' : 'publish',
                    'post_author'    => $user_id,
                    'meta_input'     => array('rewrite' => $type_ai . ' - post')
                ],
                'lock_key' => $lock_key
            ];
        }

        global $wpdb;
        $successful_items = [];
        if (!empty($posts_data)) {
            foreach ($posts_data as $item) {
                $wpdb->query('START TRANSACTION');
                try {
                    $update = wp_update_post($item['post'], true);
                    if (is_wp_error($update)) {
                        throw new Exception($post_id->get_error_message());
                    }
                    $wpdb->query('COMMIT');
                    $successful_items[] = $item['post']['ID'];
                } catch (Exception $e) {
                    $wpdb->query('ROLLBACK');
                    $this->log_mess('Rewrite bài viết ' . $item['post']['ID'] . ' thất bại! ' . $e->getMessage());
                } finally {
                    delete_transient($item['lock_key']);
                }
            }
        }

        if (!empty($successful_items)) {
            $this->log_mess('<span class="green-text">Rewrite thành công ' . count($successful_items) . ' bài viết</span>', false, true, 2);
        } else {
            $this->log_mess('<span class="red-text">Rewrite thất bại!</span>', false, true, 2);
        }

        return !empty($successful_items);
    }

    private function rewrite_keyword($type_ai, $p, $pl, $p2, $pi)
    {
        $api_str = $this->options[$type_ai . '_api_key'];
        if (empty($api_str)) {
            $this->log_mess("$type_ai không có api key");
            return false;
        }

        $max_attempts = 5;
        $attempt = 0;
        do {
            $attempt++;

            $max_item = $this->max_item('keywords');
            $this->log_mess('<span class="orange-text">Đang lấy ' . $max_item . ' keyword từ txt...</span>', false, true, 0, true);
            $cat_data = $this->get_random_cat_txt('keyword', $max_item);

            if ($cat_data === false) {
                $this->log_mess('<span class="red-text">Lấy keyword thất bại!</span>', false, true, 0);
                return false;
            }
            $this->log_mess('<span class="orange-text">Lấy thành công ' . count($cat_data['selected_item']) . ' keyword. Đang check các keyword (' . $cat_data['source'] . ')...</span>', false, true, 0);
            // Khởi tạo biến với giá trị mặc định
            $default_cat_data = [
                'rand_cate_id' => 0,
                'main_txt' => '',
                'active_txt' => '',
                'miss_txt' => '',
                'fail_txt' => '',
                '404_txt' => '',
                'main_arr' => [],
                'selected_item' => [],
                'source' => 'txt'
            ];

            // Merge dữ liệu với giá trị mặc định
            $cat_data = array_merge($default_cat_data, $cat_data);

            // Gán các giá trị
            $rand_cate_id = $cat_data['rand_cate_id'];
            $item_txt = $cat_data['main_txt'];
            $item_active_txt = $cat_data['active_txt'];
            $item_miss_txt = $cat_data['miss_txt'];
            $item_arr = $cat_data['main_arr'];
            $selected_item = $cat_data['selected_item'];

            $successful_items = [];
            $failed_items = [];
            $posts_data = [];
            $ai_requests = [];

            foreach ($selected_item as $item) {
                $item = trim($item);
                if (empty($item)) continue;

                $lock_key = 'process_ai_request_' . md5($item);
                if (get_transient($lock_key)) {
                    continue;
                }
                set_transient($lock_key, true, 60);

                $existing_post = get_posts(array(
                    'post_type' => 'post',
                    'meta_key' => 'keyword',
                    'meta_value' => $item,
                    'posts_per_page' => 1
                ));
                if (!empty($existing_post)) {
                    $this->write_txt($item, $item_txt, $item_arr, $item_miss_txt);
                    $this->log_mess('Đã tồn tại bài viết với keyword: ' . $item);
                    delete_transient($lock_key);
                    continue;
                }

                $links = $this->gg_search_api($item);

                if (empty($links)) {
                    $this->write_txt($item, $item_txt, $item_arr, $item_miss_txt);
                    $this->log_mess('Google search không có kết quả hợp lệ!');
                    delete_transient($lock_key);
                    continue;
                }

                if ($links == 'error') {
                    $this->write_txt($item, $item_txt, $item_arr, $item_miss_txt);
                    $this->log_mess('Lỗi! Vui lòng kiểm tra Google Search API key');
                    delete_transient($lock_key);
                    continue;
                }

                $content_m = '';
                $cur_url = '';
                foreach ($links as $url) {
                    $crawl_content = $this->crawl->getContent($url);
                    if (!empty($crawl_content)) {
                        $content_m = $this->html2m($crawl_content);
                        $cur_url = $url;
                        break;
                    }
                }

                if (empty($content_m)) {
                    $this->write_txt($item, $item_txt, $item_arr, $item_miss_txt);
                    $this->log_mess('crawl thất bại!');
                    delete_transient($lock_key);
                    continue;
                }

                $ai_as = $this->get_ai_as_prompt_cate($rand_cate_id, 'vnrewrite_ai_as_cate', 'vnrewrite_ai_as_common');
                $prompt_cate = $this->get_ai_as_prompt_cate($rand_cate_id, 'vnrewrite_prompt_cate', 'vnrewrite_prompt_common');
                $list_post_keyword = $this->list_post_keyword($item, $rand_cate_id);

                if (!empty($list_post_keyword) && preg_match('/-*\s*\[internal_links\]/', $prompt_cate)) {
                    $pl = str_replace('[links]', $list_post_keyword, $pl);
                    $user_prompt = preg_replace('/-*\s*\[internal_links\]/', $pl, $prompt_cate);
                } else {
                    $user_prompt = $prompt_cate;
                }

                if (empty($this->options['format_img'])) {
                    $pi = '';
                }
                $prompt = $ai_as . $p . $user_prompt . $pi . $p2 . $content_m;
                $prompt = str_replace('[keyword]', $item, $prompt);
                if (str_starts_with($ai_as, 'cuttom:') && str_starts_with($user_prompt, 'cuttom:')) {
                    $prompt = array(
                        'ai_as' => substr($ai_as, 7),
                        'prompt' => substr($user_prompt, 7) . "\n" . $content_m
                    );
                }

                $ai_requests[] = array(
                    'keyword' => $item,
                    'cur_url' => $cur_url,
                    'lock_key' => $lock_key,
                    'prompt' => $prompt,
                    'type_ai' => $type_ai
                );
            }

            if (empty($ai_requests)) {
                if ($attempt >= $max_attempts) {
                    $this->log_mess('<span class="red-text">Đã thử ' . $max_attempts . ' lần nhưng thất bại!</span>', true, true, 2);
                    return false;
                }
                $this->log_mess('<span class="orange-text">Không có keyword nào hợp lệ, đang thử lại lần thứ ' . $attempt . '...</span>', false, true, 2);
                continue; // Quay lại đầu vòng lặp lấy keyword
            }

            if ($type_ai == 'gemini' && !empty($this->options['gemini_proxy'])) {
                $proxy = $this->balance_items($this->options['gemini_proxy'], $ai_requests);
                foreach ($ai_requests as $key => $ai_request) {
                    $ai_requests[$key]['proxy'] = $proxy[$key];
                }
            }

            $api = $this->balance_items($api_str, $ai_requests, $type_ai . '_api_last');
            foreach ($ai_requests as $key => $ai_request) {
                $ai_requests[$key]['api'] = !empty($api[$key]) ? $api[$key] : '';
            }

            $this->log_mess('<span class="orange-text">Đang rewrite ' . count($ai_requests) . ' keyword...</span>', false, true, 0);

            $responses = $this->process_concurrent_ai_requests($ai_requests);
            $user_id = isset($this->options['user']) ? intval($this->options['user']) : get_current_user_id();
            global $wpdb;

            foreach ($responses as $i => $response) {
                $item = $ai_requests[$i]['keyword'] ?? '';
                $lock_key = $ai_requests[$i]['lock_key'] ?? '';
                $cur_url = $ai_requests[$i]['cur_url'] ?? '';

                if (empty($response)) {
                    $failed_items[] = $item;
                    delete_transient($lock_key);
                    continue;
                }

                if (isset($response['error']) && isset($response['api'])) {
                    $failed_items[] = $item;
                    $this->log_mess($response['error'] . ' - API: ' . $response['api']);
                    delete_transient($lock_key);
                    continue;
                }

                $content_m_arr = $this->check_content_m($response);
                if (empty($content_m_arr)) {
                    $failed_items[] = $item;
                    $this->log_mess('Nội dung không hợp lệ');
                    delete_transient($lock_key);
                    continue;
                }

                $post_title = $content_m_arr['title'];
                $slug = $this->convert_to_slug($item);
                $existing_slug = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_name 
                        FROM {$wpdb->posts} 
                        WHERE post_name = %s 
                        AND post_type = 'post' 
                        LIMIT 1",
                    $slug
                ));
                if ($existing_slug) {
                    $failed_items[] = $item;
                    $this->log_mess("Slug '$slug' đã tồn tại - Bỏ qua keyword: $item");
                    delete_transient($lock_key);
                    continue;
                }

                $content_img_m_arr = $this->content_img_m($content_m_arr['content']);
                $imgs_param = $content_img_m_arr['imgs_param'];
                $content = $content_img_m_arr['content'];
                if (isset($this->options['not_img']) && empty($imgs_param)) {
                    $failed_items[] = $item;
                    $this->log_mess('Bài viết bị loại bỏ do không có ảnh');
                    delete_transient($lock_key);
                    continue;
                }

                $content_img_tag = $this->content_img_tag($content);
                $referer = parse_url($cur_url)['host'];
                $pattern = '/<a\s+[^>]*href=["\'](?:https?:)?\/\/([^\/"\' ]*' . preg_quote($referer, '/') . ')[^"\']*["\'][^>]*>(.*?)<\/a>/i';
                $content_img_tag = preg_replace($pattern, '$2', $content_img_tag);

                $row = [
                    'post_title' => $post_title,
                    'post_name' => $slug,
                    'post_content' => $content_img_tag,
                    'post_status' => isset($this->options['draft']) ? 'draft' : 'publish',
                    'post_author' => $user_id,
                    'post_type' => 'post',
                    'post_category'  => array($rand_cate_id),
                    'meta_input'     => array(
                        'rewrite' => $type_ai . ' - keyword',
                        'keyword' => $item,
                        'url'     => $cur_url,
                        'rank_math_focus_keyword' => $item
                    )
                ];

                $posts_data[] = [
                    'post' => $row,
                    'keyword' => $item,
                    'imgs_param' => $imgs_param,
                    'lock_key' => $lock_key
                ];
            }

            if (!empty($posts_data)) {
                foreach ($posts_data as $item) {
                    $wpdb->query('START TRANSACTION');
                    try {
                        $post_id = wp_insert_post($item['post'], true);
                        if (is_wp_error($post_id)) {
                            throw new Exception($post_id->get_error_message());
                        }

                        if (!empty($item['imgs_param'])) {
                            $media_result = $this->add_lib_media_all($post_id, $item['imgs_param']);
                            if (isset($this->options['not_img']) && $media_result === false) throw new Exception('Bài viết không có ảnh');
                        }
                        $wpdb->query('COMMIT');
                        $successful_items[] = $item['keyword'];
                    } catch (Exception $e) {
                        $wpdb->query('ROLLBACK');
                        $failed_items[] = $item['keyword'];
                        $this->log_mess('Rewrite keyword ' . $item['keyword'] . ' thất bại! ' . $e->getMessage());
                    } finally {
                        delete_transient($item['lock_key']);
                    }
                }
            }

            if (!empty($successful_items)) {
                $this->write_txt($successful_items, $item_txt, $item_arr, $item_active_txt);
                $this->log_mess('<span class="green-text">Rewrite thành công ' . count($successful_items) . ' keyword</span>', false, true, 2);
            } else {
                $this->log_mess('<span class="red-text">Rewrite thất bại!</span>', false, true, 2);
            }

            if (!empty($failed_items)) {
                $this->write_txt($failed_items, $item_txt, $item_arr, $item_miss_txt);
            }

            return !empty($successful_items);
        } while (empty($successful_items) && $attempt < $max_attempts);

        return false;
    }

    private function rewrite_video($type_ai, $p, $p2)
    {
        $api_str = $this->options[$type_ai . '_api_key'];
        if (empty($api_str)) {
            $this->log_mess("$type_ai không có api key");
            return false;
        }

        $max_attempts = 5;
        $attempt = 0;
        do {
            $attempt++;

            $max_item = $this->max_item('videos');
            $this->log_mess('<span class="orange-text">Đang lấy ' . $max_item . ' youtube ID từ txt...</span>', false, true, 0, true);
            $cat_data = $this->get_random_cat_txt('video', $max_item);

            if ($cat_data === false) {
                $this->log_mess('<span class="red-text">Lấy video ID thất bại!</span>', false, true, 0);
                return false;
            }
            $this->log_mess('<span class="orange-text">Lấy thành công ' . count($cat_data['selected_item']) . ' youtube ID. Đang check các youtube ID...</span>', false, true, 0);
            // Khởi tạo biến với giá trị mặc định
            $default_cat_data = [
                'rand_cate_id' => 0,
                'main_txt' => '',
                'active_txt' => '',
                'miss_txt' => '',
                'fail_txt' => '',
                '404_txt' => '',
                'main_arr' => [],
                'selected_item' => [],
                'source' => 'txt'
            ];

            // Merge dữ liệu với giá trị mặc định
            $cat_data = array_merge($default_cat_data, $cat_data);

            // Gán các giá trị
            $rand_cate_id = $cat_data['rand_cate_id'];
            $item_txt = $cat_data['main_txt'];
            $item_active_txt = $cat_data['active_txt'];
            $item_miss_txt = $cat_data['miss_txt'];
            $item_arr = $cat_data['main_arr'];
            $selected_item = $cat_data['selected_item'];

            $successful_items = [];
            $failed_items = [];
            $posts_data = [];
            $ai_requests = [];

            foreach ($selected_item as $item) {
                $item = trim($item);
                if (empty($item)) continue;

                $lock_key = 'process_ai_request_' . md5($item);
                if (get_transient($lock_key)) {
                    continue;
                }
                set_transient($lock_key, true, 60);

                $existing_post = get_posts(array(
                    'post_type' => 'post',
                    'meta_key' => 'youtube_id',
                    'meta_value' => $item,
                    'posts_per_page' => 1
                ));
                if (!empty($existing_post)) {
                    $this->write_txt($item, $item_txt, $item_arr, $item_miss_txt);
                    $this->log_mess('Đã tồn tại bài viết với youtube ID: ' . $item);
                    delete_transient($lock_key);
                    continue;
                }

                $sub_title = $this->get_sub_title_yt($item);

                if (empty($sub_title)) {
                    $this->write_txt($item, $item_txt, $item_arr, $item_miss_txt);
                    $this->log_mess("Video ID $item không hợp lệ! (Không có sub hoặc sub quá ngắn...)");
                    delete_transient($lock_key);
                    continue;
                }

                $ai_as = $this->get_ai_as_prompt_cate($rand_cate_id, 'vnrewrite_ai_as_cate', 'vnrewrite_ai_as_common');
                $prompt_cate = $this->get_ai_as_prompt_cate($rand_cate_id, 'vnrewrite_prompt_cate', 'vnrewrite_prompt_common');
                $user_prompt = preg_replace('/-*\s*\[internal_links\]/', '', $prompt_cate);
                $prompt = $ai_as . $p . $user_prompt . $p2 . $sub_title['title'] . "\n" . $sub_title['sub'];
                if (str_starts_with($ai_as, 'cuttom:') && str_starts_with($user_prompt, 'cuttom:')) {
                    $prompt = array(
                        'ai_as' => substr($ai_as, 7),
                        'prompt' => substr($user_prompt, 7) . "\n" . $sub_title['sub']
                    );
                }

                $ai_requests[] = array(
                    'youtube_id' => $item,
                    'lock_key' => $lock_key,
                    'prompt' => $prompt,
                    'type_ai' => $type_ai
                );
            }

            if (empty($ai_requests)) {
                if ($attempt >= $max_attempts) {
                    $this->log_mess('<span class="red-text">Đã thử ' . $max_attempts . ' lần nhưng thất bại!</span>', true, true, 2);
                    return false;
                }
                $this->log_mess('<span class="orange-text">Không có youtube ID nào hợp lệ, đang thử lại lần thứ ' . $attempt . '...</span>', false, true, 2);
                continue; // Quay lại đầu vòng lặp lấy youtube_id
            }

            if ($type_ai == 'gemini' && !empty($this->options['gemini_proxy'])) {
                $proxy = $this->balance_items($this->options['gemini_proxy'], $ai_requests);
                foreach ($ai_requests as $key => $ai_request) {
                    $ai_requests[$key]['proxy'] = $proxy[$key];
                }
            }

            $api = $this->balance_items($api_str, $ai_requests, $type_ai . '_api_last');
            foreach ($ai_requests as $key => $ai_request) {
                $ai_requests[$key]['api'] = !empty($api[$key]) ? $api[$key] : '';
            }

            $this->log_mess('<span class="orange-text">Đang rewrite ' . count($ai_requests) . ' youtube...</span>', false, true, 0);

            $responses = $this->process_concurrent_ai_requests($ai_requests);
            $user_id = isset($this->options['user']) ? intval($this->options['user']) : get_current_user_id();
            global $wpdb;

            foreach ($responses as $i => $response) {
                $item = $ai_requests[$i]['youtube_id'] ?? '';
                $lock_key = $ai_requests[$i]['lock_key'] ?? '';

                if (empty($response)) {
                    $failed_items[] = $item;
                    delete_transient($lock_key);
                    continue;
                }

                if (isset($response['error']) && isset($response['api'])) {
                    $failed_items[] = $item;
                    $this->log_mess($response['error'] . ' - API: ' . $response['api']);
                    delete_transient($lock_key);
                    continue;
                }

                $content_m_arr = $this->check_content_m($response);
                if (empty($content_m_arr)) {
                    $failed_items[] = $item;
                    $this->log_mess('Nội dung không hợp lệ');
                    delete_transient($lock_key);
                    continue;
                }

                $post_title = $content_m_arr['title'];
                $slug = $this->convert_to_slug($post_title);
                $existing_slug = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_name 
                        FROM {$wpdb->posts} 
                        WHERE post_name = %s 
                        AND post_type = 'post' 
                        LIMIT 1",
                    $slug
                ));
                if ($existing_slug) {
                    $failed_items[] = $item;
                    $this->log_mess("Slug '$slug' đã tồn tại - Bỏ qua youtube ID: $item");
                    delete_transient($lock_key);
                    continue;
                }

                $content_img_m_arr = $this->content_img_m($content_m_arr['content']);
                $imgs_param = $content_img_m_arr['imgs_param'];
                $content = $content_img_m_arr['content'];
                if (isset($this->options['not_img']) && empty($imgs_param)) {
                    $failed_items[] = $item;
                    $this->log_mess('Bài viết bị loại bỏ do không có ảnh');
                    delete_transient($lock_key);
                    continue;
                }

                $content_img_tag = $this->content_img_tag($content);

                $row = [
                    'post_title' => $post_title,
                    'post_name' => $slug,
                    'post_content' => $content_img_tag,
                    'post_status' => isset($this->options['draft']) ? 'draft' : 'publish',
                    'post_author' => $user_id,
                    'post_type' => 'post',
                    'post_category'  => array($rand_cate_id),
                    'meta_input'     => array(
                        'rewrite' => $type_ai . ' - youtube',
                        'youtube_id' => $item
                    )
                ];

                $posts_data[] = [
                    'post' => $row,
                    'youtube_id' => $item,
                    'lock_key' => $lock_key
                ];
            }

            if (!empty($posts_data)) {
                foreach ($posts_data as $item) {
                    $wpdb->query('START TRANSACTION');
                    try {
                        $post_id = wp_insert_post($item['post'], true);
                        if (is_wp_error($post_id)) {
                            throw new Exception($post_id->get_error_message());
                        }

                        $img_yt_arr = $this->extract_img_yt($item['youtube_id'], $post_title);
                        if (!empty($img_yt_arr)) {
                            $save_img = $this->save_img($img_yt_arr);
                            if (!empty($save_img)) {
                                $imgs_param = [];
                                $imgs_param[0] = array('path' => $save_img['path'], 'attachment' => $save_img['attachment']);
                                $add_lib_media_all = $this->add_lib_media_all($post_id, $imgs_param);
                                if (isset($this->options['not_img']) && $add_lib_media_all === false) {
                                    $this->remove_post_permanently($post_id);
                                    $this->write_txt($item['youtube_id'], $item_txt, $item_arr, $item_miss_txt);
                                    throw new Exception('Bài viết bị loại bỏ do không tải được ảnh từ youtube');
                                }
                            }
                        }

                        $wpdb->query('COMMIT');
                        $successful_items[] = $item['youtube_id'];
                    } catch (Exception $e) {
                        $wpdb->query('ROLLBACK');
                        $failed_items[] = $item['youtube_id'];
                        $this->log_mess('Rewrite youtube ' . $item['youtube_id'] . ' thất bại! ' . $e->getMessage());
                    } finally {
                        delete_transient($item['lock_key']);
                    }
                }
            }

            if (!empty($successful_items)) {
                $this->write_txt($successful_items, $item_txt, $item_arr, $item_active_txt);
                $this->log_mess('<span class="green-text">Rewrite thành công ' . count($successful_items) . ' youtube ID</span>', false, true, 2);
            } else {
                $this->log_mess('<span class="red-text">Rewrite thất bại!</span>', false, true, 2);
            }

            if (!empty($failed_items)) {
                $this->write_txt($failed_items, $item_txt, $item_arr, $item_miss_txt);
            }

            return !empty($successful_items);
        } while (empty($successful_items) && $attempt < $max_attempts);

        return false;
    }

    private function rewrite_url($type_ai, $p, $p2, $pi)
    {
        $api_str = $this->options[$type_ai . '_api_key'];
        if (empty($api_str)) {
            $this->log_mess("$type_ai không có api key");
            return false;
        }

        $max_attempts = 5;
        $attempt = 0;
        do {
            $attempt++;

            $max_item = $this->max_item();
            $this->log_mess('<span class="orange-text">Đang lấy ' . $max_item . ' url từ json...</span>', false, true, 0, true);
            $cat_data = $this->get_priority_category_from_json($max_item);

            if ($cat_data === false) {
                $this->log_mess('<span class="orange-text">Lấy url từ cache thất bại! Đang lấy ' . $max_item . ' url từ txt...</span>', false, true, 0, true);
                $cat_data = $this->get_random_cat_txt('url', $max_item);
                if ($cat_data === false) {
                    $this->reset_rewrite_url($this->options['re_rewrite_url'] ?? null);
                }
            }

            if ($cat_data === false) {
                $this->log_mess('<span class="red-text">Lấy url thất bại!</span>', false, true, 0);
                return false;
            }
            $this->log_mess('<span class="orange-text">Lấy thành công ' . count($cat_data['selected_item']) . ' url. Đang check các url (' . $cat_data['source'] . ')...</span>', false, true, 0);
            // Khởi tạo biến với giá trị mặc định
            $default_cat_data = [
                'rand_cate_id' => 0,
                'main_txt' => '',
                'active_txt' => '',
                'miss_txt' => '',
                'fail_txt' => '',
                '404_txt' => '',
                'main_arr' => [],
                'selected_item' => [],
                'source' => 'txt'
            ];

            // Merge dữ liệu với giá trị mặc định
            $cat_data = array_merge($default_cat_data, $cat_data);

            // Gán các giá trị
            $rand_cate_id = $cat_data['rand_cate_id'];
            $url_txt = $cat_data['main_txt'];
            $url_active_txt = $cat_data['active_txt'];
            $url_miss_txt = $cat_data['miss_txt'];
            $url_fail_txt = $cat_data['fail_txt'];
            $url_404_txt = $cat_data['404_txt'];
            $url_arr = $cat_data['main_arr'];
            $source_type = $cat_data['source'];
            $selected_item = $cat_data['selected_item'];

            if ($source_type == 'json') {
                $selected_item = array_keys($cat_data['selected_item']);
            }

            $successful_items = [];
            $failed_items = [];
            $ai_requests = [];
            $posts_data = [];

            foreach ($selected_item as $url) {
                $url = trim($url);
                if (empty($url)) continue;

                $lock_key = 'process_ai_request_' . md5($url);
                if (get_transient($lock_key)) {
                    continue;
                }
                set_transient($lock_key, true, 60);

                $existing_post = get_posts(array(
                    'post_type' => 'post',
                    'meta_key' => 'url',
                    'meta_value' => $url,
                    'posts_per_page' => 1
                ));
                if (!empty($existing_post)) {
                    $this->write_txt($url, $url_txt, $url_arr, $url_active_txt);
                    delete_transient($lock_key);
                    continue;
                }

                if ($source_type == 'txt') {
                    $http_status = $this->crawl->getHttpStatus($url);
                    if ($http_status === 404) {
                        $this->write_txt($url, $url_txt, $url_arr, $url_404_txt);
                        $this->log_mess('Rewrite URL thất bại! Page 404! "' . $url . '"!');
                        delete_transient($lock_key);
                        continue;
                    }
                    if ($http_status !== 200) {
                        $this->write_txt($url, $url_txt, $url_arr, $url_fail_txt);
                        $this->log_mess('Rewrite url thất bại! Không crawl được url "' . $url . '"!');
                        delete_transient($lock_key);
                        continue;
                    }
                }

                $download_link = '';
                if (isset($this->options['download'])) {
                    if (empty($this->options['download_element'])) {
                        $this->write_txt($url, $url_txt, $url_arr, $url_fail_txt);
                        $this->log_mess('Không lấy được download_element');
                        delete_transient($lock_key);
                        continue;
                    } else {
                        $download_link = $this->crawl_download_link(esc_url_raw($url));
                        if (empty($download_link)) {
                            $this->write_txt($url, $url_txt, $url_arr, $url_fail_txt);
                            $this->log_mess('Rewrite url thất bại! Không có link download cho url "' . $url . '"!');
                            delete_transient($lock_key);
                            continue;
                        }
                    }
                }

                if ($source_type === 'txt') {
                    $crawl_element = !empty(get_term_meta($rand_cate_id, 'crawl_element_detail', true))
                        ? get_term_meta($rand_cate_id, 'crawl_element_detail', true)
                        : $this->options['crawl_element_detail_common'];
                    $remove_element = !empty(get_term_meta($rand_cate_id, 'remove_element_detail', true))
                        ? get_term_meta($rand_cate_id, 'remove_element_detail', true)
                        : $this->options['remove_element_detail_common'];
                    $content_crawl = $this->crawl->getContent($url, [
                        'selector' => $crawl_element,
                        'remove_selector' => $remove_element
                    ]);
                    if (empty($content_crawl)) {
                        $this->write_txt($url, $url_txt, $url_arr, $url_fail_txt);
                        $this->log_mess('Rewrite url thất bại! Không crawl được url "' . $url . '"!', false, false, 0);
                        delete_transient($lock_key);
                        continue;
                    }
                } else {
                    if (empty($cat_data['selected_item'][$url])) {
                        $this->write_txt($url, $url_txt, $url_arr, $url_miss_txt);
                        $this->log_mess('Không tìm thấy nội dung cho url: ' . $url);
                        delete_transient($lock_key);
                        continue;
                    }
                    $content_crawl = $cat_data['selected_item'][$url];
                }

                $title_crawl = preg_match('/<h1[^>]*>(.*?)<\/h1>/i', $content_crawl, $matches) ? $matches[1] : '';

                $content_m = $this->html2m($content_crawl);
                if (empty($content_m)) {
                    $this->write_txt($url, $url_txt, $url_arr, $url_miss_txt);
                    $this->log_mess('Rewrite url thất bại! Lỗi html');
                    delete_transient($lock_key);
                    continue;
                }

                $ai_as = $this->get_ai_as_prompt_cate($rand_cate_id, 'vnrewrite_ai_as_cate', 'vnrewrite_ai_as_common');
                $prompt_cate = $this->get_ai_as_prompt_cate($rand_cate_id, 'vnrewrite_prompt_cate', 'vnrewrite_prompt_common');
                $user_prompt = preg_replace('/-*\s*\[internal_links\]/', '', $prompt_cate);
                if (empty($this->options['format_img'])) {
                    $pi = '';
                }
                $prompt = $ai_as . $p . $user_prompt . $pi . $p2 . $content_m;
                if (str_starts_with($ai_as, 'cuttom:') && str_starts_with($user_prompt, 'cuttom:')) {
                    $prompt = array(
                        'ai_as' => substr($ai_as, 7),
                        'prompt' => substr($user_prompt, 7) . "\n" . $content_m
                    );
                }

                $ai_requests[] = array(
                    'url' => $url,
                    'lock_key' => $lock_key,
                    'prompt' => $prompt,
                    'type_ai' => $type_ai,
                    'title' => $title_crawl,
                    'download_link' => $download_link
                );
            }

            if (empty($ai_requests)) {
                if ($attempt >= $max_attempts) {
                    $this->log_mess('<span class="red-text">Đã thử ' . $max_attempts . ' lần nhưng không có url hợp lệ!</span>', true, true, 2);
                    return false;
                }
                $this->log_mess('<span class="orange-text">Không có url nào hợp lệ, đang thử lại lần thứ ' . $attempt . '...</span>', false, true, 2);
                continue; // Quay lại đầu vòng lặp lấy url
            }

            if ($type_ai == 'gemini' && !empty($this->options['gemini_proxy'])) {
                $proxy = $this->balance_items($this->options['gemini_proxy'], $ai_requests);
                foreach ($ai_requests as $key => $ai_request) {
                    $ai_requests[$key]['proxy'] = $proxy[$key];
                }
            }

            $api = $this->balance_items($api_str, $ai_requests, $type_ai . '_api_last');
            foreach ($ai_requests as $key => $ai_request) {
                $ai_requests[$key]['api'] = !empty($api[$key]) ? $api[$key] : '';
            }

            $this->log_mess('<span class="orange-text">Đang rewrite (' . $source_type . ') ' . count($ai_requests) . ' url...</span>', false, true, 0);

            $responses = $this->process_concurrent_ai_requests($ai_requests);
            $user_id = isset($this->options['user']) ? intval($this->options['user']) : get_current_user_id();
            global $wpdb;

            foreach ($responses as $i => $response) {
                $url = $ai_requests[$i]['url'] ?? '';
                $download_url = $ai_requests[$i]['download_link'] ?? '';
                $lock_key = $ai_requests[$i]['lock_key'] ?? '';

                if (empty($response)) {
                    $failed_items[] = $url;
                    delete_transient($lock_key);
                    continue;
                }

                if (isset($response['error']) && isset($response['api'])) {
                    $failed_items[] = $url;
                    $this->log_mess($response['error'] . ' - API: ' . $response['api']);
                    delete_transient($lock_key);
                    continue;
                }

                $content_m_arr = $this->check_content_m($response);
                if (empty($content_m_arr)) {
                    $failed_items[] = $url;
                    $this->log_mess('Nội dung không hợp lệ');
                    delete_transient($lock_key);
                    continue;
                }

                $post_title = (isset($this->options['not_rewrite_title']) && !empty($ai_requests[$i]['title'])) ? $ai_requests[$i]['title'] : $content_m_arr['title'];
                $slug = isset($this->options['slug_source'])
                    ? $this->get_slug_from_url($url)
                    : $this->convert_to_slug($post_title);
                $existing_slug = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_name 
                        FROM {$wpdb->posts} 
                        WHERE post_name = %s 
                        AND post_type = 'post' 
                        LIMIT 1",
                    $slug
                ));
                if ($existing_slug) {
                    $failed_items[] = $url;
                    $this->log_mess("Slug '$slug' đã tồn tại - Bỏ qua URL: $url");
                    delete_transient($lock_key);
                    continue;
                }

                $content_img_m_arr = $this->content_img_m($content_m_arr['content']);
                $imgs_param = $content_img_m_arr['imgs_param'];
                $content = $content_img_m_arr['content'];
                if (isset($this->options['not_img']) && empty($imgs_param)) {
                    $failed_items[] = $url;
                    $this->log_mess('Bài viết bị loại bỏ do không có ảnh');
                    delete_transient($lock_key);
                    continue;
                }

                // replace text
                $text_replace_detail = get_term_meta($rand_cate_id, 'text_replace_detail', true);
                if (empty($text_replace_detail)) {
                    $text_replace_detail = $this->options['text_replace_detail_common'];
                }
                if (!empty($text_replace_detail)) {
                    $lines = explode("\n", $text_replace_detail);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (strpos($line, '|') !== false) {
                            list($search, $replace) = explode('|', $line, 2);
                            $content = str_replace($search, $replace, $content);
                        }
                    }
                }

                $content_img_tag = $this->content_img_tag($content);
                $referer = parse_url($url)['host'];
                $pattern = '/<a\s+[^>]*href=["\'](?:https?:)?\/\/([^\/"\' ]*' . preg_quote($referer, '/') . ')[^"\']*["\'][^>]*>(.*?)<\/a>/i';
                $content_img_tag = preg_replace($pattern, '$2', $content_img_tag);

                $row = [
                    'post_title' => $post_title,
                    'post_name' => $slug,
                    'post_content' => $content_img_tag,
                    'post_status' => isset($this->options['draft']) ? 'draft' : 'publish',
                    'post_author' => $user_id,
                    'post_type' => 'post',
                    'post_category'  => array($rand_cate_id),
                    'meta_input'     => array(
                        'rewrite' => $type_ai . ' - url',
                        'url'     => $url
                    )
                ];

                if (!empty($download_url)) {
                    $explode_title = !empty($this->options['explode_title']) ? $this->options['explode_title'] : 'MOD APK';
                    $parts = explode($explode_title, $post_title);
                    $app_game_name = trim($parts[0] ?? '');
                    $remaining = trim($parts[1] ?? '');
                    $version = trim(explode(') ', $remaining)[1] ?? '');

                    $row['meta_input']['download_url'] = $download_url;
                    if (!empty($app_game_name)) {
                        $keyword = $app_game_name . ' ' . $explode_title;
                        $row['meta_input']['app_game_name'] = $app_game_name;
                        $row['meta_input']['keyword'] = $keyword;
                        $row['meta_input']['rank_math_focus_keyword'] = $keyword;

                        $format_slug = $this->options['format_slug'] ?? '';
                        if ($format_slug === 'name') {
                            $row['post_name'] = $this->convert_to_slug($app_game_name);
                        } elseif ($format_slug === 'name_mod') {
                            $row['post_name'] = $this->convert_to_slug($app_game_name . ' MOD');
                        } elseif ($format_slug === 'name_mod_apk') {
                            $row['post_name'] = $this->convert_to_slug($app_game_name . ' MOD APK');
                        }

                        $slug_check = $wpdb->get_var($wpdb->prepare(
                            "SELECT post_name 
                             FROM {$wpdb->posts} 
                             WHERE post_name = %s 
                             AND post_type = 'post' 
                             LIMIT 1",
                            $row['post_name']
                        ));
                        if ($slug_check) {
                            $failed_items[] = $url;
                            $this->log_mess("Slug '{$row['post_name']}' đã tồn tại - Bỏ qua URL: $url");
                            delete_transient($lock_key);
                            continue;
                        }

                        if (preg_match('/\((.*?)\)/', $remaining, $matches)) {
                            $row['meta_input']['mod_key'] = $matches[1];
                        }

                        if (!empty($version)) {
                            $row['meta_input']['version'] = $version;
                            if (preg_match('/^(.*?)\s*\((.*?)\)$/', $version, $matches_v)) {
                                $row['meta_input']['version'] = $matches_v[1] ?? $version;
                                $row['meta_input']['file_size'] = $matches_v[2] ?? '';
                                $row['post_title'] = str_replace('(' . $matches_v[2] . ')', '', $post_title);
                            }
                        }
                    }
                }

                $posts_data[] = [
                    'post' => $row,
                    'url' => $url,
                    'imgs_param' => $imgs_param,
                    'lock_key' => $lock_key
                ];
            }

            if (!empty($posts_data)) {
                foreach ($posts_data as $item) {
                    $wpdb->query('START TRANSACTION');
                    try {
                        $post_id = wp_insert_post($item['post'], true);
                        if (is_wp_error($post_id)) {
                            throw new Exception($post_id->get_error_message());
                        }

                        if (!empty($item['imgs_param'])) {
                            $media_result = $this->add_lib_media_all($post_id, $item['imgs_param']);
                            if (isset($this->options['not_img']) && $media_result === false) throw new Exception('Bài viết không có ảnh');
                        }
                        $wpdb->query('COMMIT');
                        $successful_items[] = $item['url'];
                    } catch (Exception $e) {
                        $wpdb->query('ROLLBACK');
                        $failed_items[] = $item['url'];
                        $this->log_mess('Rewrite url ' . $item['url'] . ' thất bại! ' . $e->getMessage());
                    } finally {
                        delete_transient($item['lock_key']);
                    }
                }
            }

            if (!empty($successful_items)) {
                $this->write_txt($successful_items, $url_txt, $url_arr, $url_active_txt);
                $this->log_mess('<span class="green-text">Rewrite thành công ' . count($successful_items) . ' URL (' . $source_type . ')</span>', false, true, 2);
            } else {
                $this->log_mess('<span class="red-text">Rewrite thất bại! (' . $source_type . ')</span>', false, true, 2);
            }

            if (!empty($failed_items)) {
                $this->write_txt($failed_items, $url_txt, $url_arr, $url_miss_txt);
            }

            $json_file_404 = VNREWRITE_DATA . 'failed_404_' . $rand_cate_id . '.json';
            $json_file_miss = VNREWRITE_DATA . 'failed_failed_' . $rand_cate_id . '.json';
            $this->remove_file_json($json_file_404, $url_txt, $url_404_txt);
            $this->remove_file_json($json_file_miss, $url_txt, $url_miss_txt);

            return !empty($successful_items);
        } while (empty($successful_items) && $attempt < $max_attempts);

        return false;
    }

    private function rewrite_clone($type_ai, $lang_code, $lang, $pc)
    {
        $api_str = $this->options[$type_ai . '_api_key'];
        if (empty($api_str)) {
            $this->log_mess("$type_ai không có api key");
            return false;
        }

        if (!defined('POLYLANG_VERSION')) {
            $this->log_mess('Polylang chưa được cài đặt', true, true, 0);
            return false;
        }

        if (empty($lang_code)) {
            $this->log_mess('<span class="red-text">lang_code empty</span>');
            return false;
        }

        $ai_requests = array();
        $post_ids = $lang['post_ids'];
        $lang_name = $lang['name'];
        $lock_timeout = 60;

        $language_term = get_term_by('slug', $lang_code, 'language');
        if (!$language_term) {
            $this->log_mess('Không tìm thấy ngôn ngữ ' . $lang_name, true, true, 0);
            return false;
        }

        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            $permalink = get_permalink($post_id);

            if (!$post) {
                $this->log_mess('Không tồn tại bài viết có ID: ' . $post_id);
                continue;
            }

            $cate_id = $this->get_unclone_cat($post_id, $lang_code);
            if ($cate_id <= 0) {
                $this->log_mess('Bài viết "' . $permalink . '" chưa có danh mục clone tiếng ' . $lang_name);
                continue;
            }

            $existing_post_meta = get_posts(array(
                'post_type' => 'post',
                'meta_key' => 'clone',
                'meta_value' => $post_id,
                'posts_per_page' => 1,
                'lang' => $lang_code
            ));
            if (!empty($existing_post_meta)) {
                $this->log_mess('Bài viết ' . $permalink . ' đã tồn tại bản clone tiếng ' . $lang_name);
                continue;
            }

            try {
                $content_m = $this->html2m($post->post_content);
            } catch (Exception $e) {
                $this->log_mess('Lỗi khi xử lý nội dung bài viết gốc ' . $permalink . ': ' . $e->getMessage());
                continue;
            }
            if (empty($content_m)) {
                $this->log_mess('Nội dung bài viết ' . $permalink . ' rỗng!');
                continue;
            }

            $pc = str_replace(['[brand]', '[lang]'], [get_bloginfo('name'), $lang_name], $pc);
            $prompt = $pc . "\n# " . $post->post_title . "\n\n" . $content_m;

            $ai_as = !empty($this->options['vnrewrite_ai_as_common']) ? $this->options['vnrewrite_ai_as_common'] : '';
            $prompt_cate = !empty($this->options['vnrewrite_prompt_common']) ? $this->options['vnrewrite_prompt_common'] : '';
            if (str_starts_with($ai_as, 'cuttom:') && str_starts_with($prompt_cate, 'cuttom:')) {
                $prompt = array(
                    'ai_as' => str_replace(['[brand]', '[lang]'], [get_bloginfo('name'), $lang_name], substr($ai_as, 7)),
                    'prompt' => str_replace(['[brand]', '[lang]'], [get_bloginfo('name'), $lang_name], substr($prompt_cate, 7)) . "\n" . $content_m
                );
            }

            $lock_key = 'clone_post_lock_' . $post_id . '_' . $lang_code;
            set_transient($lock_key, true, $lock_timeout);
            $ai_requests[] = array(
                'post_id' => $post_id,
                'cate_id' => $cate_id,
                'slug' => $post->post_name,
                'prompt' => $prompt,
                'type_ai' => $type_ai,
                'title' => $post->post_title,
                'lock_key' => $lock_key
            );
        }

        if (empty($ai_requests)) {
            $this->log_mess('<span class="red-text">Lượt chạy này không có bài viết nào hợp lệ!</span>', false, true, 0);
            return false;
        }

        if ($type_ai == 'gemini' && !empty($this->options['gemini_proxy'])) {
            $proxy = $this->balance_items($this->options['gemini_proxy'], $ai_requests);
            foreach ($ai_requests as $key => $ai_request) {
                $ai_requests[$key]['proxy'] = $proxy[$key];
            }
        }

        $api = $this->balance_items($api_str, $ai_requests, $type_ai . '_api_last');
        foreach ($ai_requests as $key => $ai_request) {
            $ai_requests[$key]['api'] = $api[$key];
        }

        $responses = $this->process_concurrent_ai_requests($ai_requests);
        if (empty($responses)) {
            $this->log_mess('Responses rỗng');
            return false;
        }
        $posts_data = [];
        $user_id = isset($this->options['user']) ? intval($this->options['user']) : get_current_user_id();

        foreach ($responses as $i => $response) {
            $cate_id = $ai_requests[$i]['cate_id'];
            $post_id = $ai_requests[$i]['post_id'];
            $slug = $ai_requests[$i]['slug'];
            $lock_key = $ai_requests[$i]['lock_key'];

            if (empty($response)) {
                $this->log_mess('Response rỗng');
                delete_transient($lock_key);
                continue;
            }

            if (isset($response['error']) && isset($response['api'])) {
                $this->log_mess($response['error'] . ' - API: ' . $response['api']);
                delete_transient($lock_key);
                continue;
            }

            try {
                $content_m_arr = $this->check_content_m($response);
            } catch (Exception $e) {
                $this->log_mess('Lỗi khi xử lý nội dung bài viết (m): ' . $e->getMessage());
                delete_transient($lock_key);
                continue;
            }
            if (empty($content_m_arr['title']) || empty($content_m_arr['content'])) {
                $this->log_mess('Nội dung không hợp lệ!');
                delete_transient($lock_key);
                continue;
            }

            try {
                $content_img_tag = $this->content_img_tag($content_m_arr['content']);
            } catch (Exception $e) {
                $this->log_mess('Lỗi khi xử lý nội dung bài viết (html): ' . $e->getMessage());
                delete_transient($lock_key);
                continue;
            }

            $post_title = $content_m_arr['title'];
            $post_name = isset($this->options['slug_clone'])
                ? $slug
                : $this->convert_to_slug($post_title);

            $posts_data[] = [
                'post' => [
                    'post_title'     => $post_title,
                    'post_name'      => $post_name,
                    'post_content'   => $content_img_tag,
                    'post_status'    => isset($this->options['draft']) ? 'draft' : 'publish',
                    'post_author'    => $user_id,
                    'post_type'      => 'post',
                    'post_category'  => array($cate_id),
                    'meta_input'     => array('clone' => $post_id, 'rewrite' => $type_ai . ' - clone')
                ],
                'origin_post_id' => $post_id,
                'lock_key' => $lock_key
            ];
        }

        if (empty($posts_data)) {
            $this->log_mess('<span class="red-text">Rewrite Clone sang tiếng ' . $lang_name . ' thất bại!</span>', false, true, 2);
            return false;
        }

        $successful_clone = [];
        global $wpdb;
        foreach ($posts_data as $item) {
            $slug_clone = $item['post']['post_name'];
            $slug_filter_callback = function ($slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug) use ($slug_clone) {
                return $slug_clone;
            };

            $wpdb->query('START TRANSACTION');

            try {
                add_filter('wp_unique_post_slug', $slug_filter_callback, 10, 6);

                $clone_post_id = wp_insert_post($item['post'], true);

                if (is_wp_error($clone_post_id)) {
                    throw new Exception($clone_post_id->get_error_message());
                }

                $meta_to_copy = array_diff_key(
                    get_post_meta($item['origin_post_id']),
                    array_flip(['_edit_lock', '_edit_last', 'clone', 'rewrite'])
                );
                foreach ($meta_to_copy as $key => $values) {
                    foreach ((array)$values as $value) {
                        add_post_meta(
                            $clone_post_id,
                            $key,
                            maybe_unserialize($value),
                            false
                        );
                    }
                }

                if (!pll_set_post_language($clone_post_id, $lang_code)) {
                    throw new Exception('Lỗi cập nhật ngôn ngữ');
                }
                $translations = pll_get_post_translations($item['origin_post_id']) ?: [];
                $translations[$lang_code] = $clone_post_id;
                if (!pll_save_post_translations($translations)) {
                    throw new Exception('Lỗi lưu bản dịch');
                }

                $wpdb->query('COMMIT');
                $successful_clone[] = $clone_post_id;
            } catch (Exception $e) {
                $wpdb->query('ROLLBACK');
                $mess = sprintf(
                    "Clone thất bại | Post %d => %s | Error: %s",
                    $item['origin_post_id'],
                    $slug_clone,
                    $e->getMessage()
                );
                $this->log_mess($mess, true, true, 3);
            } finally {
                delete_transient($item['lock_key']);
                remove_filter('wp_unique_post_slug', $slug_filter_callback, 10);
            }
        }

        if (!empty($successful_clone)) {
            $this->log_mess('<span class="green-text">Rewrite Clone thành công ' . count($successful_clone) . ' bài viết sang tiếng ' . $lang_name . '</span>', false, true, 2);
        } else {
            $this->log_mess('<span class="red-text">Rewrite thất bại!</span>', false, true, 2);
        }

        return !empty($successful_clone);
    }

    private function get_priority_category_from_json($max_urls)
    {
        // Bước 1: Lọc và sắp xếp file theo dung lượng
        $json_files = glob(VNREWRITE_DATA . 'url_*.json');
        if (empty($json_files)) return false;

        $file_priority = [];
        foreach ($json_files as $file) {
            if (($size = filesize($file)) > 0) {
                $file_priority[$file] = $size;
            }
        }

        if (empty($file_priority)) return false;
        arsort($file_priority);

        // Chọn ngẫu nhiên trong top 3 file lớn
        $candidates = array_slice(array_keys($file_priority), 0, 3);
        $target_file = $candidates[array_rand($candidates)];
        $category_id = (int)preg_replace('/[^0-9]/', '', basename($target_file));

        // Bước 2: Xử lý file với lock
        $handle = fopen($target_file, 'r+');
        if (!$handle || !flock($handle, LOCK_EX)) {
            @fclose($handle);
            return false;
        }

        // Đọc và validate dữ liệu
        $content = stream_get_contents($handle);
        $urls = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($urls)) {
            flock($handle, LOCK_UN);
            fclose($handle);
            return false;
        }

        // Bước 3: Trích xuất và cập nhật dữ liệu
        $selected = array_slice($urls, 0, $max_urls, true);
        $remaining = array_slice($urls, $max_urls, null, true);

        ftruncate($handle, 0);
        rewind($handle);

        if (!empty($remaining)) {
            fwrite($handle, json_encode($remaining, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            unlink($target_file);
        }

        flock($handle, LOCK_UN);
        fclose($handle);

        // Bước 4: Đọc file txt tương ứng
        $main_txt_path = VNREWRITE_DATA . "url_{$category_id}.txt";
        $main_arr = [];
        if (file_exists($main_txt_path)) {
            $main_content = file_get_contents($main_txt_path);
            $main_arr = array_filter(array_map('trim', explode("\n", $main_content)));
        }

        // Bước 5: Tạo kết quả
        return [
            'rand_cate_id' => $category_id,
            'main_txt' => $main_txt_path,
            'active_txt' => VNREWRITE_DATA . "url_active_{$category_id}.txt",
            'miss_txt' => VNREWRITE_DATA . "url_miss_{$category_id}.txt",
            'fail_txt' => VNREWRITE_DATA . "url_fail_{$category_id}.txt",
            '404_txt' => VNREWRITE_DATA . "url_404_{$category_id}.txt",
            'main_arr' => $main_arr,
            'selected_item' => $selected,
            'source' => 'json'
        ];
    }

    private function get_random_cat_txt($type = 'url', $quantity = 1)
    {
        $cates = get_categories(array('hide_empty' => false));
        $cate_id_arr = array();

        foreach ($cates as $cate) {
            $file_path = VNREWRITE_DATA . $type . '_' . $cate->cat_ID . '.txt';
            if (file_exists($file_path)) {
                if (filesize($file_path) > 5) {
                    $cate_id_arr[] = $cate->cat_ID;
                }
            }
        }

        if (empty($cate_id_arr)) {
            $this->log_mess('<span class="red-text">Không có ' . $type . '. Vui lòng nhập danh sách ' . $type . ' cho các danh mục.</span>', false, true, 2);
            return false;
        }

        $rand_cate_id = $cate_id_arr[array_rand($cate_id_arr)];
        $main_txt = VNREWRITE_DATA . $type . '_' . $rand_cate_id . '.txt';
        $content = file_exists($main_txt) ? file_get_contents($main_txt) : '';
        $items_arr = array_filter(array_map('trim', explode("\n", $content)));

        if (empty($items_arr)) {
            $this->log_mess('<span class="red-text">Không có ' . $type . '. Vui lòng nhập danh sách ' . $type . ' cho các danh mục.</span>', false, true, 2);
            return false;
        }

        shuffle($items_arr); // Xáo trộn mảng
        $quantity = max(1, (int)$quantity);
        $selected_item = array_slice($items_arr, 0, $quantity); // Lấy số lượng theo yêu cầu

        return array(
            'rand_cate_id' => $rand_cate_id,
            'main_txt' => $main_txt,
            'active_txt' => VNREWRITE_DATA . $type . '_active_' . $rand_cate_id . '.txt',
            'miss_txt' => VNREWRITE_DATA . $type . '_miss_' . $rand_cate_id . '.txt',
            'fail_txt' => VNREWRITE_DATA . $type . '_fail_' . $rand_cate_id . '.txt',
            '404_txt' => VNREWRITE_DATA . $type . '_404_' . $rand_cate_id . '.txt',
            'main_arr' => $items_arr,
            'selected_item' => $selected_item,
            'source' => 'txt'
        );
    }

    public function process_concurrent_ai_requests($requests)
    {
        $base_delay_ms = 5000;
        $responses = [];

        // Tạo batches giữ nguyên key gốc
        $batches = [];
        $current_batch = [];
        foreach ($requests as $key => $request) {
            $current_batch[$key] = $request;
            if (count($current_batch) >= $this->options['batch_size']) {
                $batches[] = $current_batch;
                $current_batch = [];
            }
        }
        if (!empty($current_batch)) {
            $batches[] = $current_batch;
        }

        foreach ($batches as $batch) {
            $batch_responses = $this->process_batch_with_retry($batch);

            // Merge responses giữ nguyên key
            $responses = $responses + $batch_responses;

            // Thêm jitter
            $jitter = $base_delay_ms * (1.0 + (mt_rand(0, 20) / 100));
            usleep((int) ($jitter * 1000));
        }

        // Sắp xếp lại theo thứ tự ban đầu
        ksort($responses);
        return array_values($responses);
    }

    private function process_batch_with_retry($original_requests, $max_retries = 3)
    {
        $final_responses = [];
        $backoff_base = 1.0;

        // Khởi tạo retry count và lưu key gốc
        $requests = [];
        foreach ($original_requests as $original_key => $req) {
            $req['_original_key'] = $original_key;
            $req['_retry_count'] = 0;
            $requests[] = $req;
        }

        for ($attempt = 0; $attempt <= $max_retries; $attempt++) {
            if ($attempt > 0) {
                $delay = max(1, $backoff_base * (2 ** ($attempt - 1))) + (rand(1, 1000) / 1000);
                sleep($delay);
                $this->log_mess("Retry attempt $attempt - Delay: " . round($delay, 2) . "s");
            }

            $mh = curl_multi_init();
            $handles = [];
            $retry_queue = [];

            // Tạo curl handles với metadata
            foreach ($requests as $req) {
                $ch = $this->prepare_ai_request(
                    $req['type_ai'],
                    $req['prompt'],
                    $req['api'] ?? '',
                    $req['proxy'] ?? '',
                    $req['model'] ?? ''
                );

                // Lưu metadata vào handle
                $metadata = [
                    'original_key' => $req['_original_key'],
                    'retry_count' => $req['_retry_count']
                ];
                curl_setopt($ch, CURLOPT_PRIVATE, json_encode($metadata));

                curl_multi_add_handle($mh, $ch);
                $handles[] = $ch;
            }

            // Thực thi batch
            $running = null;
            do {
                curl_multi_exec($mh, $running);
                curl_multi_select($mh, 0.1);
            } while ($running > 0);

            // Xử lý responses
            foreach ($handles as $ch) {
                $metadata = json_decode(curl_getinfo($ch, CURLINFO_PRIVATE), true);
                $original_key = $metadata['original_key'];
                $current_retry = $metadata['retry_count'];

                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $response = curl_multi_getcontent($ch);
                $error = curl_error($ch);

                // Tìm request tương ứng
                $request = null;
                foreach ($requests as $req) {
                    if ($req['_original_key'] === $original_key) {
                        $request = $req;
                        break;
                    }
                }

                if ($error) {
                    $this->log_mess("CURL Error [{$request['type_ai']}]: $error");
                    $final_responses[$original_key] = [
                        'error' => $error,
                        'api' => $request['api'] ?? ''
                    ];
                } elseif ($http_code === 429) {
                    if ($current_retry < $max_retries) {
                        $new_request = $request;
                        $new_request['_retry_count'] = $current_retry + 1;
                        $retry_queue[] = $new_request;
                        $this->log_mess("Retry {$original_key} ({$new_request['_retry_count']}/$max_retries)");
                    } else {
                        $final_responses[$original_key] = [
                            'error' => 'Max retries (429)',
                            'api' => $request['api'] ?? ''
                        ];
                    }
                } else {
                    if (empty($response)) {
                        $this->log_mess("{$request['type_ai']} phản hồi rỗng. HTTP Code: $http_code");
                    } else {
                        $final_responses[$original_key] = $this->parse_ai_response(
                            $request['type_ai'],
                            $response,
                            $request['api'] ?? ''
                        );
                    }
                }

                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }

            curl_multi_close($mh);
            $requests = $retry_queue;
            if (empty($requests)) break;
        }

        return $final_responses;
    }

    private function prepare_ai_request($type_ai, $prompt, $api, $proxy, $model)
    {
        switch ($type_ai) {
            case 'gemini':
                return $this->prepare_gemini_request($prompt, $api, $proxy, $model);
            case 'openai':
                return $this->prepare_openai_request($prompt, $api, $model);
            case 'claude':
                return $this->prepare_claude_request($prompt, $api, $model);
            case 'deepseek':
                return $this->prepare_deepseek_request($prompt, $api, $model);
            case 'grok':
                return $this->prepare_grok_request($prompt, $api, $model);
            case 'qwen':
                return $this->prepare_qwen_request($prompt, $api, $model);
            case 'huggingface':
                return $this->prepare_huggingface_request($prompt, $api, $model);
            default:
                return null;
        }
    }

    private function prepare_gemini_request($prompt, $api, $proxy, $model)
    {
        $gemini_model = !empty($model) ? $model : $this->options['gemini_model'];
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $gemini_model . ':generateContent?key=' . $api;

        $request_body = array(
            'contents' => array(
                array(
                    'role' => 'user',
                    'parts' => array(
                        array(
                            'text' => !empty($prompt['prompt']) ? $prompt['prompt'] : $prompt
                        )
                    )
                )
            ),
            'safetySettings' => array(
                array(
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_NONE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_NONE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_NONE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_NONE'
                )
            )
        );

        if (!empty($prompt['ai_as'])) {
            $request_body['system_instruction'] = array(
                'parts' => array(
                    array(
                        'text' => $prompt['ai_as']
                    )
                )
            );
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if ($proxy == 'empty') {
            $proxy = '';
        }

        if (!empty($proxy)) {
            $proxy_arr = explode(':', $proxy);
            if (!empty($proxy_arr[0]) && !empty($proxy_arr[1])) {
                curl_setopt($ch, CURLOPT_PROXY, $proxy_arr[0]);
                curl_setopt($ch, CURLOPT_PROXYPORT, $proxy_arr[1]);
            }
            if (!empty($proxy_arr[2]) && !empty($proxy_arr[3])) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_arr[2] . ':' . $proxy_arr[3]);
            }
        }

        return $ch;
    }

    private function prepare_openai_request($prompt, $api, $model)
    {
        $url = !empty($this->options['openai_endpoint']) ? $this->options['openai_endpoint'] : 'https://api.openai.com/v1/chat/completions';

        $request_body = [];
        if (is_array($prompt) && !empty($prompt['ai_as'])) {
            $request_body['messages'] = [
                [
                    "content" => $prompt['ai_as'],
                    "role" => "system"
                ]
            ];
        }

        $content = is_array($prompt) && !empty($prompt['prompt']) ? $prompt['prompt'] : $prompt;
        $request_body['messages'][] = [
            "content" => $content,
            "role" => "user"
        ];

        $request_body['model'] = !empty($model) ? $model : $this->options['openai_model'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        return $ch;
    }

    private function prepare_claude_request($prompt, $api, $model)
    {
        $url = !empty($this->options['claude_endpoint']) ? $this->options['claude_endpoint'] : 'https://api.anthropic.com/v1/messages';

        $request_body = [];
        if (is_array($prompt) && !empty($prompt['ai_as'])) {
            $request_body['system'] = $prompt['ai_as'];
        }
        $content = is_array($prompt) && !empty($prompt['prompt']) ? $prompt['prompt'] : $prompt;
        $request_body['messages'] = [
            [
                "content" => $content,
                "role" => "user"
            ]
        ];
        $request_body['model'] = !empty($model) ? $model : $this->options['claude_model'];
        $request_body['max_tokens'] = 8192;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'x-api-key: ' . $api,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        return $ch;
    }

    private function prepare_deepseek_request($prompt, $api, $model)
    {
        $request_body = [];

        if (is_array($prompt) && !empty($prompt['ai_as'])) {
            $request_body['messages'] = [
                [
                    "content" => $prompt['ai_as'],
                    "role" => "system"
                ]
            ];
        }

        $content = is_array($prompt) && !empty($prompt['prompt']) ? $prompt['prompt'] : $prompt;
        $request_body['messages'][] = [
            "content" => $content,
            "role" => "user"
        ];

        $request_body['model'] = !empty($model) ? $model : $this->options['deepseek_model'];
        $request_body['max_tokens'] = 8192;

        $url = !empty($this->options['deepseek_endpoint']) ? $this->options['deepseek_endpoint'] : 'https://api.deepseek.com/chat/completions';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $api
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        return $ch;
    }

    private function prepare_grok_request($prompt, $api, $model)
    {
        $request_body = [];

        if (is_array($prompt) && !empty($prompt['ai_as'])) {
            $request_body['messages'][] = [
                "role" => "system",
                "content" => $prompt['ai_as']
            ];
        }

        $content = is_array($prompt) && !empty($prompt['prompt']) ? $prompt['prompt'] : $prompt;
        if (!empty($content)) {
            $request_body['messages'][] = [
                "role" => "user",
                "content" => $content
            ];
        }

        $request_body['model'] = !empty($model) ? $model : $this->options['grok_model'];

        $url = !empty($this->options['grok_endpoint']) ? $this->options['grok_endpoint'] : 'https://api.x.ai/v1/chat/completions';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $api
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        return $ch;
    }

    private function prepare_qwen_request($prompt, $api, $model)
    {
        $request_body = [];

        if (is_array($prompt) && !empty($prompt['ai_as'])) {
            $request_body['messages'] = [
                [
                    "content" => $prompt['ai_as'],
                    "role" => "system"
                ]
            ];
        }

        $content = is_array($prompt) && !empty($prompt['prompt']) ? $prompt['prompt'] : $prompt;
        $request_body['messages'][] = [
            "content" => $content,
            "role" => "user"
        ];

        $request_body['model'] = !empty($model) ? $model : $this->options['qwen_model'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        return $ch;
    }

    private function prepare_huggingface_request($prompt, $api, $model)
    {
        $request_body = [];

        if (is_array($prompt) && !empty($prompt['ai_as'])) {
            $request_body['messages'] = [
                [
                    "content" => $prompt['ai_as'],
                    "role" => "system"
                ]
            ];
        }

        $content = is_array($prompt) && !empty($prompt['prompt']) ? $prompt['prompt'] : $prompt;
        $request_body['messages'][] = [
            "content" => $content,
            "role" => "user"
        ];

        $m_e_str = !empty($model) ? $model : ($this->options['huggingface_model'] ?? '');
        $m_e_arr = explode('|', $m_e_str);
        $m = $m_e_arr[0] ?? '';
        $e = $m_e_arr[1] ?? '';

        $request_body['model'] = $m;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $e);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $api
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        return $ch;
    }

    private function parse_ai_response($type_ai, $response, $api = '')
    {
        if ($response === null) {
            $this->log_mess('Empty data received for AI type: ' . $type_ai);
            return null;
        }

        $response_data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_mess("Dữ liệu trả về từ {$type_ai} không hợp lệ: " . json_last_error_msg());
            return null;
        }

        if (isset($response_data['promptFeedback']['blockReason']) && $response_data['promptFeedback']['blockReason'] === "OTHER") {
            $this->log_mess('Nội dung đầu vào bị block');
            return null;
        }

        if ($type_ai === 'gemini') {
            if (isset($response_data['error'])) {
                $error = $response_data['error'];
                $msg = !empty($error['message']) ? $error['message'] : (is_string($error) ? $error : 'Unknown error');
                $code = !empty($error['code']) ? $error['code'] : '';
                $this->log_mess("$type_ai Response: $msg - Code: $code - API: $api");
                strpos($msg, 'has been suspended') !== false && $this->gemini_remove_api($api);
                return null;
            }
            return $response_data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        }

        // Gom nhóm các AI có cấu trúc phản hồi tương tự nhau
        $ai_group = ['openai', 'claude', 'deepseek', 'qwen', 'huggingface', 'grok'];

        if (in_array($type_ai, $ai_group, true)) {
            if (isset($response_data['error'])) {
                $error = $response_data['error'];
                $msg = !empty($error['message']) ? $error['message'] : (is_string($error) ? $error : 'Unknown error');
                $code = !empty($error['code']) ? $error['code'] : '';
                $this->log_mess("$type_ai Response: $msg - Code: $code - API: $api");
                return null;
            }

            if ($type_ai === 'claude') {
                return $response_data['content'][0]['text'] ?? null;
            }

            $content = $response_data['choices'][0]['message']['content'] ?? null;

            if ($type_ai === 'huggingface' && !empty($content)) {
                $content = preg_replace('/<think>.*?<\/think>/s', '', $content);
            }

            return $content;
        }

        $this->log_mess("Unknown AI type: $type_ai");
        return null;
    }

    private function gg_search_api($keyword)
    {
        $links = array();

        if ($this->options['gg_search_api'] == '') {
            $mess = '<span class="red-text">Không có google search API</span>';
            $this->log_mess($mess, true, true, 0);
            return 'error';
        }

        $mess = '<span class="orange-text">Đang google search API với từ khóa "' . $keyword . '"</span>';
        $this->log_mess($mess, false, true, 0);

        $gg_search_api = $this->set_api_key($this->options['gg_search_api'], 'gg_search_api_key_last');
        $cx = '642c649fb1dfd423b';
        $url = 'https://customsearch.googleapis.com/customsearch/v1?cx=' . urlencode($cx) . '&q=' . urlencode($keyword) . '&key=' . $gg_search_api;

        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            $mess = '<span class="red-text">gg_search_api: ' . $response->get_error_message() . '</span>';
            $this->log_mess($mess);
            return $links;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            $mess = '<span class="red-text">gg_search_api: ' . $data['error']['message'] . '</span>';
            $this->log_mess($mess);
            return 'error';
        }

        if (isset($data['items'])) {
            $remove = 'google\.|facebook\.com|tiktok\.com|wikipedia\.|wiki|pinterest|shopee\.vn|tiki\.vn|ebay\.com|amazon\.|quora\.com|reddit\.com|medium\.com|stackoverflow\.com|yelp\.com|lumendatabase\.org|sex|clip|video|hentai|porn|xxx|adult|blowjob|sucking|drug|gun|dict|etsy\.com|vibrator|chastity|movie|porn|penis|erection|vape|tobacco|cigarette|marijuana|gundict|lazada|tripadvisor\.com|\.jpg|\.jpeg|\.png|\.gif|\.svg|\.pdf|\.doc|\.docx|\.JPG|\.JPEG|\.PNG|\.GIF|\.SVG|\.PDF|\.DOC|\.DOCX|%23';

            foreach ($data['items'] as $item) {
                if (!preg_match('/' . $remove . '/', $item['link'])) {
                    $links[] = $item['link'];
                }
            }
        }

        return $links;
    }

    private function crawl_download_link($url)
    {
        if (empty($this->options['download_element'])) {
            return false;
        }

        $download_link = $url;
        if (!empty($this->options['get_download_link'])) {
            $parts1 = explode('|', $this->options['get_download_link']);
            if (count($parts1) === 2) {
                $download_link_arr = $this->crawl->getContent($url, array('get_attribute' => array($parts1[0] => $parts1[1])));
                if (!empty($download_link_arr[$parts1[0]][0])) {
                    $download_link = $download_link_arr[$parts1[0]][0];
                }
            }
        }
        if (!empty($this->options['download_link_ext'])) {
            $download_link .= $this->options['download_link_ext'];
        }

        $parts2 = explode('|', $this->options['download_element']);
        if (count($parts2) === 2) {
            $href_arr = $this->crawl->getContent($download_link, array('get_attribute' => array($parts2[0] => $parts2[1])));

            if (!empty($href_arr[$parts2[0]][0])) {
                $href = $href_arr[$parts2[0]][0] ?? '';
            }
        }

        if (empty($href)) {
            return false;
        }

        if (strpos($url, 'apkmody.com') !== false) {
            $filename = html_entity_decode($href, ENT_QUOTES, 'UTF-8');
            $filename = iconv('UTF-8', 'ASCII//TRANSLIT', $filename);
            $filename = preg_replace('/[\s\/\|]/', '_', $filename);
            $filename = str_replace('_.', '.', $filename);
            $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
            $filename = preg_replace('/[-_]+/', '_', $filename);
            return base64_decode($href) . '/' . $filename;
        }

        return $href;
    }

    public function vnrewrite_user($in)
    {
        $data = array();
        $home_arr = parse_url(home_url());
        $domain = $home_arr['host'];

        $url = 'https://vnrewrite.com/api2/';
        //$url = 'https://vnrewrite.com/api-test/';
        if (!empty($in['topic']) && !empty($in['tool'])) {
            $url = 'https://vnrewrite.com/api-tool/';
        }

        $response = wp_remote_post($url, array(
            'body' => $in,
            'headers' => array(
                'Referer' => $domain,
            ),
            'timeout' => 60,
            'sslverify' => false
        ));

        if (is_wp_error($response)) {
            $mess = '<span class="red-text">vnrewrite_user: ' . $response->get_error_message() . '</span>';
            $this->log_mess($mess, true, true, 0);
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
        }

        return $data;
    }

    public function balance_items(string $need_string, array $target_arr, string $last_key = ''): array
    {
        if (empty($need_string) || empty($target_arr)) {
            return [];
        }

        $need_arr = explode('|', $need_string);

        // Xử lý last_key nếu có
        if (!empty($last_key)) {
            $value_key = get_option($last_key, '');
            if (!empty($value_key)) {
                $key_position = array_search($value_key, $need_arr);
                if ($key_position !== false) {
                    // Sắp xếp lại mảng để bắt đầu từ phần tử sau last_key
                    $start_position = ($key_position + 1) % count($need_arr);
                    $need_arr = array_merge(
                        array_slice($need_arr, $start_position),
                        array_slice($need_arr, 0, $start_position)
                    );
                }
            }
        }

        // Đếm số lượng phần tử
        $num_need = count($need_arr);
        $num_target = count($target_arr);

        // Cân bằng số lượng phần tử
        if ($num_target < $num_need) {
            $need_arr = array_slice($need_arr, 0, $num_target);
        } else {
            while (count($need_arr) < $num_target) {
                $need_arr[] = $need_arr[count($need_arr) % $num_need];
            }
        }

        if (!empty($last_key)) {
            $last_item = end($need_arr);
            update_option($last_key, $last_item);
        }

        return $need_arr;
    }

    public function set_api_key($api_key_str, $type_api_key_last = 'gemini_api_key_last')
    {
        $api_key_arr = explode('|', $api_key_str);
        if (count($api_key_arr) == 1) {
            $api_key = $api_key_arr[0];
        } else {
            $api_key_last = get_option($type_api_key_last, '');
            if ($api_key_last == '') {
                $api_key = $api_key_arr[0];
            } else {
                $key = array_search($api_key_last, $api_key_arr);
                if ($key !== false) {
                    $next_key = $key + 1;
                    if ($next_key < count($api_key_arr)) {
                        $api_key = $api_key_arr[$next_key];
                    } else {
                        $api_key = $api_key_arr[0];
                    }
                } else {
                    $api_key = $api_key_arr[0];
                }
            }
            update_option($type_api_key_last, $api_key);
        }

        return $api_key;
    }

    private function list_post_keyword($keyword, $cat)
    {
        $args = array(
            's' => $keyword,
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'cat' => $cat
        );

        $query = new WP_Query($args);
        $post_links = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $meta_keyword = get_post_meta(get_the_ID(), 'keyword', true);
                $rankmath_keyword = get_post_meta(get_the_ID(), 'rank_math_focus_keyword', true);
                $rankmath_keyword = !empty($rankmath_keyword) ? explode(',', $rankmath_keyword)[0] : '';

                if (!empty($meta_keyword)) {
                    $anchor_text = $meta_keyword;
                } elseif (!empty($rankmath_keyword)) {
                    $anchor_text = $rankmath_keyword;
                } else {
                    $anchor_text = get_the_title();
                }
                $post_links[] = '[' . esc_html($anchor_text) . '](' . esc_url(get_permalink()) . ')';
            }
            wp_reset_postdata();
        }
        return !empty($post_links) ? implode(', ', $post_links) : '';
    }

    private function check_content_m($content)
    {
        $result = array();
        $title = '';

        if (!is_string($content)) {
            return array();
        }

        $content = preg_replace('/^```markdown\n/', '', $content);
        $content = preg_replace('/\n```$/', '', $content);

        $content = preg_replace('/(!\[.*?\]\(.*?\)(?:_[^_]+?_)?\s*)$/', '', $content);
        $content = preg_replace('/(\s*(\[[^\]]+\]|\#{1,6}\s+[^\n]+)\s*)+$/', '', $content);

        preg_match_all('/^# (.+)$/m', $content, $h1matches);
        preg_match_all('/^## (.+)$/m', $content, $h2matches);
        preg_match_all('/^### .+$/m', $content, $h3matches);

        if (count($h1matches[0]) == 1) {
            $title = $h1matches[1][0];
            $h1Pos = strpos($content, $h1matches[0][0]);
        } elseif (count($h2matches[0]) == 1 && count($h3matches[0]) >= 2) {
            $content = preg_replace_callback('/^(#{1,5}) /m', function ($match) {
                return substr($match[1], 0, -1) . ' ';
            }, $content);

            preg_match('/^# (.+)$/m', $content, $newH1Match);
            if (!empty($newH1Match)) {
                $title = $newH1Match[1];
                $h1Pos = strpos($content, $newH1Match[0]);
            }
        } else {
            $this->log_mess('Bài viết có cấu trúc không hợp lệ (không có tiêu đề h1, không có các heading)');
            return $result;
        }

        if (isset($h1Pos)) {
            $textBeforeH1 = substr($content, 0, $h1Pos);
            if (trim($textBeforeH1) !== '') {
                $content = substr($content, $h1Pos);
            }
            $content = preg_replace('/^# .+$/m', '', $content, 1);
            $content = ltrim($content);
        }

        $count_word = $this->count_word($content);
        if ($count_word < 100) {
            $this->log_mess('Nội dung bài viết không hợp lệ (quá ngắn)');
            return $result;
        }

        if (isset($this->options['min_word']) && $this->options['min_word'] > 0) {
            if ($count_word < $this->options['min_word']) {
                $this->log_mess("Bài  viết chỉ có {$count_word} từ, ít hơn yêu cầu tối thiểu {$this->options['min_word']} từ");
                return $result;
            }
        }

        if (empty($title)) {
            $this->log_mess('Bài viết Không có tiêu đề');
            return $result;
        }

        return array('title' => $title, 'content' => $content);
    }

    private function get_sub_title_yt($video_id)
    {
        $result = array();
        $title = '';
        $sub = '';

        $url = "https://www.youtube.com/watch?v=" . $video_id;
        $response = wp_remote_get($url, array('sslverify' => false));

        if (is_wp_error($response)) {
            $mess = '<span class="red-text">get_sub_yt: ' . $response->get_error_message() . '</span>';
            $this->log_mess($mess);
            return $result;
        }

        $body = wp_remote_retrieve_body($response);
        preg_match('/<title>(.*?)<\/title>/', $body, $title_matches);
        $title = isset($title_matches[1]) ? str_replace('- YouTube', '', $title_matches[1]) : '';
        if (empty($title)) {
            $mess = '<span class="red-text">get_sub_yt: Không lấy được tiêu đề video</span>';
            $this->log_mess($mess);
            return $result;
        }

        preg_match('/"captionTracks":\[\{"baseUrl":"(.*?)"/', $body, $matches);

        if (isset($matches[1])) {
            $subtitle_url = json_decode('"' . $matches[1] . '"');
            $subtitle_response = wp_remote_get($subtitle_url, array('sslverify' => false));

            if (is_wp_error($subtitle_response)) {
                $mess = '<span class="red-text">get_sub_yt: ' . $subtitle_response->get_error_message() . '</span>';
                $this->log_mess($mess);
                return $result;
            }

            $subtitle_body = wp_remote_retrieve_body($subtitle_response);
            $subtitles = [];
            $xml = simplexml_load_string($subtitle_body);

            if ($xml === false) {
                return "Lỗi khi phân tích XML.";
                $mess = '<span class="red-text">get_sub_yt: Lỗi khi phân tích sub</span>';
                $this->log_mess($mess);
                return $result;
            }

            foreach ($xml->text as $text) {
                $subtitles[] = (string)$text;
            }

            $sub = implode('<br>', $subtitles);

            if (str_word_count($sub) < 100) {
                $mess = '<span class="red-text">get_sub_yt: Sub quá ngắn</span>';
                $this->log_mess($mess, true, true, 0);
                return $result;
            }

            $result = array('title' => $title, 'sub' => $sub);
        }

        return $result;
    }

    private function save_img($arr)
    {
        $MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
        $JPEG_QUALITY = 90;
        $PNG_COMPRESSION = 9;
        $WEBP_QUALITY = 80;
        $ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        $result = [];

        if (empty($arr)) {
            return $result;
        }

        $source_src = $arr['src'] ?? '';
        if (empty($source_src)) {
            return $result;
        }

        $img_info = $this->get_img_info($source_src, true);
        if (!$img_info || empty($img_info['data']) || empty($img_info['mime_type']) || empty($img_info['width']) || empty($img_info['height'])) {
            $this->log_mess('Không lấy được thông tin ảnh ' . $source_src);
            return $result;
        }

        if (strlen($img_info['data']) > $MAX_FILE_SIZE) {
            $this->log_mess('Kích thước ảnh vượt quá giới hạn cho phép: ' . ($MAX_FILE_SIZE / 1024 / 1024) . 'MB - ' . $source_src);
            return $result;
        }

        if (!in_array($img_info['mime_type'], $ALLOWED_MIME_TYPES)) {
            $this->log_mess('Định dạng ảnh không được hỗ trợ: ' . $img_info['mime_type'] . ' - ' . $source_src);
            return $result;
        }

        $upload_dir = wp_upload_dir();
        if (!is_writable($upload_dir['path'])) {
            $this->log_mess('Không có quyền ghi vào thư mục uploads ' . $source_src);
            return $result;
        }

        $alt = $arr['alt'] ?? '';
        $slug_source = isset($this->options['slug_source_img']) || empty($alt)
            ? pathinfo($source_src, PATHINFO_FILENAME)
            : $alt;
        $slug = $this->convert_to_slug($slug_source);

        $format_img = strtolower($this->options['format_img'] ?? 'jpg');
        if (!in_array('image/' . $format_img, $ALLOWED_MIME_TYPES)) {
            $format_img = 'jpg';
        }

        $file_name = $slug . '.' . $format_img;
        $path = $upload_dir['path'] . '/' . $file_name;
        $temp_path = $upload_dir['path'] . '/' . $slug . '.tmp';

        try {
            // Lưu dữ liệu hình ảnh vào tệp tạm thời
            if (file_put_contents($temp_path, $img_info['data']) === false) {
                $this->log_mess('Không thể ghi file tạm thời: ' . $temp_path);
                return false;
            }

            // Mở hình ảnh từ tệp tạm thời
            $image = imagecreatefromstring(file_get_contents($temp_path));
            if (!$image) {
                $this->log_mess('Không thể mở hình ảnh từ dữ liệu: ' . $temp_path);
                return false;
            }

            // Kiểm tra nếu hình ảnh là paletted và chuyển đổi sang true color
            if (imageistruecolor($image) === false) {
                $true_color_image = imagecreatetruecolor($img_info['width'], $img_info['height']);
                if (!$true_color_image) {
                    $this->log_mess('Không thể tạo hình ảnh true color');
                    imagedestroy($image);
                    return false;
                }

                imagecopy($true_color_image, $image, 0, 0, 0, 0, $img_info['width'], $img_info['height']);
                imagedestroy($image);
                $image = $true_color_image;
            }

            // Xử lý và lưu hình ảnh mới với định dạng tùy biến
            $save_success = false;
            switch ($format_img) {
                case 'jpg':
                case 'jpeg':
                    $save_success = imagejpeg($image, $path, $JPEG_QUALITY);
                    break;
                case 'png':
                    $save_success = imagepng($image, $path, $PNG_COMPRESSION);
                    break;
                case 'webp':
                    $save_success = imagewebp($image, $path, $WEBP_QUALITY);
                    break;
                default:
                    $this->log_mess('Định dạng hình ảnh không được hỗ trợ: ' . $format_img);
                    imagedestroy($image);
                    return false;
            }

            if (!$save_success) {
                $this->log_mess('Không thể lưu hình ảnh mới với định dạng ' . $format_img . ': ' . $path);
                return false;
            }

            $user_id = isset($this->options['user']) ? intval($this->options['user']) : get_current_user_id();
            $url = $upload_dir['url'] . '/' . $file_name;
            $attachment = [
                'guid' => $url,
                'post_mime_type' => 'image/' . $format_img,
                'post_title' => $alt,
                'post_content' => $alt,
                'post_excerpt' => $alt,
                'post_status' => 'inherit',
                'post_author' => $user_id,
            ];

            $result = [
                'src' => $url,
                'alt' => $alt,
                'attachment' => $attachment,
                'path' => $path,
                'width' => $img_info['width'],
                'height' => $img_info['height'],
            ];
        } catch (Exception $e) {
            $this->log_mess('Lỗi khi xử lý hình ảnh: ' . $e->getMessage());
            return false;
        } finally {
            if (file_exists($temp_path)) {
                @unlink($temp_path);
            }
            if (isset($image) && (is_resource($image) || $image instanceof \GdImage)) {
                imagedestroy($image);
            }
        }

        return $result;
    }

    private function get_img_info($url, $get_data = false, $timeout = 30)
    {
        $site_url = parse_url(site_url(), PHP_URL_HOST);
        $image_host = parse_url($url, PHP_URL_HOST);
        $is_local = ($site_url === $image_host);

        // Xử lý ảnh local
        if ($is_local) {
            $local_path = str_replace(
                [site_url(), home_url()],
                ABSPATH,
                $url
            );

            if (!file_exists($local_path)) {
                $this->log_mess('get_img_info: File ảnh local không tồn tại: ' . $local_path);
                return false;
            }

            try {
                $img_info = @getimagesize($local_path);
                if ($img_info === false) {
                    $this->log_mess('get_img_info: File ảnh không hợp lệ hoặc bị hỏng: ' . $local_path);
                    return false;
                }
                return ['width' => $img_info[0], 'height' => $img_info[1]];
            } catch (Exception $e) {
                $this->log_mess("get_img_info: Lỗi khi lấy kích thước ảnh local: " . $e->getMessage());
                return false;
            }
        }

        // Xử lý ảnh từ URL bên ngoài
        try {
            $head_response = wp_remote_request($url, array(
                'method'    => 'HEAD',
                'timeout'   => 5,
                'sslverify' => false,
                'headers'   => array(
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                ),
            ));

            if (is_wp_error($head_response)) {
                throw new Exception('Không thể kết nối bằng HEAD: ' . $head_response->get_error_message());
            }

            $code = wp_remote_retrieve_response_code($head_response);
            $content_type = wp_remote_retrieve_header($head_response, 'content-type');
            $content_length = wp_remote_retrieve_header($head_response, 'content-length');

            // Kiểm tra metadata từ HEAD
            if ($code !== 200 || strpos($content_type, 'image/') === false || $content_length < 100) {
                throw new Exception("HEAD check không hợp lệ (code: $code|content-type: $content_type|content-length: $content_length).");
            }

            $response = wp_remote_get($url, array(
                'timeout'   => $timeout,
                'connection_timeout' => 5,
                'sslverify' => false,
                'headers'   => array(
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                ),
            ));

            if (is_wp_error($response)) {
                throw new Exception('Không thể kết nối bằng GET: ' . $response->get_error_message());
            }

            $data = wp_remote_retrieve_body($response);

            if (empty($data)) {
                throw new Exception('Nội dung ảnh rỗng');
            }

            // Xử lý ảnh
            $img = @imagecreatefromstring($data);
            if (!$img || !is_resource($img) && !($img instanceof GdImage)) {
                throw new Exception('Nội dung ảnh không hợp lệ hoặc bị hỏng');
            }

            $width = imagesx($img);
            $height = imagesy($img);

            if ($width < 200 || $height < 100) {
                imagedestroy($img);
                throw new Exception("Kích thước ảnh quá nhỏ: {$width}x{$height}");
            }

            $info_img = [
                'width'     => $width,
                'height'    => $height,
                'mime_type' => $content_type,
            ];

            if ($get_data) {
                $info_img['data'] = $data;
            }

            imagedestroy($img);

            return $info_img;
        } catch (Exception $e) {
            if (isset($img) && (is_resource($img) || $img instanceof GdImage)) {
                imagedestroy($img);
            }
            $this->log_mess("get_img_info: Lỗi khi xử lý ảnh: " . $e->getMessage());
            return false;
        }
    }

    private function custom_generate_attachment_metadata($path)
    {
        $metadata = array();

        $metadata['file'] = _wp_relative_upload_path($path);

        $image_size = @getimagesize($path);
        if ($image_size) {
            $metadata['width'] = $image_size[0];
            $metadata['height'] = $image_size[1];
        }

        $metadata['image_meta'] = wp_read_image_metadata($path);

        return $metadata;
    }

    private function extract_img_yt($video_id, $title)
    {
        if ($this->get_img_info('https://img.youtube.com/vi/' . $video_id . '/hq720.jpg')) {
            $img = array(
                'src' => 'https://img.youtube.com/vi/' . $video_id . '/hq720.jpg',
                'alt' => $title,
                'width' => 1280,
                'height' => 720
            );
        } elseif ($this->get_img_info('https://img.youtube.com/vi/' . $video_id . '/sddefault.jpg')) {
            $img = array(
                'src' => 'https://img.youtube.com/vi/' . $video_id . '/sddefault.jpg',
                'alt' => $title,
                'width' => 640,
                'height' => 480
            );
        } elseif ($this->get_img_info('https://img.youtube.com/vi/' . $video_id . '/hqdefault.jpg')) {
            $img = array(
                'src' => 'https://img.youtube.com/vi/' . $video_id . '/hqdefault.jpg',
                'alt' => $title,
                'width' => 480,
                'height' => 360
            );
        } else {
            $img = [];
        }

        return $img;
    }

    private function extract_img($content_m)
    {
        preg_match_all('/!\[(.*?)\]\((.*?)(?:\s+"(.*?)")?\)/', $content_m, $matches);
        $result = [];
        foreach ($matches[2] as $index => $url) {
            if (!empty($url)) {
                $alt_text = $matches[1][$index];
                $result[] = [
                    'src' => $url,
                    'alt' => $alt_text,
                ];
            }
        }
        return $result;
    }

    private function content_img_m($content_m)
    {
        $imgs_param = [];
        $imgs = $this->extract_img($content_m);

        if (empty($imgs)) {
            //$this->log_mess('Bài viết không có ảnh (AI không chèn ảnh hoặc bài gốc không có ảnh)');
            return array('content' => $content_m, 'imgs_param' => $imgs_param);
        }

        foreach ($imgs as $img_old) {
            $img_new = $this->save_img($img_old);
            $pattern = '/!\[.*?\]\(\s*' . preg_quote($img_old['src'], '/') . '\s*\)/';

            if (!empty($img_new) && !empty($img_new['src']) && !empty($img_new['width']) && !empty($img_new['height'])) {
                $alt_clean = !empty($img_new['alt']) ? esc_attr($img_new['alt']) : '';
                $replacement = '![' . $alt_clean . '](' . $img_new['src'] . '){width=' . $img_new['width'] . ' height=' . $img_new['height'] . '}';
                $content_m = preg_replace($pattern, $replacement, $content_m);
                $imgs_param[] = array('path' => $img_new['path'], 'attachment' => $img_new['attachment']);
            } else {
                $content_m = preg_replace($pattern, '', $content_m);
            }
        }

        return array('content' => $content_m, 'imgs_param' => $imgs_param);
    }

    private function add_lib_media_all($post_id, $imgs_param)
    {
        if (empty($imgs_param)) {
            return false;
        }

        if (! function_exists('wp_generate_attachment_metadata')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }

        $status = false;
        $has_thumb = false;
        foreach ($imgs_param as  $param) {
            $path = $param['path'];
            $attachment = $param['attachment'];
            $alt = $attachment['post_title'];

            $attachment_id = wp_insert_attachment($attachment, $path, $post_id);
            if (!is_wp_error($attachment_id)) {
                if (!has_post_thumbnail($post_id)) {
                    set_post_thumbnail($post_id, $attachment_id);
                    $attach_data = wp_generate_attachment_metadata($attachment_id, $path);
                    $has_thumb = true;
                } else {
                    $attach_data = $this->custom_generate_attachment_metadata($path);
                }

                wp_update_attachment_metadata($attachment_id, $attach_data);
                update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
                $status = true;
            } else {
                $this->log_mess('save_img: Lỗi khi thêm ảnh vào thư viện media: ' . $attachment_id->get_error_message());
            }
        }

        if ($status === false) {
            $this->log_mess('save_img: Bài viết có ảnh nhưng không add được vào thư viện media cho bài viết: ' . get_permalink($post_id));
            return false;
        }

        if ($has_thumb === false) {
            $this->log_mess('save_img: Bài viết có ảnh nhưng không tạo được thumbnail');
            return false;
        }

        return true;
    }

    private function content_img_tag($content_m)
    {
        require_once VNREWRITE_PATH . 'lib/Parsedown.php';
        $parser = new Parsedown();
        $html = $parser->text(trim($content_m));

        $pattern = '/<img([^>]*)>(\{width=(\d+(\.\d+)?)\s+height=(\d+(\.\d+)?)\})?(\r?\n)?/i';
        $replacement = function ($matches) {
            $att = $matches[1];
            $has_size = !empty($matches[2]);
            $width = $has_size ? round($matches[3], 1) : null;
            $height = $has_size ? round($matches[5], 1) : null;

            if (preg_match('/src="([^"]+)"/', $att, $srcMatch)) {
                $img_url = $srcMatch[1];

                if (!$has_size) {
                    $img_info = $this->get_img_info($img_url);
                    if ($img_info !== false) {
                        $width = $img_info['width'];
                        $height = $img_info['height'];
                        $has_size = true;
                        $att .= ' width="' . (int)$width . '" height="' . (int)$height . '"';
                    }
                } else {
                    $att .= ' width="' . (int)$width . '" height="' . (int)$height . '"';
                }
            }

            $new_img_tag = '<img' . $att . ' />';

            if (preg_match('/alt="([^"]*)"/', $att, $altMatch)) {
                $altText = esc_attr(trim($altMatch[1]));
                if (!empty($altText)) {
                    do {
                        $decodedText = html_entity_decode($altText, ENT_QUOTES, 'UTF-8');
                        if ($decodedText === $altText) {
                            break;
                        }
                        $altText = $decodedText;
                    } while (true);

                    $new_img_tag .= '<em class="cap-ai">' . esc_html($altText) . '</em>';
                }
            }

            return $new_img_tag;
        };

        $content_h = preg_replace_callback($pattern, $replacement, $html);

        return preg_replace('/<em class="cap-ai">(.*?)<\/em>\s*<em>.*?<\/em>/', '<em class="cap-ai">$1</em>', $content_h);
    }

    private function html2m($html)
    {
        try {
            $html = preg_replace_callback('/<img[^>]+>/i', function ($matches) {
                $img_tag = $matches[0];

                if (preg_match('/data-src=["\']([^"\']+)["\']/', $img_tag, $data_src_match)) {
                    $src = esc_url_raw($data_src_match[1]);
                } elseif (preg_match('/srcset=["\']([^"\']+)["\']/', $img_tag, $srcset_match)) {
                    $srcset = $srcset_match[1];
                    $srcset_parts = explode(',', $srcset);
                    $first_src = trim(explode(' ', $srcset_parts[0])[0]);
                    $src = esc_url_raw($first_src);
                } elseif (preg_match('/src\s*=\s*(["\']?)([^"\'\s>]+)/', $img_tag, $src_match)) {
                    $src = $src_match[2];
                } else {
                    return '';
                }

                if (
                    strpos($src, 'data:image/svg+xml') !== false ||
                    strpos($src, 'data:image/gif;base64') !== false ||
                    empty(trim($src)) ||
                    strpos($src, 'blank.gif') !== false ||
                    strpos($src, 'grey.gif') !== false ||
                    strpos($src, 'placeholder') !== false ||
                    $src === 'data:,' ||
                    $src === 'about:blank'
                ) {
                    return '';
                }

                $src = preg_replace('/\?.*/', '', $src);
                $src = esc_url_raw($src);

                preg_match('/alt=["\']([^"\']*)["\']/', $img_tag, $alt_match);
                $alt = isset($alt_match[1]) ? esc_attr($alt_match[1]) : '';

                return '<img src="' . $src . '" alt="' . $alt . '">';
            }, $html);

            $autoloadPath = VNREWRITE_PATH . 'lib/html-to-markdown/autoload.php';
            if (!file_exists($autoloadPath)) {
                throw new \Exception('Required HTML to Markdown converter not found');
            }
            require_once $autoloadPath;

            $config = array(
                'header_style' => 'atx',
                'strip_tags' => true,
                'strip_placeholder_links' => true,
                'hard_break' => true,
                'use_autolinks' => false,
                'preserve_line_breaks' => true,
                'preserve_spaces' => true,
            );

            $converter = new \League\HTMLToMarkdown\HtmlConverter($config);
            $converter->getEnvironment()->addConverter(new \League\HTMLToMarkdown\Converter\TableConverter());
            $content_m = $converter->convert($html);

            $content_m = preg_replace('/(?<!^)(\s*#{1,6}\s.*)/', "\n\$1", trim($content_m));
            return $content_m;
        } catch (\Exception $e) {
            error_log("HTML to Markdown conversion error: " . $e->getMessage());
            $this->log_mess('html2m: Lỗi convert to markdown');
            return '';
        }
    }

    private function convert_to_slug($string)
    {
        $string = preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $string);
        if (class_exists('Transliterator')) {
            $transliterator = Transliterator::create('Any-Latin; Latin-ASCII; [\u0100-\u7fff] remove');
            $string = $transliterator->transliterate($string);
        }
        $slug = sanitize_title($string);
        return $slug;
    }

    private function get_slug_from_url($url)
    {
        $url = trim($url);
        $url = preg_replace('/#.*/', '', $url);
        $url = preg_replace('/\?.*/', '', $url);
        $url = preg_replace('#^https?://#i', '', $url);
        $url = preg_replace('/^(?:www\.)?[^\/]+/', '', $url);
        $url = trim($url, '/');
        $parts = explode('/', $url);
        $lastPart = end($parts);
        $slug = preg_replace('/\.[^.]*$/', '', $lastPart);
        $slug = preg_replace('/[^a-zA-Z0-9\-_]/', '', $slug);
        $slug = strtolower($slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return $slug;
    }

    private function remove_post_permanently($post_id)
    {
        $attachments = get_attached_media('', $post_id);
        foreach ($attachments as $attachment) {
            wp_delete_attachment($attachment->ID, true);
        }
        wp_delete_post($post_id, true);
    }

    private function count_word($content)
    {
        $content = strip_shortcodes($content);
        $content = wp_strip_all_tags($content);
        $content = html_entity_decode($content);
        $content = trim($content);

        preg_match_all('/\p{L}+/u', $content, $matches);

        return count($matches[0]);
    }

    private function gemini_remove_api($api)
    {
        if (empty($api) || empty($this->options['gemini_api_key'])) {
            return;
        }

        $api_str = $this->options['gemini_api_key'];
        $api_array = explode('|', $api_str);

        if (($key = array_search($api, $api_array)) !== false) {
            unset($api_array[$key]);
            $this->options['gemini_api_key'] = implode('|', array_values($api_array));
            update_option('vnrewrite_option', $this->options);
        }
    }

    private function reset_rewrite_url($max_retries)
    {
        if ($max_retries === 0) {
            update_option('num_re_rewrite_url', 0);
            return;
        }

        if (!empty($max_retries) && is_numeric($max_retries) && $max_retries > 0) {
            $num_executed = (int) get_option('num_re_rewrite_url', 0);
            $max_retries = (int) $max_retries;

            if ($num_executed < $max_retries) {
                if (get_transient('reset_file_url_lock')) {
                    $this->log_mess(
                        '<span class="orange-text">Tiến trình reset đang chạy, vui lòng đợi...</span>',
                        true,
                        true,
                        0,
                        true
                    );
                } else {
                    set_transient('reset_file_url_lock', true, 60);
                    $this->log_mess(
                        '<span class="orange-text">Đã hết url! Đang reset và chạy lại các url thất bại lần thứ ' . ($num_executed + 1) . '...</span>',
                        true,
                        true,
                        0,
                        true
                    );
                    $this->reset_file_url();
                    update_option('num_re_rewrite_url', $num_executed + 1);
                    delete_transient('reset_file_url_lock');
                }
            } else {
                $this->log_mess(
                    '<span class="orange-text">Đã chạy lại các url thất bại ' . $num_executed . ' lần. Kết thúc!</span>',
                    true,
                    true,
                    0,
                    true
                );
            }
        }
    }

    private function reset_file_url()
    {
        $data_dir = VNREWRITE_DATA;

        // Kiểm tra quyền ghi của thư mục trước khi xử lý
        if (!is_writable($data_dir)) {
            $this->log_mess("Thư mục không có quyền ghi: $data_dir");
            return false;
        }

        // 1. Xóa tất cả file JSON bắt đầu bằng 'failed_'
        foreach (glob("$data_dir/failed_*.json") as $json_file) {
            if (!unlink($json_file)) {
                $this->log_mess("Không thể xóa file JSON: $json_file");
            }
        }

        // 2. Xử lý các file txt theo danh mục
        $all_files = scandir($data_dir);
        if ($all_files === false) {
            $this->log_mess("Không thể quét thư mục: $data_dir");
            return false;
        }

        // Tập hợp tất cả ID danh mục
        $category_ids = [];
        foreach ($all_files as $file) {
            if (preg_match('/^url_(?:miss_|fail_)?(\d+)\.txt$/', $file, $matches)) {
                $category_ids[] = $matches[1];
            }
        }
        $category_ids = array_unique($category_ids);

        // Xử lý từng danh mục
        foreach ($category_ids as $id) {
            $main_file = "$data_dir/url_$id.txt";
            $miss_file = "$data_dir/url_miss_$id.txt";
            $fail_file = "$data_dir/url_fail_$id.txt";

            // Bỏ qua nếu không tồn tại cả miss và fail
            if (!file_exists($miss_file) && !file_exists($fail_file)) {
                continue;
            }

            // Đọc và gộp nội dung từ miss + fail
            $merged_urls = [];

            // Đọc file miss
            if (file_exists($miss_file)) {
                $miss_urls = file($miss_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($miss_urls !== false) {
                    $merged_urls = array_merge($merged_urls, $miss_urls);
                }
            }

            // Đọc file fail
            if (file_exists($fail_file)) {
                $fail_urls = file($fail_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($fail_urls !== false) {
                    $merged_urls = array_merge($merged_urls, $fail_urls);
                }
            }

            // Loại bỏ trùng lặp và ghi vào main
            $merged_urls = array_unique($merged_urls);
            if (!empty($merged_urls)) {
                $write_result = file_put_contents(
                    $main_file,
                    implode("\n", $merged_urls)
                );
            } else {
                $this->log_mess("Bỏ qua ghi file main (nội dung rỗng): $main_file");
            }

            if ($write_result === false) {
                $this->log_mess("Lỗi ghi file main: $main_file");
                continue; // Không xóa miss/fail nếu ghi thất bại
            }

            // Xóa file miss và fail sau khi ghi thành công
            $delete_errors = [];
            if (file_exists($miss_file) && !unlink($miss_file)) {
                $delete_errors[] = "miss_$id";
            }
            if (file_exists($fail_file) && !unlink($fail_file)) {
                $delete_errors[] = "fail_$id";
            }

            if (!empty($delete_errors)) {
                $this->log_mess("Lỗi xóa file: " . implode(", ", $delete_errors));
            }
        }

        return true;
    }

    private function write_txt($items, $main_file, &$main_array, $target_file)
    {
        // Chuẩn hóa $items thành mảng
        $items = is_array($items) ? $items : [$items];

        // Đọc nội dung của $target_file vào mảng
        $target_array = file_exists($target_file) ? array_filter(array_map('trim', file($target_file))) : [];

        // Loại bỏ các phần tử trùng lặp trong $main_array và $target_array
        $main_array = array_unique($main_array);
        $target_array = array_unique($target_array);

        // Tìm các phần tử chung giữa $main_array và $target_array
        $common_items = array_intersect($main_array, $target_array);

        // Loại bỏ các phần tử chung khỏi $main_array
        $main_array = array_diff($main_array, $common_items);

        // Thêm các phần tử từ $items vào $target_array
        $target_array = array_unique(array_merge($target_array, $items));

        // Loại bỏ các phần tử từ $items khỏi $main_array
        $main_array = array_diff($main_array, $items);

        // Ghi lại nội dung vào $main_file
        $main_success = $this->update_main_file($main_file, $main_array);

        // Ghi lại nội dung vào $target_file
        $target_success = $this->append_to_target_file($target_file, $target_array);

        return $main_success && $target_success;
    }

    private function update_main_file($file, &$array)
    {
        $maxRetries = 5;
        $retryDelay = 200000; // 0.2 giây

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $fp = fopen($file, 'c+');

            if (!$fp) {
                usleep($retryDelay);
                continue;
            }

            try {
                if (flock($fp, LOCK_EX | LOCK_NB)) {
                    clearstatcache(true, $file);
                    ftruncate($fp, 0);
                    rewind($fp);
                    fwrite($fp, implode("\n", $array));
                    fflush($fp);
                    flock($fp, LOCK_UN);
                    fclose($fp);
                    return true;
                }
            } catch (Exception $e) {
                $this->log_mess('Lỗi ghi file chính: ' . $e->getMessage());
            }

            if (is_resource($fp)) {
                fclose($fp);
            }
            usleep($retryDelay);
        }

        $this->log_mess('Không thể ghi file chính sau ' . $maxRetries . ' lần thử');
        return false;
    }

    private function append_to_target_file($file, $items)
    {
        $maxRetries = 3;
        $retryDelay = 100000;
        $success = false;

        $existing_content = file_exists($file) ? array_filter(array_map('trim', file($file))) : [];
        $items = array_unique($items);

        $content = array_unique(array_merge($existing_content, $items));
        $content = implode("\n", $content) . "\n";

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $fp = fopen($file, 'a');

            if (!$fp) {
                usleep($retryDelay);
                continue;
            }

            try {
                if (flock($fp, LOCK_EX | LOCK_NB)) {
                    ftruncate($fp, 0);
                    rewind($fp);
                    fwrite($fp, $content);
                    fflush($fp);
                    flock($fp, LOCK_UN);
                    fclose($fp);
                    $success = true;
                    break;
                }
            } catch (Exception $e) {
                $this->log_mess('Lỗi ghi file phụ: ' . $e->getMessage());
            }

            if (is_resource($fp)) {
                fclose($fp);
            }
            usleep($retryDelay);
        }

        if (!$success) {
            $this->log_mess('Không thể ghi file phụ sau ' . $maxRetries . ' lần thử');
        }
        return $success;
    }

    private function remove_file_json($json_file, $main_file, $target_file)
    {
        // Đọc và kiểm tra file JSON
        if (!file_exists($json_file)) {
            //$this->log_mess("File JSON không tồn tại: $json_file");
            return false;
        }

        $json_content = file_get_contents($json_file);
        if ($json_content === false) {
            $this->log_mess("Không thể đọc file JSON: $json_file");
            return false;
        }

        // Decode JSON
        $items = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_mess("Lỗi định dạng JSON: " . json_last_error_msg());
            return false;
        }

        // Đọc nội dung hiện tại của main_file
        $main_array = [];
        if (file_exists($main_file)) {
            $main_array = file($main_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($main_array === false) {
                $this->log_mess("Không thể đọc file chính: $main_file");
                return false;
            }
        }

        // Gọi hàm write_txt để xử lý
        $success = $this->write_txt($items, $main_file, $main_array, $target_file);

        // Xóa file JSON nếu thành công
        if ($success && !unlink($json_file)) {
            $this->log_mess("Không thể xóa file JSON: $json_file");
            return false;
        }

        return $success;
    }

    private function get_unclone_cat($post_id, $target_lang = '')
    {
        if (!defined('POLYLANG_VERSION')) {
            return 0;
        }

        $default_lang = pll_default_language();
        if (!$default_lang || $target_lang == '' || $default_lang === $target_lang) {
            return 0;
        }

        $categories = get_the_category($post_id);
        if (empty($categories)) {
            return 0;
        }

        $unclone_cat_id = pll_get_term($categories[0]->term_id, $target_lang);

        return $unclone_cat_id ? $unclone_cat_id : 0;
    }

    private function get_ai_as_prompt_cate($cate_id, $field = 'vnrewrite_ai_as_cate', $field_default = 'vnrewrite_ai_as_common')
    {
        $ai_as_prompt_cate = get_term_meta($cate_id, $field, true);

        if (empty($ai_as_prompt_cate)) {
            $parent_id = $cate_id;

            while ($parent_id) {
                $parent = get_term($parent_id);
                if (!$parent || is_wp_error($parent)) {
                    break;
                }

                $ai_as_prompt_cate = get_term_meta($parent->term_id, $field, true);

                if (!empty($ai_as_prompt_cate)) {
                    break;
                }

                $parent_id = $parent->parent;
            }
        }
        return !empty($ai_as_prompt_cate) ? $ai_as_prompt_cate : $this->options[$field_default];
    }

    private function check_u()
    {
        $end_t = get_option('vnrewrite_end_time');
        if (isset($end_t) && ($end_t >= 5 || $end_t == -1)) {
            return true;
        } else {
            return false;
        }
    }

    public function max_item($type = 'urls')
    {
        return ($this->check_u() && isset($this->options['max_' . $type])) ? $this->options['max_' . $type] : 1;
    }

    public function log_mess($mess, $on_log = true, $update_mess = false, $sleep = 0, $update_time = false)
    {
        if ($update_mess) {
            set_transient('vnrewrite_mess', $mess, 30 * MINUTE_IN_SECONDS);

            if ($update_time) {
                set_transient('vnrewrite_mess_time', time(), 30 * MINUTE_IN_SECONDS);
            } else {
                $existing_time = get_transient('vnrewrite_mess_time');
                if ($existing_time === false) {
                    $existing_time = time();
                }

                set_transient('vnrewrite_mess_time', $existing_time, 30 * MINUTE_IN_SECONDS);
            }

            if ($sleep > 0) {
                sleep($sleep);
            }
        }

        if (isset($this->options['log']) && $on_log) {
            $log_file = VNREWRITE_PATH . 'log.txt';
            if (!file_exists($log_file)) {
                touch($log_file);
                chmod($log_file, 0644);
            }
            file_put_contents($log_file, '[' . current_datetime()->format('d-m-Y H:i') . '] ' . wp_strip_all_tags($mess) . "\n", FILE_APPEND | LOCK_EX);
        }
    }
}
