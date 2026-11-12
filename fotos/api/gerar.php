<?php
declare(strict_types=1);

/**
 * Recebe a foto da cliente, gera a montagem e devolve SÓ a prévia com marca d'água.
 *
 * POST multipart: foto, foto2 (só para "ente"), acompanhante, pose, cenario, nome_famoso, obs
 * -> { pedido, token, previa (data URL), segundos, valor }
 *
 * A foto final fica no servidor e só sai por api/foto.php depois do pagamento.
 */

require __DIR__ . '/_fotos.php';

@set_time_limit(200);

$config = configFotos();
aplicarCors($config);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    responder(405, ['erro' => 'Método não permitido.']);
}

$acompanhante = (string) ($_POST['acompanhante'] ?? '');
$pose         = (string) ($_POST['pose'] ?? 'lado_a_lado');
$cenario      = (string) ($_POST['cenario'] ?? 'ceu');
$nomeFamoso   = trim(preg_replace('/[^\p{L}\p{N} .\'-]/u', '', (string) ($_POST['nome_famoso'] ?? '')) ?? '');
$obs          = trim(preg_replace('/[\x00-\x1F"\\\\]/u', ' ', (string) ($_POST['obs'] ?? '')) ?? '');

if (!isset(ACOMPANHANTES[$acompanhante])) {
    responder(422, ['erro' => 'Escolha quem vai aparecer na foto com você.']);
}
if (!isset(POSES[$pose]) || !isset(CENARIOS[$cenario])) {
    responder(422, ['erro' => 'Escolha a pose e o cenário.']);
}
if ($acompanhante === 'famoso' && (mb_strlen($nomeFamoso) < 3 || mb_strlen($nomeFamoso) > 60)) {
    responder(422, ['erro' => 'Escreva o nome da pessoa famosa.']);
}
$obs = mb_substr($obs, 0, 200);

function lerUpload(string $campo): ?string
{
    $arq = $_FILES[$campo] ?? null;
    if (!is_array($arq) || ($arq['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    if ((int) $arq['size'] > 15 * 1024 * 1024 || !is_uploaded_file((string) $arq['tmp_name'])) {
        return null;
    }
    return normalizarImagem((string) file_get_contents((string) $arq['tmp_name']));
}

$foto = lerUpload('foto');
if ($foto === null) {
    responder(422, ['erro' => 'Não conseguimos abrir sua foto. Envie uma foto JPG ou PNG.']);
}

$referencias = [$foto];
if ($acompanhante === 'ente') {
    $foto2 = lerUpload('foto2');
    if ($foto2 === null) {
        responder(422, ['erro' => 'Envie também a foto da pessoa querida.']);
    }
    $referencias[] = $foto2;
}

// Limites só depois de validar, para um erro de preenchimento não gastar tentativa.
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
if (!consumirLimite($config, 'ip:' . $ip, (int) ($config['limite_por_ip_dia'] ?? 4))) {
    responder(429, ['erro' => 'Você já criou várias prévias hoje. Libere uma das suas fotos ou volte amanhã.']);
}
if (!consumirLimite($config, 'global', (int) ($config['limite_global_dia'] ?? 300))) {
    registrarErro('gerar', 'limite global diário atingido');
    responder(503, ['erro' => 'Estamos com muitos pedidos agora. Tente de novo em algumas horas.']);
}

$pedidoId = bin2hex(random_bytes(12));
$token    = bin2hex(random_bytes(16));
$dir      = dirPedido($config, $pedidoId);
@mkdir($dir, 0770, true);

file_put_contents($dir . '/entrada.jpg', $foto);
if (isset($referencias[1])) {
    file_put_contents($dir . '/entrada2.jpg', $referencias[1]);
}

$pedido = [
    'token'        => $token,
    'criado_em'    => date('c'),
    'ip'           => hash('sha256', $ip),
    'acompanhante' => $acompanhante,
    'nome_famoso'  => $nomeFamoso,
    'pose'         => $pose,
    'cenario'      => $cenario,
    'obs'          => $obs,
    'status'       => 'gerando',
    'pago'         => false,
];
pedidoGravar($config, $pedidoId, $pedido);

$prompt = montarPrompt($acompanhante, $pose, $cenario, $nomeFamoso, $obs);
[$ok, $resultado] = gerarComFal($config, $prompt, $referencias);

if (!$ok) {
    $pedido['status'] = 'falhou:' . $resultado;
    pedidoGravar($config, $pedidoId, $pedido);

    // Falha do nosso lado (chave, rede, API fora): não conta como tentativa da cliente.
    if ($resultado !== 'recusado') {
        devolverLimite($config, 'ip:' . $ip);
        devolverLimite($config, 'global');
    }

    $mensagens = [
        'recusado' => 'Não conseguimos montar essa foto. Tente outra foto sua (de frente, bem iluminada) ou outro acompanhante.',
        'config'   => 'Serviço em manutenção. Tente novamente em alguns minutos.',
    ];
    responder(502, ['erro' => $mensagens[$resultado] ?? 'Não conseguimos criar sua foto agora. Tente de novo em instantes.']);
}

$final = normalizarImagem($resultado, 2400, 92) ?? $resultado;
file_put_contents($dir . '/resultado.jpg', $final);

$previa = gerarPrevia($final);
$pedido['status'] = 'pronto';
pedidoGravar($config, $pedidoId, $pedido);

responder(200, [
    'pedido'   => $pedidoId,
    'token'    => $token,
    'previa'   => 'data:image/jpeg;base64,' . base64_encode($previa),
    'segundos' => (int) ($config['previa_segundos'] ?? 5),
    'valor'    => (float) $config['produto']['valor'],
]);
