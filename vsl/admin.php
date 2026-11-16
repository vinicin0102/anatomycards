<?php
declare(strict_types=1);
require __DIR__ . '/_lib.php';

/* ---------- sessão ---------- */

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('vsl_admin');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

const SESSION_IDLE = 2 * 3600;
if (!empty($_SESSION['vsl_ok']) && time() - (int) ($_SESSION['vsl_seen'] ?? 0) > SESSION_IDLE) {
    $_SESSION = [];
    session_regenerate_id(true);
}
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];

function csrf_ok(): bool
{
    return is_string($_POST['csrf'] ?? null) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function redirect_self(): void
{
    header('Location: admin.php', true, 303);
    exit;
}

/* ---------- limite de tentativas de login (5 a cada 15 min por IP) ---------- */

const LOGIN_MAX = 5;
const LOGIN_WINDOW = 900;

function login_attempts(callable $fn): mixed
{
    try {
        vsl_ensure_storage(dirname(VSL_LOGIN_FILE));
    } catch (RuntimeException $ex) {
        return $fn([])[0];
    }
    $fh = @fopen(VSL_LOGIN_FILE, 'c+');
    if (!$fh) {
        return $fn([])[0];    // sem storage gravável: não bloqueia o login
    }
    flock($fh, LOCK_EX);
    $all = json_decode((string) stream_get_contents($fh), true) ?: [];
    $now = time();
    $all = array_filter($all, fn($r) => $now - (int) ($r['first'] ?? 0) < LOGIN_WINDOW);
    [$result, $all] = $fn($all);
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, (string) json_encode($all));
    flock($fh, LOCK_UN);
    fclose($fh);
    return $result;
}

$cfg  = vsl_config();
$hash = (string) ($cfg['admin_password_hash'] ?? '');
$ipKey = hash('sha256', vsl_client_ip());

/* ---------- ações ---------- */

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Upload maior que post_max_size: o PHP descarta o POST inteiro.
if ($method === 'POST' && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('err', 'O envio passou do limite do servidor (post_max_size = ' . ini_get('post_max_size') . '). Nada foi salvo. Use um vídeo menor ou um link (YouTube, Vimeo, Panda, VTurb).');
    redirect_self();
}

if ($method === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if ($hash === '' || !csrf_ok()) {
        flash('err', 'Sessão expirada. Tente de novo.');
        redirect_self();
    }
    $blocked = login_attempts(function ($all) use ($ipKey) {
        return [($all[$ipKey]['count'] ?? 0) >= LOGIN_MAX, $all];
    });
    if ($blocked) {
        flash('err', 'Muitas tentativas. Aguarde 15 minutos.');
        redirect_self();
    }
    if (password_verify((string) ($_POST['password'] ?? ''), $hash)) {
        login_attempts(function ($all) use ($ipKey) { unset($all[$ipKey]); return [null, $all]; });
        session_regenerate_id(true);
        $_SESSION['vsl_ok'] = true;
        $_SESSION['vsl_seen'] = time();
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    } else {
        login_attempts(function ($all) use ($ipKey) {
            $all[$ipKey] = [
                'count' => ($all[$ipKey]['count'] ?? 0) + 1,
                'first' => $all[$ipKey]['first'] ?? time(),
            ];
            return [null, $all];
        });
        usleep(400000);
        flash('err', 'Senha incorreta.');
    }
    redirect_self();
}

$logged = !empty($_SESSION['vsl_ok']);
if ($logged) {
    $_SESSION['vsl_seen'] = time();
}

if ($method === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    if (csrf_ok()) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    redirect_self();
}

