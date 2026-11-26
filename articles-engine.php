<?php
if (!defined('ABSPATH')) { exit; }

/**
 * مقاله‌ها (آذر ۱۴۰۵) — مکملِ seo-hubs.php:
 * ۱) «محصولاتِ مرتبط» زیرِ هر مقاله: از متای _nexv_rel ({"models":["iphone-13"],"parts":["battery"]})،
 *    وگرنه از روی عنوان (مدلِ آیفون + نوعِ قطعه) حدس زده می‌شه.
 * ۲) تولیدِ خودکارِ روزانه (ساعتِ ۱۶ به وقتِ تهران): برای هر «مدل + قطعه» که محصول داره سه مقاله‌ی کوتاه
 *    («راهنمای خرید و قیمت»، «علائم خرابی و زمان تعویض»، «اورجینال یا کپی؟») با داده‌ی واقعی؛ هر روز
 *    NEXV_ART_DAILY تا؛ تکراری ساخته نمی‌شه (_nexv_gen_key).
 */

define('NEXV_ART_DAILY', 30);

/* [علائمِ خرابی، زمانِ تعویض، اورجینال یا کپی] برای هر نوعِ قطعه */
function nexv_art_part_knowledge() {
    return [
        'battery'       => [['خاموش شدن با شارژِ ۲۰ تا ۳۰ درصد', 'سلامتِ باتری زیرِ ۸۰٪ و پیامِ «Service»', 'داغ شدنِ گوشی هنگامِ شارژ', 'باد کردنِ باتری و فاصله افتادنِ صفحه'], 'وقتی سلامتِ باتری زیرِ ۸۰٪ رسید یا گوشی ناگهان خاموش می‌شه؛ معمولاً بعد از ۵۰۰ تا ۸۰۰ چرخه‌ی شارژ', 'باتریِ اورجینال ظرفیتِ کارخانه رو داره؛ نوعِ تقویتی ظرفیتِ بیشتری داره و باتری‌های بی‌کیفیت معمولاً ظرفیتِ واقعیِ کمتری از برچسب دارن. پیامِ «Unknown Part» روی عملکرد اثری نداره.'],
        'display'       => [['ترک یا شکستگیِ شیشه', 'خط یا لکه‌ی رنگی روی تصویر', 'کار نکردنِ بخشی از لمس', 'چشمک زدن یا سیاه شدنِ صفحه'], 'بعد از ضربه یا وقتی تصویر و لمس ایراد پیدا کرد؛ اگه فقط شیشه شکسته، تعویضِ گلس فنی کافیه', 'ال سی دیِ اورجینال رنگ، روشنایی و حساسیتِ اصلی رو داره؛ Soft OLED نزدیک به اصلی و Incell ارزون‌تر ولی با رنگِ مشکیِ خاکستری‌تر و مصرفِ باتریِ بیشتره.'],
        'back-glass'    => [['ترک یا شکستگیِ شیشه‌ی پشت', 'تیزیِ لبه‌ها', 'از کار افتادنِ شارژِ وایرلس بعد از ضربه'], 'به‌محضِ ترک خوردن، تا رطوبت و گردوغبار وارد گوشی نشه', 'شیشه‌ی اصلی رنگ و بافتِ کارخانه رو داره؛ مدل‌های کپی ممکنه رنگشون کمی متفاوت باشه.'],
        'rear-camera'   => [['تار شدنِ عکس‌ها', 'لرزش یا صدای تق‌تق هنگامِ فوکوس', 'سیاه شدنِ تصویرِ دوربین', 'لکه یا هاله در عکس (معمولاً شیشه‌ی لنز)'], 'وقتی فوکوس یا لرزشگیر کار نمی‌کنه؛ اگه فقط شیشه‌ی لنز ترک خورده، تعویضِ شیشه کافیه', 'ماژولِ اورجینال کیفیتِ عکس و لرزشگیرِ اصلی رو داره؛ قطعه‌ی دست‌دوم یا کپی ممکنه فوکوسِ کندتری داشته باشه.'],
        'front-camera'  => [['تار شدنِ سلفی', 'کار نکردنِ دوربینِ جلو', 'خاموش نشدنِ صفحه هنگامِ تماس (سنسورِ مجاورت)', 'صدای ضعیف در تماسِ تصویری'], 'هنگامِ خرابیِ دوربین یا فلتِ سنسور', 'در مدل‌های فیس آیدی‌دار، دوربینِ جلو کنارِ قطعاتِ فیس آیدیه و باید با دقت تعویض بشه.'],
        'face-id'       => [['پیامِ «Face ID is not available»', 'شناسایی نشدنِ چهره', 'خرابی بعد از ضربه یا ورودِ آب'], 'هنگامِ خرابی؛ معمولاً فلتِ فیس آیدی یا دات پروجکتور آسیب دیده', 'قطعاتِ فیس آیدی با بردِ گوشی جفت (Pair) شدن و فقط تعمیرِ تخصصی جواب می‌ده.'],
        'charging-port' => [['شارژ نشدن یا قطع و وصل شدنِ کابل', 'شناسایی نشدن در کامپیوتر', 'ضعیف شدنِ صدای میکروفون پایین'], 'وقتی تمیز کردنِ سوکت جواب نداد', 'فلتِ شارژِ اورجینال شارژِ سریع و میکروفونِ سالم رو تضمین می‌کنه.'],
        'housing'       => [['خم شدنِ بدنه', 'خط و ضربه‌های عمیق', 'لق شدنِ دکمه‌ها'], 'بعد از ضربه یا برای نوسازیِ ظاهرِ گوشی', 'قابِ کپی ممکنه رنگ و ابعادش با اصلی کمی فرق داشته باشه.'],
        'motherboard'   => [['روشن نشدنِ گوشی', 'ری‌استارتِ مداوم', 'بی‌آنتن شدن یا از کار افتادنِ وای‌فای'], 'وقتی تعمیرِ آی‌سی و برد به‌صرفه نیست', 'بردِ جایگزین باید با مدل و حافظه‌ی گوشی یکی باشه؛ قفلِ iCloud رو قبل از خرید چک کنید.'],
    ];
}

