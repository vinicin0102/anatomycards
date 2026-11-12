<?php
declare(strict_types=1);

/**
 * Entrega a foto final em alta qualidade — somente de pedido pago.
 * GET ?pedido=...&token=...[&baixar=1]
 */

require __DIR__ . '/_fotos.php';

$config   = configFotos();
$pedidoId = (string) ($_GET['pedido'] ?? '');
$pedido   = pedidoAutorizado($config, $pedidoId, (string) ($_GET['token'] ?? ''));

if (empty($pedido['pago'])) {
    responder(402, ['erro' => 'Pagamento ainda não confirmado.']);
}

$arquivo = dirPedido($config, $pedidoId) . '/resultado.jpg';
if (!is_file($arquivo)) {
    responder(404, ['erro' => 'Foto não encontrada.']);
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($arquivo));
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
$disposicao = isset($_GET['baixar']) ? 'attachment' : 'inline';
header('Content-Disposition: ' . $disposicao . '; filename="minha-foto-' . substr($pedidoId, 0, 6) . '.jpg"');
readfile($arquivo);
