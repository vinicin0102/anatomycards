# AnatomyCards — Resumos e Laboratórios

Landing page de vendas do material digital **Resumos e Laboratórios**, com os planos
**R$ 4,99** (Resumos) e **R$ 14,99** (Completo) e a seção de prova social
*Feedback de Alunos* (+5 mil alunos).

## Estrutura

```
index.html              página completa
assets/css/styles.css   estilos (preto + laranja da marca)
assets/js/main.js       menu mobile, CTA fixo, acordeão do FAQ
```

## Rodar localmente

Abra o `index.html` no navegador, ou:

```bash
python3 -m http.server 8000
# http://localhost:8000
```

## O que precisa ser preenchido antes de publicar

1. **Links de checkout da Hotmart** — em `index.html`, na seção `#planos`, os dois
   botões estão com `href="#"` e um comentário `<!-- TROQUE pelo link de checkout ... -->`.
   Também vale conferir os CTAs do hero, do rodapé e o CTA fixo, que hoje apontam
   para a âncora `#planos`.
2. **Imagens da prévia** — em `#previa`, trocar cada `div.preview__ph` por
   `<img src="assets/img/previa-01.jpg" alt="...">`.
3. **Fotos dos alunos** — os depoimentos usam avatares com a inicial do nome
   (`.review__avatar[data-initial]`). Para usar as fotos reais, substituir por
   `<img class="review__avatar" src="assets/img/alunos/lali.jpg" alt="Lali">`.
4. **Pixel / analytics** — não há nenhum script de rastreamento na página.

## Depoimentos

Os 7 depoimentos da seção `#feedback` estão transcritos exatamente como aparecem
na arte original, com as 5 estrelas e o @ do perfil.
