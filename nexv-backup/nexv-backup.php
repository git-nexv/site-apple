<?php
/**
 * Plugin Name: NEXV Backup — بکاپ و بازگردانیِ کاملِ سایت
 * Description: از کلِ سایت (دیتابیس، عکس‌ها، افزونه‌ها، قالب‌ها و داده‌های NEXV) یک فایلِ بکاپ می‌سازه و روی هر وردپرسِ دیگه‌ای برمی‌گردونه. فایلِ بکاپ تکه‌تکه آپلود می‌شه، پس سقفِ آپلودِ هاست مهم نیست.
 * Version: 2.0.0
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Author: NEXV
 */
if (!defined('ABSPATH')) { exit; }

/* =========================================================================
 * NEXV Backup
 *
 * همین فایل هم داخلِ قالبِ NEXV بارگذاری می‌شه (پیشخوان ← بکاپ) و هم به‌عنوانِ افزونه‌ی
 * جدا دانلود می‌شه. همه‌ی کد داخلِ یک شرط هست تا اگه قالب و افزونه با هم فعال باشن،
 * فقط یکی‌شون بارگذاری بشه (بدونِ خطای «تابع تکراری»).
 *
 * فایلِ بکاپ (.nexvbak) یک آرشیوِ tar ساده‌ست:
 *   nexv-backup/manifest.json   مشخصاتِ بکاپ
 *   nexv-backup/db.sql.gz       کلِ دیتابیسِ وردپرس (محصولات، سفارش‌ها، مقالات، کاربران،
 *                               تنظیمات و جدول‌ها/تنظیماتِ همه‌ی افزونه‌ها مثل Rank Math)
 *   nexv-backup/files/...       عکس‌ها و رسانه، داده‌های NEXV (سفارش‌ها، مشتری‌ها، رسیدها)،
 *                               همه‌ی افزونه‌ها و قالب‌ها، فایل‌های زبان
 * ساخت، آپلود و بازگردانی همه چندتکه‌ان (هر درخواست چند ثانیه) و اگه یک درخواست قطع
 * بشه از آخرین نقطه‌ی سالم ادامه پیدا می‌کنه.
 * ========================================================================= */
