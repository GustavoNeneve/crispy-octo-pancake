/**
 * WP Demandas – Single Page Application
 * Marketing Task Management Board
 */
/* global wpDemandas */

(function () {
  'use strict';

  // ---------------------------------------------------------------
  // State
  // ---------------------------------------------------------------
  const state = {
    user: null,
    tasks: [],
    users: [],
    sectors: [],
    recurringTypes: [],
    settings: {},
    weekKey: '',
    dayOfWeek: 1,
    currentView: 'board',
    editingTaskId: null,
    taskImages: [],
    filterSectorId: 0,
    filterMemberId: 0,
    charts: {},
  };

  // ---------------------------------------------------------------
  // API helper
  // ---------------------------------------------------------------
  const api = {
    base: '',
    nonce: '',

    async request(method, path, body = null) {
      const opts = {
        method,
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': api.nonce,
        },
        credentials: 'same-origin',
      };
      if (body !== null) opts.body = JSON.stringify(body);
      const res = await fetch(api.base + path, opts);
      const data = await res.json();
      if (!res.ok) {
        throw new Error(data.message || 'Erro na requisição');
      }
      return data;
    },

    get:    (path)       => api.request('GET',    path),
    post:   (path, body) => api.request('POST',   path, body),
    put:    (path, body) => api.request('PUT',    path, body),
    delete: (path)       => api.request('DELETE', path),

    /**
     * Upload a file via multipart/form-data (no Content-Type header set
     * so the browser can add the correct multipart boundary).
     */
    async upload(path, file) {
      const form = new FormData();
      form.append('file', file);
      const res = await fetch(api.base + path, {
        method: 'POST',
        headers: { 'X-WP-Nonce': api.nonce },
        credentials: 'same-origin',
        body: form,
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.message || 'Erro no upload');
      return data;
    },
  };

  // ---------------------------------------------------------------
  // Toast notifications
  // ---------------------------------------------------------------
  function toast(msg, type = 'info') {
    const c = document.getElementById('dm-toast-container');
    const t = document.createElement('div');
    t.className = `dm-toast dm-toast-${type}`;
    t.textContent = msg;
    c.appendChild(t);
    setTimeout(() => t.remove(), 3200);
  }

  // ---------------------------------------------------------------
  // Utility
  // ---------------------------------------------------------------
  function el(id) { return document.getElementById(id); }

  function fmt(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function typeLabel(type) {
    return { routine: '🔵 Rotina', planned: '🟡 Planejado', urgent: '🩷 Urgente', planned_recurring: '🟡 Recorrente' }[type] || type;
  }

  function typeTagClass(color) {
    return { blue: 'dm-tag-blue', yellow: 'dm-tag-yellow', pink: 'dm-tag-pink' }[color] || 'dm-tag-yellow';
  }

  function statusLabel(s) {
    return { waiting: 'Aguardando', in_progress: 'Em Andamento', in_approval: 'Em Aprovação', completed: 'Concluído' }[s] || s;
  }

  function statusBadgeClass(s) {
    return { waiting: 'dm-badge-waiting', in_progress: 'dm-badge-inprogress', in_approval: 'dm-badge-approval', completed: 'dm-badge-completed' }[s] || '';
  }

  function initials(name) {
    if (!name) return '?';
    return name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
  }

  function relativeTime(dt) {
    if (!dt) return '';
    const diff = Date.now() - new Date(dt).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 60) return `${mins} min atrás`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h atrás`;
    return `${Math.floor(hrs / 24)}d atrás`;
  }

  function populateSelect(selectEl, items, valueKey, labelKey, emptyLabel = '') {
    // Keep first option if it exists
    const first = selectEl.options[0];
    selectEl.innerHTML = '';
    if (emptyLabel !== null) {
      const opt = document.createElement('option');
      opt.value = '';
      opt.textContent = emptyLabel || first?.textContent || '— Selecione —';
      selectEl.appendChild(opt);
    }
    items.forEach(item => {
      const opt = document.createElement('option');
      opt.value = item[valueKey];
      opt.textContent = item[labelKey];
      selectEl.appendChild(opt);
    });
  }

  // ---------------------------------------------------------------
  // Navigation
  // ---------------------------------------------------------------
  function setView(view) {
    state.currentView = view;
    document.querySelectorAll('.dm-view').forEach(v => (v.style.display = 'none'));
    const viewEl = el(`dm-view-${view}`);
    if (viewEl) viewEl.style.display = '';

    document.querySelectorAll('.dm-nav-link').forEach(l => {
      l.classList.toggle('active', l.dataset.view === view);
    });

    // Close mobile drawer
    el('dm-mobile-drawer').style.display = 'none';

    if (view === 'board')     loadBoard();
    if (view === 'dashboard') loadDashboard();
    if (view === 'settings')  loadSettings();
  }

  function initNavigation() {
    document.querySelectorAll('[data-view]').forEach(btn => {
      btn.addEventListener('click', () => setView(btn.dataset.view));
    });

    el('dm-mobile-toggle').addEventListener('click', () => {
      const d = el('dm-mobile-drawer');
      d.style.display = d.style.display === 'none' ? 'flex' : 'none';
    });

    // Hide dashboard for non-managers
    if (!state.user.is_manager) {
      const dEl = el('dm-nav-dashboard');
      const dMob = el('dm-nav-dashboard-mob');
      const dSide = el('dm-sidenav-dashboard');
      if (dEl) dEl.style.display = 'none';
      if (dMob) dMob.style.display = 'none';
      if (dSide) dSide.style.display = 'none';
    }
  }

  // ---------------------------------------------------------------
  // Board
  // ---------------------------------------------------------------
  async function loadBoard() {
    try {
      let path = `/tasks?week_key=${state.weekKey}`;
      if (state.filterSectorId) path += `&sector_id=${state.filterSectorId}`;
      if (state.filterMemberId) path += `&assigned_to=${state.filterMemberId}`;
      state.tasks = await api.get(path);
      renderBoard();
      checkRoutineBanner();
    } catch (e) {
      toast(e.message, 'error');
    }
  }

  function renderBoard() {
    const statuses = ['waiting', 'in_progress', 'in_approval', 'completed'];
    statuses.forEach(status => {
      const colEl = el(`dm-col-${status}`);
      const tasks = state.tasks.filter(t => t.status === status);
      el(`dm-count-${status}`).textContent = tasks.length;

      // Keep mobile tab counts in sync.
      const tabCount = el(`dm-tab-count-${status}`);
      if (tabCount) tabCount.textContent = tasks.length;

      colEl.innerHTML = '';
      if (tasks.length === 0) {
        colEl.innerHTML = '<div class="dm-empty-col">Nenhuma demanda</div>';
        return;
      }
      tasks.forEach(task => colEl.appendChild(buildTaskCard(task)));
    });
  }

  function buildTaskCard(task) {
    const card = document.createElement('div');
    card.className = 'dm-task-card';
    card.dataset.id = task.id;
    card.dataset.color = task.color;
    card.draggable = true;

    const colorClass = typeTagClass(task.color);
    const typeLbl = typeLabel(task.task_type);
    const assigneeName = task.assignee_name || 'Não atribuído';

    card.innerHTML = `
      <div class="dm-task-card-header">
        <span class="dm-task-type-tag ${colorClass}">${fmt(typeLbl)}</span>
        <span class="dm-task-card-title">${fmt(task.title)}</span>
      </div>
      ${task.description ? `<div style="font-size:12px;color:var(--dm-gray-400);margin-top:4px;line-height:1.4;overflow:hidden;max-height:36px">${fmt(task.description.substring(0,100))}</div>` : ''}
      <div class="dm-task-card-meta">
        <span class="dm-task-assignee">
          <span class="dm-avatar-sm">${initials(assigneeName)}</span>
          ${fmt(assigneeName)}
        </span>
        ${task.images && task.images.length ? `<span>📎 ${task.images.length}</span>` : ''}
      </div>
      <div class="dm-task-card-actions">
        <button class="dm-btn dm-btn-xs dm-btn-outline" data-action="view" data-id="${task.id}">Ver</button>
        ${task.status !== 'completed' ? `<button class="dm-btn dm-btn-xs dm-btn-ghost" data-action="move-next" data-id="${task.id}">Avançar →</button>` : ''}
        <button class="dm-btn dm-btn-xs dm-btn-ghost" data-action="transfer" data-id="${task.id}">Repassar</button>
        ${state.user.is_manager && task.status === 'in_approval' ? `<button class="dm-btn dm-btn-xs dm-btn-approve" data-action="approve" data-id="${task.id}">Aprovar</button>` : ''}
      </div>`;

    card.addEventListener('click', e => {
      const btn = e.target.closest('[data-action]');
      if (!btn) {
        openTaskDetail(task.id);
        return;
      }
      e.stopPropagation();
      const action = btn.dataset.action;
      const id = parseInt(btn.dataset.id, 10);
      if (action === 'view')      openTaskDetail(id);
      if (action === 'move-next') moveTaskNext(id);
      if (action === 'transfer')  openTransferModal(id);
      if (action === 'approve')   approveTask(id);
    });

    // Drag & drop
    card.addEventListener('dragstart', e => {
      e.dataTransfer.setData('text/plain', String(task.id));
      card.classList.add('dm-dragging');
    });
    card.addEventListener('dragend', () => card.classList.remove('dm-dragging'));

    return card;
  }

  function initDragDrop() {
    document.querySelectorAll('.dm-column-body').forEach(col => {
      col.addEventListener('dragover', e => {
        e.preventDefault();
        col.classList.add('dm-drag-over');
      });
      col.addEventListener('dragleave', () => col.classList.remove('dm-drag-over'));
      col.addEventListener('drop', async e => {
        e.preventDefault();
        col.classList.remove('dm-drag-over');
        const taskId = parseInt(e.dataTransfer.getData('text/plain'), 10);
        const newStatus = col.dataset.status;
        await changeStatus(taskId, newStatus);
      });
    });
  }

  async function moveTaskNext(id) {
    const task = state.tasks.find(t => t.id === id);
    if (!task) return;
    const order = ['waiting', 'in_progress', 'in_approval', 'completed'];
    const idx = order.indexOf(task.status);
    if (idx < order.length - 1) {
      await changeStatus(id, order[idx + 1]);
    }
  }

  async function changeStatus(id, newStatus) {
    try {
      await api.post(`/tasks/${id}/status`, { status: newStatus });
      toast(`Status atualizado: ${statusLabel(newStatus)}`, 'success');
      await loadBoard();
    } catch (e) {
      toast(e.message, 'error');
    }
  }

  async function approveTask(id) {
    if (!confirm('Aprovar e concluir esta demanda?')) return;
    try {
      await api.post(`/tasks/${id}/approve`, {});
      toast('Demanda aprovada!', 'success');
      await loadBoard();
    } catch (e) {
      toast(e.message, 'error');
    }
  }

  // ---- Routine banner ----
  async function checkRoutineBanner() {
    if (state.dayOfWeek < 1 || state.dayOfWeek > 5) return; // Weekdays only
    try {
      const routines = state.tasks.filter(t => t.task_type === 'routine');
      const banner = el('dm-routine-banner');
      if (routines.length === 0) {
        banner.style.display = 'flex';
      } else {
        banner.style.display = 'none';
      }
    } catch {}
  }

  // ---- Board filters ----
  function initBoardFilters() {
    const secSel = el('dm-filter-sector');
    const memSel = el('dm-filter-member');
    if (state.user.is_manager) {
      secSel.style.display = '';
      memSel.style.display = '';
    }
    secSel.addEventListener('change', () => {
      state.filterSectorId = parseInt(secSel.value, 10) || 0;
      loadBoard();
    });
    memSel.addEventListener('change', () => {
      state.filterMemberId = parseInt(memSel.value, 10) || 0;
      loadBoard();
    });
  }

  // ---------------------------------------------------------------
  // Mobile Kanban tabs
  // ---------------------------------------------------------------
  function initBoardTabs() {
    document.querySelectorAll('.dm-board-tab').forEach(btn => {
      btn.addEventListener('click', () => setActiveTab(btn.dataset.tab));
    });
    // Activate the first tab on boot so the correct column is visible.
    setActiveTab('waiting');
  }

  function setActiveTab(status) {
    document.querySelectorAll('.dm-board-tab').forEach(btn => {
      const active = btn.dataset.tab === status;
      btn.classList.toggle('active', active);
      btn.setAttribute('aria-selected', String(active));
    });
    document.querySelectorAll('#dm-board .dm-column').forEach(col => {
      col.classList.toggle('dm-tab-active', col.dataset.status === status);
    });
  }

  // ---------------------------------------------------------------
  // Task Form Modal
  // ---------------------------------------------------------------
  function initTaskForm() {
    el('dm-btn-new-task').addEventListener('click', () => openTaskModal());
    const sideNewTask = el('dm-btn-new-task-side');
    if (sideNewTask) sideNewTask.addEventListener('click', () => openTaskModal());

    el('dm-task-type').addEventListener('change', function () {
      const isRecurring = this.value === 'planned_recurring';
      el('dm-recurring-type-group').style.display = isRecurring ? '' : 'none';
    });

    el('dm-task-title').addEventListener('input', debounce(handleTitleAutocomplete, 300));
    el('dm-task-title').addEventListener('blur', () => setTimeout(() => { el('dm-title-autocomplete').style.display = 'none'; }, 200));

    el('dm-btn-add-image').addEventListener('click', () => {
      el('dm-task-file-input').click();
    });

    el('dm-task-file-input').addEventListener('change', async function () {
      const files = Array.from(this.files || []);
      if (!files.length) return;
      this.value = ''; // allow re-selecting same file

      for (const file of files) {
        const btn = el('dm-btn-add-image');
        const origText = btn.textContent;
        btn.disabled = true;
        btn.textContent = '⏳ Enviando…';
        try {
          const result = await api.upload('/tasks/upload', file);
          state.taskImages.push(result.url);
          renderImageList();
        } catch (e) {
          toast(e.message, 'error');
        } finally {
          btn.disabled = false;
          btn.textContent = origText;
        }
      }
    });

    el('dm-form-task').addEventListener('submit', async e => {
      e.preventDefault();
      await saveTask();
    });
  }

  async function handleTitleAutocomplete() {
    const q = el('dm-task-title').value.trim();
    const ac = el('dm-title-autocomplete');
    if (q.length < 2) { ac.style.display = 'none'; return; }
    try {
      const types = await api.get(`/recurring-types?search=${encodeURIComponent(q)}`);
      if (!types.length) { ac.style.display = 'none'; return; }
      ac.innerHTML = '';
      types.forEach(t => {
        const li = document.createElement('li');
        li.textContent = t.name;
        li.addEventListener('mousedown', () => {
          el('dm-task-title').value = t.name;
          el('dm-task-type').value = 'planned_recurring';
          el('dm-task-type').dispatchEvent(new Event('change'));
          // Select the recurring type
          const rSel = el('dm-task-recurring-type');
          const opt = Array.from(rSel.options).find(o => o.value == t.id);
          if (opt) rSel.value = t.id;
          ac.style.display = 'none';
        });
        ac.appendChild(li);
      });
      ac.style.display = '';
    } catch {}
  }

  function renderImageList() {
    const list = el('dm-image-list');
    list.innerHTML = '';
    const placeholder = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><rect width="64" height="64" fill="%23eee"/></svg>';
    state.taskImages.forEach((url, i) => {
      const div = document.createElement('div');
      div.className = 'dm-image-item';

      const img = document.createElement('img');
      img.src = url;
      img.alt = `Imagem ${i + 1}`;
      img.addEventListener('error', () => { img.src = placeholder; });

      const removeBtn = document.createElement('button');
      removeBtn.className = 'dm-image-item-remove';
      removeBtn.setAttribute('aria-label', 'Remover imagem');
      removeBtn.textContent = '×';
      removeBtn.addEventListener('click', () => {
        state.taskImages.splice(i, 1);
        renderImageList();
      });

      div.appendChild(img);
      div.appendChild(removeBtn);
      list.appendChild(div);
    });
  }

  function openTaskModal(taskId = null) {
    state.editingTaskId = taskId;
    state.taskImages = [];
    const form = el('dm-form-task');
    form.reset();
    el('dm-task-id').value = '';
    el('dm-image-list').innerHTML = '';
    el('dm-title-autocomplete').style.display = 'none';
    el('dm-recurring-type-group').style.display = 'none';
    el('dm-modal-task-title').textContent = taskId ? 'Editar Demanda' : 'Nova Demanda';

    // Populate recurring types
    populateSelect(el('dm-task-recurring-type'), state.recurringTypes, 'id', 'name', '— Selecione —');

    // Populate assignee for managers
    if (state.user.is_manager) {
      el('dm-task-assignee-group').style.display = '';
      populateSelect(el('dm-task-assignee'), state.users, 'id', 'display_name', '— Eu mesmo —');
    }

    // Pre-select suggested type
    if (!taskId) {
      const day = state.dayOfWeek;
      el('dm-task-type').value = day === 1 ? 'planned' : (day > 2 ? 'urgent' : 'planned');
    }

    if (taskId) {
      const t = state.tasks.find(t => t.id === taskId);
      if (t) {
        el('dm-task-id').value = t.id;
        el('dm-task-title').value = t.title;
        el('dm-task-description').value = t.description || '';
        el('dm-task-type').value = t.task_type;
        el('dm-task-type').dispatchEvent(new Event('change'));
        if (t.recurring_type_id) el('dm-task-recurring-type').value = t.recurring_type_id;
        if (state.user.is_manager) el('dm-task-assignee').value = t.assigned_to || '';
        state.taskImages = (t.images || []).slice();
        renderImageList();
      }
    }

    showModal('dm-modal-task');
  }

  async function saveTask() {
    const id     = parseInt(el('dm-task-id').value, 10) || null;
    const title  = el('dm-task-title').value.trim();
    const type   = el('dm-task-type').value;
    const desc   = el('dm-task-description').value;
    const recId  = el('dm-task-recurring-type').value || null;
    const assignee = state.user.is_manager ? (parseInt(el('dm-task-assignee').value, 10) || 0) : 0;

    if (!title) { toast('Título é obrigatório', 'error'); return; }

    const body = {
      title,
      task_type:         type,
      description:       desc,
      recurring_type_id: recId ? parseInt(recId, 10) : null,
      images:            state.taskImages,
      sector_id:         state.user.sector_id || 0,
    };
    if (state.user.is_manager && assignee) body.assigned_to = assignee;

    try {
      if (id) {
        await api.put(`/tasks/${id}`, body);
        toast('Demanda atualizada!', 'success');
      } else {
        const created = await api.post('/tasks', body);
        // Auto-promotion: user submitted 'planned' but server returned 'urgent'.
        if (created && body.task_type !== 'urgent' && created.task_type === 'urgent') {
          toast('🩷 Demanda promovida para Urgente (média semanal do setor atingida)', 'warning');
        } else {
          toast('Demanda criada!', 'success');
        }
      }
      closeModal('dm-modal-task');
      await loadBoard();
    } catch (e) {
      toast(e.message, 'error');
    }
  }

  // ---------------------------------------------------------------
  // Task Detail Modal
  // ---------------------------------------------------------------
  async function openTaskDetail(id) {
    try {
      const [task, history] = await Promise.all([
        api.get(`/tasks/${id}`),
        api.get(`/tasks/${id}/history`),
      ]);
      renderTaskDetail(task, history);
      showModal('dm-modal-detail');
    } catch (e) {
      toast(e.message, 'error');
    }
  }

  function renderTaskDetail(task, history) {
    const colorClass = typeTagClass(task.color);
    el('dm-detail-type-badge').className = `dm-task-type-badge ${colorClass}`;
    el('dm-detail-type-badge').textContent = typeLabel(task.task_type);
    el('dm-modal-detail-title').textContent = task.title;
    el('dm-detail-description').textContent = task.description || '(sem descrição)';
    el('dm-detail-assignee').textContent = task.assignee_name || 'Não atribuído';
    el('dm-detail-creator').textContent  = task.creator_name  || '—';
    el('dm-detail-week').textContent = task.week_key;

    const statusEl = el('dm-detail-status-label');
    statusEl.className = `dm-badge ${statusBadgeClass(task.status)}`;
    statusEl.textContent = statusLabel(task.status);

    // Images
    const imgEl = el('dm-detail-images');
    imgEl.innerHTML = '';
    (task.images || []).forEach(url => {
      const img = document.createElement('img');
      img.src = url;
      img.alt = 'Imagem da tarefa';
      img.addEventListener('click', () => window.open(url, '_blank'));
      imgEl.appendChild(img);
    });

    // Actions
    const actEl = el('dm-detail-actions');
    actEl.innerHTML = '';
    const uid = state.user.id;

    if (task.assigned_to == uid || state.user.is_manager) {
      const editBtn = document.createElement('button');
      editBtn.className = 'dm-btn dm-btn-outline dm-btn-sm';
      editBtn.textContent = '✏ Editar';
      editBtn.addEventListener('click', () => {
        closeModal('dm-modal-detail');
        openTaskModal(task.id);
      });
      actEl.appendChild(editBtn);

      if (task.status !== 'completed') {
        const nextBtn = document.createElement('button');
        nextBtn.className = 'dm-btn dm-btn-primary dm-btn-sm';
        nextBtn.textContent = 'Avançar →';
        nextBtn.addEventListener('click', async () => {
          closeModal('dm-modal-detail');
          await moveTaskNext(task.id);
        });
        actEl.appendChild(nextBtn);
      }

      if (task.status !== 'in_approval' && task.status !== 'completed') {
        const approvalBtn = document.createElement('button');
        approvalBtn.className = 'dm-btn dm-btn-ghost dm-btn-sm';
        approvalBtn.textContent = '📤 Enviar p/ Aprovação';
        approvalBtn.addEventListener('click', async () => {
          closeModal('dm-modal-detail');
          await changeStatus(task.id, 'in_approval');
        });
        actEl.appendChild(approvalBtn);
      }

      const transferBtn = document.createElement('button');
      transferBtn.className = 'dm-btn dm-btn-ghost dm-btn-sm';
      transferBtn.textContent = '↪ Repassar';
      transferBtn.addEventListener('click', () => {
        closeModal('dm-modal-detail');
        openTransferModal(task.id);
      });
      actEl.appendChild(transferBtn);
    }

    if (state.user.is_manager && task.status === 'in_approval') {
      const aprvBtn = document.createElement('button');
      aprvBtn.className = 'dm-btn dm-btn-approve dm-btn-sm';
      aprvBtn.textContent = '✅ Aprovar';
      aprvBtn.addEventListener('click', async () => {
        closeModal('dm-modal-detail');
        await approveTask(task.id);
      });
      actEl.appendChild(aprvBtn);
    }

    if (state.user.is_manager || task.created_by == uid) {
      const delBtn = document.createElement('button');
      delBtn.className = 'dm-btn dm-btn-danger dm-btn-sm';
      delBtn.textContent = '🗑 Excluir';
      delBtn.addEventListener('click', async () => {
        if (!confirm('Excluir esta demanda?')) return;
        closeModal('dm-modal-detail');
        try {
          await api.delete(`/tasks/${task.id}`);
          toast('Demanda excluída', 'info');
          await loadBoard();
        } catch (e) { toast(e.message, 'error'); }
      });
      actEl.appendChild(delBtn);
    }

    // History
    const histEl = el('dm-detail-history-list');
    histEl.innerHTML = '';
    if (!history.length) {
      histEl.innerHTML = '<p style="font-size:12px;color:var(--dm-gray-400)">Sem histórico.</p>';
    } else {
      history.forEach(h => {
        const div = document.createElement('div');
        div.className = 'dm-history-item';
        const actionLabels = {
          created: 'Criou',
          updated: 'Atualizou',
          status_changed: 'Mudou status',
          approved: 'Aprovou',
          transferred: 'Repassou',
          weekly_carryover: 'Semana virou',
          auto_urgent: '🩷 Promovida p/ Urgente',
          image_added: '📷 Imagem adicionada',
          deleted: 'Excluiu',
        };
        div.innerHTML = `
          <span class="dm-history-item-action">${actionLabels[h.action] || h.action}</span>
          por <span class="dm-history-item-user">${fmt(h.user_name || 'Sistema')}</span>
          ${h.new_value && h.new_value.status ? `→ <strong>${statusLabel(h.new_value.status)}</strong>` : ''}
          <br><span class="dm-history-item-time">${relativeTime(h.created_at)} · ${h.created_at ? h.created_at.slice(0,16).replace('T',' ') : ''}</span>`;
        histEl.appendChild(div);
      });
    }
  }

  // ---------------------------------------------------------------
  // Transfer Modal
  // ---------------------------------------------------------------
  function openTransferModal(taskId) {
    el('dm-transfer-task-id').value = taskId;
    el('dm-transfer-note').value = '';
    populateSelect(el('dm-transfer-user'), state.users.filter(u => u.id != state.user.id), 'id', 'display_name', '— Selecione o usuário —');
    showModal('dm-modal-transfer');
  }

  function initTransfer() {
    el('dm-btn-confirm-transfer').addEventListener('click', async () => {
      const taskId = parseInt(el('dm-transfer-task-id').value, 10);
      const userId = parseInt(el('dm-transfer-user').value, 10);
      const note   = el('dm-transfer-note').value.trim();
      if (!userId) { toast('Selecione um usuário', 'error'); return; }
      try {
        await api.post(`/tasks/${taskId}/transfer`, { user_id: userId, note });
        toast('Demanda repassada!', 'success');
        closeModal('dm-modal-transfer');
        await loadBoard();
      } catch (e) { toast(e.message, 'error'); }
    });
  }

  // ---------------------------------------------------------------
  // Routine Modal
  // ---------------------------------------------------------------
  function initRoutineModal() {
    el('dm-btn-create-routines').addEventListener('click', () => {
      el('dm-routine-inputs').innerHTML = '';
      addRoutineInput();
      showModal('dm-modal-routines');
    });

    el('dm-btn-dismiss-routines').addEventListener('click', () => {
      el('dm-routine-banner').style.display = 'none';
    });

    el('dm-btn-add-routine-input').addEventListener('click', addRoutineInput);

    el('dm-btn-confirm-routines').addEventListener('click', async () => {
      const inputs = document.querySelectorAll('.dm-routine-input');
      const titles = Array.from(inputs).map(i => i.value.trim()).filter(Boolean);
      if (!titles.length) { toast('Adicione ao menos um título', 'error'); return; }
      try {
        const result = await api.post('/tasks/routine', { titles });
        toast(`${result.created} rotina(s) criada(s)!`, 'success');
        closeModal('dm-modal-routines');
        el('dm-routine-banner').style.display = 'none';
        await loadBoard();
      } catch (e) { toast(e.message, 'error'); }
    });
  }

  function addRoutineInput() {
    const inp = document.createElement('input');
    inp.type = 'text';
    inp.className = 'dm-input dm-routine-input dm-full-width';
    inp.placeholder = 'Ex: Revisão de posts';
    inp.style.marginBottom = '8px';
    el('dm-routine-inputs').appendChild(inp);
    inp.focus();
  }

  // ---------------------------------------------------------------
  // Dashboard
  // ---------------------------------------------------------------
  async function loadDashboard() {
    let path = '/dashboard';
    const secId = parseInt(el('dm-dash-sector').value, 10) || 0;
    const params = [];
    if (secId) params.push(`sector_id=${secId}`);
    if (params.length) path += '?' + params.join('&');

    try {
      const data = await api.get(path);
      renderDashboard(data);
    } catch (e) {
      toast(e.message, 'error');
    }
  }

  function renderDashboard(data) {
    const c = el('dm-dashboard-content');
    c.innerHTML = '';

    const total     = data.total || 0;
    const byStatus  = data.by_status  || {};
    const byType    = data.by_type    || {};
    const byMember  = data.by_member  || [];

    // Status stat cards
    const statsData = [
      { label: 'Total', value: total,                  cls: 'dm-stat-blue'   },
      { label: 'Aguardando',   value: byStatus.waiting || 0,     cls: 'dm-stat-yellow' },
      { label: 'Em Andamento', value: byStatus.in_progress || 0, cls: 'dm-stat-purple' },
      { label: 'Em Aprovação', value: byStatus.in_approval || 0, cls: 'dm-stat-pink'   },
      { label: 'Concluídas',   value: byStatus.completed || 0,   cls: 'dm-stat-green'  },
    ];

    statsData.forEach(s => {
      const div = document.createElement('div');
      div.className = `dm-stat-card ${s.cls}`;
      const pct = total ? Math.round((s.value / total) * 100) : 0;
      div.innerHTML = `
        <div class="dm-stat-card-label">${s.label}</div>
        <div class="dm-stat-card-value">${s.value}</div>
        <div class="dm-stat-card-sub">${total && s.label !== 'Total' ? pct + '% do total' : `Semana ${data.week_key}`}</div>`;
      c.appendChild(div);
    });

    // Per-member breakdown table (managers only)
    if (state.user.is_manager && byMember.length) {
      const memberSection = document.createElement('div');
      memberSection.className = 'dm-dashboard-section';
      memberSection.innerHTML = `<h4>Por Liderado</h4>
        <table class="dm-member-table">
          <thead>
            <tr>
              <th>Membro</th>
              <th>Aguardando</th>
              <th>Em Andamento</th>
              <th>Em Aprovação</th>
              <th>Concluídas</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            ${byMember.map(m => {
              const tot = (m.waiting||0)+(m.in_progress||0)+(m.in_approval||0)+(m.completed||0);
              return `<tr>
                <td><span class="dm-avatar" style="margin-right:6px">${initials(m.display_name)}</span>${fmt(m.display_name)}</td>
                <td>${m.waiting||0}</td>
                <td>${m.in_progress||0}</td>
                <td>${m.in_approval||0}</td>
                <td>${m.completed||0}</td>
                <td><strong>${tot}</strong></td>
              </tr>`;
            }).join('')}
          </tbody>
        </table>`;
      c.appendChild(memberSection);
    }

    // Chart.js charts (shown below the stats grid)
    renderDashboardCharts(byType, byMember);
  }

  function renderDashboardCharts(byType, byMember) {
    const chartsWrap = el('dm-dashboard-charts');

    // Guard: Chart.js might not be loaded in certain environments.
    if (typeof window.Chart === 'undefined') {
      chartsWrap.style.display = '';
      chartsWrap.innerHTML = '<p style="color:var(--dm-gray-400);font-size:13px;padding:12px 0">Gráficos indisponíveis (biblioteca não carregada).</p>';
      return;
    }

    chartsWrap.style.display = '';

    // ---- Helper to destroy an existing chart instance ----
    function destroyChart(key) {
      if (state.charts[key]) { state.charts[key].destroy(); state.charts[key] = null; }
    }
    destroyChart('type');
    destroyChart('member');

    // ---- Pie chart: distribution by task type ----
    const typeLabels  = ['Rotina', 'Planejado', 'Recorrente', 'Urgente'];
    const typeKeys    = ['routine', 'planned', 'planned_recurring', 'urgent'];
    const typeColors  = ['#3B82F6', '#EAB308', '#A855F7', '#EC4899'];
    const typeValues  = typeKeys.map(k => byType[k] || 0);
    const hasTypeData = typeValues.some(v => v > 0);

    const typeCanvas = el('dm-chart-type');
    if (hasTypeData) {
      typeCanvas.style.display = '';
      // Remove stale no-data message if it exists from a previous render.
      const staleNoData = typeCanvas.parentElement.querySelector('.dm-chart-no-data');
      if (staleNoData) staleNoData.remove();
      state.charts.type = new window.Chart(typeCanvas, {
        type: 'pie',
        data: {
          labels: typeLabels,
          datasets: [{
            data: typeValues,
            backgroundColor: typeColors,
            borderWidth: 2,
            borderColor: '#fff',
          }],
        },
        options: {
          responsive: true,
          plugins: {
            legend: { position: 'bottom', labels: { padding: 16, font: { size: 13 } } },
            tooltip: {
              callbacks: {
                label: ctx => {
                  const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                  const pct = total ? Math.round((ctx.parsed / total) * 100) : 0;
                  return ` ${ctx.label}: ${ctx.parsed} (${pct}%)`;
                },
              },
            },
          },
        },
      });
    } else {
      typeCanvas.style.display = 'none';
      // Use a fixed sibling <p> element to avoid duplicate text on re-render.
      let noDataP = typeCanvas.parentElement.querySelector('.dm-chart-no-data');
      if (!noDataP) {
        noDataP = document.createElement('p');
        noDataP.className = 'dm-chart-no-data text-sm text-gray-400 mt-2';
        typeCanvas.parentElement.appendChild(noDataP);
      }
      noDataP.textContent = 'Nenhuma tarefa no período.';
    }

    // ---- Bar chart: completed tasks per member (managers only) ----
    const memberWrap = el('dm-chart-member-wrap');
    if (state.user.is_manager && byMember && byMember.length) {
      memberWrap.style.display = '';
      const memberLabels     = byMember.map(m => m.display_name || 'Sem nome');
      const memberCompleted  = byMember.map(m => m.completed || 0);
      const memberInProgress = byMember.map(m => (m.waiting||0) + (m.in_progress||0) + (m.in_approval||0));

      state.charts.member = new window.Chart(el('dm-chart-member'), {
        type: 'bar',
        data: {
          labels: memberLabels,
          datasets: [
            {
              label: 'Concluídas',
              data: memberCompleted,
              backgroundColor: '#10B981',
              borderRadius: 4,
            },
            {
              label: 'Em aberto',
              data: memberInProgress,
              backgroundColor: '#93C5FD',
              borderRadius: 4,
            },
          ],
        },
        options: {
          responsive: true,
          plugins: {
            legend: { position: 'bottom', labels: { padding: 16, font: { size: 13 } } },
          },
          scales: {
            x: { stacked: false, grid: { display: false } },
            y: { beginAtZero: true, ticks: { precision: 0 } },
          },
        },
      });
    } else {
      memberWrap.style.display = 'none';
    }
  }

  function initDashboardFilters() {
    el('dm-dash-sector').addEventListener('change', loadDashboard);
  }

  // ---------------------------------------------------------------
  // Settings
  // ---------------------------------------------------------------
  async function loadSettings() {
    try {
      // Populate sector selects
      populateSelect(el('dm-set-sector'), state.sectors, 'id', 'name', '— Selecione —');
      if (state.user.sector_id) el('dm-set-sector').value = state.user.sector_id;

      // Load user settings
      const settings = await api.get('/settings');
      state.settings = settings;
      if (settings.sector_id) el('dm-set-sector').value = settings.sector_id;
      el('dm-set-auto-routines').checked = !!settings.auto_create_routines;

      // Routine titles from settings_json
      const extra = settings.settings_json || {};
      if (extra.routine_titles) {
        el('dm-set-routine-titles').value = extra.routine_titles.join('\n');
      }

      // Load recurring types
      await loadRecurringList();

      // Show sectors card for managers
      if (state.user.is_manager) {
        el('dm-sectors-card').style.display = '';
        await loadSectorsList();
      }
    } catch (e) {
      toast(e.message, 'error');
    }
  }

  async function loadRecurringList() {
    const types = await api.get('/recurring-types');
    state.recurringTypes = types;
    const list = el('dm-recurring-list');
    list.innerHTML = '';
    if (!types.length) {
      list.innerHTML = '<p style="font-size:12px;color:var(--dm-gray-400)">Nenhuma demanda recorrente cadastrada.</p>';
      return;
    }
    types.forEach(t => {
      const div = document.createElement('div');
      div.className = 'dm-recurring-item';
      div.innerHTML = `
        <span class="dm-recurring-item-name">${fmt(t.name)}</span>
        <span class="dm-recurring-item-avg">Média: ${t.weekly_average}/semana</span>
        <button class="dm-btn dm-btn-ghost dm-btn-xs" data-del-rec="${t.id}" aria-label="Remover">×</button>`;
      div.querySelector('[data-del-rec]').addEventListener('click', async () => {
        if (!confirm('Remover este tipo recorrente?')) return;
        await api.delete(`/recurring-types/${t.id}`);
        await loadRecurringList();
      });
      list.appendChild(div);
    });
  }

  async function loadSectorsList() {
    const list = el('dm-sectors-list');
    list.innerHTML = '';
    state.sectors.forEach(s => {
      const div = document.createElement('div');
      div.className = 'dm-recurring-item';
      div.innerHTML = `
        <span class="dm-recurring-item-name">${fmt(s.name)}</span>
        <button class="dm-btn dm-btn-ghost dm-btn-xs" data-del-sec="${s.id}" aria-label="Remover">×</button>`;
      div.querySelector('[data-del-sec]').addEventListener('click', async () => {
        if (!confirm('Remover este setor?')) return;
        await api.delete(`/sectors/${s.id}`);
        state.sectors = state.sectors.filter(x => x.id !== s.id);
        await loadSectorsList();
      });
      list.appendChild(div);
    });
  }

  function initSettings() {
    el('dm-form-user-settings').addEventListener('submit', async e => {
      e.preventDefault();
      const sectorId   = parseInt(el('dm-set-sector').value, 10) || 0;
      const autoRoutine = el('dm-set-auto-routines').checked;
      const titles = el('dm-set-routine-titles').value.split('\n').map(s=>s.trim()).filter(Boolean);
      try {
        await api.put('/settings', {
          sector_id:           sectorId,
          auto_create_routines: autoRoutine,
          settings_json:        { routine_titles: titles },
        });
        state.user.sector_id = sectorId;
        state.user.auto_routines = autoRoutine;
        toast('Configurações salvas!', 'success');
      } catch (e) { toast(e.message, 'error'); }
    });

    el('dm-form-add-recurring').addEventListener('submit', async e => {
      e.preventDefault();
      const name = el('dm-rec-name').value.trim();
      const avg  = parseFloat(el('dm-rec-avg').value) || 1;
      if (!name) { toast('Nome é obrigatório', 'error'); return; }
      try {
        await api.post('/recurring-types', { name, weekly_average: avg, sector_id: state.user.sector_id || 0 });
        el('dm-rec-name').value = '';
        el('dm-rec-avg').value = '1';
        toast('Tipo recorrente adicionado!', 'success');
        await loadRecurringList();
      } catch (e) { toast(e.message, 'error'); }
    });

    el('dm-form-add-sector').addEventListener('submit', async e => {
      e.preventDefault();
      const name = el('dm-sec-name').value.trim();
      if (!name) { toast('Nome é obrigatório', 'error'); return; }
      try {
        const sec = await api.post('/sectors', { name });
        state.sectors.push(sec);
        el('dm-sec-name').value = '';
        toast('Setor criado!', 'success');
        await loadSectorsList();
        populateSectorSelects();
      } catch (e) { toast(e.message, 'error'); }
    });
  }

  // ---------------------------------------------------------------
  // Modal helpers
  // ---------------------------------------------------------------
  function showModal(id) {
    el(id).style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
  function closeModal(id) {
    el(id).style.display = 'none';
    document.body.style.overflow = '';
  }

  function initModals() {
    document.querySelectorAll('[data-close]').forEach(btn => {
      btn.addEventListener('click', () => closeModal(btn.dataset.close));
    });
    document.querySelectorAll('.dm-modal-overlay').forEach(overlay => {
      overlay.addEventListener('click', e => {
        if (e.target === overlay) closeModal(overlay.id);
      });
    });
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        document.querySelectorAll('.dm-modal-overlay').forEach(o => {
          if (o.style.display !== 'none') closeModal(o.id);
        });
      }
    });
  }

  // ---------------------------------------------------------------
  // Populate shared selects
  // ---------------------------------------------------------------
  function populateSectorSelects() {
    [el('dm-filter-sector'), el('dm-dash-sector'), el('dm-set-sector')].forEach(sel => {
      if (!sel) return;
      const cur = sel.value;
      populateSelect(sel, state.sectors, 'id', 'name', sel === el('dm-set-sector') ? '— Selecione —' : 'Todos os setores');
      sel.value = cur;
    });
  }

  // ---------------------------------------------------------------
  // Debounce
  // ---------------------------------------------------------------
  function debounce(fn, ms) {
    let t;
    return function (...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), ms);
    };
  }

  // ---------------------------------------------------------------
  // Boot
  // ---------------------------------------------------------------
  async function boot() {
    // Grab WordPress-injected config
    const cfg = window.wpDemandas;
    if (!cfg) return;

    api.base  = cfg.apiBase;
    api.nonce = cfg.nonce;

    state.user      = cfg.user;
    state.weekKey   = cfg.weekKey;
    state.dayOfWeek = cfg.dayOfWeek;

    if (!state.user) {
      // Not logged in – shortcode handles redirect, but just in case:
      window.location.href = cfg.loginUrl;
      return;
    }

    // Load global data in parallel
    try {
      const [users, sectors, recurringTypes] = await Promise.all([
        api.get('/users'),
        api.get('/sectors'),
        api.get('/recurring-types'),
      ]);
      state.users         = users;
      state.sectors       = sectors;
      state.recurringTypes = recurringTypes;
    } catch (e) {
      // Non-fatal – continue
    }

    // Update nav
    el('dm-nav-username').textContent  = state.user.name;
    el('dm-nav-avatar').textContent    = initials(state.user.name);
    el('dm-nav-week').textContent      = `Semana ${state.weekKey}`;
    el('dm-nav').style.display         = 'flex';
    const sideNav = el('dm-sidenav');
    if (sideNav) sideNav.style.display = 'flex';
    el('dm-main').style.display        = '';
    el('dm-loading').style.display     = 'none';

    // Populate sector selects
    populateSectorSelects();

    // Populate member filter
    if (state.user.is_manager) {
      populateSelect(el('dm-filter-member'), state.users, 'id', 'display_name', 'Todos os membros');
    }

    // Initialize all components
    initNavigation();
    initModals();
    initDragDrop();
    initTaskForm();
    initTransfer();
    initRoutineModal();
    initSettings();
    initBoardFilters();
    initBoardTabs();
    initDashboardFilters();

    // Show board first
    setView('board');

    // Auto-create routines if enabled
    if (state.user.auto_routines) {
      try {
        const result = await api.post('/tasks/routine', { titles: ['Rotina diária'] });
        if (result.created > 0) {
          toast('Rotinas do dia criadas automaticamente!', 'info');
          await loadBoard();
        }
      } catch {}
    }
  }

  // Start when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
