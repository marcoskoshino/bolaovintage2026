=== Bolão Copa 2026 Elifoot ===
Plugin de bolão para WordPress com visual inspirado no Elifoot 98.

Shortcodes:
[bolao_copa_2026] - palpites + ranking
[bolao_copa_2026_palpites] - somente formulário de palpites
[bolao_copa_2026_ranking] - somente ranking

Admin:
Bolão Copa 2026 > Jogos e Resultados
Bolão Copa 2026 > Participantes
Bolão Copa 2026 > Pontuação


== Changelog ==

= 1.0.1 =
* Ajuste de alinhamento dos palpites.
* Bandeiras por imagem via código de país.
* Cabeçalho estilo Elifoot 98 com regras dinâmicas.
* CSS refinado para responsividade.

= 1.0.0 =
* Primeira versão pública do plugin.

= 1.1 =
* Local dividido em cidade e país.
* Palpite ajustado para caber dentro do layout e ficar responsivo.
* Bandeira do time da esquerda posicionada ao lado do X.
* Textos e títulos do cabeçalho editáveis pelo admin.
* Versionamento visual e interno atualizado para 1.1.

= 1.2 =
* Oculta a coluna de número do jogo na tabela de palpites.
* Estreita a coluna Grupo/Fase.
* Corrige a coluna Palpite para exibir sempre as duas caixas lado a lado.
* Ajustes de largura e responsividade da tabela.

= 1.3 =
* Modo produto arcade/16-bit.
* Scanlines estilo CRT.
* HUD do jogador.
* Animação no X e no cursor da tabela.
* Glow nos números do placar e pontuação.
* Flash de sucesso/erro.
* Loading retrô ao salvar.
* Mini beeps via Web Audio API.
* Destaque visual no ranking.

= 1.3.1 =
* Corrige quebra dos títulos das colunas.
* Padroniza fonte e tamanho dos cabeçalhos da tabela.
* Ajusta larguras finais de Grupo/Fase, Data, Local e Palpite.
* Mantém as duas caixas do palpite sempre visíveis.
* Corrige indicador de hover para não alterar a largura da linha.
* Reduz excesso de beeps em hover.

= 1.4 =
* Corrige cabeçalhos das colunas com largura e fonte padronizadas.
* Reduz rótulo Grupo/Fase para Fase para evitar quebra.
* Padroniza visual dos campos de nome e e-mail.
* Melhora UX do cadastro do participante com instrução editável no admin.
* Adiciona novos textos editáveis no painel Textos e Títulos.

= 1.4.1 =
* Corrige definitivamente a quebra da linha de títulos.
* Usa cabeçalhos compactos: GR, JOGO, DIA, LOC e PALP.
* Força cabeçalho em uma única linha.
* Adiciona rolagem horizontal no container quando necessário.

= 1.5 =
* Corrige salvamento dos textos e títulos no admin.
* Recarrega os textos após salvar.
* Adiciona botão para restaurar textos padrão.
* Adiciona preview rápido no painel de textos.
* Fortalece fallback dos textos salvos.

= 1.5.1 =
* Atualiza cabeçalhos da tabela para GRUPO, JOGO, DIA, LOCAL e PALPITE.
* Ajusta fonte e largura para evitar quebra dos títulos.

= 1.5.2 =
* Ajusta alinhamento do primeiro time à direita.
* Mantém a bandeira do primeiro time próxima ao X.
* Mantém o segundo time no alinhamento original.
* Refina espaçamento central do confronto.

= 1.6 =
* Loading de salvamento aumentado para 4 segundos.
* Texto alterado para "NÃO DESLIGUE SEU DEVICE".
* Adiciona barra de progresso fake estilo SNES.
* Corrige envio para evitar loop de submit.

= 2.0 =
* Experiência mobile-first.
* Tabela vira cards no celular sem duplicar campos.
* Inputs maiores para toque.
* Botão de salvar fixo no fundo.
* Filtro mobile: todos ou apenas jogos abertos.
* Autoavanço entre campos de placar.
* Vibração ao salvar em dispositivos compatíveis.
* Scanlines desativadas no mobile para melhor leitura/performance.
* Status de jogo aberto/fechado no card.

= 2.1 =
* Mobile: sistema de pontuação em 3 cards por linha.
* Mobile: card da final em destaque com taça 8-bit.
* Mobile: regras em 2 cards por linha.
* Mobile: melhora de fontes e espaçamentos dos cards.
* Mobile: grupo, dia, local e palpite centralizados.
* Mobile: botão flutuante para ir direto ao botão de salvar.

