<?php
if (!defined('ABSPATH')) { exit; }

/**
 * این پوسته به هیچ افزونه‌ای (از جمله ووکامرس) وابسته نیست.
 * صفحه‌ی اصلی (site.html) برای تمام آدرس‌های سایت بارگذاری می‌شه؛
 * فقط صفحه‌ی اختصاصیِ هر محصول (نوع پست nexv_product) با اطلاعات همان محصول نمایش داده می‌شه.
 */
/* این پوسته کاملاً اختصاصیه و نوار بالای سایت خودش رو به‌صورت position:fixed
   نمایش می‌ده؛ نوار مدیریت پیش‌فرض وردپرس (Admin Bar) با اون تداخل مکانی داره
   (باعث یک فاصله‌ی خالی بین نوار بنفش و هدر می‌شه)، پس برای بازدیدکننده‌های
   عادی غیرفعاله. برای مدیر سایت (شماره‌ای که در پیشخوان ← مشتریان به‌عنوان
   ادمین علامت خورده)، وقتی از همون فرم ورود سایت (نه wp-login.php) وارد بشه،
   یک سشن واقعی وردپرس هم پشت صحنه ساخته می‌شه که نوار مدیریت وردپرس، در حالت
   عادیِ بازدید از سایت، بالای صفحه نشونش بده. */
add_filter('show_admin_bar', function ($show) {
    return is_user_logged_in() && current_user_can('manage_options');
});

/* استایل اختصاصی نوار مدیریت (Admin Bar) بالای سایت — فقط برای همون مدیری که
   نوار براش نمایش داده می‌شه (شماره‌ی مالک سایت)، تیره و هماهنگ با برند سایت. */
add_action('wp_head', function () {
    if (!is_admin_bar_showing()) return;
    ?>
    <style>
        #wpadminbar{background:#1a1a1a !important; border-bottom:2px solid #8b6bff;}
        #wpadminbar .ab-item, #wpadminbar a.ab-item, #wpadminbar > #wp-toolbar span.ab-label, #wpadminbar > #wp-toolbar span.noticon{color:#e6e6e6 !important;}
        #wpadminbar .ab-icon, #wpadminbar .ab-icon:before{color:#8b6bff !important;}
        #wpadminbar .hover > .ab-item, #wpadminbar li:hover > .ab-item, #wpadminbar li.hover > .ab-item{background:#2a2a2a !important; color:#fff !important;}
        #wpadminbar #wp-admin-bar-my-account.with-avatar .avatar{border-radius:4px;}
        #wpadminbar #wp-admin-bar-top-secondary{direction:ltr;}
    </style>
    <?php
});

/* گزینه‌ی «ویرایش محصول» در نوار مدیریت، دقیقاً مثل ووکامرس، وقتی توی
   صفحه‌ی اختصاصیِ یک محصول (nexv_product) هستیم؛ کلیک روش می‌بره به همون
   صفحه‌ی ویرایش محصول در پیشخوان. چون این قالب صفحه‌ی محصول رو با کوئریِ
   استاندارد وردپرس (is_singular) میده ولی خودش the_post()/لوپ رو صدا نمی‌زنه،
   گره پیش‌فرض «Edit Post» وردپرس ممکنه ظاهر نشه؛ برای همین خودمون اضافه‌اش می‌کنیم. */
add_action('admin_bar_menu', function ($wp_admin_bar) {
    if (!function_exists('is_singular') || !is_singular('nexv_product')) return;
    $pid = get_queried_object_id();
    if (!$pid || !current_user_can('edit_post', $pid)) return;
    $edit_link = get_edit_post_link($pid, '');
    if (!$edit_link) return;
    $wp_admin_bar->add_node([
        'id'    => 'nexv-edit-product',
        'title' => 'ویرایش محصول',
        'href'  => $edit_link,
    ]);
}, 80);

/* شماره‌ای که با ورود از فرم سایت (نه پیشخوان)، به‌صورت خودکار هم یک سشن واقعی
   وردپرس (با نقش مدیر کل) براش ساخته/باز می‌شه تا نوار پیشخوان رو ببینه. */
define('NEXV_OWNER_PHONE', '09109973428');

/* $user: حسابِ NEXV (خروجیِ public_user در api.php). مدیرِ کل → حسابِ «nexv-owner» با نقشِ
   مدیر کل وردپرس؛ ادمینِ زیرمجموعه با دسترسیِ «محصولات» → حسابِ جدا با نقشِ «ویرایشگر»
   (فقط محصولات/دسته‌ها/مقالات؛ نه تنظیمات، افزونه‌ها یا به‌روزرسانیِ قالب). بقیه سشنِ وردپرس نمی‌گیرن. */
function nexv_wp_user_for_admin($user, $create = false) {
    if (!empty($user['is_owner'])) {
        $wp_user = get_user_by('login', 'nexv-owner') ?: get_user_by('email', 'owner@nexv.local');
        if (!$wp_user && $create) {
            $new_id = wp_insert_user([
                'user_login'   => 'nexv-owner',
                'user_pass'    => wp_generate_password(24, true, true),
                'user_email'   => 'owner@nexv.local',
                'display_name' => 'مدیر NEXV',
                'role'         => 'administrator',
            ]);
            if (!is_wp_error($new_id)) { $wp_user = get_user_by('id', $new_id); }
        }
        return $wp_user ?: null;
    }
    if (empty($user['id'])) return null;
    $login = 'nexv-admin-' . (int) $user['id'];
    $wp_user = get_user_by('login', $login);
    if (!$wp_user && $create) {
        $new_id = wp_insert_user([
            'user_login'   => $login,
            'user_pass'    => wp_generate_password(24, true, true),
            'user_email'   => 'admin-' . (int) $user['id'] . '@nexv.local',
            'display_name' => trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')) ?: $login,
            'role'         => 'editor',
        ]);
        if (!is_wp_error($new_id)) { $wp_user = get_user_by('id', $new_id); }
    }
    return $wp_user ?: null;
}
function nexv_admin_has_wp_access($user) {
    return !empty($user['is_admin']) && (!empty($user['is_owner']) || in_array('products', (array) ($user['perms'] ?? []), true));
}
/* نقشِ وردپرسیِ ادمین‌های زیرمجموعه با دسترسی‌هاشون هماهنگ می‌شه (برداشتنِ دسترسی = خروج از پیشخوان) */
function nexv_wp_admin_sync($user) {
    if (!empty($user['is_owner'])) return;
    $wp_user = nexv_wp_user_for_admin($user, nexv_admin_has_wp_access($user));
    if (!$wp_user) return;
    if (nexv_admin_has_wp_access($user)) {
        if (!in_array('editor', (array) $wp_user->roles, true)) $wp_user->set_role('editor');
    } else {
        $wp_user->set_role('subscriber');
        if (class_exists('WP_Session_Tokens')) WP_Session_Tokens::get_instance($wp_user->ID)->destroy_all();
    }
}
/* بعد از تغییرِ رمز: بقیه‌ی سشن‌های وردپرسِ همین ادمین بسته می‌شن (سشنِ همین مرورگر می‌مونه) */
function nexv_wp_admin_logout_others($user) {
    $wp_user = nexv_wp_user_for_admin($user, false);
    if (!$wp_user || !class_exists('WP_Session_Tokens')) return;
    $mgr = WP_Session_Tokens::get_instance($wp_user->ID);
    if (get_current_user_id() === (int) $wp_user->ID && function_exists('wp_get_session_token') && wp_get_session_token()) $mgr->destroy_others(wp_get_session_token());
    else $mgr->destroy_all();
}
function nexv_bridge_wp_session($phone, $user = null) {
    if ($user === null) {
        if ($phone !== NEXV_OWNER_PHONE) return;
        $user = ['is_admin' => true, 'is_owner' => true];
    }
    if (!nexv_admin_has_wp_access($user)) return;
    nexv_wp_admin_sync($user);
    $wp_user = nexv_wp_user_for_admin($user, true);
    if ($wp_user && !is_wp_error($wp_user)) {
        wp_clear_auth_cookie();
        wp_set_current_user($wp_user->ID);
        wp_set_auth_cookie($wp_user->ID, true);
    }
}
/* رمزِ مدیرِ کل عوض شد (مهر ۱۴۰۵): یک‌بار همه‌ی سشن‌های قبلیِ پیشخوانِ مدیر بسته می‌شن،
   به‌جز سشنِ همین مرورگری که الان داره قالب رو به‌روزرسانی می‌کنه */
add_action('init', function () {
    if (get_option('nexv_owner_sessions_reset_202610')) return;
    update_option('nexv_owner_sessions_reset_202610', time(), false);
    $wp_user = get_user_by('login', 'nexv-owner');
    if (!$wp_user || !class_exists('WP_Session_Tokens')) return;
    $mgr = WP_Session_Tokens::get_instance($wp_user->ID);
    if (get_current_user_id() === (int) $wp_user->ID && wp_get_session_token()) $mgr->destroy_others(wp_get_session_token());
    else $mgr->destroy_all();
}, 20);

function nexv_theme_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
}
add_action('after_setup_theme', 'nexv_theme_setup');

function nexv_force_custom_template($template) {
    $custom = get_template_directory() . '/index.php';
    return file_exists($custom) ? $custom : $template;
}
add_filter('template_include', 'nexv_force_custom_template', 99);

/* =========================================================================
 * نوع پست «محصول» + دسته‌بندیِ درختی (سلسله‌مراتبی) — دقیقاً مثل دسته‌بندی‌های
 * معمولیِ وردپرس: هر دسته می‌تونه دسته‌ی والد داشته باشه (مثلاً دسته‌ی اول
 * «آیفون» و «آیپد»، و زیرمجموعه‌ی جدا برای هرکدوم). این تاکسونومی از پیشخوانِ
 * وردپرس (محصولات ← دسته‌بندی‌ها) مدیریت می‌شه و فیلتر پیشرفته‌ی سایت خودش رو
 * دقیقاً بر همین ساختار می‌سازه.
 * ========================================================================= */
function nexv_register_product_cpt() {
    register_post_type('nexv_product', [
        'labels' => [
            'name'               => 'محصولات',
            'singular_name'      => 'محصول',
            'add_new'            => 'افزودن محصول',
            'add_new_item'       => 'افزودن محصول جدید',
            'edit_item'          => 'ویرایش محصول',
            'new_item'           => 'محصول جدید',
            'view_item'          => 'مشاهده محصول',
            'all_items'          => 'همه‌ی محصولات',
            'search_items'       => 'جستجوی محصول',
            'not_found'          => 'محصولی پیدا نشد.',
            'menu_name'          => 'محصولات NEXV',
        ],
        'public'        => true,
        'has_archive'   => false,
        'show_in_menu'  => 'nexv-dashboard',
        'show_in_rest'  => true,
        'menu_icon'     => 'dashicons-cart',
        'supports'      => ['title', 'thumbnail', 'editor', 'comments'],
        'rewrite'       => ['slug' => 'product'],
    ]);
}
add_action('init', 'nexv_register_product_cpt');

/* =========================================================================
 * نوع پست «مقاله»: برای بخش وبلاگ/مقالات سایت. از ادیتور استاندارد وردپرس
 * استفاده می‌کنه (همون صفحه‌ی افزودن/ویرایش نوشته‌ی معمولی وردپرس) که خودش
 * امکان قراردادن عکس وسط متن رو داره (دکمه‌ی «افزودن رسانه» / بلوک تصویر).
 * ========================================================================= */
function nexv_register_article_cpt() {
    register_post_type('nexv_article', [
        'labels' => [
            'name'               => 'مقالات',
            'singular_name'      => 'مقاله',
            'add_new'            => 'افزودن مقاله',
            'add_new_item'       => 'افزودن مقاله‌ی جدید',
            'edit_item'          => 'ویرایش مقاله',
            'new_item'           => 'مقاله‌ی جدید',
            'view_item'          => 'مشاهده‌ی مقاله',
            'all_items'          => 'همه‌ی مقالات',
            'search_items'       => 'جستجوی مقاله',
            'not_found'          => 'مقاله‌ای پیدا نشد.',
            'menu_name'          => 'مقالات NEXV',
        ],
        'public'        => true,
        'has_archive'   => 'blog',
        'show_in_menu'  => 'nexv-dashboard',
        'show_in_rest'  => true,
        'menu_icon'     => 'dashicons-media-text',
        'supports'      => ['title', 'thumbnail', 'editor', 'excerpt'],
        'rewrite'       => ['slug' => 'article'],
    ]);
}
/* با تغییرِ has_archive/rewrite بالا، قوانینِ لینک‌دهیِ وردپرس باید یک‌بار
   دوباره ساخته بشن، وگرنه nexv.ir/blog موقتاً ۴۰۴ می‌ده. */
add_action('init', function () {
    if (get_option('nexv_rewrite_flushed_v2')) return;
    flush_rewrite_rules();
    update_option('nexv_rewrite_flushed_v2', 1);
}, 20);
/* ساختنِ خودکارِ صفحه‌ی «درباره ما» (فقط یک‌بار، اگه از قبل نساخته باشیم)؛
   پیوندش انگلیسیه: nexv.ir/about — طبق قانونِ پروژه برای همه‌ی URLها. */
add_action('init', function () {
    if (get_option('nexv_about_page_created')) return;
    $existing = get_page_by_path('about');
    if (!$existing) {
        wp_insert_post([
            'post_type'   => 'page',
            'post_title'  => 'درباره ما',
            'post_name'   => 'about',
            'post_status' => 'publish',
            'post_content' => '',
        ]);
    }
    update_option('nexv_about_page_created', 1);
});
/* ساختنِ خودکارِ صفحه‌ی «قوانین و مقررات» با پیوندِ nexv.ir/terms (فقط یک‌بار).
   محتواش از قالب خونده می‌شه، پس این صفحه فقط برای داشتنِ پیوند لازمه. */
add_action('init', function () {
    if (get_option('nexv_terms_page_created')) return;
    if (!get_page_by_path('terms')) {
        wp_insert_post([
            'post_type'   => 'page',
            'post_title'  => 'قوانین و مقررات',
            'post_name'   => 'terms',
            'post_status' => 'publish',
            'post_content' => '',
        ]);
    }
    update_option('nexv_terms_page_created', 1);
});
add_action('init', 'nexv_register_article_cpt');

/* حذف خودکارِ نوشته‌ی پیش‌فرضِ «سلام دنیا»/Hello World که خودِ وردپرس موقع نصب
   می‌سازه؛ این نوشته ربطی به سایت نداره و طبق درخواست باید کامل حذف بشه. */
add_action('init', function () {
    if (get_option('nexv_hello_world_removed')) return;
    $candidates = get_posts([
        'post_type'   => 'post',
        'post_status' => 'any',
        'numberposts' => 5,
        's'           => 'Hello world',
    ]);
    foreach ($candidates as $p) {
        if (in_array(strtolower($p->post_title), ['hello world!', 'hello world', 'سلام دنیا', 'سلام دنیا!'], true)) {
            wp_delete_post($p->ID, true);
        }
    }
    // اسلاگ فارسیِ احتمالی رو هم جدا چک کن (چون s= بالا فقط عنوان انگلیسی رو پیدا می‌کنه)
    $bySlug = get_page_by_path('سلام-دنیا', OBJECT, 'post');
    if ($bySlug) wp_delete_post($bySlug->ID, true);

    update_option('nexv_hello_world_removed', 1);
});

/* =========================================================================
 * قانون طلایی پروژه: هیچ محصول یا مقاله‌ای نباید Permalink فارسی داشته باشه.
 * چون عنوان‌ها فارسی هستن (مثلاً «باتری آیفون 6»)، وردپرس به‌صورت پیش‌فرض یا
 * همون فارسی رو Percent-Encode می‌کنه توی اسلاگ، یا (بسته به تنظیمات هاست)
 * اسلاگ خالی/عددی می‌سازه که باعث تداخل و بازنشدنِ بعضی صفحات می‌شه.
 * این تابع از روی عنوانِ فارسیِ محصول/مقاله (نوع قطعه + دستگاه + مدل)
 * یه اسلاگِ کاملاً انگلیسیِ خوانا می‌سازه، مثلاً:
 *   «باتری آیفون 6»              -> iphone-6-battery
 *   «مادربرد آیپد پرو 12.9 اینچ (نسل 3)» -> ipad-pro-12-9-gen-3-motherboard
 *   «بدنه (شاسی) اپل واچ سری 7 45 میلی‌متر» -> apple-watch-series-7-45mm-body
 * ========================================================================= */
function nexv_transliterate_title_to_slug($title) {
    $t = (string) $title;

    // اعداد فارسی/عربی -> انگلیسی
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    $t = str_replace($fa, $en, $t);
    $t = str_replace($ar, $en, $t);

    // واژه‌های تکراری/رایج -> معادل انگلیسی (ترتیب مهمه: عبارت‌های بلندتر اول)
    $map = [
        'ال سی دی' => 'lcd', 'السی\u200cدی' => 'lcd', 'ال‌سی‌دی' => 'lcd',
        'لوازم جانبی' => 'accessories', 'قطعات' => 'parts', 'خرید' => 'buy',
        'دوربین جلو' => 'front-camera', 'دوربین پشت' => 'rear-camera', 'دوربین' => 'camera',
        'جلو' => 'front', 'پشت' => 'rear', 'تاچ' => 'touch', 'فلت' => 'flex', 'اسپیکر' => 'speaker',
        'بلندگو' => 'speaker', 'میکروفون' => 'microphone', 'سوکت شارژ' => 'charging-port', 'سوکت' => 'connector',
        'شارژر' => 'charger', 'کابل' => 'cable', 'قاب' => 'case', 'گلس' => 'glass', 'لنز' => 'lens',
        'مادربرد' => 'motherboard',
        'باتری' => 'battery',
        'بدنه (شاسی)' => 'body', 'بدنه(شاسی)' => 'body', 'بدنه' => 'body', 'شاسی' => 'chassis',
        'اپل واچ' => 'apple-watch',
        'آیفون' => 'iphone', 'ایفون' => 'iphone',
        'آیپد' => 'ipad', 'ایپد' => 'ipad',
        'پرو مکس' => 'pro-max', 'پرو' => 'pro', 'پلاس' => 'plus', 'مکس' => 'max', 'مینی' => 'mini',
        'استاندارد' => 'standard', 'ایر' => 'air', 'اولترا' => 'ultra',
        'نسل' => 'gen', 'سری' => 'series',
        'میلی‌متر' => 'mm', 'میلی متر' => 'mm', 'میلیمتر' => 'mm',
        'اینچ' => 'inch',
        'رو کاری' => 'refurb', 'های کپی' => 'copy', 'اورجینال' => 'original',
    ];
    $t = strtr($t, $map);

    // نقطه‌ی اعشاری (۱۲.۹ اینچ) به خط تیره تبدیل بشه که در Slug مشکل ایجاد نکنه
    $t = str_replace('.', '-', $t);

    /* هر واژه‌ی فارسیِ دیگه‌ای که تو جدولِ بالا نبود، حرف‌به‌حرف به لاتین برگردونده می‌شه.
       قبلاً این حروف به sanitize_title می‌رسیدن و به‌صورتِ Percent-Encoded (%d8%a8...)
       توی اسلاگ می‌موندن؛ یعنی پیوندِ یکتا نصفه‌فارسی و ناخوانا می‌شد. */
    $letters = [
        'آ'=>'a','ا'=>'a','أ'=>'a','إ'=>'e','ب'=>'b','پ'=>'p','ت'=>'t','ث'=>'s','ج'=>'j','چ'=>'ch',
        'ح'=>'h','خ'=>'kh','د'=>'d','ذ'=>'z','ر'=>'r','ز'=>'z','ژ'=>'zh','س'=>'s','ش'=>'sh','ص'=>'s',
        'ض'=>'z','ط'=>'t','ظ'=>'z','ع'=>'a','غ'=>'gh','ف'=>'f','ق'=>'gh','ک'=>'k','ك'=>'k','گ'=>'g',
        'ل'=>'l','م'=>'m','ن'=>'n','و'=>'v','ه'=>'h','ة'=>'h','ی'=>'y','ي'=>'y','ئ'=>'y','ء'=>'',
        'َ'=>'','ِ'=>'','ُ'=>'','ً'=>'','ٍ'=>'','ٌ'=>'','ّ'=>'','ْ'=>'','‌'=>'-','ٔ'=>'',
    ];
    $t = strtr($t, $letters);

    $slug = sanitize_title($t);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'item-' . substr(md5($title), 0, 6);
}

/* هر بار محصول یا مقاله‌ای ذخیره می‌شه (چه از پیشخوان، چه از درون‌ریزیِ CSV)،
   اسلاگش با تابع بالا به انگلیسی اجباری می‌شه؛ wp_unique_post_slug خودِ
   وردپرس هم در صورت تکراری‌بودن، به‌صورت خودکار -2 و -3 و... اضافه می‌کنه. */
add_filter('wp_insert_post_data', function ($data, $postarr) {
    if (in_array($data['post_type'], ['nexv_product', 'nexv_article'], true) && $data['post_status'] !== 'auto-draft') {
        // اگه post_name از قبل توسط خودمون (تابعِ بازتولیدِ دستیِ اسلاگ) صریحاً انگلیسی ست شده، دست نمی‌زنیم.
        /* اسلاگی که وردپرس از عنوانِ فارسی می‌سازه Percent-Encoded ئه (مثل %d8%a8...)
           که با اینکه فقط از حروفِ ASCII تشکیل شده، در واقع همون فارسیِ کدشده‌ست؛ پس
           وجودِ «%» یعنی هنوز انگلیسیِ خوانا نیست و باید نویسه‌گردانی بشه. */
        $alreadyAscii = $data['post_name'] !== '' && strpos($data['post_name'], '%') === false && $data['post_name'] === sanitize_title($data['post_name']) && !preg_match('/[^\x00-\x7f]/', $data['post_name']);
        if (!$alreadyAscii) {
            $baseSlug = nexv_slug_from_title($data['post_title']);
            $postId = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
            $data['post_name'] = wp_unique_post_slug($baseSlug, $postId, $data['post_status'], $data['post_type'], $data['post_parent'] ?? 0);
        }
    }
    return $data;
}, 10, 2);

