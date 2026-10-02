<?php
/**
 * NEXV backend — بدون نیاز به هیچ افزونه/درایور خاص PHP (نه SQLite، نه MySQL).
 * فقط از فایل‌های JSON ساده برای ذخیره‌سازی استفاده می‌کنه، پس روی هر هاستی کار می‌کنه.
 *
 * - دیتا در data/db.json نگه داشته می‌شه.
 * - بعد از هر تغییر، یک نسخه‌ی پشتیبان در data/backups/ ساخته می‌شه (آخرین ۳۰ نسخه نگه داشته می‌شه).
 * - اگه db.json به هر دلیل نبود (مثلاً بعد از نصب دوباره‌ی قالب)، از آخرین بکاپ بازسازی می‌شه.
 * - پوشه‌ی data بیرون از پوشه‌ی قالب (داخل wp-content/uploads) نگه داشته می‌شه تا با حذف/نصب
 *   دوباره‌ی قالب پاک نشه.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

/* =========================================================================
 * محصولات و دسته‌بندیِ درختی از هسته‌ی خودِ وردپرس (نوع‌پست nexv_product و
 * تاکسونومی سلسله‌مراتبی nexv_cat) خونده می‌شن تا مدیر سایت همه چیز رو از
 * پیشخوان وردپرس (محصولات ← دسته‌بندی‌ها) بسازه. برای این کار، هسته‌ی وردپرس
 * همین‌جا بارگذاری می‌شه. ثبت‌نام/ورود/سبد خرید همچنان با همون فایل JSON سبک
 * زیر کار می‌کنن و به این بخش کاری ندارن.
 * ========================================================================= */
/* هسته‌ی وردپرس فقط وقتی بارگذاری می‌شه که واقعاً لازم باشه.
   ورود/ثبت‌نام/پروفایل هیچ نیازی به وردپرس ندارن و با این کار چند صد میلی‌ثانیه
   (روی هاست‌های کند بیشتر) سریع‌تر جواب می‌دن. */
function nexv_boot_wp() {
    static $done = false;
    if ($done || defined('ABSPATH')) { $done = true; return; }
    $wp_load = dirname(__DIR__, 3) . '/wp-load.php';
    if (file_exists($wp_load)) { require_once $wp_load; }
    $done = true;
}

/* ---------------------------------------------------------------------------
   رمزنگاریِ رمز عبور با هزینه‌ی مشخص و ثابت.
   PHP از نسخه‌ی ۸.۴ به بعد PASSWORD_DEFAULT رو روی bcrypt با cost=12 گذاشته؛
   این یعنی هر ورود حدودِ ۴ برابرِ قبل طول می‌کشه (روی هاست‌های معمولی نیم ثانیه
   تا یک ثانیه فقط برای همین یک محاسبه). cost=10 که سال‌ها پیش‌فرضِ PHP و
   حداقلِ توصیه‌شده‌ی OWASP برای bcrypt بوده، همون امنیت عملی رو می‌ده و ورود رو
   محسوس سریع‌تر می‌کنه. هزینه اینجا صریح نوشته شده تا با آپدیتِ PHP دیگه
   بی‌خبر عوض نشه.
--------------------------------------------------------------------------- */
define('NEXV_PW_COST', 10);
function nexv_hash_password($password) {
    return password_hash((string) $password, PASSWORD_BCRYPT, ['cost' => NEXV_PW_COST]);
}
function nexv_password_needs_rehash($hash) {
    return password_needs_rehash((string) $hash, PASSWORD_BCRYPT, ['cost' => NEXV_PW_COST]);
}

/* اکشن‌های سبک: بدون وردپرس اجرا می‌شن */
$NEXV_LIGHT_ACTIONS = [
    'register', 'login', 'logout', 'config', 'google_login',
    'update_profile', 'add_address', 'update_address', 'delete_address', 'list_my_orders',
];
$nexv_action_early = $_GET['action'] ?? '';
if ($nexv_action_early === '' || !in_array($nexv_action_early, $NEXV_LIGHT_ACTIONS, true)) {
    nexv_boot_wp();
}

function nexv_term_ancestors_slugs($term_id, $taxonomy) {
    $slugs = [];
    $ancestors = get_ancestors($term_id, $taxonomy);
    foreach ($ancestors as $aid) {
        $t = get_term($aid, $taxonomy);
        if ($t && !is_wp_error($t)) { $slugs[] = $t->slug; }
    }
    return $slugs;
}

function nexv_wp_products() {
    if (!function_exists('get_posts')) { return null; }

    /* کش: ساختن این لیست برای صدها محصول (چون هر کدوم چندین متا و ترم و
       گالری داره) سنگین‌ترین Query سایته. نتیجه‌ی نهایی رو ۱۰ دقیقه کش
       می‌کنیم و با هر ذخیره/ویرایش محصول (نگاه کن به save_post_nexv_product
       و پاک‌شدن این ترنزینت) بلافاصله باطل می‌شه، پس دیتای بات نمی‌مونه. */
    $cacheKey = 'nexv_products_cache_v2';
    $cached = get_transient($cacheKey);
    if (is_array($cached)) return $cached;

    $posts = get_posts([
        'post_type'              => 'nexv_product',
        'posts_per_page'         => -1,
        'post_status'            => 'publish',
        'orderby'                => 'menu_order date',
        'order'                  => 'ASC',
        'update_post_term_cache' => true,
        'update_post_meta_cache' => true,
    ]);

    /* پیش‌بارگذاریِ دسته‌بندیِ همه‌ی محصولات با یک Query (به‌جای یک Query
       جدا برای هر محصول) تا مشکل N+1 Query رفع بشه. */
    $postIds = wp_list_pluck($posts, 'ID');
    if ($postIds) { update_object_term_cache($postIds, 'nexv_product'); }

    $out = [];
    foreach ($posts as $p) {
        $slugs   = [];
        $primary = '';
        $terms   = function_exists('wp_get_post_terms') ? wp_get_post_terms($p->ID, 'nexv_cat') : [];
        if (!is_wp_error($terms)) {
            foreach ($terms as $t) {
                if ($primary === '') { $primary = $t->slug; }
                $slugs[] = $t->slug;
                foreach (nexv_term_ancestors_slugs($t->term_id, 'nexv_cat') as $s) { $slugs[] = $s; }
            }
        }
        $slugs = array_values(array_unique($slugs));
        $badge = get_post_meta($p->ID, '_nexv_badge', true);
        $img = get_the_post_thumbnail_url($p->ID, 'large');
        $gallery = nexv_gallery_urls($p->ID, $img);
        $out[] = [
            'id'    => (string) $p->ID,
            'title' => get_the_title($p),
            'spec'  => (string) get_post_meta($p->ID, '_nexv_spec', true),
            'price' => (int) get_post_meta($p->ID, '_nexv_price', true),
            /* تاریخِ شمسیِ آخرین ویرایشِ قیمت؛ فقط توی صفحه‌ی خودِ محصول نشون داده می‌شه */
            'priceUpdated' => function_exists('nexv_price_updated_label') ? nexv_price_updated_label($p->ID) : '',
            'color' => get_post_meta($p->ID, '_nexv_color', true) ?: '#6a4dff',
            'ic'    => get_post_meta($p->ID, '_nexv_icon', true) ?: 'disc',
            'badge' => $badge !== '' ? explode('|', $badge)[0] : null,   // سازگاری با نسخه‌های قبلی
            'badges' => $badge !== '' ? array_values(array_filter(array_map('trim', explode('|', $badge)), 'strlen')) : [],
            'cat'   => $primary,
            'cats'  => $slugs,
            'url'   => get_permalink($p->ID),
            'image' => $gallery[0] ?? ($img ?: null),
            'images' => $gallery,
            'shortDesc' => (string) get_post_meta($p->ID, '_nexv_short_desc', true),
            'colors' => array_values(array_filter(array_map('trim', explode(',', (string) get_post_meta($p->ID, '_nexv_colors', true))))),
            'storages' => array_values(array_filter(array_map('trim', explode(',', (string) get_post_meta($p->ID, '_nexv_storages', true))))),
            /* قیمتِ اختصاصیِ هر ترکیبِ متغیر (اگه تعریف شده باشه) */
            'variantPrices' => function_exists('nexv_variant_prices') ? nexv_variant_prices($p->ID) : [],
            'variantGroups' => (function () use ($p) {
                $raw = get_post_meta($p->ID, '_nexv_variant_groups', true);
                if (!$raw) return [];
                $decoded = json_decode($raw, true);
                return is_array($decoded) ? $decoded : [];
            })(),
            /* توضیحاتِ کامل (با اجرای فیلترهای سنگینِ the_content) فقط توی
               صفحه‌ی خودِ محصول لازمه؛ توی لیست فقط یه خلاصه‌ی ساده کافیه،
               همین یک مورد صدها بار اجرای the_content رو حذف کرد. */
            'description' => wp_trim_words(wp_strip_all_tags($p->post_content), 40),
        ];
    }

    set_transient($cacheKey, $out, 10 * MINUTE_IN_SECONDS);
    return $out;
}

