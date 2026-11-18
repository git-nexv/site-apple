<?php
if (!defined('ABSPATH')) { exit; }

/**
 * صفحه‌های سئوی «لیست قیمت قطعات» برای مدل‌های پرفروش آیفون (۱۱ تا ۱۶):
 *   /parts-price/iphone-13/            «لیست قیمت قطعات آیفون ۱۳» — همه‌ی قطعاتِ همون مدل با قیمت
 *   /parts-price/iphone-13/battery/    «قیمت باتری آیفون ۱۳» — فقط همون نوعِ قطعه + مشخصات + سؤالات متداول
 * هدف: برای جستجوهای پرتکرار («قیمت باتری آیفون ۱۳»، «قیمت ال سی دی آیفون ۱۳»، «لیست قیمت قطعات
 * آیفون ۱۳») صفحه‌ی اختصاصی با محتوای واقعی (محصولات، قیمت، مشخصاتِ مدل، FAQ و اسکیما) داشته باشیم.
 * صفحه فقط وقتی ساخته می‌شه که حداقل یک محصولِ مرتبط وجود داشته باشه (صفحه‌ی خالی = ۴۰۴).
 * سایت‌مپِ جدا: /parts-price-sitemap.xml که به فهرستِ اصلیِ Rank Math اضافه می‌شه.
 */

/* مدل‌ها: اسلاگ => [نامِ فارسی (با ارقامِ فارسی), نامِ انگلیسی, مسیرِ دسته, مشخصات] */
function nexv_hub_models() {
    $m = function ($fa, $en, $series, $catName, $display, $res, $hz, $battery, $chip, $year, $extra = '') {
        return ['fa' => $fa, 'en' => $en, 'cat' => "آیفون > قطعات آیفون > سری $series > $catName",
                'display' => $display, 'res' => $res, 'hz' => $hz, 'battery' => $battery, 'chip' => $chip, 'year' => $year, 'extra' => $extra];
    };
    return [
        'iphone-11'          => $m('آیفون ۱۱', 'iPhone 11', 11, 'آیفون 11', '۶.۱ اینچ LCD (Liquid Retina)', '1792×828', 60, 3110, 'A13 Bionic', 2019),
        'iphone-11-pro'      => $m('آیفون ۱۱ پرو', 'iPhone 11 Pro', 11, 'آیفون 11 پرو', '۵.۸ اینچ OLED (Super Retina XDR)', '2436×1125', 60, 3046, 'A13 Bionic', 2019),
        'iphone-11-pro-max'  => $m('آیفون ۱۱ پرو مکس', 'iPhone 11 Pro Max', 11, 'آیفون 11 پرو مکس', '۶.۵ اینچ OLED (Super Retina XDR)', '2688×1242', 60, 3969, 'A13 Bionic', 2019),
        'iphone-12-mini'     => $m('آیفون ۱۲ مینی', 'iPhone 12 mini', 12, 'آیفون 12 مینی', '۵.۴ اینچ OLED (Super Retina XDR)', '2340×1080', 60, 2227, 'A14 Bionic', 2020),
        'iphone-12'          => $m('آیفون ۱۲', 'iPhone 12', 12, 'آیفون 12', '۶.۱ اینچ OLED (Super Retina XDR)', '2532×1170', 60, 2815, 'A14 Bionic', 2020),
        'iphone-12-pro'      => $m('آیفون ۱۲ پرو', 'iPhone 12 Pro', 12, 'آیفون 12 پرو', '۶.۱ اینچ OLED (Super Retina XDR)', '2532×1170', 60, 2815, 'A14 Bionic', 2020),
        'iphone-12-pro-max'  => $m('آیفون ۱۲ پرو مکس', 'iPhone 12 Pro Max', 12, 'آیفون 12 پرو مکس', '۶.۷ اینچ OLED (Super Retina XDR)', '2778×1284', 60, 3687, 'A14 Bionic', 2020),
        'iphone-13-mini'     => $m('آیفون ۱۳ مینی', 'iPhone 13 mini', 13, 'آیفون 13 مینی', '۵.۴ اینچ OLED (Super Retina XDR)', '2340×1080', 60, 2406, 'A15 Bionic', 2021),
        'iphone-13'          => $m('آیفون ۱۳', 'iPhone 13', 13, 'آیفون 13', '۶.۱ اینچ OLED (Super Retina XDR)', '2532×1170', 60, 3227, 'A15 Bionic', 2021),
        'iphone-13-pro'      => $m('آیفون ۱۳ پرو', 'iPhone 13 Pro', 13, 'آیفون 13 پرو', '۶.۱ اینچ OLED با ProMotion', '2532×1170', 120, 3095, 'A15 Bionic', 2021),
        'iphone-13-pro-max'  => $m('آیفون ۱۳ پرو مکس', 'iPhone 13 Pro Max', 13, 'آیفون 13 پرو مکس', '۶.۷ اینچ OLED با ProMotion', '2778×1284', 120, 4352, 'A15 Bionic', 2021),
        'iphone-14'          => $m('آیفون ۱۴', 'iPhone 14', 14, 'آیفون 14', '۶.۱ اینچ OLED (Super Retina XDR)', '2532×1170', 60, 3279, 'A15 Bionic', 2022),
        'iphone-14-plus'     => $m('آیفون ۱۴ پلاس', 'iPhone 14 Plus', 14, 'آیفون 14 پلاس', '۶.۷ اینچ OLED (Super Retina XDR)', '2778×1284', 60, 4325, 'A15 Bionic', 2022),
        'iphone-14-pro'      => $m('آیفون ۱۴ پرو', 'iPhone 14 Pro', 14, 'آیفون 14 پرو', '۶.۱ اینچ OLED با ProMotion و Dynamic Island', '2556×1179', 120, 3200, 'A16 Bionic', 2022),
        'iphone-14-pro-max'  => $m('آیفون ۱۴ پرو مکس', 'iPhone 14 Pro Max', 14, 'آیفون 14 پرو مکس', '۶.۷ اینچ OLED با ProMotion و Dynamic Island', '2796×1290', 120, 4323, 'A16 Bionic', 2022),
        'iphone-15'          => $m('آیفون ۱۵', 'iPhone 15', 15, 'آیفون 15', '۶.۱ اینچ OLED با Dynamic Island', '2556×1179', 60, 3349, 'A16 Bionic', 2023, 'درگاه USB-C'),
        'iphone-15-plus'     => $m('آیفون ۱۵ پلاس', 'iPhone 15 Plus', 15, 'آیفون 15 پلاس', '۶.۷ اینچ OLED با Dynamic Island', '2796×1290', 60, 4383, 'A16 Bionic', 2023, 'درگاه USB-C'),
        'iphone-15-pro'      => $m('آیفون ۱۵ پرو', 'iPhone 15 Pro', 15, 'آیفون 15 پرو', '۶.۱ اینچ OLED با ProMotion و Dynamic Island', '2556×1179', 120, 3274, 'A17 Pro', 2023, 'بدنه‌ی تیتانیومی و درگاه USB-C'),
        'iphone-15-pro-max'  => $m('آیفون ۱۵ پرو مکس', 'iPhone 15 Pro Max', 15, 'آیفون 15 پرو مکس', '۶.۷ اینچ OLED با ProMotion و Dynamic Island', '2796×1290', 120, 4422, 'A17 Pro', 2023, 'بدنه‌ی تیتانیومی و درگاه USB-C'),
        'iphone-16'          => $m('آیفون ۱۶', 'iPhone 16', 16, 'آیفون 16', '۶.۱ اینچ OLED با Dynamic Island', '2556×1179', 60, 3561, 'A18', 2024, 'دکمه‌ی Camera Control'),
        'iphone-16-plus'     => $m('آیفون ۱۶ پلاس', 'iPhone 16 Plus', 16, 'آیفون 16 پلاس', '۶.۷ اینچ OLED با Dynamic Island', '2796×1290', 60, 4674, 'A18', 2024, 'دکمه‌ی Camera Control'),
        'iphone-16-pro'      => $m('آیفون ۱۶ پرو', 'iPhone 16 Pro', 16, 'آیفون 16 پرو', '۶.۳ اینچ OLED با ProMotion و Dynamic Island', '2622×1206', 120, 3582, 'A18 Pro', 2024, 'بدنه‌ی تیتانیومی و دکمه‌ی Camera Control'),
        'iphone-16-pro-max'  => $m('آیفون ۱۶ پرو مکس', 'iPhone 16 Pro Max', 16, 'آیفون 16 پرو مکس', '۶.۹ اینچ OLED با ProMotion و Dynamic Island', '2868×1320', 120, 4685, 'A18 Pro', 2024, 'بدنه‌ی تیتانیومی و دکمه‌ی Camera Control'),
    ];
}

