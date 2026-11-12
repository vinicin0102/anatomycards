<?php
declare(strict_types=1);

/**
 * Notificação da ZuckPay para os pedidos de foto.
 * Mesma verificação em duas camadas do api/webhook.php:
 * assinatura HMAC (se houver webhook_secret) + reconsulta do status na API.
 */

require __DIR__ . '/_fotos.php';

$config = configFotos();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    responder(405, ['erro' => 'Método não permitido.']);
}

$corpoRaw = (string) file_get_contents('php://input');

$segredo = (string) ($config['webhook_secret'] ?? '');
if ($segredo !== '') {
    [$valida, $motivo] = assinaturaWebhookValida((string) ($_SERVER['HTTP_X_ZUCKPAY_SIGNATURE'] ?? ''), $corpoRaw, $segredo);
    if (!$valida) {
        registrarErro('fotos-webhook', 'assinatura recusada: ' . $motivo);
        responder(401, ['erro' => 'Assinatura inválida.']);
    }
}

$corpo     = json_decode($corpoRaw, true);
$corpo     = is_array($corpo) ? $corpo : [];
$transacao = is_array($corpo['transaction'] ?? null) ? $corpo['transaction'] : [];
$transactionId = (string) ($transacao['id'] ?? $corpo['transactionId'] ?? $corpo['transaction_id'] ?? '');
$evento    = (string) ($corpo['event'] ?? '');

if (!preg_match('/^[A-Za-z0-9._-]{8,128}$/', $transactionId)) {
    responder(400, ['erro' => 'transactionId ausente ou inválido.']);
}
if (in_array($evento, ['payment_refused', 'payment_pending', 'checkout_abandoned'], true)) {
    responder(200, ['ok' => true, 'ignorado' => $evento]);
}

$pedidoId = pedidoDaTransacao($config, $transactionId);
if ($pedidoId === null) {
    // Não é um pedido de foto (ou é de outro produto): responde 200 para não haver reenvio.
    responder(200, ['ok' => true, 'ignorado' => 'pedido_desconhecido']);
}

[$status, $resposta] = chamarZuckpay($config, 'GET', '/status?transactionId=' . urlencode($transactionId));
if ($status !== 200 || !isset($resposta['status'])) {
    responder(502, ['erro' => 'Não foi possível verificar a transação.']);
}
if (strtoupper((string) $resposta['status']) !== 'PAID') {
    responder(200, ['ok' => true, 'ignorado' => 'nao_pago']);
}

marcarPago($config, $pedidoId, $transactionId, $resposta);
responder(200, ['ok' => true]);