if (!defined('NEXV_BACKUP_VERSION')) {

define('NEXV_BACKUP_VERSION', '2.0.0');
define('NEXV_BACKUP_FILE', __FILE__);
if (!defined('NEXV_BK_BUDGET')) define('NEXV_BK_BUDGET', 10);   // ثانیه‌ی کار در هر درخواست
define('NEXV_BK_KEEP', 3);                                      // چندتا بکاپِ آخر روی هاست بمونه
define('NEXV_BK_STMT_MAX', 512 * 1024);                         // حداکثر طولِ هر INSERT (برای max_allowed_packet)
define('NEXV_BK_EXT', 'nexvbak');

/* ---------- ابزارهای عمومی ---------- */
function nexv_bk_uploads() { $u = wp_upload_dir(null, false); return rtrim(wp_normalize_path($u['basedir']), '/'); }
function nexv_bk_root() { return nexv_bk_uploads() . '/nexv-backups'; }
function nexv_rs_root() { return nexv_bk_uploads() . '/nexv-import'; }
function nexv_bk_protect($dir) {
    if (!is_dir($dir)) wp_mkdir_p($dir);
    if (!file_exists($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
    if (!file_exists($dir . '/index.php')) @file_put_contents($dir . '/index.php', "<?php // silence\n");
}
function nexv_bk_rrmdir($dir) {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { ($f->isDir() && !$f->isLink()) ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
    @rmdir($dir);
}
/* فایل‌های وضعیت با <?php exit; ?> شروع می‌شن تا از وب خونده نشن */
function nexv_bk_read_php_json($file) {
    $raw = @file_get_contents($file);
    if (!$raw) return null;
    $nl = strpos($raw, "\n");
    $d = json_decode($nl === false ? $raw : substr($raw, $nl + 1), true);
    return is_array($d) ? $d : null;
}
function nexv_bk_write_php_json($file, $data) {
    return @file_put_contents($file, "<?php exit; ?>\n" . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}
function nexv_bk_fmt_size($b) { return size_format((float) $b, 1) ?: '0 B'; }
function nexv_bk_date($ts) {
    return (function_exists('nexv_jalali_pretty') && function_exists('nexv_gregorian_to_jalali')) ? nexv_jalali_pretty($ts) . ' — ' . wp_date('H:i', $ts) : wp_date('Y/m/d H:i', $ts);
}
function nexv_bk_dbh() { global $wpdb; return $wpdb->dbh; }
function nexv_bk_sql($q) { return mysqli_query(nexv_bk_dbh(), $q); }
function nexv_bk_sql_error() { return mysqli_error(nexv_bk_dbh()); }
function nexv_bk_esc($v) { return mysqli_real_escape_string(nexv_bk_dbh(), (string) $v); }
/* مقدارِ رشته‌ای برای فایلِ SQL (مستقل از اتصال؛ مثلِ mysqldump) */
function nexv_bk_quote($v) {
    return "'" . strtr((string) $v, ["\\" => "\\\\", "\0" => "\\0", "\n" => "\\n", "\r" => "\\r", "'" => "\\'", '"' => '\\"', "\x1a" => "\\Z"]) . "'";
}
function nexv_bk_time_ok($t0) { return (microtime(true) - $t0) < NEXV_BK_BUDGET; }
/* اولین واحدِ کار در هر درخواست همیشه انجام می‌شه (پیشرفتِ تضمینی)، بقیه تا وقتی زمان باشه */
function nexv_bk_go($t0, &$i) { return $i++ === 0 || nexv_bk_time_ok($t0); }
function nexv_bk_token() { return bin2hex(random_bytes(12)); }

/* ---------- tar (بدونِ وابستگی؛ نام‌های بلند با هدرِ PAX) ---------- */
function nexv_tar_header($name, $size, $mtime, $type = '0') {
    $h = str_pad(substr($name, 0, 100), 100, "\0")
       . str_pad(sprintf('%07o', 0644), 8, "\0") . str_pad(sprintf('%07o', 0), 8, "\0") . str_pad(sprintf('%07o', 0), 8, "\0")
       . str_pad(sprintf('%011o', $size), 12, "\0") . str_pad(sprintf('%011o', max(0, (int) $mtime)), 12, "\0")
       . '        ' . $type . str_repeat("\0", 100) . "ustar\0" . '00'
       . str_repeat("\0", 32) . str_repeat("\0", 32) . str_repeat("\0", 8) . str_repeat("\0", 8) . str_repeat("\0", 155) . str_repeat("\0", 12);
    $sum = 0;
    for ($i = 0; $i < 512; $i++) $sum += ord($h[$i]);
    return substr_replace($h, str_pad(sprintf('%06o', $sum), 7, "\0") . ' ', 148, 8);
}
function nexv_tar_pax($name) {
    $body = ' path=' . $name . "\n";
    $n = strlen($body) + 1;
    while (strlen((string) $n) + strlen($body) !== $n) $n = strlen((string) $n) + strlen($body);
    $rec = $n . $body;
    return nexv_tar_header('PaxHeader', strlen($rec), time(), 'x') . $rec . str_repeat("\0", (512 - strlen($rec) % 512) % 512);
}
function nexv_tar_name_header($out, $name, $size, $mtime) {
    $hname = $name;
    if (strlen($name) > 100) { fwrite($out, nexv_tar_pax($name)); $hname = 'long/' . md5($name); }
    fwrite($out, nexv_tar_header($hname, $size, $mtime));
}
function nexv_tar_add($out, $abs, $name) {
    clearstatcache(true, $abs);
    if (!is_file($abs) || !is_readable($abs)) return 0;
    $size = (int) @filesize($abs);
    $in = @fopen($abs, 'rb');
    if (!$in) return 0;
    nexv_tar_name_header($out, $name, $size, (int) @filemtime($abs));
    $copied = $size > 0 ? (int) stream_copy_to_stream($in, $out, $size) : 0;
    fclose($in);
    if ($copied < $size) fwrite($out, str_repeat("\0", $size - $copied));   // فایل در همین فاصله کوچیک شده
    $pad = (512 - $size % 512) % 512;
    if ($pad) fwrite($out, str_repeat("\0", $pad));
    return 1;
}
function nexv_tar_add_data($out, $name, $data) {
    nexv_tar_name_header($out, $name, strlen($data), time());
    fwrite($out, $data);
    $pad = (512 - strlen($data) % 512) % 512;
    if ($pad) fwrite($out, str_repeat("\0", $pad));
}
/* هدرِ بعدی؛ null یعنی آخرِ آرشیو، false یعنی آرشیو خرابه */
function nexv_tar_next($fh) {
    $pax = null;
    while (true) {
        $h = fread($fh, 512);
        if ($h === false || strlen($h) < 512 || trim($h, "\0") === '') return null;
        $sum = 0;
        for ($i = 0; $i < 512; $i++) $sum += ($i >= 148 && $i < 156) ? 32 : ord($h[$i]);
        if ($sum !== octdec(trim(substr($h, 148, 8), "\0 "))) return false;
        $name = rtrim(substr($h, 0, 100), "\0");
        $size = (int) octdec(trim(substr($h, 124, 12), "\0 "));
        $type = $h[156];
        $prefix = rtrim(substr($h, 345, 155), "\0");
        if ($type === 'x' || $type === 'g') {
            $data = $size ? fread($fh, $size) : '';
            $pad = (512 - $size % 512) % 512;
            if ($pad) fread($fh, $pad);
            if ($type === 'x' && preg_match('/\d+ path=([^\n]*)\n/', $data, $m)) $pax = $m[1];
            continue;
        }
        return ['name' => $pax !== null ? $pax : ($prefix !== '' ? $prefix . '/' . $name : $name), 'size' => $size, 'type' => $type];
    }
}

/* ---------- zipِ کوچیک (فقط برای دانلودِ خودِ افزونه) ---------- */
function nexv_zip_small(array $files) {
    $out = ''; $cd = ''; $n = 0;
    foreach ($files as $name => $data) {
        $d = getdate();
        $t = ($d['hours'] << 11) | ($d['minutes'] << 5) | intdiv($d['seconds'], 2);
        $dt = (max(0, $d['year'] - 1980) << 9) | ($d['mon'] << 5) | $d['mday'];
        $crc = unpack('N', hash('crc32b', $data, true))[1];
        $z = gzdeflate($data, 9);
        $off = strlen($out);
        $out .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 8, $t, $dt, $crc, strlen($z), strlen($data), strlen($name), 0) . $name . $z;
        $cd .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 0x031E, 20, 0x0800, 8, $t, $dt, $crc, strlen($z), strlen($data), strlen($name), 0, 0, 0, 0, (0100644 << 16), $off) . $name;
        $n++;
    }
    return $out . $cd . pack('VvvvvVVv', 0x06054b50, 0, 0, $n, $n, strlen($cd), strlen($out), 0);
}

/* =========================================================================
 * ساختِ بکاپ
 * ========================================================================= */
function nexv_bk_sources() {
    $src = [['dir' => nexv_bk_uploads(), 'as' => 'uploads'], ['dir' => WP_PLUGIN_DIR, 'as' => 'plugins'], ['dir' => get_theme_root(), 'as' => 'themes']];
    if (defined('WPMU_PLUGIN_DIR') && is_dir(WPMU_PLUGIN_DIR)) $src[] = ['dir' => WPMU_PLUGIN_DIR, 'as' => 'mu-plugins'];
    if (defined('WP_LANG_DIR') && is_dir(WP_LANG_DIR)) $src[] = ['dir' => WP_LANG_DIR, 'as' => 'languages'];
    /* داده‌های NEXV اگه (به هر دلیلی) بیرون از پوشه‌ی uploads باشن */
    if (function_exists('nexv_orders_db_path')) {
        $nd = wp_normalize_path(dirname(nexv_orders_db_path()));
        if (is_dir($nd) && strpos($nd, nexv_bk_uploads() . '/') !== 0) $src[] = ['dir' => $nd, 'as' => 'nexv-data'];
    }
    return $src;
}
function nexv_bk_skip($as, $rel, $isDir) {
    $first = strtok($rel, '/');
    if ($as === 'uploads') {
        if (in_array($first, ['nexv-backups', 'nexv-import', 'cache', 'ai1wm-backups', 'updraft', 'wpvividbackups', 'wp-staging'], true) || strpos($first, 'backwpup') === 0) return true;
        if (!$isDir && preg_match('#^nexv-data/backups/backup-[^/]+\.json$#', $rel)) return true;   // نسخه‌های چرخشی؛ روزانه‌ها و latest می‌مونن
    }
    if ($first === '.git' || $first === 'node_modules' || strpos($rel, '/.git/') !== false || strpos($rel, '/node_modules/') !== false) return true;
    if ($as === 'plugins' && preg_match('#^[^/]+/cache(/|$)#', $rel)) return true;
    return false;
}
function nexv_bk_scan($listFile) {
    $out = fopen($listFile, 'wb');
    $count = 0; $bytes = 0;
    foreach (nexv_bk_sources() as $s) {
        $base = rtrim(wp_normalize_path($s['dir']), '/');
        if (!is_dir($base)) continue;
        $it = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
                function ($f) use ($base, $s) {
                    if ($f->isLink()) return false;
                    $rel = ltrim(substr(wp_normalize_path($f->getPathname()), strlen($base)), '/');
                    return !nexv_bk_skip($s['as'], $rel, $f->isDir());
                }
            ),
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        foreach ($it as $f) {
            if (!$f->isFile() || !$f->isReadable()) continue;
            $path = wp_normalize_path($f->getPathname());
            fwrite($out, $path . "\t" . $s['as'] . '/' . ltrim(substr($path, strlen($base)), '/') . "\n");
            $count++; $bytes += $f->getSize();
        }
    }
    fclose($out);
    return [$count, $bytes];
}
function nexv_bk_counts() {
    global $wpdb;
    $c = ['products' => 0, 'articles' => 0, 'pages' => 0, 'media' => 0, 'orders' => 0, 'customers' => 0, 'users' => 0];
    $q = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish','draft','private','pending','future')";
    foreach (['nexv_product' => 'products', 'nexv_article' => 'articles', 'page' => 'pages'] as $pt => $k) $c[$k] = (int) $wpdb->get_var($wpdb->prepare($q, $pt));
    $c['media'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'");
    $c['users'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
    $f = function_exists('nexv_orders_db_path') ? nexv_orders_db_path() : nexv_bk_uploads() . '/nexv-data/db.json';
    $d = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;
    if (is_array($d)) {
        $c['orders'] = count((array) ($d['orders'] ?? []));
        $c['customers'] = count(array_filter((array) ($d['users'] ?? []), function ($u) { return empty($u['is_admin']); }));
    }
    return $c;
}
function nexv_bk_file_name($st) { return 'nexv-backup-' . wp_date('Ymd-Hi', (int) $st['started']) . '.' . NEXV_BK_EXT; }

function nexv_bk_start() {
    global $wpdb;
    @set_time_limit(300);
    $root = nexv_bk_root();
    nexv_bk_protect($root);
    foreach ((array) glob($root . '/*', GLOB_ONLYDIR) as $d) {   // کارِ نیمه‌تمامِ قبلی
        $s = nexv_bk_read_php_json($d . '/state.php');
        if (!$s || ($s['phase'] ?? '') !== 'done') nexv_bk_rrmdir($d);
    }
    $id = gmdate('Ymd-His') . '-' . nexv_bk_token();
    $dir = $root . '/' . $id;
    nexv_bk_protect($dir);
    list($count, $bytes) = nexv_bk_scan($dir . '/files.list');
    $tables = []; $dbBytes = 0;
    foreach ((array) $wpdb->get_results($wpdb->prepare('SHOW TABLE STATUS LIKE %s', $wpdb->esc_like($wpdb->prefix) . '%'), ARRAY_A) as $t) {
        if (!empty($t['Comment']) && strtoupper($t['Comment']) === 'VIEW') continue;
        $tables[] = $t['Name'];
        $dbBytes += (int) $t['Data_length'];
    }
    $free = @disk_free_space($dir);
    $need = ($bytes + $dbBytes) * 1.2 + 50 * 1048576;
    if ($free !== false && $free < $need) {
        nexv_bk_rrmdir($dir);
        return new WP_Error('space', 'فضای خالیِ هاست کافی نیست: حدود ' . nexv_bk_fmt_size($need) . ' لازمه ولی ' . nexv_bk_fmt_size($free) . ' خالیه. بکاپ‌های قدیمی رو پاک کنید یا فضای هاست رو بیشتر کنید.');
    }
    if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $active = (array) get_option('active_plugins', []);
    $plugins = [];
    foreach (get_plugins() as $file => $p) $plugins[] = ['file' => $file, 'name' => $p['Name'], 'version' => $p['Version'], 'active' => in_array($file, $active, true)];
    $manifest = [
        'format' => 'nexv-full-backup', 'version' => 2, 'created' => time(), 'home' => home_url(), 'siteurl' => site_url(),
        'prefix' => $wpdb->prefix, 'theme' => get_stylesheet(), 'template' => get_template(), 'wp' => get_bloginfo('version'), 'php' => PHP_VERSION,
        'blogname' => get_bloginfo('name'), 'tables' => $tables, 'files' => $count, 'files_bytes' => $bytes, 'db_bytes' => $dbBytes,
        'counts' => nexv_bk_counts(), 'plugins' => $plugins,
    ];
    nexv_bk_write_php_json($dir . '/manifest.php', $manifest);
    file_put_contents($dir . '/db.sql.php', "<?php exit; ?>\n");
    $st = [
        'format' => 2, 'phase' => 'db', 'started' => time(), 'tables' => $tables, 'ti' => 0, 'row' => 0, 'created' => false,
        'sql_size' => filesize($dir . '/db.sql.php'), 'files' => $count, 'files_bytes' => $bytes, 'fi' => 0, 'list_off' => 0, 'out_size' => 0, 'head' => false,
    ];
    nexv_bk_write_php_json($dir . '/state.php', $st);
    return ['id' => $id, 'state' => $st];
}

/* دیتابیس: هر جدول یک CREATE و دسته‌های INSERT (هر دستور در یک خط) */
function nexv_bk_step_db($dir, array &$st, $t0) {
    global $wpdb;
    $fh = fopen($dir . '/db.sql.php', 'r+b');
    ftruncate($fh, $st['sql_size']);     // اگه درخواستِ قبلی وسطِ کار قطع شده بود
    fseek($fh, 0, SEEK_END);
    $go = 0;
    while ($st['ti'] < count($st['tables']) && nexv_bk_go($t0, $go)) {
        $t = $st['tables'][$st['ti']];
        if (!$st['created']) {
            $row = $wpdb->get_row('SHOW CREATE TABLE `' . esc_sql($t) . '`', ARRAY_N);
            if (!$row) { $st['ti']++; continue; }
            fwrite($fh, preg_replace('/\s*\n\s*/', ' ', $row[1]) . ";\n");
            $st['created'] = true; $st['row'] = 0;
        }
        $pk = $wpdb->get_col('SHOW KEYS FROM `' . esc_sql($t) . "` WHERE Key_name = 'PRIMARY'", 4);
        $order = $pk ? ' ORDER BY `' . implode('`, `', array_map('esc_sql', $pk)) . '`' : '';
        $res = nexv_bk_sql('SELECT * FROM `' . esc_sql($t) . '`' . $order . ' LIMIT ' . (int) $st['row'] . ', 1000');
        if ($res === false) { fclose($fh); return new WP_Error('db', 'خواندنِ جدولِ ' . $t . ' ممکن نشد: ' . nexv_bk_sql_error()); }
        $bin = [];
        foreach (mysqli_fetch_fields($res) as $i => $f) $bin[$i] = ($f->charsetnr == 63 && in_array($f->type, [249, 250, 251, 252, 253, 254], true));
        $head = 'INSERT INTO `' . $t . '` VALUES ';
        $buf = ''; $n = 0;
        while ($r = mysqli_fetch_row($res)) {
            $vals = [];
            foreach ($r as $i => $v) {
                if ($v === null) $vals[] = 'NULL';
                elseif ($bin[$i]) $vals[] = $v === '' ? "''" : '0x' . bin2hex($v);
                else $vals[] = nexv_bk_quote($v);
            }
            $tuple = '(' . implode(',', $vals) . ')';
            if ($buf !== '' && strlen($buf) + strlen($tuple) > NEXV_BK_STMT_MAX) { fwrite($fh, $head . $buf . ";\n"); $buf = ''; }
            $buf .= ($buf === '' ? '' : ',') . $tuple;
            $n++;
        }
        if ($buf !== '') fwrite($fh, $head . $buf . ";\n");
        mysqli_free_result($res);
        $st['row'] += $n;
        if ($n < 1000) { $st['ti']++; $st['created'] = false; $st['row'] = 0; }
        fflush($fh);
        $st['sql_size'] = ftell($fh);
    }
    fclose($fh);
    if ($st['ti'] >= count($st['tables'])) $st['phase'] = 'gz';
    return true;
}
/* فشرده‌سازیِ دیتابیس (متن تا ۱۰ برابر کوچیک‌تر می‌شه) */
function nexv_bk_step_gz($dir, array &$st) {
    $in = fopen($dir . '/db.sql.php', 'rb'); $out = fopen($dir . '/db.sql.gz', 'wb');
    $ctx = deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6]);
    while (!feof($in)) {
        $chunk = fread($in, 1048576);
        if ($chunk === '' || $chunk === false) break;
        fwrite($out, deflate_add($ctx, $chunk, ZLIB_NO_FLUSH));
    }
    fwrite($out, deflate_add($ctx, '', ZLIB_FINISH));
    fclose($in); fclose($out);
    $st['phase'] = 'tar';
    return true;
}
/* فایلِ نهایی: مشخصات + دیتابیس + همه‌ی فایل‌ها */
function nexv_bk_step_tar($dir, array &$st, $t0) {
    $file = $dir . '/' . nexv_bk_file_name($st);
    $out = fopen($file, 'c+b');
    ftruncate($out, $st['out_size']);
    fseek($out, 0, SEEK_END);
    if (!$st['head']) {
        $m = nexv_bk_read_php_json($dir . '/manifest.php');
        nexv_tar_add_data($out, 'nexv-backup/manifest.json', json_encode($m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        nexv_tar_add($out, $dir . '/db.sql.gz', 'nexv-backup/db.sql.gz');
        $st['head'] = true;
        $st['out_size'] = ftell($out);
    }
    $list = fopen($dir . '/files.list', 'rb');
    fseek($list, $st['list_off']);
    $go = 0;
    while (nexv_bk_go($t0, $go) && ($line = fgets($list)) !== false) {
        $line = rtrim($line, "\n");
        if ($line === '') continue;
        list($abs, $name) = explode("\t", $line, 2);
        nexv_tar_add($out, $abs, 'nexv-backup/files/' . $name);
        $st['fi']++;
        $st['list_off'] = ftell($list);
        $st['out_size'] = ftell($out);
    }
    $eof = feof($list) || fgets($list) === false;
    fclose($list);
    if ($eof) {
        fwrite($out, str_repeat("\0", 1024));   // پایانِ آرشیو
        $st['out_size'] = ftell($out);
        $st['phase'] = 'done';
        $st['finished'] = time();
    }
    fclose($out);
    if ($st['phase'] === 'done') {
        foreach (['db.sql.php', 'db.sql.gz', 'files.list'] as $f) @unlink($dir . '/' . $f);
        foreach (array_slice(nexv_bk_list(), NEXV_BK_KEEP) as $old) nexv_bk_rrmdir(nexv_bk_root() . '/' . $old['id']);
    }
    return true;
}
function nexv_bk_list() {
    $out = [];
    foreach ((array) glob(nexv_bk_root() . '/*', GLOB_ONLYDIR) as $d) {
        $st = nexv_bk_read_php_json($d . '/state.php');
        if (!$st) continue;
        if ((int) ($st['format'] ?? 0) < 2) { nexv_bk_rrmdir($d); continue; }   // بکاپ‌های نسخه‌ی قدیمی (فایلِ قالب) دیگه قابلِ استفاده نیستن
        if (($st['phase'] ?? '') !== 'done') continue;
        $file = $d . '/' . nexv_bk_file_name($st);
        if (!file_exists($file)) continue;
        $m = nexv_bk_read_php_json($d . '/manifest.php') ?: [];
        $out[] = ['id' => basename($d), 'file' => basename($file), 'size' => filesize($file), 'time' => (int) ($st['finished'] ?? $st['started']), 'counts' => $m['counts'] ?? []];
    }
    usort($out, function ($a, $b) { return $b['time'] <=> $a['time']; });
    return $out;
}
function nexv_bk_progress(array $st) {
    if ($st['phase'] === 'db') return ['percent' => round(2 + 20 * ($st['ti'] / max(1, count($st['tables']))), 1), 'label' => 'دیتابیس (' . min(count($st['tables']), $st['ti'] + 1) . ' از ' . count($st['tables']) . ' جدول)'];
    if ($st['phase'] === 'gz') return ['percent' => 23, 'label' => 'فشرده‌سازیِ دیتابیس'];
    if ($st['phase'] === 'tar') return ['percent' => round(25 + 74 * ($st['fi'] / max(1, $st['files'])), 1), 'label' => 'فایل‌ها (' . number_format_i18n($st['fi']) . ' از ' . number_format_i18n($st['files']) . ')'];
    return ['percent' => 100, 'label' => 'تموم شد'];
}

/* =========================================================================
 * بازگردانی
 * ========================================================================= */
function nexv_rs_dir($token) {
    $token = (string) $token;
    if (!preg_match('/^[a-f0-9]{24}$/', $token)) return false;
    $d = nexv_rs_root() . '/' . $token;
    return is_dir($d) ? $d : false;
}
function nexv_rs_state($dir, $st = null) {
    if ($st === null) return nexv_bk_read_php_json($dir . '/state.php');
    nexv_bk_write_php_json($dir . '/state.php', $st);
    return $st;
}
/* مشخصاتِ بکاپ از اولِ فایل خونده می‌شه */
function nexv_rs_read_manifest($file) {
    $fh = @fopen($file, 'rb');
    if (!$fh) return null;
    $h = nexv_tar_next($fh);
    $m = null;
    if ($h && $h['name'] === 'nexv-backup/manifest.json' && $h['size'] < 4 * 1048576) $m = json_decode((string) fread($fh, $h['size']), true);
    fclose($fh);
    return (is_array($m) && ($m['format'] ?? '') === 'nexv-full-backup' && (int) ($m['version'] ?? 0) >= 2) ? $m : null;
}
/* فایل‌هایی که با فایل‌منیجر داخلِ uploads/nexv-import گذاشته شدن، به یک پوشه‌ی امن منتقل می‌شن */
function nexv_rs_adopt_manual() {
    $root = nexv_rs_root();
    nexv_bk_protect($root);
    foreach ((array) glob($root . '/*.' . NEXV_BK_EXT) as $f) {
        if (!is_file($f)) continue;
        $dir = $root . '/' . nexv_bk_token();
        nexv_bk_protect($dir);
        if (!@rename($f, $dir . '/backup.' . NEXV_BK_EXT)) { nexv_bk_rrmdir($dir); continue; }
        $m = nexv_rs_read_manifest($dir . '/backup.' . NEXV_BK_EXT);
        nexv_rs_state($dir, ['phase' => $m ? 'uploaded' : 'invalid', 'name' => basename($f), 'size' => filesize($dir . '/backup.' . NEXV_BK_EXT), 'manifest' => $m, 'created' => time()]);
    }
}
function nexv_rs_imports() {
    $out = [];
    foreach ((array) glob(nexv_rs_root() . '/*', GLOB_ONLYDIR) as $d) {
        $st = nexv_rs_state($d);
        if (!$st) continue;
        if (($st['phase'] ?? '') === 'upload' && time() - (int) ($st['touched'] ?? $st['created'] ?? 0) > 3 * DAY_IN_SECONDS) { nexv_bk_rrmdir($d); continue; }
        if (in_array($st['phase'] ?? '', ['upload', 'invalid'], true)) continue;
        $out[] = ['token' => basename($d), 'state' => $st];
    }
    return $out;
}

function nexv_restore_url_map($oldHome, $newHome) {
    $oldHome = untrailingslashit($oldHome); $newHome = untrailingslashit($newHome);
    if ($oldHome === $newHome) return [];
    $u = wp_parse_url($oldHome);
    $host = $u['host'] ?? ''; $path = rtrim($u['path'] ?? '', '/'); $port = isset($u['port']) ? ':' . $u['port'] : '';
    if ($host === '') return [];
    $hosts = [$host . $port, (strpos($host, 'www.') === 0 ? substr($host, 4) : 'www.' . $host) . $port];
    $map = [];
    foreach (['https://', 'http://'] as $sch) {
        foreach ($hosts as $h) {
            $map[$sch . $h . $path] = $newHome;
            $map[str_replace('/', '\\/', $sch . $h . $path)] = str_replace('/', '\\/', $newHome);
        }
    }
    unset($map[$newHome]);
    return $map;
}
/* جایگزینیِ آدرس بدونِ خراب‌کردنِ داده‌های serialize‌شده‌ی PHP (حتی آبجکت‌ها) */
function nexv_sr($v, array $map) {
    if (!is_string($v) || $v === '') return $v;
    $hit = false;
    foreach ($map as $from => $to) { if (strpos($v, (string) $from) !== false) { $hit = true; break; } }
    if (!$hit) return $v;
    if (is_serialized($v)) {
        $p = 0;
        $out = nexv_sr_ser($v, $p, $map);
        return ($out !== null && $p === strlen($v)) ? $out : $v;   // اگه قابلِ تجزیه نبود، دست نمی‌خوره
    }
    return strtr($v, $map);
}
function nexv_sr_ser($s, &$p, array $map) {
    $t = $s[$p] ?? '';
    if ($t === 'N') { if (substr($s, $p, 2) !== 'N;') return null; $p += 2; return 'N;'; }
    if ($t === 'b' || $t === 'i' || $t === 'd' || $t === 'r' || $t === 'R') {
        $e = strpos($s, ';', $p); if ($e === false) return null;
        $tok = substr($s, $p, $e - $p + 1); $p = $e + 1; return $tok;
    }
    if ($t === 's' || $t === 'E') {
        if (!preg_match('/\G' . $t . ':(\d+):"/', $s, $m, 0, $p)) return null;
        $len = (int) $m[1]; $start = $p + strlen($m[0]);
        if (substr($s, $start + $len, 2) !== '";') return null;
        $str = substr($s, $start, $len); $p = $start + $len + 2;
        if ($t === 'E') return 'E:' . $len . ':"' . $str . '";';
        $new = nexv_sr($str, $map);
        return 's:' . strlen($new) . ':"' . $new . '";';
    }
    if ($t === 'a' || $t === 'O') {
        $re = $t === 'a' ? '/\Ga:(\d+):\{/' : '/\GO:\d+:"[^"]*":(\d+):\{/';
        if (!preg_match($re, $s, $m, 0, $p)) return null;
        $n = (int) $m[1]; $p += strlen($m[0]); $out = $m[0];
        for ($i = 0; $i < $n * 2; $i++) { $x = nexv_sr_ser($s, $p, $map); if ($x === null) return null; $out .= $x; }
        if (($s[$p] ?? '') !== '}') return null;
        $p++; return $out . '}';
    }
    if ($t === 'C') {
        if (!preg_match('/\GC:\d+:"[^"]*":(\d+):\{/', $s, $m, 0, $p)) return null;
        $len = (int) $m[1]; $start = $p + strlen($m[0]);
        if (($s[$start + $len] ?? '') !== '}') return null;
        $tok = substr($s, $p, $start + $len + 1 - $p); $p = $start + $len + 1; return $tok;
    }
    return null;
}

function nexv_restore_start($dir, array $st) {
    global $wpdb;
    $m = $st['manifest'] ?? null;
    if (!$m) return new WP_Error('manifest', 'مشخصاتِ بکاپ خونده نشد؛ فایل کامل آپلود نشده یا خرابه.');
    $need = (int) ($m['db_bytes'] ?? 0) * 2 + 100 * 1048576;
    $free = @disk_free_space(WP_CONTENT_DIR);
    if ($free !== false && $free < $need) return new WP_Error('space', 'فضای خالیِ هاست کافی نیست: حداقل ' . nexv_bk_fmt_size($need) . ' لازمه ولی ' . nexv_bk_fmt_size($free) . ' خالیه.');
    do { $tmp = 'nxr' . substr(bin2hex(random_bytes(3)), 0, 4) . '_'; } while ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tmp) . '%')));
    /* حسابِ مدیری که الان داره بازگردانی می‌کنه حفظ می‌شه تا بعدش هم بتونه وارد بشه */
    $me = wp_get_current_user();
    $keep = $me && $me->ID ? ['login' => $me->user_login, 'pass' => $me->user_pass, 'email' => $me->user_email, 'nicename' => $me->user_nicename,
        'display' => $me->display_name, 'registered' => $me->user_registered, 'tokens' => get_user_meta($me->ID, 'session_tokens', true)] : null;
    $file = $dir . '/backup.' . NEXV_BK_EXT;
    return array_merge($st, [
        'phase' => 'files', 'tmp' => $tmp, 'old_prefix' => $m['prefix'], 'new_prefix' => $wpdb->prefix,
        'old_home' => $m['home'], 'new_home' => home_url(), 'new_siteurl' => site_url(), 'cur_theme' => get_stylesheet(), 'cur_template' => get_template(),
        'tar_off' => 0, 'tar_size' => filesize($file), 'files_done' => 0,
        'sql_off' => 0, 'table_start' => 0, 'sql_size' => 0, 'tables' => [], 'cur_table' => null,
        'sr_ti' => 0, 'sr_last' => null, 'keep' => $keep, 'clean' => true, 'restore_started' => time(), 'log' => [],
    ]);
}
/* مقصدِ هر فایلِ داخلِ بکاپ روی این هاست */
function nexv_restore_dest($name) {
    if ($name === '' || strpos($name, '..') !== false || $name[0] === '/' || strpos($name, "\0") !== false) return null;
    $up = nexv_bk_uploads();
    $map = ['uploads/' => $up, 'plugins/' => WP_PLUGIN_DIR, 'themes/' => get_theme_root(), 'nexv-data/' => $up . '/nexv-data',
            'mu-plugins/' => defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins',
            'languages/' => defined('WP_LANG_DIR') ? WP_LANG_DIR : WP_CONTENT_DIR . '/languages'];
    foreach ($map as $pre => $base) {
        if (strpos($name, $pre) !== 0) continue;
        $rel = substr($name, strlen($pre));
        if ($rel === '') return null;
        $dest = rtrim(wp_normalize_path($base), '/') . '/' . $rel;
        /* خودِ همین ابزار (که الان داره اجرا می‌شه) بازنویسی نمی‌شه */
        if (strpos($dest, wp_normalize_path(dirname(NEXV_BACKUP_FILE)) . '/') === 0) return null;
        return $dest;
    }
    return null;
}
function nexv_restore_step_files($dir, array &$st, $t0) {
    $fh = fopen($dir . '/backup.' . NEXV_BK_EXT, 'rb');
    fseek($fh, $st['tar_off']);
    $go = 0;
    while (nexv_bk_go($t0, $go)) {
        $h = nexv_tar_next($fh);
        if ($h === false) { fclose($fh); return new WP_Error('tar', 'فایلِ بکاپ خرابه یا کامل آپلود نشده.'); }
        if ($h === null) { $st['phase'] = 'gunzip'; break; }
        $pad = (512 - $h['size'] % 512) % 512;
        $dest = null;
        if ($h['type'] === '0' || $h['type'] === "\0") {
            if ($h['name'] === 'nexv-backup/db.sql.gz') $dest = $dir . '/db.sql.gz';
            elseif (strpos($h['name'], 'nexv-backup/files/') === 0) $dest = nexv_restore_dest(substr($h['name'], 18));
        }
        if ($dest) {
            wp_mkdir_p(dirname($dest));
            $out = @fopen($dest, 'wb');
            if ($out) { if ($h['size']) stream_copy_to_stream($fh, $out, $h['size']); fclose($out); $st['files_done']++; }
            else { fseek($fh, $h['size'], SEEK_CUR); if (count($st['log']) < 50) $st['log'][] = 'نوشته نشد: ' . $h['name']; }
        } else {
            fseek($fh, $h['size'], SEEK_CUR);
        }
        if ($pad) fseek($fh, $pad, SEEK_CUR);
        $st['tar_off'] = ftell($fh);
    }
    fclose($fh);
    return true;
}
/* بازکردنِ دیتابیسِ فشرده + آدرسِ سایت داخلِ فایل‌های داده‌ی NEXV (اگه دامنه عوض شده) */
function nexv_restore_step_gunzip($dir, array &$st) {
    if (!file_exists($dir . '/db.sql.gz')) return new WP_Error('db', 'دیتابیس داخلِ فایلِ بکاپ پیدا نشد.');
    $in = gzopen($dir . '/db.sql.gz', 'rb'); $out = fopen($dir . '/db.sql.php', 'wb');
    while (!gzeof($in)) { $c = gzread($in, 1048576); if ($c === false || $c === '') break; fwrite($out, $c); }
    gzclose($in); fclose($out);
    @unlink($dir . '/db.sql.gz');
    $st['sql_size'] = filesize($dir . '/db.sql.php');
    $map = nexv_restore_url_map($st['old_home'], $st['new_home']);
    if ($map) {
        $base = nexv_bk_uploads() . '/nexv-data';
        foreach (array_merge((array) glob($base . '/*.json'), (array) glob($base . '/orders-archive/*.json'), (array) glob($base . '/orders-archive/*.jsonl')) as $f) {
            $c = @file_get_contents($f);
            if ($c === false) continue;
            $n = strtr($c, $map);
            if ($n !== $c) @file_put_contents($f, $n, LOCK_EX);
        }
    }
    $st['phase'] = 'db';
    return true;
}
/* دیتابیس داخلِ جدول‌های موقت (سایتِ فعلی تا آخرِ کار دست‌نخورده می‌مونه) */
function nexv_restore_create_sql($line, $old, $tmp) {
    $q = preg_quote($old, '/');
    $line = preg_replace('/^CREATE TABLE `' . $q . '/', 'CREATE TABLE `' . $tmp, $line);
    return preg_replace('/REFERENCES `' . $q . '/', 'REFERENCES `' . $tmp, $line);   // کلیدهای خارجیِ بعضی افزونه‌ها
}
function nexv_restore_step_db($dir, array &$st, $t0) {
    nexv_bk_sql("SET SESSION foreign_key_checks = 0, unique_checks = 0, sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");
    $fh = fopen($dir . '/db.sql.php', 'rb');
    if (empty($st['clean'])) $st['sql_off'] = $st['table_start'];   // درخواستِ قبلی نصفه موند: جدولِ فعلی از اول
    if ($st['sql_off'] === 0) fgets($fh); else fseek($fh, $st['sql_off']);
    $st['clean'] = false;
    nexv_rs_state($dir, $st);
    $old = $st['old_prefix']; $tmp = $st['tmp'];
    $go = 0;
    while (nexv_bk_go($t0, $go)) {
        $pos = ftell($fh);
        $line = fgets($fh);
        if ($line === false) { $st['phase'] = 'fix'; break; }
        $line = rtrim($line, "\r\n");
        if ($line === '') continue;
        if (strpos($line, 'CREATE TABLE `' . $old) === 0) {
            if (!preg_match('/^CREATE TABLE `' . preg_quote($old, '/') . '([^`]+)`/', $line, $m)) continue;
            $suffix = $m[1];
            $st['table_start'] = $pos; $st['cur_table'] = $suffix;
            if (!in_array($suffix, $st['tables'], true)) $st['tables'][] = $suffix;
            nexv_bk_sql('DROP TABLE IF EXISTS `' . $tmp . $suffix . '`');
            $sql = nexv_restore_create_sql($line, $old, $tmp);
            if (nexv_bk_sql($sql) === false) {
                /* سازگاری بینِ MySQL 8 و MariaDB / نسخه‌های قدیمی‌تر */
                $alt = str_replace(['utf8mb4_0900_ai_ci', 'utf8mb4_0900_as_cs', 'utf8mb4_0900_bin'], 'utf8mb4_unicode_520_ci', $sql);
                if (nexv_bk_sql($alt) === false) {
                    $alt = preg_replace('/\s*COLLATE[= ]\s*\w+/i', '', str_replace('utf8mb4_unicode_520_ci', 'utf8mb4_unicode_ci', $alt));
                    if (nexv_bk_sql($alt) === false) { fclose($fh); return new WP_Error('db', 'ساختِ جدولِ ' . $suffix . ' ممکن نشد: ' . nexv_bk_sql_error()); }
                }
            }
        } elseif (strpos($line, 'INSERT INTO `' . $old) === 0) {
            if (nexv_bk_sql('INSERT INTO `' . $tmp . substr($line, strlen('INSERT INTO `' . $old))) === false) {
                fclose($fh); return new WP_Error('db', 'وارد کردنِ اطلاعاتِ جدولِ ' . $st['cur_table'] . ' ممکن نشد: ' . nexv_bk_sql_error());
            }
        }
        $st['sql_off'] = ftell($fh);
    }
    fclose($fh);
    $st['clean'] = true;
    return true;
}
/* پیشوندِ جدول و آدرسِ سایتِ جدید روی جدول‌های موقت */
function nexv_restore_step_fix(array &$st, $t0) {
    $tmp = $st['tmp']; $old = $st['old_prefix']; $new = $st['new_prefix'];
    if (empty($st['prefix_fixed'])) {
        if ($old !== $new) {
            if (in_array('options', $st['tables'], true)) nexv_bk_sql("UPDATE `{$tmp}options` SET option_name = '" . nexv_bk_esc($new . 'user_roles') . "' WHERE option_name = '" . nexv_bk_esc($old . 'user_roles') . "'");
            if (in_array('usermeta', $st['tables'], true)) {
                $like = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $old) . '%';
                nexv_bk_sql("UPDATE `{$tmp}usermeta` SET meta_key = CONCAT('" . nexv_bk_esc($new) . "', SUBSTRING(meta_key, " . (strlen($old) + 1) . ")) WHERE meta_key LIKE '" . nexv_bk_esc($like) . "'");
            }
        }
        $st['prefix_fixed'] = true;
    }
    $map = nexv_restore_url_map($st['old_home'], $st['new_home']);
    if (!$map) { $st['phase'] = 'swap'; return true; }
    $needles = array_keys($map);
    $go = 0;
    while ($st['sr_ti'] < count($st['tables']) && nexv_bk_go($t0, $go)) {
        $t = $tmp . $st['tables'][$st['sr_ti']];
        $cols = []; $pk = null; $pkCount = 0;
        $res = nexv_bk_sql("SHOW COLUMNS FROM `$t`");
        while ($res && ($c = mysqli_fetch_assoc($res))) {
            if ($c['Key'] === 'PRI') { $pk = $c['Field']; $pkCount++; }
            if (preg_match('/char|text/i', $c['Type'])) $cols[] = $c['Field'];
        }
        if (!$cols || $pkCount !== 1) { $st['sr_ti']++; $st['sr_last'] = null; continue; }
        $where = $st['sr_last'] === null ? '' : " WHERE `$pk` > '" . nexv_bk_esc($st['sr_last']) . "'";
        $res = nexv_bk_sql('SELECT `' . $pk . '`, `' . implode('`, `', $cols) . "` FROM `$t`$where ORDER BY `$pk` LIMIT 500");
        $n = 0;
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $n++; $st['sr_last'] = $row[$pk];
            $set = [];
            foreach ($cols as $c) {
                $v = $row[$c];
                if ($v === null || $v === '' || $c === $pk) continue;
                $hit = false;
                foreach ($needles as $nd) { if (strpos($v, $nd) !== false) { $hit = true; break; } }
                if (!$hit) continue;
                $nv = nexv_sr($v, $map);
                if ($nv !== $v) $set[] = "`$c` = '" . nexv_bk_esc($nv) . "'";
            }
            if ($set) nexv_bk_sql("UPDATE `$t` SET " . implode(', ', $set) . " WHERE `$pk` = '" . nexv_bk_esc($row[$pk]) . "'");
        }
        if ($n < 500) { $st['sr_ti']++; $st['sr_last'] = null; }
    }
    if ($st['sr_ti'] >= count($st['tables'])) $st['phase'] = 'swap';
    return true;
}
/* جایگزینیِ نهایی: جدول‌های موقت جای جدول‌های فعلی رو می‌گیرن (در یک دستور) */
function nexv_restore_step_swap(array &$st) {
    global $wpdb;
    $tmp = $st['tmp']; $new = $st['new_prefix'];
    if (!in_array('options', $st['tables'], true) || !in_array('users', $st['tables'], true)) return new WP_Error('db', 'جدول‌های اصلیِ وردپرس در بکاپ نیست.');
    $set = function ($name, $value) use ($tmp) {
        $v = nexv_bk_esc(is_scalar($value) ? (string) $value : serialize($value));
        nexv_bk_sql("INSERT INTO `{$tmp}options` (option_name, option_value, autoload) VALUES ('" . nexv_bk_esc($name) . "', '$v', 'yes') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)");
    };
    $set('siteurl', untrailingslashit($st['new_siteurl']));
    $set('home', untrailingslashit($st['new_home']));
    /* قالبِ سایتِ قبلی (که با فایل‌ها برگشته)؛ اگه نبود، قالبِ فعلی */
    $m = $st['manifest'];
    $root = get_theme_root();
    $set('template', is_dir($root . '/' . $m['template']) ? $m['template'] : $st['cur_template']);
    $set('stylesheet', is_dir($root . '/' . $m['theme']) ? $m['theme'] : $st['cur_theme']);
    $set('nexv_post_restore', time());
    nexv_bk_sql("DELETE FROM `{$tmp}options` WHERE option_name IN ('rewrite_rules', 'theme_switched') OR option_name LIKE '\\_transient\\_%' OR option_name LIKE '\\_site\\_transient\\_%'");
    $up = $wpdb->get_var("SELECT option_value FROM `{$tmp}options` WHERE option_name = 'upload_path'");
    if ($up && !is_dir($up)) $set('upload_path', '');

    $live = (array) $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($new) . '%'));
    do { $bak = 'nxo' . substr(bin2hex(random_bytes(3)), 0, 4) . '_'; } while ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($bak) . '%')));
    $pairs = [];
    foreach ($st['tables'] as $sfx) {
        if (in_array($new . $sfx, $live, true)) $pairs[] = "`{$new}{$sfx}` TO `{$bak}{$sfx}`";
        $pairs[] = "`{$tmp}{$sfx}` TO `{$new}{$sfx}`";
    }
    if (nexv_bk_sql('RENAME TABLE ' . implode(', ', $pairs)) === false) return new WP_Error('db', 'جایگزینیِ جدول‌ها ممکن نشد: ' . nexv_bk_sql_error());
    foreach ($st['tables'] as $sfx) nexv_bk_sql("DROP TABLE IF EXISTS `{$bak}{$sfx}`");

    /* مدیرِ این هاست (همونی که الان واردِ پیشخوانه) اگه هم‌نامش در بکاپ نبود، اضافه می‌شه */
    $k = $st['keep'];
    $st['kept_user'] = false;
    if ($k && !$wpdb->get_var("SELECT ID FROM `{$new}users` WHERE user_login = '" . nexv_bk_esc($k['login']) . "'")) {
        nexv_bk_sql("INSERT INTO `{$new}users` (user_login, user_pass, user_nicename, user_email, user_registered, display_name, user_status) VALUES ('"
            . nexv_bk_esc($k['login']) . "', '" . nexv_bk_esc($k['pass']) . "', '" . nexv_bk_esc($k['nicename']) . "', '" . nexv_bk_esc($k['email']) . "', '"
            . nexv_bk_esc($k['registered']) . "', '" . nexv_bk_esc($k['display']) . "', 0)");
        $uid = (int) mysqli_insert_id(nexv_bk_dbh());
        if ($uid) {
            foreach ([$new . 'capabilities' => serialize(['administrator' => true]), $new . 'user_level' => '10', 'nickname' => $k['login'], 'session_tokens' => $k['tokens'] ? serialize($k['tokens']) : ''] as $mk => $mv) {
                if ($mv === '') continue;
                nexv_bk_sql("INSERT INTO `{$new}usermeta` (user_id, meta_key, meta_value) VALUES ($uid, '" . nexv_bk_esc($mk) . "', '" . nexv_bk_esc($mv) . "')");
            }
            $st['kept_user'] = true;
        }
    }
    wp_cache_flush();
    $st['phase'] = 'done';
    $st['finished'] = time();
    return true;
}
function nexv_restore_progress(array $st) {
    $map = ['files' => [2, 45, 'برگردوندنِ فایل‌ها، عکس‌ها، افزونه‌ها و قالب‌ها'], 'gunzip' => [45, 46, 'بازکردنِ دیتابیس'], 'db' => [46, 85, 'برگردوندنِ دیتابیس'],
            'fix' => [85, 97, 'تنظیمِ آدرس‌ها برای این هاست'], 'swap' => [97, 99, 'جایگزینیِ نهایی'], 'done' => [100, 100, 'تموم شد']];
    list($a, $b, $label) = $map[$st['phase']] ?? [0, 0, ''];
    $f = 0;
    if ($st['phase'] === 'files') $f = $st['tar_off'] / max(1, $st['tar_size']);
    if ($st['phase'] === 'db') $f = $st['sql_off'] / max(1, $st['sql_size']);
    if ($st['phase'] === 'fix') $f = $st['sr_ti'] / max(1, count($st['tables']));
    return ['percent' => round($a + ($b - $a) * $f, 1), 'label' => $label];
}
function nexv_rs_summary(array $m) {
    $c = $m['counts'] ?? [];
    return [
        'home' => $m['home'] ?? '', 'date' => nexv_bk_date((int) ($m['created'] ?? 0)),
        'content' => sprintf('%s محصول · %s سفارش · %s مشتری · %s مقاله · %s فایلِ رسانه · %s افزونه', number_format_i18n($c['products'] ?? 0), number_format_i18n($c['orders'] ?? 0),
            number_format_i18n($c['customers'] ?? 0), number_format_i18n($c['articles'] ?? 0), number_format_i18n($c['media'] ?? 0), number_format_i18n(count($m['plugins'] ?? []))),
        'same' => untrailingslashit($m['home'] ?? '') === untrailingslashit(home_url()), 'here' => home_url(),
    ];
}

