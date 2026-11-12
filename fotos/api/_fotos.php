<?php
declare(strict_types=1);

/**
 * Utilitários da loja de montagens de foto.
 * Reaproveita responder(), chamarZuckpay(), cpfValido() etc. do api/_bootstrap.php.
 */

require __DIR__ . '/../../api/_bootstrap.php';

/* -------------------------------------------------------------------------
 * Catálogo usado para montar o pedido à IA.
 * Os ids precisam ser os mesmos do catálogo do index.html (const CATALOGO).
 * ---------------------------------------------------------------------- */

const ACOMPANHANTES = [
    'jesus'         => 'Jesus Christ as traditionally depicted in Christian art: long brown hair, beard, white robe with a light sash, serene and kind expression, a soft heavenly glow behind him',
    'aparecida'     => 'Our Lady of Aparecida (Nossa Senhora Aparecida) as a gentle dark-skinned Virgin Mary wearing a deep blue mantle with gold trim and a golden crown, serene expression, soft heavenly light',
    'fatima'        => 'Our Lady of Fatima, the Virgin Mary in a white veil and white mantle with golden trim, hands together in prayer, soft heavenly light',
    'anjo'          => 'a guardian angel with large white feathered wings, kind face, flowing white clothes and a gentle glowing light',
    'sao_francisco' => 'Saint Francis of Assisi in a brown Franciscan habit with a rope belt, kind bearded face, a white dove on his hand',
    'santo_antonio' => 'Saint Anthony of Padua in a brown Franciscan habit, tonsured hair, holding a lily flower',
    'sao_jorge'     => 'Saint George as a Roman soldier in shining armor with a red cape, noble expression',
    'famoso'        => '__FAMOSO__', // usa o nome digitado pela cliente
    'ente'          => 'the person shown in the second image (keep that person\'s face, age and features exactly the same)',
];

const POSES = [
    'lado_a_lado' => 'standing side by side with',
    'abraco'      => 'in a warm, affectionate hug with',
    'ombro'       => 'standing close, with a hand resting on the shoulder of',
    'rezando'     => 'praying together, hands joined, next to',
];

const CENARIOS = [
    'ceu'     => 'heaven, among soft white clouds with warm golden sunlight',
    'igreja'  => 'inside a beautiful church with stained glass windows and warm light',
    'jardim'  => 'a colorful flower garden on a sunny day',
    'casa'    => 'a cozy family living room with warm light',
    'estudio' => 'a professional photo studio with a soft neutral background',
    'palco'   => 'a concert stage with bright stage lights behind them',
];

function configFotos(): array
{
    $caminho = __DIR__ . '/config.php';
    if (!is_file($caminho)) {
        responder(500, ['erro' => 'Servidor não configurado.']);
    }
    $config = require $caminho;
    dirStorage($config);
    return $config;
}

