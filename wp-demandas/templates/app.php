<?php defined( 'ABSPATH' ) || exit; ?>
<div id="dm-app" class="dm-app" role="main">
	<div id="dm-loading" class="dm-loading-screen" aria-live="polite">
		<div class="dm-spinner"></div>
		<p><?php esc_html_e( 'Carregando...', 'wp-demandas' ); ?></p>
	</div>

	<!-- Side Navigation -->
	<aside class="dm-sidenav" id="dm-sidenav" style="display:none">
		<div class="dm-sidenav-brand">
			<div class="dm-sidenav-logo">Marketing Atelier</div>
			<div class="dm-sidenav-subtitle">Demand Management</div>
		</div>
		<nav class="dm-sidenav-menu">
			<button class="dm-nav-link active dm-sidenav-link" data-view="board" type="button">
				<span class="material-symbols-outlined">view_kanban</span>
				<span>Kanban Board</span>
			</button>
			<button class="dm-nav-link dm-sidenav-link" data-view="dashboard" id="dm-sidenav-dashboard" type="button">
				<span class="material-symbols-outlined">dashboard</span>
				<span>Dashboard</span>
			</button>
			<button class="dm-nav-link dm-sidenav-link" data-view="settings" type="button">
				<span class="material-symbols-outlined">settings</span>
				<span>Configurations</span>
			</button>
		</nav>
		<div class="dm-sidenav-footer">
			<button class="dm-btn dm-btn-primary" id="dm-btn-new-task-side" type="button">
				<span class="material-symbols-outlined">add</span>
				Nova Demanda
			</button>
		</div>
	</aside>

	<!-- Top Navigation -->
	<nav class="dm-nav" id="dm-nav" style="display:none">
		<div class="dm-nav-brand">
			<span class="material-symbols-outlined dm-nav-icon">search</span>
			<input type="text" class="dm-nav-search" id="dm-nav-search" name="dm_nav_search" aria-label="Search campaigns, tasks, or leads" placeholder="e.g., Q1 Campaign">
			<span class="dm-nav-week" id="dm-nav-week"></span>
		</div>
		<div class="dm-nav-links" id="dm-nav-links">
			<button class="dm-nav-link active" data-view="board">
				<span>🗂</span> Quadro
			</button>
			<button class="dm-nav-link" data-view="dashboard" id="dm-nav-dashboard">
				<span>📊</span> Relatório
			</button>
			<button class="dm-nav-link" data-view="settings">
				<span>⚙</span> Configurações
			</button>
		</div>
		<div class="dm-nav-user" id="dm-nav-user">
			<span class="dm-avatar" id="dm-nav-avatar"></span>
			<span id="dm-nav-username"></span>
			<a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>" class="dm-logout-link">Sair</a>
		</div>
		<button class="dm-nav-mobile-toggle" id="dm-mobile-toggle" aria-label="Menu">☰</button>
	</nav>

	<!-- Mobile drawer -->
	<div class="dm-mobile-drawer" id="dm-mobile-drawer" style="display:none">
		<button class="dm-nav-link" data-view="board">🗂 Quadro</button>
		<button class="dm-nav-link" data-view="dashboard" id="dm-nav-dashboard-mob">📊 Relatório</button>
		<button class="dm-nav-link" data-view="settings">⚙ Configurações</button>
	</div>

	<!-- Main content -->
	<main class="dm-main" id="dm-main" style="display:none">

		<!-- ======= BOARD VIEW ======= -->
		<section id="dm-view-board" class="dm-view" style="display:none">
			<div class="dm-hero">
				<div>
					<h2 class="dm-view-title">Campaign Flow</h2>
					<p class="dm-hero-sub">Curating high-performance demand management.</p>
				</div>
				<div class="dm-hero-tags">
					<span class="dm-hero-tag"><i class="dm-dot dm-dot-blue"></i>Routine</span>
					<span class="dm-hero-tag"><i class="dm-dot dm-dot-yellow"></i>Planned</span>
					<span class="dm-hero-tag"><i class="dm-dot dm-dot-pink"></i>Urgent</span>
				</div>
			</div>
			<div class="dm-board-toolbar">
				<h3 class="dm-section-title">Quadro de Demandas</h3>
				<div class="dm-board-filters">
					<select id="dm-filter-sector" class="dm-select" aria-label="Filtrar por setor" style="display:none">
						<option value="">Todos os setores</option>
					</select>
					<select id="dm-filter-member" class="dm-select" aria-label="Filtrar por membro" style="display:none">
						<option value="">Todos os membros</option>
					</select>
				</div>
				<button class="dm-btn dm-btn-primary" id="dm-btn-new-task">+ Nova Demanda</button>
			</div>

			<div class="dm-routine-banner" id="dm-routine-banner" style="display:none">
				<span>📌 Você ainda não criou suas rotinas de hoje.</span>
				<button class="dm-btn dm-btn-sm dm-btn-outline" id="dm-btn-create-routines">Criar Rotinas</button>
				<button class="dm-btn dm-btn-sm dm-btn-ghost" id="dm-btn-dismiss-routines">Dispensar</button>
			</div>

			<div class="dm-board-tabs" id="dm-board-tabs" role="tablist" aria-label="Colunas do Kanban">
				<button class="dm-board-tab active" data-tab="waiting" role="tab" aria-selected="true" aria-controls="dm-col-waiting" type="button">
					⏳ Aguardando <span class="dm-board-tab-count" id="dm-tab-count-waiting">0</span>
				</button>
				<button class="dm-board-tab" data-tab="in_progress" role="tab" aria-selected="false" aria-controls="dm-col-in_progress" type="button">
					🔄 Em Andamento <span class="dm-board-tab-count" id="dm-tab-count-in_progress">0</span>
				</button>
				<button class="dm-board-tab" data-tab="in_approval" role="tab" aria-selected="false" aria-controls="dm-col-in_approval" type="button">
					👁 Em Aprovação <span class="dm-board-tab-count" id="dm-tab-count-in_approval">0</span>
				</button>
				<button class="dm-board-tab" data-tab="completed" role="tab" aria-selected="false" aria-controls="dm-col-completed" type="button">
					✅ Concluído <span class="dm-board-tab-count" id="dm-tab-count-completed">0</span>
				</button>
			</div>

			<div class="dm-board" id="dm-board">
				<div class="dm-column" data-status="waiting">
					<div class="dm-column-header dm-col-waiting">
						<span class="dm-col-icon">⏳</span>
						<h3>Aguardando</h3>
						<span class="dm-col-count" id="dm-count-waiting">0</span>
					</div>
					<div class="dm-column-body" id="dm-col-waiting" data-status="waiting"></div>
				</div>
				<div class="dm-column" data-status="in_progress">
					<div class="dm-column-header dm-col-inprogress">
						<span class="dm-col-icon">🔄</span>
						<h3>Em Andamento</h3>
						<span class="dm-col-count" id="dm-count-in_progress">0</span>
					</div>
					<div class="dm-column-body" id="dm-col-in_progress" data-status="in_progress"></div>
				</div>
				<div class="dm-column" data-status="in_approval">
					<div class="dm-column-header dm-col-approval">
						<span class="dm-col-icon">👁</span>
						<h3>Em Aprovação</h3>
						<span class="dm-col-count" id="dm-count-in_approval">0</span>
					</div>
					<div class="dm-column-body" id="dm-col-in_approval" data-status="in_approval"></div>
				</div>
				<div class="dm-column" data-status="completed">
					<div class="dm-column-header dm-col-completed">
						<span class="dm-col-icon">✅</span>
						<h3>Concluído</h3>
						<span class="dm-col-count" id="dm-count-completed">0</span>
					</div>
					<div class="dm-column-body" id="dm-col-completed" data-status="completed"></div>
				</div>
			</div>
		</section>

		<!-- ======= DASHBOARD VIEW ======= -->
		<section id="dm-view-dashboard" class="dm-view" style="display:none">
			<div class="dm-hero">
				<div>
					<h2 class="dm-view-title">Sector Pulse</h2>
					<p class="dm-hero-sub">Aggregated performance and demand flow across the creative studio.</p>
				</div>
			</div>
			<div class="dm-board-toolbar">
				<h3 class="dm-section-title">Relatório / Dashboard</h3>
				<div class="dm-board-filters">
					<select id="dm-dash-sector" class="dm-select" aria-label="Filtrar setor">
						<option value="">Todos os setores</option>
					</select>
					<select id="dm-dash-week" class="dm-select" aria-label="Semana">
						<option value="">Semana atual</option>
					</select>
				</div>
			</div>
			<div id="dm-dashboard-content" class="dm-dashboard-grid"></div>
			<div class="dm-dashboard-charts" id="dm-dashboard-charts" style="display:none">
				<div class="dm-dashboard-section dm-chart-wrap">
					<h4><?php esc_html_e( 'Distribuição por Tipo', 'wp-demandas' ); ?></h4>
					<canvas id="dm-chart-type" aria-label="<?php esc_attr_e( 'Gráfico de pizza: distribuição por tipo de demanda', 'wp-demandas' ); ?>" role="img"></canvas>
				</div>
				<div class="dm-dashboard-section dm-chart-wrap" id="dm-chart-member-wrap" style="display:none">
					<h4><?php esc_html_e( 'Concluídas por Membro', 'wp-demandas' ); ?></h4>
					<canvas id="dm-chart-member" aria-label="<?php esc_attr_e( 'Gráfico de barras: tarefas concluídas por membro', 'wp-demandas' ); ?>" role="img"></canvas>
				</div>
			</div>
		</section>

		<!-- ======= SETTINGS VIEW ======= -->
		<section id="dm-view-settings" class="dm-view" style="display:none">
			<div class="dm-hero">
				<div>
					<h2 class="dm-view-title">Configurations</h2>
					<p class="dm-hero-sub">Manage recurrent demands, routine automation, and operational setup.</p>
				</div>
			</div>
			<div class="dm-board-toolbar">
				<h3 class="dm-section-title">Configurações</h3>
			</div>
			<div class="dm-settings-layout">
				<div class="dm-settings-card">
					<h3>Minhas Configurações</h3>
					<form id="dm-form-user-settings">
						<div class="dm-form-group">
							<label for="dm-set-sector">Setor</label>
							<select id="dm-set-sector" name="sector_id" class="dm-select dm-full-width">
								<option value="0">— Selecione —</option>
							</select>
						</div>
						<div class="dm-form-group">
							<label class="dm-checkbox-label">
								<input type="checkbox" id="dm-set-auto-routines" name="auto_create_routines">
								Criar rotinas automaticamente toda segunda
							</label>
						</div>
						<div class="dm-form-group" id="dm-routine-titles-group">
							<label>Títulos das Rotinas (uma por linha)</label>
							<textarea id="dm-set-routine-titles" class="dm-textarea" rows="4" placeholder="Rotina diária&#10;Revisão de posts&#10;..."></textarea>
						</div>
						<button type="submit" class="dm-btn dm-btn-primary">Salvar Configurações</button>
					</form>
				</div>

				<div class="dm-settings-card" id="dm-recurring-card">
					<h3>Demandas Recorrentes</h3>
					<p class="dm-text-muted">Cadastre os tipos de demandas recorrentes do seu setor e a média semanal esperada.</p>
					<div id="dm-recurring-list" class="dm-recurring-list"></div>
					<form id="dm-form-add-recurring" class="dm-inline-form">
						<input type="text" id="dm-rec-name" placeholder="Nome da demanda recorrente" class="dm-input dm-flex-1" autocomplete="off">
						<input type="number" id="dm-rec-avg" placeholder="Média/semana" class="dm-input dm-input-sm" min="0.5" step="0.5" value="1">
						<button type="submit" class="dm-btn dm-btn-primary">Adicionar</button>
					</form>
				</div>

				<div class="dm-settings-card dm-settings-card-full" id="dm-sectors-card" style="display:none">
					<h3>Setores <small>(Gestores)</small></h3>
					<div id="dm-sectors-list" class="dm-recurring-list"></div>
					<form id="dm-form-add-sector" class="dm-inline-form">
						<input type="text" id="dm-sec-name" placeholder="Nome do setor" class="dm-input dm-flex-1">
						<button type="submit" class="dm-btn dm-btn-primary">Criar Setor</button>
					</form>
				</div>
			</div>
		</section>

	</main>

	<!-- ======= MODAL: Task Form ======= -->
	<div class="dm-modal-overlay" id="dm-modal-task" style="display:none" role="dialog" aria-modal="true" aria-labelledby="dm-modal-task-title">
		<div class="dm-modal">
			<div class="dm-modal-header">
				<h3 id="dm-modal-task-title">Nova Demanda</h3>
				<button class="dm-modal-close" data-close="dm-modal-task" aria-label="Fechar">×</button>
			</div>
			<div class="dm-modal-body">
				<form id="dm-form-task">
					<input type="hidden" id="dm-task-id" value="">
					<div class="dm-form-row">
						<div class="dm-form-group dm-flex-2">
							<label for="dm-task-title">Título *</label>
							<input type="text" id="dm-task-title" class="dm-input" required placeholder="Título da demanda" autocomplete="off">
							<ul id="dm-title-autocomplete" class="dm-autocomplete" style="display:none"></ul>
						</div>
						<div class="dm-form-group">
							<label for="dm-task-type">Tipo</label>
							<select id="dm-task-type" class="dm-select">
								<option value="planned">🟡 Planejado</option>
								<option value="routine">🔵 Rotina</option>
								<option value="urgent">🩷 Urgente</option>
								<option value="planned_recurring">🟡 Planejado Recorrente</option>
							</select>
						</div>
					</div>

					<div class="dm-form-group" id="dm-recurring-type-group" style="display:none">
						<label for="dm-task-recurring-type">Tipo Recorrente</label>
						<select id="dm-task-recurring-type" class="dm-select">
							<option value="">— Selecione —</option>
						</select>
					</div>

					<div class="dm-form-group">
						<label for="dm-task-description">Descrição</label>
						<textarea id="dm-task-description" class="dm-textarea" rows="4" placeholder="Descrição, briefing, instruções..."></textarea>
					</div>

					<div class="dm-form-group" id="dm-task-assignee-group" style="display:none">
						<label for="dm-task-assignee">Responsável</label>
						<select id="dm-task-assignee" class="dm-select">
							<option value="">— Eu mesmo —</option>
						</select>
					</div>

					<div class="dm-form-group">
						<label>Imagens</label>
						<div class="dm-image-upload" id="dm-image-upload">
							<button type="button" class="dm-btn dm-btn-outline dm-btn-sm" id="dm-btn-add-image">📷 Adicionar Imagem (URL)</button>
							<div id="dm-image-list" class="dm-image-list"></div>
						</div>
					</div>

					<div class="dm-modal-actions">
						<button type="button" class="dm-btn dm-btn-ghost" data-close="dm-modal-task">Cancelar</button>
						<button type="submit" class="dm-btn dm-btn-primary" id="dm-task-submit">Salvar Demanda</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<!-- ======= MODAL: Task Detail ======= -->
	<div class="dm-modal-overlay" id="dm-modal-detail" style="display:none" role="dialog" aria-modal="true" aria-labelledby="dm-modal-detail-title">
		<div class="dm-modal dm-modal-wide">
			<div class="dm-modal-header">
				<div class="dm-modal-title-row">
					<span class="dm-task-type-badge" id="dm-detail-type-badge"></span>
					<h3 id="dm-modal-detail-title">Demanda</h3>
				</div>
				<button class="dm-modal-close" data-close="dm-modal-detail" aria-label="Fechar">×</button>
			</div>
			<div class="dm-modal-body dm-detail-body">
				<div class="dm-detail-main">
					<div id="dm-detail-description" class="dm-detail-description"></div>
					<div id="dm-detail-images" class="dm-detail-images"></div>
					<div class="dm-detail-meta">
						<span><strong>Responsável:</strong> <span id="dm-detail-assignee"></span></span>
						<span><strong>Criado por:</strong> <span id="dm-detail-creator"></span></span>
						<span><strong>Status:</strong> <span id="dm-detail-status-label"></span></span>
						<span><strong>Semana:</strong> <span id="dm-detail-week"></span></span>
					</div>
					<div class="dm-detail-actions" id="dm-detail-actions"></div>
				</div>
				<div class="dm-detail-history">
					<h4>Histórico de Alterações</h4>
					<div id="dm-detail-history-list" class="dm-history-list"></div>
				</div>
			</div>
		</div>
	</div>

	<!-- ======= MODAL: Transfer Task ======= -->
	<div class="dm-modal-overlay" id="dm-modal-transfer" style="display:none" role="dialog" aria-modal="true" aria-labelledby="dm-modal-transfer-title">
		<div class="dm-modal">
			<div class="dm-modal-header">
				<h3 id="dm-modal-transfer-title">Repassar Demanda</h3>
				<button class="dm-modal-close" data-close="dm-modal-transfer" aria-label="Fechar">×</button>
			</div>
			<div class="dm-modal-body">
				<input type="hidden" id="dm-transfer-task-id">
				<div class="dm-form-group">
					<label for="dm-transfer-user">Repassar para</label>
					<select id="dm-transfer-user" class="dm-select dm-full-width">
						<option value="">— Selecione o usuário —</option>
					</select>
				</div>
				<div class="dm-form-group">
					<label for="dm-transfer-note">Observação</label>
					<textarea id="dm-transfer-note" class="dm-textarea" rows="3" placeholder="Motivo ou instruções..."></textarea>
				</div>
				<div class="dm-modal-actions">
					<button type="button" class="dm-btn dm-btn-ghost" data-close="dm-modal-transfer">Cancelar</button>
					<button type="button" class="dm-btn dm-btn-primary" id="dm-btn-confirm-transfer">Repassar</button>
				</div>
			</div>
		</div>
	</div>

	<!-- ======= MODAL: Routine Creation ======= -->
	<div class="dm-modal-overlay" id="dm-modal-routines" style="display:none" role="dialog" aria-modal="true" aria-labelledby="dm-modal-routines-title">
		<div class="dm-modal">
			<div class="dm-modal-header">
				<h3 id="dm-modal-routines-title">Criar Rotinas de Hoje</h3>
				<button class="dm-modal-close" data-close="dm-modal-routines" aria-label="Fechar">×</button>
			</div>
			<div class="dm-modal-body">
				<p>Adicione os títulos das rotinas que deseja criar para hoje:</p>
				<div id="dm-routine-inputs">
					<input type="text" class="dm-input dm-routine-input dm-full-width" placeholder="Ex: Revisão de posts" style="margin-bottom:8px">
				</div>
				<button type="button" class="dm-btn dm-btn-outline dm-btn-sm" id="dm-btn-add-routine-input">+ Adicionar mais</button>
				<div class="dm-modal-actions" style="margin-top:16px">
					<button type="button" class="dm-btn dm-btn-ghost" data-close="dm-modal-routines">Cancelar</button>
					<button type="button" class="dm-btn dm-btn-primary" id="dm-btn-confirm-routines">Criar Rotinas</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Toast notifications -->
	<div id="dm-toast-container" aria-live="polite" aria-atomic="true"></div>
</div>