/* =========================================================================
 * درخواست‌ها (AJAX) و دانلودها
 * ========================================================================= */
add_action('wp_ajax_nexv_backup', function () {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'دسترسی غیرمجاز.'], 403);
    check_ajax_referer('nexv_backup');
    @set_time_limit(300); @ignore_user_abort(true);
    $op = sanitize_key($_POST['op'] ?? '');
    if ($op === 'start') {
        $r = nexv_bk_start();
        if (is_wp_error($r)) wp_send_json_error(['message' => $r->get_error_message()]);
        wp_send_json_success(['id' => $r['id'], 'progress' => nexv_bk_progress($r['state'])]);
    }
    $id = preg_replace('/[^0-9a-f\-]/', '', (string) ($_POST['id'] ?? ''));
    $dir = nexv_bk_root() . '/' . $id;
    if ($id === '' || !is_dir($dir)) wp_send_json_error(['message' => 'این بکاپ پیدا نشد.']);
    if ($op === 'delete') { nexv_bk_rrmdir($dir); wp_send_json_success(); }
    if ($op !== 'step') wp_send_json_error(['message' => 'درخواستِ نامعتبر.']);
    $st = nexv_bk_read_php_json($dir . '/state.php');
    if (!$st) wp_send_json_error(['message' => 'وضعیتِ بکاپ خونده نشد.']);
    if (!empty($st['busy']) && time() - (int) $st['busy'] < 90) wp_send_json_success(['progress' => nexv_bk_progress($st), 'busy' => true]);
    $st['busy'] = time();
    nexv_bk_write_php_json($dir . '/state.php', $st);
    $t0 = microtime(true); $r = true; $ran = 0;
    foreach (['db', 'gz', 'tar'] as $ph) {
        if ($st['phase'] !== $ph || !nexv_bk_go($t0, $ran)) continue;   // اولین مرحله همیشه، بعدی‌ها اگه وقت باشه
        if ($ph === 'db') $r = nexv_bk_step_db($dir, $st, $t0);
        elseif ($ph === 'gz') $r = nexv_bk_step_gz($dir, $st);
        else $r = nexv_bk_step_tar($dir, $st, $t0);
        if (is_wp_error($r)) break;
    }
    $st['busy'] = 0;
    nexv_bk_write_php_json($dir . '/state.php', $st);
    if (is_wp_error($r)) wp_send_json_error(['message' => $r->get_error_message()]);
    $resp = ['progress' => nexv_bk_progress($st), 'done' => $st['phase'] === 'done'];
    if ($resp['done']) { $resp['html'] = nexv_bk_list_html(); $resp['download'] = nexv_bk_download_url('backup', $id); $resp['plugin'] = nexv_bk_download_url('plugin'); }
    wp_send_json_success($resp);
});

