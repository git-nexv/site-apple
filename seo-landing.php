<?php
if (!defined('ABSPATH')) { exit; }

/**
 * صفحه‌های فرودِ سئو (آذر ۱۴۰۵) — مکملِ seo-hubs.php:
 * ۱) کلمه‌های کلیدی/هویتِ برند: /nexv/ (NEXV، نکس وی)، /iphone-parts/، /apple-parts/،
 *    /original-iphone-parts/، /iphone-parts-tehran/
 * ۲) قیمتِ یک قطعه برای همه‌ی مدل‌ها (مثلاً «قیمت باتری آیفون»): /iphone-<part>-price/
 *    (battery، display، back-glass، rear-camera، front-camera، face-id، charging-port، housing، motherboard)
 *    با جدولِ «مدل ← بازه‌ی قیمت ← لینک به /parts-price/<model>/<part>/»؛ بی‌محصول = ۴۰۴.
 * ۳) سایت‌مپ: /landing-sitemap.xml (خودکار به sitemap_index.xml اضافه می‌شه).
 * ۴) عنوان/توضیحِ صفحه‌ی اصلی و اسم‌های دیگه‌ی برند در اسکیمای Rank Math.
 */

function nexv_lp_money($n) {
    return $n > 0 ? nexv_hub_fa_digits(number_format($n)) . ' تومان' : 'تماس بگیرید';
}

