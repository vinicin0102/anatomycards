# Atlas das Escrituras

Landing page de vendas do **Atlas das Escrituras** (150 páginas de mapas, linhas do
tempo, personagens, resumos e contextos bíblicos), posicionada para professores de
EBD, líderes de célula e quem prepara estudos bíblicos. Checkout PIX integrado à
**ZuckPay**.

A página é um único `index.html` sem framework: CSS próprio, ícones e páginas
ilustrativas em SVG inline, sem Tailwind nem bibliotecas de ícones. Inclui CTA fixo
no celular, galeria com lightbox, popup de saída (desktop: mouse saindo pelo topo;
celular: ao voltar para a aba) e popup discreto de recuperação após ~50s — no máximo
um popup por sessão, e nunca para quem já clicou em comprar.

```
index.html               página de vendas + modal de checkout
api/pix.php              cria a cobrança PIX
api/status.php           consulta o status do pagamento
api/webhook.php          recebe a notificação da ZuckPay
api/diagnostico.php      checagem da integração (protegido por token)
tools/testar-webhook.php testa a validação de assinatura do webhook (CLI)
api/_bootstrap.php       validação, CORS e chamada autenticada à API
api/config.example.php   modelo de configuração
storage/                 log de pagamentos (não versionado)
```

## Configuração

```bash
cp api/config.example.php api/config.php
```

Preencha `client_id`, `client_secret`, `webhook_url`, `webhook_secret` e
`allowed_origins`. O **Webhook Secret** é gerado no painel em
*Integrações > Webhook Secret* e é diferente do Client Secret; sem ele os
postbacks chegam sem assinatura e só resta a verificação por reconsulta.

O `api_base` precisa usar **exatamente o host da sua tela de Credenciais API**
(com ou sem `www`). Com o host errado a ZuckPay responde um redirecionamento,
e um POST autenticado não é reenviado no redirect — a cobrança nunca chega.
Este é o motivo mais comum de "o PIX não gera".
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

Planos: **`basico` — Atlas, R$ 3,90** e **`premium` — Pacote Completo (Atlas + 3
extras), R$ 9,90**. O preço cobrado é o do `config.php` no servidor, **não** o do
HTML: ao publicar esta página, atualize `valor` e `nome` dos dois planos no seu
`config.php` real (que não é versionado) e preencha o `product_id` do produto do
Atlas no painel da ZuckPay (`0` = não envia).

## Conferir o webhook

Depois de configurar o `webhook_secret`, rode **no servidor**:

```bash
php tools/testar-webhook.php
```

Ele monta POSTs assinados como a ZuckPay faz e confere que o endpoint aceita
o legítimo e recusa assinatura falsa, replay e requisição sem header:

```
[ok]   assinatura válida        (esperado: 200) -> HTTP 200
[ok]   assinatura falsa         (esperado: 401) -> HTTP 401
[ok]   replay de 10 minutos     (esperado: 401) -> HTTP 401
[ok]   sem header de assinatura (esperado: 401) -> HTTP 401
```

O segredo é lido do `config.php`; nunca passe por argumento, porque a linha de
comando fica visível para outros processos e no histórico do shell.

## Se o PIX não gerar

1. Defina um `debug_token` no `config.php` e abra:
   `https://seu-dominio.com.br/api/diagnostico.php?token=SEU_TOKEN`

   Ele confere PHP, cURL, credenciais (mascaradas), planos e faz uma cobrança
   de teste de R$ 1,00, mostrando a resposta real da ZuckPay. Os diagnósticos
   possíveis:

   | Resultado | Causa provável |
   |---|---|
   | `REDIRECIONAMENTO` | `api_base` com o host errado (`www` sobrando ou faltando). O diagnóstico mostra o endereço certo em `va_para` e testa a variante em `alternativa`. |
   | `IP BLOQUEADO` | Credenciais válidas, mas o IP do servidor não está na IP Whitelist da ZuckPay. O diagnóstico mostra o IP a liberar. |
   | `RATE LIMIT` | 5 tentativas por 30 minutos. Aguarde. |
   | `FALHA DE CONEXAO` | A hospedagem bloqueia conexões de saída, ou DNS. |
   | `NAO AUTORIZADO` | `client_id`/`client_secret` errados, revogados ou sem permissão para PIX. |
   | `ENDPOINT NAO ENCONTRADO` | `api_base` incorreto. |
   | `OK` | A integração funciona — o problema está no front ou no caminho `/api`. |

