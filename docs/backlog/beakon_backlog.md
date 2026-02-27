# BEAKON — Product Backlog
> Versão 1.0 | Atualizado em 2025

---

## Legenda

| Símbolo | Significado |
|---|---|
| 🔴 Alta | Prioridade alta — bloqueante para outras histórias |
| 🟡 Média | Prioridade média — importante mas não bloqueante |
| 🟢 Baixa | Prioridade baixa — nice to have na versão atual |
| 🔵 Futuro | Planejado para versões posteriores |
| ⬜ Pendente | Não iniciado |
| 🔄 Em andamento | Em desenvolvimento |
| ✅ Concluído | Finalizado e validado |

---

## ÉPICO 1 — Infraestrutura e Setup

> Preparar o ambiente de desenvolvimento e a base do projeto Symfony.

---

### US-01 — Setup do ambiente Docker
**Prioridade:** 🔴 Alta | **Status:** 🔄 Em andamento | **Estimativa:** 3 pontos

**Como** desenvolvedor,
**quero** ter um ambiente Docker funcional com PHP, Nginx e PostgreSQL,
**para que** eu possa desenvolver sem dependências locais.

**Critérios de aceitação:**
- [ ] Container PHP 8.4-fpm rodando
- [ ] Container Nginx servindo na porta 8080
- [ ] Container PostgreSQL 16 rodando na porta 5432
- [ ] Volumes configurados para persistência de dados
- [ ] Rede interna `beakon-network` conectando todos os serviços
- [ ] Comando `docker-compose up -d` sobe tudo sem erros

---

### US-02 — Instalação e configuração do Symfony
**Prioridade:** 🔴 Alta | **Status:** ⬜ Pendente | **Estimativa:** 2 pontos

**Como** desenvolvedor,
**quero** ter o projeto Symfony instalado e configurado,
**para que** eu possa começar a desenvolver os módulos da API.

**Critérios de aceitação:**
- [ ] Projeto Symfony criado com `symfony/skeleton`
- [ ] Pacotes essenciais instalados (orm-pack, security-bundle, maker-bundle)
- [ ] Variáveis de ambiente configuradas no `.env`
- [ ] Conexão com PostgreSQL validada via Doctrine
- [ ] Endpoint GET `/api/health` retornando `200 OK`

---

### US-03 — Configuração do Doctrine e migrations
**Prioridade:** 🔴 Alta | **Status:** ⬜ Pendente | **Estimativa:** 2 pontos

**Como** desenvolvedor,
**quero** ter o Doctrine configurado com sistema de migrations,
**para que** eu possa versionar as mudanças no banco de dados.

**Critérios de aceitação:**
- [ ] Doctrine configurado apontando para o PostgreSQL
- [ ] Comando `doctrine:migrations:migrate` executando sem erros
- [ ] Migration inicial criando as tabelas base
- [ ] UUID como tipo padrão de primary key configurado

---

### US-04 — Padronização de resposta da API
**Prioridade:** 🔴 Alta | **Status:** ⬜ Pendente | **Estimativa:** 2 pontos

**Como** desenvolvedor,
**quero** ter uma estrutura padronizada de resposta JSON,
**para que** o frontend consuma a API de forma consistente.

**Critérios de aceitação:**
- [ ] Respostas de sucesso no formato `{ data: {}, message: "" }`
- [ ] Respostas de erro no formato `{ error: "", code: 400 }`
- [ ] Exception listener global tratando erros não capturados
- [ ] Status HTTP corretos em todos os endpoints

---

## ÉPICO 2 — Autenticação

> Sistema de login, registro e controle de sessão via JWT.

---

### US-05 — Registro de usuário
**Prioridade:** 🔴 Alta | **Status:** ⬜ Pendente | **Estimativa:** 3 pontos

**Como** novo usuário,
**quero** me cadastrar com nome, e-mail e senha,
**para que** eu possa acessar o Beakon.

**Critérios de aceitação:**
- [ ] POST `/api/auth/register` aceita `name`, `email`, `password`
- [ ] Validação: e-mail único, senha mínimo 8 caracteres
- [ ] Senha armazenada com bcrypt (custo 12)
- [ ] Retorna dados do usuário criado (sem a senha)
- [ ] Retorna `201 Created` em sucesso
- [ ] Retorna `422 Unprocessable Entity` com mensagens de validação em erro