function nexv_lp_keyword_pages() {
    $site = 'NEXV';
    return [
        'nexv' => [
            'title' => 'NEXV (نکس وی) | فروشگاه اینترنتی قطعات اورجینال اپل',
            'h1'    => 'NEXV | نکس وی — فروشگاه آنلاین قطعات آیفون، آیپد و اپل واچ',
            'desc'  => 'NEXV (نکس وی) فروشگاه اینترنتی تخصصی قطعات اورجینال اپل؛ ال سی دی، باتری، دوربین، برد و قاب آیفون، آیپد و اپل واچ با قیمت روز و ارسال به سراسر ایران.',
            'kw'    => 'NEXV، نکس وی، نکسو، فروشگاه نکس وی، nexv.ir',
            'intro' => [
                '<b>NEXV</b> (نکس وی) یک فروشگاهِ اینترنتیِ تخصصیِ <b>قطعاتِ اپل</b>ه؛ از ال سی دی و باتریِ آیفون تا دوربین، فیس آیدی، برد، قاب و فلت‌ها برای آیفون، آیپد و اپل واچ.',
                'هر قطعه به مدلِ دقیقش وصل شده و برای هر مدلِ آیفون صفحه‌ی <a href="' . esc_url(home_url('/parts-price/iphone-13/')) . '">لیست قیمت قطعات</a> داریم تا قیمتِ روزِ همه‌ی قطعات رو یکجا ببینید.',
                'قطعات قبل از ارسال تست می‌شن و برای تعمیرکارها و مشتری‌های عادی، استعلامِ سریع از طریقِ واتساپ ممکنه.',
            ],
            'faq' => [
                ['NEXV چیست؟', 'NEXV (نکس وی) فروشگاه اینترنتی قطعات اورجینال اپل (آیفون، آیپد و اپل واچ) است.'],
                ['آدرس سایت نکس وی چیست؟', 'آدرس رسمی فروشگاه ' . preg_replace('#^https?://#', '', untrailingslashit(home_url('/'))) . ' است.'],
                ['آیا نکس وی به شهرستان ارسال دارد؟', 'بله، سفارش‌ها به سراسر ایران ارسال می‌شوند.'],
            ],
        ],
        'iphone-parts' => [
            'title' => 'قطعات آیفون | خرید قطعات اورجینال آیفون با قیمت روز | ' . $site,
            'h1'    => 'خرید قطعات آیفون با قیمت روز',
            'desc'  => 'خرید قطعات آیفون ۱۱ تا ۱۶ پرو مکس: ال سی دی، باتری، دوربین، فیس آیدی، شیشه پشت، فلت شارژ، برد و قاب؛ اورجینال با تست قبل از ارسال در ' . $site . '.',
            'kw'    => 'قطعات آیفون، لوازم یدکی آیفون، قطعات اورجینال آیفون',
            'intro' => [
                'در ' . $site . ' <b>قطعاتِ آیفون</b> بر اساسِ مدل دسته‌بندی شدن؛ مدلِ گوشیتون رو انتخاب کنید تا همه‌ی قطعاتِ سازگار با قیمت نمایش داده بشه.',
                'پرتقاضاترین قطعات: <b>ال سی دی</b>، <b>باتری</b>، <b>دوربین پشت و جلو</b>، <b>فیس آیدی</b>، <b>شیشه پشت</b>، <b>سوکت شارژ</b> و <b>برد</b>.',
            ],
            'faq' => [
                ['قطعات آیفون اورجینال است؟', 'نوع هر قطعه (اورجینال، های کپی و ...) در صفحه‌ی محصول مشخص شده است.'],
                ['چطور قطعه مناسب آیفونم را پیدا کنم؟', 'مدل دقیق را از تنظیمات ← General ← About ببینید و از لیست مدل‌ها انتخاب کنید.'],
            ],
        ],
        'apple-parts' => [
            'title' => 'قطعات اپل | قطعات آیفون، آیپد و اپل واچ | ' . $site,
            'h1'    => 'فروشگاه قطعات اپل؛ آیفون، آیپد و اپل واچ',
            'desc'  => 'خرید قطعات اورجینال اپل: قطعات آیفون، آیپد و اپل واچ با قیمت روز، تست قبل از ارسال و ارسال سریع از ' . $site . '.',
            'kw'    => 'قطعات اپل، قطعات آیپد، قطعات اپل واچ، فروشگاه قطعات اپل',
            'intro' => [
                $site . ' روی <b>قطعاتِ اپل</b> تخصص داره؛ قطعاتِ آیفون، <b>آیپد</b> و <b>اپل واچ</b> رو بر اساسِ مدل پیدا کنید.',
                'اگه قطعه‌ای رو پیدا نکردید، مدلِ دستگاه و اسمِ قطعه رو در واتساپ بفرستید تا تأمینش کنیم.',
            ],
            'faq' => [['قطعات آیپد و اپل واچ هم دارید؟', 'بله؛ دسته‌بندی‌های آیپد و اپل واچ در همین صفحه آمده است.']],
        ],
        'original-iphone-parts' => [
            'title' => 'قطعات اورجینال آیفون | تفاوت اورجینال و کپی | ' . $site,
            'h1'    => 'قطعات اورجینال آیفون؛ راهنمای خرید و قیمت',
            'desc'  => 'خرید قطعات اورجینال آیفون و راهنمای تشخیص قطعه اصلی از کپی؛ ال سی دی، باتری و دوربین اورجینال آیفون با قیمت روز در ' . $site . '.',
            'kw'    => 'قطعات اورجینال آیفون، ال سی دی اورجینال آیفون، باتری اورجینال آیفون',
            'intro' => [
                'قطعه‌ی <b>اورجینال</b> همون قطعه‌ی کارخانه‌ست و بیشترین سازگاری، کیفیت و حفظِ ارزشِ گوشی رو داره. در ' . $site . ' نوعِ هر قطعه شفاف نوشته شده.',
                'برای ال سی دی، فرقِ اورجینال، Soft OLED و Incell رو در <a href="' . esc_url(home_url('/blog/')) . '">مقالات آموزشی</a> توضیح دادیم.',
            ],
            'faq' => [['قطعه اورجینال را چطور تشخیص دهیم؟', 'کیفیت رنگ و روشنایی، کدهای روی قطعه و عملکرد True Tone/سلامت باتری از نشانه‌ها هستند؛ از فروشنده‌ی معتبر بخرید.']],
        ],
        'iphone-parts-tehran' => [
            'title' => 'قطعات آیفون در تهران | خرید قطعات موبایل اپل در تهران | ' . $site,
            'h1'    => 'خرید قطعات آیفون در تهران',
            'desc'  => 'دنبال قطعات آیفون در تهران هستید؟ ' . $site . ' قطعات اورجینال آیفون و آیپد را با قیمت روز و ارسال سریع در تهران و سراسر ایران عرضه می‌کند.',
            'kw'    => 'قطعات آیفون تهران، فروشگاه قطعات آیفون در تهران',
            'intro' => [
                'برای خریدِ <b>قطعاتِ آیفون در تهران</b> لازم نیست بازار رو بگردید؛ قطعه رو آنلاین انتخاب کنید و سفارش در تهران سریع ارسال می‌شه.',
                'تعمیرکارهای تهران می‌تونن از طریقِ واتساپ قیمت و موجودی رو استعلام کنن.',
            ],
            'faq' => [['ارسال در تهران چقدر طول می‌کشد؟', 'سفارش‌های تهران معمولاً همان روز یا روز کاری بعد ارسال می‌شوند.']],
        ],
    ];
}

