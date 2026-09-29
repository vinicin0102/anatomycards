# Central AEE — página de vendas

Página estática (HTML + CSS + JS puro, sem framework) com checkout PIX da
**ZuckPay**, usando os mesmos endpoints de `api/` da página de Arritmias.

```
central-aee/index.html   página + modal de checkout PIX
api/pix.php              cria a cobrança (plano + extras, preço calculado no servidor)
api/status.php           consulta o pagamento
api/webhook.php          confirma o pagamento e registra plano + extras comprados
api/vendas-recentes.php  compras reais recentes, para os pop-ups da página
central-aee/img/         depoimentos (recortes da imagem enviada pelo cliente)
```

Planos: **Básico R$ 12,90** (Documentação, Avaliações, Planejamento,
Registros, Fichas de acompanhamento) e **Completo R$ 27,90** (tudo + Família e
escola + Recursos pedagógicos). Os extras aparecem só dentro do checkout e
valem para os dois planos.

## Antes de publicar

1. **`api/config.php`** — copie do `config.example.php` para o seu
   `config.php` real a variável `$extrasAee` (antes do `return`), os planos
   `aee-basico` e `aee-completo` e a chave `vendas_recentes`. Troque os
   `product_id` pelos ids dos produtos no painel da ZuckPay.
2. **Preços** — quem cobra é o servidor (`config.php`). O `index.html` só
   exibe: se mudar um preço, mude no `config.php`, em `PLANOS` / `EXTRAS` no
   fim do HTML e no texto dos cards de plano. O PIX mostra sempre o valor do
   servidor.
3. **FAQ** — preencha `entrega`, `formatos`, `acesso` e `programas` no bloco
   `FAQ` do HTML. Vazio = texto genérico, que não promete nada específico.
4. **Entrega** — o `TODO` em `api/webhook.php` é onde entra o envio do
   material. O log de pagamentos já grava `plano` e `extras` de cada venda
   (ex.: `"extras":["tea","pasta"]`), para saber o que entregar.

## Pop-ups de compras

Mostram **só vendas reais**: `api/vendas-recentes.php` lê o log de pagamentos
confirmados pelo webhook e devolve primeiro nome, plano e há quanto tempo
(últimos 7 dias, até 10). Cada venda aparece uma vez por visita; sem vendas,
não aparece pop-up nenhum. E-mail, CPF, telefone e valor nunca saem do
servidor. Para desligar: `'vendas_recentes' => false` no `config.php`.

Não use nomes ou horários inventados: pop-up de compra falsa é propaganda
enganosa (CDC, art. 37).

## Como os extras funcionam

O comprador escolhe o plano e marca os extras dentro do checkout; o total é
atualizado ali. Ao gerar o PIX, o navegador envia só
os ids (`["tea","pasta"]`). O `pix.php` recusa id desconhecido, soma os
valores do `config.php` e gera **um único PIX** com tudo. A descrição da
cobrança fica, por exemplo, "Central AEE + Kit Professor TEA + Pasta do Aluno".

## Pendências de conteúdo (marcadas com `TROCAR` no HTML)

1. **Prévias da seção "Veja o que você recebe"**: são desenhos em CSS da
   estrutura genérica dos modelos. Troque pelas capturas reais das páginas
   entregues e remova a legenda "Prévias ilustrativas".
2. **Quantidade de materiais**: a página diz "Dezenas de materiais". Só troque
   por um número (ex.: "+100") quando o conteúdo final tiver essa quantidade.
3. **Links do rodapé**: Termos de Uso, Política de Privacidade e Contato
   apontam para `#`.
4. **Pixel/UTM**: há um comentário no `<head>` para os scripts deste
   produto. O checkout já dispara `InitiateCheckout` e `Purchase` se o pixel
   (`fbq`) estiver carregado, e repassa UTMs e cookies `_fbc`/`_fbp` à ZuckPay.

Os depoimentos publicados são de clientes reais, com autorização. Não há
número de compradores, avaliações agregadas nem contagem regressiva: nada
disso deve ser adicionado sem dados reais.
