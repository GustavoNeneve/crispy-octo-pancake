# WP Demandas – Gestão de Demandas de Marketing

Plugin WordPress para gestão semanal de demandas e tarefas de equipes de marketing.

---

## Funcionalidades

### Tipos de Demanda
| Cor    | Tipo                 | Quando criar                                                     |
|--------|----------------------|------------------------------------------------------------------|
| 🔵 Azul  | Rotina               | Toda vez que o usuário entra na plataforma (pode ser automático) |
| 🟡 Amarelo | Planejado / Recorrente | Criado na segunda-feira para a semana                          |
| 🩷 Rosa  | Urgente              | Criado no meio da semana ou acima da média recorrente            |

### Quadro Kanban (4 colunas)
- **Aguardando** → **Em Andamento** → **Em Aprovação** → **Concluído**

### Perfis de usuário
- **Gestor** – cria tarefas, aprova demandas, vê dashboard completo (todos os membros, estatísticas do setor)
- **Liderado** – vê e gerencia suas próprias tarefas, envia para aprovação, repassa para outros

### Recursos dos cards
- Título + descrição com suporte a imagens (URLs)
- Histórico completo de alterações
- Repasse entre usuários
- Fluxo de aprovação pelo gestor

### Dashboard (Gestores)
- Total por status: Aguardando, Em Andamento, Em Aprovação, Concluído
- Gráfico por tipo de demanda
- Tabela de estatísticas por liderado

### Reset Semanal (toda segunda-feira às 00:00)
- Demandas **concluídas** → arquivadas (salvas no histórico)
- Demandas **urgentes** em andamento/aguardando → rebaixadas para **planejadas**
- Demandas **aguardando/em andamento** → mantidas e atribuídas à nova semana

### Configurações
- Setor do usuário
- Criação automática de rotinas diárias
- Cadastro de tipos de demandas recorrentes + média semanal esperada
- Gerenciamento de setores (gestores)

---

## Instalação

1. Copie a pasta `wp-demandas/` para `wp-content/plugins/`
2. Ative o plugin no painel do WordPress (**Plugins → Plugins instalados**)
3. Crie uma página e insira o shortcode `[demandas_app]`
4. Atribua os papéis **Gestor de Demandas** ou **Liderado** aos usuários via **Usuários → Editar usuário**

---

## Papéis de usuário

| Papel WordPress    | Slug           | Permissões                       |
|--------------------|----------------|----------------------------------|
| Gestor de Demandas | `dm_manager`   | Criar/aprovar tarefas, dashboard |
| Liderado           | `dm_member`    | Gerir próprias tarefas           |
| Administrador      | `administrator`| Todas as permissões              |

---

## Shortcode

```
[demandas_app]
```

Insira este shortcode em qualquer página ou post do WordPress. O plugin carrega o app automaticamente e redireciona para o login se o usuário não estiver autenticado.

---

## Estrutura do Plugin

```
wp-demandas/
├── wp-demandas.php                  # Entry point, registra roles e funções globais
├── includes/
│   ├── class-database.php           # Criação das tabelas e helpers de DB
│   ├── class-rest-api.php           # Todos os endpoints REST (demandas/v1/*)
│   ├── class-cron.php               # Reset semanal toda segunda-feira 00:00
│   └── class-shortcode.php          # Shortcode [demandas_app]
├── assets/
│   ├── css/app.css                  # Estilos responsivos
│   └── js/app.js                    # SPA frontend (vanilla JS)
└── templates/
    └── app.php                      # HTML da aplicação
```

---

## Tabelas do banco de dados

| Tabela                      | Descrição                              |
|-----------------------------|----------------------------------------|
| `{prefix}dm_tasks`          | Tarefas/demandas                       |
| `{prefix}dm_task_history`   | Histórico de alterações por tarefa     |
| `{prefix}dm_recurring_types`| Tipos de demandas recorrentes          |
| `{prefix}dm_sectors`        | Setores da empresa                     |
| `{prefix}dm_user_settings`  | Configurações por usuário              |
| `{prefix}dm_weekly_snapshots`| Snapshots das tarefas ao fim da semana|

---

## API REST

**Namespace:** `demandas/v1`  
**Autenticação:** Cookie + nonce WordPress (`X-WP-Nonce`)

| Método | Rota                          | Descrição                              |
|--------|-------------------------------|----------------------------------------|
| GET    | `/tasks`                      | Listar demandas da semana              |
| POST   | `/tasks`                      | Criar demanda                          |
| GET    | `/tasks/{id}`                 | Detalhe de uma demanda                 |
| PUT    | `/tasks/{id}`                 | Atualizar demanda                      |
| DELETE | `/tasks/{id}`                 | Excluir demanda                        |
| POST   | `/tasks/{id}/status`          | Mover coluna (mudar status)            |
| POST   | `/tasks/{id}/approve`         | Aprovar demanda (gestores)             |
| POST   | `/tasks/{id}/transfer`        | Repassar para outro usuário            |
| GET    | `/tasks/{id}/history`         | Histórico de alterações                |
| POST   | `/tasks/routine`              | Criar rotinas do dia                   |
| GET    | `/dashboard`                  | Estatísticas (semana atual)            |
| GET    | `/sectors`                    | Listar setores                         |
| POST   | `/sectors`                    | Criar setor (gestor)                   |
| GET    | `/recurring-types`            | Listar tipos recorrentes (autocomplete)|
| POST   | `/recurring-types`            | Criar tipo recorrente                  |
| PUT    | `/recurring-types/{id}`       | Atualizar tipo recorrente              |
| DELETE | `/recurring-types/{id}`       | Remover tipo recorrente                |
| GET    | `/users`                      | Listar usuários do setor               |
| GET    | `/settings`                   | Configurações do usuário atual         |
| PUT    | `/settings`                   | Atualizar configurações                |
| GET    | `/weekly-history`             | Histórico de semanas anteriores        |

---

## Requisitos

- WordPress 5.8+
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+