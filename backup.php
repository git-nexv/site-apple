<?php
if (!defined('ABSPATH')) { exit; }

/* =========================================================================
 * بکاپِ کاملِ سایت به‌شکلِ «قالب» + بازگردانی روی یک وردپرسِ تازه
 *
 * پیشخوان ← «بکاپ»: یک فایلِ zip می‌سازه که خودش یک قالبِ کاملِ وردپرسه (همین قالب) و
 * داخلش یک بسته‌ی بازگردانی هست:
 *   - db.sql.php   : کلِ دیتابیسِ وردپرس (محصولات، دسته‌ها، مقالات، برگه‌ها، کاربران،
 *                    سفارش‌های وردپرسی، تنظیماتِ سایت و همه‌ی افزونه‌ها مثل Rank Math)
 *   - files.tar    : عکس‌ها و فایل‌های رسانه، داده‌های NEXV (سفارش‌ها، مشتری‌ها، رسیدها)،
 *                    همه‌ی افزونه‌ها، قالب‌های دیگه و فایل‌های زبان
 *   - manifest.php : مشخصاتِ بکاپ
 * روی هاستِ جدید: وردپرس نصب ← نمایش ← پوسته‌ها ← بارگذاری همین zip ← فعال‌سازی ←
 * صفحه‌ی «بازگردانی بکاپ» باز می‌شه و با یک دکمه همه‌چیز برمی‌گرده (حتی با دامنه یا
 * پیشوندِ جدولِ متفاوت).
 *
 * همه‌ی مراحل چندتکه (هر درخواست حداکثر چند ثانیه) انجام می‌شن تا محدودیتِ زمانِ هاست
 * کار رو نصفه نذاره؛ اگه یک درخواست قطع بشه، از آخرین نقطه‌ی سالم ادامه پیدا می‌کنه.
 * ========================================================================= */

if (!defined('NEXV_BK_BUDGET')) define('NEXV_BK_BUDGET', 10);   // ثانیه‌ی کار در هر درخواست
define('NEXV_BK_KEEP', 3);                 // چندتا بکاپِ آخر روی هاست نگه داشته بشه
define('NEXV_BK_STMT_MAX', 512 * 1024);    // حداکثر طولِ هر دستورِ INSERT (برای max_allowed_packet)

/* ---------- ابزارهای عمومی ---------- */
function nexv_bk_root() {
    $u = wp_upload_dir(null, false);
    return trailingslashit($u['basedir']) . 'nexv-backups';
}
function nexv_bk_protect($dir) {
    if (!is_dir($dir)) wp_mkdir_p($dir);
    if (!file_exists($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\nOptions -Indexes\n");
    if (!file_exists($dir . '/index.php')) @file_put_contents($dir . '/index.php', "<?php // silence\n");
}
function nexv_bk_rrmdir($dir) {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { ($f->isDir() && !$f->isLink()) ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
    @rmdir($dir);
}
/* فایل‌های حساس با <?php exit; ?> شروع می‌شن تا اگه کسی آدرسشون رو مستقیم باز کرد، چیزی نبینه */
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
function nexv_bk_fmt_size($b) { return function_exists('size_format') ? size_format((float) $b, 1) : round($b / 1048576, 1) . ' MB'; }
function nexv_bk_date($ts) {
    return (function_exists('nexv_jalali_pretty') && function_exists('nexv_gregorian_to_jalali')) ? nexv_jalali_pretty($ts) . ' — ' . wp_date('H:i', $ts) : wp_date('Y/m/d H:i', $ts);
}
function nexv_bk_dbh() { global $wpdb; return $wpdb->dbh; }
function nexv_bk_sql($q) {
    $r = mysqli_query(nexv_bk_dbh(), $q);
    return $r === false ? false : $r;
}
function nexv_bk_sql_error() { return mysqli_error(nexv_bk_dbh()); }
function nexv_bk_esc($v) { return mysqli_real_escape_string(nexv_bk_dbh(), (string) $v); }
/* مقدارِ رشته‌ای برای فایلِ SQL (مستقل از اتصال؛ مثلِ mysqldump) */
function nexv_bk_quote($v) {
    return "'" . strtr((string) $v, ["\\" => "\\\\", "\0" => "\\0", "\n" => "\\n", "\r" => "\\r", "'" => "\\'", '"' => '\\"', "\x1a" => "\\Z"]) . "'";
}
function nexv_bk_time_ok($t0) { return (microtime(true) - $t0) < NEXV_BK_BUDGET; }
/* اولین واحدِ کار در هر درخواست همیشه انجام می‌شه (پیشرفت تضمینی)، بقیه تا وقتی زمان باشه */
function nexv_bk_go($t0, &$i) { return $i++ === 0 || nexv_bk_time_ok($t0); }

/* ---------- tar (ساده، بدونِ وابستگی؛ نام‌های بلند با هدرِ PAX) ---------- */
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
function nexv_tar_add($out, $abs, $name) {
    clearstatcache(true, $abs);
    if (!is_file($abs) || !is_readable($abs)) return 0;
    $size = (int) @filesize($abs);
    $in = @fopen($abs, 'rb');
    if (!$in) return 0;
    $hname = $name;
    if (strlen($name) > 100) { fwrite($out, nexv_tar_pax($name)); $hname = 'long/' . md5($name); }
    fwrite($out, nexv_tar_header($hname, $size, (int) @filemtime($abs)));
    $copied = $size > 0 ? (int) stream_copy_to_stream($in, $out, $size) : 0;
    fclose($in);
    if ($copied < $size) fwrite($out, str_repeat("\0", $size - $copied));   // فایل در همین فاصله کوچیک شده
    $pad = (512 - $size % 512) % 512;
    if ($pad) fwrite($out, str_repeat("\0", $pad));
    return 1;
}
/* هدرِ بعدی؛ null یعنی آخرِ آرشیو. false یعنی آرشیو خرابه. */
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

/* ---------- zip (بدونِ ZipArchive؛ store/deflate؛ قابلِ ادامه در چند درخواست) ---------- */
function nexv_zip_dos($ts) {
    $d = getdate($ts);
    return [(($d['hours'] << 11) | ($d['minutes'] << 5) | intdiv($d['seconds'], 2)), ((max(0, $d['year'] - 1980) << 9) | ($d['mon'] << 5) | $d['mday'])];
}
function nexv_zip_local($name, $method, $crc, $csize, $usize, $mtime) {
    list($t, $d) = nexv_zip_dos($mtime);
    return pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, $method, $t, $d, $crc, $csize, $usize, strlen($name), 0) . $name;
}
function nexv_zip_central(array $e) {
    list($t, $d) = nexv_zip_dos($e['mt']);
    return pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 0x031E, 20, 0x0800, $e['m'], $t, $d, $e['crc'], $e['cs'], $e['us'], strlen($e['n']), 0, 0, 0, 0, (0100644 << 16), $e['off']) . $e['n'];
}
function nexv_crc_int($raw4) { return unpack('N', $raw4)[1]; }

/* =========================================================================
 * ساختِ بکاپ (سایتِ فعلی)
 * ========================================================================= */
function nexv_bk_sources() {
    $u = wp_upload_dir(null, false);
    $src = [['dir' => $u['basedir'], 'as' => 'uploads']];
    $src[] = ['dir' => WP_PLUGIN_DIR, 'as' => 'plugins'];
    if (defined('WPMU_PLUGIN_DIR') && is_dir(WPMU_PLUGIN_DIR)) $src[] = ['dir' => WPMU_PLUGIN_DIR, 'as' => 'mu-plugins'];
    if (defined('WP_LANG_DIR') && is_dir(WP_LANG_DIR)) $src[] = ['dir' => WP_LANG_DIR, 'as' => 'languages'];
    $src[] = ['dir' => get_theme_root(), 'as' => 'themes'];
    /* داده‌های NEXV اگه (به هر دلیلی) بیرون از پوشه‌ی uploads باشن */
    if (function_exists('nexv_orders_db_path')) {
        $nd = dirname(nexv_orders_db_path());
        if (is_dir($nd) && strpos(wp_normalize_path($nd), wp_normalize_path($u['basedir'])) !== 0) $src[] = ['dir' => $nd, 'as' => 'nexv-data'];
    }
    return $src;
}
function nexv_bk_skip($as, $rel, $isDir) {
    $first = strtok($rel, '/');
    if ($as === 'uploads') {
        if (in_array($first, ['nexv-backups', 'cache', 'ai1wm-backups', 'updraft', 'wpvividbackups', 'wp-staging'], true) || strpos($first, 'backwpup') === 0) return true;
        if (!$isDir && preg_match('#^nexv-data/backups/backup-[^/]+\.json$#', $rel)) return true;   // نسخه‌های چرخشی؛ روزانه‌ها و latest می‌مونن
    }
    if ($as === 'themes' && $first === get_stylesheet()) return true;   // همین قالب خودش ریشه‌ی فایل zip ـه
    if ($first === '.git' || $first === 'node_modules' || strpos($rel, '/.git/') !== false) return true;
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
            $rel = ltrim(substr($path, strlen($base)), '/');
            fwrite($out, $path . "\t" . $s['as'] . '/' . $rel . "\n");
            $count++; $bytes += $f->getSize();
        }
    }
    fclose($out);
    return [$count, $bytes];
}
function nexv_bk_manifest_counts() {
    $c = ['products' => 0, 'articles' => 0, 'pages' => 0, 'media' => 0, 'orders' => 0, 'customers' => 0, 'users' => 0];
    foreach (['nexv_product' => 'products', 'nexv_article' => 'articles', 'page' => 'pages'] as $pt => $k) {
        $n = wp_count_posts($pt); $c[$k] = (int) ($n->publish ?? 0) + (int) ($n->draft ?? 0) + (int) ($n->private ?? 0);
    }
    $c['media'] = (int) array_sum((array) wp_count_attachments());
    $c['users'] = (int) (count_users()['total_users'] ?? 0);
    if (function_exists('nexv_orders_read')) {
        $d = nexv_orders_read();
        if (is_array($d)) {
            $c['orders'] = count((array) ($d['orders'] ?? []));
            $c['customers'] = count(array_filter((array) ($d['users'] ?? []), function ($u) { return empty($u['is_admin']); }));
        }
    }
    return $c;
}

