# BEAKON
> *Seu guia para o caminho certo*

**Documento de Levantamento de Requisitos**

| Campo | Valor |
|---|---|
| Projeto | Beakon |
| Versão | 1.0 |
| Data | 2025 |
| Responsável | Vinicio |
| Stack | PHP 8.4 + Symfony + PostgreSQL + React |
| Plataforma | Web (mobile em fase posterior) |

---

## 1. Visão Geral do Projeto

### 1.1 Descrição

Beakon é uma aplicação web de produtividade e autogestão desenvolvida especialmente para pessoas com TDAH (Transtorno do Déficit de Atenção com Hiperatividade). O nome remete à metáfora de um farol — uma luz que guia, orienta e mantém o usuário no caminho certo em direção aos seus objetivos.

O sistema atua como uma estrutura externa que substitui as funções executivas comprometidas pelo TDAH, como planejamento, iniciação de tarefas e regulação do tempo, oferecendo suporte contínuo e inteligente ao usuário.

### 1.2 Problema que Resolve

Pessoas com TDAH enfrentam desafios específicos que aplicativos de produtividade convencionais não abordam adequadamente:

- **Cegueira temporal:** dificuldade em perceber o tempo passando e planejar com antecedência
- **Dificuldade de iniciar tarefas** mesmo quando importantes
- **Inconsistência:** dias produtivos alternados com dias de paralisia total
- **Frustração intensa** quando expectativas não são atendidas
- **Necessidade de recompensas imediatas** para manter o engajamento

### 1.3 Objetivo

Desenvolver uma API RESTful robusta como base de um sistema de produtividade personalizado, com foco inicial no uso pessoal e potencial de evolução para produto SaaS.

### 1.4 Escopo da Versão 1.0

A versão inicial contempla exclusivamente o desenvolvimento da API backend, incluindo autenticação, gestão de tarefas, rotinas diárias, sessões Pomodoro e sistema de gamificação básico.

---

## 2. Requisitos Funcionais

### 2.1 Módulo de Autenticação

**RF-01 — Registro de Usuário**
O sistema deve permitir o cadastro de novos usuários com nome, e-mail e senha. A senha deve ser armazenada com hash seguro (bcrypt).

**RF-02 — Login**
O sistema deve autenticar usuários via e-mail e senha, retornando um token JWT válido por 24 horas.

**RF-03 — Refresh Token**
O sistema deve suportar renovação de token sem necessidade de novo login, mantendo a sessão ativa.

**RF-04 — Logout**
O sistema deve invalidar o token do usuário ao realizar logout.

---

### 2.2 Módulo de Tarefas

**RF-05 — Captura Rápida**
O sistema deve permitir criar uma tarefa com apenas o título, sem necessidade de informações adicionais. Tarefas criadas assim vão automaticamente para a inbox.

**RF-06 — CRUD de Tarefas**
O sistema deve suportar criação, leitura, atualização e exclusão de tarefas com os seguintes campos: título, descrição, status, prioridade, tempo estimado, data de vencimento e ordem.

**RF-07 — Status das Tarefas**
Cada tarefa deve ter um dos seguintes status: `inbox`, `today`, `week`, `backlog`, `done`. O usuário deve poder mover tarefas entre status.

**RF-08 — Prioridade**
Tarefas devem suportar três níveis de prioridade: `low`, `medium` e `high`.

**RF-09 — Reordenação**
O sistema deve permitir reordenar tarefas dentro de um mesmo status via campo de ordem numérica.

**RF-10 — Filtros e Listagem**
O sistema deve suportar listagem de tarefas filtradas por status, prioridade e data de vencimento, com paginação.

---

### 2.3 Módulo de Rotina Diária

**RF-11 — Criação de Rotina**
O sistema deve permitir criar itens de rotina com título, horário do dia, dias da semana (JSON array) e ordem de exibição.

**RF-12 — Ativação e Desativação**
Itens de rotina devem poder ser ativados ou desativados sem exclusão.

**RF-13 — Rotina do Dia**
O sistema deve retornar os itens de rotina do dia atual, filtrados pelos dias da semana configurados, ordenados por horário.

---

### 2.4 Módulo Pomodoro

**RF-14 — Iniciar Sessão**
O sistema deve registrar o início de uma sessão Pomodoro vinculada a uma tarefa, com timestamp de início.

**RF-15 — Finalizar Sessão**
O sistema deve registrar o fim de uma sessão, calculando a duração e marcando se foi completada ou interrompida.

**RF-16 — Histórico**
O sistema deve retornar o histórico de sessões Pomodoro por tarefa e por período de tempo.

**RF-17 — Estatísticas**
O sistema deve calcular o total de minutos focados por dia, semana e mês.

---

### 2.5 Módulo de Gamificação

**RF-18 — Sistema de XP**
O sistema deve atribuir pontos de experiência (XP) ao usuário ao completar tarefas. Tarefas com prioridade alta concedem mais XP que tarefas de baixa prioridade.

