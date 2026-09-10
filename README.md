# Arritmias Cardíacas do Zero ao Diagnóstico

Landing page de vendas com checkout PIX integrado à **ZuckPay**.

```
index.html               página de vendas + modal de checkout
api/pix.php              cria a cobrança PIX
api/status.php           consulta o status do pagamento
api/webhook.php          recebe a notificação da ZuckPay
api/_bootstrap.php       validação, CORS e chamada autenticada à API
api/config.example.php   modelo de configuração
storage/                 log de pagamentos (não versionado)
```

## Configuração

```bash
cp api/config.example.php api/config.php
```

Preencha `client_id`, `client_secret`, `webhook_url` e `allowed_origins`.
`api/config.php` está no `.gitignore` — **nunca** versione esse arquivo.
Em produção prefira variáveis de ambiente (`ZUCKPAY_CLIENT_ID` /
`ZUCKPAY_CLIENT_SECRET`), que o `config.example.php` já lê.

Requisitos: PHP 8+ com a extensão cURL. O front chama `/api`; se a pasta não
ficar na raiz do site, ajuste a constante `API` no script de checkout do
`index.html`.

## Como funciona

1. O visitante clica em um dos planos e preenche nome, CPF, e-mail e telefone.
2. `api/pix.php` valida os dados e chama `POST /conta/v3/pix/qrcode`.
3. A página mostra o QR Code e o copia-e-cola, e consulta `api/status.php`
   a cada 4s até o pagamento ser confirmado.
4. A ZuckPay chama `api/webhook.php`, que confirma o pagamento e registra a venda.

Planos: **Básico R$ 9,99** e **Premium R$ 29,90**.

## Decisões de segurança

Estas escolhas são deliberadas — mudá-las abre brecha real:

- **O `client_secret` nunca vai para o navegador.** A documentação da ZuckPay
  mostra um exemplo em JavaScript com `btoa(clientId + ':' + clientSecret)`
  rodando no front. Seguir aquele exemplo publica a credencial no código-fonte
  da página: qualquer visitante poderia criar cobranças, listar transações e
  consultar o saldo da conta. Por isso a chamada é feita em PHP, no servidor.
- **O preço é definido no servidor.** `api/pix.php` recebe só o *id* do plano
  (`basico` / `premium`) e busca o valor na constante `PLANOS`. Um `valor`
  enviado pelo navegador é ignorado — sem isso, bastaria editar a requisição
  para comprar o Premium por R$ 0,01.
- **As respostas são filtradas.** A API devolve `amount_liquid`, e-mail do
  comprador e outros campos internos; os endpoints repassam apenas o necessário.
- **O webhook não confia no próprio payload.** A ZuckPay não assina a
  requisição, então `webhook.php` extrai só o `transactionId` e reconsulta o
  status na API. Um POST forjado dizendo `"status":"PAID"` não libera nada.
- **Entradas são validadas**: CPF com dígito verificador, e-mail, telefone e
  limite de tamanho. Parâmetros de atribuição passam por whitelist.

## Pendências

1. **Entrega do produto** — `api/webhook.php` tem um `TODO` no ponto onde entra
   o envio do e-mail com os PDFs ou a liberação da área de membros. A ZuckPay
   pode reenviar a mesma notificação, então grave o `transactionId` e só
   entregue uma vez.
2. **Imagens** — capas do hero, as 3 prévias e as 4 capas de resumos ainda
   apontam para `medment.site` (arte do e-book de ECG). Cada bloco tem um
   comentário `<!-- TROCAR -->`.
3. **Depoimentos** — usam avatar com a inicial do nome; trocar por `<img>` se
   tiver as fotos.
4. **Rate limiting** — não há limite de requisições em `api/pix.php`. Vale pôr
   um limite por IP para evitar geração de cobranças em massa.