function nexv_bk_start() {
    global $wpdb;
    @set_time_limit(300);
    $root = nexv_bk_root();
    nexv_bk_protect($root);
    foreach ((array) glob($root . '/*', GLOB_ONLYDIR) as $d) {   // کارِ نیمه‌تمامِ قبلی
        $s = nexv_bk_read_php_json($d . '/state.php');
        if ($s && ($s['phase'] ?? '') !== 'done') nexv_bk_rrmdir($d);
    }
    $id = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(12));
    $dir = $root . '/' . $id;
    nexv_bk_protect($dir);
    list($count, $bytes) = nexv_bk_scan($dir . '/files.list');

    $tables = [];
    $dbBytes = 0;
    $like = $wpdb->esc_like($wpdb->prefix) . '%';
    foreach ((array) $wpdb->get_results($wpdb->prepare('SHOW TABLE STATUS LIKE %s', $like), ARRAY_A) as $t) {
        if (!empty($t['Comment']) && strtoupper($t['Comment']) === 'VIEW') continue;
        $tables[] = $t['Name'];
        $dbBytes += (int) $t['Data_length'];
    }
    $free = @disk_free_space($dir);
    $need = ($bytes + $dbBytes) * 2.1 + 50 * 1048576;
    if ($free !== false && $free < $need) {
        nexv_bk_rrmdir($dir);
        return new WP_Error('space', 'فضای خالیِ هاست کافی نیست: حدود ' . nexv_bk_fmt_size($need) . ' لازمه ولی ' . nexv_bk_fmt_size($free) . ' خالیه. چندتا بکاپِ قدیمی رو پاک کنید یا فضای هاست رو بیشتر کنید.');
    }
    if ($bytes + $dbBytes > 3.8 * 1073741824) {
        nexv_bk_rrmdir($dir);
        return new WP_Error('size', 'حجمِ سایت بیشتر از ۳٫۸ گیگابایته و در یک فایلِ قالب جا نمی‌شه.');
    }
    $token = bin2hex(random_bytes(12));
    $active = (array) get_option('active_plugins', []);
    $plugins = [];
    if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
    foreach (get_plugins() as $file => $p) $plugins[] = ['file' => $file, 'name' => $p['Name'], 'version' => $p['Version'], 'active' => in_array($file, $active, true)];
    $manifest = [
        'format' => 'nexv-full-backup', 'version' => 1, 'created' => time(),
        'home' => home_url(), 'siteurl' => site_url(), 'prefix' => $wpdb->prefix, 'abspath' => ABSPATH,
        'theme' => get_stylesheet(), 'template' => get_template(), 'wp' => get_bloginfo('version'), 'php' => PHP_VERSION,
        'blogname' => get_bloginfo('name'), 'tables' => $tables, 'files' => $count, 'files_bytes' => $bytes,
        'counts' => nexv_bk_manifest_counts(), 'plugins' => $plugins,
    ];
    nexv_bk_write_php_json($dir . '/manifest.php', $manifest);
    file_put_contents($dir . '/db.sql.php', "<?php exit; ?>\n");
    $st = [
        'phase' => 'db', 'token' => $token, 'started' => time(), 'tables' => $tables, 'ti' => 0, 'row' => 0, 'created' => false,
        'sql_size' => filesize($dir . '/db.sql.php'), 'files' => $count, 'files_bytes' => $bytes, 'fi' => 0, 'list_off' => 0, 'tar_size' => 0,
        'db_bytes' => $dbBytes, 'rows_done' => 0,
    ];
    nexv_bk_write_php_json($dir . '/state.php', $st);
    return ['id' => $id, 'state' => $st];
}

/* مرحله‌ی دیتابیس: هر جدول با CREATE و دسته‌های INSERT (هر دستور در یک خط) */
function nexv_bk_step_db($dir, array &$st, $t0) {
    global $wpdb;
    $file = $dir . '/db.sql.php';
    $fh = fopen($file, 'r+b');
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
        $st['row'] += $n; $st['rows_done'] += $n;
        if ($n < 1000) { $st['ti']++; $st['created'] = false; $st['row'] = 0; }
        fflush($fh);
        $st['sql_size'] = ftell($fh);
    }
    fclose($fh);
    if ($st['ti'] >= count($st['tables'])) $st['phase'] = 'files';
    return true;
}

