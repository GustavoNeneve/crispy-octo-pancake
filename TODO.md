# TODO – MVP: Gestor de Demandas Marketing

Lista de tarefas necessárias para o MVP funcional, baseada no estado verificado do plugin `wp-demandas`.

---

## Legenda de status
| Símbolo | Significado |
|---------|-------------|
| ✅ | Implementado e funcional |
| ⚠️ | Parcialmente implementado / precisa de ajuste |
| ❌ | Não implementado |

---

## 1. Upload de Arquivos ✅

> **Estado verificado:** imagens eram salvas como array de URLs recebidas via parâmetro JSON. Agora há endpoint `POST /tasks/upload` e `POST /tasks/{id}/upload` usando `wp_handle_upload()` e a Media Library do WordPress. O front-end usa `<input type="file">` com upload via `FormData`.

- [x] **Back-end (PHP):** Criar endpoint `POST /tasks/upload` e `POST /tasks/{id}/upload` com `wp_handle_upload()`.
- [x] **Back-end (PHP):** Validar tipo MIME (somente imagens) e tamanho máximo (5 MB).
- [x] **Back-end (PHP):** Registrar `action = 'image_added'` no histórico após upload bem-sucedido.
- [x] **Front-end (JS):** Substituir prompt de URL por `<input type="file">` com suporte a múltiplos arquivos.
- [x] **Front-end (JS):** Upload imediato via `FormData`; URL retornada adicionada a `state.taskImages`.
- [x] **Front-end (JS):** Botão desativado com texto "Enviando…" durante upload (feedback visual).

---

## 2. Drag and Drop ⚠️

> **Estado verificado:** D&D nativo com HTML5 está implementado (`dragstart`/`dragend`/`dragover`/`dragleave`/`drop` em `app.js:251-274`). Funciona em desktop. **Não há suporte a touch events** (mobile). Não usa `@hello-pangea/dnd`.

- **Decisão de stack (✅ feita):** Permanecer em **vanilla JS** — sem React, sem Vite. Tailwind CSS integrado via Play CDN (`class-shortcode.php`) com `preflight:false`. Não há migração para `@hello-pangea/dnd` no MVP.
- [ ] **Se mantiver D&D nativo:**
  - [ ] Adicionar suporte a touch events (`touchstart`, `touchmove`, `touchend`) para funcionar em mobile.
  - [ ] Melhorar o feedback visual da classe `dm-drag-over` (borda mais visível, placeholder de posição).
  - [ ] Garantir que, ao soltar em uma coluna, o card apareça na posição correta sem recarregar tudo.

---

## 3. Kanban Responsivo com Abas no Mobile ✅

> **Estado verificado:** Existe um mobile drawer (`.dm-mobile-drawer`) com toggle (`dm-mobile-toggle`) e media queries até 480px (`app.css:1177-1202`). O board Kanban em si não tem abas por coluna no mobile — as colunas ficam em scroll horizontal. Funciona, mas não é o layout de abas especificado.

- [ ] **Front-end (JS/CSS):** Implementar layout de abas (tabs) no mobile: uma aba por coluna do Kanban (Aguardando / Em andamento / Em aprovação / Concluído), exibindo uma coluna por vez.
- [ ] **Front-end (CSS):** Garantir que os cards e modais sejam completamente usáveis em telas de 320px–480px.

---

## 4. Gatilho Automático de Urgência ✅

> **Estado verificado:** Não existe nenhuma lógica que force uma tarefa de "Planejado" para "Urgente/Rosa" automaticamente quando o limite de média semanal é atingido. O campo `weekly_average` existe na tabela de setores e é salvo/atualizado via `/settings`, mas não é consultado em `create_task` nem em nenhum outro hook.

- [ ] **Back-end (PHP):** No endpoint `POST /tasks`, após inserir a tarefa, comparar o total de tarefas planejadas da semana com `weekly_average` do setor. Se o limite for ultrapassado, alterar o `task_type` para `urgent` e a `color` para `pink` automaticamente.
- [ ] **Back-end (PHP):** Registrar a mudança no histórico com `action = 'auto_urgent'` para rastreabilidade.
- [ ] **Front-end (JS):** Exibir um toast ou badge de aviso ao criar uma tarefa que foi automaticamente promovida para urgente.

---

## 5. Timezone do CRON ✅

> **Estado verificado:** `next_monday_midnight()` em `class-cron.php:172` calcula `00:00 UTC` usando `gmdate()` sem nenhum ajuste pelo fuso do WordPress. Para UTC-3 (Brasília), isso equivale a **domingo às 21:00** localmente — o reset semanal dispara no dia errado.

- [ ] **Back-end (PHP):** Reescrever `next_monday_midnight()` usando `wp_timezone()` e `DateTimeImmutable` para calcular segunda-feira 00:00 no fuso configurado no WordPress, convertendo para UTC antes de passar ao `wp_schedule_event()`.
- [ ] **Back-end (PHP):** Confirmar que `do_weekly_reset()` usa `current_time('timestamp')` (já ajustado ao fuso do WP) e não `time()` puro ao derivar `old_week_key` e `new_week_key`.