if ($method === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!$logged || !csrf_ok()) {
        flash('err', 'Sessão expirada. Entre de novo; nada foi salvo.');
        redirect_self();
    }

    $old    = vsl_load();
    $new    = $old;
    $errors = [];
    $str    = fn(string $k, int $max = 500) => mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max);

    $upload = function (array $file, string $kind, string $label) use (&$errors): string {
        try {
            return vsl_handle_upload($file, $kind);
        } catch (RuntimeException $ex) {
            $errors[] = $label . ': ' . $ex->getMessage();
            return '';
        }
    };
    $fileAt = function (string $field, ?string $key = null): array {
        $f = $_FILES[$field] ?? null;
        if (!$f) {
            return ['error' => UPLOAD_ERR_NO_FILE];
        }
        if ($key === null) {
            return $f;
        }
        $out = [];
        foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $p) {
            $out[$p] = $f[$p][$key] ?? null;
        }
        $out['error'] ??= UPLOAD_ERR_NO_FILE;
        return $out;
    };

    // Marca
    $new['brand_name'] = $str('brand_name', 80);
    if (!empty($_POST['logo_remove'])) {
        $new['logo'] = '';
    }
    if (($n = $upload($fileAt('logo'), 'image', 'Logo')) !== '') {
        $new['logo'] = $n;
    }

    // Topo
    $new['badge']    = $str('badge', 120);
    $new['title']    = $str('title', 300);
    $new['subtitle'] = $str('subtitle', 300);

    // Vídeo
    $type = (string) ($_POST['video_type'] ?? 'url');
    $new['video']['type']  = in_array($type, ['url', 'upload', 'embed'], true) ? $type : 'url';
    $new['video']['url']   = vsl_safe_url($str('video_url', 1000));
    $new['video']['embed'] = mb_substr((string) ($_POST['video_embed'] ?? ''), 0, 20000);
    $ratio = (string) ($_POST['video_ratio'] ?? '9:16');
    $new['video']['ratio'] = in_array($ratio, ['9:16', '16:9', '1:1', '4:5'], true) ? $ratio : '9:16';
    if (!empty($_POST['video_file_remove'])) {
        $new['video']['file'] = '';
    }
    if (($n = $upload($fileAt('video_file'), 'video', 'Vídeo')) !== '') {
        $new['video']['file'] = $n;
        $new['video']['type'] = 'upload';
    }
    if ($new['video']['url'] === '' && $str('video_url', 1000) !== '') {
        $errors[] = 'Link do vídeo: use um endereço começando com https://';
    }

    // Botão
    $new['cta']['enabled'] = !empty($_POST['cta_enabled']);
    $new['cta']['text']    = $str('cta_text', 80);
    $new['cta']['url']     = vsl_safe_url($str('cta_url', 1000));
    $new['cta']['delay']   = max(0, min(36000, (int) ($_POST['cta_delay'] ?? 0)));
    $new['cta']['color']   = vsl_safe_color($str('cta_color', 7), '#16a34a');
    if ($new['cta']['url'] === '' && $str('cta_url', 1000) !== '') {
        $errors[] = 'Link do botão: use um endereço começando com https://';
    }

    // Depoimentos — a ordem do formulário é a ordem da página.
    $new['testimonials_title'] = $str('testimonials_title', 120);
    $oldById = [];
    foreach ($old['testimonials'] as $t) {
        $oldById[(string) ($t['id'] ?? '')] = $t;
    }
    $list = [];
    foreach ((array) ($_POST['t'] ?? []) as $id => $row) {
        $id = (string) $id;
        if (!preg_match('/^[a-z0-9]{6,32}$/', $id) || !is_array($row)) {
            continue;
        }
        $prev = $oldById[$id] ?? [];
        $item = [
            'id'     => $id,
            'name'   => mb_substr(trim((string) ($row['name'] ?? '')), 0, 80),
            'city'   => mb_substr(trim((string) ($row['city'] ?? '')), 0, 80),
            'rating' => max(0, min(5, (int) ($row['rating'] ?? 0))),
            'text'   => mb_substr(trim(str_replace("\r\n", "\n", (string) ($row['text'] ?? ''))), 0, 3000),
            'photo'  => !empty($row['photo_remove']) ? '' : (string) ($prev['photo'] ?? ''),
            'proof'  => !empty($row['proof_remove']) ? '' : (string) ($prev['proof'] ?? ''),
        ];
        $label = 'Depoimento "' . ($item['name'] ?: 'sem nome') . '"';
        if (($n = $upload($fileAt('t_photo', $id), 'image', $label . ' (foto)')) !== '') {
            $item['photo'] = $n;
        }
        if (($n = $upload($fileAt('t_proof', $id), 'image', $label . ' (imagem)')) !== '') {
            $item['proof'] = $n;
        }
        if ($item['name'] === '' && $item['text'] === '' && $item['photo'] === '' && $item['proof'] === '') {
            continue; // linha vazia
        }
        $list[] = $item;
    }
    $new['testimonials'] = $list;

    // Rodapé e avançado
    $new['footer_text'] = $str('footer_text', 2000);
    $new['disclaimer']  = $str('disclaimer', 2000);
    $new['head_code']   = mb_substr((string) ($_POST['head_code'] ?? ''), 0, 20000);

    try {
        vsl_save($new);
        // Apaga arquivos que deixaram de ser usados (trocados ou removidos).
        $used = function (array $d): array {
            $f = [$d['logo'], $d['video']['file']];
            foreach ($d['testimonials'] as $t) {
                $f[] = $t['photo'] ?? '';
                $f[] = $t['proof'] ?? '';
            }
            return array_filter($f);
        };
        foreach (array_diff($used($old), $used($new)) as $orphan) {
            vsl_delete_upload((string) $orphan);
        }
        flash('ok', 'Alterações salvas.');
    } catch (RuntimeException $ex) {
        $errors[] = $ex->getMessage();
    }
    foreach ($errors as $msg) {
        flash('err', $msg);
    }
    redirect_self();
}