/* نوعِ قطعه‌ها: اسلاگ => [نامِ فارسی, کلمه‌های بخشِ انگلیسیِ عنوانِ محصول] */
function nexv_hub_parts() {
    return [
        'battery'       => ['fa' => 'باتری',            'en' => 'Battery',          'match' => ['battery']],
        'display'       => ['fa' => 'ال سی دی (صفحه نمایش)', 'en' => 'Display (LCD)', 'match' => ['lcd', 'display', 'screen', 'glass replacement']],
        'back-glass'    => ['fa' => 'شیشه پشت (درب پشت)', 'en' => 'Back Glass',     'match' => ['back glass']],
        'rear-camera'   => ['fa' => 'دوربین پشت',       'en' => 'Rear Camera',      'match' => ['rear camera', 'camera lens']],
        'front-camera'  => ['fa' => 'دوربین جلو',       'en' => 'Front Camera',     'match' => ['front camera', 'proximity']],
        'face-id'       => ['fa' => 'فیس آیدی',         'en' => 'Face ID',          'match' => ['face id']],
        'charging-port' => ['fa' => 'سوکت و فلت شارژ',  'en' => 'Charging Port',    'match' => ['charging port', 'wireless charging']],
        'housing'       => ['fa' => 'قاب و شاسی',       'en' => 'Housing',          'match' => ['housing', 'frame', 'screws', 'shield']],
        'motherboard'   => ['fa' => 'برد (مادربرد)',    'en' => 'Motherboard',      'match' => ['motherboard', 'logic board']],
    ];
}

