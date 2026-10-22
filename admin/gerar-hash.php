<?php
/* Uso único: gera o "hash" da palavra-passe do painel. Depois de o colar no config.php, APAGUE ESTE FICHEIRO. */
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');
$cfg = is_file(__DIR__ . '/../api/config.php') ? require __DIR__ . '/../api/config.php' : array();
if (!empty($cfg['admin_hash'])) {
    http_response_code(403);
    exit('Já existe uma palavra-passe definida. Apague este ficheiro (admin/gerar-hash.php).');
}
$hash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['p']) && strlen($_POST['p']) >= 10) {
    $hash = password_hash($_POST['p'], PASSWORD_DEFAULT);
}
?><!DOCTYPE html>
<html lang="pt-PT"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>Gerar palavra-passe</title>
<style>body{font:16px/1.5 system-ui,sans-serif;max-width:560px;margin:3rem auto;padding:0 1rem}input,button{font:inherit;padding:.6rem;width:100%;margin:.4rem 0}code{display:block;word-break:break-all;background:#f1f3f7;padding:.8rem;border-radius:8px}</style></head><body>
<h1>Palavra-passe do painel</h1>
<form method="post" autocomplete="off"><label>Escolha uma palavra-passe (mínimo 10 caracteres)<input type="password" name="p" minlength="10" required></label><button type="submit">Gerar</button></form>
<?php if ($hash): ?>
<p>Copie este valor para <b>admin_hash</b> no ficheiro <b>api/config.php</b> e depois apague <b>admin/gerar-hash.php</b>:</p>
<code><?php echo htmlspecialchars($hash, ENT_QUOTES, 'UTF-8'); ?></code>
<?php endif; ?>
</body></html>
