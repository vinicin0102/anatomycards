<?php
declare(strict_types=1);
require __DIR__ . '/_lib.php';

$d = vsl_load();
$v = $d['video'];
$cta = $d['cta'];

$ratios = ['9:16' => '9 / 16', '16:9' => '16 / 9', '1:1' => '1 / 1', '4:5' => '4 / 5'];
$ratio  = $ratios[$v['ratio']] ?? '9 / 16';
$wide   = in_array($v['ratio'], ['16:9'], true);

// Monta o player conforme a origem escolhida no painel.
$player = '';
if ($v['type'] === 'upload' && ($src = vsl_upload_url((string) $v['file'])) !== '') {
    $player = '<video id="vsl-video" src="' . e($src) . '" controls playsinline preload="metadata"></video>';
} elseif ($v['type'] === 'embed' && trim((string) $v['embed']) !== '') {
    // Código do player (VTurb, Panda…) colado pelo administrador.
    $player = (string) $v['embed'];
} elseif ($v['type'] === 'url') {
    $p = vsl_parse_video_url((string) $v['url']);
    if ($p['kind'] === 'video') {
        $player = '<video id="vsl-video" src="' . e($p['src']) . '" controls playsinline preload="metadata"></video>';
    } elseif ($p['kind'] === 'iframe') {
        $player = '<iframe src="' . e($p['src']) . '" title="Vídeo" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>';
    }
}