---

### US-06 — Login e geração de JWT
**Prioridade:** 🔴 Alta | **Status:** ⬜ Pendente | **Estimativa:** 3 pontos

**Como** usuário cadastrado,
**quero** fazer login com e-mail e senha,
**para que** eu receba um token JWT para acessar a API.

**Critérios de aceitação:**
- [ ] POST `/api/auth/login` aceita `email` e `password`
- [ ] Retorna `access_token` (expira em 24h) e `refresh_token` (expira em 7 dias)
- [ ] Retorna `401 Unauthorized` para credenciais inválidas
- [ ] Token JWT contém `user_id` e `email` no payload

---

### US-07 — Refresh token
**Prioridade:** 🟡 Média | **Status:** ⬜ Pendente | **Estimativa:** 2 pontos

**Como** usuário autenticado,
**quero** renovar meu token sem precisar fazer login novamente,
**para que** minha sessão permaneça ativa.

**Critérios de aceitação:**
- [ ] POST `/api/auth/refresh` aceita `refresh_token`
- [ ] Retorna novo `access_token` válido
- [ ] Retorna `401` para refresh token inválido ou expirado
- [ ] Refresh token anterior é invalidado após uso

---

### US-08 — Logout
**Prioridade:** 🟡 Média | **Status:** ⬜ Pendente | **Estimativa:** 1 ponto

**Como** usuário autenticado,
**quero** fazer logout,
**para que** meu token seja invalidado com segurança.

**Critérios de aceitação:**
- [ ] POST `/api/auth/logout` invalida o token atual
- [ ] Retorna `200 OK`
- [ ] Token invalidado não pode ser reutilizado

---

## ÉPICO 3 — Gestão de Tarefas

> Núcleo da aplicação — criar, organizar e concluir tarefas.

---

### US-09 — Captura rápida de tarefa
**Prioridade:** 🔴 Alta | **Status:** ⬜ Pendente | **Estimativa:** 2 pontos

**Como** usuário com TDAH,
**quero** criar uma tarefa informando apenas o título,
**para que** eu possa capturar ideias rapidamente sem perder o foco.

**Critérios de aceitação:**
- [ ] POST `/api/tasks` aceita apenas `title` como campo obrigatório
- [ ] Status padrão definido como `inbox`
- [ ] Prioridade padrão definida como `medium`
- [ ] Retorna `201 Created` com a tarefa criada

---

### US-10 — Criar tarefa completa
**Prioridade:** 🔴 Alta | **Status:** ⬜ Pendente | **Estimativa:** 3 pontos

**Como** usuário,
**quero** criar uma tarefa com todos os detalhes,
**para que** eu tenha controle completo sobre o planejamento.

**Critérios de aceitação:**
- [ ] POST `/api/tasks` aceita `title`, `description`, `status`, `priority`, `estimated_minutes`, `due_date`
- [ ] Validação de campos: status e priority apenas valores permitidos
- [ ] `due_date` aceita formato ISO 8601
- [ ] Tarefa criada vinculada ao usuário autenticado

---

### US-11 — Listar tarefas com filtros
**Prioridade:** 🔴 Alta | **Status:** ⬜ Pendente | **Estimativa:** 3 pontos

**Como** usuário,
**quero** listar minhas tarefas com filtros,
**para que** eu visualize apenas o que é relevante no momento.

**Critérios de aceitação:**
- [ ] GET `/api/tasks` retorna tarefas do usuário autenticado
- [ ] Filtro por `status` (query param)
- [ ] Filtro por `priority` (query param)
- [ ] Filtro por `due_date` (query param)
- [ ] Resultado ordenado por `order` asc
- [ ] Paginação com `page` e `per_page` (padrão 20)

---

### US-12 — Atualizar tarefa
**Prioridade:** 🔴 Alta | **Status:** ⬜ Pendente | **Estimativa:** 2 pontos

**Como** usuário,
**quero** editar os dados de uma tarefa,
**para que** eu mantenha as informações atualizadas.

**Critérios de aceitação:**
- [ ] PUT `/api/tasks/{id}` atualiza todos os campos
- [ ] Retorna `404` para tarefa não encontrada
- [ ] Retorna `403` se a tarefa pertence a outro usuário
- [ ] Retorna tarefa atualizada em caso de sucesso

---

