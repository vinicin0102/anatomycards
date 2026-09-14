# Páginas de vendas com checkout PIX

Duas landing pages de vendas compartilhando o mesmo checkout PIX integrado à
**ZuckPay**:

- `index.html` — Arritmias Cardíacas do Zero ao Diagnóstico
- `enem/index.html` — Mapas ENEM (mini app de mapas mentais, com order bumps)

```
index.html               página de vendas Arritmias + modal de checkout
enem/index.html          página de vendas Mapas ENEM + checkout com order bumps
enem/img/                mockups do app e capas das matérias (SVG)
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

Planos das Arritmias: **Básico R$ 9,99** e **Premium R$ 29,90**. Ambos ficam
em `config.php`, junto com o `product_id` do produto cadastrado no painel da
ZuckPay (atualmente `593187` nos dois — se cada plano tiver produto próprio,
use um id diferente em cada).

## Mapas ENEM (`enem/`)

Página do mini app de mapas mentais. A oferta tem duas portas de entrada e
order bumps no próprio checkout:

| Item | Preço | O que é |
|---|---|---|
| Plano `materia` | R$ 9,90 | mapas de **1 matéria**, escolhida na etapa 1 do checkout |
| Plano `completo` | R$ 19,90 | as 9 matérias + o questionário bônus |
| Order bump de matéria | R$ 9,90 cada | as outras matérias, somadas ao pedido |
| Order bump `redacao900` | R$ 15,99 | 10 Segredos da Redação 900+ (o destaque em dourado) |

O checkout tem uma etapa a mais que o das Arritmias: antes dos dados, o
comprador escolhe a matéria e marca os bumps, com o total atualizando na hora.
Quem já marcou uma matéria extra vê um convite para trocar pelo plano completo.

Preços, matérias e bumps ficam em `config.php` (`planos`, `materias`, `bumps`).
Como no resto do projeto, **o navegador só manda ids** — o valor cobrado é
sempre somado no servidor, então adulterar a requisição não muda o preço nem
libera um bump que não foi pago. Bumps de matéria são ignorados no plano
completo (que já inclui todas) e quando repetem a matéria escolhida.

`api/pix.php` grava a composição do pedido (plano, matéria e bumps) em
`storage/pedido-<hash>.json`, e `api/webhook.php` lê esse arquivo na hora da
entrega — é ele que diz quais materiais liberar para cada comprador.

As imagens do app são SVGs gerados em `enem/img/`: um mockup de celular por
matéria, as capas usadas na grade e nos order bumps, a tela do questionário e a
capa do guia de redação.

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
2. **Imagens** — na página de Arritmias, as capas do hero, as 3 prévias e as 4
   capas de resumos ainda apontam para `medment.site` (arte do e-book de ECG).
   Cada bloco tem um comentário `<!-- TROCAR -->`. A página do ENEM usa os SVGs
   de `enem/img/`; troque-os por prints reais do app quando ele existir.
3. **Depoimentos** — usam avatar com a inicial do nome; trocar por `<img>` se
   tiver as fotos. Os da página do ENEM são de exemplo (marcados com
   `<!-- TROCAR -->`): substitua por comentários reais antes de anunciar.

6. **product_id do ENEM** — os dois planos novos estão com `product_id => 0` no
   `config.example.php`; preencha com o id do produto cadastrado na ZuckPay.
4. **Rate limiting** — não há limite de requisições em `api/pix.php`. Vale pôr
   um limite por IP para evitar geração de cobranças em massa.
5. **Desativar o diagnóstico** — depois de resolver, apague `api/diagnostico.php`
   ou deixe `debug_token` vazio (assim ele responde 404).