$flashes = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);
$d = vsl_load();

function new_row_id(): string
{
    return bin2hex(random_bytes(6));
}

function testimonial_row(array $t, string $id): string
{
    $photo = vsl_upload_url((string) ($t['photo'] ?? ''));
    $proof = vsl_upload_url((string) ($t['proof'] ?? ''));
    $rating = (int) ($t['rating'] ?? 0);
    $n = 't[' . $id . ']';
    ob_start(); ?>
    <div class="trow" data-id="<?= e($id) ?>">
      <div class="trow-head">
        <strong class="trow-title"><?= e(($t['name'] ?? '') ?: 'Novo depoimento') ?></strong>
        <div class="trow-actions">
          <button type="button" class="icon" data-move="-1" aria-label="Mover para cima">↑</button>
          <button type="button" class="icon" data-move="1" aria-label="Mover para baixo">↓</button>
          <button type="button" class="icon danger" data-remove aria-label="Excluir depoimento">✕</button>
        </div>
      </div>
      <div class="grid2">
        <label>Nome<input name="<?= $n ?>[name]" value="<?= e($t['name'] ?? '') ?>" maxlength="80" data-title></label>
        <label>Cidade / detalhe <small>(opcional)</small><input name="<?= $n ?>[city]" value="<?= e($t['city'] ?? '') ?>" maxlength="80" placeholder="Ex.: Curitiba, PR"></label>
      </div>
      <label>Depoimento<textarea name="<?= $n ?>[text]" rows="3" maxlength="3000"><?= e($t['text'] ?? '') ?></textarea></label>
      <div class="grid3">
        <label>Estrelas
          <select name="<?= $n ?>[rating]">
            <?php for ($i = 0; $i <= 5; $i++): ?>
              <option value="<?= $i ?>"<?= $i === $rating ? ' selected' : '' ?>><?= $i === 0 ? 'Sem estrelas' : str_repeat('★', $i) ?></option>
            <?php endfor; ?>
          </select>
        </label>
        <div class="file">
          <span>Foto do cliente</span>
          <?php if ($photo): ?><img class="thumb round" src="<?= e($photo) ?>" alt=""><label class="inline"><input type="checkbox" name="<?= $n ?>[photo_remove]" value="1"> remover</label><?php endif; ?>
          <input type="file" name="t_photo[<?= e($id) ?>]" accept="image/jpeg,image/png,image/webp,image/gif">
        </div>
        <div class="file">
          <span>Imagem extra <small>(print, resultado)</small></span>
          <?php if ($proof): ?><img class="thumb" src="<?= e($proof) ?>" alt=""><label class="inline"><input type="checkbox" name="<?= $n ?>[proof_remove]" value="1"> remover</label><?php endif; ?>
          <input type="file" name="t_proof[<?= e($id) ?>]" accept="image/jpeg,image/png,image/webp,image/gif">
        </div>
      </div>
    </div>
    <?php return (string) ob_get_clean();
}
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Painel da VSL</title>
<style>
  :root {
    --bg: #0f0f0f; --panel: #181818; --field: #222; --line: #2e2e2e;
    --text: #f1f1f1; --muted: #9a9a9a; --accent: #3ea6ff; --ok: #22c55e; --err: #f87171;
  }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--bg); color: var(--text); font: 14px/1.45 Roboto, "Segoe UI", system-ui, Arial, sans-serif; }
  a { color: var(--accent); }
  header.bar {
    position: sticky; top: 0; z-index: 5; display: flex; align-items: center; justify-content: space-between; gap: 12px;
    padding: 12px 16px; background: rgba(15,15,15,.95); border-bottom: 1px solid var(--line); backdrop-filter: blur(6px);
  }
  header.bar h1 { margin: 0; font-size: 16px; }
  header.bar .right { display: flex; gap: 8px; align-items: center; }
  main { max-width: 860px; margin: 0 auto; padding: 20px 16px 120px; }
  section.card { background: var(--panel); border: 1px solid var(--line); border-radius: 12px; padding: 18px; margin-bottom: 16px; }
  section.card h2 { margin: 0 0 4px; font-size: 15px; }
  section.card > p.hint { margin: 0 0 14px; color: var(--muted); font-size: 13px; }
  label { display: block; margin-top: 12px; font-size: 13px; color: #cfcfcf; }
  label.inline { display: inline-flex; align-items: center; gap: 6px; margin: 0; }
  small { color: var(--muted); font-weight: 400; }
  input[type=text], input[type=url], input[type=number], input[type=password], input:not([type]), textarea, select {
    width: 100%; margin-top: 5px; padding: 10px 12px; color: var(--text); background: var(--field);
    border: 1px solid var(--line); border-radius: 8px; font: inherit;
  }
  textarea { resize: vertical; }
  textarea.code { font: 12px/1.4 ui-monospace, Menlo, Consolas, monospace; }
  input:focus, textarea:focus, select:focus { outline: 2px solid var(--accent); outline-offset: 0; border-color: transparent; }
  input[type=file] { display: block; margin-top: 6px; color: var(--muted); font-size: 12px; max-width: 100%; }
  input[type=color] { width: 56px; height: 40px; margin-top: 5px; padding: 2px; background: var(--field); border: 1px solid var(--line); border-radius: 8px; }
  .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 12px; }
  .grid3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0 12px; align-items: start; }
  @media (max-width: 640px) { .grid2, .grid3 { grid-template-columns: 1fr; } }
  .file { margin-top: 12px; font-size: 13px; color: #cfcfcf; }
  .file > span { display: block; }
  .thumb { display: block; max-height: 56px; max-width: 160px; margin: 6px 0; border-radius: 6px; background: #000; }
  .thumb.round { width: 48px; height: 48px; object-fit: cover; border-radius: 50%; }
  .logo-thumb { max-height: 48px; padding: 6px; background: #000; border: 1px solid var(--line); }
  .seg { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
  .seg label { margin: 0; }
  .seg input { position: absolute; opacity: 0; pointer-events: none; }
  .seg span { display: inline-block; padding: 8px 12px; border: 1px solid var(--line); border-radius: 8px; background: var(--field); cursor: pointer; }
  .seg input:checked + span { border-color: var(--accent); color: #fff; background: rgba(62,166,255,.15); }
  .seg input:focus-visible + span { outline: 2px solid var(--accent); }
  .pane[hidden] { display: none; }
  .trow { border: 1px solid var(--line); border-radius: 10px; padding: 12px 14px 14px; margin-top: 12px; background: #141414; }
  .trow-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
  .trow-title { font-size: 14px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .trow-actions { display: flex; gap: 4px; flex: none; }
  button, .btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 9px 14px;
    color: var(--text); background: #2a2a2a; border: 1px solid var(--line); border-radius: 8px; font: inherit; cursor: pointer; text-decoration: none;
  }
  button:hover, .btn:hover { background: #333; }
  button.primary { background: var(--accent); border-color: var(--accent); color: #041321; font-weight: 700; }
  button.primary:hover { filter: brightness(1.08); }
  button.icon { width: 34px; height: 34px; padding: 0; }
  button.danger:hover { background: #3a1616; border-color: #6b2222; color: var(--err); }
  .add { margin-top: 14px; width: 100%; border-style: dashed; }
  .savebar {
    position: fixed; left: 0; right: 0; bottom: 0; z-index: 5; display: flex; justify-content: center; gap: 10px;
    padding: 12px 16px; background: rgba(15,15,15,.96); border-top: 1px solid var(--line);
  }
  .savebar button { min-width: 200px; }
  .flash { padding: 10px 14px; border-radius: 8px; margin-bottom: 10px; font-size: 13px; }
  .flash.ok { background: rgba(34,197,94,.12); border: 1px solid rgba(34,197,94,.4); color: #86efac; }
  .flash.err { background: rgba(248,113,113,.1); border: 1px solid rgba(248,113,113,.4); color: #fca5a5; }
  .empty { color: var(--muted); font-size: 13px; padding: 14px 0 0; }
  .login { max-width: 360px; margin: 12vh auto 0; }
  .login button { width: 100%; margin-top: 16px; }
  pre { white-space: pre-wrap; word-break: break-all; background: #000; padding: 12px; border-radius: 8px; font-size: 12px; }
</style>
</head>
<body>

<?php if ($hash === ''): ?>
  <main class="login">
    <section class="card">
      <h2>Configure a senha do painel</h2>
      <p class="hint">Gere o hash da senha no servidor:</p>
      <pre>php -r 'echo password_hash("SUA_SENHA_AQUI", PASSWORD_DEFAULT), PHP_EOL;'</pre>
      <p class="hint">Copie <code>vsl/config.example.php</code> para <code>vsl/config.php</code> e cole o hash em <code>admin_password_hash</code>, ou defina a variável de ambiente <code>VSL_ADMIN_PASSWORD_HASH</code>.</p>
    </section>
  </main>

<?php elseif (!$logged): ?>
  <main class="login">
    <?php foreach ($flashes as [$t, $m]): ?><div class="flash <?= e($t) ?>" role="alert"><?= e($m) ?></div><?php endforeach; ?>
    <section class="card">
      <h2>Painel da VSL</h2>
      <form method="post" action="admin.php">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <label>Senha<input type="password" name="password" autocomplete="current-password" required autofocus></label>
        <button class="primary" type="submit">Entrar</button>
      </form>
    </section>
  </main>

<?php else: ?>
  <header class="bar">
    <h1>Painel da VSL</h1>
    <div class="right">
      <a class="btn" href="./" target="_blank" rel="noopener">Ver página ↗</a>
      <form method="post" action="admin.php" style="margin:0">
        <input type="hidden" name="action" value="logout">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <button type="submit">Sair</button>
      </form>
    </div>
  </header>

  <main>
    <?php foreach ($flashes as [$t, $m]): ?><div class="flash <?= e($t) ?>" role="<?= $t === 'err' ? 'alert' : 'status' ?>"><?= e($m) ?></div><?php endforeach; ?>

    <form id="f" method="post" action="admin.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

      <section class="card">
        <h2>Marca</h2>
        <p class="hint">O logo aparece na barra do topo. Sem logo, aparece o nome da marca.</p>
        <label>Nome da marca<input name="brand_name" value="<?= e($d['brand_name']) ?>" maxlength="80"></label>
        <div class="file">
          <span>Logo <small>(PNG com fundo transparente fica melhor no preto)</small></span>
          <?php if ($u = vsl_upload_url((string) $d['logo'])): ?>
            <img class="thumb logo-thumb" src="<?= e($u) ?>" alt="Logo atual">
            <label class="inline"><input type="checkbox" name="logo_remove" value="1"> remover logo</label>
          <?php endif; ?>
          <input type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/gif">
        </div>
      </section>

      <section class="card">
        <h2>Topo da página</h2>
        <label>Selo acima do título <small>(deixe vazio para esconder)</small><input name="badge" value="<?= e($d['badge']) ?>" maxlength="120"></label>
        <label>Título<textarea name="title" rows="2" maxlength="300"><?= e($d['title']) ?></textarea></label>
        <label>Linha de apoio<input name="subtitle" value="<?= e($d['subtitle']) ?>" maxlength="300"></label>
      </section>

      <section class="card">
        <h2>Vídeo</h2>
        <p class="hint">Use um link, envie o arquivo ou cole o código do player (VTurb, Panda, ConverteAI…).</p>
        <div class="seg" role="radiogroup" aria-label="Origem do vídeo">
          <?php foreach (['url' => 'Link', 'upload' => 'Enviar arquivo', 'embed' => 'Código do player'] as $k => $lbl): ?>
            <label><input type="radio" name="video_type" value="<?= $k ?>"<?= $d['video']['type'] === $k ? ' checked' : '' ?>><span><?= $lbl ?></span></label>
          <?php endforeach; ?>
        </div>

        <div class="pane" data-pane="url">
          <label>Link do vídeo <small>(YouTube, Vimeo, link direto .mp4 ou link do player)</small>
            <input type="url" name="video_url" value="<?= e($d['video']['url']) ?>" placeholder="https://">
          </label>
        </div>
        <div class="pane" data-pane="upload">
          <div class="file">
            <span>Arquivo MP4 ou WEBM <small>(limite do servidor: <?= e(ini_get('upload_max_filesize')) ?>)</small></span>
            <?php if ($u = vsl_upload_url((string) $d['video']['file'])): ?>
              <video class="thumb" src="<?= e($u) ?>" style="max-height:120px" muted></video>
              <label class="inline"><input type="checkbox" name="video_file_remove" value="1"> remover vídeo</label>
            <?php endif; ?>
            <input type="file" name="video_file" accept="video/mp4,video/webm">
          </div>
        </div>
        <div class="pane" data-pane="embed">
          <label>Código do player<textarea class="code" name="video_embed" rows="6" placeholder="&lt;div id=&quot;vid_...&quot;&gt;&lt;/div&gt;&lt;script ...&gt;&lt;/script&gt;"><?= e($d['video']['embed']) ?></textarea></label>
        </div>

        <label>Formato
          <select name="video_ratio">
            <?php foreach (['9:16' => 'Vertical 9:16 (celular)', '4:5' => 'Vertical 4:5', '1:1' => 'Quadrado 1:1', '16:9' => 'Horizontal 16:9'] as $k => $lbl): ?>
              <option value="<?= $k ?>"<?= $d['video']['ratio'] === $k ? ' selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </section>

      <section class="card">
        <h2>Botão de compra</h2>
        <p class="hint">Com atraso, o botão aparece depois desse tempo de vídeo (arquivo/MP4) ou de página aberta (YouTube, players externos).</p>
        <label class="inline"><input type="checkbox" name="cta_enabled" value="1"<?= !empty($d['cta']['enabled']) ? ' checked' : '' ?>> Mostrar botão</label>
        <div class="grid2">
          <label>Texto do botão<input name="cta_text" value="<?= e($d['cta']['text']) ?>" maxlength="80"></label>
          <label>Link do checkout<input type="url" name="cta_url" value="<?= e($d['cta']['url']) ?>" placeholder="https://"></label>
        </div>
        <div class="grid2">
          <label>Aparecer depois de (segundos)<input type="number" name="cta_delay" min="0" max="36000" value="<?= (int) $d['cta']['delay'] ?>"></label>
          <label>Cor<input type="color" name="cta_color" value="<?= e(vsl_safe_color((string) $d['cta']['color'], '#16a34a')) ?>"></label>
        </div>
      </section>

      <section class="card">
        <h2>Depoimentos</h2>
        <p class="hint">Aparecem abaixo do vídeo, na ordem desta lista.</p>
        <label>Título da seção<input name="testimonials_title" value="<?= e($d['testimonials_title']) ?>" maxlength="120"></label>
        <div id="tlist">
          <?php foreach ($d['testimonials'] as $t): ?>
            <?= testimonial_row($t, preg_match('/^[a-z0-9]{6,32}$/', (string) ($t['id'] ?? '')) ? (string) $t['id'] : new_row_id()) ?>
          <?php endforeach; ?>
        </div>
        <p class="empty" id="tempty"<?= $d['testimonials'] ? ' hidden' : '' ?>>Nenhum depoimento ainda.</p>
        <button type="button" class="add" id="tadd">+ Adicionar depoimento</button>
        <template id="ttpl"><?= testimonial_row([], '__ID__') ?></template>
      </section>

      <section class="card">
        <h2>Rodapé</h2>
        <label>Texto do rodapé <small>(empresa, CNPJ, contato)</small><textarea name="footer_text" rows="3" maxlength="2000"><?= e($d['footer_text']) ?></textarea></label>
        <label>Aviso legal<textarea name="disclaimer" rows="3" maxlength="2000"><?= e($d['disclaimer']) ?></textarea></label>
      </section>

      <section class="card">
        <h2>Avançado</h2>
        <label>Código no &lt;head&gt; <small>(Meta Pixel, UTMify, Google Tag)</small><textarea class="code" name="head_code" rows="5"><?= e($d['head_code']) ?></textarea></label>
      </section>

      <div class="savebar"><button class="primary" type="submit">Salvar alterações</button></div>
    </form>
  </main>

  <script>
  (function () {
    var form = document.getElementById('f');
    var list = document.getElementById('tlist');
    var tpl = document.getElementById('ttpl');
    var empty = document.getElementById('tempty');
    var dirty = false;

    // Origem do vídeo: mostra só o campo da opção escolhida.
    function syncPanes() {
      var sel = form.querySelector('input[name=video_type]:checked');
      var v = sel ? sel.value : 'url';
      form.querySelectorAll('.pane').forEach(function (p) { p.hidden = p.dataset.pane !== v; });
    }
    form.querySelectorAll('input[name=video_type]').forEach(function (r) { r.addEventListener('change', syncPanes); });
    syncPanes();

    function rid() {
      var a = new Uint8Array(6); crypto.getRandomValues(a);
      return Array.from(a, function (b) { return b.toString(16).padStart(2, '0'); }).join('');
    }
    function syncEmpty() { empty.hidden = list.children.length > 0; }

    document.getElementById('tadd').addEventListener('click', function () {
      var html = tpl.innerHTML.split('__ID__').join(rid());
      list.insertAdjacentHTML('beforeend', html);
      var row = list.lastElementChild;
      row.querySelector('[data-title]').focus();
      dirty = true; syncEmpty();
    });

    list.addEventListener('click', function (ev) {
      var btn = ev.target.closest('button'); if (!btn) return;
      var row = btn.closest('.trow');
      if (btn.hasAttribute('data-remove')) {
        if (confirm('Excluir este depoimento?')) { row.remove(); dirty = true; syncEmpty(); }
      } else if (btn.dataset.move === '-1' && row.previousElementSibling) {
        list.insertBefore(row, row.previousElementSibling); dirty = true; btn.focus();
      } else if (btn.dataset.move === '1' && row.nextElementSibling) {
        list.insertBefore(row.nextElementSibling, row); dirty = true; btn.focus();
      }
    });
    list.addEventListener('input', function (ev) {
      if (ev.target.matches('[data-title]')) {
        ev.target.closest('.trow').querySelector('.trow-title').textContent = ev.target.value || 'Novo depoimento';
      }
    });

    form.addEventListener('input', function () { dirty = true; });
    form.addEventListener('change', function () { dirty = true; });
    form.addEventListener('submit', function () {
      dirty = false;
      var b = form.querySelector('.savebar button'); b.disabled = true; b.textContent = 'Salvando…';
    });
    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  })();
  </script>
<?php endif; ?>
</body>
</html>
