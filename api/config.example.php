<?php
/**
 * Copie este arquivo para config.php e preencha com as credenciais reais.
 * config.php está no .gitignore e NUNCA deve ser versionado.
 *
 * Em produção, prefira variáveis de ambiente:
 *   'client_id' => getenv('ZUCKPAY_CLIENT_ID') ?: '',
 */

return [
    // Credenciais da ZuckPay (painel > Integrações > API keys)
    'client_id'     => getenv('ZUCKPAY_CLIENT_ID')     ?: 'seu_client_id',
    'client_secret' => getenv('ZUCKPAY_CLIENT_SECRET') ?: 'seu_client_secret',

    // Produção. Para testes use: https://www.zuckpay.com.br/conta/dev/api/pix
    'api_base' => 'https://www.zuckpay.com.br/conta/v3/pix',

    // URL pública que a ZuckPay chama quando o pagamento muda de status.
    // Precisa apontar para o api/webhook.php deste projeto.
    'webhook_url' => 'https://SEU-DOMINIO.com.br/api/webhook.php',

    // Origens autorizadas a chamar estes endpoints (CORS).
    'allowed_origins' => [
        'https://SEU-DOMINIO.com.br',
        'https://www.SEU-DOMINIO.com.br',
    ],

    // Onde gravar o log de pagamentos confirmados.
    'log_path' => __DIR__ . '/../storage/pagamentos.log',
];