/* ---------- دسترسی به دیتابیس JSON خودِ NEXV (همون فایلی که api.php استفاده می‌کنه) ---------- */
function nexv_data_file_path() {
    $dir = null;
    if (function_exists('wp_upload_dir')) {
        $u = wp_upload_dir();
        if (!empty($u['basedir']) && is_writable($u['basedir'])) { $dir = $u['basedir'] . '/nexv-data'; }
    }
    if (!$dir) { $dir = get_template_directory() . '/data'; }
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    return $dir . '/db.json';
}
function nexv_read_data() {
    $file = nexv_data_file_path();
    if (!file_exists($file)) return ['users' => [], 'orders' => [], 'products' => [], 'next_user_id' => 2, 'next_order_id' => 1, 'next_address_id' => 1];
    $raw = file_get_contents($file);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : ['users' => []];
}
function nexv_write_data($data) {
    $file = nexv_data_file_path();
    // یک نسخه‌ی پشتیبان قبل از نوشتن، برای احتیاط
    if (file_exists($file)) {
        @copy($file, $file . '.bak-' . date('Ymd-His'));
        // فقط ۱۰ بکاپِ آخر نگه داشته می‌شه تا این فایل‌ها بی‌نهایت روی هاست تلنبار نشن.
        $baks = glob($file . '.bak-*');
        if ($baks && count($baks) > 10) {
            usort($baks, function ($a, $b) { return filemtime($a) <=> filemtime($b); });
            foreach (array_slice($baks, 0, count($baks) - 10) as $old) { @unlink($old); }
        }
    }
    file_put_contents($file, wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
function nexv_get_all_customers() {
    $data = nexv_read_data();
    $users = $data['users'] ?? [];
    return array_values(array_filter($users, function ($u) { return empty($u['is_admin']); }));
}
/* درون‌ریزیِ ردیف‌های مشتری: با شماره تماس پیدا/به‌روزرسانی می‌کنه؛ اگه نبود، مشتری جدید با یک
   رمز عبور پیش‌فرض تصادفی می‌سازه (بعداً از پیشخوان ← مشتریان قابل مشاهده‌ست). */
function nexv_import_customers_rows($rows) {
    $data = nexv_read_data();
    if (!isset($data['users'])) $data['users'] = [];
    if (!isset($data['next_user_id'])) $data['next_user_id'] = 2;
    $count = 0;
    foreach ($rows as $row) {
        $phone = $row['phone'];
        $found = null;
        foreach ($data['users'] as &$u) {
            if (($u['phone'] ?? '') === $phone) { $found = &$u; break; }
        }
        unset($u);
        if ($found) {
            if (!empty($row['firstName'])) $found['firstName'] = $row['firstName'];
            if (!empty($row['lastName']))  $found['lastName']  = $row['lastName'];
            if (!empty($row['email']))     $found['email']     = $row['email'];
            $target = &$found;
        } else {
            $randomPass = wp_generate_password(8, false);
            $data['users'][] = [
                'id' => $data['next_user_id'],
                'firstName' => $row['firstName'] ?: '',
                'lastName'  => $row['lastName'] ?: '',
                'phone' => $phone,
                'email' => $row['email'] ?: null,
                'google_id' => null,
                'password_hash' => nexv_hash_password_fallback($randomPass),
                'password_plain' => $randomPass,
                'is_admin' => false,
                'addresses' => [],
                'created_at' => current_time('c'),
            ];
            $data['next_user_id']++;
            $target = &$data['users'][count($data['users']) - 1];
        }
        if (!empty($row['address1']) || !empty($row['city'])) {
            if (!isset($data['next_address_id'])) $data['next_address_id'] = 1;
            if (!isset($target['addresses'])) $target['addresses'] = [];
            $target['addresses'][] = [
                'id' => $data['next_address_id']++,
                'label' => 'آدرس (درون‌ریزی‌شده)',
                'province' => $row['province'] ?: '',
                'city' => $row['city'] ?: '',
                'district' => '',
                'plaque' => '',
                'unit' => '',
                'postalCode' => $row['postalCode'] ?: '',
                'fullAddress' => $row['address1'] ?: '',
            ];
        }
        unset($target);
        $count++;
    }
    nexv_write_data($data);
    return $count;
}

/* ---------- منوی یکپارچه‌ی «NEXV» در پیشخوان ----------
   محصولات، دسته‌بندی‌ها، کاربران و دیدگاه‌ها همه زیرمجموعه‌ی همین یک منو
   می‌شن تا نوار کناریِ پیشخوان تمیزتر و مرتب‌تر بمونه. */
add_action('admin_menu', function () {
    add_menu_page(
        'NEXV',
        'NEXV',
        'edit_posts',
        'nexv-dashboard',
        function () {
            echo '<div class="wrap"><h1>پیشخوان NEXV</h1><p>از منوی کنار، محصولات، دسته‌بندی‌ها، کاربران و دیدگاه‌ها رو مدیریت کن.</p></div>';
        },
        'dashicons-store',
        3
    );
    add_submenu_page('nexv-dashboard', 'دسته‌بندی محصولات', 'دسته‌بندی محصولات', 'manage_categories', 'edit-tags.php?taxonomy=nexv_cat&post_type=nexv_product');
    add_submenu_page('nexv-dashboard', 'کاربران', 'کاربران', 'list_users', 'users.php');
    add_submenu_page('nexv-dashboard', 'دیدگاه‌ها', 'دیدگاه‌ها', 'edit_posts', 'edit-comments.php');
    add_submenu_page('nexv-dashboard', 'درون‌ریزی / برون‌ریزی', 'درون‌ریزی / برون‌ریزی', 'manage_options', 'nexv-import-export', 'nexv_import_export_page_html');
}, 9);

/* =========================================================================
 * درون‌ریزی / برون‌ریزی محصولات و مشتریان (فرمتِ CSV، مثل ووکامرس)
 * ========================================================================= */
/* ---------- یک بخشِ درون‌ریزی/برون‌ریزی با ۴ دکمه‌ی یکسان ----------
   ۱. انتخاب فایل CSV  ۲. درون‌ریزی (CSV)  ۳. برون‌ریزی (CSV)  ۴. حذف همه
   «حذف همه» در هر بخش فقط داده‌های همون بخش رو پاک می‌کنه، نه چیز دیگه‌ای از سایت. */
function nexv_ie_section($key, $title, $help_html, $delete_confirm, $extra_html = '') {
    $import_action = 'nexv_import_' . $key;
    $export_action = 'nexv_export_' . $key;
    $delete_action = 'nexv_delete_all_' . $key;
    $file_id = 'nexv_csv_' . $key;
    ?>
    <div class="nexv-ie-card">
        <h2><?php echo esc_html($title); ?></h2>
        <?php if ($help_html) : ?><div class="nexv-ie-help"><?php echo $help_html; ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field($import_action); ?>
            <input type="hidden" name="action" value="<?php echo esc_attr($import_action); ?>">
            <?php /* input فایل مخفیه و با دکمه‌ی معمولی باز می‌شه (رفعِ باگِ کلیک‌نشدن روی سافاری موبایل). */ ?>
            <input type="file" name="nexv_csv" id="<?php echo esc_attr($file_id); ?>" accept=".csv,text/csv" required
                   style="display:none;"
                   onchange="document.getElementById('<?php echo esc_js($file_id); ?>_name').textContent = this.files.length ? this.files[0].name : 'فایلی انتخاب نشده';">
            <?php echo $extra_html; ?>
            <div class="nexv-ie-buttons">
                <button type="button" class="button" onclick="document.getElementById('<?php echo esc_js($file_id); ?>').click();">۱. انتخاب فایل CSV</button>
                <button type="submit" class="button button-primary">۲. درون‌ریزی (CSV)</button>
                <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=' . $export_action), $export_action)); ?>">۳. برون‌ریزی (CSV)</a>
                <a class="button nexv-ie-delete"
                   href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=' . $delete_action), $delete_action)); ?>"
                   onclick="return confirm(<?php echo esc_attr(wp_json_encode($delete_confirm, JSON_UNESCAPED_UNICODE)); ?>);">۴. حذف همه</a>
            </div>
            <p class="nexv-ie-filename" id="<?php echo esc_attr($file_id); ?>_name">فایلی انتخاب نشده</p>
        </form>
    </div>
    <?php
}

function nexv_import_export_page_html() {
    $imported = isset($_GET['imported']) ? (int) $_GET['imported'] : null;
    $type = sanitize_key($_GET['type'] ?? '');
    ?>
    <div class="wrap">
        <style>
            .nexv-ie-grid{display:grid; grid-template-columns:repeat(auto-fill,minmax(420px,1fr)); gap:18px; margin-top:16px;}
            @media (max-width:782px){ .nexv-ie-grid{grid-template-columns:1fr;} }
            .nexv-ie-card{background:#fff; border:1px solid #dcdcde; border-radius:10px; padding:16px 18px;}
            .nexv-ie-card h2{margin:0 0 8px; font-size:17px;}
            .nexv-ie-help{color:#50575e; font-size:12.5px; line-height:1.9; margin-bottom:10px;}
            .nexv-ie-help p{margin:0 0 6px;}
            .nexv-ie-buttons{display:flex; flex-wrap:wrap; gap:8px; margin-top:10px;}
            .nexv-ie-buttons .button{margin:0;}
            .nexv-ie-delete{color:#b32d2e !important; border-color:#b32d2e !important;}
            .nexv-ie-delete:hover{background:#fcf0f1 !important;}
            .nexv-ie-filename{margin:8px 0 0; color:#666; font-size:12px;}
        </style>
        <h1>درون‌ریزی / برون‌ریزی</h1>

        <?php /* ---------- پیام‌های نتیجه ---------- */ ?>
        <?php if (isset($_GET['deleted'])): ?>
            <div class="notice notice-success"><p><?php echo esc_html((int) $_GET['deleted']); ?> محصول حذف شد.</p></div>
        <?php endif; ?>
        <?php if (isset($_GET['catsdeleted'])): ?>
            <div class="notice notice-success"><p><?php echo esc_html((int) $_GET['catsdeleted']); ?> دسته‌بندی حذف شد.</p></div>
        <?php endif; ?>
        <?php if (isset($_GET['articlesdeleted'])): ?>
            <div class="notice notice-success"><p><?php echo esc_html((int) $_GET['articlesdeleted']); ?> مقاله حذف شد.</p></div>
        <?php endif; ?>
        <?php if (isset($_GET['usersdeleted'])): ?>
            <div class="notice notice-success"><p><?php echo esc_html((int) $_GET['usersdeleted']); ?> کاربر حذف شد.</p></div>
        <?php endif; ?>
        <?php if (isset($_GET['catsimported'])):
            $result = get_transient('nexv_cat_import_result');
            delete_transient('nexv_cat_import_result');
            $errs = is_array($result) ? ($result['errors'] ?? []) : [];
        ?>
            <div class="notice <?php echo (int) $_GET['catsimported'] > 0 ? 'notice-success' : 'notice-error'; ?>">
                <p><?php echo esc_html((int) $_GET['catsimported']); ?> دسته‌بندی از CSV درون‌ریزی/به‌روزرسانی شد.</p>
                <?php if ($errs): ?>
                    <p><strong>مواردی که وارد نشدن:</strong></p>
                    <ul style="list-style:disc; margin-inline-start:20px;">
                        <?php foreach ($errs as $e): ?><li><?php echo esc_html($e); ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($imported !== null):
            $typeLabels = ['customers' => 'کاربر', 'articles' => 'مقاله', 'products' => 'محصول'];
        ?>
            <div class="notice notice-success"><p><?php echo esc_html($imported); ?> مورد (<?php echo esc_html($typeLabels[$type] ?? 'محصول'); ?>) با موفقیت درون‌ریزی شد.</p></div>
            <?php if ($type === 'products' && !empty($_GET['skippedcats']) && (int) $_GET['skippedcats'] > 0): ?>
                <?php $missing = get_transient('nexv_missing_cats'); delete_transient('nexv_missing_cats'); ?>
                <div class="notice notice-warning">
                    <p>⚠️ <?php echo esc_html((int) $_GET['skippedcats']); ?> ارجاعِ دسته‌بندی در فایل با هیچ دسته‌بندیِ موجودی مطابقت نداشت و محصولِ مربوطه بدون اون دسته ثبت شد.</p>
                    <?php if (is_array($missing) && $missing): ?>
                        <p><strong>دسته‌هایی که پیدا نشدن:</strong></p>
                        <ul style="list-style:disc; margin-inline-start:20px;">
                            <?php foreach ($missing as $m): ?><li><?php echo esc_html($m); ?></li><?php endforeach; ?>
                        </ul>
                        <p>اول این‌ها رو در فایلِ دسته‌بندی‌ها اضافه و import کن، بعد دوباره محصولات رو import کن.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (isset($_GET['cachecleared'])): ?>
            <div class="notice notice-success"><p>کشِ محصولات و دسته‌بندی‌ها پاک شد.</p></div>
        <?php endif; ?>
        <?php if (isset($_GET['slugsfixed'])): ?>
            <div class="notice notice-success"><p>پیوند یکتای <?php echo (int) $_GET['slugsfixed']; ?> دسته‌بندی/محصول/مقاله به انگلیسی تبدیل شد.</p></div>
        <?php endif; ?>

        <div class="nexv-ie-grid">
        <?php
        /* ۱. محصولات */
        nexv_ie_section(
            'products',
            '۱. محصولات',
            '<p>فرمت مثل CSV محصولات ووکامرس (Name/نام، Regular price/قیمت اصلی، Short description، Description، Categories، Images، Slug و ...). گزینه‌ها: <code>Color Options</code>، <code>Storage Options</code> و <code>Variant Groups</code> به فرمت <code>نوع:روکاری اورجینال|های کپی;رنگ:سفید|مشکی</code>.</p>'
            . '<p>⚠️ ستون <code>Categories</code> باید مسیرِ کاملِ دسته‌ای باشه که از قبل ساخته شده (مثلاً <code>آیفون &gt; قطعات آیفون &gt; آیفون 6</code>)؛ درون‌ریزی دسته‌ی جدید نمی‌سازه.</p>',
            'مطمئنی؟ «همه‌ی محصولات» برای همیشه حذف می‌شن و قابل بازگشت نیست. دسته‌بندی‌ها، مقالات، کاربران، سفارش‌ها و تصاویرِ کتابخانه‌ی رسانه دست‌نخورده می‌مونن.',
            '<label style="display:block; font-size:12.5px; margin-top:4px;"><input type="checkbox" name="nexv_download_images" value="1"> تصاویر در کتابخانه‌ی رسانه‌ی سایت دانلود بشن (کندتر)</label>'
        );

        /* ۲. دسته‌بندی */
        nexv_ie_section(
            'categories',
            '۲. دسته‌بندی',
            '<p>ستون‌ها: <code>Name</code>، <code>Slug</code>، <code>Parent</code> (نام یا مسیرِ دسته‌ی والد با « &gt; »، خالی برای سطح اول)، <code>Description</code>، <code>Image</code>. خروجیِ همین بخش رو می‌تونی مستقیم دوباره درون‌ریزی کنی.</p>',
            'مطمئنی؟ «همه‌ی دسته‌بندی‌های محصولات» برای همیشه حذف می‌شن. خودِ محصولات، مقالات و کاربران حذف نمی‌شن (فقط محصولات بدون دسته‌بندی می‌مونن).'
        );

        /* ۳. مقالات */
        nexv_ie_section(
            'articles',
            '۳. مقالات',
            '<p>ستون‌ها: <code>Title</code>، <code>Content</code> (می‌تونه تگ <code>&lt;img&gt;</code> وسط متن داشته باشه)، <code>Excerpt</code> (اختیاری)، <code>Image</code> (تصویر کاور، اختیاری)، <code>Slug</code> (اختیاری).</p>',
            'مطمئنی؟ «همه‌ی مقالات» برای همیشه حذف می‌شن. محصولات، دسته‌بندی‌ها، کاربران و صفحه‌ی «درباره ما» دست‌نخورده می‌مونن.'
        );

        /* ۴. کاربران */
        nexv_ie_section(
            'customers',
            '۴. کاربران',
            '<p>ستون‌ها: <code>First Name</code>، <code>Last Name</code>، <code>Email</code>، <code>Phone</code> (یا Billing Phone)، <code>Billing Address 1</code>، <code>Billing City</code>، <code>Billing State</code>، <code>Billing Postcode</code>.</p>',
            'مطمئنی؟ «همه‌ی کاربران (مشتری‌ها)» برای همیشه حذف می‌شن. حساب مدیر، حساب‌های پیشخوان وردپرس، سفارش‌های ثبت‌شده، محصولات، دسته‌بندی‌ها و مقالات دست‌نخورده می‌مونن.'
        );
        ?>
        </div>

        <?php /* ابزارهای کمکیِ قبلی حذف نشدن تا قابلیتی از دست نره؛ فقط جدا و پایین صفحه قرار گرفتن. */ ?>
        <div style="margin-top:26px; padding-top:14px; border-top:1px solid #dcdcde;">
            <h3 style="margin:0 0 10px; font-size:14px;">ابزارهای کمکی</h3>
            <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=nexv_fix_slugs'), 'nexv_fix_slugs')); ?>"
               onclick="return confirm('پیوندِ یکتای همه‌ی محصولات و مقالاتی که الان فارسی/غیرانگلیسی هستن، به انگلیسی تبدیل می‌شه. ادامه بدم؟');">تبدیل پیوند یکتای محصولات و مقالات به انگلیسی</a>
            <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=nexv_clear_cache'), 'nexv_clear_cache')); ?>">پاک کردن کشِ محصولات و دسته‌بندی‌ها</a>
        </div>
    </div>
    <?php
}

/* ---------- برون‌ریزی محصولات ---------- */
add_action('admin_post_nexv_export_products', function () {
    if (!current_user_can('edit_posts')) wp_die('دسترسی غیرمجاز.');
    check_admin_referer('nexv_export_products');

    $posts = get_posts(['post_type' => 'nexv_product', 'numberposts' => -1, 'post_status' => 'any']);
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=nexv-products.csv');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Type', 'SKU', 'Name', 'Published', 'Short description', 'Description', 'Regular price', 'Categories', 'Images', 'Color Options', 'Storage Options', 'Tags']);
    foreach ($posts as $p) {
        /* دسته‌ها به‌صورتِ «مسیرِ کاملِ اسلاگ» نوشته می‌شن (مثل iphone > iphone-parts > iphone-15)
           تا درون‌ریزیِ دوباره‌ی همین فایل، محصول رو دقیقاً زیرِ همون شاخه‌ی درست بذاره؛
           قبلاً فقط نامِ برگ نوشته می‌شد و موقعِ import پیدا نمی‌شد. */
        $catTerms = wp_get_post_terms($p->ID, 'nexv_cat');
        $cats = [];
        if (!is_wp_error($catTerms)) {
            foreach ($catTerms as $ct) {
                $chain = [$ct->slug];
                $cur = $ct;
                $guard = 0;
                while ($cur->parent && $guard++ < 10) {
                    $cur = get_term($cur->parent, 'nexv_cat');
                    if (!$cur || is_wp_error($cur)) break;
                    array_unshift($chain, $cur->slug);
                }
                $cats[] = implode(' > ', $chain);
            }
        }
        $gallery = nexv_gallery_urls($p->ID);
        fputcsv($out, [
            $p->ID,
            'simple',
            '',
            $p->post_title,
            $p->post_status === 'publish' ? 1 : 0,
            get_post_meta($p->ID, '_nexv_short_desc', true),
            $p->post_content,
            get_post_meta($p->ID, '_nexv_price', true),
            implode(', ', $cats),
            implode(' | ', $gallery),
            get_post_meta($p->ID, '_nexv_colors', true),
            get_post_meta($p->ID, '_nexv_storages', true),
            str_replace('|', ', ', (string) get_post_meta($p->ID, '_nexv_badge', true)),
        ]);
    }
    fclose($out);
    exit;
});

/* ---------- درون‌ریزی محصولات ---------- */
add_action('admin_post_nexv_import_products', function () {
    if (!current_user_can('edit_posts')) wp_die('دسترسی غیرمجاز.');
    check_admin_referer('nexv_import_products');
    if (empty($_FILES['nexv_csv']['tmp_name'])) {
        wp_redirect(admin_url('admin.php?page=nexv-import-export'));
        exit;
    }

    // برای فایل‌های بزرگ (چند هزار محصول)، اجرای اسکریپت محدود به زمان پیش‌فرض نشه.
    @set_time_limit(0);
    @ignore_user_abort(true);

    $tmp_path = $_FILES['nexv_csv']['tmp_name'];

    /* تشخیص خودکار جداکننده‌ی ستون‌ها: بعضی از خروجی‌های اکسل/ووکامرس به‌جای
       کاما از سمی‌کالن یا تب استفاده می‌کنن؛ اگه این تشخیص داده نشه، کل سطر
       به‌عنوان یک ستون خونده می‌شه و هیچ محصولی درون‌ریزی نمی‌شه (باگی که پیش اومد). */
    $first_line = '';
    $probe = fopen($tmp_path, 'r');
    if ($probe) { $first_line = (string) fgets($probe); fclose($probe); }
    $first_line = preg_replace('/^\xEF\xBB\xBF/', '', $first_line);
    $delimiter = ',';
    $counts = [',' => substr_count($first_line, ','), ';' => substr_count($first_line, ';'), "\t" => substr_count($first_line, "\t")];
    arsort($counts);
    $best = array_key_first($counts);
    if ($counts[$best] > 0) { $delimiter = $best; }

    $fh = fopen($tmp_path, 'r');
    $header = fgetcsv($fh, 0, $delimiter);
    if (!$header || count($header) < 2) {
        wp_die('ستون‌های فایل CSV به‌درستی تشخیص داده نشد. لطفاً مطمئن شو فایل واقعاً CSV هست (نه اکسل .xlsx) و دوباره امتحان کن.<br><a href="' . esc_url(admin_url('admin.php?page=nexv-import-export')) . '">بازگشت</a>');
    }
    // حذف BOM احتمالی + یکدست‌سازی نام ستون‌ها (بدون‌حساسیت به فاصله‌ی اضافه و بزرگی/کوچکی حروف)
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    $normalized = array_map(function ($h) { return strtolower(trim((string) $h)); }, $header);
    $idx = array_flip($normalized);

    $col = function ($row, $names) use ($idx) {
        foreach ((array) $names as $n) {
            $key = strtolower(trim($n));
            if (isset($idx[$key]) && isset($row[$idx[$key]])) return $row[$idx[$key]];
        }
        return '';
    };

    // اگه اصلاً ستونی برای نام محصول پیدا نشه، به‌جای درون‌ریزیِ صفر و بی‌صدا، خطای روشن بده.
    if (!isset($idx['name']) && !isset($idx['title']) && !isset($idx['نام'])) {
        wp_die(
            'ستونِ «Name» / «نام» توی فایل پیدا نشد، برای همین هیچ محصولی درون‌ریزی نشد.<br>' .
            'ستون‌هایی که توی فایلت پیدا کردم: ' . esc_html(implode(' | ', $header)) . '<br>' .
            '<a href="' . esc_url(admin_url('admin.php?page=nexv-import-export')) . '">بازگشت</a>'
        );
    }

    $downloadImages = !empty($_POST['nexv_download_images']);

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $count = 0;
    $skippedCats = 0;
    $missingCats = [];   // نامِ دسته‌هایی که توی سایت پیدا نشدن، برای گزارش به مدیر
    while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
        if (count($row) === 1 && trim((string) $row[0]) === '') continue; // ردیف خالی
        $name = trim((string) $col($row, ['Name', 'Title', 'نام']));
        if ($name === '') continue;

        $price = (int) preg_replace('/[^0-9]/', '', (string) $col($row, ['Regular price', 'Price', 'قیمت اصلی', 'قیمت']));
        $desc = (string) $col($row, ['Description', 'توضیحات']);
        $shortDesc = (string) $col($row, ['Short description', 'توضیح کوتاه']);
        $catsRaw = (string) $col($row, ['Categories', 'دسته‌ها', 'دسته ها']);
        $imagesRaw = (string) $col($row, ['Images', 'تصاویر']);
        $colorsRaw = (string) $col($row, ['Color Options', 'Colors', 'رنگ', 'رنگ‌ها', 'گزینه‌های رنگ']);
        $storagesRaw = (string) $col($row, ['Storage Options', 'Storage', 'حجم', 'ظرفیت', 'گزینه‌های حجم']);
        // برچسب‌ها (ستونِ Tags ووکامرس). چند برچسب با ویرگول، «،» یا «|» جدا می‌شن.
        $tagsRaw = (string) $col($row, ['Tags', 'Tag', 'Badges', 'Badge', 'برچسب', 'برچسب‌ها']);
        /* فرمت ستونِ گروه‌های متغیرِ سفارشی: هر گروه «برچسب:گزینه۱|گزینه۲|...» و بین گروه‌ها
           با ; جدا می‌شه؛ مثال: «نوع:روکاری اورجینال|های کپی» یا حتی چند گروه با هم:
           «نوع:روکاری اورجینال|های کپی;رنگ:سفید|مشکی». */
        $variantGroupsRaw = (string) $col($row, ['Variant Groups', 'گروه‌های متغیر', 'گروه های متغیر']);
        $publishedFlag = trim((string) $col($row, ['Published', 'منتشر شده']));
        $postStatus = ($publishedFlag === '0') ? 'draft' : 'publish';

        /* فیلدهای سئوی رنک‌مث: اگه توی فایل ستون‌های زیر موجود باشن، مستقیم
           روی متاباکسِ Rank Math ست می‌شن (کلمه‌ی کلیدی اصلی، عنوان سئو، توضیحات متا)
           تا لازم نباشه بعد از هر درون‌ریزی، تک‌تکِ محصولات دستی ویرایش بشن. */
        $seoFocusKeyword = trim((string) $col($row, ['Focus Keyword', 'کلمه کلیدی', 'کلمه کلیدی اصلی']));
        $seoTitle        = trim((string) $col($row, ['SEO Title', 'عنوان سئو']));
        $seoDescription  = trim((string) $col($row, ['Meta Description', 'توضیحات متا']));

        /* اگه ستونِ Slug توی فایل پر شده باشه، همون عیناً (فقط sanitize ساده) به‌عنوان
           پیوند یکتا استفاده می‌شه؛ وگرنه فیلترِ خودکارِ نویسه‌گردانیِ فارسی-به-انگلیسی
           (nexv_transliterate_title_to_slug) از روی عنوان می‌سازتش. این ستون برای
           کنترلِ دقیق‌تر روی پیوندهای انگلیسی هست، مخصوصاً وقتی عنوان طولانی/جمله‌ایه. */
        $explicitSlug = trim((string) $col($row, ['Slug', 'پیوند یکتا', 'اسلاگ']));
        $explicitSlug = $explicitSlug !== '' ? sanitize_title($explicitSlug) : '';

        $existingId = (int) trim((string) $col($row, ['ID', 'شناسه']));
        $postArrCommon = [
            'post_title'   => sanitize_text_field($name),
            'post_content' => wp_kses_post($desc),
            'post_status'  => $postStatus,
        ];
        if ($explicitSlug !== '') { $postArrCommon['post_name'] = $explicitSlug; }

        if ($existingId && get_post_type($existingId) === 'nexv_product') {
            wp_update_post(['ID' => $existingId] + $postArrCommon);
            $post_id = $existingId;
        } else {
            $post_id = wp_insert_post(['post_type' => 'nexv_product'] + $postArrCommon);
        }
        if (is_wp_error($post_id) || !$post_id) continue;

        nexv_save_price_with_date($post_id, $price);
        update_post_meta($post_id, '_nexv_short_desc', sanitize_textarea_field($shortDesc));
        if ($colorsRaw !== '') update_post_meta($post_id, '_nexv_colors', sanitize_text_field($colorsRaw));
        if ($storagesRaw !== '') update_post_meta($post_id, '_nexv_storages', sanitize_text_field($storagesRaw));
        if (trim($tagsRaw) !== '') {
            $tagList = preg_split('/\s*[,،|]\s*/u', $tagsRaw);
            $tagList = array_values(array_unique(array_filter(array_map(function ($t) {
                return trim(sanitize_text_field($t));
            }, $tagList), 'strlen')));
            update_post_meta($post_id, '_nexv_badge', implode('|', $tagList));
        }
        if ($variantGroupsRaw !== '') {
            $groups = [];
            foreach (explode(';', $variantGroupsRaw) as $groupChunk) {
                $groupChunk = trim($groupChunk);
                if ($groupChunk === '' || strpos($groupChunk, ':') === false) continue;
                [$gLabel, $gOptionsRaw] = array_map('trim', explode(':', $groupChunk, 2));
                $gOptions = array_values(array_filter(array_map('trim', explode('|', $gOptionsRaw))));
                if ($gLabel === '' || !$gOptions) continue;
                $groups[] = ['label' => sanitize_text_field($gLabel), 'options' => array_map('sanitize_text_field', $gOptions)];
            }
            if ($groups) update_post_meta($post_id, '_nexv_variant_groups', wp_json_encode($groups, JSON_UNESCAPED_UNICODE));
        }
        if (!get_post_meta($post_id, '_nexv_color', true)) update_post_meta($post_id, '_nexv_color', '#6a4dff');
        if (!get_post_meta($post_id, '_nexv_icon', true)) update_post_meta($post_id, '_nexv_icon', 'disc');

        /* ذخیره‌ی فیلدهای Rank Math (پلاگین این کلیدهای متا رو خودش می‌خونه و توی
           متاباکسِ سئوی صفحه‌ی ویرایش محصول نشون می‌ده - نیازی به تنظیم دستی نیست) */
        if ($seoFocusKeyword !== '') update_post_meta($post_id, 'rank_math_focus_keyword', sanitize_text_field($seoFocusKeyword));
        if ($seoTitle !== '') update_post_meta($post_id, 'rank_math_title', sanitize_text_field($seoTitle));
        if ($seoDescription !== '') update_post_meta($post_id, 'rank_math_description', sanitize_textarea_field($seoDescription));

        /* دسته‌بندی‌ها: با کاما جدا از هم، و با « > » برای سلسله‌مراتب (دقیقاً فرمت ووکامرس).
           مهم: درون‌ریزیِ محصول هرگز دسته‌بندیِ جدید نمی‌سازه — فقط به دسته‌بندیِ
           از قبل موجود (که از صفحه‌ی «دسته‌بندی‌ها» ساخته شده) وصل می‌شه. اگه مسیر
           دقیقاً پیدا نشه، اون محصول بدون دسته‌بندی می‌مونه (skippedCats زیاد می‌شه)
           به‌جای اینکه یه دسته‌بندیِ نصفه‌ونیمه یا اشتباه بسازه. */
        if ($catsRaw !== '') {
            $termIds = [];
            foreach (explode(',', $catsRaw) as $catPath) {
                if (trim($catPath) === '') continue;
                // نامِ خودِ محصول به حل‌کننده داده می‌شه تا بینِ دسته‌های هم‌نام، درست رو انتخاب کنه
                $leafId = nexv_resolve_cat_for_product($catPath, $name);
                if ($leafId) { $termIds[] = $leafId; }
                else { $skippedCats++; $missingCats[trim($catPath)] = true; }
            }
            $termIds = array_values(array_unique($termIds));
            if ($termIds) wp_set_post_terms($post_id, $termIds, 'nexv_cat');
        }

        /* تصاویر: با « | » جدا از هم (فرمت ووکامرس).
           حالت پیش‌فرض (سریع): فقط آدرس تصویرها ذخیره می‌شه و مستقیم از همون سایت
           قبلی نمایش داده می‌شن؛ هیچ دانلودی انجام نمی‌شه، برای همین با هزاران
           محصول هم درون‌ریزی خیلی سریع تمومه.
           حالت اختیاری (کند): واقعاً دانلود و توی کتابخانه‌ی رسانه‌ی خودِ سایت ذخیره می‌شه. */
        if ($imagesRaw !== '') {
            $urls = array_filter(array_map('trim', explode('|', $imagesRaw)));
            if ($downloadImages) {
                $attIds = [];
                foreach ($urls as $url) {
                    $attId = media_sideload_image($url, $post_id, null, 'id');
                    if (!is_wp_error($attId)) $attIds[] = $attId;
                }
                if ($attIds) {
                    set_post_thumbnail($post_id, $attIds[0]);
                    update_post_meta($post_id, '_nexv_gallery', implode(',', $attIds));
                }
            } elseif ($urls) {
                update_post_meta($post_id, '_nexv_gallery', implode(',', array_map('rawurlencode', $urls)));
            }
        }

        $count++;
    }
    fclose($fh);
    delete_transient('nexv_products_cache_v2');
    if ($missingCats) set_transient('nexv_missing_cats', array_keys($missingCats), 120);
    wp_redirect(admin_url('admin.php?page=nexv-import-export&imported=' . $count . '&type=products&skippedcats=' . $skippedCats));
    exit;
});

/* ---------- حذف همه‌ی محصولات ---------- */
add_action('admin_post_nexv_delete_all_products', function () {
    if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز.');
    check_admin_referer('nexv_delete_all_products');
    @set_time_limit(0);

    $ids = get_posts(['post_type' => 'nexv_product', 'numberposts' => -1, 'post_status' => 'any', 'fields' => 'ids']);
    foreach ($ids as $id) {
        wp_delete_post($id, true); // true = واقعاً حذف بشه، نره توی زباله‌دان
    }
    delete_transient('nexv_products_cache_v2'); // مهم: وگرنه لیستِ کش‌شده‌ی قدیمی هنوز روی سایت نشون داده می‌شه
    delete_transient('nexv_categories_cache_v1'); // تعدادِ کالای هر دسته عوض شده؛ خودِ دسته‌ها حذف نمی‌شن
    wp_redirect(admin_url('admin.php?page=nexv-import-export&deleted=' . count($ids)));
    exit;
});

/* ---------- حذف همه‌ی مقالات ----------
   فقط نوع‌پستِ nexv_article حذف می‌شه؛ برگه‌ها (مثل «درباره ما»)، نوشته‌های معمولی،
   محصولات، دسته‌بندی‌ها، کاربران و فایل‌های کتابخانه‌ی رسانه دست‌نخورده می‌مونن. */
add_action('admin_post_nexv_delete_all_articles', function () {
    if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز.');
    check_admin_referer('nexv_delete_all_articles');
    @set_time_limit(0);

    $ids = get_posts(['post_type' => 'nexv_article', 'numberposts' => -1, 'post_status' => 'any', 'fields' => 'ids']);
    $n = 0;
    foreach ($ids as $id) {
        // دوباره چک می‌کنیم که واقعاً مقاله باشه، تا هیچ‌وقت چیزِ دیگه‌ای حذف نشه
        if (get_post_type($id) === 'nexv_article' && wp_delete_post($id, true)) { $n++; }
    }
    wp_redirect(admin_url('admin.php?page=nexv-import-export&articlesdeleted=' . $n));
    exit;
});

/* ---------- حذف همه‌ی کاربران (مشتری‌ها) ----------
   فقط مشتری‌های دیتابیسِ NEXV حذف می‌شن. این‌ها دست‌نخورده می‌مونن:
   - حساب مدیر (is_admin)
   - حساب‌های پیشخوانِ وردپرس (wp_users) — این عملیات اصلاً به اون‌ها دست نمی‌زنه
   - سفارش‌های ثبت‌شده (نام و آدرسِ مشتری داخلِ خودِ سفارش ذخیره شده، پس فاکتورها سالم می‌مونن)
   - محصولات، دسته‌بندی‌ها، مقالات و بقیه‌ی تنظیمات دیتابیس */
add_action('admin_post_nexv_delete_all_customers', function () {
    if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز.');
    check_admin_referer('nexv_delete_all_customers');

    $data = nexv_read_data();
    $users = isset($data['users']) && is_array($data['users']) ? $data['users'] : [];
    $admins = array_values(array_filter($users, function ($u) { return !empty($u['is_admin']); }));
    $removed = count($users) - count($admins);

    if ($removed > 0) {
        $data['users'] = $admins;          // فقط لیستِ کاربران عوض می‌شه؛ بقیه‌ی کلیدهای دیتابیس همون‌طور می‌مونن
        nexv_write_data($data);            // قبل از نوشتن، خودکار یک نسخه‌ی پشتیبان ساخته می‌شه
    }
    wp_redirect(admin_url('admin.php?page=nexv-import-export&usersdeleted=' . $removed));
    exit;
});

/* ---------- تبدیل پیوند یکتای همه‌ی محصولات و مقالاتِ موجود به انگلیسی ----------
   این دکمه برای محصولات/مقاله‌هایی لازمه که قبل از فعال‌شدنِ قانونِ اسلاگ انگلیسی
   ساخته شدن و هنوز اسلاگِ فارسی/Percent-Encoded دارن. برای هر پستی که اسلاگش
   غیر-ASCII باشه، از روی همون عنوان دوباره وردپرس رو صدا می‌زنیم تا فیلترِ
   nexv_transliterate_title_to_slug اجرا و اسلاگِ انگلیسیِ جدید جایگزین بشه. */
add_action('admin_post_nexv_fix_slugs', function () {
    if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز.');
    check_admin_referer('nexv_fix_slugs');
    @set_time_limit(0);

    $fixed = nexv_fix_all_category_slugs();
    foreach (['nexv_product', 'nexv_article'] as $pt) {
        $posts = get_posts(['post_type' => $pt, 'numberposts' => -1, 'post_status' => 'any']);
        foreach ($posts as $p) {
            if (preg_match('/[^\x00-\x7f]/', $p->post_name) || $p->post_name === '') {
                wp_update_post(['ID' => $p->ID, 'post_name' => '']); // خالی می‌ذاریم تا فیلترِ wp_insert_post_data از روی عنوان از نو بسازتش
                $fixed++;
            }
        }
    }
    wp_redirect(admin_url('admin.php?page=nexv-import-export&slugsfixed=' . $fixed));
    exit;
});

/* ---------- حذف همیشگیِ محصولات «بدنه (شاسی)» ----------
   طبق درخواستِ صریح، این خط محصول کلاً از سایت حذف می‌شه (نه فقط پیش‌نویس). */
add_action('admin_post_nexv_delete_chassis', function () {
    if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز.');
    check_admin_referer('nexv_delete_chassis');

    $posts = get_posts([
        'post_type'      => 'nexv_product',
        'numberposts'    => -1,
        'post_status'    => 'any',
        's'              => 'بدنه',
    ]);
    $count = 0;
    foreach ($posts as $p) {
        if (mb_strpos($p->post_title, 'بدنه') === 0) {
            wp_delete_post($p->ID, true);
            $count++;
        }
    }
    delete_transient('nexv_products_cache_v2');
    wp_redirect(admin_url('admin.php?page=nexv-import-export&chassisdeleted=' . $count));
    exit;
});

/* ---------- پاک کردنِ دستیِ کامل کش (برای وقتی که مطمئن نیستیم چرا سایت داده‌ی قدیمی نشون می‌ده) ---------- */
add_action('admin_post_nexv_clear_cache', function () {
    if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز.');
    check_admin_referer('nexv_clear_cache');
    delete_transient('nexv_products_cache_v2');
    delete_transient('nexv_categories_cache_v1');
    flush_rewrite_rules();
    wp_redirect(admin_url('admin.php?page=nexv-import-export&cachecleared=1'));
    exit;
});

/* ---------- حذف همه‌ی دسته‌بندی‌ها ---------- */
add_action('admin_post_nexv_delete_all_categories', function () {
    if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز.');
    check_admin_referer('nexv_delete_all_categories');
    @set_time_limit(0);

    $terms = get_terms(['taxonomy' => 'nexv_cat', 'hide_empty' => false, 'fields' => 'ids']);
    $n = 0;
    if (!is_wp_error($terms)) {
        foreach ($terms as $tid) { if (wp_delete_term($tid, 'nexv_cat')) $n++; }
    }
    delete_transient('nexv_categories_cache_v1'); // مهم: وگرنه درخت دسته‌بندیِ قدیمی هنوز کش شده و روی سایت می‌مونه
    delete_transient('nexv_products_cache_v2'); // چون لیستِ محصولاتِ کش‌شده هم اسمِ دسته‌ها رو داخل خودش داره
    wp_redirect(admin_url('admin.php?page=nexv-import-export&catsdeleted=' . $n));
    exit;
});

/* ---------- ساختن سریعِ ساختار دسته‌بندی‌ها (بدون محصول) — همچنان از راه کد قابل استفاده‌ست ---------- */
add_action('admin_post_nexv_bulk_categories', function () {
    if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز.');
    check_admin_referer('nexv_bulk_categories');
    @set_time_limit(0);

    $paths = explode("\n", (string) ($_POST['nexv_cat_paths'] ?? ''));
    $count = 0;
    foreach ($paths as $path) {
        $parts = array_filter(array_map('trim', explode('>', $path)));
        if (!$parts) continue;
        $parentId = 0;
        foreach ($parts as $partName) {
            $existing = term_exists($partName, 'nexv_cat', $parentId);
            if ($existing) {
                $termId = (int) (is_array($existing) ? $existing['term_id'] : $existing);
            } else {
                $created = wp_insert_term($partName, 'nexv_cat', ['parent' => $parentId]);
                $termId = is_wp_error($created) ? 0 : (int) $created['term_id'];
                if ($termId) $count++;
            }
            if ($termId) $parentId = $termId;
        }
    }
    wp_redirect(admin_url('admin.php?page=nexv-import-export&catscreated=' . $count));
    exit;
});

/* ---------- برون‌ریزی دسته‌بندی‌ها (CSV، مثل ووکامرس) ---------- */
add_action('admin_post_nexv_export_categories', function () {
    if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز.');
    check_admin_referer('nexv_export_categories');
    @set_time_limit(0);

    $terms = get_terms(['taxonomy' => 'nexv_cat', 'hide_empty' => false]);
    if (is_wp_error($terms)) $terms = [];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="nexv-categories-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM تا اکسل فارسی رو درست نشون بده
    fputcsv($out, ['ID', 'Name', 'Slug', 'Parent', 'Description', 'Image']);
    foreach ($terms as $t) {
        /* والد با «اسلاگ» نوشته می‌شه، نه نام: اسلاگ یکتاست و وقتی چند دسته‌ی هم‌نام
           زیرِ شاخه‌های مختلف داریم، درون‌ریزیِ دوباره‌ی همین فایل دقیقاً همون ساختار
           رو بازمی‌سازه. (درون‌ریزی نامِ دسته رو هم مثل قبل قبول می‌کنه.) */
        $parentName = '';
        if ($t->parent) {
            $p = get_term($t->parent, 'nexv_cat');
            if ($p && !is_wp_error($p)) $parentName = $p->slug;
        }
        $thumbId = get_term_meta($t->term_id, 'nexv_cat_image', true);
        $imageUrl = $thumbId ? wp_get_attachment_url($thumbId) : '';
        fputcsv($out, [$t->term_id, $t->name, $t->slug, $parentName, $t->description, $imageUrl]);
    }
    fclose($out);
    exit;
});

/* ---------- درون‌ریزی دسته‌بندی‌ها از CSV (مثل ووکامرس) ----------
 * ستون‌ها: Name، Slug (اختیاری)، Parent (نامِ دسته‌ی والد یا خالی)، Description، Image.
 * چون ترتیبِ ردیف‌های فایل ممکنه دسته‌ی والد رو قبل یا بعد از فرزندش بیاره،
 * تا ۵ دور روی فایل عبور می‌کنه تا هر والدی که هنوز ساخته نشده، توی دورِ بعد پیدا بشه. */
add_action('admin_post_nexv_import_categories', function () {
    if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز.');
    check_admin_referer('nexv_import_categories');
    @set_time_limit(0);

    if (empty($_FILES['nexv_csv']['tmp_name']) || !is_uploaded_file($_FILES['nexv_csv']['tmp_name'])) {
        set_transient('nexv_cat_import_result', ['count' => 0, 'errors' => ['هیچ فایلی دریافت نشد؛ دوباره فایل رو انتخاب و آپلود کن.']], 60);
        wp_redirect(admin_url('admin.php?page=nexv-import-export&catsimported=0'));
        exit;
    }

    // پاک‌سازیِ رشته: حذفِ BOM و کاراکترهای جهتیِ نامرئی (RLM/LRM) که کپی‌پیست از
    // ورد/اکسل فارسی معمولاً همراه متن میاره و باعث می‌شه مقایسه‌ی متنِ والد/فرزند
    // با اینکه چشمی یکی به نظر میان، از نظر کد یکی نباشن و درون‌ریزی ساکت شکست بخوره.
    $clean = function ($s) {
        $s = (string) $s;
        $s = str_replace(["\xEF\xBB\xBF", "\xE2\x80\x8E", "\xE2\x80\x8F"], '', $s); // BOM, LRM, RLM
        return trim($s);
    };

    $raw = file_get_contents($_FILES['nexv_csv']['tmp_name']);
    if ($raw === false || trim($raw) === '') {
        set_transient('nexv_cat_import_result', ['count' => 0, 'errors' => ['فایل خالیه یا خونده نشد.']], 60);
        wp_redirect(admin_url('admin.php?page=nexv-import-export&catsimported=0'));
        exit;
    }
    // اگه فایل با انکودینگِ ویندوزیِ فارسی (نه UTF-8) ذخیره شده باشه (مثلاً از اکسل
    // ویندوز بدون انتخاب "UTF-8")، تبدیلش می‌کنیم تا حروف فارسی خراب نشن.
    if (function_exists('mb_detect_encoding') && !mb_check_encoding($raw, 'UTF-8')) {
        $converted = function_exists('iconv') ? @iconv('Windows-1256', 'UTF-8//IGNORE', $raw) : false;
        if ($converted) $raw = $converted;
    }

    // تشخیص خودکارِ جداکننده‌ی ستون‌ها؛ بعضی اکسل‌های فارسی به‌جای کاما از سِمی‌کالن استفاده می‌کنن.
    $firstLine = strtok($raw, "\r\n");
    $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $raw);
    rewind($fh);

    $header = fgetcsv($fh, 0, $delimiter);
    if (!$header) {
        set_transient('nexv_cat_import_result', ['count' => 0, 'errors' => ['ردیفِ سرستون (Name, Slug, Parent, ...) پیدا نشد.']], 60);
        wp_redirect(admin_url('admin.php?page=nexv-import-export&catsimported=0'));
        exit;
    }
    $colIndex = [];
    foreach ($header as $i => $h) { $colIndex[strtolower($clean($h))] = $i; }
    if (!isset($colIndex['name'])) {
        set_transient('nexv_cat_import_result', ['count' => 0, 'errors' => ['ستونِ Name توی فایل پیدا نشد. سرستون‌های فایل باید دقیقاً Name, Slug, Parent, Description, Image باشن.']], 60);
        wp_redirect(admin_url('admin.php?page=nexv-import-export&catsimported=0'));
        exit;
    }
    $get = function ($row, $key) use ($colIndex, $clean) {
        $key = strtolower($key);
        return isset($colIndex[$key], $row[$colIndex[$key]]) ? $clean($row[$colIndex[$key]]) : '';
    };

    $rows = [];
    while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
        if (count($row) === 1 && $clean($row[0]) === '') continue;
        $rows[] = $row;
    }
    fclose($fh);

    if (!$rows) {
        set_transient('nexv_cat_import_result', ['count' => 0, 'errors' => ['بعد از ردیفِ سرستون، هیچ ردیفِ داده‌ای توی فایل پیدا نشد.']], 60);
        wp_redirect(admin_url('admin.php?page=nexv-import-export&catsimported=0'));
        exit;
    }

    /* دسته‌بندی‌ها ممکنه هم‌نام باشن ولی زیرِ والدهای متفاوت (مثلاً «آیفون سری ۴» هم زیرِ
       «آیفون» باشه، هم زیرِ «قطعات»، هم زیرِ «اکسسوری»). قبلاً والد فقط با نامِ ساده پیدا
       می‌شد (get_term_by('name', ...)) که چون بینِ چند ترمِ هم‌نام فرق نمی‌ذاره، همیشه یکی
       از اون‌ها رو برمی‌گردوند؛ در نتیجه هر ردیفِ بعدی با همون اسم، والدِ ترمِ از‌قبل‌ساخته‌شده
       رو عوض می‌کرد و همه‌چیز آخرش زیرِ آخرین شاخه‌ی پردازش‌شده جمع می‌شد (برای همینه که توی
       فیلتر پیشرفته «آیفون» و «قطعات» زیرمجموعه نداشتن ولی «اکسسوری» داشت).
       برای رفع این مشکل، ستونِ Parent می‌تونه یک مسیرِ کامل با « > » باشه (مثلاً
       «قطعات > آیفون سری 4») تا والدِ درست، بین چند ترمِ هم‌نام هم به‌درستی پیدا بشه؛
       تک‌نام هم مثلِ قبل کار می‌کنه. جست‌وجوی هر پاره از مسیر هم با term_exists($name, tax,
       $parentId) انجام می‌شه که والد رو در نظر می‌گیره، نه فقط نام. */
    $resolveTermInParent = function ($name, $parentId) {
        return nexv_find_cat_in_parent($name, $parentId);
    };

    $count = 0;
    $orderCounter = 0;
    $errors = [];
    for ($pass = 0; $pass < 8 && $rows; $pass++) {
        $remaining = [];
        foreach ($rows as $row) {
            $name = $get($row, 'name');
            if ($name === '') continue;
            $slug = $get($row, 'slug');
            $parentPath = $get($row, 'parent');
            $desc = $get($row, 'description');
            $imageUrl = $get($row, 'image');

            $parentId = 0;
            $parentUnresolved = false;
            if ($parentPath !== '') {
                $parts = array_filter(array_map('trim', explode('>', $parentPath)));
                // اسلاگ و نامِ خودِ این ردیف، به‌عنوان سرنخ برای انتخابِ والدِ درست بینِ هم‌نام‌ها
                $ctx = $slug . ' ' . $name;
                foreach ($parts as $partName) {
                    $pid = nexv_find_cat_by_name_ctx($partName, $parentId, $ctx);
                    if (!$pid) { $parentUnresolved = true; break; }
                    $parentId = $pid;
                }
            }
            if ($parentUnresolved) {
                $remaining[] = $row; // والدش هنوز ساخته نشده؛ دور بعد دوباره امتحان کن
                continue;
            }

            // بررسیِ وجودِ خودِ ترم هم باید محدود به همین والد باشه، نه هر جای درخت؛
            // وگرنه یک ترمِ هم‌نامِ زیرِ والدِ دیگه پیدا و جابه‌جا می‌شه.
            /* شناساییِ ردیف: اگه ستونِ Slug پر باشه، «فقط و فقط» با همون اسلاگ شناسایی می‌شه.
               نامِ دسته هیچ‌وقت ملاک نیست، چون توی این فروشگاه ده‌ها دسته‌ی هم‌نام زیرِ
               شاخه‌های مختلف هست («سری 1» زیرِ قطعات/خرید/لوازم جانبی) و شناساییِ نامی
               باعث می‌شد ردیف‌ها روی هم بیفتن و از ۴۵۴ دسته فقط ۹۳ تا ساخته بشه. */
            $existing = null;
            if ($slug !== '') {
                $bySlug = get_term_by('slug', $slug, 'nexv_cat');
                if ($bySlug) $existing = $bySlug;
            } else {
                $foundId = nexv_find_cat_in_parent($name, $parentId, false);
                if ($foundId) $existing = get_term($foundId, 'nexv_cat');
            }
            $args = ['description' => $desc, 'parent' => $parentId];
            if ($slug !== '') $args['slug'] = $slug;

            if ($existing && !is_wp_error($existing)) {
                $updated = wp_update_term($existing->term_id, 'nexv_cat', array_merge(['name' => $name], $args));
                $termId = is_wp_error($updated) ? $existing->term_id : $existing->term_id;
            } else {
                $created = wp_insert_term($name, 'nexv_cat', $args);
                if (is_wp_error($created)) {
                    /* خطای term_exists یعنی ترمی با همین نام از قبل هست. این فروشگاه پر از
                       دسته‌های هم‌نام زیرِ شاخه‌های مختلفه («سری 1» هم زیرِ قطعات، هم خرید،
                       هم لوازم جانبی)، پس اون ترمِ موجود رو جابه‌جا نمی‌کنیم — این کار قبلاً
                       باعث می‌شد ۴۵۴ ردیفِ فایل آخرش فقط ~۹۰ دسته بسازن و درخت به هم بریزه.
                       به‌جاش اگه واقعاً همون ترمِ این ردیفه (اسلاگ یا والدش یکیه) به‌روزش
                       می‌کنیم، وگرنه یک ترمِ تازه با اسلاگِ یکتا ساخته می‌شه. */
                    $data = (int) $created->get_error_data('term_exists');
                    $same = $data ? get_term($data, 'nexv_cat') : null;
                    $isSameRow = $same && !is_wp_error($same) && (
                        ($slug !== '' && $same->slug === $slug)                    // دقیقاً همین ردیف
                        || ($slug === '' && (int) $same->parent === (int) $parentId)
                    );
                    if ($isSameRow) {
                        $termId = (int) $same->term_id;
                        wp_update_term($termId, 'nexv_cat', array_merge(['name' => $name], $args));
                    } else {
                        $uniqueSlug = $slug !== '' ? $slug : sanitize_title($name);
                        if ($uniqueSlug === '' ) $uniqueSlug = 'cat';
                        $try = $uniqueSlug; $i = 2;
                        while (get_term_by('slug', $try, 'nexv_cat') && $i < 50) { $try = $uniqueSlug . '-' . $i; $i++; }
                        $retry = wp_insert_term($name, 'nexv_cat', array_merge($args, ['slug' => $try]));
                        if (is_wp_error($retry)) {
                            $errors[] = $name . ': ' . $retry->get_error_message();
                            continue;
                        }
                        $termId = (int) $retry['term_id'];
                    }
                } else {
                    $termId = (int) $created['term_id'];
                }
            }

            /* وردپرس موقعِ ساختِ دسته‌ی هم‌نام، اسلاگ رو خودش عوض می‌کنه و اسلاگِ والد رو
               بهش می‌چسبونه. نتیجه‌اش این بود که ردیفِ بعدیِ فایل، اسلاگِ خودش رو روی همین
               ترم پیدا می‌کرد و به‌جای ساختِ دسته‌ی جدید، همین رو بازنویسی می‌کرد — برای همین
               ۴۵۴ ردیف آخرش ۹۳ دسته می‌شد. این‌جا اسلاگِ خودِ ردیف دوباره اعمال می‌شه. */
            if ($slug !== '') {
                $cur = get_term($termId, 'nexv_cat');
                if ($cur && !is_wp_error($cur) && $cur->slug !== $slug) {
                    $owner = get_term_by('slug', $slug, 'nexv_cat');
                    if (!$owner || (int) $owner->term_id === $termId) {
                        wp_update_term($termId, 'nexv_cat', ['slug' => $slug]);
                    }
                }
            }

            // ترتیبِ چینش: همون ترتیبِ ردیف‌های فایل، طوری که در فیلتر پیشرفته‌ی سایت هم
            // به همین ترتیب نشون داده بشه (نه ترتیبِ الفباییِ پیش‌فرضِ وردپرس).
            update_term_meta($termId, 'nexv_cat_order', $orderCounter++);

            if ($imageUrl !== '' && function_exists('media_sideload_image')) {
                $attId = media_sideload_image($imageUrl, 0, null, 'id');
                if (!is_wp_error($attId)) update_term_meta($termId, 'nexv_cat_image', $attId);
            }
            $count++;
        }
        $rows = $remaining;
    }
    foreach ($rows as $leftover) {
        $errors[] = $get($leftover, 'name') . ': دسته‌ی والدش («' . $get($leftover, 'parent') . '») پیدا نشد.';
    }

    set_transient('nexv_cat_import_result', ['count' => $count, 'errors' => $errors], 60);
    delete_transient('nexv_categories_cache_v1');
    delete_transient('nexv_products_cache_v2');
    wp_redirect(admin_url('admin.php?page=nexv-import-export&catsimported=' . $count));
    exit;
});

/* ---------- برون‌ریزی مقالات ---------- */
add_action('admin_post_nexv_export_articles', function () {
    if (!current_user_can('edit_posts')) wp_die('دسترسی غیرمجاز.');
    check_admin_referer('nexv_export_articles');

    $posts = get_posts(['post_type' => 'nexv_article', 'numberposts' => -1, 'post_status' => 'any']);
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=nexv-articles.csv');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Title', 'Published', 'Excerpt', 'Content', 'Image']);
    foreach ($posts as $p) {
        $thumbId = get_post_thumbnail_id($p->ID);
        $thumbUrl = $thumbId ? wp_get_attachment_url($thumbId) : '';
        fputcsv($out, [
            $p->ID,
            $p->post_title,
            $p->post_status === 'publish' ? 1 : 0,
            $p->post_excerpt,
            $p->post_content,
            $thumbUrl,
        ]);
    }
    fclose($out);
    exit;
});

