<?php
declare(strict_types=1);

/**
 * Utilitários compartilhados pelos endpoints da API.
 * Este arquivo não responde nada sozinho.
 */

function carregarConfig(): array
{
    $caminho = __DIR__ . '/config.php';
    if (!is_file($caminho)) {
        responder(500, ['erro' => 'Servidor não configurado.']);
    }
    return require $caminho;
}

function responder(int $status, array $dados): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function aplicarCors(array $config): void
{
    $origem = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origem !== '' && in_array($origem, $config['allowed_origins'], true)) {
        header('Access-Control-Allow-Origin: ' . $origem);
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function corpoJson(): array
{
    $bruto = file_get_contents('php://input');
    $dados = json_decode((string) $bruto, true);
    return is_array($dados) ? $dados : [];
}

/** Registra no log de erros do servidor, sem devolver detalhes ao cliente. */
function registrarErro(string $contexto, string $detalhe): void
{
    error_log(sprintf('[zuckpay][%s] %s', $contexto, $detalhe));
}

/** Valida CPF incluindo os dígitos verificadores. */
function cpfValido(string $cpf): bool
{
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    for ($posicao = 9; $posicao < 11; $posicao++) {
        $soma = 0;
        for ($i = 0; $i < $posicao; $i++) {
            $soma += (int) $cpf[$i] * (($posicao + 1) - $i);
        }
        $digito = ((10 * $soma) % 11) % 10;
        if ((int) $cpf[$posicao] !== $digito) {
            return false;
        }
    }
    return true;
}

/**
 * Chamada autenticada à API da ZuckPay.
 * O client_secret nunca sai daqui — não é devolvido ao navegador em hipótese alguma.
 *
 * @return array{0:int,1:array} [status http, corpo decodificado]
 */
function chamarZuckpay(array $config, string $metodo, string $caminho, ?array $payload = null): array
{
    $url = rtrim($config['api_base'], '/') . $caminho;
    $autorizacao = 'Basic ' . base64_encode($config['client_id'] . ':' . $config['client_secret']);

    $cabecalhos = ['Accept: application/json', 'Authorization: ' . $autorizacao];
    $ch = curl_init($url);
    $opcoes = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];

    if ($metodo === 'POST') {
        $opcoes[CURLOPT_POST] = true;
        $opcoes[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $cabecalhos[] = 'Content-Type: application/json';
    }

    $opcoes[CURLOPT_HTTPHEADER] = $cabecalhos;
    curl_setopt_array($ch, $opcoes);

    $resposta = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erroCurl = curl_error($ch);
    curl_close($ch);

    if ($resposta === false) {
        registrarErro('curl', $erroCurl);
        return [0, []];
    }

    $decodificado = json_decode((string) $resposta, true);
    if (!is_array($decodificado)) {
        registrarErro('resposta', 'HTTP ' . $status . ' com corpo não-JSON');
        return [$status, []];
    }

    return [$status, $decodificado];
}