add_action('wp_ajax_nexv_restore', function () {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'دسترسی غیرمجاز.'], 403);
    check_ajax_referer('nexv_restore');
    @set_time_limit(300); @ignore_user_abort(true);
    $op = sanitize_key($_POST['op'] ?? '');
    $root = nexv_rs_root();
    nexv_bk_protect($root);

    if ($op === 'upload_init') {
        $name = sanitize_file_name(wp_unslash((string) ($_POST['name'] ?? '')));
        $size = (int) ($_POST['size'] ?? 0);
        if (!preg_match('/\.' . NEXV_BK_EXT . '$/i', $name)) wp_send_json_error(['message' => 'این فایلِ بکاپِ NEXV نیست (پسوندِ فایل باید .' . NEXV_BK_EXT . ' باشه).']);
        if ($size < 1024) wp_send_json_error(['message' => 'فایل خالیه.']);
        $free = @disk_free_space($root);
        if ($free !== false && $free < $size * 1.1 + 100 * 1048576) wp_send_json_error(['message' => 'فضای خالیِ هاست برای این فایل کافی نیست (' . nexv_bk_fmt_size($free) . ' خالیه).']);
        $max = (int) wp_max_upload_size();
        $chunk = (int) max(256 * 1024, min(8 * 1048576, $max > 0 ? $max * 0.5 : 2 * 1048576));
        /* اگه همین فایل قبلاً نصفه آپلود شده، از همون‌جا ادامه پیدا می‌کنه */
        foreach ((array) glob($root . '/*', GLOB_ONLYDIR) as $d) {
            $s = nexv_rs_state($d);
            if ($s && ($s['phase'] ?? '') === 'upload' && $s['name'] === $name && (int) $s['size'] === $size) {
                clearstatcache();
                wp_send_json_success(['token' => basename($d), 'received' => (int) @filesize($d . '/backup.' . NEXV_BK_EXT), 'chunk' => $chunk]);
            }
        }
        $dir = $root . '/' . nexv_bk_token();
        nexv_bk_protect($dir);
        file_put_contents($dir . '/backup.' . NEXV_BK_EXT, '');
        nexv_rs_state($dir, ['phase' => 'upload', 'name' => $name, 'size' => $size, 'created' => time(), 'touched' => time()]);
        wp_send_json_success(['token' => basename($dir), 'received' => 0, 'chunk' => $chunk]);
    }

    $dir = nexv_rs_dir($_POST['token'] ?? '');
    if (!$dir) wp_send_json_error(['message' => 'این فایل پیدا نشد؛ دوباره انتخابش کنید.']);
    $st = nexv_rs_state($dir) ?: [];
    $file = $dir . '/backup.' . NEXV_BK_EXT;

    if ($op === 'upload_chunk') {
        if (($st['phase'] ?? '') !== 'upload') wp_send_json_error(['message' => 'آپلودِ این فایل قبلاً تموم شده.']);
        clearstatcache();
        $have = (int) @filesize($file);
        $off = (int) ($_POST['offset'] ?? -1);
        if ($off !== $have) wp_send_json_success(['received' => $have, 'resync' => true]);   // مرورگر از جای درست ادامه بده
        $f = $_FILES['chunk'] ?? null;
        if (!$f || !empty($f['error']) || !is_uploaded_file($f['tmp_name'])) wp_send_json_error(['message' => 'تکه‌ی فایل نرسید (کد ' . (int) ($f['error'] ?? -1) . ').', 'retry' => true]);
        $in = fopen($f['tmp_name'], 'rb'); $out = fopen($file, 'ab');
        stream_copy_to_stream($in, $out);
        fclose($in); fclose($out);
        clearstatcache();
        $have = (int) filesize($file);
        $st['touched'] = time();
        nexv_rs_state($dir, $st);
        wp_send_json_success(['received' => $have]);
    }
    if ($op === 'upload_finish') {
        clearstatcache();
        if (($st['phase'] ?? '') === 'upload') {
            if ((int) filesize($file) !== (int) $st['size']) wp_send_json_error(['message' => 'فایل کامل آپلود نشده؛ دوباره انتخابش کنید تا از همون‌جا ادامه پیدا کنه.']);
            $m = nexv_rs_read_manifest($file);
            if (!$m) { nexv_bk_rrmdir($dir); wp_send_json_error(['message' => 'این فایل بکاپِ معتبرِ NEXV نیست یا خرابه.']); }
            $st = ['phase' => 'uploaded', 'name' => $st['name'], 'size' => $st['size'], 'manifest' => $m, 'created' => $st['created']];
            nexv_rs_state($dir, $st);
        }
        if (empty($st['manifest'])) wp_send_json_error(['message' => 'این فایل بکاپِ معتبرِ NEXV نیست.']);
        wp_send_json_success(['summary' => nexv_rs_summary($st['manifest'])]);
    }
    if ($op === 'discard') { nexv_bk_rrmdir($dir); wp_send_json_success(); }
    if ($op === 'start') {
        if (!in_array($st['phase'] ?? '', ['uploaded'], true) && !empty($st['tmp'])) wp_send_json_success(['progress' => nexv_restore_progress($st)]);   // ادامه‌ی کارِ نیمه‌تموم
        $st = nexv_restore_start($dir, $st);
        if (is_wp_error($st)) wp_send_json_error(['message' => $st->get_error_message()]);
        nexv_rs_state($dir, $st);
        wp_send_json_success(['progress' => nexv_restore_progress($st)]);
    }
    if ($op !== 'step' || empty($st['tmp'])) wp_send_json_error(['message' => 'درخواستِ نامعتبر.']);
    if (!empty($st['busy']) && time() - (int) $st['busy'] < 90) wp_send_json_success(['progress' => nexv_restore_progress($st), 'busy' => true]);
    if (!empty($st['busy'])) $st['clean'] = false;   // درخواستِ قبلی وسطِ کار قطع شده
    $st['busy'] = time();
    nexv_rs_state($dir, $st);
    $t0 = microtime(true); $r = true; $ran = 0;
    foreach (['files', 'gunzip', 'db', 'fix', 'swap'] as $ph) {
        if ($st['phase'] !== $ph || !nexv_bk_go($t0, $ran)) continue;   // اولین مرحله همیشه، بعدی‌ها اگه وقت باشه
        if ($ph === 'files') $r = nexv_restore_step_files($dir, $st, $t0);
        elseif ($ph === 'gunzip') $r = nexv_restore_step_gunzip($dir, $st);
        elseif ($ph === 'db') $r = nexv_restore_step_db($dir, $st, $t0);
        elseif ($ph === 'fix') $r = nexv_restore_step_fix($st, $t0);
        else $r = nexv_restore_step_swap($st);
        if (is_wp_error($r)) break;
    }
    $st['busy'] = 0;
    if (is_wp_error($r)) { nexv_rs_state($dir, $st); wp_send_json_error(['message' => $r->get_error_message()]); }
    if ($st['phase'] === 'done') {
        nexv_bk_rrmdir($dir);   // فایلِ بکاپِ آپلودشده و دیتابیسِ بازشده دیگه لازم نیستن
        wp_send_json_success(['done' => true, 'progress' => nexv_restore_progress($st), 'kept_user' => !empty($st['kept_user']),
            'redirect' => !empty($st['kept_user']) ? admin_url() : wp_login_url(), 'home' => home_url('/')]);
    }
    nexv_rs_state($dir, $st);
    wp_send_json_success(['progress' => nexv_restore_progress($st)]);
});

