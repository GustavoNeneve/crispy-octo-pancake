# TODO – MVP: Gestor de Demandas Marketing

Lista de tarefas necessárias para o MVP funcional, baseada no estado atual do plugin `wp-demandas`.

---

## 1. Upload de Arquivos

> **Status atual:** imagens são salvas apenas como array de URLs digitadas manualmente via `prompt()` (`app.js:357`). Não há upload real de arquivo.

- [ ] **Back-end (PHP):** Criar endpoint `POST /tasks/{id}/upload` que receba `multipart/form-data` e use `wp_handle_upload()` para salvar o arquivo na Media Library do WordPress, retornando a URL pública.
- [ ] **Back-end (PHP):** Validar tipo MIME (somente imagens) e tamanho máximo no endpoint de upload.
- [ ] **Front-end (JS):** Substituir o `prompt()` de URL por um `<input type="file">` real no modal de criação/edição de tarefa.
- [ ] **Front-end (JS):** Fazer `POST` para o novo endpoint de upload via `FormData` (sem `Content-Type: application/json`) e adicionar a URL retornada ao array `state.taskImages`.
- [ ] **Front-end (JS):** Exibir preview da imagem após upload bem-sucedido antes de salvar a tarefa.

---

## 2. Drag and Drop

> **Status atual:** D&D funcional com HTML5 nativo (`dragstart`/`drop` em `app.js:251-274`). Funciona em desktop, mas é frágil em mobile e não há feedback visual refinado.

- [ ] **Decisão de stack:** Confirmar se o D&D nativo atual é suficiente para o MVP ou se é necessário migrar para `@hello-pangea/dnd` (requereria setup de build com React/Vite).
- [ ] **Se mantiver D&D nativo:**
  - [ ] Adicionar suporte a touch events (`touchstart`, `touchmove`, `touchend`) para funcionar em mobile.
  - [ ] Melhorar o feedback visual do `dm-drag-over` (borda mais visível, placeholder de posição).
  - [ ] Garantir que ao soltar em uma coluna, o card novo apareça na posição correta sem recarregar tudo.
- [ ] **Se migrar para `@hello-pangea/dnd`:**
  - [ ] Configurar ambiente de build (Vite ou Webpack) na pasta `wp-demandas/assets/`.
  - [ ] Reescrever o board em React com `DragDropContext`, `Droppable` e `Draggable`.
  - [ ] Adaptar o enqueue do WordPress para carregar o bundle compilado.

---

## 3. Salvar Histórico (Logs)

> **Status atual:** O back-end registra histórico via `WP_Demandas_Database::log_history()` nas ações principais (create, update, status_changed, approved, transferred, weekly_carryover). O modal de detalhes já exibe o histórico. **Esta funcionalidade está majoritariamente implementada.**

- [ ] **Back-end (PHP):** Confirmar que o histórico é salvo também nas ações de `delete_task` (verificar `class-rest-api.php`).
- [ ] **Back-end (PHP):** Garantir que o upload de imagem (item 1) também registre entrada no histórico (`action = 'image_added'`).
- [ ] **Front-end (JS):** No modal de detalhes (`dm-modal-detail`), exibir o valor antigo e novo lado a lado para ações de `status_changed` e `transferred` (atualmente exibe apenas o novo valor).
- [ ] **Front-end (JS):** Formatar datas do histórico no fuso horário do WordPress (UTC-3), não UTC.

---

## 4. Modal de Detalhes

> **Status atual:** Modal de detalhes existe e funciona (`openTaskDetail` em `app.js:507`). Exibe: badge de tipo, título, descrição, responsável, criador, semana, status, imagens, botões de ação e histórico. **Estrutura base completa.**

- [ ] **Front-end (JS):** Adicionar campo de upload de imagem **diretamente no modal de detalhes** (sem precisar abrir o modal de edição separado).
- [ ] **Front-end (JS):** Exibir imagens em um grid clicável com lightbox simples (atualmente abre em `window.open`).
- [ ] **Front-end (JS):** Mostrar o nome do aprovador (`approved_by` / `approved_at`) quando a tarefa estiver concluída.
- [ ] **Front-end (JS):** Destacar visualmente o modal quando a tarefa estiver `in_approval` (borda colorida ou banner de alerta para o gestor).
- [ ] **Front-end (JS):** Tratar o caso de erro quando `GET /tasks/{id}` retornar 404 (task deletada por outro usuário enquanto o board estava aberto).

---

## 5. Tailwind CSS

> **Status atual:** O front-end usa CSS customizado em `assets/css/app.css` com tokens CSS (`--dm-*`). **Tailwind não está integrado.**

- [ ] **Decisão de stack:** Definir abordagem — CDN (mais simples, sem build) ou Tailwind CLI/PostCSS (necessário se houver React).
- [ ] **Se usar CDN para MVP rápido:**
  - [ ] Adicionar `<link>` do Tailwind CDN no `enqueue_assets` do shortcode (somente na página do app).
  - [ ] Mapear os tokens CSS existentes (`--dm-primary`, `--dm-gray-*`, etc.) para as classes utilitárias do Tailwind.
  - [ ] Converter os componentes principais (cards, modais, botões, nav) para classes Tailwind, removendo o CSS customizado equivalente.
  - [ ] Manter os tokens CSS globais do `app.css` para valores que o Tailwind não cobre (ex: cores de tipo de tarefa).
- [ ] **Se usar Tailwind CLI (recomendado com React):**
  - [ ] Instalar `tailwindcss` como dependência de dev.
  - [ ] Configurar `tailwind.config.js` com o `content` apontando para os arquivos JS/PHP do plugin.
  - [ ] Configurar script de build no `package.json` e adicionar o CSS compilado ao `enqueue_assets`.

---

## Resumo de Prioridade para MVP

| # | Item | Esforço | Impacto |
|---|------|---------|---------|
| 1 | Upload real de arquivos (back-end + front-end) | Alto | Alto |
| 2 | Touch support no D&D nativo (sem migrar para React) | Médio | Alto |
| 3 | Histórico: registro de delete + exibição de old/new | Baixo | Médio |
| 4 | Upload no modal de detalhes + lightbox | Médio | Médio |
| 5 | Tailwind via CDN (sem build) | Médio | Médio |