/* ---------- درون‌ریزی مقالات ----------
   ستون‌ها: Title، Content (می‌تونه تگ <img> وسط متن داشته باشه)، Excerpt (اختیاری)،
   Image (آدرس تصویر کاور، اختیاری - فقط لینک ذخیره می‌شه، دانلود نمی‌شه تا سریع بمونه). */
add_action('admin_post_nexv_import_articles', function () {
    if (!current_user_can('edit_posts')) wp_die('دسترسی غیرمجاز.');
    check_admin_referer('nexv_import_articles');

    if (empty($_FILES['nexv_csv']['tmp_name'])) wp_die('فایلی انتخاب نشده.');
    $fh = fopen($_FILES['nexv_csv']['tmp_name'], 'r');
    if (!$fh) wp_die('فایل قابل خواندن نیست.');

    $firstLine = fgets($fh);
    rewind($fh);
    $delimiter = (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) ? "\t" : ',';
    $bom = "\xEF\xBB\xBF";
    $header = fgetcsv($fh, 0, $delimiter);
    if ($header && strpos($header[0], $bom) === 0) $header[0] = substr($header[0], strlen($bom));

    $col = function ($row, array $names) use ($header) {
        foreach ($names as $name) {
            $idx = array_search($name, $header);
            if ($idx !== false && isset($row[$idx])) return $row[$idx];
        }
        return '';
    };

    $count = 0;
    while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
        if (count($row) === 1 && trim((string) $row[0]) === '') continue;
        $title = trim((string) $col($row, ['Title', 'Name', 'عنوان']));
        if ($title === '') continue;

        $content = (string) $col($row, ['Content', 'متن', 'محتوا']);
        $excerpt = (string) $col($row, ['Excerpt', 'خلاصه']);
        $imageUrl = trim((string) $col($row, ['Image', 'تصویر']));
        $publishedFlag = trim((string) $col($row, ['Published', 'منتشر شده']));
        $postStatus = ($publishedFlag === '0') ? 'draft' : 'publish';
        $existingId = (int) trim((string) $col($row, ['ID', 'شناسه']));
        $explicitSlug = trim((string) $col($row, ['Slug', 'پیوند یکتا', 'اسلاگ']));
        $explicitSlug = $explicitSlug !== '' ? sanitize_title($explicitSlug) : '';

        $postArr = [
            'post_type'    => 'nexv_article',
            'post_title'   => sanitize_text_field($title),
            'post_content' => wp_kses_post($content),
            'post_excerpt' => sanitize_textarea_field($excerpt),
            'post_status'  => $postStatus,
        ];
        if ($explicitSlug !== '') { $postArr['post_name'] = $explicitSlug; }

        if ($existingId && get_post_type($existingId) === 'nexv_article') {
            $postArr['ID'] = $existingId;
            wp_update_post($postArr);
            $post_id = $existingId;
        } else {
            $post_id = wp_insert_post($postArr);
        }
        if (is_wp_error($post_id) || !$post_id) continue;

        if ($imageUrl !== '') {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            $attId = media_sideload_image($imageUrl, $post_id, null, 'id');
            if (!is_wp_error($attId)) set_post_thumbnail($post_id, $attId);
        }

        $count++;
    }
    fclose($fh);
    wp_redirect(admin_url('admin.php?page=nexv-import-export&imported=' . $count . '&type=articles'));
    exit;
});

/* ---------- برون‌ریزی مشتریان (از دیتابیس JSON خودِ NEXV) ---------- */
add_action('admin_post_nexv_export_customers', function () {
    if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز.');
    check_admin_referer('nexv_export_customers');

    $users = nexv_get_all_customers();
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=nexv-customers.csv');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['First Name', 'Last Name', 'Email', 'Phone', 'Address Label', 'Billing Address 1', 'Billing City', 'Billing State', 'Billing Postcode']);
    foreach ($users as $u) {
        $addresses = $u['addresses'] ?? [];
        if (!$addresses) { $addresses = [[]]; }
        foreach ($addresses as $a) {
            fputcsv($out, [
                $u['firstName'] ?? '',
                $u['lastName'] ?? '',
                $u['email'] ?? '',
                $u['phone'] ?? '',
                $a['label'] ?? '',
                trim(($a['district'] ?? '') . ' ' . ($a['fullAddress'] ?? '')),
                $a['city'] ?? '',
                $a['province'] ?? '',
                $a['postalCode'] ?? '',
            ]);
        }
    }
    fclose($out);
    exit;
});

/* ---------- درون‌ریزی مشتریان ---------- */
add_action('admin_post_nexv_import_customers', function () {
    if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز.');
    check_admin_referer('nexv_import_customers');
    if (empty($_FILES['nexv_csv']['tmp_name'])) {
        wp_redirect(admin_url('admin.php?page=nexv-import-export'));
        exit;
    }
    @set_time_limit(0);

    $tmp_path = $_FILES['nexv_csv']['tmp_name'];
    $first_line = '';
    $probe = fopen($tmp_path, 'r');
    if ($probe) { $first_line = (string) fgets($probe); fclose($probe); }
    $first_line = preg_replace('/^\xEF\xBB\xBF/', '', $first_line);
    $delimiter = ',';
    $counts = [',' => substr_count($first_line, ','), ';' => substr_count($first_line, ';'), "\t" => substr_count($first_line, "\t")];
    arsort($counts);
    $best = array_key_first($counts);
    if ($counts[$best] > 0) { $delimiter = $best; }

    $fh = fopen($tmp_path, 'r');
    $header = fgetcsv($fh, 0, $delimiter);
    if (!$header || count($header) < 2) {
        wp_die('ستون‌های فایل CSV به‌درستی تشخیص داده نشد. لطفاً مطمئن شو فایل واقعاً CSV هست و دوباره امتحان کن.<br><a href="' . esc_url(admin_url('admin.php?page=nexv-import-export')) . '">بازگشت</a>');
    }
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    $normalized = array_map(function ($h) { return strtolower(trim((string) $h)); }, $header);
    $idx = array_flip($normalized);
    $col = function ($row, $names) use ($idx) {
        foreach ((array) $names as $n) {
            $key = strtolower(trim($n));
            if (isset($idx[$key]) && isset($row[$idx[$key]])) return $row[$idx[$key]];
        }
        return '';
    };

    if (!isset($idx['phone']) && !isset($idx['billing phone'])) {
        wp_die(
            'ستونِ «Phone» توی فایل پیدا نشد، برای همین هیچ مشتری‌ای درون‌ریزی نشد.<br>' .
            'ستون‌هایی که توی فایلت پیدا کردم: ' . esc_html(implode(' | ', $header)) . '<br>' .
            '<a href="' . esc_url(admin_url('admin.php?page=nexv-import-export')) . '">بازگشت</a>'
        );
    }

    $rows = [];
    while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
        if (count($row) === 1 && trim((string) $row[0]) === '') continue;
        $phone = preg_replace('/[^0-9]/', '', (string) $col($row, ['Phone', 'Billing Phone']));
        if ($phone === '') continue;
        $rows[] = [
            'firstName' => (string) $col($row, ['First Name']),
            'lastName'  => (string) $col($row, ['Last Name']),
            'email'     => (string) $col($row, ['Email']),
            'phone'     => $phone,
            'city'      => (string) $col($row, ['Billing City']),
            'province'  => (string) $col($row, ['Billing State']),
            'postalCode'=> (string) $col($row, ['Billing Postcode']),
            'address1'  => (string) $col($row, ['Billing Address 1']),
        ];
    }
    fclose($fh);

    $count = nexv_import_customers_rows($rows);
    wp_redirect(admin_url('admin.php?page=nexv-import-export&imported=' . $count . '&type=customers'));
    exit;
});

add_action('admin_menu', function () {
    remove_menu_page('users.php');
    remove_menu_page('edit-comments.php');
}, 999);

/* صفحه‌ی «افزودن/ویرایش محصول» به‌جای ویرایشگر بلوکی (گوتنبرگ) با ویرایشگر
   کلاسیک (همون چیزی که قبلاً بود، شبیه فرم محصول ووکامرس) نمایش داده می‌شه؛
   باکس مشخصات محصول + کادر توضیحات کامل، هر دو همون‌جا و منظم در دسترسن. */
add_filter('use_block_editor_for_post_type', function ($use_block_editor, $post_type) {
    return ($post_type === 'nexv_product') ? false : $use_block_editor;
}, 10, 2);

/* نظرات (نظرسنجی) روی محصولات NEXV نیازی به تایید دستی ادمین ندارن و بلافاصله
   بعد از ثبت نمایش داده می‌شن؛ این فقط مخصوص محصولاته و روی بقیه‌ی نوشته‌های
   سایت (مثلاً وبلاگ)، همون تنظیمات پیش‌فرض مدیریت دیدگاه‌های وردپرس دست‌نخورده می‌مونه. */
add_filter('pre_comment_approved', function ($approved, $commentdata) {
    if (!empty($commentdata['comment_post_ID']) && get_post_type($commentdata['comment_post_ID']) === 'nexv_product') {
        return 1;
    }
    return $approved;
}, 10, 2);

/* نظرات (نظرسنجی/تیم) براشون پیش‌فرض بازه ---------- */
add_filter('get_default_comment_status', function ($status, $post_type = 'post', $comment_type = 'comment') {
    return ($post_type === 'nexv_product') ? 'open' : $status;
}, 10, 3);

/* نام و ایمیلِ نمایش داده‌شده روی هر نظر، همیشه از حساب کاربریِ NEXV خودِ مشتریه
   (که با جاوااسکریپت روی فرم ست می‌شه)، نه از سشنِ ورودیِ وردپرس — چون ممکنه
   خودِ ادمین هم همزمان با حساب وردپرسش وارد باشه و نظرها به اسم "admin" ثبت بشن. */
add_filter('preprocess_comment', function ($commentdata) {
    if (get_post_type($commentdata['comment_post_ID']) === 'nexv_product') {
        if (!empty($_POST['author'])) {
            $commentdata['comment_author'] = sanitize_text_field(wp_unslash($_POST['author']));
        }
        if (!empty($_POST['email'])) {
            $commentdata['comment_author_email'] = sanitize_email(wp_unslash($_POST['email']));
        }
        // این نظرها همیشه به‌عنوان دیدگاهِ «مهمان» ثبت بشن، نه به حساب کاربریِ وردپرسیِ فعلاً واردشده
        $commentdata['user_id'] = 0;
    }
    return $commentdata;
});

function nexv_register_category_taxonomy() {
    register_taxonomy('nexv_cat', ['nexv_product'], [
        'labels' => [
            'name'              => 'دسته‌بندی محصولات',
            'singular_name'     => 'دسته‌بندی',
            'search_items'      => 'جستجوی دسته‌بندی',
            'all_items'         => 'همه‌ی دسته‌بندی‌ها',
            'parent_item'       => 'دسته‌بندی والد',
            'parent_item_colon' => 'دسته‌بندی والد:',
            'edit_item'         => 'ویرایش دسته‌بندی',
            'add_new_item'      => 'افزودن دسته‌بندی جدید',
            'new_item_name'     => 'نام دسته‌بندی جدید',
            'menu_name'         => 'دسته‌بندی‌ها',
        ],
        'hierarchical'      => true, // درختی: هر دسته می‌تونه زیرمجموعه‌ی یک دسته‌ی دیگه باشه
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'show_in_menu'      => 'nexv-dashboard', // مستقیم زیرِ منوی NEXV نشون داده می‌شه
        'show_ui'           => true,
        /* hierarchical => true لازمه تا وردپرس برای زیردسته‌ها هم قانون بازنویسیِ
           چندسطحی بسازه (مثلاً /product-cat/آیفون/آیفون-سری-6/آیفون-6/)؛ بدون این،
           فقط یک قانونِ تک‌سطحی ساخته می‌شه و پیوندِ دسته‌های تودرتو (که با
           get_term_link ساخته و روی سایت نمایش داده می‌شن) موقعِ بازدید یا رفرشِ
           مستقیم ۴۰۴ می‌داد؛ برای سئو و بوکمارک‌کردن باید هر پیوند واقعاً لود بشه. */
        'rewrite'           => ['slug' => 'product-cat', 'hierarchical' => true, 'with_front' => false],
    ]);
}
add_action('init', 'nexv_register_category_taxonomy');

/* توضیح: فیلدهای سئوی سفارشی (عنوان/توضیحات متا) از اینجا حذف شدن، چون
   افزونه‌ی Rank Math SEO از قبل پنل کامل خودش رو برای دسته‌بندی‌ها نشون
   می‌ده و استفاده‌ی هم‌زمان از هردو تکراری/گیج‌کننده بود. */

/* فلاش کردن یک‌بارهٔ rewrite rules بعد از اضافه شدن نوع‌پست/تاکسونومی جدید،
   چه در نصب اول قالب و چه وقتی این نسخه جایگزین نسخه‌ی قبلی می‌شه. */
function nexv_flush_rewrites_on_switch() {
    nexv_register_product_cpt();
    nexv_register_category_taxonomy();
    flush_rewrite_rules();
}
add_action('after_switch_theme', 'nexv_flush_rewrites_on_switch');

add_action('init', function () {
    /* شماره‌ی نسخه بالا رفت (v2 -> v3) چون قانونِ hierarchical به rewrite دسته‌بندی
       اضافه شد؛ بدون بالا بردنِ این عدد، سایت‌هایی که قبلاً یک‌بار v2 رو flush کرده
       بودن، دیگه هیچ‌وقت flush جدید رو نمی‌گرفتن و پیوندِ دسته‌های تودرتو همچنان ۴۰۴ می‌داد. */
    if (get_option('nexv_rewrite_flushed_v3') !== '1') {
        flush_rewrite_rules();
        update_option('nexv_rewrite_flushed_v3', '1');
    }
}, 20);

/* =========================================================================
 * باکس اطلاعات محصول (قیمت، مشخصات کوتاه، رنگ، آیکون، برچسب) در صفحه‌ی
 * ویرایشِ هر محصول در پیشخوان.
 * ========================================================================= */
/* لیستِ URL تصاویر یک محصول: اول گالریِ دستی (اگه تنظیم شده)، وگرنه فقط همون تصویر شاخص. */
function nexv_gallery_urls($post_id, $fallback_img = null) {
    if ($fallback_img === null) { $fallback_img = get_the_post_thumbnail_url($post_id, 'large'); }
    $raw = trim((string) get_post_meta($post_id, '_nexv_gallery', true));
    $urls = [];
    if ($raw !== '') {
        foreach (explode(',', $raw) as $token) {
            $token = rawurldecode(trim($token));
            if ($token === '') continue;
            if (ctype_digit($token)) {
                // شناسه‌ی یک تصویرِ واقعاً آپلودشده در کتابخانه‌ی رسانه
                $url = wp_get_attachment_image_url((int) $token, 'large');
                if ($url) $urls[] = $url;
            } else {
                // آدرس مستقیم یک تصویر (بدون دانلود در سایت؛ حالت درون‌ریزیِ سریع)
                $urls[] = $token;
            }
        }
    }
    if (!$urls && $fallback_img) { $urls[] = $fallback_img; }
    return $urls;
}

