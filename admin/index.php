<?php
/* Painel privado: lista os pedidos de avaliação, marca como contactado e exporta para CSV. */
date_default_timezone_set('Europe/Lisbon');
session_set_cookie_params(array('httponly' => true, 'samesite' => 'Strict', 'secure' => !empty($_SERVER['HTTPS'])));
session_start();
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');

$ficheiro = __DIR__ . '/../api/config.php';
if (!is_file($ficheiro)) { exit('Falta o ficheiro api/config.php (veja o guia).'); }
$cfg = require $ficheiro;

function h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];
function csrfOk() { return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']); }

$erro = '';
if (empty($cfg['admin_hash'])) {
    exit('Ainda não há palavra-passe. Abra /admin/gerar-hash.php, copie o resultado para api/config.php e apague esse ficheiro.');
}

// Terminar sessão
if (isset($_POST['sair']) && csrfOk()) { session_destroy(); header('Location: index.php'); exit; }

// Entrar
if (isset($_POST['entrar'])) {
    $tent = isset($_SESSION['tentativas']) ? (int) $_SESSION['tentativas'] : 0;
    if (!csrfOk() || $tent >= 8) {
        $erro = 'Demasiadas tentativas. Feche o browser e tente mais tarde.';
    } elseif (isset($_POST['p']) && password_verify((string) $_POST['p'], $cfg['admin_hash'])) {
        session_regenerate_id(true);
        $_SESSION['ok'] = true; $_SESSION['tentativas'] = 0;
        header('Location: index.php'); exit;
    } else {
        $_SESSION['tentativas'] = $tent + 1; sleep(1); $erro = 'Palavra-passe errada.';
    }
}

$autenticado = !empty($_SESSION['ok']);

function pagina($titulo, $corpo)
{
    echo '<!DOCTYPE html><html lang="pt-PT"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . h($titulo) . '</title>'
       . '<style>body{font:15px/1.5 system-ui,sans-serif;margin:0;background:#f3f5f9;color:#0b1220}main{max-width:1200px;margin:0 auto;padding:1.5rem}'
       . 'h1{font-size:1.4rem;margin:.2rem 0 1rem}table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px #0001}'
       . 'th,td{padding:.6rem .8rem;text-align:left;border-bottom:1px solid #e5e9f0;vertical-align:top;font-size:.9rem}th{background:#0d1730;color:#fff;font-weight:600}'
       . 'tr.feito td{opacity:.55}.bar{display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;justify-content:space-between;margin-bottom:1rem}'
       . 'a.b,button{font:inherit;cursor:pointer;border:0;border-radius:999px;padding:.45rem .9rem;background:#d7222b;color:#fff;text-decoration:none;display:inline-block}'
       . 'a.b.g,button.g{background:#0d1730}a.wa{background:#1faf5a}input[type=password]{font:inherit;padding:.6rem;width:100%;margin:.4rem 0 .8rem;border:1px solid #cdd5e2;border-radius:8px}'
       . '.login{max-width:360px;margin:12vh auto;background:#fff;padding:1.6rem;border-radius:16px;box-shadow:0 10px 30px #0001}.err{color:#b91c1c}.wrap{overflow-x:auto}</style></head><body><main>'
       . $corpo . '</main></body></html>';
}

if (!$autenticado) {
    pagina('Entrar', '<form class="login" method="post"><h1>Painel de pedidos</h1>'
        . ($erro ? '<p class="err">' . h($erro) . '</p>' : '')
        . '<input type="hidden" name="csrf" value="' . h($csrf) . '"><label>Palavra-passe<input type="password" name="p" required autofocus></label>'
        . '<button name="entrar" value="1" type="submit">Entrar</button></form>');
    exit;
}

try {
    $pdo = new PDO('mysql:host=' . $cfg['db_host'] . ';dbname=' . $cfg['db_name'] . ';charset=utf8mb4', $cfg['db_user'], $cfg['db_pass'],
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false));
} catch (Exception $e) {
    error_log('admin: ' . $e->getMessage());
    exit('Não foi possível ligar à base de dados. Confirme os dados em api/config.php.');
}