/* مرحله‌ی فایل‌ها: اضافه‌کردن به files.tar از روی لیست */
function nexv_bk_step_files($dir, array &$st, $t0) {
    $tar = fopen($dir . '/files.tar', 'c+b');
    ftruncate($tar, $st['tar_size']);
    fseek($tar, 0, SEEK_END);
    $list = fopen($dir . '/files.list', 'rb');
    fseek($list, $st['list_off']);
    $go = 0;
    while (nexv_bk_go($t0, $go) && ($line = fgets($list)) !== false) {
        $line = rtrim($line, "\n");
        if ($line === '') continue;
        list($abs, $name) = explode("\t", $line, 2);
        nexv_tar_add($tar, $abs, $name);
        $st['fi']++;
        $st['list_off'] = ftell($list);
        $st['tar_size'] = ftell($tar);
    }
    $eof = feof($list) || fgets($list) === false;
    fclose($list);
    if ($eof) {
        fwrite($tar, str_repeat("\0", 1024));   // پایانِ آرشیو
        $st['tar_size'] = ftell($tar);
        $st['phase'] = 'zip';
        $st['zi'] = -1;
    }
    fclose($tar);
    return true;
}

/* لیستِ ورودی‌های فایلِ zip: فایل‌های همین قالب + بسته‌ی بازگردانی */
function nexv_bk_zip_plan($dir, array $st) {
    $root = get_stylesheet() . '/';
    $plan = [];
    $base = rtrim(wp_normalize_path(get_stylesheet_directory()), '/');
    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
            function ($f) use ($base) {
                if ($f->isLink()) return false;
                $rel = ltrim(substr(wp_normalize_path($f->getPathname()), strlen($base)), '/');
                $first = strtok($rel, '/');
                return !in_array($first, ['.git', '.github', 'node_modules'], true) && strpos($first, 'nexv-restore-') !== 0 && $first !== '.DS_Store';
            }
        )
    );
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $abs = wp_normalize_path($f->getPathname());
        $plan[] = ['abs' => $abs, 'name' => $root . ltrim(substr($abs, strlen($base)), '/'), 'm' => $f->getSize() < 8 * 1048576 ? 8 : 0];
    }
    $p = $root . 'nexv-restore-' . $st['token'] . '/';
    $plan[] = ['abs' => $dir . '/.htaccess', 'name' => $p . '.htaccess', 'm' => 8];
    $plan[] = ['abs' => $dir . '/index.php', 'name' => $p . 'index.php', 'm' => 8];
    $plan[] = ['abs' => $dir . '/manifest.php', 'name' => $p . 'manifest.php', 'm' => 8];
    $plan[] = ['abs' => $dir . '/db.sql.php', 'name' => $p . 'db.sql.php', 'm' => 'stream'];
    $plan[] = ['abs' => $dir . '/files.tar', 'name' => $p . 'files.tar', 'm' => 'big'];
    return $plan;
}
function nexv_bk_zip_name($st) { return 'nexv-backup-' . gmdate('Ymd-Hi', $st['started']) . '.zip'; }
function nexv_bk_step_zip($dir, array &$st, $t0) {
    $zipFile = $dir . '/' . nexv_bk_zip_name($st);
    if ($st['zi'] === -1) {
        $st['plan'] = nexv_bk_zip_plan($dir, $st);
        $st['entries'] = []; $st['zi'] = 0; $st['zip_size'] = 0; $st['copy_off'] = null;
        file_put_contents($zipFile, '');
    }
    $z = fopen($zipFile, 'r+b');
    ftruncate($z, $st['zip_size']);
    fseek($z, 0, SEEK_END);
    $go = 0;
    while ($st['zi'] < count($st['plan']) && nexv_bk_go($t0, $go)) {
        $e = $st['plan'][$st['zi']];
        $mt = (int) @filemtime($e['abs']) ?: time();
        if ($e['m'] === 'big') {
            /* فایلِ بزرگ (files.tar): بدونِ فشرده‌سازی، کپی در چند درخواست */
            if ($st['copy_off'] === null) {
                $size = filesize($e['abs']);
                $crc = hexdec(hash_file('crc32b', $e['abs']));
                $off = ftell($z);
                fwrite($z, nexv_zip_local($e['name'], 0, $crc, $size, $size, $mt));
                $st['entries'][] = ['n' => $e['name'], 'm' => 0, 'crc' => $crc, 'cs' => $size, 'us' => $size, 'off' => $off, 'mt' => $mt];
                $st['copy_off'] = 0; $st['zip_size'] = ftell($z);
            }
            $in = fopen($e['abs'], 'rb');
            fseek($in, $st['copy_off']);
            $total = filesize($e['abs']);
            $goc = 0;
            while ($st['copy_off'] < $total && nexv_bk_go($t0, $goc)) {
                $c = stream_copy_to_stream($in, $z, min(8 * 1048576, $total - $st['copy_off']));
                if (!$c) break;
                $st['copy_off'] += $c;
                $st['zip_size'] = ftell($z);
            }
            fclose($in);
            if ($st['copy_off'] < $total) break;
            $st['copy_off'] = null;
        } elseif ($e['m'] === 'stream') {
            /* فایلِ متنیِ بزرگ (دیتابیس): فشرده‌سازیِ تکه‌تکه به یک فایلِ موقت، بعد کپی */
            $tmp = $dir . '/db.deflate';
            $in = fopen($e['abs'], 'rb'); $out = fopen($tmp, 'wb');
            $ctx = deflate_init(ZLIB_ENCODING_RAW, ['level' => 6]); $hc = hash_init('crc32b'); $us = 0;
            while (!feof($in)) {
                $chunk = fread($in, 1048576);
                if ($chunk === '' || $chunk === false) break;
                hash_update($hc, $chunk); $us += strlen($chunk);
                fwrite($out, deflate_add($ctx, $chunk, ZLIB_NO_FLUSH));
            }
            fwrite($out, deflate_add($ctx, '', ZLIB_FINISH));
            fclose($in); fclose($out);
            $crc = nexv_crc_int(hash_final($hc, true)); $cs = filesize($tmp);
            $off = ftell($z);
            fwrite($z, nexv_zip_local($e['name'], 8, $crc, $cs, $us, $mt));
            $in = fopen($tmp, 'rb'); stream_copy_to_stream($in, $z); fclose($in); @unlink($tmp);
            $st['entries'][] = ['n' => $e['name'], 'm' => 8, 'crc' => $crc, 'cs' => $cs, 'us' => $us, 'off' => $off, 'mt' => $mt];
        } else {
            $data = (string) @file_get_contents($e['abs']);
            $crc = nexv_crc_int(hash('crc32b', $data, true));
            $method = $e['m'];
            $payload = $method === 8 ? gzdeflate($data, 6) : $data;
            if ($method === 8 && strlen($payload) >= strlen($data)) { $method = 0; $payload = $data; }
            $off = ftell($z);
            fwrite($z, nexv_zip_local($e['name'], $method, $crc, strlen($payload), strlen($data), $mt) . $payload);
            $st['entries'][] = ['n' => $e['name'], 'm' => $method, 'crc' => $crc, 'cs' => strlen($payload), 'us' => strlen($data), 'off' => $off, 'mt' => $mt];
        }
        $st['zi']++;
        $st['zip_size'] = ftell($z);
    }
    if ($st['zi'] >= count($st['plan'])) {
        $cdOff = ftell($z); $cd = '';
        foreach ($st['entries'] as $en) $cd .= nexv_zip_central($en);
        fwrite($z, $cd . pack('VvvvvVVv', 0x06054b50, 0, 0, count($st['entries']), count($st['entries']), strlen($cd), $cdOff, 0));
        $st['zip_size'] = ftell($z);
        $st['phase'] = 'done';
        $st['finished'] = time();
        unset($st['plan'], $st['entries']);
    }
    fclose($z);
    if ($st['phase'] === 'done') {
        foreach (['files.tar', 'db.sql.php', 'files.list', 'db.deflate'] as $f) @unlink($dir . '/' . $f);
        /* فقط چندتا بکاپِ آخر نگه داشته می‌شن */
        $all = nexv_bk_list();
        foreach (array_slice($all, NEXV_BK_KEEP) as $old) nexv_bk_rrmdir(nexv_bk_root() . '/' . $old['id']);
    }
    return true;
}
function nexv_bk_list() {
    $out = [];
    foreach ((array) glob(nexv_bk_root() . '/*', GLOB_ONLYDIR) as $d) {
        $st = nexv_bk_read_php_json($d . '/state.php');
        if (!$st || ($st['phase'] ?? '') !== 'done') continue;
        $zip = $d . '/' . nexv_bk_zip_name($st);
        if (!file_exists($zip)) continue;
        $m = nexv_bk_read_php_json($d . '/manifest.php') ?: [];
        $out[] = ['id' => basename($d), 'file' => basename($zip), 'size' => filesize($zip), 'time' => (int) ($st['finished'] ?? $st['started']), 'counts' => $m['counts'] ?? []];
    }
    usort($out, function ($a, $b) { return $b['time'] <=> $a['time']; });
    return $out;
}
function nexv_bk_progress(array $st) {
    $p = 0; $label = '';
    if ($st['phase'] === 'db') { $p = 3 + 27 * ($st['ti'] / max(1, count($st['tables']))); $label = 'دیتابیس (' . ($st['ti'] + 1) . ' از ' . count($st['tables']) . ' جدول)'; }
    elseif ($st['phase'] === 'files') { $p = 30 + 50 * ($st['fi'] / max(1, $st['files'])); $label = 'فایل‌ها (' . number_format_i18n($st['fi']) . ' از ' . number_format_i18n($st['files']) . ')'; }
    elseif ($st['phase'] === 'zip') { $p = 80 + 19 * (max(0, $st['zi']) / max(1, count($st['plan'] ?? [1]))); $label = 'ساختِ فایلِ قالب'; }
    elseif ($st['phase'] === 'done') { $p = 100; $label = 'تموم شد'; }
    return ['percent' => round($p, 1), 'label' => $label];
}