$logo     = vsl_upload_url((string) $d['logo']);
$ctaUrl   = vsl_safe_url((string) $cta['url']);
$ctaColor = vsl_safe_color((string) $cta['color'], '#16a34a');
$ctaDelay = max(0, (int) $cta['delay']);
$showCta  = !empty($cta['enabled']) && $ctaUrl !== '' && trim((string) $cta['text']) !== '';
$items    = $d['testimonials'];
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($d['title'] ?: $d['brand_name']) ?></title>
<meta name="description" content="<?= e($d['subtitle']) ?>">
<meta name="theme-color" content="#0f0f0f">
<style>
  :root {
    --bg: #0f0f0f;
    --surface: #1a1a1a;
    --line: #272727;
    --text: #f1f1f1;
    --muted: #aaaaaa;
    --cta: <?= $ctaColor ?>;
  }
  * { box-sizing: border-box; }
  html, body { margin: 0; background: var(--bg); color: var(--text); }
  body {
    font-family: Roboto, "Segoe UI", system-ui, -apple-system, Arial, sans-serif;
    font-size: 15px; line-height: 1.45; -webkit-font-smoothing: antialiased;
  }
  img { max-width: 100%; display: block; }

  .topbar {
    position: sticky; top: 0; z-index: 10;
    display: flex; align-items: center; justify-content: center;
    height: 60px; padding: 0 16px; background: var(--bg);
    border-bottom: 1px solid var(--line);
  }
  .topbar img { max-height: 36px; width: auto; }
  .topbar .brand { font-weight: 700; font-size: 18px; letter-spacing: -.01em; }

  main { max-width: 760px; margin: 0 auto; padding: 24px 16px 48px; }

  .head { text-align: center; }
  .badge {
    display: inline-block; margin-bottom: 12px; padding: 4px 10px;
    font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
    color: #ff6b6b; background: rgba(255, 70, 70, .1); border: 1px solid rgba(255, 70, 70, .35);
    border-radius: 4px;
  }
  h1 { margin: 0 0 6px; font-size: clamp(19px, 4.6vw, 24px); line-height: 1.25; font-weight: 700; }
  .sub { margin: 0; color: var(--muted); font-size: 13px; }

  .player {
    margin: 20px auto 0; width: 100%; max-width: <?= $wide ? '760px' : '380px' ?>;
    aspect-ratio: <?= $ratio ?>; background: #000; border-radius: 12px; overflow: hidden;
    box-shadow: 0 10px 40px rgba(0,0,0,.6);
  }
  .player iframe, .player video { width: 100%; height: 100%; border: 0; display: block; object-fit: cover; background: #000; }
  .player.custom { aspect-ratio: auto; background: none; box-shadow: none; }
  .player-empty {
    height: 100%; display: grid; place-items: center; padding: 24px; text-align: center;
    color: var(--muted); font-size: 14px; background: var(--surface);
  }

  .cta-wrap { text-align: center; margin: 24px auto 0; max-width: 520px; }
  .cta-wrap[hidden] { display: none; }
  .cta {
    display: block; width: 100%; padding: 18px 20px; border-radius: 10px;
    background: var(--cta); color: #fff; text-decoration: none;
    font-weight: 800; font-size: 17px; letter-spacing: .01em;
    box-shadow: 0 6px 24px color-mix(in srgb, var(--cta) 45%, transparent);
    animation: pulse 1.8s ease-in-out infinite;
  }
  .cta:focus-visible { outline: 3px solid #fff; outline-offset: 3px; }
  @keyframes pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.03); } }
  @media (prefers-reduced-motion: reduce) { .cta { animation: none; } }
  .cta-wrap.reveal { animation: fadein .5s ease-out both; }
  @keyframes fadein { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }

  .testimonials { max-width: 680px; margin: 48px auto 0; }
  .testimonials h2 { margin: 0 0 20px; font-size: 19px; font-weight: 700; }
  .testimonials h2 span { color: var(--muted); font-weight: 400; font-size: 15px; margin-left: 6px; }
  .t { display: flex; gap: 14px; padding: 14px 0; border-top: 1px solid var(--line); }
  .t:first-of-type { border-top: 0; }
  .avatar {
    flex: 0 0 40px; width: 40px; height: 40px; border-radius: 50%; overflow: hidden;
    display: grid; place-items: center; background: #3a3a3a; color: #fff; font-weight: 700; font-size: 16px;
  }
  .avatar img { width: 100%; height: 100%; object-fit: cover; }
  .t-body { min-width: 0; }
  .t-meta { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 8px; margin-bottom: 3px; }
  .t-name { font-weight: 600; font-size: 14px; }
  .t-city { color: var(--muted); font-size: 12px; }
  .stars { color: #fbbf24; font-size: 12px; letter-spacing: 1px; }
  .t-text { margin: 0; font-size: 14px; white-space: pre-line; overflow-wrap: anywhere; }
  .t-photo { margin-top: 10px; max-width: 280px; border-radius: 8px; }

  footer {
    border-top: 1px solid var(--line); margin-top: 32px; padding: 24px 16px 40px;
    color: #717171; font-size: 12px; text-align: center;
  }
  footer p { max-width: 680px; margin: 0 auto 8px; white-space: pre-line; }
</style>
<?= (string) $d['head_code'] /* pixel/tags configurados no painel */ ?>
</head>
<body>

<header class="topbar">
  <?php if ($logo !== ''): ?>
    <img src="<?= e($logo) ?>" alt="<?= e($d['brand_name']) ?>">
  <?php else: ?>
    <span class="brand"><?= e($d['brand_name']) ?></span>
  <?php endif; ?>
</header>

<main>
  <div class="head">
    <?php if (trim((string) $d['badge']) !== ''): ?><div class="badge"><?= e($d['badge']) ?></div><?php endif; ?>
    <h1><?= e($d['title']) ?></h1>
    <?php if (trim((string) $d['subtitle']) !== ''): ?><p class="sub"><?= e($d['subtitle']) ?></p><?php endif; ?>
  </div>

  <div class="player<?= $v['type'] === 'embed' && $player !== '' ? ' custom' : '' ?>">
    <?php if ($player !== ''): ?>
      <?= $player ?>
    <?php else: ?>
      <div class="player-empty">Nenhum vídeo configurado.<br>Adicione o vídeo pelo painel.</div>
    <?php endif; ?>
  </div>

  <?php if ($showCta): ?>
    <div class="cta-wrap" id="cta"<?= $ctaDelay > 0 ? ' hidden' : '' ?>>
      <a class="cta" href="<?= e($ctaUrl) ?>"><?= e($cta['text']) ?></a>
    </div>
  <?php endif; ?>

  <?php if ($items): ?>
    <section class="testimonials" aria-labelledby="t-title">
      <h2 id="t-title"><?= e($d['testimonials_title']) ?><span><?= count($items) ?></span></h2>
      <?php foreach ($items as $t):
          $photo  = vsl_upload_url((string) ($t['photo'] ?? ''));
          $proof  = vsl_upload_url((string) ($t['proof'] ?? ''));
          $name   = trim((string) ($t['name'] ?? ''));
          $rating = max(0, min(5, (int) ($t['rating'] ?? 0)));
      ?>
        <article class="t">
          <div class="avatar" aria-hidden="true">
            <?php if ($photo !== ''): ?><img src="<?= e($photo) ?>" alt="" loading="lazy">
            <?php else: ?><?= e(mb_strtoupper(mb_substr($name !== '' ? $name : '?', 0, 1))) ?><?php endif; ?>
          </div>
          <div class="t-body">
            <div class="t-meta">
              <span class="t-name"><?= e($name) ?></span>
              <?php if (!empty($t['city'])): ?><span class="t-city"><?= e($t['city']) ?></span><?php endif; ?>
              <?php if ($rating > 0): ?><span class="stars" aria-label="<?= $rating ?> de 5 estrelas"><?= str_repeat('★', $rating) ?></span><?php endif; ?>
            </div>
            <p class="t-text"><?= e($t['text'] ?? '') ?></p>
            <?php if ($proof !== ''): ?><img class="t-photo" src="<?= e($proof) ?>" alt="Imagem enviada por <?= e($name) ?>" loading="lazy"><?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</main>

<?php if (trim((string) $d['footer_text']) !== '' || trim((string) $d['disclaimer']) !== ''): ?>
<footer>
  <?php if (trim((string) $d['footer_text']) !== ''): ?><p><?= e($d['footer_text']) ?></p><?php endif; ?>
  <?php if (trim((string) $d['disclaimer']) !== ''): ?><p><?= e($d['disclaimer']) ?></p><?php endif; ?>
</footer>
<?php endif; ?>

<?php if ($showCta && $ctaDelay > 0): ?>
<script>
  // Mostra o botão depois de X segundos de vídeo assistido (vídeo próprio)
  // ou de página aberta (players externos, que não expõem o tempo).
  (function () {
    var delay = <?= $ctaDelay ?>, cta = document.getElementById('cta');
    var video = document.getElementById('vsl-video'), done = false;
    function show() {
      if (done) return; done = true;
      cta.hidden = false; cta.classList.add('reveal');
      try { localStorage.setItem('vsl_cta_seen', '1'); } catch (e) {}
    }
    try { if (localStorage.getItem('vsl_cta_seen') === '1') return show(); } catch (e) {}
    if (video) {
      video.addEventListener('timeupdate', function () { if (video.currentTime >= delay) show(); });
    } else {
      setTimeout(show, delay * 1000);
    }
  })();
</script>
<?php endif; ?>
</body>
</html>