// Marcar / desmarcar como contactado
if (isset($_POST['toggle']) && csrfOk()) {
    $pdo->prepare('UPDATE pedidos SET contactado = 1 - contactado WHERE id = ?')->execute(array((int) $_POST['toggle']));
    header('Location: index.php' . (isset($_GET['pend']) ? '?pend=1' : '')); exit;
}

// Exportar CSV
if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pedidos-' . date('Y-m-d') . '.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, array('ID', 'Data', 'Nome', 'Telefone', 'Email', 'Tipo', 'Concelho', 'Freguesia', 'Área m2', 'Objetivo', 'Prazo', 'Contactado'), ';');
    foreach ($pdo->query('SELECT * FROM pedidos ORDER BY id DESC') as $r) {
        $linha = array($r['id'], $r['criado_em'], $r['nome'], $r['telefone'], $r['email'], $r['tipo_imovel'], $r['concelho'], $r['freguesia'], $r['area_m2'], $r['objetivo'], $r['prazo'], $r['contactado'] ? 'Sim' : 'Não');
        foreach ($linha as $i => $c) { if (is_string($c) && $c !== '' && strpos('=+-@', $c[0]) !== false) { $linha[$i] = "'" . $c; } }
        fputcsv($o, $linha, ';');
    }
    exit;
}

$soPendentes = isset($_GET['pend']);
$sql = 'SELECT * FROM pedidos' . ($soPendentes ? ' WHERE contactado = 0' : '') . ' ORDER BY id DESC LIMIT 300';
$linhas = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$total = (int) $pdo->query('SELECT COUNT(*) FROM pedidos')->fetchColumn();
$pend  = (int) $pdo->query('SELECT COUNT(*) FROM pedidos WHERE contactado = 0')->fetchColumn();

$corpo = '<div class="bar"><div><h1>Pedidos de avaliação</h1><span>' . $total . ' no total · ' . $pend . ' por contactar</span></div><div>'
    . ($soPendentes ? '<a class="b g" href="index.php">Ver todos</a> ' : '<a class="b g" href="index.php?pend=1">Só por contactar</a> ')
    . '<a class="b g" href="index.php?csv=1">Exportar CSV</a> '
    . '<form method="post" style="display:inline"><input type="hidden" name="csrf" value="' . h($csrf) . '"><button class="g" name="sair" value="1">Sair</button></form></div></div>';

if (!$linhas) {
    $corpo .= '<p>Ainda não há pedidos.</p>';
} else {
    $corpo .= '<div class="wrap"><table><tr><th>Data</th><th>Contacto</th><th>Imóvel</th><th>Localização</th><th>Objetivo</th><th></th></tr>';
    foreach ($linhas as $r) {
        $num = preg_replace('/^(00)?351/', '', preg_replace('/\D/', '', $r['telefone']));
        $corpo .= '<tr class="' . ($r['contactado'] ? 'feito' : '') . '"><td>' . h(date('d/m/Y H:i', strtotime($r['criado_em']))) . '</td>'
            . '<td><b>' . h($r['nome']) . '</b><br>' . h($r['telefone']) . ($r['email'] ? '<br>' . h($r['email']) : '') . '</td>'
            . '<td>' . h($r['tipo_imovel']) . ($r['area_m2'] ? '<br>' . h($r['area_m2']) . ' m²' : '') . '</td>'
            . '<td>' . h($r['concelho']) . ($r['freguesia'] ? '<br>' . h($r['freguesia']) : '') . '</td>'
            . '<td>' . h($r['objetivo']) . '<br><small>' . h($r['prazo']) . '</small></td>'
            . '<td><a class="b wa" href="https://wa.me/351' . h($num) . '" target="_blank" rel="noopener">WhatsApp</a> '
            . '<form method="post" style="display:inline"><input type="hidden" name="csrf" value="' . h($csrf) . '"><button class="g" name="toggle" value="' . (int) $r['id'] . '">'
            . ($r['contactado'] ? 'Reabrir' : 'Contactado') . '</button></form></td></tr>';
    }
    $corpo .= '</table></div>';
}
pagina('Pedidos', $corpo);