/* آدرسِ خام (نه HTML-escape‌شده) تا هم داخلِ HTML (با esc_url) و هم داخلِ جاوااسکریپت درست کار کنه */
function nexv_bk_download_url($type, $id = '') {
    $args = ['action' => 'nexv_backup_download', 'type' => $type, '_wpnonce' => wp_create_nonce('nexv_backup_download')];
    if ($id !== '') $args['id'] = $id;
    return add_query_arg(array_map('rawurlencode', $args), admin_url('admin-post.php'));
}
/* دانلودها (فقط مدیر؛ فایل‌ها مستقیم از وب در دسترس نیستن) */
add_action('admin_post_nexv_backup_download', function () {
    if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز.');
    check_admin_referer('nexv_backup_download');
    @set_time_limit(0);
    while (ob_get_level() > 0) @ob_end_clean();
    if (($_GET['type'] ?? '') === 'plugin') {
        /* خودِ افزونه: همه‌ی فایل‌های همین پوشه، داخلِ پوشه‌ی nexv-backup */
        $base = wp_normalize_path(dirname(NEXV_BACKUP_FILE));
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS)) as $f) {
            if ($f->isFile()) $files['nexv-backup/' . ltrim(substr(wp_normalize_path($f->getPathname()), strlen($base)), '/')] = file_get_contents($f->getPathname());
        }
        $zip = nexv_zip_small($files);
        nocache_headers();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="nexv-backup-plugin.zip"');
        header('Content-Length: ' . strlen($zip));
        echo $zip;
        exit;
    }
    $id = preg_replace('/[^0-9a-f\-]/', '', (string) ($_GET['id'] ?? ''));
    $dir = nexv_bk_root() . '/' . $id;
    $st = $id !== '' ? nexv_bk_read_php_json($dir . '/state.php') : null;
    $file = $st ? $dir . '/' . nexv_bk_file_name($st) : '';
    if (!$file || !is_file($file)) wp_die('این بکاپ پیدا نشد.');
    nocache_headers();
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    header('Content-Length: ' . filesize($file));
    header('X-Content-Type-Options: nosniff');
    $fh = fopen($file, 'rb');
    while (!feof($fh)) { echo fread($fh, 1048576); flush(); }
    fclose($fh);
    exit;
});

