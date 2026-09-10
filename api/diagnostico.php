<?php
declare(strict_types=1);

/**
 * Diagnóstico da integração. Acesse:
 *   /api/diagnostico.php?token=SEU_DEBUG_TOKEN
 *
 * Verifica ambiente, configuração e faz uma cobrança de teste de R$ 1,00
 * contra a ZuckPay, mostrando a resposta real.
 *
 * Protegido por token. Se debug_token estiver vazio no config, responde 404.
 * APAGUE OU DESATIVE este arquivo depois de resolver.
 */

require __DIR__ . '/_bootstrap.php';

$config = carregarConfig();

$token = (string) ($config['debug_token'] ?? '');
$informado = (string) ($_GET['token'] ?? '');

if ($token === '' || !hash_equals($token, $informado)) {
    responder(404, ['erro' => 'Não encontrado.']);
}

/** Mostra só as pontas de um segredo, nunca o valor inteiro. */
function mascarar(string $valor): string
{
    $tamanho = strlen($valor);
    if ($tamanho === 0) {
        return '(vazio)';
    }
    if ($tamanho <= 8) {
        return str_repeat('*', $tamanho) . " ({$tamanho} chars)";
    }
    return substr($valor, 0, 4) . str_repeat('*', 6) . substr($valor, -4) . " ({$tamanho} chars)";
}

$checagens = [];

// 1. Ambiente
$checagens['php'] = PHP_VERSION;
$checagens['curl'] = extension_loaded('curl') ? 'ok' : 'FALTANDO — instale a extensão php-curl';
$checagens['openssl'] = extension_loaded('openssl') ? 'ok' : 'FALTANDO';

// 2. Configuração
$checagens['client_id']     = mascarar((string) ($config['client_id'] ?? ''));
$checagens['client_secret'] = mascarar((string) ($config['client_secret'] ?? ''));
$checagens['api_base']      = (string) ($config['api_base'] ?? '');
$checagens['webhook_url']   = (string) ($config['webhook_url'] ?? '');

foreach (['client_id', 'client_secret'] as $campo) {
    $valor = (string) ($config[$campo] ?? '');
    if ($valor === '' || str_starts_with($valor, 'seu_')) {
        $checagens['ATENCAO_' . $campo] = 'ainda está com o valor de exemplo do config.example.php';
    }
}

if (!str_starts_with((string) ($config['webhook_url'] ?? ''), 'https://')
    || str_contains((string) ($config['webhook_url'] ?? ''), 'SEU-DOMINIO')) {
    $checagens['ATENCAO_webhook_url'] = 'precisa ser a URL pública real do api/webhook.php';
}

// 3. Planos
foreach (($config['planos'] ?? []) as $id => $plano) {
    $checagens['plano_' . $id] = sprintf(
        'R$ %s | product_id: %s',
        number_format((float) $plano['valor'], 2, ',', '.'),
        $plano['product_id'] ?? '(nenhum)'
    );
}

// 4. Cobrança de teste — R$ 1,00 com CPF de teste válido
$planoTeste = array_key_first($config['planos'] ?? []);
$payload = [
    'nome'     => 'Teste Diagnostico',
    'cpf'      => '52998224725',
    'valor'    => 1.00,
    'email'    => 'teste@exemplo.com',
    'telefone' => '11999998888',
    'urlnoty'  => $config['webhook_url'],
];
if ($planoTeste !== null && !empty($config['planos'][$planoTeste]['product_id'])) {
    $payload['product_id'] = (int) $config['planos'][$planoTeste]['product_id'];
}

[$status, $resposta] = chamarZuckpay($config, 'POST', '/qrcode', $payload);

$teste = ['http' => $status];

if ($status === 0) {
    $teste['resultado'] = 'FALHA DE CONEXAO — o servidor não conseguiu alcançar a ZuckPay. '
        . 'Veja o error_log do PHP. Costuma ser firewall de saída ou DNS na hospedagem.';
} elseif ($status === 401 || $status === 403) {
    $teste['resultado'] = 'NAO AUTORIZADO — client_id/client_secret errados, revogados, '
        . 'ou a chave não tem permissão para PIX.';
} elseif ($status === 404) {
    $teste['resultado'] = 'ENDPOINT NAO ENCONTRADO — confira o api_base.';
} elseif ($status === 200 && !empty($resposta['transactionId'])) {
    $teste['resultado'] = 'OK — cobrança de teste criada. A integração está funcionando.';
    $teste['transactionId'] = $resposta['transactionId'];
    $teste['tem_qrcode'] = !empty($resposta['qrcode']) ? 'sim' : 'NAO (a API não devolveu o código)';
} else {
    $teste['resultado'] = 'A API respondeu, mas sem transactionId. Veja o corpo abaixo.';
}

$teste['resposta_da_api'] = $resposta;
$teste['enviado'] = array_diff_key($payload, ['cpf' => 1, 'email' => 1, 'telefone' => 1]);

responder(200, [
    'atencao'   => 'Endpoint de diagnóstico. Desative ou apague após resolver.',
    'checagens' => $checagens,
    'teste'     => $teste,
]);