2. Se der `OK` no diagnóstico mas o botão da página continuar falhando, o
   problema é o caminho: abra o console do navegador (F12) e veja se o
   `POST /api/pix.php` retorna 404. Nesse caso a pasta `api/` não está onde o
   front espera — ajuste a constante `API` no script de checkout do `index.html`.

3. Ligue `'debug' => true` no `config.php` para que a página mostre o motivo
   real da falha em vez da mensagem genérica. **Desligue depois**, junto com o
   `debug_token`.

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
- **O webhook é verificado em duas camadas.** Primeiro a assinatura HMAC do
  header `X-ZuckPay-Signature` (`HMAC-SHA256("<timestamp>.<corpo_raw>",
  webhook_secret)`), com janela anti-replay de 5 minutos — prova que o POST
  veio da ZuckPay. Depois o `transactionId` é reconsultado na API — prova que
  o pagamento está pago agora. O corpo do POST nunca é a fonte da verdade, então
  um `"status":"PAID"` forjado não libera nada.
- **Entradas são validadas**: CPF com dígito verificador, e-mail, telefone e
  limite de tamanho. Parâmetros de atribuição passam por whitelist.
- **A entrega roda uma vez só.** A ZuckPay reenvia notificações. Antes de
  entregar, `webhook.php` cria um arquivo-marcador com `fopen(..., 'x')`, que
  falha se já existir — duas notificações simultâneas não passam as duas.
- **Cobranças não duplicam.** Cada abertura do checkout gera um `pedido`, usado
  como `external_id_client`. Clicar duas vezes devolve a mesma cobrança em vez
  de criar outra.

## Rate limit

A ZuckPay responde **429 após 5 tentativas em 30 minutos**. Por isso:

- `status.php` guarda a última consulta por 8 segundos, então várias abas ou
  recarregamentos não geram chamadas repetidas.
- Quando a API devolve 429, a resposta pede à página para esperar 30s em vez
  dos 5s normais; o front respeita esse intervalo.
- `webhook.php` responde na hora a `payment_refused`, `payment_pending` e
  `checkout_abandoned`, sem gastar uma chamada de verificação.

## Pendências

1. **Entrega do produto** — `api/webhook.php` tem um `TODO` no ponto onde entra
   o envio do e-mail com os PDFs ou a liberação da área de membros. A ZuckPay
   pode reenviar a mesma notificação, então grave o `transactionId` e só
   entregue uma vez.
2. **Mockups** — os dispositivos, a galeria e as capas dos bônus usam páginas
   *ilustrativas* desenhadas em SVG (sprite no topo do `index.html`), não
   capturas do PDF real. Troque por imagens reais das páginas assim que possível
   para que o visitante veja exatamente o que vai receber. Os pontos estão
   marcados com `<!-- TROCAR -->`.
3. **Depoimentos** — a seção existe, mas está com o atributo `hidden` porque
   ainda não há depoimentos reais. Preencha com textos verdadeiros (exatos, com
   autorização de quem escreveu) e remova o `hidden`. Não use textos de exemplo
   como se fossem avaliações.
4. **Rate limiting** — não há limite de requisições em `api/pix.php`. Vale pôr
   um limite por IP para evitar geração de cobranças em massa.
5. **Desativar o diagnóstico** — depois de resolver, apague `api/diagnostico.php`
   ou deixe `debug_token` vazio (assim ele responde 404).
