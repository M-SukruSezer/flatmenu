<?php
declare(strict_types=1);

$baseDir = __DIR__;
$dataDir = $baseDir . DIRECTORY_SEPARATOR . 'data';
$uploadsDir = $baseDir . DIRECTORY_SEPARATOR . 'uploads';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

function load_json(string $file, array $default = []): array {
    if (!file_exists($file)) return $default;
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') return $default;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

$config = load_json($dataDir . DIRECTORY_SEPARATOR . 'config.json', [
    'site_title' => 'Menü',
    'logo' => '',
    'favicon' => '',
    'social' => [
        'facebook' => '',
        'instagram' => '',
        'twitter' => ''
    ]
]);
$categories = load_json($dataDir . DIRECTORY_SEPARATOR . 'categories.json', []);
$menu = load_json($dataDir . DIRECTORY_SEPARATOR . 'menu.json', []);

$cacheDir = $baseDir . DIRECTORY_SEPARATOR . 'cache';
if (!is_dir($cacheDir)) { mkdir($cacheDir, 0777, true); }
$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'home.html';
$cfgFile = $dataDir . DIRECTORY_SEPARATOR . 'config.json';
$catsFile = $dataDir . DIRECTORY_SEPARATOR . 'categories.json';
$menuFile = $dataDir . DIRECTORY_SEPARATOR . 'menu.json';
$latest = max(
    file_exists($cfgFile) ? (int)filemtime($cfgFile) : 0,
    file_exists($catsFile) ? (int)filemtime($catsFile) : 0,
    file_exists($menuFile) ? (int)filemtime($menuFile) : 0
);
if (file_exists($cacheFile) && (int)filemtime($cacheFile) >= $latest) {
    readfile($cacheFile);
    exit;
}
ob_start();
// config.json elle düzenlenebildiği için her değer string'e zorlanır:
// h() katı tipte string bekler, sayı veya dizi gelirse sayfa fatal verir.
$lang = is_string($_COOKIE['lang'] ?? null) ? (string)$_COOKIE['lang'] : 'tr';
$lang = preg_match('/^[A-Za-z-]{2,8}$/', $lang) ? $lang : 'tr';
$title = (string)($config['site_title'] ?? 'Menü');
$logo = (string)($config['logo'] ?? '');
$favicon = (string)($config['favicon'] ?? '');
$social = $config['social'] ?? ['facebook' => '', 'instagram' => '', 'twitter' => ''];
$headerBg = (string)($config['header_bg'] ?? '');
$footerBg = (string)($config['footer_bg'] ?? '');
$footerText = trim((string)($config['footer_text'] ?? ''));

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Her kategorideki ürün sayısı: kartın altında "9 ürün" olarak görünür
$itemCounts = [];
foreach ($menu as $mi) {
    $cid = (string)($mi['category_id'] ?? '');
    if ($cid === '') continue;
    $itemCounts[$cid] = ($itemCounts[$cid] ?? 0) + 1;
}

$visibleCats = [];
foreach ($categories as $cat) {
    if (empty($cat['visible']) && ($cat['visible'] ?? null) !== true) continue;
    $visibleCats[] = $cat;
}

?><!doctype html>
<html lang="<?php echo h($lang); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h($title); ?></title>
    <?php if ($favicon): ?>
    <link rel="icon" href="<?php echo h($favicon); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="site-header" style="<?php echo $headerBg? 'background:'.h($headerBg):''; ?>">
        <div class="header-left"></div>
        <div class="header-center">
            <?php if ($logo): ?>
            <img class="logo" src="<?php echo h($logo); ?>" alt="<?php echo h($title); ?>">
            <?php else: ?>
            <div class="logo-text"><?php echo h($title); ?></div>
            <?php endif; ?>
            <div class="brand-sub">Menü</div>
        </div>
        <div class="header-right"></div>
    </header>

    <main class="container">
        <div class="sec-head">
            <h1 class="sec-title">Kategoriler</h1>
        </div>

        <?php if (!$visibleCats): ?>
        <p class="empty-note">Menü henüz hazırlanıyor. Yönetim panelinden kategori ekleyebilirsiniz.</p>
        <?php else: ?>
        <section class="cat-grid">
            <?php foreach ($visibleCats as $cat): ?>
                <?php
                $layoutVal = (string)($cat['layout'] ?? 'two');
                $layout = in_array($layoutVal, ['full','two','vertical'], true) ? $layoutVal : 'two';
                $catId = (string)($cat['id'] ?? '');
                $count = $itemCounts[$catId] ?? 0;
                ?>
                <a class="cat-tile <?php echo $layout; ?>" href="category.php?id=<?php echo h($catId); ?>">
                    <div class="cat-photo" style="<?php echo !empty($cat['image'])? 'background-image:url('.h((string)$cat['image']).')':''; ?>"></div>
                    <div class="cat-name"><?php echo h((string)($cat['name'] ?? '')); ?></div>
                    <div class="cat-count"><?php echo $count; ?> ürün</div>
                </a>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>
    </main>

    <footer class="site-footer" style="<?php echo $footerBg? 'background:'.h($footerBg):''; ?>">
        <div class="footer-center"><?php echo h($title); ?></div>
        <?php if (!empty($social['facebook']) || !empty($social['instagram']) || !empty($social['twitter'])): ?>
        <div class="footer-icons">
            <?php if (!empty($social['facebook'])): ?>
            <a class="social-link" href="<?php echo h((string)$social['facebook']); ?>" target="_blank" rel="noopener" aria-label="Facebook">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7h-2.5V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 3h-2.4v7A10 10 0 0 0 22 12z"/></svg>
            </a>
            <?php endif; ?>
            <?php if (!empty($social['instagram'])): ?>
            <a class="social-link" href="<?php echo h((string)$social['instagram']); ?>" target="_blank" rel="noopener" aria-label="Instagram">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm5 5a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm6-1.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z"/></svg>
            </a>
            <?php endif; ?>
            <?php if (!empty($social['twitter'])): ?>
            <a class="social-link" href="<?php echo h((string)$social['twitter']); ?>" target="_blank" rel="noopener" aria-label="X">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M3 3l7 7-7 7h3l5-5 5 5h3l-7-7 7-7h-3l-5 5-5-5H3z"/></svg>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($footerText !== ''): ?>
        <div class="footer-copyright"><?php echo h($footerText); ?></div>
        <?php endif; ?>
    </footer>

    <script src="assets/js/app.js"></script>
</body>
</html>
<?php
$out = ob_get_contents();
if ($out !== false) { @file_put_contents($cacheFile, $out); }
ob_end_flush();
?>