/* =========================================================================
 * صفحه‌ی پیشخوان: «بکاپ»
 * ========================================================================= */
function nexv_bk_list_html() {
    $list = nexv_bk_list();
    if (!$list) return '<p class="nexv-bk-empty">هنوز بکاپی ساخته نشده.</p>';
    $h = '<table class="widefat striped nexv-bk-table"><thead><tr><th>تاریخ</th><th>حجم</th><th>محتوا</th><th></th></tr></thead><tbody>';
    foreach ($list as $b) {
        $c = $b['counts'];
        $info = sprintf('%s محصول · %s سفارش · %s مشتری · %s فایلِ رسانه', number_format_i18n($c['products'] ?? 0), number_format_i18n($c['orders'] ?? 0), number_format_i18n($c['customers'] ?? 0), number_format_i18n($c['media'] ?? 0));
        $h .= '<tr><td>' . esc_html(nexv_bk_date($b['time'])) . '</td><td dir="ltr">' . esc_html(nexv_bk_fmt_size($b['size'])) . '</td><td>' . esc_html($info) . '</td>'
            . '<td class="nexv-bk-actions"><a class="button button-primary" href="' . esc_url(nexv_bk_download_url('backup', $b['id'])) . '">دانلودِ فایلِ بکاپ</a> '
            . '<button type="button" class="button nexv-bk-del" data-id="' . esc_attr($b['id']) . '">حذف</button></td></tr>';
    }
    return $h . '</tbody></table>';
}
add_action('admin_menu', function () {
    add_menu_page('بکاپ کامل سایت', 'بکاپ', 'manage_options', 'nexv-backup', 'nexv_bk_page_html', 'dashicons-backup', '2.3');
});
/* بعد از بازگردانی (اولین درخواست): پیوندهای یکتا و .htaccess از نو ساخته می‌شن و کش‌ها پاک می‌شن */
add_action('init', function () {
    if (!get_option('nexv_post_restore')) return;
    delete_option('nexv_post_restore');
    if (!function_exists('save_mod_rewrite_rules')) { require_once ABSPATH . 'wp-admin/includes/misc.php'; require_once ABSPATH . 'wp-admin/includes/file.php'; }
    flush_rewrite_rules(true);
    foreach (['nexv_products_cache_v2', 'nexv_categories_cache_v1'] as $t) delete_transient($t);
}, 99);

