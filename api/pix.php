<?php
declare(strict_types=1);

/**
 * Cria uma cobrança PIX na ZuckPay.
 *
 * POST { plano, nome, cpf, email, telefone, rastreio? }
 * -> { transactionId, qrcode, qrcode_image, checkout_url, expiracao, valor }
 */

require __DIR__ . '/_bootstrap.php';

$config = carregarConfig();
aplicarCors($config);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    responder(405, ['erro' => 'Método não permitido.']);
}

/**
 * O preço é definido AQUI, no servidor, a partir do id do plano.
 * O valor enviado pelo navegador é ignorado de propósito: se ele fosse
 * aceito, qualquer pessoa poderia editar o request e pagar R$ 0,01.
 */
const PLANOS = [
    'basico'  => ['nome' => 'Pacote Básico — Arritmias Cardíacas',  'valor' => 9.99],
    'premium' => ['nome' => 'Pacote Premium — Arritmias Cardíacas', 'valor' => 29.90],
];

$corpo = corpoJson();

$planoId = is_string($corpo['plano'] ?? null) ? $corpo['plano'] : '';
if (!isset(PLANOS[$planoId])) {
    responder(400, ['erro' => 'Plano inválido.']);
}
$plano = PLANOS[$planoId];

$nome     = trim((string) ($corpo['nome'] ?? ''));
$cpf      = preg_replace('/\D/', '', (string) ($corpo['cpf'] ?? '')) ?? '';
$email    = trim((string) ($corpo['email'] ?? ''));
$telefone = preg_replace('/\D/', '', (string) ($corpo['telefone'] ?? '')) ?? '';

$erros = [];
if (mb_strlen($nome) < 3 || mb_strlen($nome) > 100) {
    $erros['nome'] = 'Informe seu nome completo.';
}
if (!cpfValido($cpf)) {
    $erros['cpf'] = 'CPF inválido.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    $erros['email'] = 'E-mail inválido.';
}
if (strlen($telefone) < 10 || strlen($telefone) > 11) {
    $erros['telefone'] = 'Telefone inválido. Use DDD + número.';
}
if ($erros !== []) {
    responder(422, ['erro' => 'Dados inválidos.', 'campos' => $erros]);
}

// Parâmetros de atribuição (UTMs, Meta, Google, TikTok). Opcionais.
$payload = [
    'nome'     => $nome,
    'cpf'      => $cpf,
    'valor'    => $plano['valor'],
    'email'    => $email,
    'telefone' => $telefone,
    'urlnoty'  => $config['webhook_url'],
];

$rastreio = is_array($corpo['rastreio'] ?? null) ? $corpo['rastreio'] : [];
$permitidos = [
    'utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term',
    'fbc', 'fbp', 'gclid', 'ttclid', 'click_id',
];
foreach ($permitidos as $chave) {
    $valor = $rastreio[$chave] ?? null;
    if (is_string($valor) && $valor !== '') {
        $payload[$chave] = mb_substr($valor, 0, 255);
    }
}

[$status, $resposta] = chamarZuckpay($config, 'POST', '/qrcode', $payload);

if ($status !== 200 || empty($resposta['transactionId'])) {
    registrarErro('pix', 'HTTP ' . $status . ' ' . json_encode($resposta, JSON_UNESCAPED_UNICODE));
    responder(502, ['erro' => 'Não foi possível gerar o PIX agora. Tente novamente em instantes.']);
}

// Devolve só o que o navegador precisa. Nada de credencial, nada de valor líquido.
responder(200, [
    'transactionId' => (string) $resposta['transactionId'],
    'qrcode'        => (string) ($resposta['qrcode'] ?? $resposta['pix_code'] ?? ''),
    'qrcode_image'  => (string) ($resposta['qrcode_image'] ?? ''),
    'checkout_url'  => (string) ($resposta['checkout_url'] ?? ''),
    'expiracao'     => (int) ($resposta['calendar']['expiration'] ?? 1200),
    'valor'         => $plano['valor'],
    'plano'         => $plano['nome'],
]);
