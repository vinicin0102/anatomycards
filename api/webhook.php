<?php
declare(strict_types=1);

/**
 * Recebe as notificações da ZuckPay (campo urlnoty da cobrança).
 *
 * A ZuckPay não assina o corpo da requisição, então o payload recebido
 * NÃO é tratado como fonte de verdade: pegamos apenas o transactionId e
 * confirmamos o pagamento consultando a própria API. Assim um POST forjado
 * por terceiros não consegue liberar acesso.
 */

require __DIR__ . '/_bootstrap.php';

$config = carregarConfig();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    responder(405, ['erro' => 'Método não permitido.']);
}

$corpo = corpoJson();
$transactionId = (string) ($corpo['transactionId'] ?? $corpo['transaction_id'] ?? '');

if ($transactionId === '' || !preg_match('/^[A-Za-z0-9._-]{8,128}$/', $transactionId)) {
    responder(400, ['erro' => 'transactionId ausente ou inválido.']);
}

// Confirma direto na fonte, ignorando o status que veio no POST.
[$status, $resposta] = chamarZuckpay(
    $config,
    'GET',
    '/status?transactionId=' . urlencode($transactionId)
);

if ($status !== 200 || !isset($resposta['status'])) {
    registrarErro('webhook', 'falha ao verificar ' . $transactionId);
    responder(502, ['erro' => 'Não foi possível verificar a transação.']);
}

if (strtoupper((string) $resposta['status']) !== 'PAID') {
    // Ainda não pago: responde 200 para a ZuckPay não ficar reenviando.
    responder(200, ['ok' => true, 'ignorado' => true]);
}

$linha = json_encode([
    'transactionId' => $transactionId,
    'email'         => $resposta['email'] ?? null,
    'valor'         => $resposta['amount'] ?? null,
    'confirmado_em' => $resposta['confirmed_date'] ?? null,
    'registrado_em' => date('c'),
], JSON_UNESCAPED_UNICODE);

$diretorio = dirname((string) $config['log_path']);
if (!is_dir($diretorio)) {
    @mkdir($diretorio, 0770, true);
}
@file_put_contents($config['log_path'], $linha . PHP_EOL, FILE_APPEND | LOCK_EX);

/*
 * TODO — entrega do produto.
 * Aqui entra o envio do e-mail com o link dos PDFs / liberação da área de
 * membros. A ZuckPay pode reenviar a mesma notificação mais de uma vez,
 * então grave o transactionId e só entregue se ainda não tiver entregado.
 */

responder(200, ['ok' => true]);