/* قیمتِ یک نوعِ قطعه در همه‌ی مدل‌ها: [[modelSlug, model, min, max, count]] */
function nexv_lp_part_rows($part) {
    $rows = [];
    foreach (nexv_hub_models() as $ms => $m) {
        $sub = nexv_hub_filter_part(nexv_hub_products($ms), $part);
        if (!$sub) continue;
        $mins = array_filter(array_column($sub, 'min')); $maxs = array_filter(array_column($sub, 'max'));
        $rows[] = [$ms, $m, $mins ? min($mins) : 0, $maxs ? max($maxs) : 0, count($sub)];
    }
    return $rows;
}

function nexv_lp_all_urls() {
    $urls = [];
    foreach (array_keys(nexv_lp_keyword_pages()) as $k) $urls[] = home_url("/$k/");
    foreach (array_keys(nexv_hub_parts()) as $p) if (nexv_lp_part_rows($p)) $urls[] = home_url("/iphone-$p-price/");
    return $urls;
}

function nexv_lp_render($o) {
    $site = get_bloginfo('name') ?: 'NEXV';
    $phone = (string) get_theme_mod('nexv_contact_phone', '09109973428');
    $wa = preg_replace('/[^0-9]/', '', (string) get_theme_mod('nexv_contact_whatsapp', '09109973428'));
    if (substr($wa, 0, 1) === '0') $wa = '98' . substr($wa, 1);
    $graph = [];
    $crumbs = array_merge([[$site, home_url('/')]], $o['crumbs']);
    $li = []; $i = 0;
    foreach ($crumbs as $c) $li[] = ['@type' => 'ListItem', 'position' => ++$i, 'name' => wp_strip_all_tags($c[0]), 'item' => $c[1]];
    $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $li];
    if (!empty($o['items'])) {
        $il = []; $i = 0;
        foreach (array_slice($o['items'], 0, 100) as $it) $il[] = ['@type' => 'ListItem', 'position' => ++$i, 'url' => $it[1], 'name' => wp_strip_all_tags($it[0])];
        $graph[] = ['@type' => 'ItemList', 'name' => wp_strip_all_tags($o['h1']), 'itemListElement' => $il];
    }
    if (!empty($o['faq'])) {
        $q = [];
        foreach ($o['faq'] as $f) $q[] = ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => wp_strip_all_tags($f[1])]];
        $graph[] = ['@type' => 'FAQPage', 'mainEntity' => $q];
    }
    if (!empty($o['org'])) $graph = array_merge($graph, nexv_lp_org_schema());
    status_header(200);
    header('Content-Type: text/html; charset=UTF-8');
    $og = function_exists('nexv_icon_url') ? nexv_icon_url('icon-512.png') : '';
    ?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<script>(function(){try{var t=localStorage.getItem('nexv_theme'); if(t==='dark'){document.documentElement.setAttribute('data-theme','dark');}}catch(e){}})();</script>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php echo function_exists('nexv_favicon_tags') ? nexv_favicon_tags() : ''; ?>
