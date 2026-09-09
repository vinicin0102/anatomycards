# Arritmias Cardíacas do Zero ao Diagnóstico — landing page

Landing page de vendas do material de **Arritmias Cardíacas**.

Arquivo único: **`index.html`**.

## Origem

A estrutura vem da página `medment.site/ecgdozeroaodiagnostico/` (ECG do Zero
ao Diagnóstico), extraída do widget HTML do Elementor como documento autônomo.
Layout, seções, animações, cronômetro, cupom, sliders e toast de compras foram
mantidos como no original — o que mudou foi a **matéria**, que passou de ECG
para Arritmias Cardíacas.

## Estrutura das seções

1. Faixa de urgência com cronômetro de 15 min
2. Hero — cupom 50% OFF (R$ 9,99), capas do e-book, CTA, headline
3. Prova social rápida (faixa azul)
4. "O que tem dentro do E-book?" — 6 cards
5. 3 Bônus gratuitos
6. Biblioteca de Resumos Clínicos (exclusivo do Premium)
7. Planos
8. Feedback de Alunos — 7 depoimentos
9. Rodapé + toast de compras

## Planos

| Plano | Preço |
|---|---|
| Pacote Básico | R$ 9,99 |
| Pacote Premium | R$ 29,90 |

> A página de referência usava R$ 9,99 e R$ 19,90. Aqui o Premium está em
> R$ 29,90, seguindo a definição de preço do projeto.

## Conteúdo adaptado para Arritmias

Os 6 cards de conteúdo cobrem: mecanismos da arritmia (automatismo, reentrada,
atividade deflagrada), passo a passo do ritmo, taquiarritmias (FA, flutter, TSV,
TV), ritmos de parada (FV, TV sem pulso, assistolia, AESP), bradiarritmias e
bloqueios AV, e material de bolso. Os bônus e a biblioteca de resumos clínicos
seguem a mesma composição da página de referência.

## Pendências antes de publicar

1. **Links de checkout** — os dois botões de plano estão com `href="#"` e um
   comentário `<!-- TROCAR pelo link de checkout ... -->`. Os links da página
   de referência apontam para o produto de ECG (`medmedic.mycartpanda.com`) e
   **não** foram reaproveitados, para não vender o produto errado.
2. **Imagens** — as capas do hero, as 3 prévias e as 4 capas de resumos ainda
   apontam para `medment.site` (arte do e-book de ECG). Cada bloco tem um
   comentário `<!-- TROCAR ... -->`. Substituir pela arte de Arritmias.
3. **Depoimentos** — usam avatar com a inicial do nome. Para as fotos reais,
   trocar a `div` do avatar por `<img>`.
4. **Pixels** — Meta (`352488377782448`), OpenAI e UTMify são os do
   AnatomyCards, herdados da página anterior. O `ViewContent` do Meta foi
   atualizado para `content_name: 'Arritmias Cardíacas do Zero ao Diagnóstico'`
   e `content_category: 'Material de Cardiologia'`.

## Diferenças em relação à página de referência

- Cor `medical.50` foi adicionada ao `tailwind.config`. A referência usa
  `bg-medical-50` na seção de bônus, mas não definia esse tom — a classe não
  gerava nada e a seção ficava sem fundo.
- Seção "Feedback de Alunos" com os 7 depoimentos (não existe na referência).
- Pixels do AnatomyCards no lugar do pixel da UTMify do medment.site.

## Rodar localmente

```bash
python3 -m http.server 8000
# http://localhost:8000
```

Depende de CDN (Tailwind, Lucide Icons, Google Fonts), então precisa de internet.
