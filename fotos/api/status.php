<?php
declare(strict_types=1);

/**
 * GET ?pedido=...&token=...  ->  { pago, status, foto, baixar, proxima_consulta }
 *
 * Quando pago, devolve os links da foto final.
 */

require __DIR__ . '/_fotos.php';

$config = configFotos();
aplicarCors($config);

$pedidoId = (string) ($_GET['pedido'] ?? '');
$token    = (string) ($_GET['token'] ?? '');
$pedido   = pedidoAutorizado($config, $pedidoId, $token);

$pago = function () use ($pedidoId, $token): never {
    responder(200, [
        'pago'   => true,
        'status' => 'PAID',
        'foto'   => urlFoto($pedidoId, $token),
        'baixar' => urlFoto($pedidoId, $token, true),
    ]);
};

if (!empty($pedido['pago'])) {
    $pago();
}

$transactionId = (string) ($pedido['transactionId'] ?? '');
if ($transactionId === '') {
    responder(200, ['pago' => false, 'status' => 'SEM_COBRANCA', 'proxima_consulta' => 5]);
}

$cache = cacheStatusLer($config, $transactionId);
if ($cache === null) {
    [$http, $resposta] = chamarZuckpay($config, 'GET', '/status?transactionId=' . urlencode($transactionId));

    if ($http === 429) {
        responder(200, ['pago' => false, 'status' => 'PENDING', 'proxima_consulta' => 30]);
    }
    if ($http !== 200 || !isset($resposta['status'])) {
        registrarErro('fotos-status', 'HTTP ' . $http . ' para ' . $transactionId);
        responder(200, ['pago' => false, 'status' => 'PENDING', 'proxima_consulta' => 10]);
    }
    $cache = ['status' => strtoupper((string) $resposta['status']), 'amount' => $resposta['amount'] ?? null];
    cacheStatusGravar($config, $transactionId, $cache);
}

if ($cache['status'] === 'PAID') {
    marcarPago($config, $pedidoId, $transactionId, $cache);
    $pago();
}

responder(200, [
    'pago'             => false,
    'status'           => $cache['status'],
    'final'            => in_array($cache['status'], ['FAILED', 'REFUSED', 'EXPIRADO', 'REFUNDED'], true),
    'proxima_consulta' => 5,
]);
