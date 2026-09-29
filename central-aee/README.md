# Central AEE — página de vendas

Página estática (HTML + CSS + JS puro, sem framework). Basta publicar
`index.html` em qualquer hospedagem.

## Antes de publicar

Tudo que precisa de ajuste fica no bloco `CONFIG` no fim do `index.html`:

| Campo | O que colocar |
|---|---|
| `checkoutUrl` | Link do checkout do produto. Vazio = o botão avisa que não está configurado. |
| `preco` | Preço em centavos (2790 = R$ 27,90). O texto do preço na caixa de oferta também está no HTML. |
| `paramExtras` | Nome do parâmetro com os extras marcados, enviado na URL do checkout. |
| `entrega`, `formatos`, `acesso`, `programas` | Respostas reais do FAQ. Vazio = texto genérico, que não promete nada específico. |

Os extras (order bumps) estão no array `EXTRAS`, logo abaixo.

**Extras e checkout:** a página soma os extras marcados, mostra o total e
envia os ids escolhidos ao checkout (`?extras=relatorios,tea`). Só a
plataforma de pagamento cobra de fato. Se ela não ler esse parâmetro,
cadastre os mesmos extras como order bump na própria plataforma. As UTMs da
URL da página também são repassadas ao checkout.

## Pendências de conteúdo (marcadas com `TROCAR` no HTML)

1. **Prévias da seção "Veja o que você recebe"**: são desenhos em CSS da
   estrutura genérica dos modelos. Troque pelas capturas reais das páginas
   entregues e remova a legenda "Prévias ilustrativas".
2. **Quantidade de materiais**: a página diz "Dezenas de materiais". Só troque
   por um número (ex.: "+100") quando o conteúdo final tiver essa quantidade.
3. **Links do rodapé**: Termos de Uso, Política de Privacidade e Contato
   apontam para `#`.
4. **Pixel/UTM**: há um comentário no `<head>` para os scripts de rastreamento
   deste produto.

A página não tem depoimentos, número de compradores, avaliações nem contagem
regressiva, de propósito: nada disso deve ser adicionado sem dados reais.