### US-13 — Alterar status da tarefa
**Prioridade:** 🔴 Alta | **Status:** ⬜ Pendente | **Estimativa:** 2 pontos

**Como** usuário,
**quero** mover uma tarefa entre os status,
**para que** eu organize meu fluxo de trabalho.

**Critérios de aceitação:**
- [ ] PATCH `/api/tasks/{id}/status` aceita `{ status: "today" }`
- [ ] Ao mover para `done`, registra `completed_at` com timestamp atual
- [ ] Ao mover de `done` para outro status, limpa `completed_at`
- [ ] Dispara evento de XP quando status muda para `done`

---

### US-14 — Excluir tarefa
**Prioridade:** 🟡 Média | **Status:** ⬜ Pendente | **Estimativa:** 1 ponto

**Como** usuário,
**quero** excluir uma tarefa,
**para que** eu remova itens que não são mais relevantes.

**Critérios de aceitação:**
- [ ] DELETE `/api/tasks/{id}` remove a tarefa
- [ ] Retorna `204 No Content`
- [ ] Retorna `403` para tarefas de outro usuário
- [ ] Sessões Pomodoro vinculadas são mantidas no histórico

---

### US-15 — Reordenar tarefas
**Prioridade:** 🟡 Média | **Status:** ⬜ Pendente | **Estimativa:** 2 pontos

**Como** usuário,
**quero** reordenar tarefas dentro de um mesmo status,
**para que** eu priorize visualmente o que fazer primeiro.

**Critérios de aceitação:**
- [ ] PATCH `/api/tasks/{id}/reorder` aceita `{ order: 3 }`
- [ ] Reordena as outras tarefas do mesmo status automaticamente
- [ ] Retorna lista reordenada

---

## ÉPICO 4 — Rotina Diária

> Estrutura fixa de atividades que guia o usuário ao longo do dia.

---

### US-16 — Criar item de rotina
**Prioridade:** 🔴 Alta | **Status:** ⬜ Pendente | **Estimativa:** 2 pontos

**Como** usuário,
**quero** criar itens fixos na minha rotina diária,
**para que** eu tenha um guia estruturado para o dia.

**Critérios de aceitação:**
- [ ] POST `/api/routines` aceita `title`, `time_of_day`, `days_of_week`, `order`
- [ ] `days_of_week` é array de inteiros de 0 a 6 (0=domingo)
- [ ] `time_of_day` aceita formato `HH:MM`
- [ ] Item criado com `is_active: true` por padrão

---

### US-17 — Rotina do dia atual
**Prioridade:** 🔴 Alta | **Status:** ⬜ Pendente | **Estimativa:** 2 pontos

**Como** usuário,
**quero** ver apenas os itens de rotina do dia de hoje,
**para que** eu saiba exatamente o que fazer sem precisar filtrar.

**Critérios de aceitação:**
- [ ] GET `/api/routines/today` retorna itens do dia atual
- [ ] Filtra por `days_of_week` contendo o dia da semana atual
- [ ] Filtra apenas itens com `is_active: true`
- [ ] Ordenado por `time_of_day` asc

---

### US-18 — Ativar e desativar rotina
**Prioridade:** 🟡 Média | **Status:** ⬜ Pendente | **Estimativa:** 1 ponto

**Como** usuário,
**quero** pausar um item de rotina sem excluí-lo,
**para que** eu possa reativá-lo depois.

**Critérios de aceitação:**
- [ ] PATCH `/api/routines/{id}/toggle` alterna `is_active`
- [ ] Retorna o item atualizado com o novo valor de `is_active`

---

### US-19 — Editar e excluir rotina
**Prioridade:** 🟡 Média | **Status:** ⬜ Pendente | **Estimativa:** 2 pontos

**Como** usuário,
**quero** editar ou excluir itens da minha rotina,
**para que** eu adapte a rotina conforme minha vida muda.

**Critérios de aceitação:**
- [ ] PUT `/api/routines/{id}` atualiza todos os campos
- [ ] DELETE `/api/routines/{id}` remove o item
- [ ] Retorna `403` para itens de outro usuário

---

## ÉPICO 5 — Pomodoro

> Sessões de foco cronometradas vinculadas a tarefas.

---

### US-20 — Iniciar sessão Pomodoro
**Prioridade:** 🔴 Alta | **Status:** ⬜ Pendente | **Estimativa:** 2 pontos