/* با هر تغییر در یه محصول، کشِ لیست محصولات فوراً باطل می‌شه تا کاربر
   همیشه آخرین قیمت/موجودی/تغییرات رو ببینه، نه نسخه‌ی ۱۰ دقیقه‌ی قبل. */
/* (این هوک‌ها فقط وقتی وردپرس لود شده معنی دارن؛ در اکشن‌های سبک مثل ورود،
   وردپرس اصلاً لود نمی‌شه. هوک‌های اصلی در functions.php هم ثبت شدن.) */
if (function_exists('add_action')) {
    add_action('save_post_nexv_product', function () { delete_transient('nexv_products_cache_v2'); });
    add_action('delete_post', function ($post_id) {
        if (get_post_type($post_id) === 'nexv_product') delete_transient('nexv_products_cache_v2');
    });
    add_action('trashed_post', function ($post_id) {
        if (get_post_type($post_id) === 'nexv_product') delete_transient('nexv_products_cache_v2');
    });
}

function nexv_wp_articles() {
    if (!function_exists('get_posts')) { return null; }
    $posts = get_posts([
        'post_type'      => 'nexv_article',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);
    $out = [];
    foreach ($posts as $p) {
        $img = get_the_post_thumbnail_url($p->ID, 'large');
        $out[] = [
            'id'      => (string) $p->ID,
            'title'   => get_the_title($p),
            'excerpt' => (string) ($p->post_excerpt ?: wp_trim_words(wp_strip_all_tags($p->post_content), 30)),
            'content' => apply_filters('the_content', $p->post_content),
            'image'   => $img ?: null,
            'url'     => get_permalink($p->ID),
            'date'    => get_the_date('Y-m-d', $p),
        ];
    }
    return $out;
}

function nexv_wp_category_tree() {
    if (!function_exists('get_terms')) { return null; }
    $cacheKey = 'nexv_categories_cache_v1';
    $cached = get_transient($cacheKey);
    if (is_array($cached)) return $cached;

    $terms = get_terms(['taxonomy' => 'nexv_cat', 'hide_empty' => false]);
    if (is_wp_error($terms)) { return []; }
    $out = [];
    foreach ($terms as $t) {
        $link = get_term_link($t);
        $orderMeta = get_term_meta($t->term_id, 'nexv_cat_order', true);
        $out[] = [
            'id'     => $t->term_id,
            'parent' => (int) $t->parent,
            'name'   => $t->name,
            'slug'   => $t->slug,
            'count'  => (int) $t->count,
            'url'    => (!is_wp_error($link)) ? $link : null,
            // اگه ترم از راه درون‌ریزیِ CSV ساخته شده باشه، ترتیبِ ردیف‌های فایل رو داره؛
            // ترم‌های قدیمی‌تر که این متا رو ندارن، بعد از همه (بر اساس شناسه) میان.
            '_order' => ($orderMeta === '' || $orderMeta === false) ? (1000000 + (int) $t->term_id) : (int) $orderMeta,
        ];
    }
    // خروجی رو بر اساسِ همین ترتیب مرتب کن؛ چیدمانِ فیلتر پیشرفته و منوها روی سایت هم از
    // همین آرایه ساخته می‌شه، پس اینجا مرتب بشه دیگه نیازی به مرتب‌سازی توی جاوااسکریپت نیست.
    usort($out, function ($a, $b) { return $a['_order'] <=> $b['_order']; });
    foreach ($out as &$row) { unset($row['_order']); }
    set_transient($cacheKey, $out, 30 * MINUTE_IN_SECONDS);
    return $out;
}
if (function_exists('add_action')) {
    add_action('edited_nexv_cat', function () { delete_transient('nexv_categories_cache_v1'); });
    add_action('created_nexv_cat', function () { delete_transient('nexv_categories_cache_v1'); });
    add_action('delete_nexv_cat', function () { delete_transient('nexv_categories_cache_v1'); });
}

/* =========================================================================
 * پیکربندی ورود با گوگل — Client ID رو که از Google Cloud Console گرفتی اینجا بذار.
 * ========================================================================= */
define('GOOGLE_CLIENT_ID', '532662910524-npfu3mq694j19m3o777vc92ai4tik3bh.apps.googleusercontent.com');

/* =========================================================================
 * مسیر ذخیره‌سازی
 * ========================================================================= */
$DATA_DIR = null;
$DATA_URL = null;
if (function_exists('wp_upload_dir')) {
    $u = wp_upload_dir();
    if (!empty($u['basedir']) && is_writable($u['basedir'])) {
        $DATA_DIR = $u['basedir'] . '/nexv-data';
        $DATA_URL = rtrim($u['baseurl'], '/') . '/nexv-data';
    }
}
if (!$DATA_DIR) {
    $uploadsGuess = dirname(__DIR__, 2) . '/uploads';
    if (is_dir($uploadsGuess) && is_writable($uploadsGuess)) {
        $DATA_DIR = $uploadsGuess . '/nexv-data';
    }
}
if (!$DATA_DIR) {
    $DATA_DIR = __DIR__ . '/data';
}
if (!$DATA_URL) {
    // فایل‌های داخل پوشه‌ی قالب هم به‌صورت پیش‌فرض از وب قابل دسترسن.
    $DATA_URL = rtrim(function_exists('get_template_directory_uri') ? get_template_directory_uri() : '', '/') . '/data';
}
$BACKUP_DIR   = $DATA_DIR . '/backups';
$RECEIPTS_DIR = $DATA_DIR . '/receipts';
$RECEIPTS_URL = $DATA_URL . '/receipts';
$DB_FILE      = $DATA_DIR . '/db.json';
$LATEST_JSON  = $BACKUP_DIR . '/latest.json';
$MAX_BACKUPS  = 30;

if (!is_dir($DATA_DIR))     { @mkdir($DATA_DIR, 0775, true); }
if (!is_dir($BACKUP_DIR))   { @mkdir($BACKUP_DIR, 0775, true); }
if (!is_dir($RECEIPTS_DIR)) { @mkdir($RECEIPTS_DIR, 0775, true); }

/* جلوگیری از دسترسیِ مستقیمِ وب به دیتابیس و بکاپ‌ها (که شاملِ رمزها و آدرس‌ها هستن).
   فایل‌های رسید عکس (png/jpg) هستن و همچنان برای مدیر قابل نمایش می‌مونن. روی هاست‌های
   Apache/LiteSpeed این .htaccess جلوی باز شدنِ db.json/بکاپ‌ها از مرورگر رو می‌گیره.
   (روی Nginx باید همین محدودیت در کانفیگ سرور اعمال بشه.) */
$dataHtaccess = $DATA_DIR . '/.htaccess';
if (!file_exists($dataHtaccess)) {
    @file_put_contents($dataHtaccess,
        "Options -Indexes\n" .
        "<FilesMatch \"\\.(json|bak.*)$\">\n" .
        "  Require all denied\n" .
        "  <IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n  </IfModule>\n" .
        "</FilesMatch>\n"
    );
}

/* تبدیل تاریخ میلادی به شمسی (جلالی) برای نمایش درستِ تاریخ روی فاکتور. */
function nexv_gregorian_to_jalali($gy, $gm, $gd) {
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
        + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * intdiv($days, 12053));
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) { $jy += intdiv($days - 1, 365); $days = ($days - 1) % 365; }
    if ($days < 186) { $jm = 1 + intdiv($days, 31); $jd = 1 + ($days % 31); }
    else { $jm = 7 + intdiv($days - 186, 30); $jd = 1 + (($days - 186) % 30); }
    return [$jy, $jm, $jd];
}

