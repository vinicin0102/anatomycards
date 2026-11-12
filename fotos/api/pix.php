<?php
declare(strict_types=1);

/**
 * Cria a cobrança PIX de um pedido já gerado.
 *
 * POST { pedido, token, nome, cpf, email, telefone, rastreio? }
 * -> { transactionId, qrcode, qrcode_image, expiracao, valor }
 */

require __DIR__ . '/_fotos.php';

$config = configFotos();
aplicarCors($config);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    responder(405, ['erro' => 'Método não permitido.']);
}

$corpo    = corpoJson();
$pedidoId = (string) ($corpo['pedido'] ?? '');
$pedido   = pedidoAutorizado($config, $pedidoId, (string) ($corpo['token'] ?? ''));

if (($pedido['status'] ?? '') !== 'pronto') {
    responder(409, ['erro' => 'Esta foto ainda não está pronta.']);
}
if (!empty($pedido['pago'])) {
    responder(409, ['erro' => 'Esta foto já foi paga.', 'pago' => true]);
}

$nome     = trim((string) ($corpo['nome'] ?? ''));
$cpf      = preg_replace('/\D/', '', (string) ($corpo['cpf'] ?? '')) ?? '';
$email    = trim((string) ($corpo['email'] ?? ''));
$telefone = preg_replace('/\D/', '', (string) ($corpo['telefone'] ?? '')) ?? '';

$erros = [];
if (mb_strlen($nome) < 3 || mb_strlen($nome) > 100) {
    $erros['nome'] = 'Escreva seu nome completo.';
}
if (!cpfValido($cpf)) {
    $erros['cpf'] = 'CPF inválido. Confira os números.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    $erros['email'] = 'E-mail inválido.';
}
if (strlen($telefone) < 10 || strlen($telefone) > 11) {
    $erros['telefone'] = 'WhatsApp inválido. Coloque o DDD + número.';
}
if ($erros !== []) {
    responder(422, ['erro' => 'Confira os dados destacados.', 'campos' => $erros]);
}

$produto = $config['produto'];
$payload = [
    'nome'               => $nome,
    'cpf'                => $cpf,
    'valor'              => $produto['valor'],
    'email'              => $email,
    'telefone'           => $telefone,
    'urlnoty'            => $config['webhook_url'],
    'descricao'          => $produto['nome'],
    // Mesmo pedido = mesma cobrança: clicar duas vezes não gera dois PIX.
    'external_id_client' => 'MF-' . $pedidoId,
];
if (!empty($produto['product_id'])) {
    $payload['product_id'] = (int) $produto['product_id'];
}

$rastreio = is_array($corpo['rastreio'] ?? null) ? $corpo['rastreio'] : [];
foreach (['utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term', 'fbc', 'fbp', 'fbclid', 'gclid', 'ttclid', 'src', 'sck'] as $chave) {
    if (is_string($rastreio[$chave] ?? null) && $rastreio[$chave] !== '') {
        $payload[$chave] = mb_substr($rastreio[$chave], 0, 255);
    }
}

[$status, $resposta] = chamarZuckpay($config, 'POST', '/qrcode', $payload);

if ($status === 429) {
    responder(429, ['erro' => 'Muitas tentativas em pouco tempo. Aguarde alguns minutos e tente de novo.']);
}
if ($status !== 200 || empty($resposta['transactionId'])) {
    $ref = bin2hex(random_bytes(4));
    registrarErro('fotos-pix', 'ref=' . $ref . ' HTTP ' . $status . ' ' . json_encode($resposta, JSON_UNESCAPED_UNICODE));
    $saida = ['erro' => 'Não foi possível gerar o PIX agora. Tente novamente em instantes.', 'ref' => $ref];
    if (!empty($config['debug'])) {
        $saida['debug'] = ['http' => $status, 'resposta' => $resposta];
    }
    responder(502, $saida);
}

$transactionId = (string) $resposta['transactionId'];
indexarTransacao($config, $transactionId, $pedidoId);

$pedido['transactionId'] = $transactionId;
$pedido['comprador']     = ['nome' => $nome, 'email' => $email, 'telefone' => $telefone];
pedidoGravar($config, $pedidoId, $pedido);

responder(200, [
    'transactionId' => $transactionId,
    'qrcode'        => (string) ($resposta['qrcode'] ?? $resposta['pix_code'] ?? ''),
    'qrcode_image'  => (string) ($resposta['qrcode_image'] ?? ''),
    'expiracao'     => (int) ($resposta['calendar']['expiration'] ?? 1200),
    'valor'         => $produto['valor'],
]);