**Como** usuário,
**quero** iniciar uma sessão Pomodoro vinculada a uma tarefa,
**para que** eu registre meu tempo de foco.

**Critérios de aceitação:**
- [ ] POST `/api/pomodoro/start` aceita `{ task_id: "uuid" }`
- [ ] Registra `started_at` com timestamp atual
- [ ] Impede iniciar nova sessão se já existe uma ativa
- [ ] Retorna sessão criada com `201 Created`

---

### US-21 — Finalizar sessão Pomodoro
**Prioridade:** 🔴 Alta | **Status:** ⬜ Pendente | **Estimativa:** 2 pontos

**Como** usuário,
**quero** finalizar uma sessão Pomodoro,
**para que** eu registre se completei ou interrompi o ciclo.

**Critérios de aceitação:**
- [ ] PATCH `/api/pomodoro/{id}/finish` aceita `{ completed: true|false }`
- [ ] Registra `finished_at` e calcula `duration_minutes`
- [ ] Retorna sessão finalizada

---

### US-22 — Histórico de sessões
**Prioridade:** 🟡 Média | **Status:** ⬜ Pendente | **Estimativa:** 2 pontos

**Como** usuário,
**quero** ver o histórico de minhas sessões Pomodoro,
**para que** eu acompanhe meu padrão de foco ao longo do tempo.

**Critérios de aceitação:**
- [ ] GET `/api/pomodoro/history` retorna sessões do usuário
- [ ] Filtro por `task_id` (query param)
- [ ] Filtro por período `date_from` e `date_to` (query param)
- [ ] Paginado com `page` e `per_page`

---

### US-23 — Estatísticas de foco
**Prioridade:** 🟡 Média | **Status:** ⬜ Pendente | **Estimativa:** 3 pontos

**Como** usuário,
**quero** ver estatísticas do meu tempo de foco,
**para que** eu entenda minha produtividade ao longo do tempo.

**Critérios de aceitação:**
- [ ] GET `/api/pomodoro/stats` retorna:
  - Total de minutos focados hoje
  - Total de minutos focados na semana
  - Total de minutos focados no mês
  - Total de sessões completadas vs interrompidas
  - Média de sessões por dia na semana

---

## ÉPICO 6 — Gamificação

> Sistema de XP, streak e conquistas para manter o engajamento.

---

### US-24 — Atribuição de XP ao concluir tarefa
**Prioridade:** 🔴 Alta | **Status:** ⬜ Pendente | **Estimativa:** 3 pontos

**Como** usuário,
**quero** ganhar XP ao concluir tarefas,
**para que** eu sinta progresso e motivação contínua.

**Critérios de aceitação:**
- [ ] XP atribuído automaticamente ao mover tarefa para `done`
- [ ] Tabela de XP: low=10, medium=25, high=50
- [ ] Bônus de 50% aplicado se streak ativo (3+ dias)
- [ ] XP acumulado no campo `xp` do usuário
- [ ] Evento registrado para histórico futuro

---

### US-25 — Sistema de streak diário
**Prioridade:** 🔴 Alta | **Status:** ⬜ Pendente | **Estimativa:** 3 pontos

**Como** usuário,
**quero** manter uma sequência diária de atividade,
**para que** eu seja recompensado pela consistência.

**Critérios de aceitação:**
- [ ] `streak_days` incrementado ao concluir primeira tarefa do dia
- [ ] `last_activity_date` atualizado com a data atual
- [ ] Scheduler diário (meia-noite) verifica e zera streaks inativos
- [ ] Streak zerado se `last_activity_date` for anterior a ontem

---

### US-26 — Sistema de conquistas
**Prioridade:** 🟡 Média | **Status:** ⬜ Pendente | **Estimativa:** 4 pontos

**Como** usuário,
**quero** desbloquear conquistas por marcos atingidos,
**para que** eu tenha metas adicionais que tornem a experiência mais engajante.

**Critérios de aceitação:**
- [ ] Tabela `achievements` populada com conquistas iniciais
- [ ] Verificação de conquistas disparada após cada ação relevante
- [ ] Conquista desbloqueada apenas uma vez por usuário
- [ ] XP bônus creditado ao desbloquear conquista
- [ ] GET `/api/gamification/achievements` lista todas com status (bloqueada/desbloqueada)

---