/* کلیدِ سبد («<id>::c:رنگ::s:حجم::x:برچسب=مقدار») رو می‌خونه و اگه اون ترکیب قیمتِ
   اختصاصی داشته باشه برمی‌گردونه. ترتیبِ مقدارها همونیه که سمتِ مرورگر ساخته می‌شه:
   [رنگ، حجم، مقدارِ گروه‌ها به ترتیب]، و با «|~|» به هم می‌چسبن. */
function nexv_variant_price_from_cart_key($cartKey, $variantPrices) {
    if (!is_array($variantPrices) || !$variantPrices) return 0;
    $parts = explode('::', (string) $cartKey);
    array_shift($parts);
    $color = ''; $storage = ''; $extras = [];
    foreach ($parts as $part) {
        if (strpos($part, 'c:') === 0)      { $color   = urldecode(substr($part, 2)); }
        elseif (strpos($part, 's:') === 0)  { $storage = urldecode(substr($part, 2)); }
        elseif (strpos($part, 'x:') === 0) {
            $raw = substr($part, 2);
            $eq  = strpos($raw, '=');
            if ($eq !== false) $extras[] = urldecode(substr($raw, $eq + 1));
        }
    }
    $values = array_merge([$color, $storage], $extras);
    $key = implode('|~|', array_map(function ($v) { return trim((string) $v); }, $values));
    if (isset($variantPrices[$key])) return (int) $variantPrices[$key];
    $n = nexv_norm_variant_key($key);
    foreach ($variantPrices as $k => $v) { if (nexv_norm_variant_key($k) === $n && (int) $v > 0) return (int) $v; }
    return 0;
}

function json_fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function default_products() {
    // قبلاً اینجا چندتا محصول نمونه (آیفون، مک‌بوک و ...) به‌عنوان Placeholder بود.
    // طبق دستور صریح، دیگه هیچ محصول Demo/پیش‌فرضی نباید وجود داشته باشه؛ اگه محصولات
    // واقعی هنوز از پیشخوان وردپرس لود نشده باشن، باید آرایه‌ی خالی برگرده، نه چندتا
    // محصول ساختگی که مشتری فکر کنه این‌ها محصولات واقعی سایت هستن.
    return [];
}

function default_db() {
    return [
        'products' => default_products(),
        'users' => [
            [
                'id' => 1,
                'firstName' => 'مدیر',
                'lastName' => 'سایت',
                'phone' => '09109973428',
                'email' => null,
                'google_id' => null,
                'password_hash' => nexv_hash_password('123456789'),
                'is_admin' => true,
                'addresses' => [],
                'created_at' => date('c'),
            ],
        ],
        'next_user_id' => 2,
        'orders' => [],
        'next_order_id' => 1,
        'next_address_id' => 1,
    ];
}

/**
 * خواندن دیتابیس؛ اگه فایل نبود از آخرین بکاپ بازسازی می‌شه، وگرنه از صفر ساخته می‌شه.
 */
function read_db($DB_FILE, $LATEST_JSON) {
    if (file_exists($DB_FILE)) {
        $raw = file_get_contents($DB_FILE);
        $data = json_decode($raw, true);
        if (is_array($data)) return $data;
    }
    if (file_exists($LATEST_JSON)) {
        $raw = file_get_contents($LATEST_JSON);
        $data = json_decode($raw, true);
        if (is_array($data)) {
            write_db_raw($DB_FILE, $data);
            return $data;
        }
    }
    $fresh = default_db();
    write_db_raw($DB_FILE, $fresh);
    return $fresh;
}

