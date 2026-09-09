# Terminologia Médica Descomplicada — landing page

Clone da página `anatomycards.com.br/ebook-de-terminologia-medica-hotmart/`,
com os preços ajustados e uma seção de depoimentos adicionada.

Arquivo único: **`index.html`**.

## O que mudou em relação ao original

| Item | Original | Agora |
|---|---|---|
| Preço no bloco promocional (hero) | R$ 9,99 | R$ 9,99 (mantido) |
| Combo Essencial (front) | R$ 9,99 | R$ 9,99 (mantido) |
| Combo Completo | R$ 29,99 | **R$ 29,90** |
| Seção "Feedback de Alunos" | não existia | **7 depoimentos + selo "+5 mil alunos"**, entre os Planos e o FAQ |

Todo o resto foi mantido: textos, imagens, ordem das seções, cronômetro de
urgência, sliders, FAQ, toast de compras, pixels (Meta/OpenAI/UTMify) e os
links de checkout da Cartpanda.

## Diferenças estruturais em relação ao site atual

No WordPress a página vive dentro de um widget HTML do Elementor. Aqui ela é um
documento HTML autônomo: o wrapper do Elementor/WordPress foi removido e os dois
widgets (a landing e o toast de compras) foram unificados num arquivo só. Os
pixels, que no site ficam no `<head>` do WordPress, foram trazidos para o `<head>`
deste arquivo.

## Dependências externas

Carregadas por CDN, como no original — a página precisa de internet para
renderizar corretamente:

- Tailwind CSS (`cdn.tailwindcss.com`) com a config de cores customizada
- Lucide Icons (`unpkg.com/lucide@latest`)
- Google Fonts (Inter)
- Imagens hospedadas em `anatomycards.com.br/wp-content/uploads/`

## Rodar localmente

```bash
python3 -m http.server 8000
# http://localhost:8000
```

## Pontos de atenção herdados do original

Foram mantidos como estão, mas vale revisar antes de publicar:

1. **Meta Pixel** dispara `ViewContent` com `content_name: 'Farmacologia Fácil'`
   e `content_category: 'Material de Farmacologia'` — não bate com este produto.
2. **Marca d'água `vetfacil.com`** sobre o mockup da coluna direita do hero.
3. **Selo "50% OFF"** no bloco promocional, sem nenhum preço "de" exibido ao lado.
4. **Depoimentos** usam avatar com a inicial do nome. Para as fotos reais,
   trocar a `div` do avatar por `<img>` na seção "FEEDBACK DE ALUNOS".