function nexv_product_meta_box() {
    add_meta_box('nexv_product_details', 'جزئیات محصول (قیمت، مشخصات، رنگ و ...)', 'nexv_product_meta_box_html', 'nexv_product', 'normal', 'high');
}
add_action('add_meta_boxes', 'nexv_product_meta_box');

function nexv_product_meta_box_html($post) {
    wp_nonce_field('nexv_save_product_meta', 'nexv_product_meta_nonce');
    $price = get_post_meta($post->ID, '_nexv_price', true);
    $spec  = get_post_meta($post->ID, '_nexv_spec', true);
    $color = get_post_meta($post->ID, '_nexv_color', true);
    $ic    = get_post_meta($post->ID, '_nexv_icon', true);
    $badge = get_post_meta($post->ID, '_nexv_badge', true);
    if ($color === '') { $color = '#6a4dff'; }
    if ($ic === '')    { $ic = 'phone'; }
    $icons = [
        'phone'   => 'گوشی (آیفون)',
        'tablet'  => 'تبلت (آیپد)',
        'laptop'  => 'لپ‌تاپ (مک‌بوک)',
        'watch'   => 'ساعت (اپل واچ)',
        'earbuds' => 'هندزفری (ایرپاد)',
        'disc'    => 'سایر',
    ];
    ?>
    <p style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:6px;padding:10px 12px;color:#3730a3;">
        📸 برای <strong>عکس محصول</strong>: از باکس «تصویر شاخص» (سمت راست همین صفحه) استفاده کن.<br>
        📝 برای <strong>توضیح کامل محصول</strong>: از کادر بزرگ متن بالای همین صفحه (زیر عنوان) استفاده کن؛ همون توی صفحه‌ی محصول کامل نمایش داده می‌شه.
    </p>
    <p>
        <label style="display:block;font-weight:600;margin-bottom:4px;">قیمت (تومان)</label>
        <input type="number" name="nexv_price" value="<?php echo esc_attr($price); ?>" style="width:100%" min="0">
        <button type="button" class="button" id="nexvVarPriceBtn" style="margin-top:8px;">🏷️ قیمت جدا برای هر متغیر</button>
        <span id="nexvVarPriceCount" style="margin-inline-start:8px;font-size:12px;color:#646970;"></span>
        <input type="hidden" name="nexv_variant_prices" id="nexvVarPriceJson"
               value="<?php echo esc_attr(wp_json_encode(nexv_variant_prices($post->ID), JSON_UNESCAPED_UNICODE)); ?>">
        <div id="nexvVarPricePanel" style="display:none;margin-top:10px;border:1px solid #dcdcde;border-radius:8px;background:#fff;">
            <div style="padding:10px 12px;border-bottom:1px solid #f0f0f1;background:#fbfbfc;border-radius:8px 8px 0 0;">
                <b>قیمتِ هر متغیر</b>
                <span style="font-size:12px;color:#646970;">— هر ترکیبی که خالی بمونه، با همون قیمتِ بالا (قیمتِ پایه) فروخته می‌شه.</span>
                <div style="font-size:12px;color:#8a6d3b;background:#fff8e1;border:1px solid #ffe0a3;border-radius:6px;padding:7px 9px;margin-top:8px;">
                    اگه بعداً یه <b>گروهِ متغیرِ جدید</b> اضافه یا حذف کنی، ترکیب‌ها عوض می‌شن و قیمت‌هایی که
                    به ترکیب‌های قبلی خورده بودن دیگه نشون داده نمی‌شن؛ در اون صورت همین پنل رو باز کن و
                    قیمت‌ها رو یه بار دیگه وارد کن. (اضافه‌کردنِ یه رنگ یا حجمِ جدید مشکلی ایجاد نمی‌کنه.)
                </div>
            </div>
            <div id="nexvVarPriceBody" style="max-height:340px;overflow:auto;padding:6px 12px 12px;"></div>
        </div>
        <?php $priceDate = nexv_price_updated_label($post->ID); ?>
        <p class="description" style="margin-top:4px;">
            <?php if ($priceDate) : ?>
                آخرین به‌روزرسانی قیمت: <b><?php echo esc_html($priceDate); ?></b>
                — همین تاریخ کنارِ قیمت به مشتری نشون داده می‌شه.
            <?php else : ?>
                تاریخِ به‌روزرسانی، اولین باری که قیمت ذخیره بشه خودکار ثبت می‌شه.
            <?php endif; ?>
        </p>
    </p>
    <p>
        <label style="display:block;font-weight:600;margin-bottom:4px;">مشخصات کوتاه (زیر عنوان، در کارت محصول)</label>
        <input type="text" name="nexv_spec" value="<?php echo esc_attr($spec); ?>" style="width:100%" placeholder="مثلاً: ۱۲۸ گیگ | رنگ مشکی">
    </p>
    <p>
        <label style="display:block;font-weight:600;margin-bottom:4px;">رنگ کارت (کد HEX)</label>
        <input type="text" name="nexv_color" value="<?php echo esc_attr($color); ?>" style="width:100%">
    </p>
    <p>
        <label style="display:block;font-weight:600;margin-bottom:4px;">آیکون</label>
        <select name="nexv_icon" style="width:100%">
            <?php foreach ($icons as $key => $label) : ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($ic, $key); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
    </p>
    <?php
    /* برچسب‌ها: چند برچسب برای هر محصول. در دیتابیس با «|» از هم جدا ذخیره می‌شن
       (محصولاتِ قدیمی که فقط یک برچسب داشتن، بدون تغییر درست کار می‌کنن). */
    $badgeList = array_values(array_filter(array_map('trim', explode('|', (string) $badge)), 'strlen'));
    ?>
    <div style="margin:1em 0;">
        <label style="display:block;font-weight:600;margin-bottom:4px;">برچسب‌ها (اختیاری، مثلاً «جدید»، «پرفروش»، «اورجینال»)</label>
        <div class="nexv-tags" id="nexvTags">
            <?php foreach ($badgeList as $b): ?>
                <span class="nexv-tag"><span><?php echo esc_html($b); ?></span><button type="button" aria-label="حذف">×</button></span>
            <?php endforeach; ?>
            <input type="text" class="nexv-tag-input" id="nexvTagInput" placeholder="برچسب را بنویس و اینتر بزن" autocomplete="off">
        </div>
        <input type="hidden" name="nexv_badge" id="nexvBadgeHidden" value="<?php echo esc_attr(implode('|', $badgeList)); ?>">
        <p class="description" style="margin-top:4px;">بعد از نوشتنِ هر برچسب <b>Enter</b> (یا ویرگول) بزن تا ثبت بشه و برو سراغِ برچسبِ بعدی. برای حذف روی × بزن.</p>
    </div>
    <style>
        .nexv-tags{display:flex; flex-wrap:wrap; align-items:center; gap:6px; padding:6px 8px; min-height:38px;
            border:1px solid #8c8f94; border-radius:4px; background:#fff; cursor:text;}
        .nexv-tags:focus-within{border-color:#2271b1; box-shadow:0 0 0 1px #2271b1;}
        .nexv-tag{display:inline-flex; align-items:center; gap:4px; padding:3px 4px 3px 10px; border-radius:999px;
            background:#ede9fe; color:#4c1d95; font-size:13px; font-weight:600; line-height:1.4;}
        .nexv-tag button{border:0; background:rgba(76,29,149,.12); color:#4c1d95; width:20px; height:20px; border-radius:50%;
            cursor:pointer; font-size:14px; line-height:1; padding:0;}
        .nexv-tag button:hover{background:#4c1d95; color:#fff;}
        .nexv-tag-input{flex:1 1 140px; min-width:120px; border:0 !important; box-shadow:none !important; outline:0; padding:4px !important; background:transparent;}
    </style>
    <script>
    (function(){
        var box = document.getElementById('nexvTags'), input = document.getElementById('nexvTagInput'),
            hidden = document.getElementById('nexvBadgeHidden');
        if(!box || !input || !hidden) return;
        function values(){ return Array.prototype.map.call(box.querySelectorAll('.nexv-tag > span'), function(s){ return s.textContent.trim(); }); }
        function sync(){ hidden.value = values().join('|'); }
        function add(text){
            text = String(text || '').replace(/[|]/g, ' ').trim();
            if(!text) return;
            if(values().some(function(v){ return v === text; })) return;   // تکراری اضافه نمی‌شه
            var tag = document.createElement('span'); tag.className = 'nexv-tag';
            var label = document.createElement('span'); label.textContent = text;
            var x = document.createElement('button'); x.type = 'button'; x.setAttribute('aria-label', 'حذف'); x.textContent = '×';
            tag.appendChild(label); tag.appendChild(x);
            box.insertBefore(tag, input);
            sync();
        }
        input.addEventListener('keydown', function(e){
            // Enter نباید فرمِ ویرایشِ محصول رو ذخیره کنه؛ فقط برچسب رو ثبت می‌کنه
            if(e.key === 'Enter' || e.key === ',' || e.key === '،'){
                e.preventDefault();
                add(input.value); input.value = '';
            } else if(e.key === 'Backspace' && input.value === ''){
                var tags = box.querySelectorAll('.nexv-tag');
                if(tags.length){ tags[tags.length - 1].remove(); sync(); }
            }
        });
        // اگه چیزی نوشته شده ولی Enter زده نشده، موقعِ خروج از کادر یا ذخیره‌ی محصول هم ثبت بشه
        input.addEventListener('blur', function(){ if(input.value.trim()){ add(input.value); input.value = ''; } });
        var form = input.form;
        if(form) form.addEventListener('submit', function(){ if(input.value.trim()){ add(input.value); input.value = ''; } sync(); });
        box.addEventListener('click', function(e){
            if(e.target.tagName === 'BUTTON' && e.target.parentNode.classList.contains('nexv-tag')){
                e.target.parentNode.remove(); sync(); input.focus();
            } else if(e.target === box){ input.focus(); }
        });
    })();
    </script>
    <p>
        <label style="display:block;font-weight:600;margin-bottom:4px;">توضیحات کوتاه (بین باکس محصول و توضیحات کامل نمایش داده می‌شه)</label>
        <textarea name="nexv_short_desc" rows="3" style="width:100%"><?php echo esc_textarea(get_post_meta($post->ID, '_nexv_short_desc', true)); ?></textarea>
    </p>
    <p>
        <label style="display:block;font-weight:600;margin-bottom:4px;">تصاویر محصول (چند عکس؛ اولی روی کارت محصول نمایش داده می‌شه)</label>
        <input type="hidden" name="nexv_gallery" id="nexv_gallery_ids" value="<?php echo esc_attr(get_post_meta($post->ID, '_nexv_gallery', true)); ?>">
        <div id="nexv_gallery_preview" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px;"></div>
        <button type="button" class="button" id="nexv_gallery_pick">انتخاب / مرتب‌سازی تصاویر</button>
    </p>
    <p style="background:#fff8e1;border:1px solid #ffe0a3;border-radius:6px;padding:10px 12px;">
        🎨 <strong>گزینه‌های محصول</strong> (مثل رنگ یا حجم): مشتری قبل از افزودن به سبد، یکی از این گزینه‌ها رو انتخاب می‌کنه.
        اگه بخوای هر ترکیب قیمتِ خودش رو داشته باشه، گزینه‌ها رو همین‌جا بنویس و بعد بالا روی
        <b>«قیمت جدا برای هر متغیر»</b> بزن.
    </p>
    <p>
        <label style="display:block;font-weight:600;margin-bottom:4px;">گزینه‌های رنگ (با کاما جدا کن؛ خالی بذار اگه لازم نیست)</label>
        <input type="text" name="nexv_colors" value="<?php echo esc_attr(get_post_meta($post->ID, '_nexv_colors', true)); ?>" style="width:100%" placeholder="مثلاً: مشکی, سفید, آبی, بنفش">
    </p>
    <p>
        <label style="display:block;font-weight:600;margin-bottom:4px;">گزینه‌های حجم/ظرفیت (با کاما جدا کن؛ خالی بذار اگه لازم نیست)</label>
        <input type="text" name="nexv_storages" value="<?php echo esc_attr(get_post_meta($post->ID, '_nexv_storages', true)); ?>" style="width:100%" placeholder="مثلاً: 128 گیگ, 256 گیگ, 512 گیگ">
    </p>
    <p style="background:#eef7ee;border:1px solid #bfe3bf;border-radius:6px;padding:10px 12px;">
        ➕ <strong>گروه‌های متغیر سفارشی</strong>: برای محصولاتی که رنگ/حجم کافی نیست و به یک متغیرِ دیگه نیاز دارن
        (مثلاً برای LCD: «نوع» با گزینه‌های «روکاری اورجینال» و «کپی»). هر ردیف یک گروه‌ست: یک برچسب + چند گزینه با کاما جدا.
        این بخش رو <u>فقط وقتی محصول واقعاً به یک متغیرِ دیگه نیاز داره</u> پر کن؛ برای بقیه‌ی محصولات (مثل باتری) خالی بذار.
    </p>
    <div id="nexv_variant_groups_wrap">
        <?php
        $variantGroupsRaw = get_post_meta($post->ID, '_nexv_variant_groups', true);
        $variantGroups = [];
        if ($variantGroupsRaw) {
            $decoded = json_decode($variantGroupsRaw, true);
            if (is_array($decoded)) { $variantGroups = $decoded; }
        }
        if (!$variantGroups) { $variantGroups = [['label' => '', 'options' => []]]; }
        foreach ($variantGroups as $vg):
            $vgLabel = isset($vg['label']) ? $vg['label'] : '';
            $vgOptions = isset($vg['options']) && is_array($vg['options']) ? implode(', ', $vg['options']) : '';
        ?>
        <div class="nexv-variant-group-row" style="display:flex; gap:8px; margin-bottom:6px;">
            <input type="text" name="nexv_variant_group_label[]" value="<?php echo esc_attr($vgLabel); ?>" placeholder="برچسب گروه، مثلاً: نوع" style="width:35%">
            <input type="text" name="nexv_variant_group_options[]" value="<?php echo esc_attr($vgOptions); ?>" placeholder="گزینه‌ها با کاما، مثلاً: روکاری اورجینال, کپی" style="width:55%">
            <button type="button" class="button nexv-variant-group-remove">حذف</button>
        </div>
        <?php endforeach; ?>
    </div>
    <p><button type="button" class="button" id="nexv_variant_group_add">+ افزودن گروهِ متغیرِ دیگر</button></p>
    <script>
    jQuery(function($){
        $('#nexv_variant_group_add').on('click', function(){
            var row = $('<div class="nexv-variant-group-row" style="display:flex; gap:8px; margin-bottom:6px;">'
                + '<input type="text" name="nexv_variant_group_label[]" placeholder="برچسب گروه، مثلاً: نوع" style="width:35%">'
                + '<input type="text" name="nexv_variant_group_options[]" placeholder="گزینه‌ها با کاما، مثلاً: روکاری اورجینال, کپی" style="width:55%">'
                + '<button type="button" class="button nexv-variant-group-remove">حذف</button>'
                + '</div>');
            $('#nexv_variant_groups_wrap').append(row);
        });
        /* ---------- قیمتِ جدا برای هر متغیر ----------
           ترکیب‌ها از روی همون چیزی ساخته می‌شن که همین لحظه توی فیلدهای رنگ/حجم/گروه‌ها
           نوشته شده (حتی اگه هنوز ذخیره نشده باشه)، پس بعد از اضافه‌کردنِ یه گزینه‌ی جدید
           لازم نیست اول پست رو ذخیره کنی. ترتیبِ بُعدها همیشه: رنگ، حجم، گروه‌ها. */
        function nexvSplitOpts(v){
            return String(v || '').split(/\s*[,،]\s*/).map(function(x){ return x.trim(); }).filter(Boolean);
        }
        function nexvDims(){
            var dims = [
                { label: 'رنگ', values: nexvSplitOpts($('input[name="nexv_colors"]').val()) },
                { label: 'حجم/ظرفیت', values: nexvSplitOpts($('input[name="nexv_storages"]').val()) }
            ];
            $('.nexv-variant-group-row').each(function(){
                var lbl = $(this).find('input[name="nexv_variant_group_label[]"]').val();
                var ops = nexvSplitOpts($(this).find('input[name="nexv_variant_group_options[]"]').val());
                dims.push({ label: String(lbl || '').trim() || 'گروه', values: ops });
            });
            return dims;
        }
        /* ضربِ دکارتیِ بُعدها؛ بُعدِ خالی یک مقدارِ '' می‌ده تا جایگاهش توی کلید حفظ بشه */
        function nexvCombos(dims){
            var rows = [[]];
            dims.forEach(function(d){
                var vals = d.values.length ? d.values : [''];
                var next = [];
                rows.forEach(function(r){ vals.forEach(function(v){ next.push(r.concat([v])); }); });
                rows = next;
                if(rows.length > 400) { rows = rows.slice(0, 400); }   // سقفِ ایمنی
            });
            return rows;
        }
        function nexvVarMap(){
            try { var m = JSON.parse($('#nexvVarPriceJson').val() || '{}'); return (m && typeof m === 'object') ? m : {}; }
            catch(e){ return {}; }
        }
        function nexvVarKey(values){
            return values.map(function(v){ return String(v || '').trim(); }).join('|~|');
        }
        function nexvVarSync(){
            var map = {};
            $('#nexvVarPriceBody input[data-vkey]').each(function(){
                var val = parseInt(String($(this).val()).replace(/[^0-9]/g, ''), 10);
                if(val > 0) map[$(this).data('vkey')] = val;
            });
            $('#nexvVarPriceJson').val(JSON.stringify(map));
            nexvVarCount();
        }
        function nexvVarCount(){
            var n = Object.keys(nexvVarMap()).length;
            $('#nexvVarPriceCount').text(n ? n + ' متغیر قیمتِ اختصاصی دارد' : '');
        }
        function nexvVarRender(){
            var dims = nexvDims(), map = nexvVarMap(), base = $('input[name="nexv_price"]').val() || '';
            var hasAny = dims.some(function(d){ return d.values.length; });
            var body = $('#nexvVarPriceBody').empty();
            if(!hasAny){
                body.append('<p style="color:#646970;margin:10px 2px;">هنوز هیچ گزینه‌ای (رنگ، حجم یا گروهِ سفارشی) برای این محصول ننوشتی. اول پایین‌تر گزینه‌ها رو اضافه کن.</p>');
                return;
            }
            var combos = nexvCombos(dims);
            var labels = dims.filter(function(d){ return d.values.length; }).map(function(d){ return d.label; }).join(' × ');
            body.append('<p style="color:#646970;margin:8px 2px 12px;">' + combos.length + ' ترکیب (' + labels + ') — قیمتِ پایه: <b>' + (base || '—') + '</b></p>');
            var table = $('<table class="widefat striped" style="border:0;"><tbody></tbody></table>');
            var tb = table.find('tbody');
            combos.forEach(function(c){
                var key = nexvVarKey(c);
                var shown = c.filter(Boolean).join(' / ') || '(بدون گزینه)';
                var tr = $('<tr></tr>');
                $('<td style="width:60%;font-weight:600;"></td>').text(shown).appendTo(tr);
                var td = $('<td></td>');
                $('<input type="number" min="0" step="1000" style="width:100%">')
                    .attr('placeholder', base ? ('پایه: ' + base) : 'قیمت پایه')
                    .attr('data-vkey', key)
                    .val(map[key] ? map[key] : '')
                    .appendTo(td);
                td.appendTo(tr);
                tr.appendTo(tb);
            });
            body.append(table);
            body.on('input change', 'input[data-vkey]', nexvVarSync);
        }
        $('#nexvVarPriceBtn').on('click', function(){
            var panel = $('#nexvVarPricePanel');
            if(panel.is(':visible')){ panel.hide(); return; }
            nexvVarRender();
            panel.show();
        });
        nexvVarCount();

        $(document).on('click', '.nexv-variant-group-remove', function(){
            $(this).closest('.nexv-variant-group-row').remove();
        });
    });
    </script>
    <p style="color:#666;">دسته‌بندی محصول رو از باکس «دسته‌بندی محصولات» در همین صفحه (سمت راست) انتخاب کن.</p>
    <script>
    jQuery(function($){
        var ids = ($('#nexv_gallery_ids').val() || '').split(',').filter(Boolean).map(Number);
        var frame;
        function renderPreview(){
            var wrap = $('#nexv_gallery_preview').empty();
            ids.forEach(function(id){
                wrap.append('<img src="' + wp.media.attachment(id).get('url') + '" style="width:64px;height:64px;object-fit:cover;border-radius:6px;border:1px solid #ccc;">');
            });
        }
        if(ids.length){
            ids.forEach(function(id){ wp.media.attachment(id).fetch(); });
            setTimeout(renderPreview, 400);
        }
        $('#nexv_gallery_pick').on('click', function(e){
            e.preventDefault();
            if(frame){ frame.open(); return; }
            frame = wp.media({ title:'انتخاب تصاویر محصول', multiple:true, library:{type:'image'} });
            frame.on('select', function(){
                var selection = frame.state().get('selection');
                ids = selection.map(function(att){ return att.get('id'); });
                $('#nexv_gallery_ids').val(ids.join(','));
                renderPreview();
            });
            frame.open();
        });
    });
    </script>
    <?php
}

function nexv_save_product_meta($post_id) {
    if (!isset($_POST['nexv_product_meta_nonce']) || !wp_verify_nonce($_POST['nexv_product_meta_nonce'], 'nexv_save_product_meta')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return; }
    if (!current_user_can('edit_post', $post_id)) { return; }

    if (isset($_POST['nexv_price'])) { nexv_save_price_with_date($post_id, (int) $_POST['nexv_price']); }
    if (isset($_POST['nexv_spec']))  { update_post_meta($post_id, '_nexv_spec', sanitize_text_field($_POST['nexv_spec'])); }
    if (isset($_POST['nexv_color'])) { update_post_meta($post_id, '_nexv_color', sanitize_text_field($_POST['nexv_color'])); }
    if (isset($_POST['nexv_icon']))  { update_post_meta($post_id, '_nexv_icon', sanitize_text_field($_POST['nexv_icon'])); }
    // چند برچسب، جداشده با «|»؛ هر کدوم جدا تمیز می‌شه و تکراری‌ها حذف می‌شن
    $badgesIn = array_map('sanitize_text_field', explode('|', (string) wp_unslash($_POST['nexv_badge'] ?? '')));
    $badgesIn = array_values(array_unique(array_filter(array_map('trim', $badgesIn), 'strlen')));
    update_post_meta($post_id, '_nexv_badge', implode('|', $badgesIn));
    update_post_meta($post_id, '_nexv_short_desc', sanitize_textarea_field($_POST['nexv_short_desc'] ?? ''));
    update_post_meta($post_id, '_nexv_colors', sanitize_text_field($_POST['nexv_colors'] ?? ''));
    update_post_meta($post_id, '_nexv_storages', sanitize_text_field($_POST['nexv_storages'] ?? ''));

    /* گروه‌های متغیر سفارشی: هر ردیف یک برچسب + چند گزینه (با کاما جدا) بود؛
       این‌ها رو به‌صورت JSON ذخیره می‌کنیم تا فرانت هر تعداد گروه دلخواه رو نشون بده،
       نه فقط رنگ/حجمِ ثابت. ردیف‌های خالی (بدون برچسب یا بدون هیچ گزینه‌ای) نادیده گرفته می‌شن. */
    $vgLabels  = isset($_POST['nexv_variant_group_label']) ? (array) $_POST['nexv_variant_group_label'] : [];
    $vgOptions = isset($_POST['nexv_variant_group_options']) ? (array) $_POST['nexv_variant_group_options'] : [];
    $variantGroups = [];
    foreach ($vgLabels as $i => $label) {
        $label = sanitize_text_field($label);
        $optionsRaw = isset($vgOptions[$i]) ? (string) $vgOptions[$i] : '';
        $options = array_values(array_filter(array_map('trim', explode(',', $optionsRaw))));
        if ($label === '' || !$options) continue;
        $variantGroups[] = ['label' => $label, 'options' => array_map('sanitize_text_field', $options)];
    }
    update_post_meta($post_id, '_nexv_variant_groups', $variantGroups ? wp_json_encode($variantGroups, JSON_UNESCAPED_UNICODE) : '');

    /* نقشه‌ی قیمتِ متغیرها؛ فقط کلیدهایی که عددِ مثبت دارن نگه داشته می‌شن. اگه قیمتی
       تغییر کرده باشه، تاریخِ «آخرین به‌روزرسانی قیمت» هم تازه می‌شه، چون از دیدِ
       مشتری این هم یه تغییرِ قیمته. */
    $vpRaw = (string) wp_unslash($_POST['nexv_variant_prices'] ?? '');
    $vpIn  = $vpRaw !== '' ? json_decode($vpRaw, true) : [];
    $vpClean = [];
    if (is_array($vpIn)) {
        foreach ($vpIn as $vk => $vv) {
            $vv = (int) $vv;
            if ($vv > 0) $vpClean[sanitize_text_field((string) $vk)] = $vv;
        }
    }
    $vpOld = nexv_variant_prices($post_id);
    update_post_meta($post_id, '_nexv_variant_prices', $vpClean ? wp_json_encode($vpClean, JSON_UNESCAPED_UNICODE) : '');
    if ($vpClean != $vpOld && ($vpClean || (int) get_post_meta($post_id, '_nexv_price', true) > 0)) {
        update_post_meta($post_id, '_nexv_price_updated', (int) current_time('timestamp'));
    }
    if (isset($_POST['nexv_gallery'])) {
        $ids = array_filter(array_map('intval', explode(',', $_POST['nexv_gallery'])));
        update_post_meta($post_id, '_nexv_gallery', implode(',', $ids));
    }
}
add_action('save_post_nexv_product', 'nexv_save_product_meta');

/* باطل‌کردنِ کشِ لیست محصولات/دسته‌ها هنگام هر تغییر از خودِ پیشخوان وردپرس.
   قبلاً این هوک‌ها فقط داخل api.php ثبت شده بودن که موقعِ ذخیره در پیشخوان اجرا
   نمی‌شد؛ برای همین بعد از ویرایش، سایت تا چند دقیقه نسخه‌ی کش‌شده‌ی قدیمی رو
   نشون می‌داد. حالا این‌جا هم ثبت می‌شن تا تغییرات بلافاصله روی سایت دیده بشن. */
add_action('save_post_nexv_product', function () { delete_transient('nexv_products_cache_v2'); }, 20);
add_action('before_delete_post', function ($post_id) {
    if (get_post_type($post_id) === 'nexv_product') { delete_transient('nexv_products_cache_v2'); }
});
add_action('trashed_post', function ($post_id) {
    if (get_post_type($post_id) === 'nexv_product') { delete_transient('nexv_products_cache_v2'); }
});
add_action('untrashed_post', function ($post_id) {
    if (get_post_type($post_id) === 'nexv_product') { delete_transient('nexv_products_cache_v2'); }
});
add_action('edited_nexv_cat',  function () { delete_transient('nexv_categories_cache_v1'); delete_transient('nexv_products_cache_v2'); });
add_action('created_nexv_cat', function () { delete_transient('nexv_categories_cache_v1'); });
add_action('delete_nexv_cat',  function () { delete_transient('nexv_categories_cache_v1'); delete_transient('nexv_products_cache_v2'); });

/* برای انتخاب چند تصویر محصول، کتابخانه‌ی رسانه‌ی وردپرس (wp.media) لازمه. */
add_action('admin_enqueue_scripts', function ($hook) {
    global $post_type;
    if (in_array($hook, ['post.php', 'post-new.php'], true) && $post_type === 'nexv_product') {
        wp_enqueue_media();
    }
});

/* ستون قیمت در لیست محصولاتِ پیشخوان، برای دیدِ سریع‌تر مدیر سایت. */
add_filter('manage_nexv_product_posts_columns', function ($columns) {
    $columns['nexv_price'] = 'قیمت (تومان)';
    return $columns;
});
add_action('manage_nexv_product_posts_custom_column', function ($column, $post_id) {
    if ($column === 'nexv_price') {
        $price = (int) get_post_meta($post_id, '_nexv_price', true);
        echo $price ? esc_html(number_format($price)) : 'تماس بگیرید';
    }
}, 10, 2);

/* =========================================================================
 * نوار بالای سایت — از پیشخوان ← شخصی‌سازی (Customizer) قابل تنظیمه.
 * ========================================================================= */
function nexv_customize_register($wp_customize) {
    $wp_customize->add_section('nexv_topbar', [
        'title'    => 'نوار بالای سایت',
        'priority' => 30,
    ]);

    $wp_customize->add_setting('nexv_topbar_enabled', [
        'default'           => true,
        'sanitize_callback' => 'rest_sanitize_boolean',
    ]);
    $wp_customize->add_control('nexv_topbar_enabled', [
        'label'   => 'نمایش نوار بالای سایت',
        'section' => 'nexv_topbar',
        'type'    => 'checkbox',
    ]);

    $wp_customize->add_setting('nexv_contact_phone', [
        'default'           => '09109973428',
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    $wp_customize->add_control('nexv_contact_phone', [
        'label'       => 'شماره تماس',
        'description' => 'در نوار بالای سایت (کنار آیکون تلفن) نمایش داده می‌شه.',
        'section'     => 'nexv_topbar',
        'type'        => 'text',
    ]);

    $wp_customize->add_setting('nexv_contact_whatsapp', [
        'default'           => '09109973428',
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    $wp_customize->add_control('nexv_contact_whatsapp', [
        'label'       => 'شماره واتساپ',
        'description' => 'روی کلیک، گفتگوی واتساپ با همین شماره باز می‌شه.',
        'section'     => 'nexv_topbar',
        'type'        => 'text',
    ]);

    $wp_customize->add_setting('nexv_instagram_url', [
        'default'           => 'https://www.instagram.com/nexv.ir?igsh=bWRndTZ5aDQxMGFj&utm_source=qr',
        'sanitize_callback' => 'esc_url_raw',
    ]);
    $wp_customize->add_control('nexv_instagram_url', [
        'label'       => 'لینک صفحه‌ی اینستاگرام',
        'description' => 'در نوار بالای سایت، کنار شماره تماس و واتساپ نمایش داده می‌شه.',
        'section'     => 'nexv_topbar',
        'type'        => 'text',
    ]);

    $wp_customize->add_setting('nexv_topbar_bg', [
        'default'           => '#8b6bff',
        'sanitize_callback' => 'sanitize_hex_color',
    ]);
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'nexv_topbar_bg', [
        'label'   => 'رنگ پس‌زمینه‌ی نوار',
        'section' => 'nexv_topbar',
    ]));
}

/* ---------- پیدا کردنِ یک دسته زیرِ یک والدِ مشخص ----------
   مقدارِ ورودی می‌تونه «اسلاگ» باشه یا «نامِ نمایشی»؛ هر دو کار می‌کنن.
   قبلاً فقط با نام جست‌وجو می‌شد و فایل‌هایی که ستونِ Parent (یا Categories) رو
   با اسلاگ پر کرده بودن، «والد پیدا نشد» می‌گرفتن و ردیف وارد نمی‌شد. */
/* ------------------------------------------------------------------
   قیمتِ جدا برای هر متغیر (ترکیبِ رنگ / حجم / گروه‌های سفارشی)
   نقشه‌ی قیمت‌ها توی _nexv_variant_prices به شکل JSON ذخیره می‌شه:
     { "<کلیدِ ترکیب>": 4500000, ... }
   کلیدِ ترکیب = مقدارها به ترتیبِ [رنگ، حجم، گروه‌ها به ترتیبِ خودشون]
   که با «|~|» به هم چسبیدن. اگه محصول یکی از این بُعدها رو نداشته باشه،
   جای اون رشته‌ی خالی می‌مونه تا جایگاه‌ها همیشه ثابت بمونن و سمتِ
   مرورگر و سمتِ سرور دقیقاً یک کلید بسازن.
------------------------------------------------------------------- */
function nexv_variant_key($values) {
    $parts = array_map(function ($v) { return trim((string) $v); }, (array) $values);
    return implode('|~|', $parts);
}

function nexv_norm_variant_key($k) {
    $parts = array_map(function ($v) {
        $v = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'], ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'], (string) $v);
        $v = str_replace(["\xE2\x80\x8C", "\xE2\x80\x8E", "\xE2\x80\x8F", "\xC2\xA0", 'ي', 'ك'], [' ', ' ', ' ', ' ', 'ی', 'ک'], $v);
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $v)), 'UTF-8');
    }, explode('|~|', (string) $k));
    while ($parts && end($parts) === '') array_pop($parts);
    return implode('|~|', $parts);
}

function nexv_variant_prices($post_id) {
    $raw = get_post_meta($post_id, '_nexv_variant_prices', true);
    if (!$raw) return [];
    $decoded = json_decode((string) $raw, true);
    if (!is_array($decoded)) return [];
    $out = [];
    foreach ($decoded as $k => $v) {
        $v = (int) $v;
        if ($v > 0) $out[(string) $k] = $v;
    }
    return $out;
}

/* قیمتِ نهاییِ یک ترکیب: اگه قیمتِ اختصاصی داشته باشه همون، وگرنه قیمتِ پایه. */
function nexv_price_for_variant($post_id, $values) {
    $map = nexv_variant_prices($post_id);
    $key = nexv_variant_key($values);
    if (isset($map[$key])) return (int) $map[$key];
    $n = nexv_norm_variant_key($key);
    foreach ($map as $k => $v) { if (nexv_norm_variant_key($k) === $n) return (int) $v; }
    return (int) get_post_meta($post_id, '_nexv_price', true);
}

/* ------------------------------------------------------------------
   تاریخِ آخرین به‌روزرسانیِ قیمت
   هر جا قیمتِ یک محصول ذخیره می‌شه (پیشخوان یا درون‌ریزیِ CSV) از همین
   تابع رد می‌شه؛ اگه عددِ قیمت واقعاً عوض شده باشه، زمانِ همون لحظه توی
   _nexv_price_updated ثبت می‌شه. اگه قیمت تغییری نکرده، تاریخِ قبلی دست
   نمی‌خوره تا یه ذخیره‌ی بی‌تغییر، تاریخ رو الکی جلو نبره.
------------------------------------------------------------------- */
function nexv_save_price_with_date($post_id, $price) {
    $price = (int) $price;
    $old   = get_post_meta($post_id, '_nexv_price', true);
    $isNew = ($old === '' || $old === null);
    update_post_meta($post_id, '_nexv_price', $price);
    $hasDate = get_post_meta($post_id, '_nexv_price_updated', true) !== '';
    // اگه تاریخی از قبل ثبت نشده باشه (محصولاتی که قبل از این قابلیت ساخته شدن)،
    // همین ذخیره‌ی فعلی به‌عنوان اولین تاریخ ثبت می‌شه
    if ($isNew || !$hasDate || (int) $old !== $price) {
        if ($price > 0) {
            update_post_meta($post_id, '_nexv_price_updated', (int) current_time('timestamp'));
        } else {
            // قیمت برداشته شده («تماس بگیرید»)، پس تاریخ هم معنایی نداره
            delete_post_meta($post_id, '_nexv_price_updated');
        }
    }
}

if (!function_exists('nexv_gregorian_to_jalali')) {
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
}

/* رمزنگاریِ رمز عبور با همون هزینه‌ی ثابتی که بک‌اند (api.php) استفاده می‌کنه.
   با هزینه‌ی پیش‌فرضِ PHP 8.4 (cost=12) هر کاربر حدودِ ۳ دهم ثانیه وقت می‌گرفت،
   یعنی درون‌ریزیِ چندصد مشتری چند دقیقه طول می‌کشید. */
if (!function_exists('nexv_hash_password_fallback')) {
    function nexv_hash_password_fallback($password) {
        if (function_exists('nexv_hash_password')) return nexv_hash_password($password);
        return password_hash((string) $password, PASSWORD_BCRYPT, ['cost' => 10]);
    }
}

/* تبدیلِ رقم‌های لاتین به فارسی، برای شماره‌گذاریِ درستِ متن‌های فارسی. */
if (!function_exists('nexv_fa_digits')) {
    function nexv_fa_digits($v) {
        return strtr((string) $v, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
    }
}

/* «۱۴ مهر ۱۴۰۵» از یک timestamp؛ اگه تاریخی ثبت نشده باشه رشته‌ی خالی برمی‌گردونه. */
function nexv_jalali_pretty($ts) {
    $ts = (int) $ts;
    if ($ts <= 0) return '';
    $months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    $digits = ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹'];
    list($jy, $jm, $jd) = nexv_gregorian_to_jalali((int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts));
    $name = $months[max(0, min(11, $jm - 1))];
    return strtr((string) $jd, $digits) . ' ' . $name . ' ' . strtr((string) $jy, $digits);
}

/* رشته‌ی آماده‌ی نمایش برای کنارِ قیمتِ یک محصول (خالی، اگه قیمت یا تاریخ نباشه). */
function nexv_price_updated_label($post_id) {
    /* محصولی که فقط قیمتِ متغیرها رو داره (قیمتِ پایه صفره) هم تاریخ می‌گیره */
    $hasVariant = (bool) nexv_variant_prices($post_id);
    if ((int) get_post_meta($post_id, '_nexv_price', true) <= 0 && !$hasVariant) return '';
    $ts = get_post_meta($post_id, '_nexv_price_updated', true);
    if ($ts === '' && $hasVariant) $ts = (int) get_post_modified_time('U', false, $post_id);
    return nexv_jalali_pretty($ts);
}

function nexv_find_cat_in_parent($value, $parentId = 0, $allowGlobalName = true) {
    $value = trim((string) $value);
    if ($value === '') return 0;

    /* ۱) اسلاگ: اسلاگ در وردپرس در کلِ یک تاکسونومی یکتاست، پس محدود کردنش به والد
       لازم نیست و حتی مضره؛ فایل‌هایی که ستونِ Parent رو فقط با اسلاگِ دسته پر می‌کنن
       (مثل «iphone-parts» بدون نوشتنِ مسیرِ کامل) این‌طوری درست پیدا می‌شن. */
    /* اسلاگِ فارسی در وردپرس به شکلِ Percent-Encoded ذخیره می‌شه (%d8%a2...). فایل‌های
       واقعی گاهی اسلاگِ کدشده دارن و گاهی همون فارسیِ خوانا («خرید-اپل-واچ»)؛ پس هر دو
       شکل (و شکلِ نرمال‌شده‌ی وردپرس) امتحان می‌شه تا والد در هر صورت پیدا بشه. */
    $slugTries = [$value];
    $decoded = rawurldecode($value);
    if ($decoded !== $value) $slugTries[] = $decoded;
    $sanitized = sanitize_title($value);
    if ($sanitized !== '' && !in_array($sanitized, $slugTries, true)) $slugTries[] = $sanitized;
    $sanitizedDecoded = sanitize_title($decoded);
    if ($sanitizedDecoded !== '' && !in_array($sanitizedDecoded, $slugTries, true)) $slugTries[] = $sanitizedDecoded;

    $bySlug = get_terms([
        'taxonomy'   => 'nexv_cat',
        'slug'       => $slugTries,
        'hide_empty' => false,
        'number'     => 1,
    ]);
    if (!is_wp_error($bySlug) && !empty($bySlug)) return (int) $bySlug[0]->term_id;

    // ۲) نامِ دقیق، محدود به همین والد
    $byName = get_terms([
        'taxonomy'   => 'nexv_cat',
        'name'       => $value,
        'parent'     => $parentId,
        'hide_empty' => false,
        'number'     => 1,
    ]);
    if (!is_wp_error($byName) && !empty($byName)) return (int) $byName[0]->term_id;

    // ۳) روشِ خودِ وردپرس (نام یا اسلاگِ نرمال‌شده)، محدود به همین والد
    $existing = term_exists($value, 'nexv_cat', $parentId);
    if ($existing) return (int) (is_array($existing) ? $existing['term_id'] : $existing);

    /* ۴) نام، در کلِ سایت — فقط وقتی دقیقاً یک دسته با این نام وجود داره.
       این برای پیدا کردنِ «والد» مفیده، ولی موقعِ ساختِ خودِ دسته باید خاموش باشه
       ($allowGlobalName = false)؛ وگرنه ردیفِ «سری 1»ِ زیرِ «خرید» به دسته‌ی هم‌نامِ
       زیرِ «قطعات» وصل می‌شد و ۴۵۴ ردیفِ فایل آخرش فقط ~۹۰ دسته می‌ساخت. */
    if (!$allowGlobalName) return 0;
    $anyName = get_terms([
        'taxonomy'   => 'nexv_cat',
        'name'       => $value,
        'hide_empty' => false,
    ]);
    if (!is_wp_error($anyName) && count($anyName) === 1) return (int) $anyName[0]->term_id;

    return 0;
}

/* نرمال‌سازیِ متنِ فارسی برای مقایسه: ارقامِ فارسی/عربی به انگلیسی، حذفِ نیم‌فاصله،
   یکسان‌سازیِ ی/ک، و حذفِ فاصله و نشانه‌ها. با این کار «آیپد پرو ۱۲.۹/۱۳ اینچ» و
   «آیپد پرو 13 اینچ» قابلِ مقایسه می‌شن. */
function nexv_norm_fa($s) {
    $s = (string) $s;
    $s = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], ['0','1','2','3','4','5','6','7','8','9'], $s);
    $s = str_replace(['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'], ['0','1','2','3','4','5','6','7','8','9'], $s);
    $s = str_replace(['ي', 'ك', "\xE2\x80\x8C", "\xE2\x80\x8E", "\xE2\x80\x8F"], ['ی', 'ک', '', '', ''], $s);
    // حذفِ فاصله و نشانه‌ها تا «آیپد پرو ۱۲.۹/۱۳ اینچ» و «آیپد پرو 13 اینچ» قابلِ مقایسه بشن
    $s = str_replace([' ', "\t", "\r", "\n", '.', '-', '_', '(', ')', '[', ']', '،', ',', '/', '\\', '«', '»', '"', "'"], '', $s);
    return trim(mb_strtolower($s, 'UTF-8'));
}