**RF-19 — Streak Diário**
O sistema deve registrar sequências de dias consecutivos com atividade, incrementando o `streak_days`. Se o usuário não registrar atividade em um dia, o streak é zerado.

**RF-20 — Conquistas**
O sistema deve suportar um catálogo de conquistas desbloqueáveis com base em ações do usuário (primeira tarefa concluída, 7 dias de streak, 100 pomodoros, etc.).

**RF-21 — Dashboard de Progresso**
O sistema deve retornar um resumo com XP total, streak atual, conquistas desbloqueadas e estatísticas de produtividade.

---

## 3. Requisitos Não Funcionais

| Código | Categoria | Descrição |
|---|---|---|
| RNF-01 | Performance | A API deve responder em até 300ms para 95% das requisições |
| RNF-02 | Segurança | Todas as rotas (exceto login/registro) devem exigir autenticação JWT |
| RNF-03 | Segurança | Senhas armazenadas exclusivamente com hash bcrypt (custo mínimo 12) |
| RNF-04 | Segurança | Proteção contra SQL Injection via Doctrine ORM parametrizado |
| RNF-05 | CORS | API deve aceitar requisições do frontend via CORS configurável |
| RNF-06 | Validação | Todos os inputs devem ser validados com mensagens de erro descritivas |
| RNF-07 | Padrão | Respostas em formato JSON com estrutura padronizada |
| RNF-08 | HTTP | Uso correto de status codes HTTP (200, 201, 400, 401, 403, 404, 422) |
| RNF-09 | Container | Ambiente de desenvolvimento 100% containerizado com Docker |
| RNF-10 | Docs | API documentada via Swagger/OpenAPI gerado automaticamente |

---

## 4. Mapa de Endpoints da API

### 4.1 Autenticação — `/api/auth`

| Método | Endpoint | Descrição |
|---|---|---|
| POST | `/api/auth/register` | Registro de novo usuário |
| POST | `/api/auth/login` | Login e geração de JWT |
| POST | `/api/auth/refresh` | Renovação do token JWT |
| POST | `/api/auth/logout` | Invalidação do token |

### 4.2 Tarefas — `/api/tasks`

| Método | Endpoint | Descrição |
|---|---|---|
| GET | `/api/tasks` | Listar tarefas com filtros |
| POST | `/api/tasks` | Criar tarefa (captura rápida ou completa) |
| GET | `/api/tasks/{id}` | Buscar tarefa por ID |
| PUT | `/api/tasks/{id}` | Atualizar tarefa completa |
| PATCH | `/api/tasks/{id}/status` | Alterar status da tarefa |
| PATCH | `/api/tasks/{id}/reorder` | Reordenar tarefa |
| DELETE | `/api/tasks/{id}` | Excluir tarefa |

### 4.3 Rotinas — `/api/routines`

| Método | Endpoint | Descrição |
|---|---|---|
| GET | `/api/routines` | Listar todas as rotinas |
| GET | `/api/routines/today` | Rotina do dia atual |
| POST | `/api/routines` | Criar item de rotina |
| PUT | `/api/routines/{id}` | Atualizar rotina |
| PATCH | `/api/routines/{id}/toggle` | Ativar/desativar rotina |
| DELETE | `/api/routines/{id}` | Excluir rotina |

### 4.4 Pomodoro — `/api/pomodoro`

| Método | Endpoint | Descrição |
|---|---|---|
| POST | `/api/pomodoro/start` | Iniciar sessão Pomodoro |
| PATCH | `/api/pomodoro/{id}/finish` | Finalizar sessão |
| GET | `/api/pomodoro/history` | Histórico de sessões |
| GET | `/api/pomodoro/stats` | Estatísticas de foco |

### 4.5 Gamificação — `/api/gamification`

| Método | Endpoint | Descrição |
|---|---|---|
| GET | `/api/gamification/dashboard` | Dashboard de progresso |
| GET | `/api/gamification/achievements` | Listar conquistas |
| GET | `/api/gamification/streak` | Streak atual do usuário |

---

## 5. Modelo de Dados

### 5.1 Entidades Principais