function nexv_hub_fa_digits($s) { return strtr((string) $s, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹', ','=>'٬']); }

/* محصولاتِ یک مدل (همه‌ی زیرشاخه‌های دسته‌اش)، با کش */
function nexv_hub_products($modelSlug) {
    $models = nexv_hub_models();
    if (!isset($models[$modelSlug])) return [];
    $key = 'nexv_hub_p_' . md5($modelSlug);
    $cached = get_transient($key);
    if (is_array($cached)) return $cached;
    $term = function_exists('nexv_resolve_cat_path') ? nexv_resolve_cat_path($models[$modelSlug]['cat']) : 0;
    $out = [];
    if ($term) {
        $ids = get_posts(['post_type' => 'nexv_product', 'post_status' => 'publish', 'numberposts' => 300, 'fields' => 'ids',
            'orderby' => 'title', 'order' => 'ASC',
            'tax_query' => [['taxonomy' => 'nexv_cat', 'terms' => (int) $term, 'include_children' => true]]]);
        foreach ($ids as $id) {
            $title = get_the_title($id);
            $en = strpos($title, '|') !== false ? strtolower(trim(substr($title, strrpos($title, '|') + 1))) : strtolower($title);
            $price = (int) get_post_meta($id, '_nexv_price', true);
            $vp = function_exists('nexv_variant_prices') ? array_values(nexv_variant_prices($id)) : [];
            $all = array_filter(array_merge($vp, $price > 0 ? [$price] : []));
            $out[] = ['id' => $id, 'title' => $title, 'en' => $en, 'url' => get_permalink($id),
                      'min' => $all ? min($all) : 0, 'max' => $all ? max($all) : 0,
                      'updated' => (int) get_post_meta($id, '_nexv_price_updated', true),
                      'short' => (string) get_post_meta($id, '_nexv_short_desc', true)];
        }
    }
    set_transient($key, $out, 6 * HOUR_IN_SECONDS);
    return $out;
}
function nexv_hub_flush() { foreach (array_keys(nexv_hub_models()) as $s) delete_transient('nexv_hub_p_' . md5($s)); delete_transient('nexv_hub_sitemap_v1'); }
add_action('save_post_nexv_product', 'nexv_hub_flush');

function nexv_hub_filter_part($items, $partSlug) {
    $parts = nexv_hub_parts();
    if (!isset($parts[$partSlug])) return [];
    return array_values(array_filter($items, function ($p) use ($parts, $partSlug) {
        foreach ($parts[$partSlug]['match'] as $w) if (strpos($p['en'], $w) !== false) return true;
        return false;
    }));
}

/* ---------- مسیرها ---------- */
add_action('init', function () {
    add_rewrite_tag('%nexv_hub_model%', '([a-z0-9\-]+)');
    add_rewrite_tag('%nexv_hub_part%', '([a-z0-9\-]+)');
    add_rewrite_rule('^parts-price/([a-z0-9\-]+)/([a-z0-9\-]+)/?$', 'index.php?nexv_hub_model=$matches[1]&nexv_hub_part=$matches[2]', 'top');
    add_rewrite_rule('^parts-price/([a-z0-9\-]+)/?$', 'index.php?nexv_hub_model=$matches[1]', 'top');
    if (get_option('nexv_hub_rewrite_v1') !== '1') { flush_rewrite_rules(false); update_option('nexv_hub_rewrite_v1', '1', false); }
}, 20);
add_filter('query_vars', function ($v) { $v[] = 'nexv_hub_model'; $v[] = 'nexv_hub_part'; return $v; });

function nexv_hub_url($model, $part = '') { return home_url('/parts-price/' . $model . '/' . ($part !== '' ? $part . '/' : '')); }

/* ---------- محتوای مخصوصِ هر نوعِ قطعه (متن + سؤالات متداول) ---------- */
function nexv_hub_part_copy($partSlug, $m) {
    $name = $m['fa'];
    $bat = nexv_hub_fa_digits(number_format($m['battery']));
    $hz = $m['hz'] >= 120 ? 'با نرخ نوسازیِ ۱۲۰ هرتز (ProMotion)' : 'با نرخ نوسازیِ ۶۰ هرتز';
    switch ($partSlug) {
        case 'battery': return [
            'intro' => "باتریِ اصلیِ {$name} ظرفیتی حدود {$bat} میلی‌آمپرساعت داره. وقتی «سلامت باتری» (Battery Health) زیرِ ۸۰٪ بیاد، گوشی زود خاموش بشه یا موقعِ شارژ داغ کنه، وقتِ تعویضه. در این صفحه انواعِ باتریِ {$name} (اصلی، تقویتی) و چسبِ زیرِ باتری رو با قیمتِ روز می‌بینید.",
            'faq' => [
                ["باتری {$name} چند میلی‌آمپر است؟", "باتریِ اصلیِ {$name} حدود {$bat} میلی‌آمپرساعت ظرفیت داره. نسخه‌های تقویتی معمولاً ۱۵ تا ۲۰ درصد ظرفیتِ بیشتری دارن."],
                ["بعد از تعویض باتری {$name} پیام «قطعه‌ی ناشناس» نمایش داده می‌شود؟", "در آیفون‌های جدید، اگه باتری با گوشی همگام‌سازی (Serialize) نشه، در بخشِ سلامتِ باتری پیامِ «Unknown Part» دیده می‌شه؛ این پیام روی کارکردِ باتری اثری نداره."],
                ["چه زمانی باید باتری {$name} را عوض کنم؟", "وقتی سلامتِ باتری زیرِ ۸۰٪ باشه، گوشی با شارژِ بالا خاموش بشه، یا باتری باد کرده باشه (فاصله افتادن بینِ صفحه و بدنه)."],
            ]];
        case 'display': return [
            'intro' => "صفحه‌نمایشِ {$name} یک پنلِ {$m['display']} با رزولوشنِ {$m['res']} پیکسل {$hz} است. انواعِ ال سی دیِ این مدل (اورجینال، و در صورتِ موجودی کیفیت‌های دیگه) به‌همراه چسبِ دورِ ال سی دی و خدمتِ تعویضِ گلس در این صفحه هست.",
            'faq' => [
                ["فرق ال سی دی اورجینال و های کپی {$name} چیست؟", "ال سی دیِ اورجینال همون پنلِ کارخونه‌ست با رنگ، روشنایی و حساسیتِ لمسیِ اصلی؛ نسخه‌های های کپی (مثل Incell) ارزون‌ترن ولی معمولاً ضخیم‌تر، کم‌نورتر و پرمصرف‌ترن."],
                ["اگر فقط شیشه {$name} شکسته باشد باید کل ال سی دی عوض شود؟", "اگه تصویر و لمس سالم باشه، فقط «تعویضِ گلس» (جدا کردنِ شیشه از پنل) کافیه و خیلی ارزون‌تر از تعویضِ کاملِ ال سی دیه."],
                ["بعد از تعویض صفحه {$name}، True Tone کار می‌کند؟", "با انتقالِ اطلاعاتِ پنلِ قبلی (با دستگاهِ پروگرامر) True Tone دوباره فعال می‌شه؛ موقعِ تعویض از تعمیرکار بخواید این کار رو انجام بده."],
            ]];
        case 'back-glass': return [
            'intro' => "شیشه‌ی پشتِ {$name} علاوه بر ظاهر، محلِ عبورِ شارژِ بی‌سیم (MagSafe در مدل‌های ۱۲ به بعد) هم هست. ترک خوردنش می‌تونه باعثِ بریدگیِ دست و ورودِ رطوبت بشه. شیشه‌ی پشتِ این مدل رو در رنگ‌بندیِ اصلی اینجا می‌بینید.",
            'faq' => [
                ["تعویض شیشه پشت {$name} چگونه انجام می‌شود؟", "شیشه‌ی شکسته معمولاً با دستگاهِ لیزر از بدنه جدا می‌شه تا به قطعاتِ زیرش آسیب نرسه، بعد شیشه‌ی جدید با چسبِ مخصوص نصب می‌شه."],
                ["بعد از تعویض شیشه پشت، شارژ بی‌سیم {$name} کار می‌کند؟", "بله، اگه فلت و کویلِ شارژِ بی‌سیم سالم بمونه، شارژِ بی‌سیم مثلِ قبل کار می‌کنه."],
            ]];
        case 'rear-camera': return [
            'intro' => "دوربینِ پشتِ {$name} و شیشه‌ی محافظِ لنزش در این صفحه هستن. خیلی وقت‌ها تاری یا لکه در عکس فقط به‌خاطرِ شکستنِ شیشه‌ی لنزه و با تعویضِ همون شیشه (به‌جای کلِ ماژول) درست می‌شه.",
            'faq' => [
                ["عکس‌های دوربین {$name} تار شده؛ باید دوربین عوض شود؟", "اول شیشه‌ی لنز رو از نظرِ ترک یا خش بررسی کنید؛ اگه شیشه شکسته باشه تعویضِ شیشه‌ی لنز کافیه. لرزش یا صدای تق‌تق و فوکوس نکردن معمولاً نشانه‌ی خرابیِ ماژولِ دوربینه."],
                ["گلس محافظ لنز دوربین {$name} لازم است؟", "بله؛ گلسِ لنز ارزونه و جلوی شکستنِ شیشه‌ی اصلیِ لنز رو می‌گیره که تعویضش هزینه‌ی بیشتری داره."],
            ]];
        case 'front-camera': return [
            'intro' => "دوربینِ جلو (سلفی) و فلتِ سنسورِ مجاورت و میکروفونِ بالای {$name} در این صفحه هستن. اگه موقعِ تماس صفحه خاموش نمی‌شه یا صدای شما به طرفِ مقابل نمی‌رسه، معمولاً همین فلت ایراد داره.",
            'faq' => [
                ["چرا موقع تماس صفحه {$name} خاموش نمی‌شود؟", "این مشکل معمولاً از سنسورِ مجاورت (Proximity) یا فلتِ اونه؛ گاهی گلسِ صفحه‌ی نامناسب هم جلوی سنسور رو می‌گیره."],
                ["تعویض دوربین جلو روی فیس آیدی {$name} اثر دارد؟", "در بسیاری از مدل‌ها دوربینِ جلو جدا از ماژولِ فیس آیدیه، ولی باید با دقت تعویض بشه تا به فلتِ فیس آیدی آسیب نرسه."],
            ]];
        case 'face-id': return [
            'intro' => "فیس آیدیِ {$name} از ماژولِ دات پروجکتور، دوربینِ مادونِ قرمز و فلت‌های مربوطه تشکیل شده. پیامِ «Face ID is not available» بعد از ضربه یا ورودِ آب رایجه و تعمیرش نیاز به تجربه‌ی تخصصی داره.",
            'faq' => [
                ["فیس آیدی {$name} کار نمی‌کند؛ قابل تعمیر است؟", "در بیشترِ موارد بله؛ با تعویضِ فلت یا تعمیرِ دات پروجکتور، فیس آیدی دوباره فعال می‌شه. اگه برد آسیب دیده باشه، باید جداگانه بررسی بشه."],
                ["آیا با تعویض فلت فیس آیدی {$name}، اطلاعات گوشی پاک می‌شود؟", "نه؛ تعمیرِ فیس آیدی روی اطلاعاتِ گوشی اثری نداره، ولی بعد از تعمیر باید چهره رو دوباره در تنظیمات ثبت کنید."],
            ]];
        case 'charging-port': return [
            'intro' => "سوکت و فلتِ شارژِ {$name} شاملِ درگاهِ شارژ، میکروفونِ پایین و گاهی آنتن‌ها می‌شه. اگه گوشی دیر شارژ می‌شه، شارژ قطع‌ووصل می‌شه یا صدای شما در تماس ضعیفه، این قطعه رو بررسی کنید." . ($m['extra'] && strpos($m['extra'], 'USB-C') !== false ? " این مدل درگاهِ USB-C داره." : " این مدل درگاهِ Lightning داره."),
            'faq' => [
                ["{$name} شارژ نمی‌شود؛ مشکل از سوکت است؟", "اول درگاه رو از نظرِ گردوخاک و پرز تمیز کنید و کابلِ دیگه‌ای امتحان کنید؛ اگه مشکل ادامه داشت، فلتِ شارژ (یا آی‌سیِ شارژِ روی برد) باید بررسی بشه."],
                ["تعویض فلت شارژ {$name} روی ضدآب بودن اثر دارد؟", "بعد از هر تعمیری که بدنه باز می‌شه، با نصبِ چسبِ آب‌بندیِ نو بخشی از مقاومت در برابرِ آب حفظ می‌شه، ولی به‌اندازه‌ی کارخونه تضمینی نیست."],
            ]];
        case 'housing': return [
            'intro' => "قاب و شاسیِ {$name}" . ($m['extra'] && strpos($m['extra'], 'تیتانیوم') !== false ? " (بدنه‌ی تیتانیومی)" : "") . "، ست پیچ‌ها و شیلدهای داخلیِ این مدل در این صفحه هستن؛ برای تعویضِ بدنه‌ی خم‌شده یا ضربه‌خورده و بازسازیِ کاملِ ظاهرِ گوشی.",
            'faq' => [
                ["با تعویض شاسی {$name}، رنگ گوشی عوض می‌شود؟", "بله؛ شاسی در رنگ‌بندی‌های اصلیِ همین مدل موجوده و می‌تونید همون رنگ یا رنگِ دیگه‌ای انتخاب کنید."],
            ]];
        case 'motherboard': return [
            'intro' => "برد (مادربرد) {$name} با پردازنده‌ی {$m['chip']}، قلبِ گوشیه. اگه گوشی روشن نمی‌شه، مدام ری‌استارت می‌شه یا آنتن نمی‌ده و تعمیرِ برد ممکن نیست، تعویضِ برد راه‌حله. حافظه‌ی برد و ظرفیتِ دستگاه رو قبل از سفارش دقیق انتخاب کنید.",
            'faq' => [
                ["برد {$name} قابل تعمیر است یا باید عوض شود؟", "خیلی از ایرادهای برد (آی‌سیِ شارژ، تغذیه یا آنتن) قابلِ تعمیره؛ اگه پردازنده یا حافظه آسیبِ جدی دیده باشه، تعویضِ برد پیشنهاد می‌شه."],
                ["با تعویض برد {$name}، اطلاعات منتقل می‌شود؟", "اطلاعات روی حافظه‌ی بردِ قبلیه؛ قبل از تعویض اگه گوشی روشن می‌شه از اطلاعات بکاپ (iCloud یا کامپیوتر) بگیرید."],
            ]];
    }
    return ['intro' => '', 'faq' => []];
}

/* ---------- خروجیِ صفحه ---------- */
add_action('template_redirect', function () {
    $model = sanitize_title((string) get_query_var('nexv_hub_model'));
    if ($model === '') return;
    $part = sanitize_title((string) get_query_var('nexv_hub_part'));
    $models = nexv_hub_models(); $parts = nexv_hub_parts();
    $items = isset($models[$model]) ? nexv_hub_products($model) : [];
    if ($part !== '') $items = isset($parts[$part]) ? nexv_hub_filter_part($items, $part) : [];
    if (!isset($models[$model]) || !$items) {
        global $wp_query; $wp_query->set_404(); status_header(404); return;   /* صفحه‌ی خالی ساخته نمی‌شه */
    }
    $m = $models[$model];
    $year = nexv_hub_fa_digits(wp_date('Y', null, new DateTimeZone('Asia/Tehran')));
    if ($part === '') {
        $h1 = 'لیست قیمت قطعات ' . $m['fa'];
        $title = $h1 . ' | قیمت روز ' . get_bloginfo('name');
        $desc = 'لیست قیمت به‌روزِ همه‌ی قطعات ' . $m['fa'] . ' (' . $m['en'] . '): ال سی دی، باتری، دوربین، برد، شیشه پشت، فلت شارژ و قاب؛ اورجینال با گارانتی و ارسال به سراسر ایران.';
        $intro = $m['fa'] . ' (' . $m['en'] . ') سالِ ' . nexv_hub_fa_digits($m['year']) . ' معرفی شد؛ صفحه‌نمایشِ ' . $m['display'] . '، پردازنده‌ی ' . $m['chip'] . ' و باتریِ حدود ' . nexv_hub_fa_digits(number_format($m['battery'])) . ' میلی‌آمپرساعت' . ($m['extra'] ? '، ' . $m['extra'] : '') . '. در این صفحه قیمتِ همه‌ی قطعاتِ این مدل رو یکجا می‌بینید؛ برای دیدنِ جزئیات، رنگ‌بندی و ثبتِ سفارش روی هر قطعه بزنید.';
        $faq = [
            ['قیمت قطعات ' . $m['fa'] . ' به‌روز است؟', 'بله؛ قیمت‌ها بر اساسِ نرخِ روز به‌روزرسانی می‌شن و تاریخِ آخرین به‌روزرسانیِ هر قطعه در صفحه‌ی همون محصول نوشته شده. برای قطعه‌هایی که قیمت ندارن تماس بگیرید.'],
            ['قطعات ' . $m['fa'] . ' گارانتی دارد؟', 'برای قطعاتِ این مدل گارانتیِ تعویض در نظر گرفته شده و هر قطعه قبل از ارسال از نظرِ سلامت و عملکرد بررسی می‌شه.'],
            ['ارسال قطعات ' . $m['fa'] . ' به شهرستان انجام می‌شود؟', 'بله؛ ارسال به سراسرِ ایران با پست پیشتاز یا تیپاکس انجام می‌شه و در تهران ارسالِ سریع با پیک هم ممکنه.'],
        ];
    } else {
        $p = $parts[$part];
        $h1 = 'قیمت ' . $p['fa'] . ' ' . $m['fa'];
        $title = $h1 . ' | ' . $m['en'] . ' ' . $p['en'] . ' | ' . get_bloginfo('name');
        $copy = nexv_hub_part_copy($part, $m);
        $intro = $copy['intro']; $faq = $copy['faq'];
        $desc = wp_trim_words('خرید و قیمت ' . $p['fa'] . ' ' . $m['fa'] . ' (' . $m['en'] . ' ' . $p['en'] . ')؛ ' . $intro, 32, '…');
    }
    $canon = nexv_hub_url($model, $part);
    $last = 0; $lastId = 0; foreach ($items as $it) if ($it['updated'] > $last) { $last = $it['updated']; $lastId = $it['id']; }
    $lastLabel = ($lastId && function_exists('nexv_jalali_pretty')) ? nexv_jalali_pretty($last) : '';
    $fmt = function ($n) { return nexv_hub_fa_digits(number_format($n)); };

    /* اسکیما: BreadcrumbList + ItemList + FAQPage */
    $crumbs = [['صفحه اصلی', home_url('/')], ['لیست قیمت قطعات ' . $m['fa'], nexv_hub_url($model)]];
    if ($part !== '') $crumbs[] = [$h1, $canon];
    $ld = ['@context' => 'https://schema.org', '@graph' => [
        ['@type' => 'BreadcrumbList', 'itemListElement' => array_map(function ($c, $i) { return ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1]]; }, $crumbs, array_keys($crumbs))],
        ['@type' => 'ItemList', 'name' => $h1, 'itemListElement' => array_map(function ($it, $i) { return ['@type' => 'ListItem', 'position' => $i + 1, 'url' => $it['url'], 'name' => $it['title']]; }, array_slice($items, 0, 100), array_keys(array_slice($items, 0, 100)))],
    ]];
    if ($faq) $ld['@graph'][] = ['@type' => 'FAQPage', 'mainEntity' => array_map(function ($q) { return ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]]; }, $faq)];

    /* پیوندهای داخلی: نوع‌قطعه‌های همین مدل + همین قطعه در مدل‌های هم‌سری */
    $all = nexv_hub_products($model);
    $partLinks = [];
    foreach ($parts as $ps => $pp) if ($ps !== $part && nexv_hub_filter_part($all, $ps)) $partLinks[] = ['قیمت ' . $pp['fa'] . ' ' . $m['fa'], nexv_hub_url($model, $ps)];
    $series = preg_replace('/^iphone-(\d+).*$/', '$1', $model);
    $sibLinks = [];
    foreach ($models as $ms => $mm) {
        if ($ms === $model || strpos($ms, 'iphone-' . $series) !== 0) continue;
        $sibLinks[] = [($part !== '' ? 'قیمت ' . $parts[$part]['fa'] . ' ' : 'قیمت قطعات ') . $mm['fa'], nexv_hub_url($ms, $part)];
    }
    $wa = preg_replace('/[^0-9]/', '', (string) get_theme_mod('nexv_contact_whatsapp', defined('NEXV_OWNER_PHONE') ? NEXV_OWNER_PHONE : ''));
    if (substr($wa, 0, 1) === '0') $wa = '98' . substr($wa, 1);

    status_header(200);
    nocache_headers();
    ?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<script>(function(){try{var t=localStorage.getItem('nexv_theme'); if(t==='dark'){document.documentElement.setAttribute('data-theme','dark');}}catch(e){}})();</script>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php echo function_exists('nexv_favicon_tags') ? nexv_favicon_tags() : ''; ?>