### US-27 — Dashboard de progresso
**Prioridade:** 🟡 Média | **Status:** ⬜ Pendente | **Estimativa:** 2 pontos

**Como** usuário,
**quero** ver um resumo do meu progresso,
**para que** eu acompanhe minha evolução de forma rápida.

**Critérios de aceitação:**
- [ ] GET `/api/gamification/dashboard` retorna:
  - XP total acumulado
  - Streak atual em dias
  - Número de conquistas desbloqueadas / total
  - Tarefas concluídas hoje
  - Minutos focados hoje
  - Últimas 3 conquistas desbloqueadas

---

### US-28 — Endpoint de streak
**Prioridade:** 🟢 Baixa | **Status:** ⬜ Pendente | **Estimativa:** 1 ponto

**Como** usuário,
**quero** consultar meu streak atual,
**para que** eu saiba quantos dias consecutivos estou ativo.

**Critérios de aceitação:**
- [ ] GET `/api/gamification/streak` retorna `streak_days` e `last_activity_date`

---

## ÉPICO 7 — Qualidade e Documentação

> Testes, documentação e polimento da API.

---

### US-29 — Documentação Swagger
**Prioridade:** 🟡 Média | **Status:** ⬜ Pendente | **Estimativa:** 3 pontos

**Como** desenvolvedor,
**quero** ter a API documentada automaticamente via Swagger,
**para que** eu possa testar e consultar endpoints facilmente.

**Critérios de aceitação:**
- [ ] NelmioApiDocBundle instalado e configurado
- [ ] Todos os endpoints documentados com anotações
- [ ] Swagger UI acessível em `/api/docs`
- [ ] Schemas de request e response documentados

---

### US-30 — Testes de integração dos endpoints principais
**Prioridade:** 🟡 Média | **Status:** ⬜ Pendente | **Estimativa:** 5 pontos

**Como** desenvolvedor,
**quero** ter testes automatizados para os fluxos principais,
**para que** eu tenha segurança ao fazer alterações.

**Critérios de aceitação:**
- [ ] PHPUnit configurado no projeto
- [ ] Banco de dados de teste isolado
- [ ] Testes para: registro, login, CRUD de tarefas, Pomodoro, XP
- [ ] Todos os testes passando no `docker-compose exec php bin/phpunit`

---

## ÉPICO 8 — Futuro (Versões Posteriores)

> Funcionalidades planejadas para após o MVP.

---

### US-31 — Interface web (React + Tailwind)
**Prioridade:** 🔵 Futuro | **Status:** ⬜ Pendente

Frontend completo consumindo a API, com timer visual Pomodoro, kanban de tarefas e dashboard de gamificação.

---

### US-32 — Aplicativo mobile (React Native)
**Prioridade:** 🔵 Futuro | **Status:** ⬜ Pendente

Versão mobile com notificações push para lembretes de rotina e início de sessões Pomodoro.

---

### US-33 — Notificações e lembretes
**Prioridade:** 🔵 Futuro | **Status:** ⬜ Pendente

Sistema de notificações por e-mail ou push para lembrar o usuário de itens de rotina e tarefas com prazo próximo.

---

### US-34 — Relatórios semanais
**Prioridade:** 🔵 Futuro | **Status:** ⬜ Pendente

Relatório semanal automático com resumo de produtividade, enviado por e-mail.

---

### US-35 — Multi-usuário / SaaS
**Prioridade:** 🔵 Futuro | **Status:** ⬜ Pendente

Evolução para produto SaaS com planos, billing e onboarding para novos usuários.

---

## Resumo do Backlog

| Épico | Total de Histórias | Pontos Estimados | Status |
|---|---|---|---|
| 1 — Infraestrutura | 4 | 9 pts | 🔄 Em andamento |
| 2 — Autenticação | 4 | 9 pts | ⬜ Pendente |
| 3 — Tarefas | 7 | 15 pts | ⬜ Pendente |
| 4 — Rotina Diária | 4 | 7 pts | ⬜ Pendente |
| 5 — Pomodoro | 4 | 9 pts | ⬜ Pendente |
| 6 — Gamificação | 5 | 13 pts | ⬜ Pendente |
| 7 — Qualidade | 2 | 8 pts | ⬜ Pendente |
| 8 — Futuro | 5 | — | 🔵 Futuro |
| **Total MVP** | **30** | **70 pts** | |

---

*Beakon v1.0 — Product Backlog*