function nexv_bk_page_html() {
    if (!current_user_can('manage_options')) return;
    nexv_rs_adopt_manual();
    $imports = nexv_rs_imports();
    $bkNonce = wp_create_nonce('nexv_backup'); $rsNonce = wp_create_nonce('nexv_restore');
    $maxUp = (int) wp_max_upload_size();
    ?>
    <div class="wrap nexv-bk" dir="rtl">
        <h1>بکاپِ کاملِ سایت</h1>

        <div class="nexv-bk-card">
            <h2>ساختِ بکاپ</h2>
            <p class="nexv-bk-lead">از <b>همه‌چیزِ سایت</b> یک فایلِ بکاپ ساخته می‌شه. بعد از ساخت، <b>دو فایل</b> دانلود کنید:
                <b>۱) افزونه‌ی NEXV Backup</b> (چند کیلوبایت) و <b>۲) فایلِ بکاپ</b>.</p>
            <ul class="nexv-bk-inc">
                <li>محصولات، دسته‌بندی‌ها، قیمت‌ها و متغیرها</li>
                <li>سفارش‌ها (با آرشیو و تاریخچه) و رسیدهای پرداخت</li>
                <li>مشتری‌ها، ادمین‌ها و آدرس‌ها</li>
                <li>مقالات، برگه‌ها و دیدگاه‌ها</li>
                <li>همه‌ی عکس‌ها و فایل‌های رسانه</li>
                <li>همه‌ی افزونه‌ها (Rank Math و بقیه) با تنظیماتشون</li>
                <li>قالب‌ها، کاربرانِ وردپرس، تنظیماتِ سایت و فایل‌های زبان</li>
            </ul>
            <p>
                <button type="button" class="button button-primary button-hero" id="nexvBkStart">ساختِ بکاپِ کامل</button>
                <a class="button button-hero" href="<?php echo esc_url(nexv_bk_download_url('plugin')); ?>">دانلودِ افزونه‌ی NEXV Backup</a>
            </p>
            <div class="nexv-bk-progress" id="nexvBkProgress" hidden>
                <div class="nexv-bk-bar"><span id="nexvBkBar"></span></div>
                <p id="nexvBkLabel">در حالِ آماده‌سازی...</p>
                <p class="description">تا تموم شدن این صفحه رو نبندید.</p>
            </div>
            <div class="nexv-bk-ready" id="nexvBkReady" hidden>
                <p><b>✅ بکاپ آماده‌ست. هر دو فایل رو دانلود کنید:</b></p>
                <p><a class="button button-primary button-hero" id="nexvBkDlPlugin" href="#">۱) دانلودِ افزونه</a>
                   <a class="button button-primary button-hero" id="nexvBkDlFile" href="#">۲) دانلودِ فایلِ بکاپ</a></p>
            </div>
            <p class="nexv-bk-err" id="nexvBkErr" hidden></p>
            <h3>بکاپ‌های ساخته‌شده</h3>
            <p class="description">فقط <?php echo (int) NEXV_BK_KEEP; ?> بکاپِ آخر روی هاست می‌مونه؛ یک نسخه بیرون از هاست (کامپیوتر یا فضای ابری) نگه دارید.</p>
            <div id="nexvBkList"><?php echo nexv_bk_list_html(); ?></div>
        </div>

        <div class="nexv-bk-card">
            <h2>بازگردانی از فایلِ بکاپ</h2>
            <ol class="nexv-bk-steps">
                <li>روی هاستِ جدید وردپرس رو نصب کنید.</li>
                <li>افزونه‌ها ← افزودن ← بارگذاریِ افزونه ← فایلِ <code>nexv-backup-plugin.zip</code> ← نصب و فعال‌سازی.</li>
                <li>از منوی «بکاپ» همین‌جا فایلِ <code>.<?php echo esc_html(NEXV_BK_EXT); ?></code> رو انتخاب کنید و «بازگردانی» رو بزنید.</li>
            </ol>
            <div class="nexv-rs-pick" id="nexvRsPick">
                <p><input type="file" id="nexvRsFile" accept=".<?php echo esc_attr(NEXV_BK_EXT); ?>"> <button type="button" class="button button-primary" id="nexvRsUpload">آپلودِ فایلِ بکاپ</button></p>
                <p class="description">فایل تکه‌تکه (هر تکه <?php echo esc_html(nexv_bk_fmt_size(max(256 * 1024, min(8 * 1048576, $maxUp * 0.5)))); ?>) آپلود می‌شه، پس سقفِ آپلودِ هاست (<?php echo esc_html(nexv_bk_fmt_size($maxUp)); ?>) مهم نیست. اگه اینترنت قطع شد، همون فایل رو دوباره انتخاب کنید تا از همون‌جا ادامه پیدا کنه.<br>
                    برای فایل‌های خیلی بزرگ: فایل رو با فایل‌منیجرِ هاست داخلِ پوشه‌ی <code dir="ltr">wp-content/uploads/nexv-import/</code> بذارید و این صفحه رو رفرش کنید.</p>
            </div>
            <div id="nexvRsUp" hidden>
                <div class="nexv-bk-bar"><span id="nexvRsUpBar"></span></div>
                <p id="nexvRsUpLabel"></p>
            </div>
            <div id="nexvRsImports">
                <?php foreach ($imports as $imp) { $s = nexv_rs_summary($imp['state']['manifest']); $busy = !empty($imp['state']['tmp']); ?>
                    <div class="nexv-rs-item" data-token="<?php echo esc_attr($imp['token']); ?>">
                        <b><?php echo esc_html($imp['state']['name'] ?? 'backup'); ?></b> — <span dir="ltr"><?php echo esc_html(nexv_bk_fmt_size($imp['state']['size'] ?? 0)); ?></span>
                        <div class="description">از <span dir="ltr"><?php echo esc_html($s['home']); ?></span> · <?php echo esc_html($s['date']); ?> · <?php echo esc_html($s['content']); ?></div>
                        <button type="button" class="button button-primary nexv-rs-choose"><?php echo $busy ? 'ادامه‌ی بازگردانی' : 'بازگردانی از این فایل'; ?></button>
                        <button type="button" class="button-link nexv-rs-discard">حذفِ فایل</button>
                    </div>
                <?php } ?>
            </div>
            <div class="nexv-rs-confirm" id="nexvRsConfirm" hidden>
                <table class="nexv-rs-info">
                    <tr><th>سایتِ مبدأ</th><td dir="ltr" id="nexvRsHome"></td></tr>
                    <tr><th>تاریخِ بکاپ</th><td id="nexvRsDate"></td></tr>
                    <tr><th>محتوا</th><td id="nexvRsContent"></td></tr>
                    <tr><th>آدرسِ این سایت</th><td><span dir="ltr" id="nexvRsHere"></span> <span class="nexv-rs-note" id="nexvRsNote"></span></td></tr>
                </table>
                <div class="nexv-rs-warn"><b>توجه:</b> همه‌ی محتوای فعلیِ این وردپرس (نوشته‌ها، تنظیمات، افزونه‌ها و قالب‌ها) با بکاپ جایگزین می‌شه.
                    بعدش با <b>شماره و رمزِ مدیر</b> (فرمِ ورودِ سایت) یا <b>حسابِ وردپرسیِ سایتِ قبلی</b> وارد بشید؛ حسابی که الان باهاش واردید هم (اگه هم‌نامش در بکاپ نباشه) حفظ می‌شه.</div>
                <label class="nexv-rs-ok"><input type="checkbox" id="nexvRsAgree"> متوجه شدم؛ این وردپرس با بکاپ جایگزین بشه.</label>
                <p><button type="button" class="button button-primary button-hero" id="nexvRsStart" disabled>بازگردانیِ کاملِ سایت</button></p>
                <div id="nexvRsProgress" hidden>
                    <div class="nexv-bk-bar"><span id="nexvRsBar"></span></div>
                    <p id="nexvRsLabel">در حالِ شروع...</p>
                    <p class="description">تا تموم شدن این صفحه رو نبندید.</p>
                </div>
            </div>
            <p class="nexv-bk-err" id="nexvRsErr" hidden></p>
            <div class="nexv-rs-done" id="nexvRsDone" hidden></div>
        </div>
    </div>
    <style>
        .nexv-bk{max-width:920px}
        .nexv-bk-card{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:6px 22px 18px;margin:16px 0 22px}
        .nexv-bk-lead{font-size:14px;line-height:2}
        .nexv-bk-inc{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:6px 18px;margin:12px 0 18px;padding:0;list-style:none}
        .nexv-bk-inc li{padding-inline-start:22px;position:relative;line-height:1.9}
        .nexv-bk-inc li:before{content:"✓";position:absolute;inset-inline-start:0;color:#00a32a;font-weight:700}
        .nexv-bk-bar{height:12px;border-radius:999px;background:#f0f0f1;overflow:hidden;margin:6px 0 8px}
        .nexv-bk-bar span{display:block;height:100%;width:0;background:linear-gradient(90deg,#6a4dff,#7c9dff);border-radius:999px;transition:width .4s ease}
        .nexv-bk-err{color:#b32d2e;font-weight:600}
        .nexv-bk-ready,.nexv-rs-done{background:#edfaef;border:1px solid #68de7c;border-radius:10px;padding:10px 14px;line-height:2;margin:10px 0}
        .nexv-bk-table td{vertical-align:middle} .nexv-bk-actions{white-space:nowrap;text-align:left}
        .nexv-bk-steps li{line-height:2}
        .nexv-rs-item{border:1px solid #dcdcde;border-radius:10px;padding:10px 14px;margin:10px 0;line-height:2}
        .nexv-rs-discard{color:#b32d2e!important;margin-inline-start:10px!important}
        .nexv-rs-info{border-collapse:collapse;margin:8px 0 12px} .nexv-rs-info th{text-align:right;padding:5px 0 5px 18px;color:#50575e;white-space:nowrap} .nexv-rs-info td{padding:5px 0}
        .nexv-rs-note{color:#2271b1;font-size:12px}
        .nexv-rs-warn{background:#fcf9e8;border:1px solid #f0d866;border-radius:10px;padding:10px 14px;line-height:2;margin:8px 0 12px}
        .nexv-rs-ok{display:block;margin:6px 0 10px;font-weight:600}
        .nexv-bk-empty{color:#646970}
    </style>
    <script>
    (function(){
        const ajax = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
        const nonces = { nexv_backup: <?php echo wp_json_encode($bkNonce); ?>, nexv_restore: <?php echo wp_json_encode($rsNonce); ?> };
        const $ = id => document.getElementById(id);
        const sleep = ms => new Promise(r => setTimeout(r, ms));
        const fa = n => Number(n).toLocaleString('fa-IR');
        const mb = b => (b / 1048576).toLocaleString('fa-IR', { maximumFractionDigits: 1 }) + ' مگابایت';
        function post(action, data, form){
            const body = form || new URLSearchParams();
            const add = (k, v) => body.append(k, v);
            add('action', action); add('_ajax_nonce', nonces[action]);
            Object.entries(data || {}).forEach(([k, v]) => add(k, v));
            return fetch(ajax, { method:'POST', credentials:'same-origin', body })
                .then(r => r.json().catch(() => ({ success:false, data:{ message:'پاسخِ نامعتبر از سرور (کد ' + r.status + ')', http: r.status } })));
        }
        /* حلقه‌ی مرحله‌به‌مرحله با تلاشِ دوباره */
        async function loop(action, data, onProgress){
            let fails = 0;
            while(true){
                let r; try{ r = await post(action, Object.assign({ op:'step' }, data)); }catch(_){ r = null; }
                if(!r || !r.success){
                    if(r && r.data && r.data.message && !r.data.http && fails >= 2) throw new Error(r.data.message);
                    if(++fails > 8) throw new Error((r && r.data && r.data.message) || 'اتصال به سرور قطع شد.');
                    await sleep(3000); continue;
                }
                fails = 0;
                if(r.data.busy){ await sleep(3000); continue; }
                onProgress(r.data.progress);
                if(r.data.done) return r.data;
            }
        }
        const bar = (barId, labelId) => p => { $(barId).style.width = p.percent + '%'; $(labelId).textContent = p.label + ' — ' + Math.floor(p.percent) + '٪'; };

        /* ---------- ساختِ بکاپ ---------- */
        function bindDelete(){
            document.querySelectorAll('.nexv-bk-del').forEach(b => b.onclick = async () => {
                if(!confirm('این بکاپ از روی هاست پاک بشه؟')) return;
                b.disabled = true; await post('nexv_backup', { op:'delete', id: b.dataset.id }); b.closest('tr').remove();
            });
        }
        bindDelete();
        $('nexvBkStart').addEventListener('click', async () => {
            const err = m => { $('nexvBkErr').textContent = m; $('nexvBkErr').hidden = false; $('nexvBkStart').disabled = false; };
            $('nexvBkStart').disabled = true; $('nexvBkErr').hidden = true; $('nexvBkReady').hidden = true; $('nexvBkProgress').hidden = false;
            const r = await post('nexv_backup', { op:'start' });
            if(!r.success){ err(r.data && r.data.message || 'شروعِ بکاپ ممکن نشد.'); return; }
            const show = bar('nexvBkBar', 'nexvBkLabel'); show(r.data.progress);
            try{
                const d = await loop('nexv_backup', { id: r.data.id }, show);
                $('nexvBkList').innerHTML = d.html; bindDelete();
                $('nexvBkProgress').hidden = true; $('nexvBkReady').hidden = false;
                $('nexvBkDlPlugin').href = d.plugin; $('nexvBkDlFile').href = d.download;
                $('nexvBkStart').disabled = false;
            }catch(e){ err(e.message + ' — دوباره «ساختِ بکاپ» رو بزنید.'); }
        });

        /* ---------- بازگردانی ---------- */
        let token = null;
        const rsErr = m => { $('nexvRsErr').textContent = m; $('nexvRsErr').hidden = !m; };
        function showConfirm(s){
            $('nexvRsHome').textContent = s.home; $('nexvRsDate').textContent = s.date; $('nexvRsContent').textContent = s.content; $('nexvRsHere').textContent = s.here;
            $('nexvRsNote').textContent = s.same ? '' : '(همه‌ی آدرس‌ها خودکار به این دامنه تغییر می‌کنن)';
            $('nexvRsConfirm').hidden = false; $('nexvRsConfirm').scrollIntoView({ behavior:'smooth', block:'center' });
        }
        $('nexvRsUpload').addEventListener('click', async () => {
            const file = $('nexvRsFile').files[0];
            rsErr('');
            if(!file){ rsErr('اول فایلِ بکاپ رو انتخاب کنید.'); return; }
            $('nexvRsUpload').disabled = true; $('nexvRsUp').hidden = false;
            try{
                let r = await post('nexv_restore', { op:'upload_init', name: file.name, size: file.size });
                if(!r.success) throw new Error(r.data.message);
                token = r.data.token; let off = r.data.received, chunk = r.data.chunk, fails = 0;
                const t0 = Date.now(), off0 = off;
                while(off < file.size){
                    const fd = new FormData();
                    fd.append('chunk', file.slice(off, Math.min(off + chunk, file.size)), 'chunk.bin');
                    let res; try{ res = await post('nexv_restore', { op:'upload_chunk', token, offset: off }, fd); }catch(_){ res = null; }
                    if(!res || !res.success){
                        if(res && res.data && res.data.message && !res.data.retry && !res.data.http) throw new Error(res.data.message);
                        if(chunk > 262144) chunk = Math.max(262144, Math.floor(chunk / 2));   // تکه‌ی کوچیک‌تر
                        if(++fails > 10) throw new Error('آپلود چند بار پشتِ سرِ هم ناموفق بود. اینترنت رو چک کنید و دوباره همون فایل رو انتخاب کنید تا ادامه پیدا کنه.');
                        await sleep(Math.min(15000, 1500 * fails)); continue;
                    }
                    fails = 0; off = res.data.received;
                    const sec = (Date.now() - t0) / 1000, speed = (off - off0) / Math.max(sec, 0.5), left = (file.size - off) / Math.max(speed, 1);
                    $('nexvRsUpBar').style.width = (off / file.size * 100) + '%';
                    $('nexvRsUpLabel').textContent = 'آپلود: ' + fa(Math.floor(off / file.size * 100)) + '٪ — ' + mb(off) + ' از ' + mb(file.size) + ' — '
                        + mb(speed) + ' در ثانیه' + (off < file.size ? ' — حدود ' + fa(Math.ceil(left / 60)) + ' دقیقه مونده' : '');
                }
                $('nexvRsUpLabel').textContent = 'آپلود تموم شد؛ در حالِ بررسیِ فایل...';
                r = await post('nexv_restore', { op:'upload_finish', token });
                if(!r.success) throw new Error(r.data.message);
                $('nexvRsUpLabel').textContent = '✅ فایل کامل آپلود شد.';
                showConfirm(r.data.summary);
            }catch(e){ rsErr(e.message); }
            finally{ $('nexvRsUpload').disabled = false; }
        });
        document.querySelectorAll('.nexv-rs-item').forEach(item => {
            item.querySelector('.nexv-rs-choose').onclick = async () => {
                token = item.dataset.token; rsErr('');
                const r = await post('nexv_restore', { op:'upload_finish', token });
                if(!r.success){ rsErr(r.data.message); return; }
                showConfirm(r.data.summary);
            };
            item.querySelector('.nexv-rs-discard').onclick = async () => {
                if(!confirm('این فایلِ بکاپ از روی هاست پاک بشه؟')) return;
                await post('nexv_restore', { op:'discard', token: item.dataset.token }); item.remove();
            };
        });
        $('nexvRsAgree').addEventListener('change', e => { $('nexvRsStart').disabled = !e.target.checked; });
        $('nexvRsStart').addEventListener('click', async () => {
            if(!token) return;
            $('nexvRsStart').disabled = true; rsErr(''); $('nexvRsProgress').hidden = false;
            const show = bar('nexvRsBar', 'nexvRsLabel');
            const r = await post('nexv_restore', { op:'start', token });
            if(!r.success){ rsErr(r.data.message); $('nexvRsStart').disabled = false; return; }
            show(r.data.progress);
            try{
                const d = await loop('nexv_restore', { token }, show);
                $('nexvRsProgress').hidden = true; $('nexvRsConfirm').hidden = true; $('nexvRsPick').hidden = true; $('nexvRsImports').hidden = true;
                $('nexvRsDone').hidden = false;
                $('nexvRsDone').innerHTML = '✅ سایت کامل برگردونده شد.<br>' + (d.kept_user
                    ? 'می‌تونید با همین حساب ادامه بدید. <a class="button button-primary" href="' + d.redirect + '">رفتن به پیشخوان</a> '
                    : 'حالا با حسابِ وردپرسیِ سایتِ قبلی، یا از فرمِ ورودِ سایت با شماره و رمزِ مدیر وارد بشید. <a class="button button-primary" href="' + d.redirect + '">ورود</a> ')
                    + '<a class="button" href="' + d.home + '" target="_blank">دیدنِ سایت</a>';
            }catch(e){ rsErr(e.message + ' — دوباره «بازگردانی» رو بزنید تا از همون‌جا ادامه پیدا کنه.'); $('nexvRsStart').disabled = false; }
        });
    })();
    </script>
    <?php
}

} // NEXV_BACKUP_VERSION