/** Cria a pasta de dados e bloqueia acesso direto pela web (Apache). */
function dirStorage(array $config): string
{
    $dir = rtrim((string) $config['storage'], '/');
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess, "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

function idPedidoValido(string $id): bool
{
    return (bool) preg_match('/^[a-f0-9]{24}$/', $id);
}

function dirPedido(array $config, string $id): string
{
    return dirStorage($config) . '/pedidos/' . $id;
}

function pedidoLer(array $config, string $id): ?array
{
    if (!idPedidoValido($id)) {
        return null;
    }
    $arquivo = dirPedido($config, $id) . '/pedido.json';
    if (!is_file($arquivo)) {
        return null;
    }
    $dados = json_decode((string) file_get_contents($arquivo), true);
    return is_array($dados) ? $dados : null;
}

function pedidoGravar(array $config, string $id, array $dados): void
{
    $dir = dirPedido($config, $id);
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    file_put_contents($dir . '/pedido.json', json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

/** Carrega o pedido e confere o token. Responde 404 em qualquer falha (não revela se o pedido existe). */
function pedidoAutorizado(array $config, string $id, string $token): array
{
    $pedido = pedidoLer($config, $id);
    if ($pedido === null || $token === '' || !hash_equals((string) $pedido['token'], $token)) {
        responder(404, ['erro' => 'Pedido não encontrado.']);
    }
    return $pedido;
}

/** Liga um transactionId ao pedido (o webhook só recebe o transactionId). */
function indexarTransacao(array $config, string $transactionId, string $pedidoId): void
{
    $dir = dirStorage($config) . '/transacoes';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    file_put_contents($dir . '/' . sha1($transactionId), $pedidoId, LOCK_EX);
}

function pedidoDaTransacao(array $config, string $transactionId): ?string
{
    $arquivo = dirStorage($config) . '/transacoes/' . sha1($transactionId);
    $id = is_file($arquivo) ? trim((string) file_get_contents($arquivo)) : '';
    return idPedidoValido($id) ? $id : null;
}

/** Marca o pedido como pago (idempotente) e registra a venda uma única vez. */
function marcarPago(array $config, string $pedidoId, string $transactionId, array $resposta = []): void
{
    $pedido = pedidoLer($config, $pedidoId);
    if ($pedido === null) {
        return;
    }
    if (empty($pedido['pago'])) {
        $pedido['pago']    = true;
        $pedido['pago_em'] = date('c');
        pedidoGravar($config, $pedidoId, $pedido);
    }

    if (!transacaoJaRegistrada($config, $transactionId)) {
        registrarPagamento($config, [
            'transactionId' => $transactionId,
            'pedido'        => $pedidoId,
            'nome'          => $pedido['comprador']['nome'] ?? null,
            'email'         => $pedido['comprador']['email'] ?? null,
            'telefone'      => $pedido['comprador']['telefone'] ?? null,
            'acompanhante'  => $pedido['acompanhante'] ?? null,
            'valor'         => $resposta['amount'] ?? ($config['produto']['valor'] ?? null),
            'registrado_em' => date('c'),
        ]);

        /*
         * TODO — avisos após a venda (roda uma vez por pagamento):
         * enviar a foto por e-mail ao comprador e/ou avisar você no WhatsApp.
         * A foto final está em dirPedido($config, $pedidoId) . '/resultado.jpg'.
         */
    }
}

/**
 * Limite simples por chave e por dia, com arquivo e flock.
 * Retorna false quando o limite já foi atingido.
 */
function consumirLimite(array $config, string $chave, int $maximo): bool
{
    if ($maximo <= 0) {
        return true;
    }
    $dir = dirStorage($config) . '/limites';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    $arquivo = $dir . '/' . date('Ymd') . '-' . sha1($chave);
    $h = fopen($arquivo, 'c+');
    if ($h === false) {
        return true;
    }
    flock($h, LOCK_EX);
    $atual = (int) stream_get_contents($h);
    if ($atual >= $maximo) {
        flock($h, LOCK_UN);
        fclose($h);
        return false;
    }
    ftruncate($h, 0);
    rewind($h);
    fwrite($h, (string) ($atual + 1));
    flock($h, LOCK_UN);
    fclose($h);
    return true;
}

/** Devolve uma tentativa (usado quando a falha foi nossa, não da cliente). */
function devolverLimite(array $config, string $chave): void
{
    $arquivo = dirStorage($config) . '/limites/' . date('Ymd') . '-' . sha1($chave);
    $h = @fopen($arquivo, 'c+');
    if ($h === false) {
        return;
    }
    flock($h, LOCK_EX);
    $atual = (int) stream_get_contents($h);
    ftruncate($h, 0);
    rewind($h);
    fwrite($h, (string) max(0, $atual - 1));
    flock($h, LOCK_UN);
    fclose($h);
}

/* -------------------------------------------------------------------------
 * Imagens
 * ---------------------------------------------------------------------- */

/**
 * Valida e normaliza uma imagem para JPEG com no máximo $max px no maior lado.
 * Corrige a rotação de fotos de celular (EXIF). Retorna null se não for imagem válida.
 */
function normalizarImagem(string $bytes, int $max = 1600, int $qualidade = 90): ?string
{
    $info = @getimagesizefromstring($bytes);
    if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        return null;
    }
    if ($info[0] * $info[1] > 40_000_000) { // evita estourar memória
        return null;
    }
    $src = @imagecreatefromstring($bytes);
    if ($src === false) {
        return null;
    }

    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($bytes));
        $graus = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        if ($graus !== 0) {
            $girada = imagerotate($src, $graus, 0);
            if ($girada !== false) {
                imagedestroy($src);
                $src = $girada;
            }
        }
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $escala = min(1, $max / max($w, $h));
    $nw = max(1, (int) round($w * $escala));
    $nh = max(1, (int) round($h * $escala));

    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // PNG transparente vira fundo branco
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    ob_start();
    imagejpeg($dst, null, $qualidade);
    imagedestroy($dst);
    return (string) ob_get_clean();
}

/**
 * Prévia: versão reduzida com marca d'água repetida na diagonal.
 * É isso — e só isso — que vai para o navegador antes do pagamento.
 */
function gerarPrevia(string $jpeg, int $largura = 640): string
{
    $src = imagecreatefromstring($jpeg);
    $w = imagesx($src);
    $h = imagesy($src);
    $nw = min($largura, $w);
    $nh = (int) round($h * $nw / $w);
    $img = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($img, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    $texto = 'PREVIA';
    $fonte = 5;
    $tw = imagefontwidth($fonte) * strlen($texto) + 30;
    $th = imagefontheight($fonte) + 6;

    $tile = imagecreatetruecolor($tw, $th);
    imagealphablending($tile, false);
    imagesavealpha($tile, true);
    imagefill($tile, 0, 0, imagecolorallocatealpha($tile, 0, 0, 0, 127));
    imagealphablending($tile, true);
    imagestring($tile, $fonte, 6, 4, $texto, imagecolorallocatealpha($tile, 0, 0, 0, 85));      // sombra
    imagestring($tile, $fonte, 5, 3, $texto, imagecolorallocatealpha($tile, 255, 255, 255, 45)); // texto

    $esc = max(2, (int) round($nw / 220));
    $grande = imagecreatetruecolor($tw * $esc, $th * $esc);
    imagealphablending($grande, false);
    imagesavealpha($grande, true);
    imagefill($grande, 0, 0, imagecolorallocatealpha($grande, 0, 0, 0, 127));
    imagecopyresampled($grande, $tile, 0, 0, 0, 0, $tw * $esc, $th * $esc, $tw, $th);
    imagedestroy($tile);

    $rot = imagerotate($grande, 28, imagecolorallocatealpha($grande, 0, 0, 0, 127));
    imagedestroy($grande);
    imagealphablending($rot, false);
    imagesavealpha($rot, true);

    imagealphablending($img, true);
    $rw = imagesx($rot);
    $rh = imagesy($rot);
    $passoY = max(40, (int) ($rh * 0.6));
    $linha = 0;
    for ($y = -(int) ($rh / 2); $y < $nh; $y += $passoY, $linha++) {
        $desloc = ($linha % 2) * (int) ($rw / 2);
        for ($x = -$rw + $desloc; $x < $nw; $x += $rw) {
            imagecopy($img, $rot, $x, $y, 0, 0, $rw, $rh);
        }
    }
    imagedestroy($rot);

    ob_start();
    imagejpeg($img, null, 70);
    imagedestroy($img);
    return (string) ob_get_clean();
}

/* -------------------------------------------------------------------------
 * fal.ai
 * ---------------------------------------------------------------------- */

function montarPrompt(string $acompanhante, string $pose, string $cenario, string $nomeFamoso, string $obs): string
{
    $quem = ACOMPANHANTES[$acompanhante];
    if ($quem === '__FAMOSO__') {
        $quem = 'the famous person ' . $nomeFamoso . ', with their real, recognizable appearance';
    }

    $prompt = 'Create one photorealistic photograph. The person from the first image is '
        . POSES[$pose] . ' ' . $quem . '. Setting: ' . CENARIOS[$cenario] . '. '
        . 'Keep the person from the first image EXACTLY the same: same face, identity, facial features, '
        . 'skin tone, age, body type, hairstyle and glasses if any. '
        . 'Both people framed from the waist up, looking at the camera, natural proportions, '
        . 'warm natural lighting, sharp focus, high detail, respectful and emotional mood. '
        . 'A single photo: no text, no watermark, no border, no collage.';

    if ($obs !== '') {
        $prompt .= ' Extra request from the customer (in Portuguese): "' . $obs . '"';
    }
    return $prompt;
}

/**
 * Chama o modelo de edição no fal.ai (modo síncrono).
 *
 * @param string[] $jpegs imagens de referência; a primeira é sempre a cliente
 * @return array{0:bool,1:string} [ok, bytes do JPEG | código do erro]
 */
function gerarComFal(array $config, string $prompt, array $jpegs): array
{
    $chave = (string) ($config['fal_key'] ?? '');
    if ($chave === '') {
        registrarErro('fal', 'fal_key não configurada');
        return [false, 'config'];
    }

    $payload = array_merge(
        is_array($config['fal_parametros'] ?? null) ? $config['fal_parametros'] : [],
        [
            'prompt'        => $prompt,
            'image_urls'    => array_map(fn ($b) => 'data:image/jpeg;base64,' . base64_encode($b), $jpegs),
            'num_images'    => 1,
            'output_format' => 'jpeg',
        ]
    );

    $ch = curl_init('https://fal.run/' . ltrim((string) $config['fal_modelo'], '/'));
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Authorization: Key ' . $chave, 'Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 170,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);
    $bruto  = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro   = curl_error($ch);
    curl_close($ch);

    if ($bruto === false) {
        registrarErro('fal', 'curl: ' . $erro);
        return [false, 'conexao'];
    }

    $resposta = json_decode((string) $bruto, true);
    if ($status !== 200 || !is_array($resposta)) {
        registrarErro('fal', 'HTTP ' . $status . ' ' . substr((string) $bruto, 0, 500));
        // 422 costuma ser conteúdo recusado pelo filtro do modelo
        return [false, $status === 422 ? 'recusado' : 'api'];
    }

    if (!empty($resposta['has_nsfw_concepts'][0])) {
        return [false, 'recusado'];
    }

    $url = (string) ($resposta['images'][0]['url'] ?? '');
    if ($url === '') {
        registrarErro('fal', 'sem imagem na resposta: ' . substr((string) $bruto, 0, 500));
        return [false, 'recusado'];
    }

    if (str_starts_with($url, 'data:')) {
        $bytes = base64_decode(substr($url, strpos($url, ',') + 1), true);
        return $bytes === false ? [false, 'api'] : [true, $bytes];
    }

    if (!str_starts_with($url, 'https://')) {
        return [false, 'api'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
        CURLOPT_MAXFILESIZE    => 25 * 1024 * 1024,
    ]);
    $bytes  = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($bytes === false || $status !== 200) {
        registrarErro('fal', 'download da imagem falhou (HTTP ' . $status . ')');
        return [false, 'api'];
    }
    return [true, (string) $bytes];
}

function urlFoto(string $pedido, string $token, bool $baixar = false): string
{
    return 'api/foto.php?pedido=' . $pedido . '&token=' . $token . ($baixar ? '&baixar=1' : '');
}