= 2.1.1 =
* Corrige centralização efetiva de grupo, dia, local e palpite nos cards mobile.
* Move botão flutuante para fora do formulário e força exibição no mobile.
* Adiciona scroll suave até a âncora de salvar.

= 2.2 =
* Desktop: aumenta largura da coluna LOCAL para equalizar altura das linhas.
* Normaliza nomes de cidades: "Nova York/Nova Jersey" → "New York"; "Cidade do México" → "CDMX".

= 2.2.1 =
* Corrige centralização mobile de Grupo, Dia, Local e Palpite.
* Adiciona classes específicas para células mobile.
* Fortalece seletores CSS para vencer conflitos anteriores.

= 2.4 =
* Adiciona Top 3 com premiação esperada no topo do bolão.
* Adiciona painel Produto e Marca.
* Adiciona configuração de prêmios no admin.
* Adiciona customização de cores no admin.
* Adiciona base de WhatsApp por link compartilhável.
* Botão flutuante de salvar em desktop e mobile.
* Regra para abrir palpites apenas X dias antes do jogo.

= 2.4.2 =
* Corrige salvamento dos textos usando campos individuais em vez de array aninhado.
* Regrava a option bce26_texts de forma direta.
* Mostra quantidade de campos recebidos após salvar.

= 2.4.3 =
* Adiciona diagnóstico de alterações em Textos e Títulos.
* Mostra qual campo mudou, valor anterior e novo valor.
* Salva textos também em options individuais para evitar falha com arrays no POST.
* Mantém compatibilidade com option bce26_texts.

= 2.4.4 =
* Troca campo de e-mail por telefone no cadastro.
* Adiciona prefixo fixo +55.
* Valida telefone no padrão DDD 2 dígitos + número 9 dígitos.
* Mantém compatibilidade interna usando a coluna existente de e-mail para armazenar telefone.

= 2.4.5 =
* Troca DDI fixo +55 por lista suspensa com bandeira e código do país.
* Brasil vem pré-selecionado por padrão.
* Mantém validação especial para telefone brasileiro: DDD 2 dígitos + 9 dígitos.
* Admin passa a exibir Telefone em vez de E-mail.

= 2.4.6 =
* Corrige erro crítico ao salvar participante com telefone.
* Remove chamada inexistente sanitize_telefone().
* Corrige cadastro usando DDI + telefone.
* Corrige edição de telefone no admin.
* Adiciona migração para garantir coluna telefone em instalações antigas.

= 2.4.7 =
* Permite que o participante altere o próprio nome e telefone depois do cadastro.
* Mantém a regra de nome único entre participantes.
* Identifica o participante pelo cookie do navegador.
* Remove travas de readonly/disabled nos campos de nome, DDI e telefone.

= 2.5 =
* Adiciona acesso opcional via usuários WordPress.
* Participante pode continuar jogando rápido com nome + telefone.
* Participante pode criar acesso com e-mail e senha após salvar.
* Participante logado recupera os palpites em outro dispositivo.
* Adiciona coluna wp_user_id e migração automática.
* Admin mostra se o participante possui conta vinculada.

= 2.5.1 =
* Adiciona login WordPress inline dentro da página do bolão.
* Usuário não é levado para wp-login.php para entrar.
* Mantém criação de acesso WordPress dentro da própria página.
* Recuperação de senha usa redirect para voltar ao bolão.
* Login externo com redirect_to preserva retorno para o bolão.

= 2.5.2 =
* Corrige ausência da opção de criar usuário/acesso.
* Login inline agora funciona mesmo sem participante carregado no navegador.
* Mostra orientação para salvar palpites antes de criar acesso.
* Mantém criação de acesso opcional após salvar nome, telefone e palpites.

= 2.5.3 =
* Altera ordem da página: Cadastro do jogador antes de Acesso do participante.
* Move acesso do participante para depois do cadastro.
* Remove formulários aninhados para evitar HTML inválido.
* Botões de criar/entrar/vincular acesso não disparam salvamento de palpites.

= 2.5.4 =
* Corrige salvamento da aba Produto e Marca usando campos individuais.
* Prêmios, cores e WhatsApp passam a ser salvos também em options individuais.
* Mantém compatibilidade com as options antigas em array.
* Após salvar, mostra cada campo alterado com valor anterior e novo valor.

= 2.5.5 =
* Corrige leitura das configurações da aba Produto e Marca.
* Funções de leitura agora priorizam options individuais salvas.
* Recarrega os campos do admin usando a mesma leitura do frontend.
* Adiciona leitura atual após salvar para diagnóstico.