/* همه‌ی دسته‌ها یک‌بار خونده و کش می‌شن (برای فایل‌های چندهزار ردیفی مهمه). */
function nexv_all_cats($fresh = false) {
    static $cache = null;
    if ($fresh) $cache = null;
    if ($cache !== null) return $cache;
    $terms = get_terms(['taxonomy' => 'nexv_cat', 'hide_empty' => false]);
    $cache = is_wp_error($terms) ? [] : $terms;
    return $cache;
}

/* زنجیره‌ی والدهای یک دسته، از ریشه تا خودش (آرایه‌ای از نام‌ها). */
function nexv_cat_chain($termId) {
    $names = [];
    $guard = 0;
    $t = get_term($termId, 'nexv_cat');
    while ($t && !is_wp_error($t) && $guard++ < 10) {
        array_unshift($names, $t->name);
        if (!$t->parent) break;
        $t = get_term($t->parent, 'nexv_cat');
    }
    return $names;
}

/* ---------- پیدا کردنِ دسته‌ی درست برای یک محصول ----------
   در فایل‌های واقعیِ سایت، ستونِ Categories فقط «نامِ آخرین دسته» رو داره (مثل «نسل ۸»)
   در حالی که همون نام زیرِ چند شاخه‌ی مختلف هست (قطعات/خرید/لوازم جانبی و چند مدلِ متفاوت).
   این تابع بینِ همه‌ی دسته‌های هم‌نام، اونی رو انتخاب می‌کنه که والدهاش بیشترین
   هم‌خوانی رو با نامِ خودِ محصول دارن؛ و اگه باز هم مساوی بودن، شاخه‌ی «قطعات» رو
   ترجیح می‌ده (چون محصولاتِ این فروشگاه قطعه‌ان). */
function nexv_resolve_cat_for_product($value, $productTitle = '') {
    $value = trim((string) $value);
    if ($value === '') return 0;

    // مسیرِ کامل («A > B > C») یا اسلاگ، همون روالِ قطعیِ قبلی
    if (strpos($value, '>') !== false) return nexv_resolve_cat_path($value);
    $direct = nexv_find_cat_in_parent($value, 0);

    $needle = nexv_norm_fa($value);
    $cands = [];
    foreach (nexv_all_cats() as $t) {
        if (nexv_norm_fa($t->name) === $needle || $t->slug === $value) $cands[] = $t;
    }
    if (!$cands) return $direct ?: 0;
    if (count($cands) === 1) return (int) $cands[0]->term_id;

    $title = nexv_norm_fa($productTitle);
    $best = 0; $bestScore = -1; $tie = false;
    foreach ($cands as $t) {
        $chain = nexv_cat_chain($t->term_id);
        array_pop($chain);              // خودِ دسته رو کنار می‌ذاریم؛ فقط والدها مهم‌ان
        $score = 0;
        foreach ($chain as $ancestor) {
            $a = nexv_norm_fa($ancestor);
            if ($a === '') continue;
            if (mb_strpos($a, 'قطعات') !== false) { $score += 2; continue; }   // ترجیحِ شاخه‌ی قطعات
            if (mb_strpos($a, 'خرید') !== false || mb_strpos($a, 'لوازمجانبی') !== false) { $score -= 1; continue; }
            if ($title !== '' && mb_strpos($title, $a) !== false) { $score += 6; continue; }
            // تطبیقِ جزئی: چند تکه‌ی اولِ نامِ والد (مثل «آیپدپرو» از «آیپدپرو12.9/13اینچ»)
            $len = mb_strlen($a);
            for ($cut = $len; $cut >= 6; $cut--) {
                if ($title !== '' && mb_strpos($title, mb_substr($a, 0, $cut)) !== false) { $score += 3; break; }
            }
        }
        if ($score > $bestScore) { $bestScore = $score; $best = (int) $t->term_id; $tie = false; }
        elseif ($score === $bestScore) { $tie = true; }
    }
    if ($tie && $bestScore <= 0) return $direct ?: 0;   // واقعاً قابلِ تشخیص نیست
    return $best ?: ($direct ?: 0);
}

/* ---------- انتخابِ دسته‌ی درست بینِ چند دسته‌ی هم‌نام، با کمکِ «متنِ کمکی» ----------
   در فایل‌های واقعی، ستونِ Parent گاهی فقط نامه («سری 1») در حالی که سه دسته با همین نام
   زیرِ «قطعات»، «خرید» و «لوازم جانبی» وجود داره. ولی اسلاگِ خودِ ردیفِ فرزند معمولاً
   شاخه رو در خودش داره (مثل «۳۸-میلی‌متر-سری-1-قطعات-اپل-واچ»). این تابع از همون
   اسلاگ استفاده می‌کنه تا والدِ درست انتخاب بشه. */
function nexv_find_cat_by_name_ctx($name, $parentId, $context) {
    $name = trim((string) $name);
    if ($name === '') return 0;

    /* مهم: اول باید دید چند دسته با این «نام» هست. اگه چند تا بود، هرگز نباید سراغِ
       جست‌وجوی اسلاگ رفت؛ چون نامی مثل «سری 1» وقتی به اسلاگ تبدیل می‌شه دقیقاً برابرِ
       اسلاگِ یکی از همون چند دسته‌ست و همیشه همون یکی (شاخه‌ی «خرید») انتخاب می‌شد و
       بقیه‌ی زیرشاخه‌ها اشتباه زیرِ اون می‌رفتن. */

    /* کاندیدها مستقیم از دیتابیس خونده می‌شن (نه از کش)، چون وسطِ همین درون‌ریزی
       دسته‌های جدید ساخته می‌شن و کش قدیمی می‌شه. */
    $cands = get_terms(['taxonomy' => 'nexv_cat', 'name' => $name, 'hide_empty' => false]);
    if (is_wp_error($cands) || !$cands) {
        $needle = nexv_norm_fa($name);
        $cands = [];
        foreach (nexv_all_cats(true) as $t) {
            if (nexv_norm_fa($t->name) === $needle) $cands[] = $t;
        }
    }
    // هیچ دسته‌ای با این نام نیست → احتمالاً اسلاگ بوده، نه نام
    if (!$cands) return nexv_find_cat_in_parent($name, $parentId, false);
    if (count($cands) === 1) return (int) $cands[0]->term_id;

    // چند دسته‌ی هم‌نام: اگه دقیقاً یکی‌شون زیرِ همین والده، همون درسته
    $inParent = [];
    foreach ($cands as $t) { if ((int) $t->parent === (int) $parentId) $inParent[] = $t; }
    if (count($inParent) === 1) return (int) $inParent[0]->term_id;
    if (count($inParent) > 1) $cands = $inParent;

    $ctx = nexv_norm_fa(rawurldecode((string) $context));
    $best = 0; $bestLen = -1;
    foreach ($cands as $t) {
        $slugTxt = nexv_norm_fa(rawurldecode($t->slug));
        if ($slugTxt !== '' && $ctx !== '' && mb_strpos($ctx, $slugTxt) !== false) {
            // بلندترین تطبیق برنده‌ست (دقیق‌ترین شاخه)
            $len = mb_strlen($slugTxt);
            if ($len > $bestLen) { $bestLen = $len; $best = (int) $t->term_id; }
        }
    }
    if ($best) return $best;

    // اگه از اسلاگ نشد، از نامِ شاخه‌های والدِ هر کاندید کمک بگیر
    foreach ($cands as $t) {
        foreach (nexv_cat_chain($t->term_id) as $anc) {
            $a = nexv_norm_fa($anc);
            if ($a !== '' && $ctx !== '' && mb_strpos($ctx, $a) !== false) {
                $len = mb_strlen($a);
                if ($len > $bestLen) { $bestLen = $len; $best = (int) $t->term_id; }
            }
        }
    }
    return $best;
}

/* مسیرِ کاملِ دسته («آیفون > قطعات آیفون > آیفون 15» یا با اسلاگ) رو به آیدیِ برگ تبدیل می‌کنه. */
function nexv_resolve_cat_path($path) {
    $parts = array_filter(array_map('trim', explode('>', (string) $path)), 'strlen');
    $parentId = 0; $leafId = 0;
    foreach ($parts as $part) {
        /* اول نامِ دقیق زیرِ همین والد؛ قبلاً اول اسلاگ (بدون توجه به والد) چک می‌شد و
           مثلاً «سری 18» در مسیرِ «قطعات آیفون» به «سری 18»ی شاخه‌ی «خرید آیفون» می‌رسید. */
        $id = nexv_cat_child_by_name($part, $parentId);
        if (!$id) $id = nexv_find_cat_in_parent($part, $parentId);
        if (!$id) return 0;
        $parentId = $id; $leafId = $id;
    }
    return $leafId;
}

add_action('customize_register', 'nexv_customize_register');

/* ---------- ویترینِ بخشِ اولِ صفحه (باکسِ شیشه‌ای) ----------
   پیشخوان ← نمایش ← سفارشی‌سازی ← «ویترین صفحه اول»
   تا ۶ عکسِ محصول + نام؛ عکس‌ها داخل باکسِ شیشه‌ای یکی‌یکی نمایش داده می‌شن.
   بهترین نتیجه: PNG با پس‌زمینه‌ی شفاف یا عکس با پس‌زمینه‌ی سفید (سفید خودکار
   با رنگِ باکس یکی می‌شه). اگه هیچ عکسی انتخاب نشه، آیکون‌های پیش‌فرض نمایش داده می‌شن. */
function nexv_customize_hero_showcase($wp_customize) {
    $wp_customize->add_section('nexv_hero_showcase', [
        'title'       => 'ویترین صفحه اول',
        'description' => 'پوستر افقی بالای صفحه‌ی اول: نام مدل (انگلیسی) بزرگ و بولد در پشت، و عکس محصول ایستاده با کمی زاویه در سمت راست. بهترین نتیجه: عکس رو‌به‌روی محصول، ایستاده، PNG بدون پس‌زمینه (یا با پس‌زمینه‌ی سفید که خودکار همرنگ پوستر میشه). اندازه‌ی پیشنهادی: ارتفاع حدود ۱۲۰۰ پیکسل. فقط از عکس‌هایی استفاده کن که حق استفاده‌شون رو داری.',
        'priority'    => 31,
    ]);
    for ($i = 1; $i <= 6; $i++) {
        $wp_customize->add_setting("nexv_hero_img_$i", ['default' => '', 'sanitize_callback' => 'esc_url_raw']);
        $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, "nexv_hero_img_$i", [
            'label'   => "عکس محصول $i",
            'section' => 'nexv_hero_showcase',
        ]));
        $wp_customize->add_setting("nexv_hero_name_$i", ['default' => '', 'sanitize_callback' => 'sanitize_text_field']);
        $wp_customize->add_control("nexv_hero_name_$i", [
            'label'       => "نام مدل $i (انگلیسی)",
            'description' => 'به انگلیسی و با فونت بولد پشت محصول نمایش داده می‌شه؛ مثلاً: iPhone 17 Pro',
            'section'     => 'nexv_hero_showcase',
            'type'        => 'text',
        ]);
    }
}
add_action('customize_register', 'nexv_customize_hero_showcase');

/* ---------- بیلبوردهای تبلیغاتیِ صفحه‌ی اول ----------
   پیشخوان ← نمایش ← سفارشی‌سازی ← «بیلبوردهای تبلیغاتی» */
function nexv_customize_billboards($wp_customize) {
    $wp_customize->add_section('nexv_billboards', [
        'title'       => 'بیلبوردهای تبلیغاتی',
        'description' => 'دو بیلبوردِ متحرکِ زیرِ باکس‌های ویژگی در صفحه‌ی اول. اگه فیلدی خالی بمونه، متنِ پیش‌فرض نمایش داده می‌شه.',
        'priority'    => 32,
    ]);
    $fields = [
        'nexv_bb_deal_badge' => ['برچسب بیلبورد تخفیف', '٪ پیشنهاد ویژه', 'text'],
        'nexv_bb_deal_title' => ['تیتر بیلبورد تخفیف', 'تخفیف‌های ویژه‌ی قطعات اپل', 'text'],
        'nexv_bb_deal_text'  => ['متن بیلبورد تخفیف', 'پیشنهادهای محدودِ این هفته روی قطعاتِ اورجینال', 'textarea'],
        'nexv_bb_deal_link'  => ['لینک بیلبورد تخفیف (خالی = پرش به محصولات)', '', 'url'],
        'nexv_bb_ticker'     => ['کلمه‌های نوار متحرک (با ویرگول جدا کن)', 'باتری، ال‌سی‌دی، مادربرد، آیفون، آیپد، اپل واچ، قطعات اورجینال', 'text'],
        'nexv_bb_help_title' => ['تیتر بیلبورد مشاوره', 'مطمئن نیستی کدوم قطعه؟', 'text'],
        'nexv_bb_help_text'  => ['متن بیلبورد مشاوره', 'مدل دستگاهت رو بفرست؛ قطعه‌ی دقیق و سازگار رو رایگان برات پیدا می‌کنیم.', 'textarea'],
    ];
    foreach ($fields as $id => [$label, $default, $type]) {
        $wp_customize->add_setting($id, [
            'default' => $default,
            'sanitize_callback' => $type === 'url' ? 'esc_url_raw' : ($type === 'textarea' ? 'sanitize_textarea_field' : 'sanitize_text_field'),
        ]);
        $wp_customize->add_control($id, ['label' => $label, 'section' => 'nexv_billboards', 'type' => $type === 'url' ? 'url' : $type]);
    }
}
add_action('customize_register', 'nexv_customize_billboards');

function nexv_hero_slides() {
    $slides = [];
    for ($i = 1; $i <= 6; $i++) {
        $src = trim((string) get_theme_mod("nexv_hero_img_$i", ''));
        if ($src === '') continue;
        $slides[] = ['src' => esc_url($src), 'name' => (string) get_theme_mod("nexv_hero_name_$i", '')];
    }
    if ($slides) return $slides;

    /* پوسترهای پیش‌فرض: عکس‌هایی که همراه قالب هستن (پوشه‌ی hero).
       هر عکس رنگِ پس‌زمینه‌ی خودش رو داره تا لبه‌ی عکس با پوستر یکی بشه و مرزی دیده نشه.
       به‌محضِ اینکه از «سفارشی‌سازی ← ویترین صفحه اول» عکس بذاری، این‌ها کنار می‌رن. */
    $u = get_template_directory_uri() . '/hero/';
    return [
        /* scale: بزرگ‌نماییِ عکس از پایین به بالا (۱ = اندازه‌ی عادی) تا قدِ محصول بلندتر دیده بشه */
        /* این سه عکس روی بومِ یکسان (۱۶:۱۰) نرمال شدن: قدِ هر سه محصول برابره، پس
           دیگه نیازی به scale نیست. ایرپاد دست‌نخورده و به‌صورت اصلی نمایش داده می‌شه. */
        ['src' => $u . 'iphone.jpg',     'name' => 'iPhone',     'bg' => '#f4f8f9', 'wide' => true],
        ['src' => $u . 'iphone-duo.jpg', 'name' => 'iPhone Duo', 'bg' => '#f6f6f8', 'wide' => true],
        ['src' => $u . 'ipad.jpg',       'name' => 'iPad Pro',   'bg' => '#f5f5f7', 'wide' => true],
        ['src' => $u . 'airpods.jpg',    'name' => 'AirPods',    'bg' => '#e3e3e3', 'wide' => true],
    ];
}

/* =========================================================================
 * به‌روزرسانیِ قالب از گیت‌هاب با یک کلیک (NEXV ← به‌روزرسانی قالب)
 * ========================================================================= */
define('NEXV_UPDATE_REPO', 'git-nexv/site-apple');
define('NEXV_UPDATE_BRANCH', 'main');

add_action('admin_menu', function () {
    add_submenu_page('nexv-dashboard', 'به‌روزرسانی قالب', 'به‌روزرسانی قالب', 'manage_options', 'nexv-theme-update', 'nexv_theme_update_page_html');
}, 10);

function nexv_theme_update_page_html() {
    $token = get_option('nexv_github_token', '');
    ?>
    <div class="wrap">
        <h1>به‌روزرسانی قالب</h1>
        <?php if (isset($_GET['updated'])): ?>
            <div class="notice notice-success"><p>قالب با موفقیت به آخرین نسخه به‌روز شد.</p></div>
        <?php elseif (!empty($_GET['error'])): ?>
            <div class="notice notice-error"><p>خطا در به‌روزرسانی: <?php echo esc_html(wp_unslash($_GET['error'])); ?></p></div>
        <?php endif; ?>
        <p>با زدن دکمه‌ی زیر، آخرین نسخه‌ی قالب از گیت‌هاب (<code><?php echo esc_html(NEXV_UPDATE_REPO . ' / ' . NEXV_UPDATE_BRANCH); ?></code>) دانلود و جایگزین فایل‌های فعلی می‌شه.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="nexv_theme_update">
            <?php wp_nonce_field('nexv_theme_update'); ?>
            <p>
                <label>توکن گیت‌هاب (فقط اگه مخزن خصوصیه):<br>
                <input type="password" name="token" class="regular-text" value="<?php echo esc_attr($token); ?>" autocomplete="off"></label>
            </p>
            <?php submit_button('به‌روزرسانی قالب', 'primary large', 'submit', false); ?>
        </form>
    </div>
    <?php
}

add_action('admin_post_nexv_theme_update', function () {
    if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز.');
    check_admin_referer('nexv_theme_update');
    $back = admin_url('admin.php?page=nexv-theme-update');
    $fail = function ($msg) use ($back) { wp_redirect(add_query_arg('error', rawurlencode($msg), $back)); exit; };

    $token = trim(sanitize_text_field(wp_unslash($_POST['token'] ?? '')));
    update_option('nexv_github_token', $token, false);

    require_once ABSPATH . 'wp-admin/includes/file.php';
    WP_Filesystem();

    $headers = ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'nexv-theme-updater'];
    if ($token !== '') $headers['Authorization'] = 'Bearer ' . $token;
    $res = wp_remote_get('https://api.github.com/repos/' . NEXV_UPDATE_REPO . '/zipball/' . NEXV_UPDATE_BRANCH, ['headers' => $headers, 'timeout' => 120]);
    if (is_wp_error($res)) $fail($res->get_error_message());
    $code = wp_remote_retrieve_response_code($res);
    if ($code !== 200) $fail('پاسخ گیت‌هاب ' . $code . ($code == 404 ? ' (اگه مخزن خصوصیه، توکن رو وارد کن)' : ''));

    $zip = wp_tempnam('nexv-theme.zip');
    file_put_contents($zip, wp_remote_retrieve_body($res));
    $tmp = trailingslashit(get_temp_dir()) . 'nexv-theme-' . wp_generate_password(8, false);
    $un = unzip_file($zip, $tmp);
    @unlink($zip);
    if (is_wp_error($un)) $fail($un->get_error_message());

    $dirs = glob($tmp . '/*', GLOB_ONLYDIR);
    if (empty($dirs) || !file_exists($dirs[0] . '/style.css')) { global $wp_filesystem; $wp_filesystem->delete($tmp, true); $fail('فایل‌های قالب در بسته پیدا نشد.'); }
    $src = $dirs[0];
    global $wp_filesystem;
    $wp_filesystem->delete($src . '/.github', true);
    $wp_filesystem->delete($src . '/README.md');
    $copied = copy_dir($src, get_stylesheet_directory());
    $wp_filesystem->delete($tmp, true);
    if (is_wp_error($copied)) $fail($copied->get_error_message());

    delete_transient('nexv_products_cache_v2');
    delete_transient('nexv_categories_cache_v1');
    if (function_exists('opcache_reset')) @opcache_reset();
    wp_redirect(add_query_arg('updated', '1', $back));
    exit;
});

/* =========================================================================
 * سایت‌مپ Rank Math: مقالات و دسته‌بندی‌ها هم حتماً داخل سایت‌مپ باشن،
 * هر سایت‌مپ تا ۱۰۰۰ لینک، و عکس‌های گالریِ محصول هم برای Google Images.
 * ========================================================================= */
add_filter('rank_math/sitemap/exclude_post_type', function ($exclude, $type) {
    return in_array($type, ['nexv_product', 'nexv_article'], true) ? false : $exclude;
}, 20, 2);
add_filter('rank_math/sitemap/exclude_taxonomy', function ($exclude, $type) {
    return $type === 'nexv_cat' ? false : $exclude;
}, 20, 2);
add_filter('rank_math/sitemap/entries_per_page', function () { return 1000; });
add_filter('rank_math/sitemap/urlimages', function ($images, $post_id) {
    if (get_post_type($post_id) !== 'nexv_product' || !function_exists('nexv_gallery_urls')) return $images;
    $have = wp_list_pluck((array) $images, 'src');
    $title = get_the_title($post_id);
    foreach ((array) nexv_gallery_urls($post_id) as $src) {
        if ($src && !in_array($src, $have, true)) { $images[] = ['src' => $src, 'title' => $title, 'alt' => $title]; $have[] = $src; }
    }
    return $images;
}, 20, 2);

