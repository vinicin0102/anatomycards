# Foto com Quem Você Ama: montagens de foto com IA

Página de vendas com gerador de prévia por IA e checkout PIX (mesma conta ZuckPay
da página de arritmias).

```
fotos/index.html              página de vendas + criador + checkout
fotos/api/gerar.php           recebe a foto, gera a montagem, devolve só a prévia com marca d'água
fotos/api/pix.php             cria a cobrança PIX do pedido
fotos/api/status.php          consulta o pagamento; quando pago, devolve o link da foto
fotos/api/foto.php            entrega a foto final (só pedido pago)
fotos/api/webhook.php         notificação da ZuckPay → marca o pedido como pago
fotos/api/_fotos.php          catálogo, prompts, imagens, fal.ai, pedidos
fotos/api/config.example.php  modelo de configuração
```

Ver sem servidor nem custo: abra `fotos/index.html?demo=1`. A prévia e o PIX são simulados.

## Fluxo

1. A cliente escolhe o acompanhante, a pose e o cenário, e envia a foto. O navegador
   reduz a foto para 1600 px antes de enviar, o que também acerta a rotação de fotos de celular.
2. `gerar.php` chama o modelo no fal.ai e guarda o resultado **no servidor**.
   O navegador recebe só uma prévia de 640 px com "PRÉVIA" estampado.
3. A prévia aparece por 5 s (`previa_segundos`) e depois fica borrada com cadeado e a oferta.
4. Com o PIX pago, `status.php` libera o link de `foto.php`, que serve a foto em alta.
5. O pedido fica salvo no `localStorage`: se a pessoa sair para o app do banco e a
   página recarregar, ela volta direto para o PIX ou para a foto liberada.

## Configuração

```bash
cp fotos/api/config.example.php fotos/api/config.php
```

- `fal_key`: crie em https://fal.ai/dashboard/keys (prefira a variável de ambiente `FAL_KEY`).
- `fal_modelo`: id do modelo de edição. Confira o id e o preço atuais na página do modelo.
- `produto.valor`: preço cobrado. Mude também `PRECO_POR` no `index.html` (é só texto).
- `webhook_url`: `https://SEU-DOMINIO/fotos/api/webhook.php`.
- `storage`: de preferência **fora** da pasta pública. Dentro dela, o código cria um
  `.htaccess` que bloqueia acesso, mas isso só funciona no Apache. No Nginx, mova a pasta.
- No `index.html`: `WHATSAPP` (seu número) e, se quiser, `VSL_VIDEO` (um .mp4 real).

## Custos e limites

Cada prévia custa uma geração no fal.ai, **mesmo que a pessoa não compre**.
Por isso existem `limite_por_ip_dia` (padrão 4) e `limite_global_dia` (padrão 300).
Falhas do nosso lado (chave, rede, API fora) não contam como tentativa.
Faça a conta: com conversão de 10 % e geração a ~US$ 0,08, cada venda carrega ~10 gerações,
cerca de R$ 4 a 5 de custo de IA.

## Pendências

1. **Exemplos reais**: o antes/depois do topo usa ilustração. Troque por montagens reais
   (marcado com `<!-- TROCAR -->`). Isso faz mais diferença na conversão do que qualquer texto.
2. **Depoimentos**: só reais, com autorização. Há um comentário marcando onde entram.
3. **E-mail pós-venda**: `marcarPago()` tem um `TODO` para enviar a foto por e-mail ou
   avisar você. Hoje a entrega é na tela (download + compartilhar).
4. **Limpeza**: as fotos ficam em `storage/fotos/pedidos/`. Agende uma remoção dos
   pedidos com mais de 30 dias. A FAQ promete que a foto é usada só para a montagem.
5. **Pixel/UTMify**: cole os scripts no `<head>`. O front já dispara `InitiateCheckout`
   e `Purchase` se o `fbq` existir.
