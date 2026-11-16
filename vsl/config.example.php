<?php
/*
 * Copie para vsl/config.php (não versionado) e cole o hash da senha.
 *
 * Gere o hash no servidor:
 *   php -r 'echo password_hash("SUA_SENHA_AQUI", PASSWORD_DEFAULT), PHP_EOL;'
 *
 * Alternativa: variável de ambiente VSL_ADMIN_PASSWORD_HASH (tem prioridade).
 */
return [
    'admin_password_hash' => '',
];
