<?php
declare(strict_types=1);

/**
 * Consulta o status de uma cobrança PIX.
 * GET ?transactionId=...  ->  { status, pago, confirmado_em }
 */

require __DIR__ . '/_bootstrap.php';

$config = carregarConfig();
aplicarCors($config);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    responder(405, ['erro' => 'Método não permitido.']);
}

$transactionId = (string) ($_GET['transactionId'] ?? '');
if ($transactionId === '' || !preg_match('/^[A-Za-z0-9._-]{8,128}$/', $transactionId)) {
    responder(400, ['erro' => 'transactionId inválido.']);
}

[$status, $resposta] = chamarZuckpay(
    $config,
    'GET',
    '/status?transactionId=' . urlencode($transactionId)
);

if ($status !== 200 || !isset($resposta['status'])) {
    registrarErro('status', 'HTTP ' . $status . ' para ' . $transactionId);
    responder(502, ['erro' => 'Não foi possível consultar o pagamento.']);
}

$situacao = strtoupper((string) $resposta['status']);

// Só o essencial. Valores e dados do comprador não voltam para o navegador.
responder(200, [
    'status'        => $situacao,
    'pago'          => $situacao === 'PAID',
    'confirmado_em' => (string) ($resposta['confirmed_date'] ?? ''),
]);