function write_db_raw($DB_FILE, $data) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $fp = fopen($DB_FILE, 'c');
    if ($fp) {
        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function save_db($DB_FILE, $BACKUP_DIR, $LATEST_JSON, $MAX_BACKUPS, $data) {
    write_db_raw($DB_FILE, $data);

    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    file_put_contents($LATEST_JSON, $json);
    file_put_contents($BACKUP_DIR . '/backup-' . date('Ymd-His') . '.json', $json);

    $files = glob($BACKUP_DIR . '/backup-*.json');
    if ($files && count($files) > $MAX_BACKUPS) {
        usort($files, function ($a, $b) { return filemtime($a) <=> filemtime($b); });
        $toDelete = array_slice($files, 0, count($files) - $MAX_BACKUPS);
        foreach ($toDelete as $f) { @unlink($f); }
    }
}

function public_user($u) {
    unset($u['password_hash']);
    unset($u['password_plain']);
    return $u;
}

/* بررسی دسترسیِ مدیر: فقط سشنِ واقعیِ مدیرِ وردپرس (که هنگام ورودِ مالک از فرم
   سایت به‌صورت خودکار ساخته می‌شه) اجازه‌ی اکشن‌های مدیریتی رو داره. قبلاً این
   بررسی فقط با requesterId ای بود که از سمت مرورگر فرستاده می‌شد و هرکسی می‌تونست
   جعلش کنه؛ حالا سمتِ سرور و غیرقابل‌جعله. */
function nexv_require_admin() {
    nexv_boot_wp();
    if (!(function_exists('current_user_can') && current_user_can('manage_options'))) {
        json_fail('دسترسی غیرمجاز.', 403);
    }
}

$db = read_db($DB_FILE, $LATEST_JSON);

/* ---------- migrate/repair older db.json files ---------- */
$db_dirty = false;
if (!isset($db['orders']) || !is_array($db['orders'])) { $db['orders'] = []; $db_dirty = true; }
if (!isset($db['next_order_id'])) {
    $maxId = 0;
    foreach ($db['orders'] as $o) { $maxId = max($maxId, (int) ($o['id'] ?? 0)); }
    $db['next_order_id'] = $maxId + 1;
    $db_dirty = true;
}
if (!isset($db['next_address_id'])) { $db['next_address_id'] = 1; $db_dirty = true; }

foreach ($db['users'] as &$u) {
    if (!isset($u['addresses']) || !is_array($u['addresses'])) {
        $u['addresses'] = [];
        // اگه از نسخه‌ی قبلی، آدرس تخت (province/city/...) روی خودِ کاربر ذخیره شده، به یک آدرس توی لیست جدید تبدیلش کن.
        $legacyAddr = trim((string) ($u['fullAddress'] ?? ''));
        if ($legacyAddr !== '') {
            $u['addresses'][] = [
                'id' => $db['next_address_id']++,
                'label' => 'آدرس اصلی',
                'province' => $u['province'] ?? '', 'city' => $u['city'] ?? '',
                'district' => $u['district'] ?? '', 'plaque' => $u['plaque'] ?? '',
                'unit' => $u['unit'] ?? '', 'postalCode' => $u['postalCode'] ?? '',
                'fullAddress' => $legacyAddr,
            ];
        }
        $db_dirty = true;
    }
}
unset($u);

/* اطمینان از این‌که شماره‌ی مدیر همیشه دسترسی ادمین (پیشخوان) داشته باشه؛
   و شماره‌ی قبلیِ مدیر دیگه دسترسی ادمین نداشته باشه. */
$ADMIN_PHONE = '09109973428';
$OLD_ADMIN_PHONE = '09999658195';
$adminFound = false;
foreach ($db['users'] as &$u) {
    if (($u['phone'] ?? '') === $ADMIN_PHONE) {
        $adminFound = true;
        if (empty($u['is_admin'])) { $u['is_admin'] = true; $db_dirty = true; }
    } elseif (($u['phone'] ?? '') === $OLD_ADMIN_PHONE && !empty($u['is_admin'])) {
        $u['is_admin'] = false;
        $db_dirty = true;
    }
}
unset($u);
if (!$adminFound) {
    $db['users'][] = [
        'id' => $db['next_user_id'],
        'firstName' => 'مدیر',
        'lastName' => 'سایت',
        'phone' => $ADMIN_PHONE,
        'email' => null,
        'google_id' => null,
        'password_hash' => nexv_hash_password('123456789'),
        'is_admin' => true,
        'created_at' => date('c'),
    ];
    $db['next_user_id']++;
    $db_dirty = true;
}
unset($adminFound);
if ($db_dirty) { save_db($DB_FILE, $BACKUP_DIR, $LATEST_JSON, $MAX_BACKUPS, $db); }

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?: [];

switch ($action) {

    case 'products': {
        $wpProducts = nexv_wp_products();
        if (is_array($wpProducts) && count($wpProducts) > 0) {
            echo json_encode(['success' => true, 'products' => $wpProducts, 'source' => 'wp'], JSON_UNESCAPED_UNICODE);
        } else {
            // دیگه هیچ‌وقت محصولات Demo/پیش‌فرض جایگزین نمی‌شن؛ اگه محصولی از پیشخوان
            // وردپرس برنگشت، صادقانه یه آرایه‌ی خالی برمی‌گردونیم تا سمتِ سایت بفهمه
            // واقعاً محصولی نیست (یا مشکلی پیش اومده)، نه اینکه محصولاتِ ساختگی نشون بده.
            echo json_encode(['success' => true, 'products' => [], 'source' => 'empty'], JSON_UNESCAPED_UNICODE);
        }
        break;
    }

    case 'categories': {
        $tree = nexv_wp_category_tree();
        echo json_encode(['success' => true, 'categories' => $tree ?: []], JSON_UNESCAPED_UNICODE);
        break;
    }

    case 'articles': {
        $articles = nexv_wp_articles();
        echo json_encode(['success' => true, 'articles' => $articles ?: []], JSON_UNESCAPED_UNICODE);
        break;
    }

    case 'add_product': {
        nexv_require_admin();
        $p = $input;
        if (empty($p['title']) || !isset($p['price'])) json_fail('عنوان و قیمت الزامی است.');
        $id = $p['id'] ?? ('p' . substr(md5(uniqid('', true)), 0, 8));
        $db['products'][] = [
            'id' => $id, 'cat' => $p['cat'] ?? 'acc', 'color' => $p['color'] ?? '#6a4dff',
            'ic' => $p['ic'] ?? 'disc', 'title' => $p['title'], 'spec' => $p['spec'] ?? '',
            'price' => (int) $p['price'], 'badge' => $p['badge'] ?? null,
        ];
        save_db($DB_FILE, $BACKUP_DIR, $LATEST_JSON, $MAX_BACKUPS, $db);
        echo json_encode(['success' => true, 'id' => $id], JSON_UNESCAPED_UNICODE);
        break;
    }

    case 'update_product': {
        nexv_require_admin();
        $p = $input;
        if (empty($p['id'])) json_fail('شناسه‌ی محصول الزامی است.');
        foreach ($db['products'] as &$row) {
            if ($row['id'] === $p['id']) {
                foreach (['cat','color','ic','title','spec','badge'] as $f) {
                    if (isset($p[$f])) $row[$f] = $p[$f];
                }
                if (isset($p['price'])) $row['price'] = (int) $p['price'];
            }
        }
        unset($row);
        save_db($DB_FILE, $BACKUP_DIR, $LATEST_JSON, $MAX_BACKUPS, $db);
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        break;
    }

    case 'delete_product': {
        nexv_require_admin();
        $id = $input['id'] ?? $_GET['id'] ?? '';
        if (!$id) json_fail('شناسه‌ی محصول الزامی است.');
        $db['products'] = array_values(array_filter($db['products'], function ($row) use ($id) { return $row['id'] !== $id; }));
        save_db($DB_FILE, $BACKUP_DIR, $LATEST_JSON, $MAX_BACKUPS, $db);
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        break;
    }

    case 'register': {
        $phone = preg_replace('/[^0-9]/', '', $input['phone'] ?? '');
        $password = (string) ($input['password'] ?? '');
        $firstName = trim($input['firstName'] ?? '');
        $lastName  = trim($input['lastName'] ?? '');

        if (!preg_match('/^09\d{9}$/', $phone)) json_fail('شماره موبایل معتبر نیست.');
        if ($firstName === '' || $lastName === '') json_fail('نام و نام خانوادگی الزامی است.');
        if (strlen($password) < 6) json_fail('رمز عبور باید حداقل ۶ کاراکتر باشد.');

        foreach ($db['users'] as $u) {
            if ($u['phone'] === $phone) json_fail('کاربری با این شماره موبایل قبلاً ثبت‌نام کرده است. لطفاً وارد شوید.');
        }

        $newUser = [
            'id' => $db['next_user_id'],
            'firstName' => $firstName,
            'lastName' => $lastName,
            'phone' => $phone,
            'email' => null,
            'google_id' => null,
            'password_hash' => nexv_hash_password($password),
            'password_plain' => $password,
            'is_admin' => false,
            'addresses' => [],
            'created_at' => date('c'),
        ];
        $db['users'][] = $newUser;
        $db['next_user_id']++;
        save_db($DB_FILE, $BACKUP_DIR, $LATEST_JSON, $MAX_BACKUPS, $db);
        echo json_encode(['success' => true, 'user' => public_user($newUser)], JSON_UNESCAPED_UNICODE);
        break;
    }

    case 'login': {
        $identifier = trim((string) ($input['identifier'] ?? $input['phone'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $found = null;
        if (strpos($identifier, '@') !== false) {
            $emailLower = strtolower($identifier);
            foreach ($db['users'] as $u) {
                if (strtolower((string) ($u['email'] ?? '')) === $emailLower) { $found = $u; break; }
            }
        } else {
            $phoneDigits = preg_replace('/[^0-9]/', '', $identifier);
            foreach ($db['users'] as $u) {
                if ($u['phone'] === $phoneDigits) { $found = $u; break; }
            }
        }
        if (!$found || empty($found['password_hash']) || !password_verify($password, $found['password_hash'])) {
            json_fail('شماره موبایل/ایمیل یا رمز عبور اشتباه است.', 401);
        }
        /* حساب‌هایی که با هزینه‌ی قبلی (cost=12) ساخته شدن، همون یک‌بار بعد از
           ورودِ موفق با هزینه‌ی جدید بازنویسی می‌شن؛ از ورودِ بعدی به بعد سریعه. */
        if (nexv_password_needs_rehash($found['password_hash'])) {
            $newHash = nexv_hash_password($password);
            foreach ($db['users'] as &$uRef) {
                if ((int) $uRef['id'] === (int) $found['id']) { $uRef['password_hash'] = $newHash; break; }
            }
            unset($uRef);
            $found['password_hash'] = $newHash;
            save_db($DB_FILE, $BACKUP_DIR, $LATEST_JSON, $MAX_BACKUPS, $db);
        }

        // فقط برای مدیر لازمه سشنِ وردپرس ساخته بشه؛ کاربرِ عادی اصلاً وردپرس رو لود نمی‌کنه
        if (!empty($found['is_admin'])) {
            nexv_boot_wp();
            if (function_exists('nexv_bridge_wp_session')) { nexv_bridge_wp_session($found['phone']); }
        }
        echo json_encode(['success' => true, 'user' => public_user($found)], JSON_UNESCAPED_UNICODE);
        break;
    }

    /* ---------- خروج: هم حساب NEXV (سمت کاربر با جاوااسکریپت) هم سشن واقعی وردپرس (اگه بریج شده بود) پاک بشه ---------- */
    case 'logout': {
        // فقط اگه واقعاً سشنِ وردپرسی وجود داره (یعنی مدیر بوده) وردپرس لود می‌شه
        $hasWpCookie = false;
        foreach (array_keys($_COOKIE) as $ck) { if (strpos($ck, 'wordpress_logged_in') === 0) { $hasWpCookie = true; break; } }
        if ($hasWpCookie) { nexv_boot_wp(); }
        if (function_exists('wp_logout')) { wp_logout(); }
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        break;
    }

    /* ---------- ورود/ثبت‌نام با گوگل ---------- */
    case 'google_login': {
        $credential = $input['credential'] ?? '';
        if (!$credential) json_fail('توکن گوگل ارسال نشده است.');

        $verifyUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential);
        $ctx = stream_context_create(['http' => ['timeout' => 15]]);
        $response = @file_get_contents($verifyUrl, false, $ctx);
        if ($response === false) json_fail('اعتبارسنجی توکن گوگل ممکن نشد.', 500);

        $payload = json_decode($response, true);
        if (empty($payload['email']) || empty($payload['sub'])) {
            json_fail('توکن گوگل معتبر نیست.', 400);
        }
        if (GOOGLE_CLIENT_ID !== '' && ($payload['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
            json_fail('توکن گوگل برای این سایت صادر نشده است.', 400);
        }

        $email = strtolower($payload['email']);
        $googleId = $payload['sub'];
        $firstName = $payload['given_name'] ?? ($payload['name'] ?? 'کاربر');
        $lastName  = $payload['family_name'] ?? '';

        $found = null;
        foreach ($db['users'] as &$u) {
            if (($u['email'] ?? null) === $email || ($u['google_id'] ?? null) === $googleId) {
                $u['google_id'] = $googleId;
                $found = $u;
                break;
            }
        }
        unset($u);

        if (!$found) {
            $newUser = [
                'id' => $db['next_user_id'],
                'firstName' => $firstName,
                'lastName' => $lastName,
                'phone' => null,
                'email' => $email,
                'google_id' => $googleId,
                'password_hash' => null,
                'is_admin' => false,
                'created_at' => date('c'),
            ];
            $db['users'][] = $newUser;
            $db['next_user_id']++;
            $found = $newUser;
        }

        save_db($DB_FILE, $BACKUP_DIR, $LATEST_JSON, $MAX_BACKUPS, $db);
        echo json_encode(['success' => true, 'user' => public_user($found)], JSON_UNESCAPED_UNICODE);
        break;
    }

    /* ---------- ویرایش اطلاعات شخصی (نام، نام‌خانوادگی، تماس، ایمیل) ---------- */
    case 'update_profile': {
        $id = (int) ($input['id'] ?? 0);
        if (!$id) json_fail('ابتدا وارد حساب کاربری خود شوید.');

        $firstName = trim((string) ($input['firstName'] ?? ''));
        $lastName  = trim((string) ($input['lastName'] ?? ''));
        $phone     = trim((string) ($input['phone'] ?? ''));
        $email     = trim((string) ($input['email'] ?? ''));
        if ($firstName === '' || $lastName === '' || $phone === '') {
            json_fail('نام، نام‌خانوادگی و شماره تماس الزامی است.');
        }
        if (!preg_match('/^09\d{9}$/', $phone)) json_fail('شماره موبایل باید ۱۱ رقم و به‌صورت 09xxxxxxxxx باشد.');

        $updated = null;
        foreach ($db['users'] as &$u) {
            if ((int) $u['id'] === $id) {
                $u['firstName'] = $firstName;
                $u['lastName']  = $lastName;
                $u['phone']     = $phone;
                if ($email !== '') $u['email'] = $email;
                $updated = $u;
                break;
            }
        }
        unset($u);

        if (!$updated) json_fail('کاربر پیدا نشد.', 404);
        save_db($DB_FILE, $BACKUP_DIR, $LATEST_JSON, $MAX_BACKUPS, $db);
        echo json_encode(['success' => true, 'user' => public_user($updated)], JSON_UNESCAPED_UNICODE);
        break;
    }

    /* ---------- افزودن یک آدرس جدید ---------- */
    case 'add_address': {
        $id = (int) ($input['id'] ?? 0);
        if (!$id) json_fail('ابتدا وارد حساب کاربری خود شوید.');

        $addr = [
            'id'          => $db['next_address_id'],
            'label'       => trim((string) ($input['label'] ?? '')) ?: 'آدرس',
            'province'    => trim((string) ($input['province'] ?? '')),
            'city'        => trim((string) ($input['city'] ?? '')),
            'district'    => trim((string) ($input['district'] ?? '')),
            'plaque'      => trim((string) ($input['plaque'] ?? '')),
            'unit'        => trim((string) ($input['unit'] ?? '')),
            'postalCode'  => trim((string) ($input['postalCode'] ?? '')),
            'fullAddress' => trim((string) ($input['fullAddress'] ?? '')),
        ];
        foreach (['province', 'city', 'district', 'plaque', 'unit', 'postalCode', 'fullAddress'] as $f) {
            if ($addr[$f] === '') json_fail('لطفاً همه‌ی فیلدهای آدرس را کامل کنید.');
        }
        if (!preg_match('/^\d{10}$/', $addr['postalCode'])) json_fail('کد پستی باید دقیقاً ۱۰ رقم باشد.');

        $updated = null;
        foreach ($db['users'] as &$u) {
            if ((int) $u['id'] === $id) {
                if (!isset($u['addresses']) || !is_array($u['addresses'])) $u['addresses'] = [];
                $u['addresses'][] = $addr;
                $updated = $u;
                break;
            }
        }
        unset($u);
        if (!$updated) json_fail('کاربر پیدا نشد.', 404);
        $db['next_address_id']++;
        save_db($DB_FILE, $BACKUP_DIR, $LATEST_JSON, $MAX_BACKUPS, $db);
        echo json_encode(['success' => true, 'user' => public_user($updated)], JSON_UNESCAPED_UNICODE);
        break;
    }

    /* ---------- ویرایش یک آدرس موجود ---------- */
    case 'update_address': {
        $id = (int) ($input['id'] ?? 0);
        $addressId = (int) ($input['addressId'] ?? 0);
        if (!$id || !$addressId) json_fail('اطلاعات ناقص است.');
        if (isset($input['postalCode']) && !preg_match('/^\d{10}$/', trim((string) $input['postalCode']))) {
            json_fail('کد پستی باید دقیقاً ۱۰ رقم باشد.');
        }

        $updated = null;
        foreach ($db['users'] as &$u) {
            if ((int) $u['id'] === $id) {
                foreach ($u['addresses'] as &$a) {
                    if ((int) $a['id'] === $addressId) {
                        foreach (['label', 'province', 'city', 'district', 'plaque', 'unit', 'postalCode', 'fullAddress'] as $f) {
                            if (isset($input[$f])) $a[$f] = trim((string) $input[$f]);
                        }
                        break;
                    }
                }
                unset($a);
                $updated = $u;
                break;
            }
        }
        unset($u);
        if (!$updated) json_fail('کاربر یا آدرس پیدا نشد.', 404);
        save_db($DB_FILE, $BACKUP_DIR, $LATEST_JSON, $MAX_BACKUPS, $db);
        echo json_encode(['success' => true, 'user' => public_user($updated)], JSON_UNESCAPED_UNICODE);
        break;
    }

    /* ---------- حذف یک آدرس ---------- */
    case 'delete_address': {
        $id = (int) ($input['id'] ?? 0);
        $addressId = (int) ($input['addressId'] ?? 0);
        if (!$id || !$addressId) json_fail('اطلاعات ناقص است.');

        $updated = null;
        foreach ($db['users'] as &$u) {
            if ((int) $u['id'] === $id) {
                $u['addresses'] = array_values(array_filter($u['addresses'], function ($a) use ($addressId) { return (int) $a['id'] !== $addressId; }));
                $updated = $u;
                break;
            }
        }
        unset($u);
        if (!$updated) json_fail('کاربر پیدا نشد.', 404);
        save_db($DB_FILE, $BACKUP_DIR, $LATEST_JSON, $MAX_BACKUPS, $db);
        echo json_encode(['success' => true, 'user' => public_user($updated)], JSON_UNESCAPED_UNICODE);
        break;
    }

    /* ---------- ثبت سفارش (کارت‌به‌کارت) + بارگذاری تصویر رسید ---------- */
    case 'create_order': {
        $userId = (int) ($input['userId'] ?? 0);
        $addressId = (int) ($input['addressId'] ?? 0);
        $items  = $input['items'] ?? [];
        $shippingMethod = (string) ($input['shippingMethod'] ?? 'post');
        /* هزینه‌ی ارسال هرگز از سمت مرورگر پذیرفته نمی‌شه؛ سمتِ سرور و بر اساسِ
           همون روش‌های ارسالِ تعریف‌شده‌ی سایت محاسبه می‌شه (فقط پست هزینه داره،
           بقیه پس‌کرایه‌ان). مقدارِ پست با همونی که در سایت به مشتری نشون داده می‌شه یکیه. */
        $NEXV_SHIPPING_FEES = ['post' => 190000, 'tipax' => 0, 'mahex' => 0, 'peyk' => 0];
        $shippingFee = $NEXV_SHIPPING_FEES[$shippingMethod] ?? 190000;
        $itemsTotal = 0; // پایین‌تر از روی قیمتِ واقعیِ محصولات (نه ورودیِ کاربر) محاسبه می‌شه
        $receiptData = (string) ($input['receiptImage'] ?? '');

        if (!$userId) json_fail('ابتدا وارد حساب کاربری خود شوید.');
        if (!$addressId) json_fail('لطفاً یک آدرس برای ارسال انتخاب کنید.');
        if (empty($items) || !is_array($items)) json_fail('سبد خرید خالی است.');
        if ($receiptData === '') json_fail('لطفاً تصویر رسید واریزی را ارسال کنید.');

        $user = null;
        foreach ($db['users'] as $u) { if ((int) $u['id'] === $userId) { $user = $u; break; } }
        if (!$user) json_fail('کاربر پیدا نشد.', 404);

        $address = null;
        foreach (($user['addresses'] ?? []) as $a) { if ((int) $a['id'] === $addressId) { $address = $a; break; } }
        if (!$address) json_fail('آدرس انتخاب‌شده پیدا نشد.');

        if (!preg_match('/^data:image\/(png|jpe?g|webp);base64,(.+)$/i', $receiptData, $m)) {
            json_fail('فرمت تصویر رسید نامعتبر است.');
        }
        $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
        $bin = base64_decode($m[2]);
        if ($bin === false || strlen($bin) < 100) json_fail('تصویر رسید قابل خواندن نیست.');
        if (strlen($bin) > 6 * 1024 * 1024) json_fail('حجم تصویر رسید زیاد است (حداکثر ۶ مگابایت).');

        $orderId  = $db['next_order_id'];
        $filename = 'receipt_' . $orderId . '_' . time() . '_' . substr(md5(uniqid('', true)), 0, 6) . '.' . $ext;
        if (@file_put_contents($RECEIPTS_DIR . '/' . $filename, $bin) === false) {
            json_fail('ذخیره‌ی تصویر رسید ممکن نشد.', 500);
        }

        /* قیمتِ هر کالا از قیمتِ واقعیِ همون محصول در پیشخوان وردپرس خونده می‌شه،
           نه از مقداری که مرورگر فرستاده؛ این‌طوری کسی نمی‌تونه با دستکاریِ درخواست،
           محصول رو ارزون‌تر ثبت کنه. کلیدِ سبد ممکنه شاملِ متغیر (رنگ/حجم) باشه، پس
           شناسه‌ی پایه‌ی محصول از قبل از «::» جدا می‌شه. */
        $priceMap = [];
        $variantPriceMap = [];
        $wpProductsForOrder = function_exists('nexv_wp_products') ? nexv_wp_products() : [];
        if (is_array($wpProductsForOrder)) {
            foreach ($wpProductsForOrder as $wpp) {
                $priceMap[(string) $wpp['id']] = (int) $wpp['price'];
                $variantPriceMap[(string) $wpp['id']] = is_array($wpp['variantPrices'] ?? null) ? $wpp['variantPrices'] : [];
            }
        }
        $cleanItems = [];
        $computedTotal = 0;
        foreach ($items as $it) {
            $rawId  = (string) ($it['id'] ?? '');
            $baseId = explode('::', $rawId)[0];
            $qty    = max(1, (int) ($it['qty'] ?? 1));
            $price  = isset($priceMap[$baseId]) ? $priceMap[$baseId] : (int) ($it['price'] ?? 0);
            /* اگه این ترکیبِ متغیر قیمتِ اختصاصی داشته باشه، همون قیمت جایگزین می‌شه؛
               کلیدِ ترکیب از خودِ کلیدِ سبد بازخونی می‌شه، نه از عددی که مرورگر فرستاده. */
            $variantPrice = nexv_variant_price_from_cart_key($rawId, $variantPriceMap[$baseId] ?? []);
            if ($variantPrice > 0) $price = $variantPrice;
            $computedTotal += $price * $qty;
            $cleanItems[] = [
                'id'    => $rawId,
                'title' => (string) ($it['title'] ?? ''),
                'qty'   => $qty,
                'price' => $price,
            ];
        }
        $itemsTotal = $computedTotal;

        $order = [
            'id' => $orderId,
            'userId' => $userId,
            'customerName' => trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')),
            'phone' => $user['phone'] ?? '',
            'address' => $address,
            'items' => $cleanItems,
            'itemsTotal' => $itemsTotal,
            'shippingFee' => $shippingFee,
            'shippingMethod' => $shippingMethod,
            'grandTotal' => $itemsTotal + $shippingFee,
            'receiptUrl' => $RECEIPTS_URL . '/' . $filename,
            'status' => 'pending',
            'created_at' => date('c'),
        ];
        $db['orders'][] = $order;
        $db['next_order_id']++;
        save_db($DB_FILE, $BACKUP_DIR, $LATEST_JSON, $MAX_BACKUPS, $db);
        echo json_encode(['success' => true, 'order' => $order], JSON_UNESCAPED_UNICODE);
        break;
    }

    /* ---------- سفارش‌های خودِ مشتری (قسمت «سفارشات من») ---------- */
    case 'list_my_orders': {
        $userId = (int) ($_GET['userId'] ?? $input['userId'] ?? 0);
        if (!$userId) json_fail('ابتدا وارد حساب کاربری خود شوید.');
        $mine = array_values(array_filter($db['orders'], function ($o) use ($userId) { return (int) $o['userId'] === $userId; }));
        $mine = array_reverse($mine);
        echo json_encode(['success' => true, 'orders' => $mine], JSON_UNESCAPED_UNICODE);
        break;
    }

    /* ---------- پیشخوان مدیریت: فهرست سفارش‌ها (فقط ادمین) ---------- */
    case 'list_orders': {
        nexv_require_admin();
        $orders = array_reverse($db['orders']);
        echo json_encode(['success' => true, 'orders' => $orders], JSON_UNESCAPED_UNICODE);
        break;
    }

    /* ---------- پیشخوان مدیریت: صفحه‌ی قابل‌چاپِ فاکتور برای یک سفارش (فقط ادمین) ----------
       این اکشن به‌جای JSON، یک صفحه‌ی HTML مستقل و آماده‌ی چاپ (قواره‌ی A5) برمی‌گردونه؛
       ادمین از پیشخوان روش کلیک می‌کنه، توی تب جدید باز می‌شه، و از همون‌جا Ctrl+P یا
       دکمه‌ی چاپِ خودِ صفحه رو می‌زنه (خروجی چاپگر یا PDF، برای چسبوندن روی کارتن). */
    case 'invoice': {
        if (!(function_exists('current_user_can') && current_user_can('manage_options'))) { http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); echo 'دسترسی غیرمجاز.'; break; }

        $orderId = (int) ($_GET['order_id'] ?? 0);
        $order = null;
        foreach ($db['orders'] as $o) { if ((int) $o['id'] === $orderId) { $order = $o; break; } }
        if (!$order) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo 'سفارش پیدا نشد.'; break; }

        header('Content-Type: text/html; charset=utf-8');

        $siteNameEn = 'NEXV';
        $shipLabels = [
            'post'  => 'پست پیشتاز',
            'tipax' => 'تیپاکس (پس‌کرایه)',
            'mahex' => 'ماهکس (پس‌کرایه)',
            'peyk'  => 'پیک - فقط تهران (پس‌کرایه)',
        ];
        $shipMethod = $order['shippingMethod'] ?? 'post';
        $shipLabel = $shipLabels[$shipMethod] ?? 'پست پیشتاز';
        $isCod = $shipMethod !== 'post';

        /* شماره‌ی سفارش: پیشوندِ NEXV + عددِ یکتای همون سفارش (که مخصوص به همون مشتریه و
           تکراری نمی‌شه)، به‌فرمتِ ۶ رقمی برای یکدست بودنِ ظاهرِ فاکتورها. */
        $orderNumber = 'NEXV-' . str_pad((string) $order['id'], 6, '0', STR_PAD_LEFT);
        $fa_digits = ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹'];
        $faNum = function ($s) use ($fa_digits) { return strtr((string) $s, $fa_digits); };
        $fmt = function ($n) { return number_format((float) $n); };

        $createdAt = $order['created_at'] ?? date('c');
        $dateObj = date_create($createdAt) ?: new DateTime();
        [$jY, $jM, $jD] = nexv_gregorian_to_jalali((int) date_format($dateObj, 'Y'), (int) date_format($dateObj, 'n'), (int) date_format($dateObj, 'j'));
        $jDate = sprintf('%04d/%02d/%02d', $jY, $jM, $jD); // تاریخ شمسی
        $timeStr = date_format($dateObj, 'H:i');

        $address = $order['address'] ?? [];
        $addrProvince = $address['province'] ?? '';
        $addrCity = $address['city'] ?? '';
        $addrDetail = trim(
            (($address['district'] ?? '') !== '' ? ($address['district'] . '، ') : '')
            . (($address['fullAddress'] ?? ($address['line'] ?? ($address['full'] ?? ($address['text'] ?? '')))) ?? '')
            . (($address['plaque'] ?? '') !== '' ? ('، پلاک ' . $address['plaque']) : '')
            . (($address['unit'] ?? '') !== '' ? ('، واحد ' . $address['unit']) : '')
        );
        $postalCode = $address['postalCode'] ?? ($address['postal_code'] ?? '');

        $itemsHtml = '';
        foreach (($order['items'] ?? []) as $it) {
            $lineTotal = (int) $it['price'] * (int) $it['qty'];
            $itemsHtml .= '<tr>'
                . '<td class="c-title">' . htmlspecialchars($it['title'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="c-num">' . $faNum($it['qty']) . '</td>'
                . '<td class="c-num">' . $faNum($fmt($it['price'])) . '</td>'
                . '<td class="c-num">' . $faNum($fmt($lineTotal)) . '</td>'
                . '</tr>';
        }

        $shippingCell = $isCod ? 'پس‌کرایه (دریافت توسط باربری)' : ($faNum($fmt($order['shippingFee'])) . ' تومان');
        $grandTotalCell = $isCod
            ? ($faNum($fmt($order['itemsTotal'])) . ' تومان + پس‌کرایه')
            : ($faNum($fmt($order['grandTotal'])) . ' تومان');
        ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<title>فاکتور سفارش <?php echo htmlspecialchars($orderNumber); ?></title>
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css">
<style>
  @page { size: A5; margin: 10mm; }
  * { box-sizing: border-box; }
  body {
    font-family: 'Vazirmatn', Tahoma, sans-serif; margin: 0; padding: 14px; color: #201f2b; font-size: 12.5px;
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
  }
  .head { display: flex; align-items: center; gap: 10px; justify-content: flex-start; border-bottom: 3px solid #6a4dff; padding-bottom: 12px; margin-bottom: 16px; }
  .head .logo-dot { width: 30px; height: 30px; border-radius: 9px; background: linear-gradient(135deg,#6a4dff,#8f6bff); flex-shrink: 0; }
  .head .brand-en { font-family: 'Vazirmatn', Tahoma, sans-serif; font-size: 24px; font-weight: 800; letter-spacing: .3px; color: #201f2b; direction: ltr; line-height: 1.1; }
  .head .brand-en small { display: block; font-size: 10.5px; font-weight: 500; color: #7a7788; margin-top: 3px; direction: rtl; }
  h1 { font-size: 16px; margin: 0 0 12px; text-align: center; font-weight: 800; letter-spacing: .3px; }
  .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 7px 18px; background: #f7f6fb; border: 1px solid #e3e0f5; border-radius: 10px; padding: 12px 14px; margin-bottom: 14px; font-size: 11.5px; }
  .meta-grid div span.k { color: #7a7788; }
  .meta-grid div span.v { font-weight: 700; }
  .addr-block { grid-column: 1 / -1; display: flex; flex-direction: column; gap: 2px; }
  .addr-block .k { color: #7a7788; }
  .addr-block .addr-line { font-weight: 700; }
  .addr-block .addr-line.detail { font-weight: 500; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 11.5px; }
  th { background: #6a4dff; color: #fff; padding: 8px 6px; text-align: center; font-weight: 700; }
  th:first-child { border-radius: 6px 0 0 0; }
  th:last-child { border-radius: 0 6px 0 0; }
  td { padding: 7px 6px; border-bottom: 1px solid #eee; text-align: center; }
  td.c-title { text-align: right; font-weight: 600; }
  td.c-num, th.c-num { text-align: center; }
  tfoot td { font-weight: 700; border-top: 2px solid #6a4dff; border-bottom: none; }
  .totals { margin-top: -4px; margin-bottom: 14px; }
  .totals div { display: flex; justify-content: space-between; padding: 4px 4px; font-size: 12px; }
  .totals div.grand { font-weight: 800; font-size: 14.5px; color: #6a4dff; border-top: 1px dashed #ccc; padding-top: 8px; margin-top: 4px; }
  .foot-note { font-size: 10.5px; color: #7a7788; text-align: center; margin-top: 18px; border-top: 1px dashed #ccc; padding-top: 10px; }
  .print-btn { display: block; width: 100%; margin-bottom: 14px; padding: 10px; background: #6a4dff; color: #fff; border: none; border-radius: 8px; font-size: 13px; font-family: inherit; font-weight: 700; cursor: pointer; }
  @media print { .print-btn { display: none; } body { padding: 0; } }
</style>
</head>
<body>
  <button class="print-btn" onclick="window.print()">🖨 چاپ فاکتور / ذخیره PDF</button>

  <div class="head">
    <div class="logo-dot"></div>
    <div class="brand-en"><?php echo htmlspecialchars($siteNameEn); ?><small>فروشگاه لوازم و قطعات اپل</small></div>
  </div>

  <h1>فاکتور فروش</h1>

  <div class="meta-grid">
    <div><span class="k">شماره سفارش: </span><span class="v"><?php echo htmlspecialchars($orderNumber); ?></span></div>
    <div><span class="k">تاریخ: </span><span class="v"><?php echo $faNum($jDate) . ' - ' . $faNum($timeStr); ?></span></div>
    <div><span class="k">نام مشتری: </span><span class="v"><?php echo htmlspecialchars($order['customerName'] ?? ''); ?></span></div>
    <div><span class="k">شماره تماس: </span><span class="v" style="direction:ltr; display:inline-block;"><?php echo htmlspecialchars($order['phone'] ?? ''); ?></span></div>
    <div class="addr-block">
      <span class="k">آدرس:</span>
      <?php if ($addrProvince): ?><span class="addr-line"><?php echo htmlspecialchars($addrProvince); ?></span><?php endif; ?>
      <?php if ($addrCity): ?><span class="addr-line"><?php echo htmlspecialchars($addrCity); ?></span><?php endif; ?>
      <?php if ($addrDetail): ?><span class="addr-line detail"><?php echo htmlspecialchars($addrDetail); ?></span><?php endif; ?>
    </div>
    <?php if ($postalCode): ?><div><span class="k">کد پستی: </span><span class="v" style="direction:ltr; display:inline-block;"><?php echo htmlspecialchars($postalCode); ?></span></div><?php endif; ?>
    <div><span class="k">روش ارسال: </span><span class="v"><?php echo htmlspecialchars($shipLabel); ?></span></div>
  </div>

  <table>
    <thead>
      <tr><th>شرح کالا</th><th class="c-num">تعداد</th><th class="c-num">قیمت واحد (تومان)</th><th class="c-num">جمع (تومان)</th></tr>
    </thead>
    <tbody><?php echo $itemsHtml; ?></tbody>
  </table>

  <div class="totals">
    <div><span>جمع کالاها</span><span><?php echo $faNum($fmt($order['itemsTotal'])); ?> تومان</span></div>
    <div><span>هزینه ارسال</span><span><?php echo $shippingCell; ?></span></div>
    <div class="grand"><span>مبلغ نهایی</span><span><?php echo $grandTotalCell; ?></span></div>
  </div>

  <div class="foot-note">
    این فاکتور به‌صورت خودکار از فروشگاه <?php echo htmlspecialchars($siteNameEn); ?> صادر شده است. شماره سفارش را برای پیگیری نزد خود نگه دارید.
  </div>
</body>
</html>
<?php
        break;
    }

    /* ---------- پیشخوان مدیریت: فهرست مشتریان (فقط ادمین) ----------
       شامل رمز عبورِ خواناست (فقط برای این‌که در صورت فراموشی، مدیر بتونه
       به مشتری بگه رمزش چیه)؛ توجه: این یعنی رمزها اینجا به‌صورت متن ساده
       هم نگه داشته می‌شن، پس این فهرست باید فقط دست ادمین باشه. */
    case 'list_customers': {
        nexv_require_admin();
        $customers = [];
        foreach ($db['users'] as $u) {
            if (!empty($u['is_admin'])) continue;
            $customers[] = [
                'id' => $u['id'],
                'firstName' => $u['firstName'] ?? '',
                'lastName' => $u['lastName'] ?? '',
                'phone' => $u['phone'] ?? '',
                'email' => $u['email'] ?? '',
                'password' => $u['password_plain'] ?? '',
                'addresses' => $u['addresses'] ?? [],
                'created_at' => $u['created_at'] ?? '',
            ];
        }
        echo json_encode(['success' => true, 'customers' => array_reverse($customers)], JSON_UNESCAPED_UNICODE);
        break;
    }

    /* ---------- پیشخوان مدیریت: تغییر وضعیت سفارش (فقط ادمین) ---------- */
    case 'update_order_status': {
        nexv_require_admin();

        $orderId = (int) ($input['orderId'] ?? 0);
        $status  = (string) ($input['status'] ?? '');
        $validStatuses = ['pending', 'confirmed', 'rejected', 'packing', 'courier', 'shipped'];
        if (!in_array($status, $validStatuses, true)) json_fail('وضعیت نامعتبر است.');

        $found = false;
        foreach ($db['orders'] as &$o) {
            if ((int) $o['id'] === $orderId) { $o['status'] = $status; $found = true; break; }
        }
        unset($o);
        if (!$found) json_fail('سفارش پیدا نشد.', 404);
        save_db($DB_FILE, $BACKUP_DIR, $LATEST_JSON, $MAX_BACKUPS, $db);
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        break;
    }

    /* ---------- حذفِ کاملِ یک سفارش (فقط مدیر) ---------- */
    case 'delete_order': {
        nexv_require_admin();
        $orderId = (int) ($input['orderId'] ?? 0);
        $before = count($db['orders']);
        $removedReceipt = null;
        $db['orders'] = array_values(array_filter($db['orders'], function ($o) use ($orderId, &$removedReceipt) {
            if ((int) $o['id'] === $orderId) { $removedReceipt = $o['receiptUrl'] ?? null; return false; }
            return true;
        }));
        if (count($db['orders']) === $before) json_fail('سفارش پیدا نشد.', 404);
        // فایلِ رسیدِ همون سفارش هم پاک می‌شه تا روی هاست تلنبار نشه
        if ($removedReceipt) { @unlink($RECEIPTS_DIR . '/' . basename($removedReceipt)); }
        save_db($DB_FILE, $BACKUP_DIR, $LATEST_JSON, $MAX_BACKUPS, $db);
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        break;
    }

    case 'config':
        echo json_encode(['success' => true, 'googleClientId' => GOOGLE_CLIENT_ID], JSON_UNESCAPED_UNICODE);
        break;

    case 'backup_now':
        nexv_require_admin();
        save_db($DB_FILE, $BACKUP_DIR, $LATEST_JSON, $MAX_BACKUPS, $db);
        echo json_encode(['success' => true, 'message' => 'بکاپ گرفته شد.'], JSON_UNESCAPED_UNICODE);
        break;

    default:
        json_fail('اکشن نامعتبر است.', 404);
}