function nexv_art_display_title($t) { return trim(explode('|', (string) $t)[0]); }
function nexv_art_seo_meta($id, $title, $excerpt, $kw = '') {
    update_post_meta($id, 'rank_math_title', nexv_art_display_title($title) . ' %sep% %sitename%');
    update_post_meta($id, 'rank_math_description', mb_substr(wp_strip_all_tags($excerpt), 0, 158));
    if ($kw !== '') update_post_meta($id, 'rank_math_focus_keyword', $kw);
}

/* ---------- محصولاتِ مرتبط ---------- */
function nexv_art_guess_rel($title) {
    $t = mb_strtolower(str_replace("\u{200C}", ' ', (string) $title));
    $t = strtr($t, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);
    $rel = ['models' => [], 'parts' => []];
    $models = nexv_hub_models();
    uasort($models, function ($a, $b) { return mb_strlen($b['fa']) <=> mb_strlen($a['fa']); });   /* «۱۳ پرو مکس» قبل از «۱۳» */
    foreach ($models as $ms => $m) {
        $fa = strtr($m['fa'], ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);
        if (mb_strpos($t, $fa) !== false || strpos($t, strtolower($m['en'])) !== false) { $rel['models'][] = $ms; break; }
    }
    $map = ['باتری' => 'battery', 'battery' => 'battery', 'ال سی دی' => 'display', 'صفحه نمایش' => 'display', 'lcd' => 'display', 'تاچ' => 'display', 'گلس فنی' => 'display', 'oled' => 'display',
            'شیشه پشت' => 'back-glass', 'درب پشت' => 'back-glass', 'دوربین جلو' => 'front-camera', 'سنسور مجاورت' => 'front-camera', 'دوربین' => 'rear-camera', 'لنز' => 'rear-camera',
            'فیس آیدی' => 'face-id', 'face id' => 'face-id', 'شارژ' => 'charging-port', 'سوکت' => 'charging-port', 'قاب' => 'housing', 'شاسی' => 'housing', 'برد' => 'motherboard'];
    foreach ($map as $k => $p) if (mb_strpos($t, $k) !== false && !in_array($p, $rel['parts'], true)) { $rel['parts'][] = $p; if (count($rel['parts']) >= 2) break; }
    return $rel;
}
function nexv_art_rel($aid) {
    $r = json_decode((string) get_post_meta($aid, '_nexv_rel', true), true);
    return is_array($r) && $r ? $r : nexv_art_guess_rel(get_the_title($aid));
}
function nexv_art_related_ids($aid, $limit = 8) {
    $key = 'nexv_art_rel_' . (int) $aid . '_' . (int) get_option('nexv_lp_mod', 1);
    $c = get_transient($key);
    if (is_array($c)) return $c;
    $rel = nexv_art_rel($aid);
    $models = !empty($rel['models']) ? (array) $rel['models'] : array_reverse(array_keys(nexv_hub_models()));   /* بدونِ مدل: از جدیدترین مدل‌ها */
    $ids = [];
    foreach ($models as $ms) {
        $items = nexv_hub_products($ms);
        if (!empty($rel['parts'])) { $f = []; foreach ((array) $rel['parts'] as $ps) $f = array_merge($f, nexv_hub_filter_part($items, $ps)); $items = $f; }
        foreach (array_slice($items, 0, !empty($rel['models']) ? $limit : 2) as $it) $ids[$it['id']] = true;
        if (count($ids) >= $limit) break;
    }
    $ids = array_slice(array_keys($ids), 0, $limit);
    set_transient($key, $ids, 12 * HOUR_IN_SECONDS);
    return $ids;
}
function nexv_art_price($pid) {
    $all = [];
    $b = (int) get_post_meta($pid, '_nexv_price', true); if ($b > 0) $all[] = $b;
    if (function_exists('nexv_variant_prices')) foreach ((array) nexv_variant_prices($pid) as $v) if ((int) $v > 0) $all[] = (int) $v;
    return $all ? min($all) : 0;
}
function nexv_art_related_html($aid) {
    $ids = nexv_art_related_ids($aid);
    if (!$ids) return '';
    if (function_exists('nexv_art_related_cards_html')) return nexv_art_related_cards_html($ids);   /* همون کارتِ صفحه‌ی اصلی */
    $h = '<section class="art-rel"><h2>محصولاتِ مرتبط با این مقاله</h2><div class="art-rel-grid">';
    foreach ($ids as $pid) {
        $img = function_exists('nexv_gallery_urls') ? (array) nexv_gallery_urls($pid) : [];
        $img = $img ? $img[0] : get_the_post_thumbnail_url($pid, 'medium');
        $pr = nexv_art_price($pid);
        $h .= '<a class="art-rel-card" href="' . esc_url(get_permalink($pid)) . '">'
            . ($img ? '<img src="' . esc_url($img) . '" alt="" loading="lazy">' : '<span class="ph"></span>')
            . '<b>' . esc_html(nexv_art_display_title(get_the_title($pid))) . '</b>'
            . '<small>' . esc_html($pr > 0 ? nexv_hub_fa_digits(number_format($pr)) . ' تومان' : 'برای قیمت تماس بگیرید') . '</small>'
            . '<i>مشاهده و خرید ←</i></a>';
    }
    return $h . '</div></section>';
}
function nexv_art_more_html($aid) {
    $rel = nexv_art_rel($aid);
    $args = ['post_type' => 'nexv_article', 'post_status' => 'publish', 'numberposts' => 6, 'post__not_in' => [(int) $aid], 'orderby' => 'rand', 'fields' => 'ids'];
    if (!empty($rel['parts'])) $args['meta_query'] = [['key' => '_nexv_rel', 'value' => '"' . $rel['parts'][0] . '"', 'compare' => 'LIKE']];
    $ids = get_posts($args);
    if (count($ids) < 3) { unset($args['meta_query']); $ids = get_posts($args); }
    if (!$ids) return '';
    $h = '<section class="art-more"><h2>مقاله‌های مرتبط</h2><ul>';
    foreach ($ids as $id) $h .= '<li><a href="' . esc_url(get_permalink($id)) . '">' . esc_html(nexv_art_display_title(get_the_title($id))) . '</a></li>';
    return $h . '</ul></section>';
}
function nexv_art_css() {
    return '.art-rel,.art-more{max-width:720px;margin:22px auto 0}.art-rel h2,.art-more h2{font-size:17px;margin:0 0 12px}'
        . '.art-rel-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}'
        . '.art-rel-card{display:flex;flex-direction:column;gap:4px;background:#fff;border-radius:16px;padding:10px;text-decoration:none;color:#201f2b;box-shadow:0 8px 22px rgba(0,0,0,.06)}'
        . '.art-rel-card img,.art-rel-card .ph{width:100%;aspect-ratio:1;object-fit:contain;border-radius:12px;background:#f3f2f7;display:block}'
        . '.art-rel-card b{font-size:12.5px;line-height:1.7}.art-rel-card small{font-size:12px;color:#6a4dff;font-weight:700}.art-rel-card i{font-style:normal;font-size:11.5px;color:#7a7788}'
        . '.art-more ul{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:8px}.art-more a{display:block;background:#fff;border-radius:12px;padding:10px 14px;text-decoration:none;color:#201f2b;font-size:13.5px}'
        . 'html[data-theme="dark"] .art-rel-card,html[data-theme="dark"] .art-more a{background:#15141c;color:#ececf4}html[data-theme="dark"] .art-rel-card img,html[data-theme="dark"] .art-rel-card .ph{background:#1f1d28}';
}

