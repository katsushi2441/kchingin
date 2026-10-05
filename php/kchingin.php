<?php
/**
 * Kurage 最低賃金・賃上げ助成金チェック（kchingin）― 1ファイルPHP。
 *
 * 経営者が「来月から最低賃金がいくらになるか」「うちで下回る人は何人で、人件費はいくら増えるか」
 * 「業務改善助成金はまだ申請できるか、いくら出るか」を、自社の時給の一覧から計算する。
 * 時給の計算はブラウザの中だけで行い、入力した数字はサーバーへ送らない。
 *
 *   /kchingin.php/            入口（計算）・47都道府県の一覧
 *   /kchingin.php/p/<コード>/  都道府県ごとのページ（例 /p/23/ 愛知県）
 *   /kchingin.php/josei/      業務改善助成金（令和8年度）の要件と、都道府県別の申請締切
 *   /kchingin.php/about       出典・計算のしかた・しないこと
 *   /kchingin.php/sitemap.xml /robots.txt /llms.txt
 *
 * 数字の出典（2026-10-05 確認）
 *   地域別最低賃金：厚生労働省「令和8年度 地域別最低賃金 答申状況」（別紙）https://www.mhlw.go.jp/content/11302000/001745621.pdf
 *   業務改善助成金：厚生労働省「令和8年度業務改善助成金のご案内」https://www.mhlw.go.jp/content/11200000/001693381.pdf
 * 年度が変わったら MW と JOSEI_* を差し替える。発効日は答申後の手続きで変わることがある。
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Tokyo');

const SITE = 'https://kurage.exbridge.jp';
const BASE = SITE . '/kchingin.php';
const NAME = 'Kurage 最低賃金・賃上げ助成金チェック';
const FY   = '令和8年度（2026年度）';
const SRC_MW    = 'https://www.mhlw.go.jp/content/11302000/001745621.pdf';
const SRC_JOSEI = 'https://www.mhlw.go.jp/content/11200000/001693381.pdf';
const SRC_JOSEI_PAGE = 'https://www.mhlw.go.jp/stf/seisakunitsuite/bunya/koyou_roudou/roudoukijun/zigyonushi/shienjigyou/03.html';
const CHECKED = '2026-10-05';
const BUY = '';   // 商品ページができたら URL を入れる

const PREFS = ['01'=>'北海道','02'=>'青森県','03'=>'岩手県','04'=>'宮城県','05'=>'秋田県','06'=>'山形県','07'=>'福島県','08'=>'茨城県','09'=>'栃木県','10'=>'群馬県','11'=>'埼玉県','12'=>'千葉県','13'=>'東京都','14'=>'神奈川県','15'=>'新潟県','16'=>'富山県','17'=>'石川県','18'=>'福井県','19'=>'山梨県','20'=>'長野県','21'=>'岐阜県','22'=>'静岡県','23'=>'愛知県','24'=>'三重県','25'=>'滋賀県','26'=>'京都府','27'=>'大阪府','28'=>'兵庫県','29'=>'奈良県','30'=>'和歌山県','31'=>'鳥取県','32'=>'島根県','33'=>'岡山県','34'=>'広島県','35'=>'山口県','36'=>'徳島県','37'=>'香川県','38'=>'愛媛県','39'=>'高知県','40'=>'福岡県','41'=>'佐賀県','42'=>'長崎県','43'=>'熊本県','44'=>'大分県','45'=>'宮崎県','46'=>'鹿児島県','47'=>'沖縄県'];

// 都道府県コード => [改定後の時間額, 改定前の時間額, 発効日（予定）]
const MW = [
    '01' => [1131, 1075, '2026-10-01'],
    '02' => [1090, 1029, '2026-10-29'],
    '03' => [1090, 1031, '2026-12-01'],
    '04' => [1098, 1038, '2026-10-01'],
    '05' => [1090, 1031, '2026-10-14'],
    '06' => [1092, 1032, '2026-10-30'],
    '07' => [1094, 1033, '2026-10-16'],
    '08' => [1136, 1074, '2026-10-18'],
    '09' => [1125, 1068, '2026-10-01'],
    '10' => [1120, 1063, '2026-10-03'],
    '11' => [1196, 1141, '2026-10-01'],
    '12' => [1195, 1140, '2026-10-01'],
    '13' => [1280, 1226, '2026-10-01'],
    '14' => [1279, 1225, '2026-10-01'],
    '15' => [1108, 1050, '2026-10-01'],
    '16' => [1119, 1062, '2026-10-01'],
    '17' => [1113, 1054, '2026-10-03'],
    '18' => [1112, 1053, '2026-10-04'],
    '19' => [1113, 1052, '2026-11-01'],
    '20' => [1117, 1061, '2026-10-02'],
    '21' => [1121, 1065, '2026-10-01'],
    '22' => [1154, 1097, '2026-10-15'],
    '23' => [1195, 1140, '2026-10-01'],
    '24' => [1143, 1087, '2026-10-01'],
    '25' => [1136, 1080, '2026-10-03'],
    '26' => [1180, 1122, '2026-11-16'],
    '27' => [1231, 1177, '2026-10-01'],
    '28' => [1172, 1116, '2026-10-01'],
    '29' => [1107, 1051, '2026-10-04'],
    '30' => [1101, 1045, '2026-10-03'],
    '31' => [1090, 1030, '2026-10-03'],
    '32' => [1092, 1033, '2026-10-10'],
    '33' => [1104, 1047, '2026-10-02'],
    '34' => [1141, 1085, '2026-10-11'],
    '35' => [1101, 1043, '2026-10-08'],
    '36' => [1103, 1046, '2026-11-01'],
    '37' => [1092, 1036, '2026-10-01'],
    '38' => [1093, 1033, '2026-11-01'],
    '39' => [1086, 1023, '2026-10-29'],
    '40' => [1114, 1057, '2026-10-04'],
    '41' => [1095, 1030, '2026-11-15'],
    '42' => [1087, 1031, '2026-11-02'],
    '43' => [1092, 1034, '2026-12-01'],
    '44' => [1096, 1035, '2026-11-01'],
    '45' => [1085, 1023, '2026-10-24'],
    '46' => [1090, 1026, '2026-10-25'],
    '47' => [1086, 1023, '2026-12-02']
];

// 業務改善助成金（令和8年度）
const JOSEI_OPEN  = '2026-09-01';
const JOSEI_LAST  = '2026-11-30';   // 申請期間の終わりは「発効日の前日」と「11月30日」の早い方
const JOSEI_DONE  = '2027-01-31';   // 事業完了期限（交付決定の年度の1月31日）
const JOSEI_RATE_LINE = 1050;       // 引上げ前の事業場内最低賃金がこれ未満なら 4/5、以上なら 3/4
// コース => 人数区分 => [30人未満以外の事業者, 30人未満の事業者]（円）。'10' は特例事業者が10人以上を引き上げる場合のみ
const JOSEI_CAP = [
    50 => ['1' => [300000, 400000], '2' => [400000, 700000], '4' => [700000, 700000], '6' => [900000, 900000], '8' => [1100000, 1100000], '10' => [1300000, 1300000]],
    70 => ['1' => [400000, 500000], '2' => [500000, 1000000], '4' => [1300000, 1300000], '6' => [1800000, 1800000], '8' => [2300000, 2300000], '10' => [3000000, 3000000]],
    90 => ['1' => [900000, 1000000], '2' => [1500000, 2400000], '4' => [2700000, 2700000], '6' => [3600000, 3600000], '8' => [4500000, 4500000], '10' => [6000000, 6000000]],
];

// 生産性向上の設備投資として並べる当社のシステム（kappstore）。助成の対象になるかは労働局の審査で決まる
const TOOLS = [
    ['勤怠・シフト（顔打刻）', 'Kurage 勤怠', 'f0f56c6e4da881be', 'https://proto.exbridge.jp/kkintai/', '打刻と集計を自動にし、締め作業の時間を減らす'],
    ['受発注（FAX・電話の注文をWEBに）', 'Kurage Web Order', '88acc4be142bd444', 'https://proto.exbridge.jp/kweborder/', '注文の転記と確認の電話を減らす'],
    ['注文書・FAXの読み取り（AI-OCR）', 'Kurage OCR Work', 'ef52a62e1c6bbe7a', 'https://proto.exbridge.jp/kocrwork.php/', '手入力をなくす'],
    ['顧客管理・商談記録', 'Kurage Deal Desk', '63000166082c7a26', 'https://proto.exbridge.jp/kdealdesk/', '公式の例にある「顧客管理情報のシステム化」'],
    ['予約・受付', 'Kurage 予約・受付', '362c94ab4e1384f2', 'https://proto.exbridge.jp/kreserve/', '電話受付と台帳の転記を減らす'],
    ['請求書の発行と入金確認', 'Kurage 請求書', '15abb025dc2ee4f6', 'https://proto.exbridge.jp/kbilling/', '請求と消し込みの手作業を減らす'],
];

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function u(string $p): string { return BASE . $p; }
function yen(int|float $v): string { return number_format((int)round($v)) . '円'; }
function man(int $v): string { return number_format($v / 10000) . '万円'; }
function today(): string { return date('Y-m-d'); }
function jd(string $ymd): string { [$y, $m, $d] = array_map('intval', explode('-', $ymd)); return $m . '月' . $d . '日'; }
function wd(string $ymd): string { return ['日','月','火','水','木','金','土'][(int)date('w', strtotime($ymd))]; }
function days_between(string $a, string $b): int { return (int)round((strtotime($b) - strtotime($a)) / 86400); }

/** 都道府県の数字と、今日の時点での状態 */
function pref(string|int $c): array {
    $c = sprintf('%02d', (int)$c);   // PHP は '10'〜'47' のキーを整数にする
    [$new, $old, $eff] = MW[$c];
    $dl = date('Y-m-d', strtotime($eff . ' -1 day'));
    if ($dl > JOSEI_LAST) { $dl = JOSEI_LAST; }
    $t = today();
    return ['code' => $c, 'name' => PREFS[$c], 'new' => $new, 'old' => $old, 'up' => $new - $old, 'eff' => $eff,
            'in_effect' => $t >= $eff, 'deadline' => $dl, 'open' => $t >= JOSEI_OPEN && $t <= $dl, 'left' => days_between($t, $dl)];
}
function pref_short(string $n): string { return $n === '北海道' ? $n : mb_substr($n, 0, -1); }

