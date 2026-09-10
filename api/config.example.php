<?php
/**
 * Copie este arquivo para config.php e preencha com os dados reais.
 * config.php está no .gitignore e NUNCA deve ser versionado.
 */

return [
    // Credenciais da ZuckPay (painel > Integrações > API keys)
    'client_id'     => getenv('ZUCKPAY_CLIENT_ID')     ?: 'seu_client_id',
    'client_secret' => getenv('ZUCKPAY_CLIENT_SECRET') ?: 'seu_client_secret',

    /**
     * Base da API. Use exatamente o host que aparece na sua tela de
     * Credenciais API — com ou sem "www". Se estiver errado, a ZuckPay
     * responde com um redirecionamento e o POST não chega: o
     * api/diagnostico.php detecta isso e aponta a URL certa.
     *
     * Para testes: https://zuckpay.com.br/conta/dev/api/pix
     */
    'api_base' => 'https://zuckpay.com.br/conta/v3/pix',

    /**
     * Planos vendidos na página.
     *
     * O preço fica AQUI, no servidor. O navegador envia apenas o id do plano
     * ("basico" ou "premium") — um valor vindo do cliente é sempre ignorado.
     *
     * product_id: id do produto cadastrado no painel da ZuckPay. É opcional
     * na API, mas preenchê-lo vincula a venda ao produto nos relatórios.
     * Se cada plano tiver seu próprio produto, use um id diferente em cada um.
     */
    'planos' => [
        'basico' => [
            'nome'       => 'Pacote Básico — Arritmias Cardíacas',
            'valor'      => 9.99,
            'product_id' => 593187,
        ],
        'premium' => [
            'nome'       => 'Pacote Premium — Arritmias Cardíacas',
            'valor'      => 29.90,
            'product_id' => 593187,
        ],
    ],

    // URL pública que a ZuckPay chama quando o pagamento muda de status.
    // Cadastre-a também em Integrações > Webhooks no painel.
    'webhook_url' => 'https://SEU-DOMINIO.com.br/api/webhook.php',

    /**
     * Webhook Secret — gerado no painel em Integrações > Webhook Secret.
     * É DIFERENTE do client_secret.
     *
     * Com ele preenchido, api/webhook.php valida o header
     * X-ZuckPay-Signature e recusa qualquer POST que não venha da ZuckPay.
     * Vazio, os postbacks continuam chegando sem assinatura e a validação
     * fica só por reconsulta à API.
     */
    'webhook_secret' => '',

    // Origens autorizadas a chamar estes endpoints (CORS).
    'allowed_origins' => [
        'https://SEU-DOMINIO.com.br',
        'https://www.SEU-DOMINIO.com.br',
    ],

    // Onde gravar o log de pagamentos confirmados.
    'log_path' => __DIR__ . '/../storage/pagamentos.log',

    /**
     * Modo diagnóstico.
     *
     * Com true, os endpoints devolvem a mensagem de erro real da ZuckPay em
     * vez da mensagem genérica — útil para descobrir por que o PIX não gera.
     * DESLIGUE depois de resolver: mensagens de erro podem revelar detalhes
     * da conta.
     */
    'debug' => false,

    /**
     * Token do api/diagnostico.php. Troque por uma string aleatória.
     * Sem ele o diagnóstico responde 404.
     */
    'debug_token' => '',
];