<title><?php echo esc_html($o['title']); ?></title>
<meta name="description" content="<?php echo esc_attr($o['desc']); ?>">
<?php if (!empty($o['kw'])): ?><meta name="keywords" content="<?php echo esc_attr($o['kw']); ?>"><?php endif; ?>
<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large">
<link rel="canonical" href="<?php echo esc_url($o['url']); ?>">
<meta property="og:locale" content="fa_IR"><meta property="og:type" content="website"><meta property="og:site_name" content="<?php echo esc_attr($site); ?>">
<meta property="og:title" content="<?php echo esc_attr($o['title']); ?>"><meta property="og:description" content="<?php echo esc_attr($o['desc']); ?>">
<meta property="og:url" content="<?php echo esc_url($o['url']); ?>"><?php if ($og): ?><meta property="og:image" content="<?php echo esc_url($og); ?>"><?php endif; ?>
<script type="application/ld+json"><?php echo wp_json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" onload="this.onload=null;this.rel='stylesheet'">
<style>
:root{--bg:#f7f6fb;--card:#fff;--text:#201f2b;--dim:#5c5a6c;--line:#eee;--accent:#6a4dff;--soft:#efebff}
html[data-theme="dark"]{--bg:#08080d;--card:#15141c;--text:#ececf4;--dim:#b9b6c9;--line:#26242f;--accent:#a58fff;--soft:#1d1733}
html,body{background:var(--bg)}
body{font-family:'Vazirmatn',Tahoma,sans-serif;margin:0;padding:28px 16px 120px;color:var(--text);line-height:2}
.lp{max-width:900px;margin:0 auto;background:var(--card);border-radius:24px;padding:30px 26px;box-shadow:0 20px 50px rgba(0,0,0,.07)}
.lp nav.bc{font-size:12.5px;color:var(--dim);margin-bottom:10px}.lp nav.bc a{color:var(--dim);text-decoration:none}
.lp h1{font-size:23px;line-height:1.6;margin:0 0 6px}.lp .upd{font-size:12.5px;color:var(--dim);margin:0 0 16px}
.lp h2{font-size:17px;margin:28px 0 10px;padding-top:18px;border-top:1px solid var(--line)}
.lp p{font-size:14.5px;color:var(--dim);margin:0 0 12px}.lp p b{color:var(--text)}
.lp a{color:var(--accent)}
.cta{display:flex;flex-wrap:wrap;gap:10px;margin:16px 0 4px}.cta a{flex:1 1 180px;text-align:center;padding:11px 14px;border-radius:14px;font-weight:700;font-size:14px;text-decoration:none;background:var(--accent);color:#fff}.cta a.alt{background:var(--soft);color:var(--accent)}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px;padding:0;margin:0;list-style:none}
.grid a{display:block;padding:10px 12px;border:1px solid var(--line);border-radius:12px;text-decoration:none;color:var(--text);font-size:13.5px}.grid a small{display:block;color:var(--dim);font-size:12px}
table{width:100%;border-collapse:collapse;font-size:13.5px}td,th{padding:9px 6px;border-bottom:1px solid var(--line);text-align:right;vertical-align:top}th{color:var(--dim);font-weight:600}td.pr{white-space:nowrap;font-weight:700}
td a{color:var(--text);text-decoration:none}
details{border:1px solid var(--line);border-radius:12px;padding:10px 14px;margin:8px 0}summary{cursor:pointer;font-weight:700;font-size:14px}details p{margin:8px 0 0}
</style>
</head>
<body>
<main class="lp">
<nav class="bc"><?php $n = count($crumbs); foreach ($crumbs as $k => $c) { echo $k < $n - 1 ? '<a href="' . esc_url($c[1]) . '">' . esc_html($c[0]) . '</a> › ' : esc_html($c[0]); } ?></nav>
<h1><?php echo esc_html($o['h1']); ?></h1>
<?php if (!empty($o['updated'])): ?><p class="upd">آخرین به‌روزرسانیِ قیمت‌ها: <?php echo esc_html($o['updated']); ?></p><?php endif; ?>
<?php foreach ((array) $o['intro'] as $p) echo '<p>' . wp_kses($p, ['b' => [], 'a' => ['href' => []]]) . '</p>'; ?>
<div class="cta"><a href="<?php echo esc_url($o['shop'] ?? home_url('/')); ?>"><?php echo esc_html($o['shop_label'] ?? 'ورود به فروشگاه'); ?></a><?php if ($wa): ?><a class="alt" href="https://wa.me/<?php echo esc_attr($wa); ?>">استعلام در واتساپ</a><?php endif; ?><a class="alt" href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone)); ?>">تماس: <span dir="ltr"><?php echo esc_html($phone); ?></span></a></div>
<?php echo $o['body']; ?>
<?php if (!empty($o['faq'])): ?><h2>پرسش‌های پرتکرار</h2><?php foreach ($o['faq'] as $f) echo '<details><summary>' . esc_html($f[0]) . '</summary><p>' . esc_html(wp_strip_all_tags($f[1])) . '</p></details>'; endif; ?>
<?php echo nexv_lp_more_links($o['url']); ?>
</main>
<?php echo function_exists('nexv_static_dock_html') ? nexv_static_dock_html() : ''; ?>
</body>
</html><?php
    exit;
}