---

## 6. Salvar Histórico (Logs) ⚠️

> **Estado verificado:** `log_history()` é chamado em: `create_task` ✅, `update_task` ✅, `delete_task` ✅ (linha 407), `change_status` ✅, `approve_task` ✅, `transfer_task` ✅, `do_weekly_reset` ✅. **O registro de delete já existe.** Gaps: upload de imagem não registra histórico; front-end não exibe old/new em diff; datas exibidas em UTC.

- [x] **Back-end (PHP):** `delete_task` já registra `log_history` com `action = 'deleted'` (`class-rest-api.php:407`). ✅
- [ ] **Back-end (PHP):** Após implementar o endpoint de upload (item 1), registrar `action = 'image_added'` no histórico.
- [ ] **Front-end (JS):** No modal de detalhes, exibir old/new lado a lado para ações `status_changed` e `transferred` (atualmente exibe apenas o novo valor — `renderTaskDetail` em `app.js:620`).
- [ ] **Front-end (JS):** Formatar datas do histórico no fuso horário do WordPress (UTC-3), não UTC bruto.

---

## 7. Modal de Detalhes ⚠️

> **Estado verificado:** Modal existe e funciona (`openTaskDetail` em `app.js:507`). Exibe: badge de tipo, título, descrição, responsável, criador, semana, status, imagens (clicáveis com `window.open`), botões de ação (Editar, Avançar, Enviar p/ Aprovação, **Repassar ✅**, Aprovar, Excluir) e histórico. **Aprovador (`approved_by`/`approved_at`) não é exibido. Não há lightbox. Não há destaque visual para `in_approval`. Erro 404 só mostra toast genérico.**

- [ ] **Front-end (JS):** Adicionar campo de upload de imagem diretamente no modal de detalhes (sem abrir o modal de edição separado).
- [ ] **Front-end (JS):** Substituir `window.open` por um lightbox simples (overlay com navegação prev/next).
- [ ] **Front-end (JS):** Exibir `approved_by` e `approved_at` quando a tarefa estiver `completed`.
- [ ] **Front-end (JS):** Destacar visualmente o modal quando `task.status === 'in_approval'` (borda colorida ou banner de alerta para o gestor).
- [ ] **Front-end (JS):** Tratar especificamente erro 404 em `openTaskDetail` com mensagem "Esta demanda foi removida" em vez de toast genérico.

---

## 8. Dashboard com Gráficos ✅

> **Estado verificado:** Endpoint `GET /dashboard` existe e retorna dados agregados ✅. O front-end (`app.js:716`) já renderiza barras CSS customizadas por tipo de tarefa e uma tabela de membros. **Não há biblioteca de gráficos (Recharts/Chart.js). Gráfico de pizza não está implementado.**

- [ ] **Decisão de stack:** Escolher entre Chart.js (sem build, via CDN) ou Recharts (requer React/build).
- [ ] **Front-end (JS):** Integrar Chart.js via CDN e renderizar gráfico de pizza (distribuição por tipo) e barras (por membro) no painel do gestor.
- [ ] **Front-end (JS):** Substituir as barras CSS manuais pelos componentes Chart.js equivalentes.

---

## 9. React.js ❌ / Tailwind CSS ✅

> **Decisão de stack (✅ feita):** O front-end permanece em **JavaScript vanilla**. Tailwind CSS integrado via Play CDN com `preflight: false` para coexistência com os tokens CSS `--dm-*` existentes. Não há React, não há etapa de build.

- **Decisão arquitetural (✅ feita):** Permanece vanilla JS + Tailwind CDN.
- [x] Integrar Tailwind via CDN no `enqueue_assets` do shortcode com `preflight: false`.
- [x] Aplicar classes Tailwind nos novos componentes: upload UI, chart wrappers, tabs do Kanban.
- [ ] (Opcional pós-MVP) Mapear tokens CSS `--dm-*` para `theme.extend.colors` do Tailwind progressivamente.

---

## Resumo de Prioridade para MVP

| # | Item | Status | Esforço | Impacto |
|---|------|--------|---------|---------|
| 1 | Corrigir timezone do CRON (risco operacional) | ✅ | Baixo | Alto |
| 2 | Decisão: vanilla JS vs. React (define toda a Fase 3) | ✅ | — | Alto |
| 3 | Gatilho automático de urgência (POST /tasks) | ✅ | Médio | Alto |
| 4 | Upload real de arquivos (wp_handle_upload) | ✅ | Alto | Alto |
| 5 | Touch support no D&D nativo | ⚠️ | Médio | Alto |
| 6 | Abas Kanban no mobile | ✅ | Médio | Médio |
| 7 | Histórico: diff old/new + fuso horário | ⚠️ | Baixo | Médio |
| 8 | Modal de detalhes: lightbox + aprovador + destaque | ⚠️ | Médio | Médio |
| 9 | Dashboard: integrar biblioteca de gráficos | ✅ | Médio | Médio |
| 10 | Tailwind CSS (após decisão de stack) | ✅ | Médio | Baixo |
