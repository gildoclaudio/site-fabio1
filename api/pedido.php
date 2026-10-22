<?php
/* Recebe o pedido de avaliação do formulário e guarda-o na base de dados MySQL. */
date_default_timezone_set('Europe/Lisbon');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function responder($ok, $codigo = 200)
{
    http_response_code($codigo);
    echo json_encode(array('ok' => $ok));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { responder(false, 405); }

$ficheiro = __DIR__ . '/config.php';
if (!is_file($ficheiro)) { error_log('pedido.php: falta api/config.php'); responder(false, 500); }
$cfg = require $ficheiro;

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 8192) { responder(false, 413); }
$d = json_decode($raw, true);
if (!is_array($d)) { responder(false, 400); }

// Campo-armadilha para robôs: se vier preenchido, finge que correu bem e não guarda nada.
if (!empty($d['website'])) { responder(true); }

function limpa($v, $max)
{
    $v = is_string($v) ? trim($v) : '';
    $r = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $v);
    if ($r === null) { $r = ''; }
    return mb_substr($r, 0, $max, 'UTF-8');
}

$tipos     = array('Apartamento', 'Moradia', 'Terreno', 'Loja / espaço comercial', 'Escritório', 'Outro');
$concelhos = array('Amadora', 'Odivelas', 'Cascais', 'Oeiras', 'Sintra', 'Grande Lisboa');
$objetivos = array('Vender', 'Arrendar', 'Estou a avaliar opções');
$prazos    = array('O mais breve possível', 'Nos próximos 3 meses', 'Daqui a 3–6 meses', 'Estou só a recolher informação');

$nome      = limpa(isset($d['nome']) ? $d['nome'] : '', 120);
$telefone  = limpa(isset($d['telefone']) ? $d['telefone'] : '', 30);
$email     = limpa(isset($d['email']) ? $d['email'] : '', 160);
$tipo      = limpa(isset($d['tipo']) ? $d['tipo'] : '', 60);
$concelho  = limpa(isset($d['concelho']) ? $d['concelho'] : '', 40);
$freguesia = limpa(isset($d['freguesia']) ? $d['freguesia'] : '', 100);
$objetivo  = limpa(isset($d['objetivo']) ? $d['objetivo'] : '', 60);
$prazo     = limpa(isset($d['prazo']) ? $d['prazo'] : '', 80);
$consent   = !empty($d['consentimento']);

$soDigitos = preg_replace('/\D/', '', $telefone);
$soDigitos = preg_replace('/^(00)?351/', '', $soDigitos);

if (mb_strlen($nome, 'UTF-8') < 2)                      { responder(false, 422); }
if (!preg_match('/^[29]\d{8}$/', $soDigitos))           { responder(false, 422); }
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { responder(false, 422); }
if (!in_array($tipo, $tipos, true))                     { responder(false, 422); }
if (!in_array($concelho, $concelhos, true))             { responder(false, 422); }
if (!in_array($objetivo, $objetivos, true))             { responder(false, 422); }
if (!in_array($prazo, $prazos, true))                   { responder(false, 422); }
if (!$consent)                                          { responder(false, 422); }

$area = null;
if (isset($d['area']) && $d['area'] !== '') {
    $a = (int) $d['area'];
    if ($a > 0 && $a <= 1000000) { $area = $a; }
}

$ip     = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
$ipHash = hash('sha256', $ip . (isset($cfg['sal']) ? $cfg['sal'] : ''));
$agora  = date('Y-m-d H:i:s');
$limite = date('Y-m-d H:i:s', time() - 600);

try {
    $pdo = new PDO(
        'mysql:host=' . $cfg['db_host'] . ';dbname=' . $cfg['db_name'] . ';charset=utf8mb4',
        $cfg['db_user'],
        $cfg['db_pass'],
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false)
    );

    // Máximo de 5 pedidos por IP em 10 minutos
    $q = $pdo->prepare('SELECT COUNT(*) FROM pedidos WHERE ip_hash = ? AND criado_em > ?');
    $q->execute(array($ipHash, $limite));
    if ((int) $q->fetchColumn() >= 5) { responder(false, 429); }

    $ins = $pdo->prepare(
        'INSERT INTO pedidos (criado_em, nome, telefone, email, tipo_imovel, concelho, freguesia, area_m2, objetivo, prazo, consentimento, ip_hash)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
    );
    $ins->execute(array(
        $agora, $nome, $telefone,
        $email === '' ? null : $email,
        $tipo, $concelho,
        $freguesia === '' ? null : $freguesia,
        $area, $objetivo, $prazo, $ipHash
    ));
    responder(true);
} catch (Exception $e) {
    error_log('pedido.php: ' . $e->getMessage());
    responder(false, 500);
}