```sql
-- Usuários
users
  id UUID PRIMARY KEY
  name VARCHAR(255)
  email VARCHAR(255) UNIQUE
  password_hash VARCHAR(255)
  xp INTEGER DEFAULT 0
  streak_days INTEGER DEFAULT 0
  last_activity_date DATE
  created_at TIMESTAMP

-- Tarefas
tasks
  id UUID PRIMARY KEY
  user_id UUID REFERENCES users(id)
  title VARCHAR(255)
  description TEXT
  status ENUM('inbox','today','week','backlog','done')
  priority ENUM('low','medium','high') DEFAULT 'medium'
  estimated_minutes INTEGER
  due_date DATE
  order INTEGER
  created_at TIMESTAMP
  completed_at TIMESTAMP

-- Rotinas
routines
  id UUID PRIMARY KEY
  user_id UUID REFERENCES users(id)
  title VARCHAR(255)
  time_of_day TIME
  days_of_week JSON  -- ex: [1,2,3,4,5] para seg-sex
  is_active BOOLEAN DEFAULT true
  order INTEGER

-- Sessões Pomodoro
pomodoro_sessions
  id UUID PRIMARY KEY
  task_id UUID REFERENCES tasks(id)
  user_id UUID REFERENCES users(id)
  started_at TIMESTAMP
  finished_at TIMESTAMP
  duration_minutes INTEGER
  completed BOOLEAN DEFAULT false

-- Conquistas
achievements
  id UUID PRIMARY KEY
  key VARCHAR(100) UNIQUE
  title VARCHAR(255)
  description TEXT
  xp_reward INTEGER

-- Conquistas do Usuário
user_achievements
  id UUID PRIMARY KEY
  user_id UUID REFERENCES users(id)
  achievement_id UUID REFERENCES achievements(id)
  unlocked_at TIMESTAMP
```

### 5.2 Campos de Destaque

| Entidade | Campo | Tipo | Descrição |
|---|---|---|---|
| users | xp | INTEGER | Pontos de experiência acumulados |
| users | streak_days | INTEGER | Dias consecutivos de atividade |
| tasks | status | ENUM | inbox / today / week / backlog / done |
| tasks | priority | ENUM | low / medium / high |
| tasks | estimated_minutes | INTEGER | Tempo estimado em minutos |
| routines | days_of_week | JSON | Array de dias [0-6] onde 0=domingo |
| pomodoro_sessions | completed | BOOLEAN | Se a sessão foi completada ou interrompida |

---

## 6. Regras de Negócio

### 6.1 XP por Tarefa Concluída

| Prioridade | XP Base | Com Streak Ativo (3+ dias) |
|---|---|---|
| Low | 10 XP | 15 XP |
| Medium | 25 XP | 35 XP |
| High | 50 XP | 75 XP |

### 6.2 Streak

- O streak é incrementado quando o usuário conclui ao menos uma tarefa no dia
- O streak é zerado se não houver atividade por um dia completo
- Um scheduler rodará diariamente à meia-noite para verificar e resetar streaks inativos
- O bônus de XP com streak ativo se aplica a partir de 3 dias consecutivos

### 6.3 Captura Rápida

- Uma tarefa criada apenas com título recebe status `inbox` automaticamente
- Prioridade padrão é `medium` quando não informada
- Tarefas na inbox não aparecem na rotina diária até serem movidas para `today`

### 6.4 Conquistas Planejadas

| Conquista | Condição | XP Bônus |
|---|---|---|
| Primeira Luz | Completar primeira tarefa | 50 XP |
| Em Chamas | 7 dias de streak consecutivos | 200 XP |
| Maratonista | 25 dias de streak consecutivos | 500 XP |
| Focado | Completar 10 sessões Pomodoro | 100 XP |
| Mestre do Foco | Completar 100 sessões Pomodoro | 1000 XP |
| Inbox Zero | Mover todas as tarefas da inbox | 150 XP |
| Produtivo | Completar 10 tarefas em um dia | 300 XP |

---

## 7. Stack Tecnológica

| Camada | Tecnologia | Justificativa |
|---|---|---|
| Runtime | PHP 8.4 | Familiaridade do desenvolvedor, features modernas |
| Framework | Symfony 7 | Robusto, componentes modulares, ativo |
| ORM | Doctrine | Nativo do Symfony, migrations automáticas |
| Banco de Dados | PostgreSQL 16 | Confiável, suporte a JSON nativo, performance |
| Autenticação | JWT (LexikBundle) | Stateless, ideal para API REST |
| Containerização | Docker + Docker Compose | Ambiente reproduzível, isolado |
| Servidor Web | Nginx + PHP-FPM | Alta performance para APIs |
| Documentação | NelmioApiDocBundle | Swagger/OpenAPI automático |

---

## 8. Fases de Desenvolvimento

| Fase | Nome | Entregas | Status |
|---|---|---|---|
| 1 | Infraestrutura | Docker, Symfony, PostgreSQL, JWT, estrutura base | Em andamento |
| 2 | Tarefas | CRUD completo, captura rápida, filtros, reordenação | Pendente |
| 3 | Rotinas | Gestão de rotinas, endpoint /today | Pendente |
| 4 | Pomodoro | Sessões, histórico, estatísticas | Pendente |
| 5 | Gamificação | XP, streak, conquistas, dashboard | Pendente |
| 6 | Polimento | Testes, documentação Swagger, ajustes | Pendente |
| 7 | Frontend | Interface React + Tailwind | Futuro |
| 8 | Mobile | React Native | Futuro |

---

*Beakon v1.0 — Documento de Levantamento de Requisitos*