/* =========================================================================
 * صفحه‌های کمکیِ سبد خرید/حساب: در گوگل ایندکس نشن و در سایت‌مپ نباشن؛
 * «برگه نمونه»ی پیش‌فرض وردپرس هم به صفحه‌ی اصلی ریدایرکت (۳۰۱) می‌شه.
 * ========================================================================= */
function nexv_utility_page_slugs() { return ['cart', 'checkout', 'my-account', 'shop']; }
add_action('template_redirect', function () {
    if (is_page(['برگه-نمونه', 'sample-page'])) { wp_safe_redirect(home_url('/'), 301); exit; }
});
add_filter('wp_robots', function ($robots) {
    return $robots;
}, 99);
add_filter('rank_math/frontend/robots', function ($robots) {
    return $robots;
}, 99);
add_filter('rank_math/sitemap/entry', function ($url, $type, $object) {
    if ($type === 'post' && $object instanceof WP_Post && $object->post_type === 'page'
        && in_array(urldecode($object->post_name), ['برگه-نمونه', 'sample-page'], true)) {
        return false;
    }
    return $url;
}, 20, 3);

/* =========================================================================
 * پیوند یکتای انگلیسی برای دسته‌بندی‌های محصول (nexv_cat)
 * هر دسته‌ای که ساخته/ویرایش بشه و اسلاگش فارسی باشه، از روی نامش انگلیسی می‌شه
 * (مثلاً «قطعات آیفون» -> iphone-parts). اگه تکراری بود، اسلاگِ والد به آخرش اضافه می‌شه.
 * اسلاگِ فارسیِ قبلی نگه داشته می‌شه تا لینک‌های قدیمی با ۳۰۱ به لینکِ جدید برن.
 * ========================================================================= */
function nexv_slug_is_english($slug) {
    return $slug !== '' && strpos($slug, '%') === false && !preg_match('/[^\x00-\x7f]/', $slug);
}
function nexv_english_term_slug($term) {
    $base = nexv_transliterate_title_to_slug($term->name);
    $slug = $base;
    $taken = function ($s) use ($term) { $t = get_term_by('slug', $s, 'nexv_cat'); return $t && (int) $t->term_id !== (int) $term->term_id; };
    if ($taken($slug) && $term->parent) {
        $parent = get_term($term->parent, 'nexv_cat');
        if ($parent && !is_wp_error($parent)) $slug = $base . '-' . (nexv_slug_is_english($parent->slug) ? $parent->slug : nexv_transliterate_title_to_slug($parent->name));
    }
    $i = 2; $try = $slug;
    while ($taken($try)) { $try = $slug . '-' . $i++; }
    return $try;
}
function nexv_fix_category_slug($term_id) {
    static $busy = false;
    if ($busy) return false;
    $term = get_term($term_id, 'nexv_cat');
    if (!$term || is_wp_error($term) || nexv_slug_is_english($term->slug)) return false;
    $busy = true;
    add_term_meta($term->term_id, '_nexv_old_slug', urldecode($term->slug));
    $r = wp_update_term($term->term_id, 'nexv_cat', ['slug' => nexv_english_term_slug($term)]);
    $busy = false;
    return !is_wp_error($r);
}
add_action('created_nexv_cat', 'nexv_fix_category_slug', 20);
add_action('edited_nexv_cat', 'nexv_fix_category_slug', 20);

function nexv_fix_all_category_slugs() {
    $fixed = 0;
    /* اول والدها، بعد فرزندها (ترتیب بر اساس عمق) تا اسلاگِ والد قبل از فرزند انگلیسی شده باشه */
    $terms = get_terms(['taxonomy' => 'nexv_cat', 'hide_empty' => false]);
    if (is_wp_error($terms)) return 0;
    usort($terms, function ($a, $b) { return count(get_ancestors($a->term_id, 'nexv_cat')) - count(get_ancestors($b->term_id, 'nexv_cat')); });
    foreach ($terms as $t) { if (nexv_fix_category_slug($t->term_id)) $fixed++; }
    delete_transient('nexv_categories_cache_v1');
    delete_transient('nexv_products_cache_v2');
    flush_rewrite_rules();
    return $fixed;
}
/* یک بار به‌صورت خودکار بعد از به‌روزرسانیِ قالب اجرا می‌شه */
add_action('admin_init', function () {
    if (get_option('nexv_cat_slugs_en_v1') || !current_user_can('manage_options')) return;
    @set_time_limit(0);
    nexv_fix_all_category_slugs();
    update_option('nexv_cat_slugs_en_v1', 1, false);
});

/* لینک‌های قدیمیِ فارسیِ دسته‌بندی -> ۳۰۱ به لینکِ انگلیسیِ جدید */
add_action('template_redirect', function () {
    if (!is_404()) return;
    $path = trim(urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH)), '/');
    if (!preg_match('#(?:^|/)product-cat/(.+)$#u', $path, $m)) return;
    $parts = explode('/', trim($m[1], '/'));
    $last = end($parts);
    $found = get_terms(['taxonomy' => 'nexv_cat', 'hide_empty' => false, 'number' => 1, 'meta_key' => '_nexv_old_slug', 'meta_value' => $last]);
    if (!$found || is_wp_error($found)) { $t = get_term_by('slug', sanitize_title($last), 'nexv_cat'); $found = $t ? [$t] : []; }
    if ($found) { $link = get_term_link($found[0]); if (!is_wp_error($link)) { wp_redirect($link, 301); exit; } }
}, 1);

/* =========================================================================
 * افزودنِ یک‌باره‌ی محصولاتِ جدید از فایل‌های پوشه‌ی seed/ (بعد از به‌روزرسانیِ قالب)
 * فقط محصول جدید می‌سازه؛ هیچ محصولِ موجودی تغییر نمی‌کنه. اگه محصولی با همین
 * عنوان از قبل (منتشرشده/پیش‌نویس/خصوصی) وجود داشته باشه، رد می‌شه تا تکراری نشه.
 * ========================================================================= */
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    $files = glob(get_stylesheet_directory() . '/seed/*.csv');
    if (!$files) return;
    global $wpdb;
    /* به درخواستِ صریحِ مدیر سایت: همین ۶ محصولِ مشخص (فقط با این شناسه‌ها) یک بار حذف
       می‌شن تا از روی فایل seed دوباره کامل ساخته بشن (فقط محصولاتی که عنوانشون دقیقاً یکی از
       عنوان‌های فایل seed هست). به هیچ محصولِ دیگه‌ای دست زده نمی‌شه. */
    if (!get_option('nexv_seed_recreate_v2')) {
        $titles = [];
        foreach (array_filter($files, function ($f) { return basename($f) === 'products-2026-10.csv'; }) as $f) {
            if (($h = fopen($f, 'r')) === false) continue;
            $hd = fgetcsv($h); $ni = array_search('Name', array_map(function ($x) { return preg_replace('/^\xEF\xBB\xBF/', '', $x); }, (array) $hd));
            while (($rw = fgetcsv($h)) !== false) { if ($ni !== false && isset($rw[$ni])) $titles[] = sanitize_text_field($rw[$ni]); }
            fclose($h);
        }
        foreach ($titles as $t) {
            $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'nexv_product' AND post_title = %s", $t));
            foreach ($ids as $pid) wp_delete_post((int) $pid, true);
        }
        update_option('nexv_seed_recreate_v2', 1, false);
        delete_option('nexv_seed_done_v2');
    }
    $done = (array) get_option('nexv_seed_done_v2', []);
    $offsets = (array) get_option('nexv_seed_offsets', []);
    $report = (array) get_transient('nexv_seed_report') + ['created' => [], 'skipped' => [], 'nocat' => []];
    /* پردازشِ دسته‌ای: هر بار حداکثر ~۲۰ ثانیه کار انجام می‌شه و جای ادامه ذخیره می‌شه؛
       با هر بار باز شدنِ پیشخوان (یا رفرشِ خودکار) ادامه پیدا می‌کنه تا تموم بشه. */
    $started = microtime(true);
    @set_time_limit(120);
    wp_defer_term_counting(true);
    foreach ($files as $file) {
        $key = basename($file);
        if (in_array($key, $done, true)) continue;
        $fh = fopen($file, 'r');
        if (!$fh) continue;
        $head = fgetcsv($fh);
        if ($head) $head[0] = preg_replace('/^\xEF\xBB\xBF/', '', $head[0]);
        $rowNo = 0; $from = (int) ($offsets[$key] ?? 0); $finished = true;
        while (($row = fgetcsv($fh)) !== false) {
            $rowNo++;
            if ($rowNo <= $from) continue;
            if (microtime(true) - $started > 20) { $finished = false; $rowNo--; break; }
            if (!$head || count($row) !== count($head)) continue;
            $r = array_combine($head, $row);
            $name = sanitize_text_field($r['Name'] ?? '');
            if ($name === '') continue;
            $dupe = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'nexv_product' AND post_title = %s AND post_status IN ('publish','draft','pending','private','future') LIMIT 1",
                $name
            ));
            if ($dupe) { $report['skipped'][] = $name . ' (#' . $dupe . ')'; continue; }
            $postArr = ['post_type' => 'nexv_product', 'post_title' => $name, 'post_content' => wp_kses_post($r['Description'] ?? ''), 'post_status' => 'publish'];
            if (!empty($r['Slug'])) $postArr['post_name'] = sanitize_title($r['Slug']);
            $id = wp_insert_post($postArr);
            if (is_wp_error($id) || !$id) continue;
            nexv_save_price_with_date($id, 0);
            update_post_meta($id, '_nexv_short_desc', sanitize_textarea_field($r['Short description'] ?? ''));
            if (!empty($r['Color Options'])) update_post_meta($id, '_nexv_colors', sanitize_text_field($r['Color Options']));
            if (!empty($r['Storage Options'])) update_post_meta($id, '_nexv_storages', sanitize_text_field($r['Storage Options']));
            if (!empty($r['Tags'])) update_post_meta($id, '_nexv_badge', implode('|', array_filter(array_map('trim', preg_split('/\s*[,،|]\s*/u', $r['Tags'])))));
            if (!empty($r['Variant Groups'])) {
                $groups = [];
                foreach (explode(';', $r['Variant Groups']) as $chunk) {
                    if (strpos($chunk, ':') === false) continue;
                    [$gl, $go] = array_map('trim', explode(':', $chunk, 2));
                    $opts = array_values(array_filter(array_map('trim', explode('|', $go))));
                    if ($gl !== '' && $opts) $groups[] = ['label' => sanitize_text_field($gl), 'options' => array_map('sanitize_text_field', $opts)];
                }
                if ($groups) update_post_meta($id, '_nexv_variant_groups', wp_json_encode($groups, JSON_UNESCAPED_UNICODE));
            }
            update_post_meta($id, '_nexv_color', '#6a4dff');
            update_post_meta($id, '_nexv_icon', 'disc');
            if (!empty($r['Categories'])) {
                $leaf = nexv_resolve_cat_for_product($r['Categories'], $name);
                if ($leaf) wp_set_post_terms($id, [$leaf], 'nexv_cat'); else $report['nocat'][] = $name;
            }
            $report['created'][] = $name;
        }
        fclose($fh);
        if ($finished) {
            $done[] = $key; unset($offsets[$key]);
            update_option('nexv_seed_done_v2', $done, false);
        } else {
            $offsets[$key] = $rowNo;
            set_transient('nexv_seed_pending', ['file' => $key, 'row' => $rowNo, 'created' => count($report['created'])], HOUR_IN_SECONDS);
        }
        update_option('nexv_seed_offsets', $offsets, false);
        delete_transient('nexv_products_cache_v2');
        set_transient('nexv_seed_report', $report, DAY_IN_SECONDS);
        if (!$finished) break;
        delete_transient('nexv_seed_pending');
    }
    wp_defer_term_counting(false);
});
/* تا وقتی افزودنِ محصولات تموم نشده، پیشخوان خودش رو چند ثانیه یک‌بار رفرش می‌کنه تا ادامه بده */
add_action('admin_notices', function () {
    $p = get_transient('nexv_seed_pending');
    if (!$p || !current_user_can('manage_options')) return;
    echo '<div class="notice notice-warning"><p><strong>NEXV — در حال افزودن محصولات جدید...</strong> تا الان ' . (int) $p['created'] . ' محصول ساخته شد. لطفاً این صفحه رو نبندید؛ خودکار ادامه پیدا می‌کنه.</p></div>';
    echo '<script>setTimeout(function(){ location.reload(); }, 1500);</script>';
});
add_action('admin_notices', function () {
    $r = get_transient('nexv_seed_report');
    if (!$r || !current_user_can('manage_options')) return;
    if (get_transient('nexv_seed_pending')) return;   // تا پایانِ کار فقط پیامِ پیشرفت نشون داده می‌شه
    delete_transient('nexv_seed_report');
    echo '<div class="notice notice-info is-dismissible"><p><strong>NEXV — افزودن محصولات جدید:</strong></p>';
    $list = $r['created']; $more = count($list) > 30 ? ' و ' . (count($list) - 30) . ' محصول دیگه' : '';
    echo '<p>ساخته شد (' . count($r['created']) . '): ' . esc_html(implode('، ', array_slice($list, 0, 30)) ?: '—') . esc_html($more) . '</p>';
    if ($r['skipped']) echo '<p>از قبل وجود داشت و ساخته نشد (' . count($r['skipped']) . '): ' . esc_html(implode('، ', array_slice($r['skipped'], 0, 20))) . '</p>';
    if ($r['nocat']) echo '<p>دسته‌بندی پیدا نشد برای: ' . esc_html(implode('، ', $r['nocat'])) . '</p>';
    echo '</div>';
});

function nexv_cat_child_by_name($name, $parentId) {
    $needle = nexv_norm_fa($name);
    $kids = get_terms(['taxonomy' => 'nexv_cat', 'parent' => (int) $parentId, 'hide_empty' => false]);
    if (is_wp_error($kids)) return 0;
    foreach ($kids as $k) { if (nexv_norm_fa($k->name) === $needle) return (int) $k->term_id; }
    return 0;
}
function nexv_cat_by_path_names($path) {
    $id = 0;
    foreach (array_map('trim', explode('>', $path)) as $part) {
        $id = nexv_cat_child_by_name($part, $id);
        if (!$id) return 0;
    }
    return $id;
}

/* =========================================================================
 * اصلاحِ یک‌باره‌ی دسته‌بندی‌ها (به درخواستِ مدیر سایت):
 * ۱) «۴۰ میلی‌متر» و «۴۴ میلی‌متر» اشتباهاً زیرِ «آیفون > خرید آیفون > سری 4» بودن؛
 *    به «اپل واچ > خرید اپل واچ > سری 4» منتقل می‌شن.
 * ۲) «اپل واچ > خرید اپل واچ > سری 7» زیردسته نداشت؛ «۴۱ میلی‌متر» و «۴۵ میلی‌متر» ساخته می‌شن.
 * ۳) ۶ محصولِ جدید (مادربرد 4S و قطعاتِ ۱۸ پرو مکس) به دسته‌ی درستِ شاخه‌ی «قطعات» وصل می‌شن.
 * ========================================================================= */
add_action('admin_init', function () {
    if (get_option('nexv_cat_fix_v1') || !current_user_can('manage_options')) return;
    $log = [];
    $iphoneS4 = nexv_cat_by_path_names('آیفون > خرید آیفون > سری 4');
    $watchS4  = nexv_cat_by_path_names('اپل واچ > خرید اپل واچ > سری 4');
    if ($iphoneS4 && $watchS4) {
        foreach (['۴۰ میلی‌متر', '۴۴ میلی‌متر'] as $n) {
            $wrong = nexv_cat_child_by_name($n, $iphoneS4);
            if (!$wrong) continue;
            if (nexv_cat_child_by_name($n, $watchS4)) { $log[] = "«$n» از قبل زیرِ سری 4 اپل واچ بود؛ نسخه‌ی اشتباه دست نخورد"; continue; }
            wp_update_term($wrong, 'nexv_cat', ['parent' => $watchS4]);
            $log[] = "«$n» به اپل واچ > خرید اپل واچ > سری 4 منتقل شد";
        }
    }
    $watchS7 = nexv_cat_by_path_names('اپل واچ > خرید اپل واچ > سری 7');
    if ($watchS7) {
        foreach (['۴۱ میلی‌متر', '۴۵ میلی‌متر'] as $n) {
            if (nexv_cat_child_by_name($n, $watchS7)) continue;
            $r = wp_insert_term($n, 'nexv_cat', ['parent' => $watchS7, 'slug' => 'apple-watch-series-7-' . (strpos($n, '۴۱') !== false ? '41' : '45') . 'mm-buy']);
            if (!is_wp_error($r)) $log[] = "«$n» زیرِ اپل واچ > خرید اپل واچ > سری 7 ساخته شد";
        }
    }
    $fixes = [
        'مادربرد آیفون 4S | iPhone 4s Motherboard' => 'آیفون > قطعات آیفون > سری 4 > آیفون 4S',
    ];
    foreach (['مادربرد آیفون 18 پرو مکس | iPhone 18 Pro Max Motherboard', 'ال سی دی آیفون ۱۸ پرو مکس | iPhone 18 Pro Max LCD',
              'باتری آیفون ۱۸ پرو مکس | iPhone 18 Pro Max Battery', 'دوربین پشت آیفون ۱۸ پرو مکس | iPhone 18 Pro Max Rear Camera',
              'دوربین جلو آیفون ۱۸ پرو مکس | iPhone 18 Pro Max Front Camera'] as $t) {
        $fixes[$t] = 'آیفون > قطعات آیفون > سری 18 > آیفون 18 پرو مکس';
    }
    global $wpdb;
    foreach ($fixes as $title => $path) {
        $leaf = nexv_cat_by_path_names($path);
        if (!$leaf) { $log[] = "دسته‌ی «$path» پیدا نشد"; continue; }
        $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'nexv_product' AND post_title = %s AND post_status <> 'trash'", $title));
        foreach ($ids as $pid) { wp_set_post_terms((int) $pid, [$leaf], 'nexv_cat'); $log[] = "دسته‌ی «$title» درست شد"; }
    }
    delete_transient('nexv_categories_cache_v1');
    delete_transient('nexv_products_cache_v2');
    clean_term_cache([], 'nexv_cat');
    update_option('nexv_cat_fix_v1', 1, false);
    set_transient('nexv_cat_fix_report', $log, DAY_IN_SECONDS);
}, 30);
add_action('admin_notices', function () {
    $log = get_transient('nexv_cat_fix_report');
    if (!$log || !current_user_can('manage_options')) return;
    delete_transient('nexv_cat_fix_report');
    echo '<div class="notice notice-info is-dismissible"><p><strong>NEXV — اصلاح دسته‌بندی‌ها:</strong></p><ul style="list-style:disc;padding-right:20px">';
    foreach ($log as $l) echo '<li>' . esc_html($l) . '</li>';
    echo '</ul></div>';
});

/* =========================================================================
 * اصلاحِ دومِ دسته‌بندی‌ها (نسخه‌ی مقاوم‌تر، بر اساسِ داده‌ی واقعیِ سایت):
 * ۱) هر محصولی که عنوانش «قطعه + آیفون ۱۸ پرو مکس» هست (مادربرد/ال سی دی/باتری/دوربین)
 *    فقط به «آیفون 18 پرو مکس»ِ شاخه‌ی «قطعات آیفون» وصل می‌شه.
 * ۲) زیردسته‌های «اپل واچ > خرید اپل واچ > سری 7»: سایزِ تکراری حذف (محصولاتش به
 *    نسخه‌ی باقی‌مونده منتقل) و سایزِ جاافتاده ساخته می‌شه تا دقیقاً ۴۱ و ۴۵ بمونه.
 * ========================================================================= */
function nexv_cat_has_ancestor_like($termId, $needle) {
    foreach (get_ancestors($termId, 'nexv_cat') as $a) {
        $t = get_term($a, 'nexv_cat');
        if ($t && !is_wp_error($t) && mb_strpos(nexv_norm_fa($t->name), nexv_norm_fa($needle)) !== false) return true;
    }
    return false;
}
add_action('admin_init', function () {
    if (get_option('nexv_cat_fix_v2') || !current_user_can('manage_options')) return;
    global $wpdb;
    $log = [];
    $all = get_terms(['taxonomy' => 'nexv_cat', 'hide_empty' => false]);
    if (is_wp_error($all)) return;

    /* ---- ۱) قطعاتِ آیفون ۱۸ پرو مکس ---- */
    $partsLeaf = 0; $buyLeaf = 0;
    foreach ($all as $t) {
        if (nexv_norm_fa($t->name) !== nexv_norm_fa('آیفون 18 پرو مکس')) continue;
        if (nexv_cat_has_ancestor_like($t->term_id, 'قطعات')) $partsLeaf = (int) $t->term_id;
        elseif (nexv_cat_has_ancestor_like($t->term_id, 'خرید')) $buyLeaf = (int) $t->term_id;
    }
    if (!$partsLeaf) {
        $log[] = 'دسته‌ی «آیفون 18 پرو مکس» زیرِ «قطعات آیفون» پیدا نشد.';
    } else {
        $rows = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'nexv_product' AND post_status <> 'trash' AND post_status <> 'auto-draft'");
        foreach ($rows as $r) {
            $n = nexv_norm_fa($r->post_title);
            $is18pm = mb_strpos($n, nexv_norm_fa('آیفون 18 پرو مکس')) !== false || mb_strpos($n, 'iphone18promax') !== false;
            $isPart = preg_match('/مادربرد|السیدی|باتری|دوربین|motherboard|lcd|battery|camera/u', $n);
            if (!$is18pm || !$isPart) continue;
            $before = wp_get_post_terms((int) $r->ID, 'nexv_cat', ['fields' => 'ids']);
            if (is_array($before) && $before === [$partsLeaf]) { $log[] = "«{$r->post_title}» از قبل درست بود"; continue; }
            wp_set_post_terms((int) $r->ID, [$partsLeaf], 'nexv_cat');
            $log[] = "«{$r->post_title}» (#{$r->ID}) به قطعات آیفون ← آیفون 18 پرو مکس منتقل شد";
        }
    }

    /* ---- ۲) سری 7ِ خریدِ اپل واچ ---- */
    $s7 = 0;
    foreach ($all as $t) {
        if (nexv_norm_fa($t->name) === nexv_norm_fa('سری 7') && nexv_cat_has_ancestor_like($t->term_id, 'خرید') && nexv_cat_has_ancestor_like($t->term_id, 'اپل')) { $s7 = (int) $t->term_id; break; }
    }
    if (!$s7) {
        $log[] = 'دسته‌ی «اپل واچ > خرید اپل واچ > سری 7» پیدا نشد.';
    } else {
        $kids = get_terms(['taxonomy' => 'nexv_cat', 'parent' => $s7, 'hide_empty' => false, 'orderby' => 'term_id']);
        $bySize = [];
        foreach ((array) $kids as $k) {
            $num = preg_replace('/\D/', '', nexv_norm_fa($k->name));
            $bySize[$num][] = $k;
        }
        $mm = function ($n) { return strtr($n, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']) . ' میلی‌متر'; };
        /* اگه ۴۱ دوتا هست و ۴۵ نیست، دومیِ ۴۱ (همونی که بعداً ساخته شده) به ۴۵ تغییر نام می‌ده */
        if (count($bySize['41'] ?? []) > 1 && empty($bySize['45'])) {
            $extra = array_pop($bySize['41']);
            wp_update_term($extra->term_id, 'nexv_cat', ['name' => $mm('45'), 'slug' => 'apple-watch-series-7-45mm-buy']);
            $bySize['45'] = [$extra];
            $log[] = 'یکی از دو «۴۱ میلی‌متر»ِ سری 7 به «۴۵ میلی‌متر» تغییر کرد';
        }
        foreach ($bySize as $num => $list) {
            if ($num === '' ) continue;
            $keep = array_shift($list);
            if (nexv_norm_fa($keep->name) !== nexv_norm_fa($mm($num))) wp_update_term($keep->term_id, 'nexv_cat', ['name' => $mm($num)]);
            foreach ($list as $dup) {
                $pids = get_objects_in_term($dup->term_id, 'nexv_cat');
                foreach ((array) $pids as $pid) wp_add_object_terms((int) $pid, (int) $keep->term_id, 'nexv_cat');
                wp_delete_term($dup->term_id, 'nexv_cat');
                $log[] = "«{$dup->name}» تکراری در سری 7 حذف شد" . ($pids ? ' (محصولاتش منتقل شدن)' : '');
            }
        }
        foreach (['41', '45'] as $num) {
            if (!empty($bySize[$num])) continue;
            $r = wp_insert_term($mm($num), 'nexv_cat', ['parent' => $s7, 'slug' => "apple-watch-series-7-{$num}mm-buy"]);
            if (!is_wp_error($r)) $log[] = "«{$mm($num)}» زیرِ سری 7 ساخته شد";
        }
        $final = get_terms(['taxonomy' => 'nexv_cat', 'parent' => $s7, 'hide_empty' => false, 'fields' => 'names']);
        $log[] = 'زیردسته‌های فعلیِ سری 7 (خرید): ' . implode('، ', (array) $final);
    }

    delete_transient('nexv_categories_cache_v1');
    delete_transient('nexv_products_cache_v2');
    clean_term_cache([], 'nexv_cat');
    update_option('nexv_cat_fix_v2', 1, false);
    set_transient('nexv_cat_fix_report', $log, DAY_IN_SECONDS);
}, 31);

/* =========================================================================
 * اصلاحِ سوم: هر محصولی که «قطعه» هست (مادربرد، ال سی دی، باتری، دوربین، ...)
 * ولی به دسته‌ای زیرِ شاخه‌ی «خرید ...» وصل شده، به دسته‌ی هم‌مسیرش در شاخه‌ی
 * «قطعات ...» منتقل می‌شه (مثلاً خرید آیفون > سری 18 > آیفون 18 پرو
 *  -> قطعات آیفون > سری 18 > آیفون 18 پرو).
 * ========================================================================= */
add_action('admin_init', function () {
    if (get_option('nexv_cat_fix_v3') || !current_user_can('manage_options')) return;
    global $wpdb;
    $log = [];
    $partRe = '/مادربرد|السیدی|باتری|دوربین|بدنه|شاسی|تاچ|فلت|اسپیکر|بلندگو|میکروفون|سوکت|motherboard|lcd|battery|camera|body|chassis/u';
    $rows = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'nexv_product' AND post_status NOT IN ('trash','auto-draft')");
    foreach ($rows as $r) {
        if (!preg_match($partRe, nexv_norm_fa($r->post_title))) continue;
        $terms = wp_get_post_terms((int) $r->ID, 'nexv_cat');
        if (is_wp_error($terms) || !$terms) continue;
        $newIds = []; $changed = false;
        foreach ($terms as $t) {
            $chain = array_reverse(array_map(function ($id) { $x = get_term($id, 'nexv_cat'); return $x ? $x->name : ''; }, get_ancestors($t->term_id, 'nexv_cat')));
            $chain[] = $t->name;
            $buyIdx = null;
            foreach ($chain as $i => $n) { if (mb_strpos(nexv_norm_fa($n), 'خرید') === 0) { $buyIdx = $i; break; } }
            if ($buyIdx === null) { $newIds[] = (int) $t->term_id; continue; }
            $alt = $chain;
            $alt[$buyIdx] = preg_replace('/^\s*خرید/u', 'قطعات', $chain[$buyIdx]);
            $target = nexv_cat_by_path_names(implode(' > ', $alt));
            if ($target) { $newIds[] = $target; $changed = true; $log[] = "«{$r->post_title}» (#{$r->ID}): " . implode(' ← ', $chain) . '  →  ' . implode(' ← ', $alt); }
            else { $newIds[] = (int) $t->term_id; $log[] = "«{$r->post_title}»: دسته‌ی معادل در قطعات پیدا نشد (" . implode(' > ', $alt) . ')'; }
        }
        if ($changed) wp_set_post_terms((int) $r->ID, array_values(array_unique($newIds)), 'nexv_cat');
    }
    if (!$log) $log[] = 'هیچ قطعه‌ای در شاخه‌ی «خرید» پیدا نشد.';
    delete_transient('nexv_categories_cache_v1');
    delete_transient('nexv_products_cache_v2');
    update_option('nexv_cat_fix_v3', 1, false);
    set_transient('nexv_cat_fix_report', $log, DAY_IN_SECONDS);
}, 32);

/* =========================================================================
 * لوگوی سایت (logo.png در پوشه‌ی قالب) — برای صفحه‌های مستقل و آیکونِ تب
 * ========================================================================= */
function nexv_logo_url() { return get_template_directory_uri() . '/logo.png'; }
function nexv_favicon_tags() {
    $u = esc_url(nexv_logo_url());
    return '<link rel="icon" type="image/png" href="' . $u . '"><link rel="apple-touch-icon" href="' . $u . '">';
}
/* اگه در وردپرس «نماد سایت» تنظیم نشده، همین لوگو آیکونِ تب و گوگل می‌شه */
add_action('wp_head', function () { if (!has_site_icon()) echo nexv_favicon_tags(); }, 2);

/* =========================================================================
 * حذفِ نوشته‌ی پیش‌فرضِ «سلام دنیا!» وردپرس (یک‌بار) + ریدایرکتِ ۳۰۱ آدرسش
 * ========================================================================= */
add_action('admin_init', function () {
    if (get_option('nexv_hello_removed') || !current_user_can('manage_options')) return;
    foreach (['hello-world', 'سلام-دنیا', rawurlencode('سلام-دنیا')] as $slug) {
        $p = get_page_by_path($slug, OBJECT, 'post');
        if ($p) wp_delete_post($p->ID, true);
    }
    global $wpdb;
    $ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_title IN ('سلام دنیا!', 'Hello world!')");
    foreach ($ids as $id) wp_delete_post((int) $id, true);
    update_option('nexv_hello_removed', 1, false);
});
add_action('template_redirect', function () {
    $path = urldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
    if (preg_match('#/(سلام-دنیا|hello-world)/?$#u', $path)) { wp_redirect(home_url('/'), 301); exit; }
}, 0);

/* =========================================================================
 * نوار داینامیکِ پایین برای صفحه‌های مستقل (وبلاگ، مقاله، قوانین، درباره ما)
 * همون ظاهرِ نوارِ صفحه‌ی اصلی؛ دکمه‌ها به بخش‌های فروشگاه می‌برن.
 * ========================================================================= */
function nexv_static_dock_parts() {
    static $parts = null;
    if ($parts !== null) return $parts;
    $h = (string) @file_get_contents(get_template_directory() . '/site.html');
    $cut = function ($from, $to, $incTo = false) use ($h) {
        $a = strpos($h, $from); if ($a === false) return '';
        $b = strpos($h, $to, $a + strlen($from)); if ($b === false) return '';
        return substr($h, $a, $b - $a + ($incTo ? strlen($to) : 0));
    };
    $vars  = $cut(":root{\n  /* Liquid Glass", "\n}\n", true) . "\n" . $cut("[data-theme=\"dark\"]{\n  --bg:", "\n}\n", true);
    $dockA = strpos($h, 'DYNAMIC DOCK — نوار پایینِ شناور');
    $dockA = $dockA !== false ? strrpos(substr($h, 0, $dockA), '/*') : false;
    $dockB = $dockA !== false ? strpos($h, 'HERO — طراحیِ جدید', $dockA) : false;
    $dockB = $dockB !== false ? strrpos(substr($h, 0, $dockB), '/*') : false;
    $dockCss = ($dockA !== false && $dockB !== false) ? substr($h, $dockA, $dockB - $dockA) : '';
    $kf = '';
    foreach (['dockJelly', 'bump'] as $k) {
        if (preg_match('/@keyframes ' . $k . '\s*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/', $h, $m)) $kf .= $m[0] . "\n";
    }
    $html = $cut('<div class="nexv-dock" id="nexvDock">', "\n<script>");
    $js   = $cut('(function initNexvDock(){', "\n})();", true);
    $parts = compact('vars', 'dockCss', 'kf', 'html', 'js');
    return $parts;
}
function nexv_static_dock_html($active = '') {
    $home = home_url('/');
    $d = nexv_static_dock_parts();
    ob_start(); ?>
<style>
<?php echo $d['vars']; ?>
<?php echo $d['dockCss']; ?>
<?php echo $d['kf']; ?>
.nexv-dock .search-results{display:none !important;}
body{padding-bottom:calc(110px + env(safe-area-inset-bottom)) !important;}
/* پس‌زمینه‌ی ثابت و متحرکِ صفحه‌ی اصلی؛ باکس‌ها به‌صورت شیشه‌ای روش اسکرول می‌شن */
html, body{background:transparent !important;}
html{background:#f3f1f9 !important;} html[data-theme="dark"]{background:#08080d !important;}
#nexvBg{
  position:fixed; inset:0;
  /* کمی بلندتر از صفحه تا موقعِ جمع‌شدنِ نوار آدرسِ سافاری، لبه‌ی پایین دیده نشه */
  bottom:-120px;
  z-index:-1; pointer-events:none; overflow:hidden; contain:strict;
  background:linear-gradient(165deg, #f5f3fc 0%, #f1f1f8 50%, #f3f1f9 100%);
}
html[data-theme="dark"] #nexvBg{background:linear-gradient(165deg, #0a0912 0%, #08080d 50%, #0a0a13 100%);}
#nexvBg .blob{
  position:absolute; border-radius:50%; will-change:transform;
  background:radial-gradient(circle closest-side, var(--c) 0%, color-mix(in srgb, var(--c) 45%, transparent) 45%, transparent 100%);
  animation:var(--anim) var(--dur) ease-in-out infinite alternate;
}
/* رنگ‌های ملایم و بدون سبز: بنفش، یاسی، آبیِ آسمانیِ کم‌رنگ و صورتیِ پودری */
#nexvBg .b1{--c:rgba(139,112,255,.30); width:78vmax; height:78vmax; left:-22vmax; top:-26vmax; --anim:nexvDance1; --dur:26s;}
#nexvBg .b2{--c:rgba(167,139,250,.22); width:62vmax; height:62vmax; right:-20vmax; top:6vh;    --anim:nexvDance2; --dur:31s;}
#nexvBg .b3{--c:rgba(216,180,254,.26); width:70vmax; height:70vmax; left:18vw;  bottom:-34vmax; --anim:nexvDance3; --dur:37s;}
#nexvBg .b4{--c:rgba(147,180,245,.20); width:54vmax; height:54vmax; left:-16vmax; bottom:4vh;  --anim:nexvDance4; --dur:43s;}
#nexvBg .b5{--c:rgba(244,184,214,.16); width:42vmax; height:42vmax; right:4vw;  top:44vh;       --anim:nexvDance5; --dur:29s;}
html[data-theme="dark"] #nexvBg .b1{--c:rgba(124,100,240,.26);}
html[data-theme="dark"] #nexvBg .b2{--c:rgba(139,112,255,.14);}
html[data-theme="dark"] #nexvBg .b3{--c:rgba(168,120,240,.16);}
html[data-theme="dark"] #nexvBg .b4{--c:rgba(99,130,220,.13);}
html[data-theme="dark"] #nexvBg .b5{--c:rgba(210,120,170,.07);}
/* لایه‌ی نورِ نرم برای عمق و خوانایی متن */
#nexvBg::after{
  content:""; position:absolute; inset:0;
  background:radial-gradient(120% 80% at 50% 0%, rgba(255,255,255,.35), transparent 60%);
}
html[data-theme="dark"] #nexvBg::after{background:radial-gradient(120% 80% at 50% 0%, rgba(255,255,255,.04), transparent 60%);}

@keyframes nexvDance1{
  0%  {transform:translate3d(0,0,0) scale(1);}
  50% {transform:translate3d(14vw, 10vh,0) scale(1.12);}
  100%{transform:translate3d(26vw,-4vh,0) scale(.94);}
}
@keyframes nexvDance2{
  0%  {transform:translate3d(0,0,0) scale(1);}
  50% {transform:translate3d(-18vw,14vh,0) scale(.9);}
  100%{transform:translate3d(-8vw,32vh,0) scale(1.1);}
}
@keyframes nexvDance3{
  0%  {transform:translate3d(0,0,0) scale(1.05);}
  50% {transform:translate3d(-20vw,-16vh,0) scale(.92);}
  100%{transform:translate3d(10vw,-30vh,0) scale(1.14);}
}
@keyframes nexvDance4{
  0%  {transform:translate3d(0,0,0) scale(.95);}
  50% {transform:translate3d(22vw,-20vh,0) scale(1.1);}
  100%{transform:translate3d(36vw,-6vh,0) scale(1);}
}
@keyframes nexvDance5{
  0%  {transform:translate3d(0,0,0) scale(1); opacity:.7;}
  50% {transform:translate3d(-26vw,-22vh,0) scale(1.25); opacity:1;}
  100%{transform:translate3d(-40vw,10vh,0) scale(.9); opacity:.75;}
}
@media (prefers-reduced-motion: reduce){ #nexvBg .blob{animation:none;} }
.body-content, .blog-card, .terms-box, .about-box, .rp-card{
  background:rgba(255,255,255,.62) !important; border:1px solid rgba(255,255,255,.7) !important;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.6), 0 14px 36px rgba(20,10,60,.08) !important;
  -webkit-backdrop-filter:blur(16px) saturate(170%); backdrop-filter:blur(16px) saturate(170%);
}
/* کارت‌های ردیفِ افقی blur نمی‌گیرن (وگرنه یه نوارِ روشن پشتِ ردیف کشیده می‌شه) */
.rp-card{-webkit-backdrop-filter:none !important; backdrop-filter:none !important; background:rgba(255,255,255,.72) !important;}
html[data-theme="dark"] .body-content, html[data-theme="dark"] .blog-card, html[data-theme="dark"] .terms-box,
html[data-theme="dark"] .about-box, html[data-theme="dark"] .rp-card{
  background:rgba(30,30,40,.55) !important; border-color:rgba(255,255,255,.08) !important;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.06), 0 14px 36px rgba(0,0,0,.45) !important;
}
</style>
<div id="nexvBg" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span><span class="blob b3"></span><span class="blob b4"></span><span class="blob b5"></span></div>
<span id="cartCount" style="display:none">0</span>
<?php echo $d['html']; ?>
<script>
/* همون نوارِ صفحه‌ی اصلی؛ اینجا هر گزینه به بخشِ مربوطش در فروشگاه می‌ره */
(function(){
  var HOME = <?php echo wp_json_encode($home); ?>;
  window.HOME_URL = HOME; window.SINGLE_VIEW_ID = 'static';
  window.currentUser = function(){ return null; };
  window.openAuth = window.openAccount = function(){ location.href = HOME + 'my-account/'; };
  window.openDrawer = function(){ location.href = HOME + 'cart/'; };
  window.renderAdvancedFilter = window.lockBodyScroll = window.renderSearchResults = function(){};
  try{ var c = JSON.parse(localStorage.getItem('nexv_cart') || '{}'), n = 0; for(var k in c) n += Number(c[k]) || 0;
       document.getElementById('cartCount').textContent = n; }catch(e){}
  document.addEventListener('keydown', function(e){
    var i = document.getElementById('searchInput');
    if(e.key === 'Enter' && document.activeElement === i && i.value.trim()){
      location.href = HOME + '?nexv_search=' + encodeURIComponent(i.value.trim());
    }
  });
})();
var HOME_URL = window.HOME_URL, SINGLE_VIEW_ID = window.SINGLE_VIEW_ID;
<?php echo $d['js']; ?>
</script>
<?php
    return ob_get_clean();
}