function nexv_lp_more_links($self) {
    $links = [[home_url('/nexv/'), 'درباره NEXV'], [home_url('/iphone-parts/'), 'قطعات آیفون'], [home_url('/apple-parts/'), 'قطعات اپل'], [home_url('/original-iphone-parts/'), 'قطعات اورجینال آیفون'], [home_url('/iphone-parts-tehran/'), 'قطعات آیفون در تهران']];
    foreach (nexv_hub_parts() as $ps => $p) $links[] = [home_url("/iphone-$ps-price/"), 'قیمت ' . $p['fa'] . ' آیفون'];
    $h = '<h2>صفحه‌های مرتبط</h2><ul class="grid">';
    foreach ($links as $l) if (untrailingslashit($l[0]) !== untrailingslashit($self)) $h .= '<li><a href="' . esc_url($l[0]) . '">' . esc_html($l[1]) . '</a></li>';
    return $h . '</ul>';
}
function nexv_lp_model_grid() {
    $h = '<ul class="grid">';
    foreach (nexv_hub_models() as $ms => $m) if (nexv_hub_products($ms)) $h .= '<li><a href="' . esc_url(nexv_hub_url($ms)) . '">قیمت قطعات ' . esc_html($m['fa']) . '<small>' . esc_html($m['en']) . '</small></a></li>';
    return $h . '</ul>';
}
function nexv_lp_cat_grid() {
    $h = '<ul class="grid">';
    foreach ((array) get_terms(['taxonomy' => 'nexv_cat', 'hide_empty' => true, 'parent' => 0]) as $top) {
        if (is_wp_error($top)) continue;
        foreach ((array) get_terms(['taxonomy' => 'nexv_cat', 'hide_empty' => true, 'parent' => $top->term_id]) as $c) {
            if (is_wp_error($c)) continue;
            $l = get_term_link($c);
            if (!is_wp_error($l)) $h .= '<li><a href="' . esc_url($l) . '">' . esc_html($c->name) . '<small>' . esc_html($top->name) . '</small></a></li>';
        }
    }
    return $h . '</ul>';
}
function nexv_lp_org_schema() {
    $alts = ['نکس وی', 'نکسو', 'Nexv', 'NEXV.ir', 'فروشگاه اینترنتی نکس وی'];
    $ig = 'https://www.instagram.com/nexv.ir';
    $logo = function_exists('nexv_logo_url') ? nexv_logo_url() : '';
    return [
        ['@type' => 'WebSite', '@id' => home_url('/#website'), 'url' => home_url('/'), 'name' => 'NEXV', 'alternateName' => $alts, 'inLanguage' => 'fa-IR',
         'publisher' => ['@id' => home_url('/#organization')],
         'potentialAction' => ['@type' => 'SearchAction', 'target' => home_url('/?nexv_search={search_term_string}'), 'query-input' => 'required name=search_term_string']],
        ['@type' => ['Organization', 'Store'], '@id' => home_url('/#organization'), 'name' => 'NEXV', 'alternateName' => $alts, 'url' => home_url('/'),
         'logo' => $logo, 'image' => $logo, 'telephone' => (string) get_theme_mod('nexv_contact_phone', '09109973428'),
         'description' => 'فروشگاه اینترنتی قطعات اورجینال اپل؛ قطعات آیفون، آیپد و اپل واچ.',
         'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'تهران', 'addressRegion' => 'تهران', 'addressCountry' => 'IR'],
         'areaServed' => ['@type' => 'Country', 'name' => 'Iran'], 'sameAs' => [$ig]],
    ];
}