<title><?php echo esc_html($title); ?></title>
<meta name="description" content="<?php echo esc_attr($desc); ?>">
<meta name="robots" content="index, follow, max-image-preview:large">
<link rel="canonical" href="<?php echo esc_url($canon); ?>">
<meta property="og:type" content="website"><meta property="og:title" content="<?php echo esc_attr($h1); ?>"><meta property="og:description" content="<?php echo esc_attr($desc); ?>"><meta property="og:url" content="<?php echo esc_url($canon); ?>"><meta property="og:locale" content="fa_IR">
<script type="application/ld+json"><?php echo wp_json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap">
<style>
:root{--bg:#f7f6fb;--card:#fff;--text:#201f2b;--dim:#7a7788;--accent:#6a4dff;--soft:rgba(106,77,255,.10);--line:rgba(20,10,60,.08);}
html[data-theme="dark"]{--bg:#08080d;--card:#15141c;--text:#ececf4;--dim:#9a97ad;--accent:#8b6bff;--soft:rgba(139,107,255,.16);--line:rgba(255,255,255,.08);}
body{font-family:'Vazirmatn',Tahoma,sans-serif;background:var(--bg);color:var(--text);margin:0;padding:24px 16px 130px;}
.hub{max-width:860px;margin:0 auto;}
.crumbs{font-size:12.5px;color:var(--dim);margin-bottom:12px;display:flex;flex-wrap:wrap;gap:6px;}
.crumbs a{color:var(--dim);text-decoration:none;}.crumbs a:hover{color:var(--accent);}
h1{font-size:clamp(22px,4vw,30px);line-height:1.5;margin:0 0 10px;}
.upd{font-size:12.5px;color:var(--dim);margin-bottom:18px;}
.card{background:var(--card);border-radius:18px;padding:20px;box-shadow:0 14px 34px rgba(0,0,0,.06);margin-bottom:18px;}
.intro{font-size:15px;line-height:2.1;}
table{width:100%;border-collapse:collapse;font-size:14px;}
th,td{padding:12px 10px;border-bottom:1px solid var(--line);text-align:right;vertical-align:middle;}
th{font-size:12.5px;color:var(--dim);font-weight:600;}
td a{color:var(--text);text-decoration:none;font-weight:600;}td a:hover{color:var(--accent);}
td.price{white-space:nowrap;font-weight:700;color:var(--accent);}
td.price small{font-weight:500;color:var(--dim);}
.cta{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,var(--accent),#8f6bff);color:#fff;padding:10px 18px;border-radius:12px;text-decoration:none;font-weight:700;font-size:14px;}
h2{font-size:18px;margin:0 0 12px;}
.faq details{border-bottom:1px solid var(--line);padding:12px 0;}.faq summary{cursor:pointer;font-weight:700;font-size:14.5px;}
.faq p{margin:8px 0 0;line-height:2;font-size:14px;color:var(--dim);}
.links{display:flex;flex-wrap:wrap;gap:8px;}.links a{padding:8px 14px;border-radius:999px;background:var(--soft);color:var(--accent);text-decoration:none;font-size:13px;}
@media (max-width:600px){th.hide,td.hide{display:none;}}
</style>
</head>
<body>
<main class="hub">
  <nav class="crumbs" aria-label="مسیر"><?php foreach ($crumbs as $i => $c) { echo $i ? '<span>›</span>' : ''; echo $i === count($crumbs) - 1 ? '<span>' . esc_html($c[0]) . '</span>' : '<a href="' . esc_url($c[1]) . '">' . esc_html($c[0]) . '</a>'; } ?></nav>
  <h1><?php echo esc_html($h1); ?></h1>
  <div class="upd"><?php echo $lastLabel !== '' ? 'آخرین به‌روزرسانیِ قیمت: ' . esc_html($lastLabel) : 'قیمت‌ها بر اساسِ نرخِ روز به‌روزرسانی می‌شن'; ?></div>
  <section class="card intro"><p><?php echo esc_html($intro); ?></p>
  <?php if ($wa): ?><p><a class="cta" href="https://wa.me/<?php echo esc_attr($wa); ?>" rel="nofollow">مشاوره و استعلامِ قیمت در واتساپ</a></p><?php endif; ?></section>
  <section class="card">
    <h2><?php echo esc_html($part === '' ? 'قیمت قطعات ' . $m['fa'] : 'انواع ' . $parts[$part]['fa'] . ' ' . $m['fa']); ?></h2>
    <table>
      <thead><tr><th>قطعه</th><th class="hide">توضیح</th><th>قیمت (تومان)</th></tr></thead>
      <tbody>
      <?php foreach ($items as $it):
          $fa = trim(explode('|', $it['title'])[0]);
          $price = $it['min'] ? ($it['max'] > $it['min'] ? '<small>از</small> ' . $fmt($it['min']) : $fmt($it['min'])) : '<small>تماس بگیرید</small>'; ?>
        <tr><td><a href="<?php echo esc_url($it['url']); ?>"><?php echo esc_html($fa); ?></a></td>
            <td class="hide"><?php echo esc_html(wp_trim_words(strtok($it['short'], "\n"), 14, '…')); ?></td>
            <td class="price"><?php echo $price; ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <?php if ($faq): ?>
  <section class="card faq"><h2>سؤالات متداول</h2>
    <?php foreach ($faq as $q): ?><details><summary><?php echo esc_html($q[0]); ?></summary><p><?php echo esc_html($q[1]); ?></p></details><?php endforeach; ?>
  </section>
  <?php endif; ?>
  <?php if ($partLinks): ?><section class="card"><h2>قطعات دیگر <?php echo esc_html($m['fa']); ?></h2><div class="links">
    <?php if ($part !== ''): ?><a href="<?php echo esc_url(nexv_hub_url($model)); ?>">لیست قیمت همه‌ی قطعات <?php echo esc_html($m['fa']); ?></a><?php endif; ?>
    <?php foreach ($partLinks as $l) echo '<a href="' . esc_url($l[1]) . '">' . esc_html($l[0]) . '</a>'; ?></div></section><?php endif; ?>
  <?php if ($sibLinks): ?><section class="card"><h2>مدل‌های دیگرِ این سری</h2><div class="links">
    <?php foreach ($sibLinks as $l) echo '<a href="' . esc_url($l[1]) . '">' . esc_html($l[0]) . '</a>'; ?></div></section><?php endif; ?>
</main>
<?php echo function_exists('nexv_static_dock_html') ? nexv_static_dock_html() : ''; ?>
</body>
</html><?php
    exit;
}, 1);

/* ---------- سایت‌مپ: /parts-price-sitemap.xml ---------- */
function nexv_hub_sitemap_rows() {
    $rows = get_transient('nexv_hub_sitemap_v1');
    if (is_array($rows)) return $rows;
    $rows = [];
    foreach (array_keys(nexv_hub_models()) as $ms) {
        $items = nexv_hub_products($ms);
        if (!$items) continue;
        $last = 0; foreach ($items as $it) $last = max($last, $it['updated']);
        $rows[] = [nexv_hub_url($ms), $last];
        foreach (array_keys(nexv_hub_parts()) as $ps) {
            $sub = nexv_hub_filter_part($items, $ps);
            if (!$sub) continue;
            $l = 0; foreach ($sub as $it) $l = max($l, $it['updated']);
            $rows[] = [nexv_hub_url($ms, $ps), $l];
        }
    }
    set_transient('nexv_hub_sitemap_v1', $rows, 12 * HOUR_IN_SECONDS);
    return $rows;
}
add_filter('rank_math/sitemap/index', function ($xml) {
    $rows = nexv_hub_sitemap_rows();
    if (!$rows) return $xml;
    $last = 0; foreach ($rows as $r) $last = max($last, (int) $r[1]);
    return $xml . "\t<sitemap>\n\t\t<loc>" . esc_url(home_url('/parts-price-sitemap.xml')) . "</loc>\n" . ($last ? "\t\t<lastmod>" . gmdate('c', $last) . "</lastmod>\n" : '') . "\t</sitemap>\n";
}, 20);
add_action('wp_loaded', function () {
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (!preg_match('#/parts-price-sitemap\.xml$#', $path)) return;
    $rows = nexv_hub_sitemap_rows();
    status_header($rows ? 200 : 404);
    header('Content-Type: application/xml; charset=UTF-8');
    header('X-Robots-Tag: noindex, follow');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($rows as $r) echo "\t<url>\n\t\t<loc>" . esc_url($r[0]) . "</loc>\n" . ($r[1] ? "\t\t<lastmod>" . gmdate('c', (int) $r[1]) . "</lastmod>\n" : '') . "\t</url>\n";
    echo '</urlset>';
    exit;
}, 1);

/* پیوندِ صفحه‌ی «لیست قیمت» برای یک محصول (از روی نامِ انگلیسیِ عنوان)، یا '' */
function nexv_hub_link_for_title($title) {
    $en = strpos($title, '|') !== false ? strtolower(trim(substr($title, strrpos($title, '|') + 1))) : '';
    if ($en === '') return '';
    $best = ''; $bestLen = 0;
    foreach (nexv_hub_models() as $ms => $mm) {
        $n = strtolower($mm['en']);
        if (preg_match('/(^|\s)' . preg_quote($n, '/') . '(\s|$)/', $en) && strlen($n) > $bestLen) { $best = $ms; $bestLen = strlen($n); }
    }
    return $best;
}

/* =========================================================================
 * غنی‌سازیِ یک‌باره‌ی صفحه‌ی قطعاتِ اصلیِ مدل‌های پرفروش (ال سی دی، باتری، برد، دوربین پشت و جلو):
 * بخشِ «مشخصات و سازگاری» با مشخصاتِ واقعیِ مدل + علائمِ خرابی + سؤالات متداول + پیوند به صفحه‌های
 * لیست قیمت؛ و اگه عنوان/توضیحِ سئوی Rank Math خالی باشه، پر می‌شه. محتوای قبلی دست نمی‌خوره
 * (فقط به انتها اضافه می‌شه) و هر محصول فقط یک بار (نشانه‌ی nexv-specs-v1).
 * ========================================================================= */
function nexv_hub_enrich_targets() {
    return ['lcd' => 'display', 'battery' => 'battery', 'motherboard' => 'motherboard', 'rear-camera' => 'rear-camera', 'front-camera' => 'front-camera'];
}
function nexv_hub_enrich_block($modelSlug, $partSlug) {
    $m = nexv_hub_models()[$modelSlug]; $p = nexv_hub_parts()[$partSlug];
    $copy = nexv_hub_part_copy($partSlug, $m);
    $bat = nexv_hub_fa_digits(number_format($m['battery'])) ;
    $specs = [
        'مدلِ سازگار' => $m['fa'] . ' (' . $m['en'] . ')',
        'سالِ معرفی' => nexv_hub_fa_digits($m['year']),
        'صفحه‌نمایش' => $m['display'] . ' — ' . $m['res'],
        'پردازنده' => $m['chip'],
        'ظرفیتِ باتریِ اصلی' => $bat . ' میلی‌آمپرساعت',
    ];
    $h = '<!--nexv-specs-v1--><h3>مشخصات و سازگاری</h3><ul>';
    foreach ($specs as $k => $v) $h .= '<li><strong>' . esc_html($k) . ':</strong> ' . esc_html($v) . '</li>';
    $h .= '</ul>';
    if ($copy['intro'] !== '') $h .= '<p>' . esc_html($copy['intro']) . '</p>';
    if ($copy['faq']) {
        $h .= '<h3>سؤالات متداول</h3>';
        foreach ($copy['faq'] as $q) $h .= '<p><strong>' . esc_html($q[0]) . '</strong><br>' . esc_html($q[1]) . '</p>';
    }
    $h .= '<p>مقایسه‌ی قیمت: <a href="' . esc_url(nexv_hub_url($modelSlug, $partSlug)) . '">قیمت ' . esc_html($p['fa'] . ' ' . $m['fa']) . '</a> · '
        . '<a href="' . esc_url(nexv_hub_url($modelSlug)) . '">لیست قیمت همه‌ی قطعات ' . esc_html($m['fa']) . '</a></p>';
    return $h;
}
add_action('admin_init', function () {
    if (get_option('nexv_hub_enrich_v1') === 'done' || !current_user_can('manage_options') || wp_doing_ajax()) return;
    $done = 0;
    foreach (nexv_hub_models() as $ms => $m) {
        foreach (nexv_hub_enrich_targets() as $suffix => $ps) {
            $post = get_page_by_path($ms . '-' . $suffix, OBJECT, 'nexv_product');
            if (!$post || strpos($post->post_content, 'nexv-specs-v1') !== false) continue;
            wp_update_post(['ID' => $post->ID, 'post_content' => rtrim($post->post_content) . "\n\n" . nexv_hub_enrich_block($ms, $ps)]);
            $pfa = nexv_hub_parts()[$ps]['fa'];
            if (!get_post_meta($post->ID, 'rank_math_title', true))
                update_post_meta($post->ID, 'rank_math_title', 'خرید و قیمت ' . $pfa . ' ' . $m['fa'] . ' | اورجینال با گارانتی %sep% %sitename%');
            if (!get_post_meta($post->ID, 'rank_math_description', true))
                update_post_meta($post->ID, 'rank_math_description', mb_substr('قیمت روز و خرید ' . $pfa . ' ' . $m['fa'] . ' (' . $m['en'] . ')؛ اورجینال با گارانتی تعویض، ارسال سریع به سراسر ایران و مشاوره‌ی رایگان برای انتخابِ قطعه‌ی درست.', 0, 158));
            if (!get_post_meta($post->ID, 'rank_math_focus_keyword', true))
                update_post_meta($post->ID, 'rank_math_focus_keyword', 'قیمت ' . $pfa . ' ' . $m['fa'] . ',' . $pfa . ' ' . $m['fa'] . ',' . strtolower($m['en'] . ' ' . nexv_hub_parts()[$ps]['en']));
            $done++;
            if ($done >= 40) return;   /* دسته‌دسته؛ بارِ بعدیِ پیشخوان ادامه پیدا می‌کنه */
        }
    }
    update_option('nexv_hub_enrich_v1', 'done', false);
    delete_transient('nexv_products_cache_v2');
    nexv_hub_flush();
}, 40);
