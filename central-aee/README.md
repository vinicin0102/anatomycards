# Central AEE — página de vendas

Página estática (HTML + CSS + JS puro, sem framework) com checkout PIX da
**ZuckPay**, usando os mesmos endpoints de `api/` da página de Arritmias.

```
central-aee/index.html   página + modal de checkout PIX
api/pix.php              cria a cobrança (plano + extras, preço calculado no servidor)
api/status.php           consulta o pagamento
api/webhook.php          confirma o pagamento e registra plano + extras comprados
```

## Antes de publicar

1. **`api/config.php`** — o plano `central-aee` já está no
   `config.example.php`. Copie o bloco para o seu `config.php` real e troque o
   `product_id` pelo id do produto Central AEE no painel da ZuckPay.
2. **Preços** — quem cobra é o servidor (`config.php`). O `index.html` só
   exibe: se mudar um preço, mude nos dois lugares (`PRECO` e `EXTRAS` no fim
   do HTML, `valor` no `config.php`). O PIX mostra sempre o valor do servidor.
3. **FAQ** — preencha `entrega`, `formatos`, `acesso` e `programas` no bloco
   `FAQ` do HTML. Vazio = texto genérico, que não promete nada específico.
4. **Entrega** — o `TODO` em `api/webhook.php` é onde entra o envio do
   material. O log de pagamentos já grava `plano` e `extras` de cada venda
   (ex.: `"extras":["tea","pasta"]`), para saber o que entregar.

## Como os extras funcionam

O comprador marca os extras na página; o total aparece na caixa de oferta,
na barra fixa do celular e no modal. Ao gerar o PIX, o navegador envia só
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

A página não tem depoimentos, número de compradores, avaliações nem contagem
regressiva, de propósito: nada disso deve ser adicionado sem dados reais.