add_action('parse_request', function () {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $homePath = trim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');
    if ($homePath !== '' && strpos($path, $homePath . '/') === 0) $path = substr($path, strlen($homePath) + 1);
    if (!preg_match('#^[a-z0-9-]+$#', $path) || !function_exists('nexv_hub_models')) return;
    $kw = nexv_lp_keyword_pages();
    if (isset($kw[$path])) {
        $p = $kw[$path];
        $body = '<h2>لیست قیمت قطعات بر اساس مدل آیفون</h2>' . nexv_lp_model_grid() . '<h2>دسته‌بندی قطعات</h2>' . nexv_lp_cat_grid();
        nexv_lp_render(['url' => home_url("/$path/"), 'title' => $p['title'], 'h1' => $p['h1'], 'desc' => $p['desc'], 'kw' => $p['kw'],
            'intro' => $p['intro'], 'faq' => $p['faq'], 'body' => $body, 'crumbs' => [[$p['h1'], home_url("/$path/")]], 'org' => $path === 'nexv']);
    }
    if (!preg_match('#^iphone-([a-z-]+)-price$#', $path, $mm)) return;
    $parts = nexv_hub_parts();
    $ps = $mm[1];
    if (!isset($parts[$ps])) return;
    $rows = nexv_lp_part_rows($ps);
    if (!$rows) return;
    $pf = $parts[$ps]['fa'];
    $short = trim(preg_replace('/\s*\(.+\)\s*/u', '', $pf));
    $tbl = '<h2>جدول قیمت ' . esc_html($short) . ' همه‌ی مدل‌های آیفون</h2><table><thead><tr><th>مدل</th><th>تعداد</th><th>قیمت</th></tr></thead><tbody>';
    $items = []; $gmin = 0; $gmax = 0;
    foreach ($rows as [$ms, $m, $min, $max, $n]) {
        $u = nexv_hub_url($ms, $ps);
        $items[] = [$short . ' ' . $m['fa'], $u];
        if ($min) $gmin = $gmin ? min($gmin, $min) : $min;
        $gmax = max($gmax, $max);
        $range = $min ? ($max > $min ? 'از ' . nexv_lp_money($min) . ' تا ' . nexv_lp_money($max) : nexv_lp_money($min)) : 'تماس بگیرید';
        $tbl .= '<tr><td><a href="' . esc_url($u) . '">' . esc_html($short . ' ' . $m['fa']) . '</a><br><small>' . esc_html($m['en']) . '</small></td><td>' . esc_html(nexv_hub_fa_digits($n)) . '</td><td class="pr">' . esc_html($range) . '</td></tr>';
    }
    $tbl .= '</tbody></table>';
    $copy = function_exists('nexv_hub_part_copy') ? nexv_hub_part_copy($ps, $rows[0][1]) : [];
    $faq = [
        ['قیمت ' . $short . ' آیفون چقدر است؟', $gmin ? ('قیمت ' . $short . ' آیفون بسته به مدل و نوع قطعه از ' . nexv_lp_money($gmin) . ($gmax > $gmin ? ' تا ' . nexv_lp_money($gmax) : '') . ' است؛ قیمت دقیق هر مدل در جدول آمده است.') : 'قیمت بر اساس مدل با تماس اعلام می‌شود.'],
        [$short . ' مدل‌های مختلف آیفون با هم یکی است؟', 'خیر؛ ' . $short . ' هر مدل مخصوص همان مدل است (حتی مدل‌های هم‌اندازه)؛ مدل دقیق را از تنظیمات ← General ← About ببینید.'],
        ['ارسال و گارانتی چگونه است؟', 'قطعات قبل از ارسال تست می‌شوند و به سراسر ایران ارسال می‌شوند.'],
    ];
    nexv_lp_render(['url' => home_url("/iphone-$ps-price/"),
        'title' => 'قیمت ' . $short . ' آیفون | لیست قیمت ' . $short . ' همه‌ی مدل‌ها | NEXV',
        'h1' => 'قیمت ' . $pf . ' آیفون؛ همه‌ی مدل‌ها', 'updated' => function_exists('nexv_jalali_pretty') ? nexv_jalali_pretty(current_time('timestamp')) : '',
        'desc' => 'لیست قیمت روز ' . $short . ' آیفون ۱۱ تا ۱۶ پرو مکس' . ($gmin ? ' از ' . nexv_lp_money($gmin) : '') . '؛ مقایسه‌ی قیمت ' . $short . ' همه‌ی مدل‌های آیفون و خرید با تست قبل از ارسال.',
        'kw' => 'قیمت ' . $short . ' آیفون، ' . $short . ' آیفون، خرید ' . $short . ' آیفون',
        'intro' => ['قیمتِ روزِ <b>' . esc_html($pf) . 'ِ همه‌ی مدل‌های آیفون</b> رو در جدولِ زیر یکجا ببینید و روی مدلِ خودتون بزنید تا همه‌ی گزینه‌ها (اورجینال، تقویتی، کپی و ...) با قیمت نمایش داده بشه.',
                    'قبل از خرید، مدلِ دقیقِ گوشی رو بدونید؛ قطعه‌ی هر مدل فقط با همون مدل سازگاره.'],
        'faq' => array_merge($faq, array_slice((array) ($copy['faq'] ?? []), 0, 2)),
        'items' => $items, 'body' => $tbl . '<h2>همه‌ی مدل‌ها</h2>' . nexv_lp_model_grid(), 'crumbs' => [['قیمت ' . $short . ' آیفون', home_url("/iphone-$ps-price/")]]]);
}, 1);