add_action('wp_ajax_nexv_backup', function () {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'دسترسی غیرمجاز.'], 403);
    check_ajax_referer('nexv_backup');
    @set_time_limit(300);
    @ignore_user_abort(true);
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
    $t0 = microtime(true);
    $r = true; $ran = 0;
    foreach (['db' => 'nexv_bk_step_db', 'files' => 'nexv_bk_step_files', 'zip' => 'nexv_bk_step_zip'] as $ph => $fn) {
        if ($st['phase'] !== $ph || !nexv_bk_go($t0, $ran)) continue;   // اولین مرحله همیشه، بعدی‌ها اگه وقت باشه
        $r = $fn($dir, $st, $t0);
        if (is_wp_error($r)) break;
    }
    $st['busy'] = 0;
    nexv_bk_write_php_json($dir . '/state.php', $st);
    if (is_wp_error($r)) wp_send_json_error(['message' => $r->get_error_message()]);
    $resp = ['progress' => nexv_bk_progress($st), 'done' => $st['phase'] === 'done'];
    if ($resp['done']) $resp['html'] = nexv_bk_list_html();
    wp_send_json_success($resp);
});

/* دانلودِ بکاپ (فقط مدیر؛ فایل‌ها مستقیم از وب در دسترس نیستن) */
add_action('admin_post_nexv_backup_download', function () {
    if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز.');
    check_admin_referer('nexv_backup_download');
    $id = preg_replace('/[^0-9a-f\-]/', '', (string) ($_GET['id'] ?? ''));
    $dir = nexv_bk_root() . '/' . $id;
    $st = $id !== '' ? nexv_bk_read_php_json($dir . '/state.php') : null;
    $file = $st ? $dir . '/' . nexv_bk_zip_name($st) : '';
    if (!$file || !is_file($file)) wp_die('این بکاپ پیدا نشد.');
    @set_time_limit(0);
    while (ob_get_level() > 0) @ob_end_clean();
    nocache_headers();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    header('Content-Length: ' . filesize($file));
    header('X-Content-Type-Options: nosniff');
    $fh = fopen($file, 'rb');
    while (!feof($fh)) { echo fread($fh, 1048576); flush(); }
    fclose($fh);
    exit;
});

function nexv_bk_list_html() {
    $list = nexv_bk_list();
    if (!$list) return '<p class="nexv-bk-empty">هنوز بکاپی ساخته نشده.</p>';
    $h = '<table class="widefat striped nexv-bk-table"><thead><tr><th>تاریخ</th><th>حجم</th><th>محتوا</th><th></th></tr></thead><tbody>';
    foreach ($list as $b) {
        $c = $b['counts'];
        $info = sprintf('%s محصول · %s سفارش · %s مشتری · %s فایلِ رسانه',
            number_format_i18n($c['products'] ?? 0), number_format_i18n($c['orders'] ?? 0), number_format_i18n($c['customers'] ?? 0), number_format_i18n($c['media'] ?? 0));
        $dl = wp_nonce_url(admin_url('admin-post.php?action=nexv_backup_download&id=' . rawurlencode($b['id'])), 'nexv_backup_download');
        $h .= '<tr><td>' . esc_html(nexv_bk_date($b['time'])) . '</td><td dir="ltr">' . esc_html(nexv_bk_fmt_size($b['size'])) . '</td><td>' . esc_html($info) . '</td>'
            . '<td class="nexv-bk-actions"><a class="button button-primary" href="' . esc_url($dl) . '">دانلود</a> '
            . '<button type="button" class="button nexv-bk-del" data-id="' . esc_attr($b['id']) . '">حذف</button></td></tr>';
    }
    return $h . '</tbody></table>';
}

add_action('admin_menu', function () {
    if (nexv_restore_payload()) return;   // روی سایتی که منتظرِ بازگردانیه، فقط صفحه‌ی بازگردانی نشون داده می‌شه
    add_menu_page('بکاپ کامل سایت', 'بکاپ', 'manage_options', 'nexv-backup', 'nexv_bk_page_html', 'dashicons-backup', '2.3');
});