function josei_status(array $p): string {
    if ($p['open']) {
        return '<b class="ok">申請できます：あと' . $p['left'] . '日（' . jd($p['deadline']) . '（' . wd($p['deadline']) . '）まで）</b>';
    }
    return '<b class="ng">令和8年度の申請は締め切りました（' . jd($p['deadline']) . 'まで）</b>';
}

// ---------------- 画面の骨格 ----------------
function page(string $title, string $desc, string $url, string $body, array $ld = [], array $crumbs = []): void {
    $ld[] = ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => NAME, 'url' => BASE . '/',
             'publisher' => ['@type' => 'Organization', 'name' => '株式会社エクスブリッジ', 'url' => 'https://exbridge.jp/']];
    if ($crumbs) {
        $items = [['@type' => 'ListItem', 'position' => 1, 'name' => NAME, 'item' => BASE . '/']];
        foreach ($crumbs as $i => [$n, $l]) { $items[] = ['@type' => 'ListItem', 'position' => $i + 2, 'name' => $n, 'item' => $l]; }
        $ld[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . '</title><meta name="description" content="' . h($desc) . '"><link rel="canonical" href="' . h($url) . '">';
    echo '<meta property="og:type" content="website"><meta property="og:site_name" content="' . h(NAME) . '"><meta property="og:title" content="' . h($title) . '"><meta property="og:description" content="' . h($desc) . '"><meta property="og:url" content="' . h($url) . '"><meta property="og:image" content="' . SITE . '/images/ogp/kchingin.png"><meta property="og:locale" content="ja_JP"><meta name="twitter:card" content="summary_large_image"><meta name="color-scheme" content="light">';
    echo '<link rel="icon" href="https://exbridge.jp/images/logo-mark-128.png">';
    foreach ($ld as $j) { echo '<script type="application/ld+json">' . json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>'; }
    echo '<style>
:root{--navy:#14213d;--blue:#1d4ed8;--blue-l:#eaf0fe;--ink:#1f2933;--sub:#5b6676;--line:#dde3ea;--bg:#f6f8fb;--ok:#047857;--ok-l:#e7f6ef;--ng:#b42318;--ng-l:#fdecea;--amber:#9a6700;--amber-l:#fff6e0}
*{box-sizing:border-box}html{color-scheme:light}body{margin:0;background:#fff;color:var(--ink);font:15.5px/1.75 "Noto Sans JP","Hiragino Sans","Yu Gothic",system-ui,sans-serif}
a{color:var(--blue);overflow-wrap:anywhere}.wrap{max-width:980px;margin:0 auto;padding:0 16px;min-width:0}
header.top{border-bottom:1px solid var(--line);background:#fff}header.top .wrap{display:flex;align-items:center;gap:6px 16px;flex-wrap:wrap;min-height:58px;padding-top:6px;padding-bottom:6px}
.brand{display:flex;align-items:center;gap:10px;color:var(--navy);text-decoration:none;font-weight:800;font-size:16px;line-height:1.3}.brand img{width:34px;height:34px;object-fit:contain;flex:none}.brand small{display:block;color:var(--blue);font-size:11.5px;font-weight:700}
nav.menu{display:flex;flex-wrap:wrap;gap:2px 14px;font-size:14px;font-weight:700}nav.menu a{color:var(--sub);text-decoration:none}
.promo{margin-left:auto;display:flex;flex-wrap:wrap;gap:2px 12px;font-size:13px;font-weight:800}.promo a{color:#b45309;text-decoration:none}
main{padding:10px 0 40px}h1{font-size:clamp(22px,4vw,30px);line-height:1.4;color:var(--navy);margin:18px 0 8px;text-wrap:balance}
h2{font-size:19px;color:var(--navy);margin:30px 0 10px;border-left:5px solid var(--blue);padding-left:10px}h3{font-size:16px;color:var(--navy);margin:18px 0 6px}
.lead{color:var(--sub);margin:0 0 14px}.panel{background:var(--bg);border:1px solid var(--line);border-radius:12px;padding:16px;margin:12px 0;min-width:0}
.big{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,200px),1fr));gap:10px;margin:12px 0}.big div{background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px 14px;min-width:0}
.big .k{display:block;font-size:12.5px;color:var(--sub);font-weight:700}.big .v{display:block;font-size:24px;font-weight:800;color:var(--navy);line-height:1.3;font-variant-numeric:tabular-nums}.big .s{display:block;font-size:12.5px;color:var(--sub)}
.ok{color:var(--ok)}.ng{color:var(--ng)}.box-ok{background:var(--ok-l);border:1px solid #b7e4cf;border-radius:10px;padding:10px 14px}.box-ng{background:var(--ng-l);border:1px solid #f3c2bd;border-radius:10px;padding:10px 14px}
.note{background:var(--amber-l);border-left:4px solid var(--amber);border-radius:8px;padding:10px 12px;font-size:14px;margin:10px 0}.src{font-size:12.5px;color:var(--sub)}
label{font-weight:700;font-size:14px;display:block;margin:10px 0 4px}select,input,textarea{font:inherit;font-size:16px;padding:9px 11px;border:2px solid var(--line);border-radius:10px;max-width:100%;background:#fff}
textarea{width:100%;min-height:150px;font-variant-numeric:tabular-nums}.row{display:flex;flex-wrap:wrap;gap:10px 18px}.row>div{min-width:0}
.btn{display:inline-block;background:var(--blue);color:#fff;border:0;border-radius:10px;padding:10px 18px;font-weight:700;text-decoration:none;cursor:pointer;font-size:15px}.btn.sub{background:#fff;color:var(--blue);border:2px solid var(--blue)}
.tbl{overflow-x:auto;-webkit-overflow-scrolling:touch}table{border-collapse:collapse;width:100%;font-size:14px}th,td{border-bottom:1px solid var(--line);padding:7px 8px;text-align:left;vertical-align:top}th{color:var(--sub);white-space:nowrap}
td.n{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}.tbl table{min-width:560px}
.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,260px),1fr));gap:10px}.card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px 14px;min-width:0}.card b{color:var(--navy)}.card p{margin:4px 0 8px;font-size:14px;color:var(--sub)}.card .a{display:flex;gap:10px;flex-wrap:wrap;font-size:14px;font-weight:700}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,150px),1fr));gap:8px}.grid a{display:block;background:#fff;border:1px solid var(--line);border-radius:10px;padding:8px 10px;text-decoration:none;color:var(--ink)}.grid a b{color:var(--navy)}.grid a small{display:block;color:var(--sub);font-size:12px}
details{border-bottom:1px solid var(--line);padding:8px 0}summary{cursor:pointer;font-weight:700}
#out{margin-top:14px}#out .err{color:var(--ng);font-weight:700}
footer{margin-top:40px;border-top:1px solid var(--line);padding:18px 0;font-size:13px;color:var(--sub)}footer p{margin:6px 0}
.xshare{display:inline-flex;align-items:center;gap:8px;background:#0f1419;color:#fff;text-decoration:none;font-weight:800;font-size:14px;border-radius:999px;padding:8px 16px}
</style></head><body>';
    echo '<header class="top"><div class="wrap"><a class="brand" href="' . u('/') . '"><img src="https://exbridge.jp/images/logo-mark-128.png" width="34" height="34" alt="株式会社エクスブリッジ"><span>' . h(NAME) . '<small>最低賃金 ' . FY . '・業務改善助成金</small></span></a>';
    echo '<nav class="menu"><a href="' . u('/') . '">計算する</a><a href="' . u('/josei/') . '">業務改善助成金</a><a href="' . u('/about') . '">出典と計算</a></nav>';
    echo '<!--kurage-only--><span class="promo"><a href="https://exbridge.jp/ai-it-komon.html?ref=kchingin-head-komon" target="_blank" rel="noopener">AI-IT顧問</a><a href="https://kurage.exbridge.jp/vibe-customize.html?ref=kchingin-head-vibe" target="_blank" rel="noopener">バイブカスタマイズ</a><a href="https://kurage.exbridge.jp/reseller.html?ref=kchingin-head-reseller" target="_blank" rel="noopener">販売代理店募集</a></span><!--/kurage-only-->';
    echo '</div></header><main class="wrap">' . $body . '</main>';
    echo '<footer><div class="wrap">';
    echo '<p>出典：厚生労働省「<a href="' . SRC_MW . '" rel="noopener">令和8年度 地域別最低賃金 答申状況</a>」「<a href="' . SRC_JOSEI . '" rel="noopener">令和8年度業務改善助成金のご案内</a>」（' . jd(CHECKED) . '確認）。発効日は答申後の手続きで変わることがあります。</p>';
    echo '<p>このページは計算の目安です。助成金の要件・金額は労働局の審査で決まります。申請は事業所の所在地の都道府県労働局へ。入力した時給はブラウザの中だけで計算し、送信・保存しません。</p>';
    echo '<p><a href="https://exbridge.jp/?ref=kchingin">株式会社エクスブリッジ</a>（名古屋・業務システム開発）・<a href="https://exbridge.jp/ai-agent-automation.html?ref=kchingin-foot">AIエージェントで業務を自動化する</a>・<a href="' . SITE . '/khojokin.php/?ref=kchingin">Kurage 補助金ナビ</a>・<a href="' . SITE . '/ktoriteki.php/?ref=kchingin">Kurage 取適法チェック</a>・<a href="' . SITE . '/kseidocal.php/?ref=kchingin">経営者の制度カレンダー</a></p>';
    echo '</div></footer>';
    echo '<img src="' . SITE . '/simpletrack.php?t=img&url=' . rawurlencode($url) . '&ref=' . rawurlencode((string)($_GET['ref'] ?? '')) . '" width="1" height="1" alt="" aria-hidden="true" style="position:absolute;left:-9999px">';
    if (($_SERVER['HTTP_HOST'] ?? '') === 'kurage.exbridge.jp') echo '<script src="https://kurage.exbridge.jp/partner-bar.js" defer></script>';
    echo '</body></html>';
}

// ---------------- 部品 ----------------
/** 入力例：改定前の最低賃金ちょうどの人が2人いる、よくある小さな事業場（人が書き換えるまで都道府県に合わせて差し替える） */
function sample_wages(string $c): array {
    [$new, $old] = MW[$c];
    return array_map('strval', [$old, $old, $old + 15, $old + 30, $new, $new + 30, $new + 80]);
}
function calc_form(string $sel = '23'): string {
    $o = '';
    foreach (PREFS as $c => $n) { $c = sprintf('%02d', $c); $o .= '<option value="' . $c . '"' . ($c === $sel ? ' selected' : '') . '>' . h($n) . '（' . number_format(MW[$c][0]) . '円）</option>'; }
    return '<div class="panel" id="calc">'
        . '<div class="row"><div><label for="pref">事業所の都道府県</label><select id="pref">' . $o . '</select></div>'
        . '<div><label for="hours">1人あたり月の労働時間</label><input id="hours" type="number" min="1" max="300" value="120" style="width:7em"> 時間</div>'
        . '<div><label for="size">事業場の規模</label><select id="size"><option value="1">30人未満</option><option value="0">30人以上</option></select></div></div>'
        . '<label for="wages">従業員の時給（1行に1人。「山田 1150」のように名前を付けても、数字だけでも可）</label>'
        . '<textarea id="wages" spellcheck="false">' . implode("\n", sample_wages($sel)) . '</textarea>'
        . '<div class="row"><div><label for="invest">導入を考えている設備・システムの費用（税抜）</label><input id="invest" type="number" min="0" step="10000" value="1000000" style="width:11em"> 円</div>'
        . '<div><label for="toku">特例事業者（物価高騰等で利益率が3ポイント以上低下）</label><select id="toku"><option value="0">いいえ・分からない</option><option value="1">はい</option></select></div></div>'
        . '<p style="margin-top:12px"><button class="btn" id="go" type="button">計算する</button> <span class="src">月給の人は、月給÷1か月平均の所定労働時間で時給にしてから入れてください。数字はこの画面の中だけで計算し、送信しません。</span></p>'
        . '<div id="out" aria-live="polite"></div></div>';
}

function calc_js(): string {
    $mw = []; foreach (MW as $c => $v) { $p = pref($c); $mw[$p['code']] = ['name' => $p['name'], 'new' => $p['new'], 'old' => $p['old'], 'eff' => $p['eff'], 'deadline' => $p['deadline'], 'open' => $p['open'], 'left' => $p['left'], 'in_effect' => $p['in_effect']]; }
    $tools = array_map(fn($t) => ['what' => $t[0], 'name' => $t[1], 'id' => $t[2], 'demo' => $t[3], 'why' => $t[4]], TOOLS);
    $data = json_encode(['mw' => $mw, 'cap' => JOSEI_CAP, 'line' => JOSEI_RATE_LINE, 'tools' => $tools, 'base' => BASE], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return <<<JS
<script>
(function(){
var D={$data};
function yen(v){return Math.round(v).toLocaleString('ja-JP')+'円'}
function man(v){return (v/10000).toLocaleString('ja-JP')+'万円'}
function jd(s){var a=s.split('-');return (+a[1])+'月'+(+a[2])+'日'}
function esc(s){return String(s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function parse(t){var out=[];t.split(/\\r?\\n/).forEach(function(l){l=l.replace(/[０-９]/g,function(c){return String.fromCharCode(c.charCodeAt(0)-65248)}).replace(/[,，円]/g,'');var m=l.match(/(\\d+(?:\\.\\d+)?)\\s*$/);if(m){var v=parseFloat(m[1]);if(v>=300&&v<=10000)out.push({label:l.replace(m[0],'').trim(),w:v})}});return out}
function bucket(n,toku){if(n>=10&&toku)return '10';if(n>=8)return '8';if(n>=6)return '6';if(n>=4)return '4';if(n>=2)return '2';return '1'}
function run(){
  var c=document.getElementById('pref').value,P=D.mw[c],H=+document.getElementById('hours').value||0,small=document.getElementById('size').value==='1',
      inv=+document.getElementById('invest').value||0,toku=document.getElementById('toku').value==='1',ws=parse(document.getElementById('wages').value),o=document.getElementById('out');
  if(!ws.length){o.innerHTML='<p class="err">時給を1行に1人ずつ入れてください（300〜10,000円の数字）。</p>';return}
  var M=P.new,below=ws.filter(function(x){return x.w<M}),gap=below.reduce(function(s,x){return s+(M-x.w)},0),mon=gap*H,L=Math.min.apply(null,ws.map(function(x){return x.w}));
  var h='<h3>'+esc(P.name)+'の最低賃金：'+M.toLocaleString('ja-JP')+'円（'+jd(P.eff)+(P.in_effect?'から適用中':'から')+'）</h3>';
  h+='<div class="big"><div><span class="k">新しい最低賃金を下回る人</span><span class="v">'+below.length+'人</span><span class="s">'+ws.length+'人中</span></div>'
    +'<div><span class="k">最低賃金まで上げると、月の人件費の増加</span><span class="v">'+yen(mon)+'</span><span class="s">1人月'+H+'時間で計算（社会保険料などは含まない）</span></div>'
    +'<div><span class="k">年間では</span><span class="v">'+yen(mon*12)+'</span><span class="s">12か月分</span></div></div>';
  if(below.length){h+='<div class="tbl"><table><tr><th>従業員</th><th>今の時給</th><th>不足</th><th>月の増加</th></tr>'+below.map(function(x,i){return '<tr><td>'+(esc(x.label)||('#'+(i+1)))+'</td><td class="n">'+yen(x.w)+'</td><td class="n">'+yen(M-x.w)+'</td><td class="n">'+yen((M-x.w)*H)+'</td></tr>'}).join('')+'</table></div>'}
  else{h+='<p class="box-ok">全員が新しい最低賃金以上です。</p>'}
  h+='<h3>業務改善助成金（令和8年度）</h3>';
  if(!P.open){
    h+='<p class="box-ng">'+esc(P.name)+'の令和8年度の申請期間は、'+jd(P.deadline)+'までで終わりました。申請期間は「新しい最低賃金が発効する前日」と「11月30日」の早い方までです。</p>'
     +'<p>この先は、増える人件費を<b>業務の時間を減らして取り戻す</b>ことになります。次の年度の受付が始まったら、引き上げと設備投資の計画を立ててから申請します（賃上げも設備の導入も、交付決定の後でないと対象になりません）。</p>';
  }else if(L>=M){
    h+='<p class="box-ng">いちばん低い時給（'+yen(L)+'）が、すでに新しい最低賃金（'+yen(M)+'）以上です。令和8年度の業務改善助成金は「事業場内最低賃金が令和8年度の地域別最低賃金未満」の事業場が対象なので、この一覧では対象になりません。</p>';
  }else{
    var rate=L<D.line?0.8:0.75,rows='';
    [50,70,90].forEach(function(cs){
      var F=Math.max(L+cs,M),n=ws.filter(function(x){return F-x.w>=cs}).length,b=bucket(n,toku),cap=D.cap[cs][b][small?1:0],sub=Math.min(inv*rate,cap),
          add=ws.reduce(function(s,x){return s+Math.max(0,F-x.w)},0)*H*12;
      rows+='<tr><td>'+cs+'円コース</td><td class="n">'+yen(F)+'</td><td class="n">'+n+'人</td><td class="n">'+man(cap)+'</td><td class="n"><b>'+yen(sub)+'</b></td><td class="n">'+yen(inv-sub)+'</td><td class="n">'+yen(add)+'</td></tr>';
    });
    h+='<p class="box-ok"><b>申請できます：あと'+P.left+'日（'+jd(P.deadline)+'まで）</b>。賃金の引き上げは、新しい最低賃金が発効する前日（'+jd(P.deadline)+'）までに済ませる必要があります。</p>'
     +'<p>今いちばん低い時給は'+yen(L)+'なので、助成率は<b>'+(rate===0.8?'4/5':'3/4')+'</b>（1,050円未満は4/5）。設備・システムの費用 '+yen(inv)+' で計算しました。</p>'
     +'<div class="tbl"><table><tr><th>コース</th><th>引上げ後の最低時給</th><th>人数に入る人</th><th>助成上限</th><th>助成額の目安</th><th>自己負担</th><th>年間の人件費の増加</th></tr>'+rows+'</table></div>'
     +'<p class="src">「人数に入る人」は、コースの金額以上に時給が上がる人（週20時間以上・雇用保険に入っている・雇い入れから6か月以上の人に限られます）。10人以上の上限区分は特例事業者だけ。助成額は「費用×助成率」と「上限」の安い方。交付決定の前に買った設備は対象外です。</p>';
  }
  h+='<h3>人件費の増加を、業務の時間を減らして取り戻す</h3><p>業務改善助成金の公式の案内には、対象経費の例として「POSレジシステム導入」「顧客管理情報のシステム化」が挙がっています。当社の業務システムは自社のサーバーに置く買い切り型で、月額はかかりません（対象になるかは労働局の審査で決まります）。</p><div class="cards">'
    +D.tools.map(function(t){return '<div class="card"><b>'+esc(t.what)+'</b><p>'+esc(t.name)+'：'+esc(t.why)+'</p><div class="a"><a href="https://kappstore.exbridge.jp/app.php?id='+t.id+'&amp;ref=kchingin-calc" target="_blank" rel="noopener">商品ページ</a><a href="'+t.demo+'?ref=kchingin-calc" target="_blank" rel="noopener">デモ</a></div></div>'}).join('')
    +'</div><p style="margin-top:12px"><a class="btn sub" href="https://exbridge.jp/ai-it-komon.html?ref=kchingin-calc" target="_blank" rel="noopener">どの業務から減らすか相談する（AI-IT顧問）</a></p>';
  o.innerHTML=h;
}
var dirty=false,ta=document.getElementById('wages');ta.addEventListener('input',function(){dirty=true});
document.getElementById('go').addEventListener('click',run);
document.getElementById('pref').addEventListener('change',function(){if(!dirty){var P=D.mw[this.value];ta.value=[P.old,P.old,P.old+15,P.old+30,P.new,P.new+30,P.new+80].join('\\n')}run()});
run();
})();
</script>
JS;
}

function pref_table(): string {
    $r = '';
    foreach (PREFS as $c => $n) {
        $p = pref($c);
        $r .= '<tr><td><a href="' . u('/p/' . $p['code'] . '/') . '">' . h($n) . '</a></td><td class="n"><b>' . number_format($p['new']) . '円</b></td><td class="n">+' . $p['up'] . '円</td><td>' . jd($p['eff']) . '</td><td>' . ($p['open'] ? '<span class="ok">' . jd($p['deadline']) . 'まで（あと' . $p['left'] . '日）</span>' : '<span class="ng">締切済み（' . jd($p['deadline']) . '）</span>') . '</td></tr>';
    }
    return '<div class="tbl"><table><tr><th>都道府県</th><th>最低賃金</th><th>引上げ</th><th>発効日</th><th>業務改善助成金の申請</th></tr>' . $r . '</table></div>';
}

function faq(array $qa): array {
    $html = '';
    $ld = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => []];
    foreach ($qa as [$q, $a]) {
        $html .= '<details><summary>' . h($q) . '</summary><p>' . h($a) . '</p></details>';
        $ld['mainEntity'][] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
    }
    return [$html, $ld];
}

function xshare(string $text, string $path): string {
    $q = http_build_query(['text' => $text, 'url' => BASE . $path . '?ref=x-share-kchingin', 'hashtags' => '最低賃金']);
    return '<p><a class="xshare" href="https://x.com/intent/post?' . h($q) . '" target="_blank" rel="noopener">𝕏 このページをXで共有</a></p>';
}

// ---------------- ルーティング ----------------
$path = $_SERVER['PATH_INFO'] ?? '';
if ($path === '' && !str_ends_with((string)($_SERVER['REQUEST_URI'] ?? ''), '/') && !str_contains((string)($_SERVER['REQUEST_URI'] ?? ''), '?')) {
    header('Location: ' . BASE . '/', true, 302); exit;   // 301 は使わない
}

if ($path === '/robots.txt') { header('Content-Type: text/plain; charset=UTF-8'); echo "User-agent: *\nAllow: /\nSitemap: " . u('/sitemap.xml') . "\n"; exit; }

if ($path === '/sitemap.xml') {
    header('Content-Type: application/xml; charset=UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach (array_merge(['/', '/josei/', '/about'], array_map(fn($c) => '/p/' . sprintf('%02d', $c) . '/', array_keys(PREFS))) as $p) { echo '<url><loc>' . h(u($p)) . '</loc><lastmod>' . CHECKED . '</lastmod></url>'; }
    echo '</urlset>'; exit;
}

if ($path === '/llms.txt') {
    header('Content-Type: text/plain; charset=UTF-8');
    $l = ["# " . NAME, '', '> ' . FY . 'の地域別最低賃金（47都道府県の改定額・発効日）と、業務改善助成金の申請期限・助成額を、事業者の側から計算するサイト。従業員の時給の一覧を入れると、新しい最低賃金を下回る人数、月と年の人件費の増加、業務改善助成金のコース別の助成額の目安が出る。計算はブラウザの中だけで行う。株式会社エクスブリッジ製。', '',
          '## 最低賃金（' . FY . '・時間額・発効日）'];
    foreach (PREFS as $c => $n) { $p = pref($c); $l[] = '- ' . $n . ': ' . number_format($p['new']) . '円（+' . $p['up'] . '円・' . jd($p['eff']) . '発効予定）業務改善助成金の申請は' . jd($p['deadline']) . 'まで'; }
    $l = array_merge($l, ['', '## 業務改善助成金（' . FY . '）の要点',
        '- 申請期間: 2026年9月1日〜「申請事業所の都道府県の地域別最低賃金の発効日の前日」と「11月30日」の早い方',
        '- 事業場内最低賃金を50円・70円・90円以上引き上げ、生産性向上の設備投資等を行う。賃上げも設備投資も交付決定の後に行うものが対象',
        '- 助成率: 引上げ前の事業場内最低賃金が1,050円未満は4/5、1,050円以上は3/4。助成上限は30万〜600万円（コース・人数・事業場規模による）',
        '- 対象: 事業場内最低賃金が令和8年度の地域別最低賃金未満の中小企業・小規模事業者。事業完了期限は2027年1月31日',
        '', '## 注意', '- 発効日は答申後の手続きで変わることがある。要件・金額は労働局の審査で決まる。申請書の作成はしない。',
        '', '## ページ', '- 計算: ' . u('/'), '- 業務改善助成金: ' . u('/josei/'), '- 都道府県ごと: ' . u('/p/23/') . '（愛知県）など', '- 出典と計算: ' . u('/about')]);
    echo implode("\n", $l) . "\n"; exit;
}

$seg = array_values(array_filter(explode('/', $path), fn($x) => $x !== ''));

// 都道府県ページ
if (($seg[0] ?? '') === 'p' && isset($seg[1]) && isset(PREFS[$seg[1]])) {
    $p = pref($seg[1]); $n = $p['name']; $url = u('/p/' . $p['code'] . '/');
    $title = $n . 'の最低賃金 2026は' . number_format($p['new']) . '円｜' . jd($p['eff']) . 'から';
    $desc = $n . 'の最低賃金は' . jd($p['eff']) . 'から時間額' . number_format($p['new']) . '円（' . number_format($p['old']) . '円から' . $p['up'] . '円引上げ）。従業員の時給を入れると、下回る人数と人件費の増加、業務改善助成金の申請期限と助成額の目安を計算できます。';
    $body = '<h1>' . h($n) . 'の最低賃金は ' . number_format($p['new']) . '円（' . jd($p['eff']) . 'から）</h1>';
    $body .= '<p class="lead">' . FY . 'の改定。' . number_format($p['old']) . '円から' . $p['up'] . '円の引上げです' . ($p['in_effect'] ? '（すでに適用中）' : '') . '。</p>';
    $body .= '<div class="big"><div><span class="k">新しい最低賃金（時間額）</span><span class="v">' . number_format($p['new']) . '円</span><span class="s">改定前 ' . number_format($p['old']) . '円</span></div>'
          . '<div><span class="k">発効日（予定）</span><span class="v">' . jd($p['eff']) . '</span><span class="s">' . ($p['in_effect'] ? '適用中' : 'あと' . days_between(today(), $p['eff']) . '日') . '</span></div>'
          . '<div><span class="k">業務改善助成金の申請</span><span class="v" style="font-size:18px">' . ($p['open'] ? '<span class="ok">' . jd($p['deadline']) . 'まで</span>' : '<span class="ng">締切済み</span>') . '</span><span class="s">' . ($p['open'] ? 'あと' . $p['left'] . '日' : jd($p['deadline']) . 'まででした') . '</span></div></div>';
    $body .= '<p>' . josei_status($p) . '</p>';
    $body .= xshare($n . 'の最低賃金は' . jd($p['eff']) . 'から' . number_format($p['new']) . '円（+' . $p['up'] . '円）。従業員の時給を入れると、下回る人数と人件費の増加、業務改善助成金の目安が出ます', '/p/' . $p['code'] . '/');
    $body .= '<h2>うちの会社で計算する</h2>' . calc_form($p['code']);
    $h = (int)round($p['up'] * 120 * 12);
    [$fh, $fl] = faq([
        [$n . 'の最低賃金はいくらですか？', FY . 'は時間額' . number_format($p['new']) . '円で、' . jd($p['eff']) . 'に発効する予定です（改定前は' . number_format($p['old']) . '円）。'],
        ['最低賃金が上がると、人件費はいくら増えますか？', '最低賃金ちょうどで働く人が1人、月120時間働く場合、' . $p['up'] . '円の引上げで月' . number_format($p['up'] * 120) . '円、年' . number_format($h) . '円増えます（社会保険料などは含みません）。従業員の時給を入れると、会社全体の増加を計算できます。'],
        [$n . 'で業務改善助成金はまだ申請できますか？', $p['open'] ? jd($p['deadline']) . 'まで申請できます。賃金の引上げは新しい最低賃金の発効日の前日までに済ませ、設備投資は交付決定の後に行います。' : '令和8年度の申請は' . jd($p['deadline']) . 'までで終わりました。申請期間は「発効日の前日」と「11月30日」の早い方までです。'],
        ['月給の人は、どう比べればいいですか？', '月給を1か月平均の所定労働時間で割って時給にし、最低賃金と比べます。通勤手当・家族手当・時間外手当などは含めません。'],
    ]);
    $body .= '<h2>よくある質問</h2>' . $fh;
    $near = ''; $ci = (int)$p['code'];
    foreach ([$ci - 1, $ci + 1] as $x) { if ($x >= 1 && $x <= 47) { $k = sprintf('%02d', $x); $near .= '<a href="' . u('/p/' . $k . '/') . '"><b>' . h(PREFS[$k]) . '</b><small>' . number_format(MW[$k][0]) . '円・' . jd(MW[$k][2]) . '</small></a>'; } }
    $body .= '<h2>ほかの都道府県</h2><div class="grid">' . $near . '<a href="' . u('/#list') . '"><b>47都道府県の一覧</b><small>最低賃金と申請期限</small></a></div>';
    $body .= calc_js();
    page($title, $desc, $url, $body, [$fl], [[$n, $url]]);
    exit;
}

if (($seg[0] ?? '') === 'josei') {
    $url = u('/josei/');
    $open = array_filter(array_map('pref', array_keys(PREFS)), fn($p) => $p['open']);
    usort($open, fn($a, $b) => strcmp($a['deadline'], $b['deadline']));
    $title = '業務改善助成金 2026の申請期限は都道府県で違う｜締切一覧';
    $desc = '令和8年度の業務改善助成金は、申請期間が「地域別最低賃金の発効日の前日」と「11月30日」の早い方まで。10月1日発効の都府県はすでに締切。まだ申請できる県と締切日、助成上限・助成率、時給からの計算をまとめました。';
    $body = '<h1>業務改善助成金（令和8年度）の申請期限は、都道府県ごとに違う</h1>';
    $body .= '<p class="lead">申請期間は <b>2026年9月1日から、事業所のある都道府県の新しい最低賃金が発効する前日まで（遅くとも11月30日まで）</b>です。10月1日に発効した都府県は、9月30日で締め切られました。</p>';
    $rows = '';
    foreach ($open as $p) { $rows .= '<tr><td><a href="' . u('/p/' . $p['code'] . '/') . '">' . h($p['name']) . '</a></td><td><b>' . jd($p['deadline']) . '（' . wd($p['deadline']) . '）</b></td><td class="n">あと' . $p['left'] . '日</td><td class="n">' . number_format($p['new']) . '円（' . jd($p['eff']) . '）</td></tr>'; }
    $body .= '<h2>' . date('n月j日') . '時点で、まだ申請できる都道府県（' . count($open) . '）</h2>';
    $body .= $rows ? '<div class="tbl"><table><tr><th>都道府県</th><th>申請の締切</th><th>残り</th><th>新しい最低賃金</th></tr>' . $rows . '</table></div>' : '<p class="box-ng">令和8年度の申請期間は、すべての都道府県で終わりました。</p>';
    $cap = '';
    foreach (JOSEI_CAP as $cs => $tb) {
        foreach ($tb as $k => [$a, $b]) { $lab = ['1' => '1人', '2' => '2〜3人', '4' => '4〜5人', '6' => '6〜7人', '8' => '8人以上', '10' => '10人以上（特例事業者のみ）'][$k]; $cap .= '<tr><td>' . $cs . '円コース</td><td>' . $lab . '</td><td class="n">' . man($b) . '</td><td class="n">' . man($a) . '</td></tr>'; }
    }
    $body .= '<h2>助成上限と助成率</h2><p>事業場内でいちばん低い時給（事業場内最低賃金）を、コースの金額以上引き上げた人数で上限が決まります。助成率は、引上げ前の事業場内最低賃金が <b>1,050円未満なら4/5、1,050円以上なら3/4</b>。助成額は「設備投資等の費用×助成率」と「上限」の安い方です。</p>';
    $body .= '<div class="tbl"><table><tr><th>コース</th><th>引き上げる人数</th><th>30人未満の事業者</th><th>それ以外</th></tr>' . $cap . '</table></div>';
    $body .= '<h2>おもな要件</h2><ul><li>中小企業・小規模事業者で、事業場内最低賃金が令和8年度の地域別最低賃金<b>未満</b>であること</li><li>賃金の引上げも設備投資も、<b>交付決定の後</b>（賃上げは申請の後）に行うもの。発効日に合わせて引き上げる場合は、発効日の前日までに引き上げる</li><li>引き上げる対象は、週20時間以上で雇用保険に入っている人。事業場内最低賃金は雇い入れ後6か月を過ぎた人で見る</li><li>事業完了期限は2027年1月31日（納品・支払い・賃上げをすべて）</li><li>対象経費の例（公式）：POSレジシステムの導入、送迎用の車両、業務フローの見直しのコンサルティング、<b>顧客管理情報のシステム化</b></li></ul>';
    $body .= '<p class="note">厚労省は「委託を受けたと装って助成金を勧誘する事業者」への注意を呼びかけています。申請は事業所の所在地の都道府県労働局へ直接行います。当社は計算と業務システムの側だけを扱い、申請書の作成はしません。</p>';
    $body .= '<p>出典：<a href="' . SRC_JOSEI_PAGE . '" rel="noopener">厚生労働省 業務改善助成金</a>・<a href="' . SRC_JOSEI . '" rel="noopener">令和8年度業務改善助成金のご案内（PDF）</a></p>';
    $body .= '<h2>時給の一覧から計算する</h2>' . calc_form($open ? $open[0]['code'] : '23') . calc_js();
    [$fh, $fl] = faq([
        ['業務改善助成金 2026の申請期限はいつですか？', '2026年9月1日から、事業所のある都道府県の地域別最低賃金の発効日の前日か11月30日の早い方までです。10月1日に発効した都府県は9月30日で締め切られています。'],
        ['業務改善助成金でシステムやソフトウェアは買えますか？', '生産性向上に資する設備投資等が対象で、公式の案内には「顧客管理情報のシステム化」「POSレジシステム導入」が例として挙がっています。対象になるかは労働局の審査で決まります。パソコン・スマホ等は、物価高騰等要件に当たる特例事業者の新規導入に限られます。'],
        ['助成率はいくらですか？', '引上げ前の事業場内最低賃金が1,050円未満なら4/5、1,050円以上なら3/4です。'],
    ]);
    $body .= '<h2>よくある質問</h2>' . $fh;
    page($title, $desc, $url, $body, [$fl], [['業務改善助成金', $url]]);
    exit;
}

if (($seg[0] ?? '') === 'about') {
    $url = u('/about');
    $body = '<h1>出典と計算のしかた</h1>'
        . '<h2>出典</h2><ul><li>地域別最低賃金：厚生労働省「<a href="' . SRC_MW . '" rel="noopener">令和8年度 地域別最低賃金 答申状況</a>」（改定額・発効日（予定））。発効日は答申後の異議申出の手続きで変わることがあります。</li><li>業務改善助成金：厚生労働省「<a href="' . SRC_JOSEI . '" rel="noopener">令和8年度業務改善助成金のご案内</a>」と<a href="' . SRC_JOSEI_PAGE . '" rel="noopener">制度のページ</a>（申請期間・助成上限・助成率）。</li></ul><p>' . jd(CHECKED) . 'に確認した数字です。</p>'
        . '<h2>計算のしかた</h2><ul><li>人件費の増加＝新しい最低賃金を下回る人ごとの不足額×1人あたり月の労働時間（社会保険料・賞与への影響は含みません）</li><li>業務改善助成金：今いちばん低い時給を「事業場内最低賃金」とし、50・70・90円のコースごとに、引上げ後の最低時給（コースの金額を足した額と新しい最低賃金の高い方）と、コースの金額以上に上がる人数から上限を引き、「費用×助成率」と比べて安い方を出します。</li><li>申請期限＝発効日の前日と11月30日の早い方。今日の日付で「申請できます／締切済み」を出します。</li></ul>'
        . '<h2>このサイトがしないこと</h2><p>助成金の申請書の作成・申請の代行はしません（労働局へ直接申請します）。計算は目安で、要件を満たすか・いくら出るかは労働局の審査で決まります。入力した時給はブラウザの中だけで計算し、サーバーへ送信・保存しません。</p>';
    page('出典と計算のしかた｜' . NAME, '最低賃金と業務改善助成金の計算に使った厚生労働省の資料（令和8年度の答申状況・業務改善助成金のご案内）と、人件費の増加・助成額の目安の計算方法。入力した時給は送信も保存もせず、ブラウザの中だけで計算します。', $url, $body, [], [['出典と計算', $url]]);
    exit;
}

if ($seg) { http_response_code(404); page('見つかりません｜' . NAME, '', BASE . '/', '<h1>見つかりません</h1><p><a class="btn" href="' . u('/') . '">入口へ戻る</a></p>'); exit; }

// トップ
$aichi = pref('23');
$open = array_filter(array_map('pref', array_keys(PREFS)), fn($p) => $p['open']);
$title = '最低賃金 2026の影響を計算｜人件費と業務改善助成金';
$desc = '令和8年度の最低賃金（全国平均1,177円）で、うちの会社は人件費がいくら増えるか。従業員の時給を入れると、下回る人数・月と年の増加額と、業務改善助成金の申請期限・助成額の目安を計算します。47都道府県の改定額と発効日つき。';
$body = '<h1>最低賃金が上がると、うちの人件費はいくら増えるか</h1>';
$body .= '<p class="lead">' . FY . 'の地域別最低賃金は、全国平均で1,177円（56円の引上げ）。10月1日から12月2日にかけて都道府県ごとに発効します。従業員の時給を入れると、新しい最低賃金を下回る人数、月と年の人件費の増加、業務改善助成金をまだ申請できるかと助成額の目安が出ます。</p>';
$body .= '<div class="big"><div><span class="k">愛知県</span><span class="v">' . number_format($aichi['new']) . '円</span><span class="s">' . jd($aichi['eff']) . 'から（+' . $aichi['up'] . '円）</span></div><div><span class="k">業務改善助成金をまだ申請できる都道府県</span><span class="v">' . count($open) . '</span><span class="s">' . date('n月j日') . '時点</span></div><div><span class="k">入力した時給</span><span class="v" style="font-size:18px">送信しません</span><span class="s">ブラウザの中だけで計算</span></div></div>';
$body .= calc_form('23');
$body .= xshare('最低賃金が上がると、うちの人件費はいくら増えるか。時給の一覧を入れると、下回る人数と増加額、業務改善助成金の目安が出ます（47都道府県）', '/');
$body .= '<h2 id="list">47都道府県の最低賃金と、業務改善助成金の申請期限</h2>' . pref_table();
$body .= '<p class="src">発効日は予定で、手続きにより変わることがあります。業務改善助成金の申請期間は「発効日の前日」と「11月30日」の早い方まで。<a href="' . u('/josei/') . '">要件と助成上限の一覧 →</a></p>';
[$fh, $fl] = faq([
    ['2026年の最低賃金はいくらですか？', FY . 'の地域別最低賃金は全国加重平均で1,177円（56円の引上げ）です。最も高いのは東京都の1,280円、愛知県は1,195円で10月1日から適用されています。'],
    ['最低賃金を下回ると、どうなりますか？', '最低賃金法で、使用者は最低賃金以上の賃金を支払う必要があります。下回る額で合意していても無効になり、最低賃金額で契約したものとみなされます。'],
    ['業務改善助成金はまだ申請できますか？', '令和8年度の申請期間は、都道府県の地域別最低賃金の発効日の前日か11月30日の早い方までです。10月1日に発効した都府県は締め切られ、発効が10月中旬以降の県はまだ申請できます。上の一覧で確認できます。'],
    ['入力した時給はどこかに送られますか？', '送られません。計算はブラウザの中だけで行い、サーバーへ送信も保存もしません。'],
]);
$body .= '<h2>よくある質問</h2>' . $fh;
$body .= calc_js();
page($title, $desc, BASE . '/', $body, [$fl]);
