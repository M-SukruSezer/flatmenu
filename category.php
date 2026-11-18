<?php
declare(strict_types=1);

$baseDir = __DIR__;
$dataDir = $baseDir . DIRECTORY_SEPARATOR . 'data';

function load_json(string $file, array $default = []): array {
    if (!file_exists($file)) return $default;
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') return $default;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function fmt_price($p): string {
    $s = trim((string)$p);
    if ($s === '') return '';
    $s = preg_replace('/\s*(₺|tl)\s*/iu', '', $s);
    return trim($s) . ' ₺';
}

// Besin değerleri basılı menü gibi kısa yazılır: "5 kcal", "6 g protein"
function fmt_facts(array $nutrition): array {
    $units = [
        'kcal'    => '%s kcal',
        'kj'      => '%s kJ',
        'carb'    => '%s g karbonhidrat',
        'protein' => '%s g protein',
        'fat'     => '%s g yağ',
        'satfat'  => '%s g doymuş yağ',
        'sugar'   => '%s g şeker',
        'salt'    => '%s g tuz',
        'portion' => '%s g porsiyon',
        'volume'  => '%s mL',
    ];
    $out = [];
    foreach ($units as $key => $tpl) {
        $val = trim((string)($nutrition[$key] ?? ''));
        if ($val === '') continue;
        $out[] = sprintf($tpl, $val);
    }
    return $out;
}

$config = load_json($dataDir . DIRECTORY_SEPARATOR . 'config.json', []);
$categories = load_json($dataDir . DIRECTORY_SEPARATOR . 'categories.json', []);
$menu = load_json($dataDir . DIRECTORY_SEPARATOR . 'menu.json', []);

$id = isset($_GET['id']) ? (string)$_GET['id'] : '';
$category = null;
foreach ($categories as $cat) {
    if ((string)($cat['id'] ?? '') === $id) { $category = $cat; break; }
}
if (!$category) { header('Location: index.php'); exit; }

// config.json elle düzenlenebildiği için her değer string'e zorlanır:
// h() katı tipte string bekler, sayı veya dizi gelirse sayfa fatal verir.
$lang = is_string($_COOKIE['lang'] ?? null) ? (string)$_COOKIE['lang'] : 'tr';
$lang = preg_match('/^[A-Za-z-]{2,8}$/', $lang) ? $lang : 'tr';
$siteTitle = (string)($config['site_title'] ?? 'Menü');
$title = (string)($category['name'] ?? '') . ' - ' . $siteTitle;
$logo = (string)($config['logo'] ?? '');
$social = $config['social'] ?? ['facebook' => '', 'instagram' => '', 'twitter' => ''];
$headerBg = (string)($config['header_bg'] ?? '');
$footerBg = (string)($config['footer_bg'] ?? '');
$footerText = trim((string)($config['footer_text'] ?? ''));
$bg = (string)($category['image'] ?? '');

// Bu kategorinin ürünleri
$items = [];
foreach ($menu as $item) {
    if ((string)($item['category_id'] ?? '') === $id) $items[] = $item;
}
// Kategoride tek bir görsel bile varsa foto sütunu TÜM satırlarda ayrılır.
// Aksi halde fiyatlar görselli ve görselsiz satırlarda iki ayrı sağ hizaya
// düşüyor ve basılı menünün omurgası olan tek fiyat sütunu bozuluyor.
$anyPhoto = false;
foreach ($items as $it) { if (!empty($it['image'])) { $anyPhoto = true; break; } }