function nexv_bk_page_html() {
    if (!current_user_can('manage_options')) return;
    $nonce = wp_create_nonce('nexv_backup');
    $u = wp_upload_dir(null, false);
    ?>
    <div class="wrap nexv-bk" dir="rtl">
        <h1>بکاپِ کاملِ سایت</h1>
        <div class="nexv-bk-card">
            <p class="nexv-bk-lead">با یک کلیک از <b>همه‌چیزِ سایت</b> یک فایل می‌سازه. این فایل خودش یک <b>قالبِ وردپرسه</b>؛ روی هر هاست و وردپرسِ تازه‌ای بارگذاری و فعالش کنید، سایت کامل برمی‌گرده.</p>
            <ul class="nexv-bk-inc">
                <li>محصولات، دسته‌بندی‌ها، قیمت‌ها و متغیرها</li>
                <li>سفارش‌ها (به‌همراهِ آرشیو و تاریخچه) و رسیدهای پرداخت</li>
                <li>مشتری‌ها، ادمین‌ها و آدرس‌ها</li>
                <li>مقالات، برگه‌ها و دیدگاه‌ها</li>
                <li>همه‌ی عکس‌ها و فایل‌های رسانه</li>
                <li>همه‌ی افزونه‌ها (Rank Math و بقیه) با تنظیماتشون</li>
                <li>کاربرانِ وردپرس، تنظیماتِ سایت، فایل‌های زبان و قالب</li>
            </ul>
            <p><button type="button" class="button button-primary button-hero" id="nexvBkStart">ساختِ بکاپِ کامل</button></p>
            <div class="nexv-bk-progress" id="nexvBkProgress" hidden>
                <div class="nexv-bk-bar"><span id="nexvBkBar"></span></div>
                <p id="nexvBkLabel">در حالِ آماده‌سازی...</p>
                <p class="description">تا تموم شدن این صفحه رو نبندید. بسته به حجمِ عکس‌ها ممکنه چند دقیقه طول بکشه.</p>
            </div>
            <p class="nexv-bk-err" id="nexvBkErr" hidden></p>
        </div>

        <h2>بکاپ‌های ساخته‌شده</h2>
        <p class="description">فقط <?php echo (int) NEXV_BK_KEEP; ?> بکاپِ آخر روی هاست نگه داشته می‌شه. هر بکاپ رو دانلود کنید و یک نسخه بیرون از هاست (کامپیوتر یا فضای ابری) نگه دارید.</p>
        <div id="nexvBkList"><?php echo nexv_bk_list_html(); ?></div>

        <h2>بازگردانی روی هاستِ جدید</h2>
        <div class="nexv-bk-card">
            <ol class="nexv-bk-steps">
                <li>روی هاستِ جدید یک وردپرسِ تازه نصب کنید (ترجیحاً آخرین نسخه).</li>
                <li>پیشخوان ← نمایش ← پوسته‌ها ← افزودن ← <b>بارگذاری پوسته</b> ← همین فایلِ zip رو انتخاب و نصب کنید.<br>
                    <span class="description">اگه حجمِ فایل از سقفِ آپلودِ هاست بیشتر بود: فایل رو با فایل‌منیجرِ هاست داخلِ پوشه‌ی <code>wp-content/themes</code> آپلود و همون‌جا اکسترکت کنید.</span></li>
                <li>پوسته رو <b>فعال</b> کنید؛ صفحه‌ی «بازگردانی بکاپ» خودش باز می‌شه. دکمه‌ی بازگردانی رو بزنید و صبر کنید تا تموم بشه.</li>
                <li>بعد از بازگردانی، با شماره و رمزِ مدیرِ سایت (از فرمِ ورودِ سایت) یا همون حسابِ وردپرسی که موقعِ نصب ساختید وارد بشید.</li>
            </ol>
        </div>
    </div>
    <style>
        .nexv-bk{max-width:900px}
        .nexv-bk-card{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px 22px;margin:14px 0 22px}
        .nexv-bk-lead{font-size:14px;line-height:2}
        .nexv-bk-inc{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:6px 18px;margin:12px 0 18px;padding:0;list-style:none}
        .nexv-bk-inc li{padding-inline-start:22px;position:relative;line-height:1.9}
        .nexv-bk-inc li:before{content:"✓";position:absolute;inset-inline-start:0;color:#00a32a;font-weight:700}
        .nexv-bk-bar{height:12px;border-radius:999px;background:#f0f0f1;overflow:hidden;margin:6px 0 8px}
        .nexv-bk-bar span{display:block;height:100%;width:0;background:linear-gradient(90deg,#6a4dff,#7c9dff);border-radius:999px;transition:width .4s ease}
        .nexv-bk-err{color:#b32d2e;font-weight:600}
        .nexv-bk-table td{vertical-align:middle}
        .nexv-bk-actions{white-space:nowrap;text-align:left}
        .nexv-bk-steps li{line-height:2;margin-bottom:6px}
        .nexv-bk-empty{color:#646970}
    </style>
    <script>
    (function(){
        const ajax = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, nonce = <?php echo wp_json_encode($nonce); ?>;
        const $ = id => document.getElementById(id);
        const post = data => fetch(ajax, { method:'POST', credentials:'same-origin', body: new URLSearchParams(Object.assign({ action:'nexv_backup', _ajax_nonce: nonce }, data)) })
            .then(r => r.json().catch(() => ({ success:false, data:{ message:'پاسخِ نامعتبر از سرور (کد ' + r.status + ')' } })));
        const show = p => { $('nexvBkBar').style.width = p.percent + '%'; $('nexvBkLabel').textContent = p.label + ' — ' + Math.floor(p.percent) + '٪'; };
        const err = m => { $('nexvBkErr').textContent = m; $('nexvBkErr').hidden = false; $('nexvBkStart').disabled = false; };
        function bindDelete(){
            document.querySelectorAll('.nexv-bk-del').forEach(b => b.onclick = async () => {
                if(!confirm('این بکاپ از روی هاست پاک بشه؟')) return;
                b.disabled = true;
                await post({ op:'delete', id: b.dataset.id });
                b.closest('tr').remove();
            });
        }
        bindDelete();
        $('nexvBkStart').addEventListener('click', async () => {
            $('nexvBkStart').disabled = true; $('nexvBkErr').hidden = true; $('nexvBkProgress').hidden = false;
            let r = await post({ op:'start' });
            if(!r.success){ err(r.data && r.data.message || 'شروعِ بکاپ ممکن نشد.'); return; }
            const id = r.data.id; show(r.data.progress);
            let fails = 0;
            while(true){
                try{ r = await post({ op:'step', id }); }catch(_){ r = null; }
                if(!r || !r.success){
                    if(r && r.data && r.data.message && fails >= 2){ err(r.data.message); return; }
                    if(++fails > 6){ err((r && r.data && r.data.message) || 'اتصال به سرور قطع شد؛ دوباره «ساختِ بکاپ» رو بزنید.'); return; }
                    await new Promise(res => setTimeout(res, 3000)); continue;
                }
                fails = 0;
                if(r.data.busy){ await new Promise(res => setTimeout(res, 3000)); continue; }
                show(r.data.progress);
                if(r.data.done){
                    $('nexvBkList').innerHTML = r.data.html; bindDelete();
                    $('nexvBkLabel').textContent = '✅ بکاپ آماده‌ست؛ از جدولِ پایین دانلودش کنید.';
                    $('nexvBkStart').disabled = false;
                    return;
                }
            }
        });
    })();
    </script>
    <?php
}

/* =========================================================================
 * بازگردانی (روی وردپرسِ تازه، وقتی این قالب با بسته‌ی بازگردانی فعال شده)
 * ========================================================================= */
function nexv_restore_payload() {
    static $dir = null;
    if ($dir !== null) return $dir ?: false;
    $found = glob(get_stylesheet_directory() . '/nexv-restore-*/manifest.php');
    $dir = ($found && file_exists(dirname($found[0]) . '/db.sql.php')) ? dirname($found[0]) : '';
    return $dir ?: false;
}
function nexv_restore_pending() { return (bool) nexv_restore_payload(); }

/* بعد از فعال‌کردنِ قالب، صفحه‌ی بازگردانی خودش باز می‌شه. (داخلِ خودِ after_switch_theme نباید
   exit کرد، وگرنه وردپرس علامتِ «قالب عوض شد» رو پاک نمی‌کنه و هر صفحه دوباره ریدایرکت می‌شه.) */
add_action('after_switch_theme', function () {
    if (nexv_restore_payload()) set_transient('nexv_restore_redirect', 1, 10 * MINUTE_IN_SECONDS);
});
add_action('admin_init', function () {
    if (!get_transient('nexv_restore_redirect') || wp_doing_ajax() || !current_user_can('manage_options')) return;
    delete_transient('nexv_restore_redirect');
    if (nexv_restore_payload() && ($_GET['page'] ?? '') !== 'nexv-restore') {
        wp_safe_redirect(admin_url('admin.php?page=nexv-restore'));
        exit;
    }
});
add_action('admin_menu', function () {
    if (!nexv_restore_payload()) return;
    add_menu_page('بازگردانی بکاپ', 'بازگردانی بکاپ', 'manage_options', 'nexv-restore', 'nexv_restore_page_html', 'dashicons-backup', 2);
});
add_action('admin_notices', function () {
    if (!nexv_restore_payload() || !current_user_can('manage_options') || (($_GET['page'] ?? '') === 'nexv-restore')) return;
    echo '<div class="notice notice-warning"><p dir="rtl">این قالب یک <b>بکاپِ کاملِ سایت</b> همراهش داره. برای برگردوندنِ همه‌چیز به <a href="' . esc_url(admin_url('admin.php?page=nexv-restore')) . '">صفحه‌ی بازگردانی</a> برید.</p></div>';
});

function nexv_restore_state($st = null) {
    $dir = nexv_restore_payload();
    if (!$dir) return null;
    if ($st === null) return nexv_bk_read_php_json($dir . '/state.php');
    nexv_bk_write_php_json($dir . '/state.php', $st);
    return $st;
}

/* جایگزینیِ آدرسِ سایتِ قدیم با جدید، بدونِ خراب‌کردنِ داده‌های serialize‌شده‌ی PHP */
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
function nexv_restore_url_map($oldHome, $newHome) {
    $oldHome = untrailingslashit($oldHome); $newHome = untrailingslashit($newHome);
    if ($oldHome === $newHome) return [];
    $u = wp_parse_url($oldHome);
    $host = $u['host'] ?? ''; $path = rtrim($u['path'] ?? '', '/'); $port = isset($u['port']) ? ':' . $u['port'] : '';
    if ($host === '') return [];
    $hosts = [$host . $port];
    if (strpos($host, 'www.') === 0) $hosts[] = substr($host, 4) . $port; else $hosts[] = 'www.' . $host . $port;
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

function nexv_restore_start() {
    global $wpdb;
    $dir = nexv_restore_payload();
    $man = nexv_bk_read_php_json($dir . '/manifest.php');
    if (!$man || ($man['format'] ?? '') !== 'nexv-full-backup') return new WP_Error('manifest', 'مشخصاتِ بکاپ خونده نشد.');
    $tar = $dir . '/files.tar';
    $need = (file_exists($tar) ? filesize($tar) : 0) * 1.1 + filesize($dir . '/db.sql.php') * 2;
    $free = @disk_free_space(WP_CONTENT_DIR);
    if ($free !== false && $free < $need) return new WP_Error('space', 'فضای خالیِ هاست کافی نیست: حدود ' . nexv_bk_fmt_size($need) . ' لازمه ولی ' . nexv_bk_fmt_size($free) . ' خالیه.');
    do { $tmp = 'nxr' . substr(bin2hex(random_bytes(3)), 0, 4) . '_'; } while ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tmp) . '%')));
    /* حسابِ مدیری که الان داره بازگردانی می‌کنه نگه داشته می‌شه تا بعد از بازگردانی هم بتونه وارد بشه */
    $me = wp_get_current_user();
    $keep = $me && $me->ID ? [
        'login' => $me->user_login, 'pass' => $me->user_pass, 'email' => $me->user_email, 'nicename' => $me->user_nicename, 'display' => $me->display_name,
        'registered' => $me->user_registered, 'tokens' => get_user_meta($me->ID, 'session_tokens', true),
    ] : null;
    $st = [
        'phase' => 'files', 'tmp' => $tmp, 'old_prefix' => $man['prefix'], 'new_prefix' => $wpdb->prefix,
        'old_home' => $man['home'], 'new_home' => home_url(), 'new_siteurl' => site_url(), 'theme' => get_stylesheet(), 'template' => get_template(),
        'tar_off' => 0, 'tar_size' => file_exists($tar) ? filesize($tar) : 0, 'files_done' => 0,
        'sql_off' => 0, 'table_start' => 0, 'sql_size' => filesize($dir . '/db.sql.php'), 'tables' => [], 'cur_table' => null,
        'sr_ti' => 0, 'sr_last' => null, 'keep' => $keep, 'clean' => true, 'started' => time(), 'log' => [],
    ];
    nexv_restore_state($st);
    return $st;
}

/* مقصدِ هر فایلِ داخلِ آرشیو */
function nexv_restore_dest($name) {
    if ($name === '' || strpos($name, '..') !== false || $name[0] === '/' || strpos($name, "\0") !== false) return null;
    $u = wp_upload_dir(null, false);
    $map = ['uploads/' => $u['basedir'], 'plugins/' => WP_PLUGIN_DIR, 'mu-plugins/' => defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins',
            'languages/' => defined('WP_LANG_DIR') ? WP_LANG_DIR : WP_CONTENT_DIR . '/languages', 'themes/' => get_theme_root(), 'nexv-data/' => $u['basedir'] . '/nexv-data'];
    foreach ($map as $pre => $base) {
        if (strpos($name, $pre) === 0) {
            $rel = substr($name, strlen($pre));
            if ($pre === 'themes/' && strtok($rel, '/') === get_stylesheet()) return null;   // قالبِ فعلی رو بازنویسی نکن
            return $rel === '' ? null : rtrim($base, '/') . '/' . $rel;
        }
    }
    return null;
}
function nexv_restore_step_files(array &$st, $t0) {
    $dir = nexv_restore_payload();
    $tar = $dir . '/files.tar';
    if (!file_exists($tar)) { $st['phase'] = 'db'; return true; }
    $fh = fopen($tar, 'rb');
    fseek($fh, $st['tar_off']);
    $go = 0;
    while (nexv_bk_go($t0, $go)) {
        $h = nexv_tar_next($fh);
        if ($h === false) { fclose($fh); return new WP_Error('tar', 'فایلِ بکاپ (files.tar) خرابه.'); }
        if ($h === null) { $st['phase'] = 'data'; break; }
        $dest = ($h['type'] === '0' || $h['type'] === "\0") ? nexv_restore_dest($h['name']) : null;
        $pad = (512 - $h['size'] % 512) % 512;
        if ($dest) {
            wp_mkdir_p(dirname($dest));
            $out = @fopen($dest, 'wb');
            if ($out) { if ($h['size']) stream_copy_to_stream($fh, $out, $h['size']); fclose($out); $st['files_done']++; }
            else { fseek($fh, $h['size'], SEEK_CUR); $st['log'][] = 'نوشته نشد: ' . $h['name']; }
        } else {
            fseek($fh, $h['size'], SEEK_CUR);
        }
        if ($pad) fseek($fh, $pad, SEEK_CUR);
        $st['tar_off'] = ftell($fh);
    }
    fclose($fh);
    return true;
}
/* آدرسِ سایت داخلِ فایل‌های داده‌ی NEXV (رسیدها و ...) اگه دامنه عوض شده باشه */
function nexv_restore_step_data(array &$st) {
    $map = nexv_restore_url_map($st['old_home'], $st['new_home']);
    if ($map) {
        $u = wp_upload_dir(null, false);
        $base = $u['basedir'] . '/nexv-data';
        $files = array_merge((array) glob($base . '/*.json'), (array) glob($base . '/orders-archive/*.json'), (array) glob($base . '/orders-archive/*.jsonl'));
        foreach ($files as $f) {
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
function nexv_restore_step_db(array &$st, $t0) {
    $dir = nexv_restore_payload();
    nexv_bk_sql("SET SESSION foreign_key_checks = 0, unique_checks = 0, sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");
    $fh = fopen($dir . '/db.sql.php', 'rb');
    if (empty($st['clean'])) $st['sql_off'] = $st['table_start'];   // درخواستِ قبلی نصفه موند: جدولِ فعلی از اول
    if ($st['sql_off'] === 0) fgets($fh); else fseek($fh, $st['sql_off']);
    $st['clean'] = false;
    nexv_restore_state($st);
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
            $sql = 'INSERT INTO `' . $tmp . substr($line, strlen('INSERT INTO `' . $old));
            if (nexv_bk_sql($sql) === false) { fclose($fh); return new WP_Error('db', 'وارد کردنِ اطلاعاتِ جدولِ ' . $st['cur_table'] . ' ممکن نشد: ' . nexv_bk_sql_error()); }
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
        $sel = '`' . $pk . '`, `' . implode('`, `', $cols) . '`';
        $res = nexv_bk_sql("SELECT $sel FROM `$t`$where ORDER BY `$pk` LIMIT 500");
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
    /* تنظیماتی که باید مالِ هاستِ جدید باشن */
    $set = function ($name, $value) use ($tmp) {
        $v = nexv_bk_esc(is_scalar($value) ? (string) $value : serialize($value));
        nexv_bk_sql("INSERT INTO `{$tmp}options` (option_name, option_value, autoload) VALUES ('" . nexv_bk_esc($name) . "', '$v', 'yes') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)");
    };
    $set('siteurl', untrailingslashit($st['new_siteurl']));
    $set('home', untrailingslashit($st['new_home']));
    $set('template', $st['template']);
    $set('stylesheet', $st['theme']);
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

    /* مدیرِ هاستِ جدید (همونی که الان واردِ پیشخوانه) اگه هم‌نامش در بکاپ نبود، اضافه می‌شه */
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
    $map = ['files' => [2, 40, 'برگردوندنِ فایل‌ها، عکس‌ها و افزونه‌ها'], 'data' => [42, 42, 'داده‌های NEXV'], 'db' => [43, 85, 'برگردوندنِ دیتابیس'], 'fix' => [85, 97, 'تنظیمِ آدرس‌ها برای هاستِ جدید'], 'swap' => [97, 99, 'جایگزینیِ نهایی'], 'done' => [100, 100, 'تموم شد']];
    list($a, $b, $label) = $map[$st['phase']] ?? [0, 0, ''];
    $f = 0;
    if ($st['phase'] === 'files') $f = $st['tar_off'] / max(1, $st['tar_size']);
    if ($st['phase'] === 'db') $f = $st['sql_off'] / max(1, $st['sql_size']);
    if ($st['phase'] === 'fix') $f = $st['sr_ti'] / max(1, count($st['tables']));
    return ['percent' => round($a + ($b - $a) * $f, 1), 'label' => $label];
}

add_action('wp_ajax_nexv_restore', function () {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'دسترسی غیرمجاز.'], 403);
    check_ajax_referer('nexv_restore');
    if (!nexv_restore_payload()) wp_send_json_error(['message' => 'بسته‌ی بازگردانی پیدا نشد.']);
    @set_time_limit(300);
    @ignore_user_abort(true);
    $op = sanitize_key($_POST['op'] ?? '');
    if ($op === 'discard') {
        nexv_bk_rrmdir(nexv_restore_payload());
        wp_send_json_success(['redirect' => admin_url()]);
    }
    if ($op === 'start') {
        $st = nexv_restore_state();
        if (!$st || ($st['phase'] ?? '') === 'done') {
            $st = nexv_restore_start();
            if (is_wp_error($st)) wp_send_json_error(['message' => $st->get_error_message()]);
        }
        wp_send_json_success(['progress' => nexv_restore_progress($st)]);
    }
    $st = nexv_restore_state();
    if (!$st) wp_send_json_error(['message' => 'بازگردانی هنوز شروع نشده.']);
    if (!empty($st['busy']) && time() - (int) $st['busy'] < 90) wp_send_json_success(['progress' => nexv_restore_progress($st), 'busy' => true]);
    if (!empty($st['busy'])) $st['clean'] = false;   // درخواستِ قبلی وسطِ کار قطع شده
    $st['busy'] = time();
    nexv_restore_state($st);
    $t0 = microtime(true);
    $r = true; $ran = 0;
    foreach (['files', 'data', 'db', 'fix', 'swap'] as $ph) {
        if ($st['phase'] !== $ph || !nexv_bk_go($t0, $ran)) continue;   // اولین مرحله همیشه، بعدی‌ها اگه وقت باشه
        if ($ph === 'files') $r = nexv_restore_step_files($st, $t0);
        elseif ($ph === 'data') $r = nexv_restore_step_data($st);
        elseif ($ph === 'db') $r = nexv_restore_step_db($st, $t0);
        elseif ($ph === 'fix') $r = nexv_restore_step_fix($st, $t0);
        else $r = nexv_restore_step_swap($st);
        if (is_wp_error($r)) break;
    }
    $st['busy'] = 0;
    if (is_wp_error($r)) { nexv_restore_state($st); wp_send_json_error(['message' => $r->get_error_message()]); }
    if ($st['phase'] === 'done') {
        $kept = !empty($st['kept_user']);
        nexv_bk_rrmdir(nexv_restore_payload());   // بسته‌ی بازگردانی (شاملِ دیتابیس) دیگه لازم نیست
        wp_send_json_success(['done' => true, 'progress' => nexv_restore_progress($st), 'kept_user' => $kept,
            'redirect' => $kept ? admin_url() : wp_login_url(), 'home' => home_url('/')]);
    }
    nexv_restore_state($st);
    wp_send_json_success(['progress' => nexv_restore_progress($st)]);
});

function nexv_restore_page_html() {
    if (!current_user_can('manage_options')) return;
    $dir = nexv_restore_payload();
    $m = $dir ? nexv_bk_read_php_json($dir . '/manifest.php') : null;
    if (!$m) { echo '<div class="wrap"><h1>بازگردانی بکاپ</h1><p>بسته‌ی بازگردانی پیدا نشد.</p></div>'; return; }
    $c = $m['counts'] ?? [];
    $st = nexv_restore_state();
    $nonce = wp_create_nonce('nexv_restore');
    $tarSize = file_exists($dir . '/files.tar') ? filesize($dir . '/files.tar') : 0;
    $sameDomain = untrailingslashit($m['home']) === untrailingslashit(home_url());
    ?>
    <div class="wrap nexv-rs" dir="rtl">
        <h1>بازگردانی بکاپِ کاملِ سایت</h1>
        <div class="nexv-rs-card">
            <table class="nexv-rs-info">
                <tr><th>سایتِ مبدأ</th><td dir="ltr"><?php echo esc_html($m['home']); ?></td></tr>
                <tr><th>تاریخِ بکاپ</th><td><?php echo esc_html(wp_date('Y/m/d H:i', (int) $m['created'])); ?></td></tr>
                <tr><th>محتوا</th><td><?php echo esc_html(sprintf('%s محصول · %s سفارش · %s مشتری · %s مقاله · %s فایلِ رسانه · %s افزونه',
                    number_format_i18n($c['products'] ?? 0), number_format_i18n($c['orders'] ?? 0), number_format_i18n($c['customers'] ?? 0),
                    number_format_i18n($c['articles'] ?? 0), number_format_i18n($c['media'] ?? 0), number_format_i18n(count($m['plugins'] ?? [])))); ?></td></tr>
                <tr><th>حجمِ فایل‌ها</th><td dir="ltr"><?php echo esc_html(nexv_bk_fmt_size($tarSize)); ?></td></tr>
                <tr><th>آدرسِ این سایت</th><td dir="ltr"><?php echo esc_html(home_url()); ?><?php if (!$sameDomain) echo ' <span class="nexv-rs-note" dir="rtl">(همه‌ی آدرس‌ها خودکار به این دامنه تغییر می‌کنن)</span>'; ?></td></tr>
            </table>
            <div class="nexv-rs-warn">
                <b>توجه:</b> همه‌ی محتوای فعلیِ این وردپرس (نوشته‌ها، تنظیمات و افزونه‌ها) با بکاپ جایگزین می‌شه.
                بعد از بازگردانی با <b>شماره و رمزِ مدیرِ سایت</b> (فرمِ ورودِ خودِ سایت) یا <b>حسابِ وردپرسیِ سایتِ قبلی</b> وارد بشید؛
                حسابی که الان باهاش واردید هم (اگه هم‌نامش در بکاپ نباشه) به‌عنوانِ مدیر حفظ می‌شه.
            </div>
            <label class="nexv-rs-ok"><input type="checkbox" id="nexvRsAgree"> متوجه شدم؛ این وردپرس با بکاپ جایگزین بشه.</label>
            <p>
                <button type="button" class="button button-primary button-hero" id="nexvRsStart" disabled><?php echo $st && ($st['phase'] ?? '') !== 'done' ? 'ادامه‌ی بازگردانی' : 'بازگردانیِ کاملِ سایت'; ?></button>
                <button type="button" class="button-link nexv-rs-discard" id="nexvRsDiscard">حذفِ این بسته (بدونِ بازگردانی)</button>
            </p>
            <div id="nexvRsProgress" hidden>
                <div class="nexv-bk-bar"><span id="nexvRsBar"></span></div>
                <p id="nexvRsLabel">در حالِ شروع...</p>
                <p class="description">تا تموم شدن این صفحه رو نبندید.</p>
            </div>
            <p class="nexv-bk-err" id="nexvRsErr" hidden></p>
            <div class="nexv-rs-done" id="nexvRsDone" hidden></div>
        </div>
    </div>
    <style>
        .nexv-rs{max-width:860px}
        .nexv-rs-card{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px 22px;margin:14px 0}
        .nexv-rs-info{border-collapse:collapse;margin-bottom:14px} .nexv-rs-info th{text-align:right;padding:6px 0 6px 18px;color:#50575e;font-weight:600;white-space:nowrap} .nexv-rs-info td{padding:6px 0}
        .nexv-rs-note{color:#2271b1;font-size:12px}
        .nexv-rs-warn{background:#fcf9e8;border:1px solid #f0d866;border-radius:10px;padding:12px 14px;line-height:2;margin:10px 0 14px}
        .nexv-rs-ok{display:block;margin:6px 0 12px;font-weight:600}
        .nexv-rs-discard{color:#b32d2e!important;margin-inline-start:12px!important}
        .nexv-bk-bar{height:12px;border-radius:999px;background:#f0f0f1;overflow:hidden;margin:6px 0 8px}
        .nexv-bk-bar span{display:block;height:100%;width:0;background:linear-gradient(90deg,#6a4dff,#7c9dff);border-radius:999px;transition:width .4s ease}
        .nexv-bk-err{color:#b32d2e;font-weight:600}
        .nexv-rs-done{background:#edfaef;border:1px solid #68de7c;border-radius:10px;padding:14px;line-height:2}
    </style>
    <script>
    (function(){
        const ajax = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, nonce = <?php echo wp_json_encode($nonce); ?>;
        const $ = id => document.getElementById(id);
        const post = data => fetch(ajax, { method:'POST', credentials:'same-origin', body: new URLSearchParams(Object.assign({ action:'nexv_restore', _ajax_nonce: nonce }, data)) })
            .then(r => r.json().catch(() => ({ success:false, data:{ message:'پاسخِ نامعتبر از سرور (کد ' + r.status + ')' } })));
        const show = p => { $('nexvRsBar').style.width = p.percent + '%'; $('nexvRsLabel').textContent = p.label + ' — ' + Math.floor(p.percent) + '٪'; };
        const err = m => { $('nexvRsErr').textContent = m + ' — دوباره دکمه رو بزنید تا از همون‌جا ادامه پیدا کنه.'; $('nexvRsErr').hidden = false; $('nexvRsStart').disabled = false; };
        $('nexvRsAgree').addEventListener('change', e => { $('nexvRsStart').disabled = !e.target.checked; });
        $('nexvRsDiscard').addEventListener('click', async () => {
            if(!confirm('بسته‌ی بکاپ از داخلِ قالب پاک بشه؟ (بازگردانی انجام نمی‌شه)')) return;
            const r = await post({ op:'discard' }); if(r.success) location.href = r.data.redirect;
        });
        $('nexvRsStart').addEventListener('click', async () => {
            $('nexvRsStart').disabled = true; $('nexvRsErr').hidden = true; $('nexvRsProgress').hidden = false;
            let r = await post({ op:'start' });
            if(!r.success){ err(r.data && r.data.message || 'شروعِ بازگردانی ممکن نشد.'); return; }
            show(r.data.progress);
            let fails = 0;
            while(true){
                try{ r = await post({ op:'step' }); }catch(_){ r = null; }
                if(!r || !r.success){
                    if(r && r.data && r.data.message && fails >= 2){ err(r.data.message); return; }
                    if(++fails > 6){ err((r && r.data && r.data.message) || 'اتصال به سرور قطع شد.'); return; }
                    await new Promise(res => setTimeout(res, 3000)); continue;
                }
                fails = 0;
                if(r.data.busy){ await new Promise(res => setTimeout(res, 3000)); continue; }
                show(r.data.progress);
                if(r.data.done){
                    $('nexvRsProgress').hidden = true;
                    $('nexvRsDone').hidden = false;
                    $('nexvRsDone').innerHTML = '✅ سایت کامل برگردونده شد.<br>' + (r.data.kept_user
                        ? 'می‌تونید با همین حساب ادامه بدید. <a class="button button-primary" href="' + r.data.redirect + '">رفتن به پیشخوان</a> '
                        : 'حالا با حسابِ وردپرسیِ سایتِ قبلی، یا از فرمِ ورودِ سایت با شماره و رمزِ مدیر وارد بشید. <a class="button button-primary" href="' + r.data.redirect + '">ورود</a> ')
                        + '<a class="button" href="' + r.data.home + '" target="_blank">دیدنِ سایت</a>';
                    return;
                }
            }
        });
    })();
    </script>
    <?php
}

/* بعد از بازگردانی (اولین درخواست): پیوندهای یکتا و .htaccess از نو ساخته می‌شن و کش‌ها پاک می‌شن */
add_action('init', function () {
    if (!get_option('nexv_post_restore')) return;
    delete_option('nexv_post_restore');
    if (!function_exists('save_mod_rewrite_rules')) { require_once ABSPATH . 'wp-admin/includes/misc.php'; require_once ABSPATH . 'wp-admin/includes/file.php'; }
    flush_rewrite_rules(true);
    foreach (['nexv_products_cache_v2', 'nexv_categories_cache_v1'] as $t) delete_transient($t);
}, 99);
