# +100 Receitas para um Natal Fora do Óbvio

Página de vendas estática (HTML + CSS + JS, sem dependências). Abra
`natal/index.html` direto no navegador ou publique a pasta `natal/` em
qualquer hospedagem.

## Antes de publicar

### 1. Link do checkout

No fim do `index.html`:

```js
const CHECKOUT_URL = '';   // ex.: 'https://pay.kiwify.com.br/xxxx'
```

Vazio, todos os botões levam até a seção de oferta. Preenchido, todos
apontam para o checkout e repassam `utm_*`, `src`, `sck`, `fbclid`, `gclid`
e `ttclid` da URL da página.

> O checkout PIX da raiz do repositório (`api/`) está amarrado aos planos do
> produto de Arritmias. Para usá-lo aqui seria preciso cadastrar um plano
> `natal` de R$ 9,90 no `config.php` e trazer o modal — não foi feito.

### 2. Fotos

Enquanto uma foto não existir, a página mostra um fundo ilustrado com emoji
no lugar (não aparece imagem quebrada). Coloque os arquivos com estes nomes:

| Arquivo | Onde aparece | Formato sugerido |
|---|---|---|
| `img/hero-mesa.jpg` | fundo do topo e mockup do topo/oferta | 1600×1600, mesa de Natal farta |
| `img/capa.jpg` | foto da capa do livro | 800×700, prato natalino em destaque |
| `img/mesa-bonita.jpg` | apresentação, benefícios, CTA final | 1600×1400, mesa decorada |
| `img/familia-ceia.jpg` | bloco "Onde você aprendeu essa receita?" | 1920×1200, família à mesa |
| `img/receitas/<nome>.jpg` | cards de receitas e páginas de amostra | 800×1000 (vertical 4:5) |

Nomes dos cards (`img/receitas/`):

```
pave-de-natal               rabanada-recheada        cheesecake-frutas-vermelhas
lombo-especial              frango-assado-natalino   arroz-a-grega-especial
farofa-crocante             salpicao-cremoso         torta-de-chocolate
mousse-especial             sobremesa-de-morango     pudim-especial
pernil-assado               batatas-gratinadas       torta-salgada
brownie-natalino
```

Para trocar nomes ou a lista de receitas, edite a constante `RECEITAS`.
Use fotos próprias, do ebook ou de banco de imagens com licença comercial.

### 3. Páginas de amostra ("folhear o livro")

As 6 páginas são uma reprodução do formato (foto, ingredientes, preparo,
tempo, rendimento e dica), montadas pela constante `PAGINAS`. Ajuste para
as receitas que estão de fato no ebook — ou troque cada página por um print
real. Os números de página (`pág. 12`, `27`…) também são ilustrativos.

### 4. Avaliações

Os 6 depoimentos são **exemplos de layout** e aparecem com o selo
"Exemplo" e um aviso na seção. Quando tiver avaliações reais (com
autorização), edite `AVALIACOES` e mude `AVALIACOES_SAO_EXEMPLO` para
`false` — o aviso e os selos somem. Para usar foto, adicione
`foto: 'img/avaliacoes/nome.jpg'` ao item.

### 5. Pixels

A página não traz pixel de Meta/TikTok/UTMify. Cole os scripts no `<head>`
se for rodar tráfego.

## Decisões

- **Contagem regressiva honesta**: conta até a noite de 24/12 (a ceia), não
  até um prazo de oferta inventado. Depois do Natal ela vira uma mensagem de
  boas festas.
- **Barra fixa no celular**: aparece depois do topo e some na seção de oferta.
- **Animações** (luzes, neve, brilho do botão, revelar ao rolar) são
  desligadas para quem usa "reduzir movimento" no sistema.