?><!doctype html>
<html lang="<?php echo h($lang); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h($title); ?></title>
    <?php if (!empty($config['favicon'])): ?>
    <link rel="icon" href="<?php echo h((string)$config['favicon']); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="site-header" style="<?php echo $headerBg? 'background:'.h($headerBg):''; ?>">
        <div class="header-left"></div>
        <div class="header-center">
            <?php if ($logo): ?>
            <img class="logo" src="<?php echo h($logo); ?>" alt="<?php echo h($siteTitle); ?>">
            <?php else: ?>
            <div class="logo-text"><?php echo h($siteTitle); ?></div>
            <?php endif; ?>
            <div class="brand-sub">Menü</div>
        </div>
        <div class="header-right"></div>
    </header>

    <?php if ($bg): ?>
    <div class="cat-cover" style="background-image:url(<?php echo h($bg); ?>)"></div>
    <?php endif; ?>

    <div class="cat-head">
        <h1 class="cat-head-title"><?php echo h((string)($category['name'] ?? '')); ?></h1>
        <div class="cat-head-meta">
            <span class="eyebrow"><?php echo count($items); ?> ürün</span>
            <a class="back-btn" href="index.php">← Menüye dön</a>
        </div>
    </div>

    <main class="container">
        <section class="menu-list">
            <?php if (!$items): ?>
            <p class="empty-note">Bu kategoride henüz ürün yok.</p>
            <?php endif; ?>


            <?php foreach ($items as $item): ?>
                <?php
                $itemId   = (string)($item['id'] ?? '');
                $vars     = isset($item['variants']) && is_array($item['variants']) ? $item['variants'] : [];
                $hasVars  = count($vars) > 0;
                $basePrice = fmt_price($item['price'] ?? '');
                $baseFacts = fmt_facts(isset($item['nutrition']) && is_array($item['nutrition']) ? $item['nutrition'] : []);
                $baseAllergen = trim((string)($item['allergen'] ?? ''));

                // Boy listesi: temel ürünün kendi fiyatı varsa ilk satır olur
                $sizes = [];
                if ($hasVars) {
                    if ($basePrice !== '') {
                        $sizes[] = ['label' => 'Standart', 'price' => $basePrice,
                                    'facts' => $baseFacts, 'allergen' => $baseAllergen];
                    }
                    foreach ($vars as $v) {
                        $sizes[] = [
                            'label'    => trim((string)($v['name'] ?? '')),
                            'price'    => fmt_price($v['price'] ?? ''),
                            'facts'    => fmt_facts(isset($v['nutrition']) && is_array($v['nutrition']) ? $v['nutrition'] : []),
                            'allergen' => trim((string)($v['allergen'] ?? '')),
                        ];
                    }
                } else {
                    $sizes[] = ['label' => '', 'price' => $basePrice,
                                'facts' => $baseFacts, 'allergen' => $baseAllergen];
                }

                // Alerjen ve besin değerleri ürün düzeyinde toplanır: her boyun
                // altında tekrarlanınca fiyat listesi okunmaz hale geliyordu.
                $allergens = [];
                $allergenFree = false;   // "-" girilmiş: alerjen içermiyor
                foreach ($sizes as $sz) {
                    $a = $sz['allergen'];
                    if ($a === '') continue;
                    if ($a === '-') { $allergenFree = true; continue; }
                    $allergens[$a][] = $sz['label'];
                }
                // Dizi anahtarı: "0" gibi sayısal bir alerjen değeri PHP tarafından
                // int'e çevrilir; h() katı tipte string beklediği için cast şart.
                $sharedAllergen = (count($allergens) === 1) ? (string)array_key_first($allergens) : '';

                $factRows = [];
                foreach ($sizes as $sz) {
                    if ($sz['facts']) $factRows[] = ['label' => $sz['label'], 'facts' => $sz['facts']];
                }
                ?>
                <article class="menu-row" id="item-<?php echo h($itemId); ?>">
                    <div class="menu-text">

                        <div class="lead-line">
                            <span class="lead-name"><?php echo h((string)($item['name'] ?? '')); ?></span>
                            <?php if (!$hasVars && $basePrice !== ''): ?>
                            <span class="lead-dots"></span>
                            <span class="lead-price"><?php echo h($basePrice); ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($item['desc'])): ?>
                        <p class="menu-desc"><?php echo h((string)$item['desc']); ?></p>
                        <?php endif; ?>

                        <?php if ($hasVars): ?>
                        <div class="size-list">
                            <?php foreach ($sizes as $sz): ?>
                            <div class="size-row">
                                <span class="size-name"><?php echo h($sz['label'] !== '' ? $sz['label'] : 'Standart'); ?></span>
                                <?php if ($sz['price'] !== ''): ?>
                                <span class="size-dots"></span>
                                <span class="size-price"><?php echo h($sz['price']); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php /* Alerjen güvenlik bilgisidir: asla katlanmaz, besin
                                 değerlerinden bir kademe daha belirgin durur. */ ?>
                        <?php if ($sharedAllergen !== ''): ?>
                        <p class="allergen"><span class="allergen-label">Alerjen</span><?php echo h($sharedAllergen); ?></p>
                        <?php elseif ($allergens): ?>
                            <?php foreach ($allergens as $aName => $aLabels): ?>
                            <p class="allergen"><span class="allergen-label">Alerjen<?php $l = implode(', ', array_filter($aLabels)); echo $l !== '' ? ' · '.h($l) : ''; ?></span><?php echo h((string)$aName); ?></p>
                            <?php endforeach; ?>
                        <?php elseif ($allergenFree): ?>
                        <p class="allergen is-free"><span class="allergen-label">Alerjen</span>İçermez</p>
                        <?php endif; ?>

                        <?php if ($factRows): ?>
                        <details class="facts-toggle">
                            <summary>Besin değerleri</summary>
                            <?php /* Açılırken yüksekliği canlandırılan tek sarmalayıcı */ ?>
                            <div class="facts-body">
                                <?php foreach ($factRows as $fr): ?>
                                <div class="facts">
                                    <?php if ($fr['label'] !== '' && count($factRows) > 1): ?>
                                    <b class="facts-label"><?php echo h($fr['label']); ?></b>
                                    <?php endif; ?>
                                    <?php foreach ($fr['facts'] as $f): ?><span class="fact"><?php echo h($f); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                        <?php endif; ?>

                    </div>

                    <?php if ($anyPhoto): ?>
                        <?php if (!empty($item['image'])): ?>
                        <div class="menu-photo" style="background-image:url(<?php echo h((string)$item['image']); ?>)"></div>
                        <?php else: ?>
                        <div class="menu-photo is-empty" aria-hidden="true"></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    </main>

    <footer class="site-footer" style="<?php echo $footerBg? 'background:'.h($footerBg):''; ?>">
        <div class="footer-center"><?php echo h($siteTitle); ?></div>
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