/* ---------- تولیدِ خودکارِ روزانه ---------- */
function nexv_art_combos() {
    $out = [];
    foreach (nexv_hub_models() as $ms => $m) {
        $items = nexv_hub_products($ms);
        if (!$items) continue;
        foreach (array_keys(nexv_hub_parts()) as $ps) if (nexv_hub_filter_part($items, $ps)) $out[] = [$ms, $ps];
    }
    /* مدل‌های پرفروش‌تر اول (۱۳، ۱۱، ۱۲، ۱۴ ...) */
    $prio = ['iphone-13', 'iphone-11', 'iphone-12', 'iphone-13-pro-max', 'iphone-14', 'iphone-14-pro-max', 'iphone-12-pro-max', 'iphone-15', 'iphone-13-pro', 'iphone-15-pro-max'];
    usort($out, function ($a, $b) use ($prio) {
        $pa = array_search($a[0], $prio, true); $pb = array_search($b[0], $prio, true);
        return [$pa === false ? 99 : $pa, $a[0], $a[1]] <=> [$pb === false ? 99 : $pb, $b[0], $b[1]];
    });
    return $out;
}
function nexv_art_generate_one($ms, $ps, $topic) {
    $genKey = $ms . ':' . $ps . ':' . $topic;
    global $wpdb;
    if ($wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_nexv_gen_key' AND meta_value = %s LIMIT 1", $genKey))) return false;
    $models = nexv_hub_models(); $parts = nexv_hub_parts();
    if (!isset($models[$ms], $parts[$ps])) return false;
    $m = $models[$ms]; $p = $parts[$ps];
    $items = nexv_hub_filter_part(nexv_hub_products($ms), $ps);
    if (!$items) return false;
    $pfa = trim(preg_replace('/\s*\(.+\)\s*/u', '', $p['fa']));
    $full = $pfa . ' ' . $m['fa'];
    $K = nexv_art_part_knowledge()[$ps] ?? [[], '', ''];
    $mins = array_filter(array_column($items, 'min'));
    $min = $mins ? min($mins) : 0;
    $priceTxt = $min ? 'قیمتِ ' . $full . ' از حدود <b>' . nexv_hub_fa_digits(number_format($min)) . ' تومان</b> شروع می‌شه' : 'قیمتِ این قطعه تلفنی اعلام می‌شه';
    $spec = $m['fa'] . ' (' . $m['en'] . ') سالِ ' . nexv_hub_fa_digits($m['year']) . ' معرفی شد؛ صفحه‌نمایشِ ' . $m['display'] . '، پردازنده‌ی ' . $m['chip'] . ' و باتریِ حدود ' . nexv_hub_fa_digits(number_format($m['battery'])) . ' میلی‌آمپرساعت.';
    $list = '<ul>';
    foreach (array_slice($items, 0, 6) as $it) $list .= '<li><a href="' . esc_url($it['url']) . '">' . esc_html(nexv_art_display_title($it['title'])) . '</a>' . ($it['min'] ? ' — از ' . nexv_hub_fa_digits(number_format($it['min'])) . ' تومان' : '') . '</li>';
    $list .= '</ul>';
    $links = '<p>📌 <a href="' . esc_url(nexv_hub_url($ms, $ps)) . '">لیست قیمت ' . esc_html($full) . '</a> — <a href="' . esc_url(nexv_hub_url($ms)) . '">قیمت همه‌ی قطعات ' . esc_html($m['fa']) . '</a></p>';
    $li = function ($a) { return $a ? '<ul><li>' . implode('</li><li>', array_map('esc_html', $a)) . '</li></ul>' : ''; };
    $v = abs(crc32($genKey)) % 3;
    if ($topic === 'guide') {
        $title = 'راهنمای خرید و قیمت ' . $full . ' | ' . $m['en'] . ' ' . $p['en'] . ' Buying Guide';
        $slug = $ms . '-' . $ps . '-buying-guide';
        $intro = ["اگه می‌خواید <b>{$full}</b> بخرید، این راهنمای کوتاه کمک می‌کنه نوعِ مناسب رو با قیمتِ درست انتخاب کنید.",
                  "خریدِ <b>{$full}</b> با دونستنِ چند نکته‌ی ساده خیلی مطمئن‌تر می‌شه.",
                  "در این مطلب نکاتِ مهمِ خریدِ <b>{$pfa}</b> مخصوصِ <b>{$m['fa']}</b> رو خلاصه کردیم."][$v];
        $body = '<p>' . $intro . '</p><p>' . esc_html($spec) . ' ' . esc_html($pfa) . 'ِ این مدل مخصوصِ خودشه و با مدل‌های دیگه (حتی هم‌اندازه) جابه‌جا نمی‌شه.</p>'
            . '<h2>انواع ' . esc_html($full) . '</h2><p>' . esc_html($K[2]) . '</p>' . $list
            . '<h2>قیمت</h2><p>' . $priceTxt . '؛ قیمتِ نهایی به نوع و کیفیتِ قطعه بستگی داره.</p>' . $links;
        $excerpt = 'راهنمای خرید ' . $full . ': انواع قطعه، تفاوت کیفیت‌ها و قیمت روز.';
        $kw = 'قیمت ' . $full;
    } elseif ($topic === 'symptoms') {
        $title = 'علائم خرابی ' . $full . ' و زمان تعویض | ' . $m['en'] . ' ' . $p['en'] . ' Problems';
        $slug = $ms . '-' . $ps . '-symptoms';
        $intro = ["چطور بفهمیم <b>{$full}</b> خراب شده؟ این نشانه‌ها رو بررسی کنید.",
                  "<b>{$pfa}</b> در <b>{$m['fa']}</b> هم مثلِ هر قطعه‌ی دیگه ممکنه خراب بشه؛ با شناختِ علائم، به‌موقع اقدام کنید.",
                  "اگه به خرابیِ <b>{$full}</b> شک دارید، این مطلبِ کوتاه رو بخونید."][$v];
        $body = '<p>' . $intro . '</p><h2>علائم خرابی ' . esc_html($full) . '</h2>' . $li($K[0])
            . '<h2>زمان تعویض</h2><p>' . esc_html($K[1]) . '.</p>'
            . '<h2>قطعه‌ی جایگزین</h2>' . $list . '<p>' . $priceTxt . '.</p>' . $links;
        $excerpt = 'علائم خرابی ' . $full . '، زمان مناسب تعویض و قیمت قطعه‌ی جایگزین.';
        $kw = 'خرابی ' . $full;
    } else {
        $title = $full . ' اورجینال یا کپی؟ مقایسه و قیمت | ' . $m['en'] . ' ' . $p['en'] . ' Original vs Copy';
        $slug = $ms . '-' . $ps . '-original-vs-copy';
        $body = '<p>یکی از سؤال‌های همیشگی موقعِ تعمیرِ ' . esc_html($m['fa']) . ' اینه که <b>' . esc_html($pfa) . 'ِ اورجینال بخریم یا کپی؟</b></p>'
            . '<h2>تفاوت‌ها</h2><p>' . esc_html($K[2]) . '</p>'
            . '<h2>کدوم رو انتخاب کنیم؟</h2><p>اگه گوشی رو طولانی‌مدت نگه می‌دارید یا قصدِ فروش دارید، اورجینال ارزشِ گوشی رو حفظ می‌کنه؛ برای بودجه‌ی محدود، گزینه‌های باکیفیتِ غیراصلی هم وجود داره.</p>'
            . $list . '<p>' . $priceTxt . '.</p>' . $links;
        $excerpt = 'مقایسه‌ی ' . $full . ' اورجینال و کپی: کیفیت، دوام و قیمت.';
        $kw = $full . ' اورجینال';
    }
    $slug = sanitize_title($slug);
    if (get_page_by_path($slug, OBJECT, 'nexv_article')) return false;
    $id = wp_insert_post(['post_type' => 'nexv_article', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_excerpt' => $excerpt, 'post_content' => $body]);
    if (!$id || is_wp_error($id)) return false;
    update_post_meta($id, '_nexv_gen_key', $genKey);
    update_post_meta($id, '_nexv_rel', wp_json_encode(['models' => [$ms], 'parts' => [$ps]]));
    nexv_art_seo_meta($id, $title, $excerpt, $kw);
    return true;
}
function nexv_art_run_batch($n = NEXV_ART_DAILY) {
    if (get_transient('nexv_art_lock')) return 0;
    set_transient('nexv_art_lock', 1, 900);
    @set_time_limit(600);
    $topics = ['guide', 'symptoms', 'original'];
    $combos = nexv_art_combos();
    $total = count($combos) * 3;
    $cursor = (int) get_option('nexv_art_cursor', 0);
    $made = 0; $tries = 0;
    while ($made < $n && $cursor < $total && $tries < $n * 6) {
        $c = $combos[intdiv($cursor, 3)];
        if (nexv_art_generate_one($c[0], $c[1], $topics[$cursor % 3])) $made++;
        $cursor++; $tries++;
    }
    update_option('nexv_art_cursor', $cursor, false);
    update_option('nexv_art_last', ['date' => (new DateTime('now', new DateTimeZone('Asia/Tehran')))->format('Y-m-d'), 'made' => $made, 'cursor' => $cursor, 'total' => $total], false);
    if ($made && function_exists('nexv_pub_cache_clear')) nexv_pub_cache_clear(['articles']);
    delete_transient('nexv_art_lock');
    return $made;
}
function nexv_art_next_16() {
    $d = new DateTime('today 16:00', new DateTimeZone('Asia/Tehran'));
    if ($d->getTimestamp() <= time()) $d->modify('+1 day');
    return $d->getTimestamp();
}
add_action('init', function () { if (!wp_next_scheduled('nexv_art_daily')) wp_schedule_event(nexv_art_next_16(), 'daily', 'nexv_art_daily'); });
add_action('nexv_art_daily', function () { nexv_art_run_batch(); });
add_action('shutdown', function () {
    $now = new DateTime('now', new DateTimeZone('Asia/Tehran'));
    /* شروعِ اولیه: یک‌بار ۳۰ مقاله همون اول (منتظرِ ساعتِ ۱۶ نمی‌مونه) */
    $boot = !get_option('nexv_art_boot_v1');
    if (!$boot && (int) $now->format('G') < 16) return;
    $last = (array) get_option('nexv_art_last', []);
    if ((!$boot && ($last['date'] ?? '') === $now->format('Y-m-d')) || get_transient('nexv_art_lock') || get_transient('nexv_art_tried')) return;
    set_transient('nexv_art_tried', 1, 600);
    if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
    elseif (function_exists('litespeed_finish_request')) @litespeed_finish_request();
    elseif (!is_admin()) return;
    @ignore_user_abort(true);
    if ($boot) update_option('nexv_art_boot_v1', time(), false);
    nexv_art_run_batch();
}, 998);

/* =========================================================================
 * کارتِ محصول دقیقاً مثلِ صفحه‌ی اصلی (برای «محصولاتِ مرتبط» زیرِ مقاله):
 * CSS کارت‌ها (همه‌ی قانون‌های .card/.grid/... حتی داخلِ @media و حالتِ شب) و تابع‌های JS
 * (cardHtml، badgesHtml، icon، fmt و ...) مستقیم از site.html برداشته می‌شن؛ پس هر تغییری
 * در کارتِ صفحه‌ی اصلی، خودکار اینجا هم اعمال می‌شه. داده‌ی محصول‌ها هم همون لیستِ کش‌شده‌ی API.
 * ========================================================================= */
function nexv_css_pick($css, $re) {
    $out = ''; $i = 0; $n = strlen($css);
    while ($i < $n) {
        $o = strpos($css, '{', $i); if ($o === false) break;
        $sel = trim(substr($css, $i, $o - $i));
        $sel = trim(preg_replace('#/\*.*?\*/#s', '', $sel));
        $d = 1; $j = $o + 1;
        while ($j < $n && $d > 0) { if ($css[$j] === '{') $d++; elseif ($css[$j] === '}') $d--; $j++; }
        $body = substr($css, $o + 1, $j - $o - 2);
        if (stripos($sel, '@media') === 0 || stripos($sel, '@supports') === 0) {
            $inner = nexv_css_pick($body, $re);
            if ($inner !== '') $out .= $sel . '{' . $inner . "}\n";
        } elseif ($sel !== '' && $sel[0] !== '@' && preg_match($re, $sel)) {
            $out .= $sel . '{' . $body . "}\n";
        }
        $i = $j;
    }
    return $out;
}
function nexv_js_pick($h, $name) {
    foreach (["\nfunction $name(", "\nconst $name ="] as $k) {
        $a = strpos($h, $k); if ($a === false) continue;
        $a++;
        if (strpos($k, 'function') === 1) {
            $o = strpos($h, '{', $a); $d = 1; $j = $o + 1; $n = strlen($h);
            while ($j < $n && $d > 0) { if ($h[$j] === '{') $d++; elseif ($h[$j] === '}') $d--; $j++; }
            return substr($h, $a, $j - $a) . "\n";
        }
        $eq = strpos($h, '=', $a); $rest = ltrim(substr($h, $eq + 1));
        if ($rest !== '' && $rest[0] === '{') {
            $o = strpos($h, '{', $eq); $d = 1; $j = $o + 1; $n = strlen($h);
            while ($j < $n && $d > 0) { if ($h[$j] === '{') $d++; elseif ($h[$j] === '}') $d--; $j++; }
            return substr($h, $a, $j - $a) . ";\n";
        }
        $e = strpos($h, ";\n", $a);
        return substr($h, $a, $e - $a + 1) . "\n";
    }
    return '';
}
function nexv_home_card_kit() {
    static $kit = null;
    if ($kit !== null) return $kit;
    $f = get_template_directory() . '/site.html';
    $key = 'nexv_card_kit_' . (int) @filemtime($f) . '_' . (int) @filemtime(__FILE__);
    $kit = get_transient($key);
    if (is_array($kit)) return $kit;
    $h = (string) @file_get_contents($f);
    $css = '';
    if (preg_match_all('#<style[^>]*>(.*?)</style>#s', $h, $m)) {
        $css = nexv_css_pick(implode("\n", $m[1]), '/\.(card|grid\b|price|add-btn|cv-row|cv-chip|cv-more|spec\b|reveal|nexv-call-price)/');
    }
    $js = '';
    foreach (['escHtml', 'badgesHtml', 'ICONS', 'icon', 'fmt', 'cardVariantsHtml', 'minVariantPrice', 'priceRange', 'brandCardColor', 'CONTACT_PHONE', 'CALL_PRICE_HTML', 'cardHtml'] as $nm) $js .= nexv_js_pick($h, $nm);
    $kit = ['css' => $css, 'js' => $js];
    set_transient($key, $kit, WEEK_IN_SECONDS);
    return $kit;
}
/* داده‌ی محصول‌ها با همون شکلِ API (از کشِ لیستِ محصولات)؛ اگه کش نبود، از متاها */
function nexv_art_items($ids) {
    if (!$ids) return [];
    $all = get_transient('nexv_products_cache_v2');
    if (!is_array($all)) {
        $f = dirname(get_template_directory(), 2) . '/uploads/nexv-data/cache/products.json';
        $j = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        $all = is_array($j) ? (array) ($j['products'] ?? []) : [];
    }
    $by = [];
    foreach ($all as $p) if (isset($p['id'])) $by[(string) $p['id']] = $p;
    $out = [];
    foreach ($ids as $id) {
        if (isset($by[(string) $id])) { $out[] = $by[(string) $id]; continue; }
        $g = function_exists('nexv_gallery_urls') ? array_values(array_filter((array) nexv_gallery_urls($id))) : [];
        $img = $g ? $g[0] : get_the_post_thumbnail_url($id, 'medium');
        $out[] = ['id' => (string) $id, 'title' => get_the_title($id), 'spec' => (string) get_post_meta($id, '_nexv_spec', true),
            'price' => (int) get_post_meta($id, '_nexv_price', true), 'color' => (string) get_post_meta($id, '_nexv_color', true) ?: '#6a4dff',
            'ic' => get_post_meta($id, '_nexv_icon', true) ?: 'disc', 'badges' => [], 'url' => get_permalink($id),
            'image' => $img ?: null, 'thumb' => $img ?: null, 'variantPrices' => (object) [], 'variantGroups' => [], 'colors' => [], 'storages' => []];
    }
    return $out;
}
function nexv_art_related_cards_html($ids, $title = 'محصولاتِ مرتبط با این مقاله') {
    $items = nexv_art_items($ids);
    if (!$items) return '';
    $kit = nexv_home_card_kit();
    $phone = (string) get_theme_mod('nexv_contact_phone', '');
    /* همون ریستِ پایه‌ی صفحه‌ی اصلی (فقط داخلِ این بخش) تا کارت‌ها دقیقاً یکی بشن */
    $reset = '.art-rel,.art-rel *,.art-rel *::before,.art-rel *::after{box-sizing:border-box;margin:0;padding:0}.art-rel a{color:inherit;text-decoration:none}'
        . '.art-rel button{font-family:inherit;cursor:pointer;border:none}.art-rel img,.art-rel svg{display:block}.art-rel{font-family:Vazirmatn,Tahoma,sans-serif}';
    return '<style>' . $reset . $kit['css'] . '.art-rel{max-width:760px;margin:26px auto 0}.art-rel h2{font-size:17px;margin:0 2px 12px !important;font-weight:800}'
        . '.art-rel .grid{grid-template-columns:repeat(auto-fill,minmax(200px,1fr))}@media (max-width:520px){.art-rel .grid{grid-template-columns:repeat(2,1fr)}}.art-rel .card{cursor:pointer}</style>'
        . '<section class="art-rel"><h2>' . esc_html($title) . '</h2><div class="grid" id="artRelGrid"></div></section>'
        . '<script>(function(){window.NEXV_CONTACT_PHONE=' . wp_json_encode($phone) . ';' . $kit['js']
        . 'var items=' . wp_json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
        . 'items=items.map(function(p){p=Object.assign({badges:[],variantPrices:{},variantGroups:[],colors:[],storages:[]},p);p.price=Number(p.price)||0;'
        . 'if(typeof brandCardColor==="function")p.color=brandCardColor(p.color);if(!p.badges.length&&p.badge)p.badges=[p.badge];return p;});'
        . 'var g=document.getElementById("artRelGrid");g.innerHTML=items.map(cardHtml).join("");'
        . 'g.addEventListener("click",function(e){if(e.target.closest("a[href^=\'tel:\']"))return;var c=e.target.closest(".card");if(c&&c.dataset.url)location.href=c.dataset.url;});'
        . '})();</script>';
}
