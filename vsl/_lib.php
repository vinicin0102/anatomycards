<?php
declare(strict_types=1);

/*
 * Funções compartilhadas da VSL: leitura/gravação do conteúdo,
 * uploads e montagem do player de vídeo.
 */

const VSL_DATA_FILE   = __DIR__ . '/../storage/vsl.json';
const VSL_LOGIN_FILE  = __DIR__ . '/../storage/vsl-login.json';
const VSL_UPLOAD_DIR  = __DIR__ . '/uploads';
const VSL_UPLOAD_URL  = 'uploads';

const VSL_IMAGE_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];
const VSL_VIDEO_TYPES = [
    'video/mp4'  => 'mp4',
    'video/webm' => 'webm',
];
const VSL_IMAGE_MAX = 4 * 1024 * 1024;      // 4 MB
const VSL_VIDEO_MAX = 500 * 1024 * 1024;    // 500 MB (o php.ini também precisa permitir)

function vsl_defaults(): array
{
    return [
        'brand_name'         => 'Sua Marca',
        'logo'               => '',
        'badge'              => 'Assista até o final',
        'title'              => 'Seu título principal aqui',
        'subtitle'           => 'Uma linha de apoio que reforça a promessa do vídeo',
        'video'              => [
            'type'  => 'url',      // url | upload | embed
            'url'   => '',
            'file'  => '',
            'embed' => '',
            'ratio' => '9:16',     // 9:16 | 16:9 | 1:1 | 4:5
        ],
        'cta'                => [
            'enabled' => true,
            'text'    => 'QUERO GARANTIR O MEU',
            'url'     => '',
            'delay'   => 0,        // segundos de vídeo antes do botão aparecer
            'color'   => '#16a34a',
        ],
        'testimonials_title' => 'Depoimentos de clientes',
        'testimonials'       => [],
        'footer_text'        => '',
        'disclaimer'         => 'Os resultados podem variar de pessoa para pessoa.',
        'head_code'          => '',
    ];
}

/** Lê o conteúdo salvo, completando campos novos com o padrão. */
function vsl_load(): array
{
    $data = [];
    if (is_file(VSL_DATA_FILE)) {
        $json = json_decode((string) file_get_contents(VSL_DATA_FILE), true);
        if (is_array($json)) {
            $data = $json;
        }
    }
    $merged = array_replace_recursive(vsl_defaults(), $data);
    // array_replace_recursive mescla listas por índice; a lista salva vale inteira.
    $merged['testimonials'] = is_array($data['testimonials'] ?? null) ? array_values($data['testimonials']) : [];
    return $merged;
}

/** Grava de forma atômica (arquivo temporário + rename). */
function vsl_save(array $data): void
{
    vsl_write_json(VSL_DATA_FILE, $data);
}

/** Cria storage/ bloqueada para acesso pelo navegador. */
function vsl_ensure_storage(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Não foi possível criar a pasta storage/.');
    }
    if (!is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
}

function vsl_write_json(string $file, array $data): void
{
    vsl_ensure_storage(dirname($file));
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false) {
        throw new RuntimeException('Não foi possível gravar em storage/. Verifique as permissões.');
    }
    if (!rename($tmp, $file)) {
        @unlink($tmp);
        throw new RuntimeException('Não foi possível gravar em storage/.');
    }
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Só aceita links http(s) — bloqueia javascript: e afins. */
function vsl_safe_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    return preg_match('~^https?://~i', $url) ? $url : '';
}

function vsl_safe_color(string $c, string $fallback): string
{
    return preg_match('/^#[0-9a-f]{6}$/i', trim($c)) ? strtolower(trim($c)) : $fallback;
}

/** Caminho público de um arquivo enviado, ou '' se inválido. */
function vsl_upload_url(string $name): string
{
    return ($name !== '' && preg_match('/^[a-f0-9]{16}\.[a-z0-9]{2,4}$/', $name)) ? VSL_UPLOAD_URL . '/' . $name : '';
}

/**
 * Processa um upload. Retorna o nome do arquivo salvo, '' se nada foi enviado,
 * ou lança RuntimeException com a mensagem para o painel.
 *
 * O tipo é conferido pelo conteúdo do arquivo (finfo), não pela extensão
 * enviada pelo navegador, e o nome é sempre gerado aqui.
 */
function vsl_handle_upload(array $file, string $kind): string
{
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('Arquivo maior que o limite do servidor (upload_max_filesize / post_max_size).');
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
        throw new RuntimeException('Falha no envio do arquivo (código ' . $err . ').');
    }

    $types = $kind === 'video' ? VSL_VIDEO_TYPES : VSL_IMAGE_TYPES;
    $max   = $kind === 'video' ? VSL_VIDEO_MAX : VSL_IMAGE_MAX;

    if ((int) $file['size'] > $max) {
        throw new RuntimeException('Arquivo muito grande (máx. ' . round($max / 1048576) . ' MB).');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
    if (!isset($types[$mime])) {
        $aceitos = $kind === 'video' ? 'MP4 ou WEBM' : 'JPG, PNG, WEBP ou GIF';
        throw new RuntimeException('Formato não aceito. Envie ' . $aceitos . '.');
    }

    if (!is_dir(VSL_UPLOAD_DIR) && !mkdir(VSL_UPLOAD_DIR, 0775, true) && !is_dir(VSL_UPLOAD_DIR)) {
        throw new RuntimeException('Não foi possível criar a pasta vsl/uploads/.');
    }
    $name = bin2hex(random_bytes(8)) . '.' . $types[$mime];
    if (!move_uploaded_file((string) $file['tmp_name'], VSL_UPLOAD_DIR . '/' . $name)) {
        throw new RuntimeException('Não foi possível salvar o arquivo em vsl/uploads/.');
    }
    return $name;
}

function vsl_delete_upload(string $name): void
{
    if (vsl_upload_url($name) !== '' && is_file(VSL_UPLOAD_DIR . '/' . $name)) {
        @unlink(VSL_UPLOAD_DIR . '/' . $name);
    }
}

/**
 * Converte links do YouTube/Vimeo para o endereço de embed.
 * Retorna ['kind' => 'iframe'|'video'|'', 'src' => ...].
 */
function vsl_parse_video_url(string $url): array
{
    $url = vsl_safe_url($url);
    if ($url === '') {
        return ['kind' => '', 'src' => ''];
    }
    if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
        return ['kind' => 'iframe', 'src' => 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0&modestbranding=1&playsinline=1'];
    }
    if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
        return ['kind' => 'iframe', 'src' => 'https://player.vimeo.com/video/' . $m[1]];
    }
    if (preg_match('~\.(mp4|webm)(\?.*)?$~i', $url)) {
        return ['kind' => 'video', 'src' => $url];
    }
    // Qualquer outro link de player (Panda, VTurb etc.) vai num iframe.
    return ['kind' => 'iframe', 'src' => $url];
}

function vsl_client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/** Configuração do painel (senha). Variável de ambiente tem prioridade. */
function vsl_config(): array
{
    $cfg = [];
    if (is_file(__DIR__ . '/config.php')) {
        $loaded = require __DIR__ . '/config.php';
        if (is_array($loaded)) {
            $cfg = $loaded;
        }
    }
    $env = getenv('VSL_ADMIN_PASSWORD_HASH');
    if (is_string($env) && $env !== '') {
        $cfg['admin_password_hash'] = $env;
    }
    return $cfg;
}
