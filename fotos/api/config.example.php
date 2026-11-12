<?php
/**
 * Copie para config.php (que está no .gitignore) e preencha.
 *
 *   cp fotos/api/config.example.php fotos/api/config.php
 */

return [
    /* ---------- ZuckPay (mesma conta da página de arritmias) ---------- */
    'client_id'      => getenv('ZUCKPAY_CLIENT_ID')     ?: 'seu_client_id',
    'client_secret'  => getenv('ZUCKPAY_CLIENT_SECRET') ?: 'seu_client_secret',
    'api_base'       => 'https://www.zuckpay.com.br/conta/v3/pix',
    'webhook_secret' => getenv('ZUCKPAY_WEBHOOK_SECRET') ?: '',
    'webhook_url'    => 'https://SEU-DOMINIO.com.br/fotos/api/webhook.php',

    /**
     * O preço fica AQUI, no servidor. O navegador nunca envia valor.
     * Se mudar o preço, mude também o texto na página (constante PRECO no index.html).
     */
    'produto' => [
        'nome'       => 'Montagem de Foto em Alta Qualidade',
        'valor'      => 19.90,
        'product_id' => 0, // id do produto no painel da ZuckPay (opcional)
    ],

    /* ---------- Geração de imagem (fal.ai) ---------- */
    // Chave em https://fal.ai/dashboard/keys — prefira a variável de ambiente.
    'fal_key' => getenv('FAL_KEY') ?: '',

    /**
     * Modelo de edição com várias imagens de referência.
     *   fal-ai/nano-banana-2/edit           (Google; ótima fidelidade de rosto)
     *   fal-ai/bytedance/seedream/v4/edit   (alternativa; teste os dois)
     * Confira o id atual e o preço na página do modelo no fal.ai.
     */
    'fal_modelo' => 'fal-ai/nano-banana-2/edit',

    // Parâmetros extras enviados ao modelo (variam por modelo).
    'fal_parametros' => [
        'aspect_ratio' => '3:4',
    ],

    /* ---------- Proteção do orçamento ---------- */
    // Cada geração custa dinheiro mesmo se a pessoa não comprar.
    'limite_por_ip_dia' => 4,
    'limite_global_dia' => 300,

    // Quantos segundos a prévia fica visível antes de borrar.
    'previa_segundos' => 5,

    /**
     * Onde ficam fotos enviadas, resultados e pedidos.
     * O ideal é FORA da pasta pública (ex.: /home/usuario/dados/fotos).
     * Se ficar dentro, o código cria um .htaccess negando acesso (só vale no Apache).
     */
    'storage'  => __DIR__ . '/../../storage/fotos',
    'log_path' => __DIR__ . '/../../storage/fotos/pagamentos.log',

    // Origens autorizadas (CORS). Mesma origem não precisa estar aqui.
    'allowed_origins' => [
        'https://SEU-DOMINIO.com.br',
        'https://www.SEU-DOMINIO.com.br',
    ],

    // true devolve o erro real da ZuckPay/fal. DESLIGUE em produção.
    'debug' => false,
];