add_filter('rank_math/sitemap/index', function ($xml) {
    return $xml . "\t<sitemap>\n\t\t<loc>" . esc_url(home_url('/landing-sitemap.xml')) . "</loc>\n\t\t<lastmod>" . esc_html(gmdate('c', (int) get_option('nexv_lp_mod', time()))) . "</lastmod>\n\t</sitemap>\n";
}, 21);
add_action('save_post_nexv_product', function () { update_option('nexv_lp_mod', time(), false); });
add_action('wp_loaded', function () {
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (!preg_match('#/landing-sitemap\.xml$#', $path)) return;
    $mod = gmdate('c', (int) get_option('nexv_lp_mod', time()));
    status_header(200);
    header('Content-Type: application/xml; charset=UTF-8');
    header('X-Robots-Tag: noindex, follow');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach (nexv_lp_all_urls() as $u) echo "\t<url>\n\t\t<loc>" . esc_url($u) . "</loc>\n\t\t<lastmod>" . esc_html($mod) . "</lastmod>\n\t</url>\n";
    echo '</urlset>';
    exit;
}, 1);

/* صفحه‌ی اصلی: عنوان/توضیحِ سئو + اسم‌های دیگه‌ی برند در اسکیمای Rank Math */
function nexv_lp_home_title() { return 'NEXV (نکس وی) | فروشگاه قطعات اورجینال آیفون، آیپد و اپل واچ'; }
function nexv_lp_home_desc() { return 'NEXV (نکس وی)؛ خرید آنلاین قطعات اورجینال آیفون، آیپد و اپل واچ — ال سی دی، باتری، دوربین، فیس آیدی، برد و قاب — با قیمت روز، تست قبل از ارسال و ارسال سریع.'; }
add_filter('rank_math/frontend/title', function ($t) { return (function_exists('is_front_page') && is_front_page()) ? nexv_lp_home_title() : $t; }, 100);
add_filter('rank_math/frontend/description', function ($d) { return (function_exists('is_front_page') && is_front_page()) ? nexv_lp_home_desc() : $d; }, 100);
add_filter('rank_math/json_ld', function ($data) {
    if (!is_array($data)) return $data;
    $mine = nexv_lp_org_schema();
    foreach ($data as $k => $e) {
        if (!is_array($e) || empty($e['@type'])) continue;
        $types = (array) $e['@type'];
        if (in_array('WebSite', $types, true)) { $data[$k]['alternateName'] = $mine[0]['alternateName']; }
        elseif (array_intersect($types, ['Organization', 'Store', 'LocalBusiness', 'ElectronicsStore'])) {
            $data[$k]['alternateName'] = $mine[1]['alternateName'];
            $data[$k]['sameAs'] = array_values(array_unique(array_merge((array) ($e['sameAs'] ?? []), $mine[1]['sameAs'])));
        }
    }
    return $data;
}, 99);