/* =========================================================================
 * محصولاتِ مرتبط با یک مقاله (بر اساسِ کلمه‌های عنوانِ مقاله)
 * ========================================================================= */
function nexv_related_products_for_text($text, $limit = 8) {
    $n = nexv_norm_fa($text);
    $devices = ['آیفون' => 'آیفون', 'iphone' => 'آیفون', 'آیپد' => 'آیپد', 'ipad' => 'آیپد', 'اپلواچ' => 'اپلواچ', 'applewatch' => 'اپلواچ', 'ایرپاد' => 'ایرپاد', 'مکبوک' => 'مکبوک'];
    $parts = ['باتری', 'السیدی', 'مادربرد', 'دوربین', 'بدنه', 'تاچ', 'اسپیکر', 'شارژر', 'کابل', 'قاب', 'گلس', 'فلت'];
    $dev = ''; foreach ($devices as $k => $v) { if (mb_strpos($n, $k) !== false) { $dev = $v; break; } }
    $foundParts = array_values(array_filter($parts, function ($p) use ($n) { return mb_strpos($n, $p) !== false; }));
    preg_match_all('/\d+/', nexv_norm_fa($text), $m);
    $nums = array_unique($m[0]);
    $products = get_posts(['post_type' => 'nexv_product', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids']);
    $scored = [];
    foreach ($products as $pid) {
        $t = nexv_norm_fa(get_the_title($pid));
        $s = 0;
        if ($dev !== '' && mb_strpos($t, $dev) !== false) $s += 3;
        foreach ($foundParts as $p) if (mb_strpos($t, $p) !== false) $s += 4;
        foreach ($nums as $num) if (preg_match('/(?<!\d)' . $num . '(?!\d)/', $t)) $s += 2;
        if ($s >= 3) $scored[$pid] = $s + (has_post_thumbnail($pid) ? 0.5 : 0);
    }
    arsort($scored);
    return array_slice(array_keys($scored), 0, $limit);
}
function nexv_related_products_html($ids, $title = 'محصولات مرتبط') {
    if (!$ids) return '';
    ob_start(); ?>
<style>
.rp-wrap{max-width:960px; margin:34px auto 0;}
.rp-wrap h2{font-size:19px; margin:0 4px 14px; font-weight:800;}
.rp-track{display:flex; gap:14px; overflow-x:auto; scroll-snap-type:x mandatory; padding:24px 4px 64px; margin:-24px -4px -58px; scrollbar-width:none; -webkit-overflow-scrolling:touch;}
.rp-track::-webkit-scrollbar{display:none;}
.rp-card{flex:0 0 200px; scroll-snap-align:start; background:#fff; border:1px solid rgba(255,255,255,.7); border-radius:22px; padding:14px; text-decoration:none; color:inherit;
  display:flex; flex-direction:column; box-shadow:inset 0 1px 0 rgba(255,255,255,.6), 0 12px 28px rgba(20,10,60,.08); transition:transform .3s ease;}
.rp-card:hover{transform:translateY(-4px);}
.rp-img{width:100%; aspect-ratio:1/1; border-radius:14px; overflow:hidden; display:grid; place-items:center; margin-bottom:12px;
  background:linear-gradient(160deg,#6a4dff 0%, rgba(106,77,255,.25) 120%);}
.rp-img img{width:100%; height:100%; object-fit:cover; display:block;}
.rp-img.has{background:#f1f0f6;}
.rp-img svg{width:46px; height:46px; stroke:#fff;}
.rp-card h3{font-size:14px; line-height:1.6; margin:0 0 8px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; min-height:3.2em;}
.rp-price{margin-top:auto; font-weight:800; font-size:14.5px;}
.rp-price small{font-size:11px; color:#7a7788; font-weight:400; margin-right:3px;}
.rp-price .call{font-size:12px; color:#6a4dff;}
html[data-theme="dark"] .rp-card{background:#15141c; border-color:rgba(255,255,255,.08); box-shadow:0 12px 28px rgba(0,0,0,.45);}
html[data-theme="dark"] .rp-img.has{background:#1f1e29;}
</style>
<section class="rp-wrap">
  <h2><?php echo esc_html($title); ?></h2>
  <div class="rp-track">
  <?php foreach ($ids as $pid):
      $img = get_the_post_thumbnail_url($pid, 'medium');
      if (!$img && function_exists('nexv_gallery_urls')) { $g = nexv_gallery_urls($pid); $img = $g[0] ?? ''; }
      $price = (int) get_post_meta($pid, '_nexv_price', true);
      $vp = function_exists('nexv_variant_prices') ? array_values(nexv_variant_prices($pid)) : [];
      $all = array_filter(array_merge($vp, $price > 0 ? [$price] : []));
      $min = $all ? min($all) : 0;
      $varies = $all && max($all) !== $min;
  ?>
    <a class="rp-card" href="<?php echo esc_url(get_permalink($pid)); ?>">
      <div class="rp-img<?php echo $img ? ' has' : ''; ?>"><?php if ($img): ?><img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr(get_the_title($pid)); ?>" loading="lazy"><?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/></svg><?php endif; ?></div>
      <h3><?php echo esc_html(get_the_title($pid)); ?></h3>
      <div class="rp-price"><?php if ($min): echo ($varies ? 'از ' : '') . esc_html(number_format($min)) . '<small>تومان</small>'; else: ?><span class="call">برای دریافت قیمت تماس بگیرید</span><?php endif; ?></div>
    </a>
  <?php endforeach; ?>
  </div>
</section>
<?php
    return ob_get_clean();
}

/* =========================================================================
 * پیوند یکتای انگلیسیِ محصولات و مقالات از روی بخشِ انگلیسیِ عنوان
 * عنوان‌ها به شکلِ «فارسی | English» هستن؛ بخشِ بعد از «|» دقیق‌ترین منبع برای
 * پیوندِ انگلیسیه (مثلاً «دوربین جلو آیفون ۵ | iPhone 5 Front Camera» → iphone-5-front-camera).
 * اگه عنوان بخشِ انگلیسی نداشت، از جدولِ ترجمه‌ی فارسی-به-انگلیسی استفاده می‌شه.
 * ========================================================================= */
function nexv_slug_from_title($title) {
    $title = (string) $title;
    if (strpos($title, '|') !== false) {
        $en = trim(substr($title, strrpos($title, '|') + 1));
        if (preg_match('/[A-Za-z]/', $en) && !preg_match('/[^\x00-\x7f]/', $en)) {
            $en = str_replace('.', '-', $en);
            $en = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $en));
            $en = trim(preg_replace('/-+/', '-', $en), '-');
            if ($en !== '') return $en;
        }
    }
    return nexv_transliterate_title_to_slug($title);
}

/* اصلاحِ یک‌باره‌ی پیوندِ همه‌ی محصولات (به درخواستِ مدیر سایت). پیوندِ قدیمی در
   _wp_old_slug ذخیره می‌شه تا با ۳۰۱ به پیوندِ جدید بره و اعتبارِ گوگل حفظ بشه. */
add_action('admin_init', function () {
    if (get_option('nexv_product_slugs_en_v2') || !current_user_can('manage_options')) return;
    @set_time_limit(0);
    global $wpdb;
    $rows = $wpdb->get_results("SELECT ID, post_title, post_name, post_status FROM {$wpdb->posts} WHERE post_type = 'nexv_product' AND post_status IN ('publish','draft','pending','private','future') ORDER BY ID ASC");
    $changed = 0; $same = 0; $examples = [];
    foreach ($rows as $r) {
        $want = nexv_slug_from_title($r->post_title);
        if ($want === '' ) continue;
        $cur = urldecode($r->post_name);
        if ($cur === $want) { $same++; continue; }
        $new = wp_unique_post_slug($want, (int) $r->ID, 'publish', 'nexv_product', 0);
        if ($new === $cur) { $same++; continue; }
        $wpdb->update($wpdb->posts, ['post_name' => $new], ['ID' => (int) $r->ID]);
        if ($r->post_name !== '' && $r->post_status === 'publish') add_post_meta((int) $r->ID, '_wp_old_slug', $r->post_name);
        clean_post_cache((int) $r->ID);
        $changed++;
        if (count($examples) < 6) $examples[] = $cur . ' → ' . $new;
    }
    delete_transient('nexv_products_cache_v2');
    flush_rewrite_rules();
    update_option('nexv_product_slugs_en_v2', 1, false);
    set_transient('nexv_slug_fix_report', ['changed' => $changed, 'same' => $same, 'examples' => $examples], DAY_IN_SECONDS);
});
add_action('admin_notices', function () {
    $r = get_transient('nexv_slug_fix_report');
    if (!$r || !current_user_can('manage_options')) return;
    delete_transient('nexv_slug_fix_report');
    echo '<div class="notice notice-success is-dismissible"><p><strong>NEXV — پیوند یکتای محصولات:</strong> ' . (int) $r['changed'] . ' محصول اصلاح شد، ' . (int) $r['same'] . ' محصول از قبل درست بود. لینک‌های قدیمی با ۳۰۱ به لینکِ جدید می‌رن.</p>';
    if ($r['examples']) { echo '<ul style="direction:ltr;text-align:left;list-style:disc;padding-left:20px">'; foreach ($r['examples'] as $e) echo '<li><code>' . esc_html($e) . '</code></li>'; echo '</ul>'; }
    echo '</div>';
});

/* ریدایرکتِ ۳۰۱ لینک‌های قدیمیِ محصول (پشتیبانِ ریدایرکتِ خودِ وردپرس) */
add_action('template_redirect', function () {
    if (!is_404()) return;
    $path = urldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
    if (!preg_match('#/product/([^/]+)/?$#u', $path, $m)) return;
    global $wpdb;
    $old = $m[1];
    $id = $wpdb->get_var($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_wp_old_slug' AND (m.meta_value = %s OR m.meta_value = %s) AND p.post_type = 'nexv_product' AND p.post_status = 'publish' LIMIT 1",
        $old, strtolower(rawurlencode($old))
    ));
    if ($id) { wp_redirect(get_permalink((int) $id), 301); exit; }
}, 2);

/* =========================================================================
 * سئو: اصلاحِ گزارش‌های Search Console
 * ========================================================================= */
/* ۱) http → https (گوگل http://nexv.ir/ رو به‌عنوان صفحه‌ی تکراری دیده بود) */
add_action('template_redirect', function () {
    if (is_ssl() || strpos(home_url('/'), 'https://') !== 0 || (defined('WP_CLI') && WP_CLI)) return;
    /* پشتِ CDN (آروان، کلودفلر و ...) اتصالِ کاربر https هست حتی اگه سرور http ببینه؛ حلقه نسازه */
    $xf = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') . (string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') . (string) ($_SERVER['HTTP_CF_VISITOR'] ?? ''));
    if (strpos($xf, 'https') !== false || strpos($xf, 'on') !== false) return;
    wp_redirect('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], 301);
    exit;
}, -10);

/* ۲) جستجوی پیش‌فرضِ وردپرس (?s=...) در این قالب صفحه‌ی جدایی نداره؛ آدرسِ
   جستجوی اسکیمای Rank Math به جستجوی خودِ فروشگاه اشاره کنه و صفحه‌های جستجو ایندکس نشن. */
add_filter('rank_math/json_ld/search_url', function () { return home_url('/?nexv_search={search_term_string}'); });
add_action('template_redirect', function () {
    if (isset($_GET['s']) && !is_admin()) {
        $q = trim(wp_unslash((string) $_GET['s']));
        wp_redirect(($q === '' || strpos($q, '{') !== false) ? home_url('/') : add_query_arg('nexv_search', rawurlencode($q), home_url('/')), 301);
        exit;
    }
}, 1);
add_filter('rank_math/frontend/robots', function ($r) {
    if (isset($_GET['nexv_search']) || isset($_GET['nexv_tag'])) { $r['index'] = 'noindex'; $r['follow'] = 'follow'; }
    return $r;
}, 99);

/* ۳) مقاله‌هایی که قبلاً آدرسِ فارسی داشتن: اگه مقاله‌ای با همون عنوان و آدرسِ انگلیسی هست، ۳۰۱ به اون */
add_action('template_redirect', function () {
    $path = urldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
    if (!preg_match('#/article/([^/]+)/?$#u', $path, $m) || !preg_match('/[^\x00-\x7f]/', $m[1])) return;
    $needle = nexv_norm_fa(str_replace('-', ' ', $m[1]));
    foreach (get_posts(['post_type' => 'nexv_article', 'post_status' => 'publish', 'numberposts' => -1]) as $a) {
        if (nexv_slug_is_english(urldecode($a->post_name)) && nexv_norm_fa($a->post_title) === $needle) {
            wp_redirect(get_permalink($a), 301); exit;
        }
    }
    if (is_404()) { wp_redirect(get_post_type_archive_link('nexv_article') ?: home_url('/'), 301); exit; }
}, 3);

/* ۴) گزارشِ محصولاتِ تکراری (دو محصول با عنوانِ یکسان → آدرس‌های ...-2) در پیشخوان */
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) return;
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || !in_array($screen->id, ['dashboard', 'edit-nexv_product', 'toplevel_page_nexv-dashboard'], true)) return;
    global $wpdb;
    $rows = $wpdb->get_results("SELECT post_title, GROUP_CONCAT(ID ORDER BY ID) ids, COUNT(*) c FROM {$wpdb->posts} WHERE post_type = 'nexv_product' AND post_status IN ('publish','draft','private','pending') GROUP BY post_title HAVING c > 1 LIMIT 50");
    if (!$rows) return;
    echo '<div class="notice notice-warning"><p><strong>NEXV — محصولاتِ تکراری (' . count($rows) . ' عنوان):</strong> این محصولات دو بار در سایت هستن و آدرسِ یکی‌شون با «-2» تموم می‌شه. نسخه‌ی اضافه رو (معمولاً شماره‌ی بزرگ‌تر) حذف کنید.</p><ul style="list-style:disc;padding-right:20px">';
    foreach ($rows as $r) {
        $links = array_map(function ($id) { return '<a href="' . esc_url(get_edit_post_link((int) $id)) . '">#' . (int) $id . '</a>'; }, explode(',', $r->ids));
        echo '<li>' . esc_html($r->post_title) . ' — ' . implode('، ', $links) . '</li>';
    }
    echo '</ul></div>';
});

/* =========================================================================
 * آدرس‌های فارسیِ قدیمیِ سایتِ قبلی: اگه بعد از همه‌ی ریدایرکت‌ها (دسته، مقاله، محصول)
 * هنوز صفحه‌ای پیدا نشد، به‌جای 404 کدِ 410 (Gone) برمی‌گرده تا گوگل سریع‌تر از ایندکس
 * حذفشون کنه. آدرس‌های انگلیسی همون 404ِ عادی رو می‌گیرن.
 * ========================================================================= */
add_action('template_redirect', function () {
    if (!is_404()) return;
    $path = urldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
    if (!preg_match('/[^\x00-\x7f]/', $path)) return;
    status_header(410);
    nocache_headers();
    header('X-Robots-Tag: noindex');
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="robots" content="noindex"><meta name="viewport" content="width=device-width,initial-scale=1"><title>این صفحه حذف شده | NEXV</title></head>'
       . '<body style="font-family:Vazirmatn,Tahoma,sans-serif;text-align:center;padding:80px 16px;background:#f3f1f9;color:#201f2b">'
       . '<h1 style="font-size:20px">این صفحه دیگه وجود نداره</h1><p style="color:#7a7788">آدرسی که باز کردید مربوط به نسخه‌ی قبلیِ سایته.</p>'
       . '<a href="' . esc_url(home_url('/')) . '" style="display:inline-block;margin-top:14px;padding:10px 22px;border-radius:30px;background:#6a4dff;color:#fff;text-decoration:none">رفتن به فروشگاه NEXV</a></body></html>';
    exit;
}, 50);

/* =========================================================================
 * سایت‌مپِ برگه‌ها: سبد/تسویه/حساب/فروشگاه noindex و بیرون از سایت‌مپ؛ «برگه نمونه» حذف
 * (Rank Math هر برگه‌ای که noindex باشه رو خودش از سایت‌مپ برمی‌داره)
 * ========================================================================= */
add_action('admin_init', function () {
    if (get_option('nexv_pages_cleanup_v1') || !current_user_can('manage_options')) return;
    foreach (nexv_utility_page_slugs() as $slug) {
        $p = get_page_by_path($slug, OBJECT, 'page');
        if ($p) update_post_meta($p->ID, 'rank_math_robots', ['noindex', 'follow']);
    }
    global $wpdb;
    $ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND (post_name IN ('sample-page', '" . esc_sql(rawurlencode('برگه-نمونه')) . "', '" . esc_sql(strtolower(rawurlencode('برگه-نمونه'))) . "', 'برگه-نمونه') OR post_title IN ('برگه نمونه', 'Sample Page'))");
    foreach ($ids as $id) wp_delete_post((int) $id, true);
    update_option('nexv_pages_cleanup_v1', 1, false);
});
add_filter('rank_math/sitemap/exclude_post', function ($exclude, $post) {
    if ($post && isset($post->post_type) && $post->post_type === 'page' && in_array(urldecode($post->post_name), ['برگه-نمونه', 'sample-page'], true)) return true;
    return $exclude;
}, 20, 2);

/* سایت‌مپِ برگه‌ها (نسخه‌ی ۲): شناسه‌ی برگه‌های کمکی به تنظیمِ «Exclude Posts» خودِ Rank Math
   اضافه می‌شه (همون فیلدی که در تنظیمات نقشه سایت ← عمومی هست)، برگه‌ی نمونه حذف می‌شه
   و کشِ سایت‌مپِ Rank Math پاک می‌شه تا تغییر همون لحظه دیده بشه. */
function nexv_flush_rank_math_sitemap() {
    if (class_exists('\RankMath\Sitemap\Cache')) { try { \RankMath\Sitemap\Cache::invalidate_storage(); } catch (\Throwable $e) {} }
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_rank\_math\_sitemap%' OR option_name LIKE '\_transient\_timeout\_rank\_math\_sitemap%' OR option_name LIKE '%rank_math%sitemap%cache%'");
}
add_action('admin_init', function () {
    if (get_option('nexv_pages_cleanup_v2') || !current_user_can('manage_options')) return;
    global $wpdb;
    $samples = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND (post_title IN ('برگه نمونه', 'Sample Page') OR post_name = 'sample-page' OR post_name LIKE '%25d8%25a8%25d8%25b1%25da%25af%25d9%2587-%' OR post_name LIKE '%D8%A8%D8%B1%DA%AF%D9%87-%')");
    foreach ($samples as $id) wp_delete_post((int) $id, true);
    $ids = [];
    foreach (nexv_utility_page_slugs() as $slug) {
        $p = get_page_by_path($slug, OBJECT, 'page');
        if ($p) { $ids[] = (int) $p->ID; update_post_meta($p->ID, 'rank_math_robots', ['noindex', 'follow']); }
    }
    $opt = get_option('rank-math-options-sitemap', []);
    if (is_array($opt) && $ids) {
        $cur = array_filter(array_map('intval', preg_split('/\s*,\s*/', (string) ($opt['exclude_posts'] ?? ''))));
        $opt['exclude_posts'] = implode(',', array_values(array_unique(array_merge($cur, $ids))));
        update_option('rank-math-options-sitemap', $opt);
    }
    nexv_flush_rank_math_sitemap();
    update_option('nexv_pages_cleanup_v2', 1, false);
}, 40);

/* بازگردانی (به درخواستِ مدیر سایت): سبد خرید، فروشگاه، تسویه حساب و حساب کاربری
   دوباره قابلِ ایندکس و داخلِ سایت‌مپ هستن؛ فقط «برگه نمونه» حذف شده می‌مونه. */
add_action('admin_init', function () {
    if (get_option('nexv_pages_restore_v1') || !current_user_can('manage_options')) return;
    $ids = [];
    foreach (nexv_utility_page_slugs() as $slug) {
        $p = get_page_by_path($slug, OBJECT, 'page');
        if ($p) {
            $ids[] = (int) $p->ID;
            delete_post_meta($p->ID, 'rank_math_robots');
            if ($p->post_status !== 'publish') wp_update_post(['ID' => $p->ID, 'post_status' => 'publish']);
        }
    }
    $opt = get_option('rank-math-options-sitemap', []);
    if (is_array($opt) && isset($opt['exclude_posts'])) {
        $cur = array_filter(array_map('intval', preg_split('/\\s*,\\s*/', (string) $opt['exclude_posts'])));
        $opt['exclude_posts'] = implode(',', array_values(array_diff($cur, $ids)));
        update_option('rank-math-options-sitemap', $opt);
    }
    nexv_flush_rank_math_sitemap();
    update_option('nexv_pages_restore_v1', 1, false);
}, 50);

/* =========================================================================
 * «تسویه حساب» همون «سبد خرید»ه و «فروشگاه» همون صفحه‌ی اصلی (به درخواستِ مدیر سایت):
 * برگه‌هاشون حذف و آدرس‌هاشون ۳۰۱ می‌شن: /checkout/ → /cart/ ، /shop/ → صفحه‌ی اصلی
 * ========================================================================= */
add_action('template_redirect', function () {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $base = trim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');
    if ($base !== '' && strpos($path, $base) === 0) $path = trim(substr($path, strlen($base)), '/');
    if ($path === 'checkout') { wp_redirect(home_url('/cart/'), 301); exit; }
    if ($path === 'shop')     { wp_redirect(home_url('/'), 301); exit; }
}, 0);
add_action('admin_init', function () {
    if (get_option('nexv_pages_merge_v1') || !current_user_can('manage_options')) return;
    foreach (['checkout', 'shop'] as $slug) {
        $p = get_page_by_path($slug, OBJECT, 'page');
        if ($p) wp_delete_post($p->ID, true);
    }
    nexv_flush_rank_math_sitemap();
    update_option('nexv_pages_merge_v1', 1, false);
}, 60);

/* عکسِ کارتِ محصول در اندازه‌ی medium_large (برای لیست‌ها؛ صفحه‌ی محصول همون عکسِ بزرگ رو داره) */
function nexv_card_thumb_url($post_id) {
    $raw = trim((string) get_post_meta($post_id, '_nexv_gallery', true));
    if ($raw !== '') {
        $first = rawurldecode(trim(explode(',', $raw)[0]));
        if (ctype_digit($first)) return wp_get_attachment_image_url((int) $first, 'medium_large') ?: '';
        return '';
    }
    $tid = get_post_thumbnail_id($post_id);
    return $tid ? (wp_get_attachment_image_url($tid, 'medium_large') ?: '') : '';
}

/* =========================================================================
 * سفارش‌ها در پیشخوانِ وردپرس (NEXV ← سفارش‌ها) + اطلاع‌رسانیِ سفارشِ جدید
 * این صفحه مستقیم از همون فایلِ سفارش‌های سایت می‌خونه؛ یعنی حتی اگه پنلِ داخلِ
 * سایت به هر دلیلی باز نشه، مدیر همیشه سفارش‌ها رو اینجا می‌بینه.
 * ========================================================================= */
function nexv_orders_db_path() {
    $u = wp_upload_dir();
    $cands = [];
    if (!empty($u['basedir'])) $cands[] = $u['basedir'] . '/nexv-data/db.json';
    $cands[] = WP_CONTENT_DIR . '/uploads/nexv-data/db.json';
    $cands[] = get_template_directory() . '/data/db.json';
    foreach ($cands as $c) { if (file_exists($c)) return $c; }
    return $cands[0];
}
function nexv_orders_read() {
    $f = nexv_orders_db_path();
    $d = file_exists($f) ? json_decode((string) file_get_contents($f), true) : null;
    return is_array($d) ? $d : null;
}
function nexv_order_status_labels() {
    return ['pending' => 'در انتظار بررسی', 'confirmed' => 'تأیید شد', 'rejected' => 'رد شد', 'packing' => 'در حال بسته‌بندی', 'courier' => 'تحویل به پیک/پست', 'shipped' => 'ارسال شد'];
}

/* اطلاع‌رسانیِ سفارشِ جدید: ایمیل به مدیر + شمارنده‌ی «سفارشِ جدید» روی منوی NEXV */
function nexv_notify_new_order($order, $detached = true) {
    update_option('nexv_unseen_orders', (int) get_option('nexv_unseen_orders', 0) + 1, false);
    /* اعلان روی گوشیِ مدیر (اگه دستگاهی ثبت شده باشه) */
    if (function_exists('nexv_push_order_notify')) { try { nexv_push_order_notify($order); } catch (\Throwable $e) {} }
    $to = get_option('admin_email');
    /* ایمیل فقط وقتی فرستاده می‌شه که جوابِ مشتری از قبل کامل فرستاده شده باشه (معطلش نکنه) */
    if (!$detached || !$to || strpos($to, '@nexv.local') !== false) return;
    $lines = [];
    foreach ((array) ($order['items'] ?? []) as $it) $lines[] = '- ' . ($it['title'] ?? '') . ' × ' . (int) ($it['qty'] ?? 1) . ' — ' . number_format((int) ($it['price'] ?? 0)) . ' تومان';
    $body = "سفارش جدید #" . $order['id'] . "\n\nمشتری: " . ($order['customerName'] ?? '') . "\nتلفن: " . ($order['phone'] ?? '')
          . "\n\n" . implode("\n", $lines) . "\n\nجمع کل: " . number_format((int) ($order['grandTotal'] ?? 0)) . " تومان\n\nرسید: " . ($order['receiptUrl'] ?? '')
          . "\n\nمشاهده در پیشخوان: " . admin_url('admin.php?page=nexv-orders');
    @wp_mail($to, 'NEXV — سفارش جدید #' . $order['id'], $body);
}

add_action('admin_post_nexv_order_status', function () {
    if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز.');
    check_admin_referer('nexv_order_status');
    $id = (int) ($_POST['order_id'] ?? 0);
    $st = sanitize_key($_POST['status'] ?? '');
    if (!isset(nexv_order_status_labels()[$st])) wp_die('وضعیت نامعتبر.');
    $f = nexv_orders_db_path();
    $fp = fopen($f, 'c+');
    if ($fp && flock($fp, LOCK_EX)) {
        $d = json_decode(stream_get_contents($fp), true);
        if (is_array($d)) {
            $changed = null;
            foreach ($d['orders'] as &$o) {
                if ((int) $o['id'] === $id) {
                    $o['status'] = $st;
                    $o['statusHistory'] = array_merge((array) ($o['statusHistory'] ?? []), [['status' => $st, 'at' => date('c')]]);
                    $changed = $o; break;
                }
            }
            unset($o);
            if ($changed) { nexv_archive_order_wp($changed, 'status:' . $st); nexv_mirror_order_to_wp($changed); }
            $json = json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            ftruncate($fp, 0); rewind($fp); fwrite($fp, $json); fflush($fp);
            @file_put_contents(dirname($f) . '/backups/latest.json', $json);
        }
        flock($fp, LOCK_UN);
    }
    if ($fp) fclose($fp);
    wp_redirect(admin_url('admin.php?page=nexv-orders&updated=' . $id . '#order-' . $id));
    exit;
});


/* =========================================================================
 * سفارش‌ها — منوی جداگانه در پیشخوان، سه نسخه‌ی مستقل، بکاپ و خروجی
 * ۱) فایلِ اصلیِ سایت (db.json)  ۲) آرشیوِ دائمیِ هر سفارش (orders-archive/)
 * ۳) دیتابیسِ وردپرس (نوع‌پستِ مخفیِ nexv_order، با همون تاریخِ ثبت)
 * لیستِ این صفحه از هر سه منبع ساخته می‌شه؛ اگه یکی آسیب ببینه، بقیه سفارش رو نگه داشتن.
 * ========================================================================= */
add_action('init', function () {
    register_post_type('nexv_order', ['public' => false, 'show_ui' => false, 'exclude_from_search' => true, 'publicly_queryable' => false, 'rewrite' => false, 'query_var' => false, 'label' => 'سفارش‌ها']);
});
function nexv_orders_archive_dir() { return dirname(nexv_orders_db_path()) . '/orders-archive'; }
function nexv_order_key($o) { return (int) ($o['id'] ?? 0) . '|' . (string) ($o['created_at'] ?? ''); }
function nexv_archive_order_wp($order, $event = 'save') {
    $dir = nexv_orders_archive_dir();
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $file = $dir . '/order-' . (int) $order['id'] . '.json';
    if (file_exists($file)) {
        $prev = json_decode((string) @file_get_contents($file), true);
        if (is_array($prev) && !empty($prev['created_at']) && !empty($order['created_at']) && $prev['created_at'] !== $order['created_at']) {
            $file = $dir . '/order-' . (int) $order['id'] . '-' . preg_replace('/\D/', '', $order['created_at']) . '.json';
        }
    }
    $json = wp_json_encode($order, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (@file_put_contents($file . '.tmp', $json, LOCK_EX) !== false) @rename($file . '.tmp', $file);
    @file_put_contents($dir . '/orders-log.jsonl', wp_json_encode(['event' => $event, 'at' => date('c'), 'order' => $order], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}
function nexv_mirror_order_to_wp($order) {
    if (empty($order['id'])) return;
    $key = nexv_order_key($order);
    $found = get_posts(['post_type' => 'nexv_order', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_nexv_order_key', 'meta_value' => $key]);
    $ts = strtotime((string) ($order['created_at'] ?? '')) ?: time();
    $arr = [
        'post_type' => 'nexv_order', 'post_status' => 'private',
        'post_title' => 'سفارش #' . (int) $order['id'] . ' — ' . ($order['customerName'] ?? ''),
        'post_content' => wp_slash(wp_json_encode($order, JSON_UNESCAPED_UNICODE)),
        'post_date' => wp_date('Y-m-d H:i:s', $ts), 'post_date_gmt' => gmdate('Y-m-d H:i:s', $ts),
    ];
    if ($found) { $arr['ID'] = $found[0]; wp_update_post($arr); $pid = $found[0]; }
    else { $pid = wp_insert_post($arr); if ($pid && !is_wp_error($pid)) update_post_meta($pid, '_nexv_order_key', $key); }
    if (!empty($pid) && !is_wp_error($pid)) { update_post_meta($pid, '_nexv_order_id', (int) $order['id']); update_post_meta($pid, '_nexv_order_status', (string) ($order['status'] ?? '')); }
}
/* همه‌ی سفارش‌ها از هر سه منبع، بدون تکرار؛ جدیدترین اول */
function nexv_all_orders() {
    $by = [];
    $add = function ($o, $src) use (&$by) {
        if (!is_array($o) || empty($o['id'])) return;
        $k = nexv_order_key($o);
        if (!isset($by[$k])) { $by[$k] = $o; $by[$k]['_src'] = [$src]; return; }
        $by[$k]['_src'][] = $src;
        /* اگه یکی از منابع وضعیتِ جدیدتری داره (تاریخچه‌ی بلندتر)، همون نشون داده می‌شه */
        if (count((array) ($o['statusHistory'] ?? [])) > count((array) ($by[$k]['statusHistory'] ?? []))) { $src0 = $by[$k]['_src']; $by[$k] = $o; $by[$k]['_src'] = $src0; }
    };
    $d = nexv_orders_read();
    foreach ((array) ($d['orders'] ?? []) as $o) $add($o, 'db');
    foreach ((array) glob(nexv_orders_archive_dir() . '/order-*.json') as $f) $add(json_decode((string) @file_get_contents($f), true), 'archive');
    foreach (get_posts(['post_type' => 'nexv_order', 'post_status' => 'any', 'numberposts' => -1]) as $p) $add(json_decode($p->post_content, true), 'wp');
    $all = array_values($by);
    usort($all, function ($a, $b) { return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')) ?: ((int) $b['id'] <=> (int) $a['id']); });
    return $all;
}
/* یک‌بار: همه‌ی سفارش‌های فعلی آرشیو و در دیتابیسِ وردپرس هم ثبت می‌شن */
add_action('admin_init', function () {
    if (get_option('nexv_orders_mirror_v1') || !current_user_can('manage_options')) return;
    $d = nexv_orders_read();
    foreach ((array) ($d['orders'] ?? []) as $o) {
        if (!file_exists(nexv_orders_archive_dir() . '/order-' . (int) $o['id'] . '.json')) nexv_archive_order_wp($o, 'backfill');
        nexv_mirror_order_to_wp($o);
    }
    update_option('nexv_orders_mirror_v1', 1, false);
});

/* منوی مستقلِ «سفارش‌ها» در پیشخوان */
add_action('admin_menu', function () {
    $n = (int) get_option('nexv_unseen_orders', 0);
    $badge = $n > 0 ? ' <span class="awaiting-mod">' . $n . '</span>' : '';
    add_menu_page('سفارش‌ها', 'سفارش‌ها' . $badge, 'manage_options', 'nexv-orders', 'nexv_orders_page_html', 'dashicons-cart', 2);
});

function nexv_jalali_dt($iso) {
    $ts = strtotime((string) $iso);
    if (!$ts) return '';
    return (function_exists('nexv_jalali_pretty') ? nexv_jalali_pretty($ts) : date_i18n('Y/m/d', $ts)) . ' — ساعت ' . wp_date('H:i', $ts);
}
function nexv_backup_dir() { $d = dirname(nexv_orders_db_path()) . '/orders-archive/backups'; if (!is_dir($d)) @mkdir($d, 0775, true); return $d; }

/* ---------- بکاپ و خروجی‌ها ---------- */
add_action('admin_post_nexv_orders_backup', function () {
    if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز.');
    check_admin_referer('nexv_orders_backup');
    $f = nexv_backup_dir() . '/orders-' . wp_date('Y-m-d_H-i-s') . '.json';
    $ok = @file_put_contents($f, wp_json_encode(array_map(function ($o) { unset($o['_src']); return $o; }, nexv_all_orders()), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
    wp_redirect(admin_url('admin.php?page=nexv-orders&backup=' . ($ok ? 'ok' : 'fail')));
    exit;
});
add_action('admin_post_nexv_orders_download', function () {
    if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز.');
    check_admin_referer('nexv_orders_download');
    $fmt = sanitize_key($_GET['fmt'] ?? 'json');
    $orders = array_map(function ($o) { unset($o['_src']); return $o; }, nexv_all_orders());
    $stamp = wp_date('Y-m-d_H-i');
    nocache_headers();
    if ($fmt === 'file') {   // دانلودِ یکی از بکاپ‌های ذخیره‌شده
        $name = basename((string) ($_GET['name'] ?? ''));
        $path = nexv_backup_dir() . '/' . $name;
        if (!preg_match('/^orders-[\d_-]+\.json$/', $name) || !file_exists($path)) wp_die('فایل پیدا نشد.');
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        readfile($path); exit;
    }
    if ($fmt === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="nexv-orders-' . $stamp . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['شماره', 'تاریخ ثبت', 'مشتری', 'تلفن', 'استان/شهر', 'آدرس', 'کدپستی', 'اقلام', 'جمع اقلام', 'هزینه ارسال', 'روش ارسال', 'جمع کل', 'وضعیت', 'رسید']);
        $labels = nexv_order_status_labels();
        foreach ($orders as $o) {
            $a = (array) ($o['address'] ?? []);
            $items = implode(' | ', array_map(function ($it) { return ($it['title'] ?? '') . ' × ' . (int) ($it['qty'] ?? 1) . ' (' . (int) ($it['price'] ?? 0) . ')'; }, (array) ($o['items'] ?? [])));
            fputcsv($out, [(int) $o['id'], nexv_jalali_dt($o['created_at'] ?? ''), $o['customerName'] ?? '', $o['phone'] ?? '',
                trim(($a['province'] ?? '') . ' / ' . ($a['city'] ?? ''), ' /'), trim(($a['district'] ?? '') . ' ' . ($a['fullAddress'] ?? '') . ' پلاک ' . ($a['plaque'] ?? '') . ' واحد ' . ($a['unit'] ?? '')),
                $a['postalCode'] ?? '', $items, (int) ($o['itemsTotal'] ?? 0), (int) ($o['shippingFee'] ?? 0), $o['shippingMethod'] ?? '', (int) ($o['grandTotal'] ?? 0),
                $labels[$o['status'] ?? ''] ?? ($o['status'] ?? ''), $o['receiptUrl'] ?? '']);
        }
        fclose($out); exit;
    }
    if ($fmt === 'zip' && class_exists('ZipArchive')) {
        $tmp = wp_tempnam('nexv-orders.zip');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) === true) {
            $zip->addFromString('orders.json', wp_json_encode($orders, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $rdir = dirname(nexv_orders_db_path()) . '/receipts';
            foreach ($orders as $o) {
                $r = basename((string) ($o['receiptUrl'] ?? ''));
                if ($r && file_exists($rdir . '/' . $r)) $zip->addFile($rdir . '/' . $r, 'receipts/' . $r);
            }
            $zip->close();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="nexv-orders-full-' . $stamp . '.zip"');
            header('Content-Length: ' . filesize($tmp));
            readfile($tmp); @unlink($tmp); exit;
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="nexv-orders-' . $stamp . '.json"');
    echo wp_json_encode($orders, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
});

function nexv_orders_page_html() {
    update_option('nexv_unseen_orders', 0, false);
    $orders = nexv_all_orders();
    $labels = nexv_order_status_labels();
    $api = get_template_directory_uri() . '/api.php';
    $filter = sanitize_key($_GET['st'] ?? '');
    $q = trim((string) wp_unslash($_GET['q'] ?? ''));
    $counts = array_count_values(array_map(function ($o) { return (string) ($o['status'] ?? 'pending'); }, $orders));
    $shown = array_filter($orders, function ($o) use ($filter, $q) {
        if ($filter && ($o['status'] ?? '') !== $filter) return false;
        if ($q !== '' && strpos((string) ($o['phone'] ?? '') . ' ' . ($o['customerName'] ?? '') . ' #' . $o['id'], $q) === false) return false;
        return true;
    });
    $dl = function ($fmt) { return wp_nonce_url(admin_url('admin-post.php?action=nexv_orders_download&fmt=' . $fmt), 'nexv_orders_download'); };
    $backups = array_reverse((array) glob(nexv_backup_dir() . '/orders-*.json'));
    $total = 0; foreach ($orders as $o) if (($o['status'] ?? '') !== 'rejected') $total += (int) ($o['grandTotal'] ?? 0);
    ?>
    <div class="wrap nx-wrap">
        <h1>سفارش‌ها</h1>
        <?php if (isset($_GET['updated'])): ?><div class="notice notice-success is-dismissible"><p>وضعیت سفارش #<?php echo (int) $_GET['updated']; ?> ذخیره شد.</p></div><?php endif; ?>
        <?php if (($_GET['backup'] ?? '') === 'ok'): ?><div class="notice notice-success is-dismissible"><p>بکاپِ همه‌ی سفارش‌ها ساخته شد و پایینِ همین صفحه قابل دانلوده.</p></div><?php endif; ?>
        <?php if (($_GET['backup'] ?? '') === 'fail'): ?><div class="notice notice-error"><p>ساختِ بکاپ ممکن نشد (دسترسیِ نوشتن روی هاست).</p></div><?php endif; ?>
        <style>
            .nx-wrap .nx-top{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:12px 0 16px}
            .nx-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:10px 0 18px}
            .nx-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:12px 14px}
            .nx-card b{display:block;font-size:20px;margin-top:4px}
            .nx-safe{background:#edfaef;border:1px solid #b8e6c1;border-radius:10px;padding:10px 14px;margin:6px 0 14px;color:#1d5b2b;font-size:13px;line-height:1.9}
            .nx-orders{display:grid;gap:14px}
            .nx-order{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:14px 16px;display:grid;grid-template-columns:1fr 150px;gap:16px}
            .nx-order h2{margin:0 0 6px;font-size:16px}
            .nx-order .meta{color:#50575e;font-size:13px;line-height:1.9}
            .nx-order table{border-collapse:collapse;margin:8px 0;font-size:13px}
            .nx-order td{padding:3px 8px;border-bottom:1px solid #f0f0f1}
            .nx-order .rc img{width:150px;height:150px;object-fit:cover;border-radius:8px;border:1px solid #ddd}
            .nx-hist{font-size:12px;color:#646970;margin:4px 0 8px}
            .nx-st{display:inline-block;padding:2px 10px;border-radius:20px;font-size:12px;background:#f0f0f1}
            .nx-st.pending{background:#fff4d6;color:#8a5a00}.nx-st.confirmed,.nx-st.shipped{background:#d9f7e6;color:#0a6b3a}.nx-st.rejected{background:#fde2e2;color:#a00}
            .nx-src{font-size:11px;color:#8c8f94}
            @media(max-width:782px){.nx-order{grid-template-columns:1fr}}
        </style>

        <div class="nx-cards">
            <div class="nx-card">همه‌ی سفارش‌ها<b><?php echo count($orders); ?></b></div>
            <div class="nx-card">در انتظار بررسی<b><?php echo (int) ($counts['pending'] ?? 0); ?></b></div>
            <div class="nx-card">ارسال‌شده<b><?php echo (int) ($counts['shipped'] ?? 0); ?></b></div>
            <div class="nx-card">جمع فروش (بدون ردشده‌ها)<b style="font-size:16px"><?php echo esc_html(number_format($total)); ?> تومان</b></div>
        </div>

        <div class="nx-safe">🔒 هر سفارش در <b>سه جای مستقل</b> ذخیره می‌شه (فایل اصلی سایت، آرشیو دائمی، دیتابیس وردپرس) و <b>امکانِ حذف سفارش غیرفعاله</b>. تاریخِ ثبت هیچ‌وقت عوض نمی‌شه و هر تغییرِ وضعیت با تاریخش ثبت می‌شه. هر روز هم یک نسخه‌ی کامل به‌صورت خودکار نگه داشته می‌شه.</div>

        <div class="nx-top">
            <a class="button button-primary" href="<?php echo esc_url($dl('zip')); ?>">⬇ دانلود بکاپ کامل (سفارش‌ها + عکس رسیدها، ZIP)</a>
            <a class="button" href="<?php echo esc_url($dl('csv')); ?>">⬇ دانلود اکسل (CSV)</a>
            <a class="button" href="<?php echo esc_url($dl('json')); ?>">⬇ دانلود JSON</a>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                <input type="hidden" name="action" value="nexv_orders_backup"><?php wp_nonce_field('nexv_orders_backup'); ?>
                <button class="button">💾 ساخت بکاپ روی هاست</button>
            </form>
        </div>

        <form method="get" class="nx-top">
            <input type="hidden" name="page" value="nexv-orders">
            <select name="st"><option value="">همه‌ی وضعیت‌ها</option><?php foreach ($labels as $k => $l): ?><option value="<?php echo esc_attr($k); ?>" <?php selected($filter, $k); ?>><?php echo esc_html($l . ' (' . (int) ($counts[$k] ?? 0) . ')'); ?></option><?php endforeach; ?></select>
            <input type="search" name="q" value="<?php echo esc_attr($q); ?>" placeholder="جستجو: شماره سفارش، نام یا تلفن">
            <button class="button">فیلتر</button>
        </form>

        <div class="nx-orders">
        <?php foreach ($shown as $o):
            $a = (array) ($o['address'] ?? []);
            $st = (string) ($o['status'] ?? 'pending');
        ?>
            <div class="nx-order" id="order-<?php echo (int) $o['id']; ?>">
                <div>
                    <h2>سفارش #<?php echo (int) $o['id']; ?> <span class="nx-st <?php echo esc_attr($st); ?>"><?php echo esc_html($labels[$st] ?? $st); ?></span></h2>
                    <div class="meta">
                        🗓 ثبت: <b><?php echo esc_html(nexv_jalali_dt($o['created_at'] ?? '')); ?></b><br>
                        <b><?php echo esc_html($o['customerName'] ?? ''); ?></b> — <a href="tel:<?php echo esc_attr($o['phone'] ?? ''); ?>" style="direction:ltr;display:inline-block"><?php echo esc_html($o['phone'] ?? ''); ?></a><br>
                        <?php echo esc_html(implode('، ', array_filter([$a['province'] ?? '', $a['city'] ?? '', $a['district'] ?? '', !empty($a['plaque']) ? 'پلاک ' . $a['plaque'] : '', !empty($a['unit']) ? 'واحد ' . $a['unit'] : '']))); ?>
                        <?php if (!empty($a['postalCode'])) echo ' — کدپستی ' . esc_html($a['postalCode']); ?><br><?php echo esc_html($a['fullAddress'] ?? ''); ?>
                    </div>
                    <table>
                        <?php foreach ((array) ($o['items'] ?? []) as $it): ?>
                            <tr><td><?php echo esc_html($it['title'] ?? ''); ?></td><td>× <?php echo (int) ($it['qty'] ?? 1); ?></td><td><?php echo esc_html(number_format((int) ($it['price'] ?? 0))); ?> تومان</td></tr>
                        <?php endforeach; ?>
                        <tr><td>ارسال (<?php echo esc_html($o['shippingMethod'] ?? ''); ?>)</td><td></td><td><?php echo esc_html(number_format((int) ($o['shippingFee'] ?? 0))); ?> تومان</td></tr>
                        <tr><td><b>جمع کل</b></td><td></td><td><b><?php echo esc_html(number_format((int) ($o['grandTotal'] ?? 0))); ?> تومان</b></td></tr>
                    </table>
                    <?php if (!empty($o['statusHistory'])): ?>
                        <div class="nx-hist">تاریخچه: <?php echo esc_html(implode('  ←  ', array_map(function ($h) use ($labels) { return ($labels[$h['status'] ?? ''] ?? ($h['status'] ?? '')) . ' (' . nexv_jalali_dt($h['at'] ?? '') . ')'; }, (array) $o['statusHistory']))); ?></div>
                    <?php endif; ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        <input type="hidden" name="action" value="nexv_order_status"><input type="hidden" name="order_id" value="<?php echo (int) $o['id']; ?>">
                        <?php wp_nonce_field('nexv_order_status'); ?>
                        <select name="status"><?php foreach ($labels as $k => $l): ?><option value="<?php echo esc_attr($k); ?>" <?php selected($st, $k); ?>><?php echo esc_html($l); ?></option><?php endforeach; ?></select>
                        <button class="button button-primary">ذخیره وضعیت</button>
                        <a class="button" target="_blank" href="<?php echo esc_url($api . '?action=invoice&order_id=' . (int) $o['id']); ?>">فاکتور</a>
                        <span class="nx-src">ذخیره‌شده در: <?php echo esc_html(implode('، ', array_map(function ($s) { return ['db' => 'فایل اصلی', 'archive' => 'آرشیو', 'wp' => 'دیتابیس وردپرس'][$s] ?? $s; }, array_unique((array) ($o['_src'] ?? []))))); ?></span>
                    </form>
                </div>
                <div class="rc"><?php if (!empty($o['receiptUrl'])): ?><a href="<?php echo esc_url($o['receiptUrl']); ?>" target="_blank"><img src="<?php echo esc_url($o['receiptUrl']); ?>" alt="رسید" loading="lazy"></a><?php endif; ?></div>
            </div>
        <?php endforeach; ?>
        <?php if (!$shown): ?><p><?php echo $orders ? 'سفارشی با این فیلتر پیدا نشد.' : 'هنوز سفارشی ثبت نشده.'; ?></p><?php endif; ?>
        </div>

        <h2 style="margin-top:28px">بکاپ‌های ذخیره‌شده روی هاست</h2>
        <?php if ($backups): ?>
            <ul><?php foreach (array_slice($backups, 0, 50) as $b): $n = basename($b); ?>
                <li><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=nexv_orders_download&fmt=file&name=' . rawurlencode($n)), 'nexv_orders_download')); ?>"><?php echo esc_html($n); ?></a> — <?php echo esc_html(size_format(filesize($b))); ?></li>
            <?php endforeach; ?></ul>
        <?php else: ?><p>هنوز بکاپ دستی ساخته نشده. (بکاپ روزانه‌ی خودکار جدا نگه داشته می‌شه.)</p><?php endif; ?>
    </div>
    <?php
}

/* =========================================================================
 * افزودنِ یک‌باره‌ی مقاله‌ها از seed/articles-*.json (پیوندِ یکتای انگلیسیِ مشخص)
 * اگه مقاله‌ای با همین پیوند از قبل باشه، دوباره ساخته نمی‌شه.
 * ========================================================================= */
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    $done = (array) get_option('nexv_article_seed_done', []);
    $made = [];
    foreach ((array) glob(get_stylesheet_directory() . '/seed/articles-*.json') as $file) {
        $key = basename($file);
        if (in_array($key, $done, true)) continue;
        $list = json_decode((string) file_get_contents($file), true);
        foreach ((array) $list as $a) {
            $slug = sanitize_title($a['slug'] ?? '');
            if ($slug === '' || empty($a['title'])) continue;
            if (get_page_by_path($slug, OBJECT, 'nexv_article')) continue;
            $id = wp_insert_post([
                'post_type' => 'nexv_article', 'post_status' => 'publish', 'post_name' => $slug,
                'post_title' => sanitize_text_field($a['title']), 'post_excerpt' => sanitize_textarea_field($a['excerpt'] ?? ''),
                'post_content' => wp_kses_post($a['content'] ?? ''),
            ]);
            if ($id && !is_wp_error($id)) $made[] = $a['title'];
        }
        $done[] = $key;
    }
    if ($made || count($done) !== count((array) get_option('nexv_article_seed_done', []))) {
        update_option('nexv_article_seed_done', $done, false);
        if ($made) set_transient('nexv_article_seed_report', $made, DAY_IN_SECONDS);
    }
});
add_action('admin_notices', function () {
    $m = get_transient('nexv_article_seed_report');
    if (!$m || !current_user_can('manage_options')) return;
    delete_transient('nexv_article_seed_report');
    echo '<div class="notice notice-success is-dismissible"><p><strong>NEXV — ' . count($m) . ' مقاله‌ی جدید اضافه شد:</strong> ' . esc_html(implode('، ', $m)) . '</p></div>';
});

/* =========================================================================
 * اعلان‌ها (PWA + Web Push): اعلانِ سفارشِ جدید برای مدیر + اعلانِ همگانی برای مشتری‌ها
 * - کلیدهای VAPID یک‌بار روی خودِ سایت ساخته می‌شن (بدون سرویس یا کتابخونه‌ی بیرونی)
 * - مشترک‌ها و ارسال: push-lib.php (مشترکِ همین فایل و api.php)
 * - روی آیفون فقط وقتی کار می‌کنه که سایت «Add to Home Screen» شده و از همون آیکون باز بشه
 * ========================================================================= */
if (file_exists(get_template_directory() . '/push-lib.php')) require_once get_template_directory() . '/push-lib.php';
function nexv_b64u($bin) { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }
function nexv_vapid() {
    $v = get_option('nexv_vapid');
    if (is_array($v) && !empty($v['private']) && !empty($v['public'])) return $v;
    if (!function_exists('openssl_pkey_new')) return null;
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if (!$key) return null;
    openssl_pkey_export($key, $pem);
    $d = openssl_pkey_get_details($key);
    $x = str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT); $y = str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
    $v = ['private' => $pem, 'public' => nexv_b64u("\x04" . $x . $y)];
    update_option('nexv_vapid', $v, false);
    return $v;
}
function nexv_push_subject() { $e = (string) get_option('admin_email'); return 'mailto:' . (is_email($e) && strpos($e, '@nexv.local') === false ? $e : 'admin@nexv.ir'); }
function nexv_push_data_dir() { return dirname(nexv_orders_db_path()); }
/* اعلانِ سفارشِ جدید → فقط دستگاه‌های مدیر */
function nexv_push_order_notify($order) {
    if (!function_exists('nexv_pl_send')) return null;
    $v = nexv_vapid(); if (!$v) return null;
    $items = implode('، ', array_map(function ($it) { return ($it['title'] ?? '') . ' ×' . (int) ($it['qty'] ?? 1); }, array_slice((array) ($order['items'] ?? []), 0, 3)));
    return nexv_pl_send(nexv_push_data_dir(), $v, 'admins', [
        'title' => '🛒 سفارش جدید #' . (int) ($order['id'] ?? 0),
        'body'  => trim(($order['customerName'] ?? '') . ' — ' . number_format((int) ($order['grandTotal'] ?? 0)) . ' تومان' . ($items !== '' ? "\n" . $items : '')),
        'url'   => home_url('/?nexv_admin=orders'),
        'tag'   => 'nexv-order-' . (int) ($order['id'] ?? 0),
    ], nexv_push_subject());
}
/* دستگاه‌هایی که با نسخه‌ی قبلی (لیستِ داخلِ تنظیماتِ وردپرس) ثبت شده بودن، یک‌بار منتقل می‌شن */
add_action('admin_init', function () {
    $old = get_option('nexv_push_subs', null);
    if (!is_array($old) || !function_exists('nexv_pl_subscribe')) return;
    foreach ($old as $s) { if (!empty($s['endpoint'])) nexv_pl_subscribe(nexv_push_data_dir(), ['endpoint' => $s['endpoint']], true, $s['ua'] ?? ''); }
    delete_option('nexv_push_subs'); delete_option('nexv_push_last'); delete_option('nexv_push_test_until');
});
function nexv_manifest_data() {
    $logo = get_template_directory_uri() . '/logo.png';
    return [
        'name' => 'NEXV — فروشگاه قطعات اپل', 'short_name' => 'NEXV', 'lang' => 'fa', 'dir' => 'rtl', 'id' => home_url('/'),
        'start_url' => home_url('/?source=pwa'), 'scope' => home_url('/'), 'display' => 'standalone',
        'background_color' => '#f3f1f9', 'theme_color' => '#6a4dff',
        'icons' => [['src' => $logo, 'sizes' => '300x300', 'type' => 'image/png', 'purpose' => 'any'], ['src' => $logo, 'sizes' => '192x192', 'type' => 'image/png'], ['src' => $logo, 'sizes' => '512x512', 'type' => 'image/png']],
    ];
}

/* راه‌های جایگزین برای سرویس‌ورکر و manifest (اصلی‌ها از api.php سرو می‌شن):
   /?nexv_sw=1 و /?nexv_manifest=1 ، و آدرس‌های قبلی /nexv-sw.js و /nexv-manifest.webmanifest */
add_action('init', function () {
    if (!function_exists('nexv_pl_serve_sw')) return;
    $path = trim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $base = trim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');
    if ($base !== '' && strpos($path, $base) === 0) $path = trim(substr($path, strlen($base)), '/');
    if ($path === 'nexv-manifest.webmanifest' || ($path === '' && isset($_GET['nexv_manifest']))) {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo wp_json_encode(nexv_manifest_data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($path === 'nexv-sw.js' || ($path === '' && isset($_GET['nexv_sw']))) {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        nexv_pl_serve_sw();
    }
}, 0);
