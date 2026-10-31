# Arquitetura — hubtcc (Symfony + PostgreSQL + Docker)

Documento de referência para a migração do backend NestJS/Prisma/MySQL para Symfony/Doctrine/PostgreSQL. Define **o que** será construído e **por quê**, antes de escrever código.

> Status: decisões fechadas · Migração concluída: fases 0 a 8 (Fase 6 sem e-mail) · Versão 10 · 2026-10

---

## 0. Decisões tomadas

| Tema | Decisão |
|------|---------|
| Servidor | **FrankenPHP** (1 container: PHP + Caddy) |
| Frontend | **React mantido**; Symfony é só API |
| Pastas | **`api/` + `web/`** (o NestJS em `backend/` foi removido na Fase 8) |
| Deploy | **Só local / apresentação**: sem compose de produção por enquanto |
| Papéis | **Vários papéis por usuário**. Aluno é exclusivo; Orientador, Avaliador e Coordenação combinam livremente |
| Navegação | **Seletor de papel** no topo ("Ver como…"), reaproveitando as telas atuais |
| Conflito | **Orientador não pode** ser avaliador do TCC que orienta |
| Login | **Só e-mail e senha** (Google vai para o backlog) |
| Banca | Tamanho **configurável** pela coordenação (padrão: mín. 2, máx. 3) |
| Notas | **Cada avaliador** lança nota de **projeto e apresentação**, **após a defesa**. Orientador não dá nota |
| Nota final | **Média simples**: (média projeto + média apresentação) / 2 |
| Nota mínima | **Configurável** (padrão 6,0). Abaixo dela, **a coordenação decide** entre reprovar ou permitir nova defesa |
| Defesa | **Agendada no sistema** (data, hora, sala/link) pelo orientador ou pela coordenação |
| Melhorias no escopo | **Notificações** (e-mail + sininho), **histórico de versões do PDF**, **repositório de TCCs** (só para logados) |
| Publicação | **Aluno autoriza + coordenação publica** |
| Testes | **Cobertura alta** (meta ≥ 85% de linhas no backend) |

---

## 1. Visão geral

```mermaid
flowchart LR
    U[Navegador] -->|HTTPS localhost| P[php · FrankenPHP/Caddy]
    U -->|:5173 dev| W[web · Vite React]
    W -->|/api| P
    P --> A[API Symfony]
    A --> DB[(database · PostgreSQL 16)]
    A --> FS[[volume uploads<br/>privado]]
    A -->|fila| DB
    WK[worker · Messenger] --> DB
    WK -->|SMTP| M[mailer · Mailpit]
```

**Princípios**

1. **Contrato de API o mais próximo possível do atual.** As rotas e o formato JSON se mantêm. As mudanças necessárias (vários papéis, notas por avaliador, defesa, notificações) estão listadas na §8.
2. **Regras de negócio declarativas.** O fluxo do TCC é uma máquina de estados (Symfony Workflow).
3. **Tudo roda no Docker.** Só é preciso ter Docker e `make`. Um `make up` sobe tudo.
4. **A apresentação é reproduzível.** `make demo` recria o banco com dados de exemplo cobrindo todos os status.

---

## 2. Decisões técnicas

| # | Decisão | Alternativa descartada | Motivo |
|---|---------|------------------------|--------|
| T1 | Symfony 7.4 LTS · imagem com PHP 8.5, código compatível com PHP ≥ 8.3 | Symfony 8.x | Suporte longo, cobre todo o ciclo do TCC |
| T2 | FrankenPHP (base `dunglas/symfony-docker`) | Nginx + PHP-FPM | 1 container, HTTPS local automático, template oficial |
| T3 | Controllers próprios + Serializer | API Platform | Preserva o contrato usado pelo frontend |
| T4 | Doctrine ORM + Migrations | — | Padrão do ecossistema |
| T5 | UUID v7 como id | cuid, autoincrement | Tipo nativo no Postgres, ordenável |
| T6 | Symfony Workflow (`state_machine`) | `if`s no service | Transições, guards e histórico declarados e testáveis |
| T7 | JWT 15 min + refresh token em cookie httpOnly | JWT 7 dias no `localStorage` | Mudança de papel vale em minutos; token vazado expira rápido |
| T8 | Messenger com transporte Doctrine | RabbitMQ/Redis | Reaproveita o Postgres |
| T9 | Storage em volume privado (`var/storage`, volume `uploads_data`) | Pasta pública `/uploads` | Download com checagem de permissão. Flysystem fica para quando houver S3/MinIO |
| T10 | Busca full-text nativa do Postgres (`tsvector`, dicionário `portuguese`) | Elasticsearch/Meilisearch | Busca no repositório sem mais um serviço |
| T11 | Sininho por *polling* (React Query, 30 s) | Mercure (SSE, já embutido no FrankenPHP) | Mais simples. Mercure fica como evolução |
| T12 | `roles` como array JSON no usuário (padrão Symfony Security) | Tabela `user_roles` | Integra direto com `ROLE_*` e `isGranted` |

---

## 3. Containers Docker

### 3.1 Serviços

| Serviço | Imagem | Porta | Função |
|---------|--------|-------|--------|
| `php` | build `api/Dockerfile` (FrankenPHP) | 443 / 80 | API Symfony + Caddy |
| `worker` | mesma imagem do `php` | — | `messenger:consume async`: e-mails *(adiado: entra junto com o e-mail, ver §15)* |
| `database` | `postgres:16-alpine` | 5432 | Banco de dados (e fila do Messenger) |
| `web` | `node:22-alpine` | 5173 | Vite dev server do React, com hot reload |
| `mailer` | `axllent/mailpit` | 8025 | Caixa de e-mails de teste |
| `adminer` | `adminer` | 8080 | Inspecionar o banco (perfil opcional `tools`) |

### 3.2 Arquivos

```
compose.yaml            # php, database (e worker a partir da Fase 6)
compose.override.yaml   # dev: build, bind mounts, Xdebug, portas,
                        # web, mailer e adminer (profile "tools")
```

O deploy ainda é só local, mas a produção já está pronta: `compose.prod.yaml` (imagem com API + frontend compilado, HTTPS automático, segredos em `.env.prod`), testada a cada push pelo job **Produção** da CI. Guia em [`docs/DEPLOY.md`](DEPLOY.md).

### 3.3 Volumes

| Volume | Montado em | Conteúdo |
|--------|-----------|----------|
| `database_data` | `/var/lib/postgresql/data` | Dados do Postgres |
| `uploads_data` | `/app/var/storage` | PDFs (fora de `public/`) |
| `web_node_modules` | `/app/node_modules` (web) | Evita conflito com o `node_modules` do host |
| `caddy_data`, `caddy_config` | `/data`, `/config` | Certificado HTTPS local |

### 3.4 Dockerfile (`api/Dockerfile`)

```
frankenphp_base → PHP 8.5 + pdo_pgsql, intl, opcache, zip, apcu
frankenphp_dev  → + Xdebug/PCOV, composer
frankenphp_prod → composer --no-dev, cache warmup (pronto para o futuro)
```

### 3.5 Makefile

| Comando | Faz |
|---------|-----|
| `make up` / `make down` | Sobe / derruba |
| `make sh` | Shell no container `php` |
| `make db-reset` | Recria banco + migrations + fixtures do grupo `base` (usuários, catálogos, prazos) |
| `make fixtures` | Recarrega só o grupo `base` (apaga os dados atuais) |
| `make demo` | `up` + migrations + grupo `demo`: TCCs em revisão, na banca, com defesa agendada e publicados |
| `make migration` | Gera migration a partir das entidades |
| `make test` / `make coverage` | PHPUnit / relatório HTML de cobertura (copiado para `api/var/coverage`) |
| `make test-coverage` / `make web-coverage` | Testes exigindo cobertura mínima de 85% (API / web) |
| `make qa` | CS-Fixer + PHPStan + testes e cobertura da API e da web (o mesmo que a CI) |
| `make logs` | Logs de todos os serviços |

---

## 4. Estrutura do repositório

```
hubtcc/
├── api/
│   ├── config/packages/{security,workflow,messenger,flysystem}.yaml
│   ├── migrations/
│   ├── src/
│   │   ├── Controller/          # finos: DTO → Service → JSON
│   │   ├── Dto/{Request,Response}/
│   │   ├── Entity/
│   │   ├── Enum/                # Role, TccStatus, EvaluationVerdict, PeriodType,
│   │   │                        # NotificationType, DefenseOutcome
│   │   ├── Repository/
│   │   ├── Security/Voter/      # TccVoter, DefenseVoter, UserVoter
│   │   ├── Service/
│   │   │   ├── TccService.php
│   │   │   ├── BoardService.php        # montagem da banca + consenso dos pareceres
│   │   │   ├── DefenseService.php      # agendamento, notas, resultado
│   │   │   ├── DocumentStorage.php     # versões do PDF
│   │   │   ├── RepositorySearch.php    # busca full-text
│   │   │   ├── PeriodChecker.php
│   │   │   └── SettingsProvider.php
│   │   ├── Workflow/            # guards e listeners
│   │   ├── Notification/        # Message, Handler, NotificationFactory
│   │   ├── EventListener/       # ExceptionListener (JSON de erro)
│   │   └── DataFixtures/{Base,Demo}/
│   ├── templates/email/
│   ├── tests/{Unit,Integration,Functional}/
│   ├── frankenphp/              # Caddyfile, php.ini, entrypoint
│   └── Dockerfile
├── web/                          # React (ex-frontend/)
├── docs/
├── compose.yaml
├── compose.override.yaml
├── Makefile
└── .github/workflows/ci.yaml
```

**Camadas:** `Controller → Service → (Workflow · Repository · Storage) → Entity`. Toda regra de "quem pode fazer o quê neste TCC" fica nos **Voters**.

---

## 5. Papéis e autorização

### 5.1 Papéis

| Papel | `ROLE_*` | Pode combinar com |
|-------|----------|-------------------|
| Aluno | `ROLE_STUDENT` | **nenhum** |
| Orientador | `ROLE_ADVISOR` | Avaliador, Coordenação |
| Avaliador | `ROLE_EVALUATOR` | Orientador, Coordenação |
| Coordenação | `ROLE_COORDINATION` | Orientador, Avaliador |

- O registro público cria sempre `[ROLE_STUDENT]`. Os demais papéis são atribuídos pela coordenação.
- **Validação:** `roles` não pode ser vazio, e `ROLE_STUDENT` não pode estar junto de outro papel. Não é possível remover `ROLE_COORDINATION` do último coordenador.
- Não há hierarquia de papéis (Coordenação **não** herda Orientador). Cada um precisa ser atribuído explicitamente.

### 5.2 Papel ativo no frontend

- O JWT traz `roles: [...]`. O frontend guarda o **papel ativo** (`activeRole`) e mostra o seletor quando há mais de um.
- O papel ativo só serve para **navegação** (qual tela mostrar). A API decide pelo conjunto completo de papéis e pelos Voters. Exemplo: `GET /api/tcc?as=ADVISOR` filtra a listagem, mas a permissão de ver um TCC vale para qualquer papel que o usuário tenha.

### 5.3 Voters

| Atributo | Regra |
|----------|-------|
| `TCC_VIEW` | Coordenação · aluno autor · orientador do TCC · avaliador da banca |
| `TCC_EDIT` | Aluno autor em `DRAFT`/`CORRECTIONS_REQUESTED` · coordenação sempre |
| `TCC_UPLOAD` / `TCC_SUBMIT` | Aluno autor em `DRAFT`/`CORRECTIONS_REQUESTED` |
| `TCC_ADVISOR_REVIEW` | Orientador do TCC |
| `TCC_EVALUATE` | Membro da banca, status `SENT_TO_EVALUATION`, período de avaliação ativo |
| `DEFENSE_SCHEDULE` | Orientador do TCC ou coordenação, status `AWAITING_DEFENSE` |
| `DEFENSE_GRADE` | Membro da banca, defesa agendada e já iniciada (`scheduled_at ≤ agora`) |
| `TCC_DECIDE` | Coordenação, status `AWAITING_DECISION` |
| `TCC_AUTHORIZE_PUBLICATION` | Aluno autor |
| `TCC_PUBLISH` | Coordenação, TCC `APPROVED` com autorização do aluno |

**Banca:** um avaliador precisa ter `ROLE_EVALUATOR`, **não pode ser o orientador** do TCC e não pode ser o próprio aluno. A regra vale tanto ao montar a banca quanto ao trocar o orientador.

---

## 6. Modelo de dados (PostgreSQL)

```mermaid
erDiagram
    users ||--o{ tccs : "aluno"
    users ||--o{ tccs : "orientador"
    users ||--o{ board_members : "avalia"
    users ||--o{ notifications : "recebe"
    tccs ||--o{ tcc_documents : "versões"
    tccs ||--o{ board_members : "banca"
    tccs ||--o{ defenses : "tentativas"
    tccs ||--o{ tcc_status_history : ""
    tccs }o--o{ thematic_areas : "tcc_thematic_areas"
    tccs }o--o{ keywords : "tcc_keywords"
    defenses ||--o{ defense_grades : ""
    board_members ||--o{ defense_grades : ""

    users {
        uuid id PK
        varchar name
        varchar email UK
        varchar password_hash
        jsonb roles
        timestamptz created_at
    }
    tccs {
        uuid id PK
        varchar title
        text abstract
        varchar status
        uuid student_id FK
        uuid advisor_id FK
        numeric final_grade "nullable"
        boolean publication_authorized
        timestamptz published_at "nullable"
        timestamptz created_at
        timestamptz updated_at
    }
    tcc_documents {
        uuid id PK
        uuid tcc_id FK
        int version
        varchar storage_path
        varchar original_name
        bigint size_bytes
        char sha256
        uuid uploaded_by_id FK
        timestamptz uploaded_at
    }
    board_members {
        uuid id PK
        uuid tcc_id FK
        uuid evaluator_id FK
        varchar verdict
        text comment
        timestamptz updated_at
    }
    defenses {
        uuid id PK
        uuid tcc_id FK
        int attempt
        timestamptz scheduled_at "nullable"
        varchar location "nullable"
        varchar meeting_url "nullable"
        numeric project_average "nullable"
        numeric presentation_average "nullable"
        numeric final_grade "nullable"
        varchar outcome "PENDING|PASSED|FAILED"
        uuid scheduled_by_id FK
    }
    defense_grades {
        uuid id PK
        uuid defense_id FK
        uuid board_member_id FK
        numeric project_grade
        numeric presentation_grade
        text comment
        timestamptz created_at
    }
    tcc_status_history {
        uuid id PK
        uuid tcc_id FK
        varchar from_status
        varchar to_status
        varchar transition
        text comment
        uuid changed_by_id FK
        timestamptz created_at
    }
    notifications {
        uuid id PK
        uuid user_id FK
        varchar type
        varchar title
        text body
        uuid tcc_id FK "nullable"
        timestamptz read_at "nullable"
        timestamptz created_at
    }
    submission_periods {
        uuid id PK
        varchar type
        varchar label
        timestamptz start_date
        timestamptz end_date
    }
    settings {
        int id PK "sempre 1"
        int board_min_size
        int board_max_size
        numeric passing_grade
    }
    thematic_areas {
        uuid id PK
        varchar name UK
    }
    keywords {
        uuid id PK
        varchar name UK
    }
```

Também existem as tabelas `refresh_tokens` (do bundle de refresh) e `messenger_messages` (fila).

**Notas de modelagem**

- **Notas** em `numeric(4,2)`, com check `0 ≤ nota ≤ 10`. `UNIQUE(defense_id, board_member_id)`, ou seja, uma nota por avaliador por tentativa.
- **Várias tentativas de defesa.** Se a coordenação permitir nova defesa, cria-se uma nova linha em `defenses` com `attempt + 1`. As notas antigas ficam guardadas no histórico.
- **`tccs.final_grade`** guarda a nota da última defesa aprovada, para facilitar listagem e repositório.
- **Documento atual** é a versão mais alta em `tcc_documents` (não há `current_document_id` em `tccs`, para evitar chave estrangeira circular).
- **Defesa** nasce sem `scheduled_at` quando o TCC entra em `AWAITING_DEFENSE` e recebe data no agendamento.
- **Busca do repositório** (Fase 7): tabela `tcc_search (tcc_id, document tsvector)` com índice GIN, só com os TCCs publicados. Fica fora das entidades (`schema_filter` no `doctrine.yaml`) porque o Doctrine não conhece `tsvector`; quem mantém é `App\Search\RepositoryIndex`. Não é coluna gerada porque palavras-chave e áreas estão em outras tabelas.
- **Índices:** `tccs(student_id)`, `tccs(advisor_id)`, `tccs(status)`, `board_members(evaluator_id)`, `notifications(user_id, read_at)`, `submission_periods(type, start_date, end_date)`. Sem índice GIN em `users.roles`: a tabela é pequena e o Doctrine não mapeia esse tipo de índice.
- **CHECKs no banco** (além das regras nas entidades): notas entre 0 e 10, `end_date > start_date` nos períodos, `settings` com linha única (`id = 1`) e tamanho de banca coerente. A migration já insere as configurações padrão.
- **`UNIQUE(tcc_id, evaluator_id)`** em `board_members` e **`UNIQUE(tcc_id, version)`** em `tcc_documents`.
- Removidos em relação ao schema atual: `google_id`, `documentUrl`/`documentVersion` (agora em `tcc_documents`), `projectGrade`/`presentationGrade` do TCC (agora em `defense_grades`), `publishable` (substituído por `publication_authorized` + `published_at`) e o status não usado `ADVISOR_REVIEW`.

---

## 7. Máquina de estados do TCC

```mermaid
stateDiagram-v2
    [*] --> DRAFT
    DRAFT --> SUBMITTED : submit
    CORRECTIONS_REQUESTED --> RESUBMITTED : resubmit
    SUBMITTED --> SENT_TO_EVALUATION : advisor_approve
    RESUBMITTED --> SENT_TO_EVALUATION : advisor_approve
    SUBMITTED --> REJECTED : advisor_reject
    RESUBMITTED --> REJECTED : advisor_reject
    SENT_TO_EVALUATION --> ADVISOR_FINAL_REVIEW : board_approve
    SENT_TO_EVALUATION --> CORRECTIONS_REQUESTED : board_request_corrections
    ADVISOR_FINAL_REVIEW --> AWAITING_DEFENSE : final_approve
    ADVISOR_FINAL_REVIEW --> CORRECTIONS_REQUESTED : final_request_corrections
    AWAITING_DEFENSE --> APPROVED : defense_pass
    AWAITING_DEFENSE --> AWAITING_DECISION : defense_fail
    AWAITING_DECISION --> AWAITING_DEFENSE : allow_new_defense
    AWAITING_DECISION --> REJECTED : coordination_reject
    APPROVED --> [*]
    REJECTED --> [*]
```

| Transição | Quem | Guard |
|-----------|------|-------|
| `submit` / `resubmit` | Aluno autor | Tem PDF e orientador |
| `advisor_approve` | Orientador | Tamanho da banca entre `board_min_size` e `board_max_size` |
| `advisor_reject` | Orientador | — |
| `board_approve` / `board_request_corrections` | **Sistema** | Todos os pareceres dados: todos aprovaram, ou ≥ 1 pediu correção |
| `final_approve` / `final_request_corrections` | Orientador | — |
| `defense_pass` / `defense_fail` | **Sistema** | Todos os avaliadores lançaram nota: final ≥ `passing_grade`, ou abaixo |
| `allow_new_defense` | Coordenação | Comentário obrigatório |
| `coordination_reject` | Coordenação | Comentário obrigatório |

**Listeners**

- `completed`: grava `tcc_status_history` na **mesma transação** da mudança.
- `entered.SENT_TO_EVALUATION`: zera os pareceres da banca.
- `entered.AWAITING_DEFENSE`: cria `defenses` com `attempt = n + 1` (sem data, aguardando agendamento).
- `entered.*`: despacha `TccStatusChanged`, que gera as notificações (§9).

**Fechamento automático (pareceres e notas).** Os dois seguem o mesmo padrão, dentro de uma transação com `SELECT … FOR UPDATE` na linha do TCC:

```
grava parecer/nota do avaliador
se todos da banca já registraram → calcula resultado → aplica transição do sistema
```

O fechamento dos pareceres também é recalculado quando a coordenação altera a banca. Durante `AWAITING_DEFENSE`, a banca fica **congelada**.

**Cálculo da nota final**

```
média_projeto       = avg(project_grade)       da tentativa atual
média_apresentação  = avg(presentation_grade)  da tentativa atual
nota_final          = (média_projeto + média_apresentação) / 2    -- arredondada a 2 casas
```

**Regras fora do workflow**

- Criar TCC: só com período `SUBMISSION` ativo.
- Dar parecer: só com período `EVALUATION` ativo.
- Lançar nota: só depois do horário agendado da defesa. A nota pode ser corrigida até o fechamento.
- Avaliação cega: vale para pareceres **e** notas. O avaliador não vê o que os outros registraram enquanto não registrar o seu.
- Reagendar a defesa: permitido enquanto nenhuma nota tiver sido lançada.

---

## 8. API

Prefixo `/api`. JSON sem envelope. ✱ = novo · ✎ = muda em relação ao NestJS.

**Auth**

| Método | Rota | Acesso |
|--------|------|--------|
| POST | `/auth/register` | público (cria aluno) |
| POST | `/auth/login` ✎ | público. Retorna `accessToken` + cookie de refresh. Payload do JWT: `roles[]` |
| POST | `/auth/refresh` ✱ · `/auth/logout` ✱ | cookie |
| GET | `/me` ✱ | autenticado |

**Cadastros**

| Método | Rota | Acesso |
|--------|------|--------|
| GET | `/users?role=` ✎ | autenticado. Filtra quem *tem* o papel |
| POST · DELETE `/{id}` | `/users` | Coordenação |
| PUT | `/users/{id}/roles` ✎ (antes `PATCH …/role`) | Coordenação, body `{roles: [...]}` |
| CRUD | `/thematic-areas`, `/keywords`, `/periods` | leitura: todos · escrita: Coordenação |
| GET · PUT | `/settings` ✱ | leitura: todos · escrita: Coordenação |

**TCC**

| Método | Rota | Acesso |
|--------|------|--------|
| GET | `/tcc?as=&status=&page=` ✎ | autenticado. `as` = papel ativo |
| POST | `/tcc` | Aluno |
| GET · PATCH | `/tcc/{id}` | `TCC_VIEW` / `TCC_EDIT` |
| POST | `/tcc/{id}/document` | `TCC_UPLOAD` |
| GET | `/tcc/{id}/documents` ✱ | `TCC_VIEW`. Lista as versões |
| GET | `/tcc/{id}/documents/{version}/download` ✱ | URL assinada (5 min) |
| POST | `/tcc/{id}/submit` | `TCC_SUBMIT` |
| PUT | `/tcc/{id}/evaluators` | Coordenação. Valida tamanho e conflito |
| POST | `/tcc/{id}/advisor-review` | `TCC_ADVISOR_REVIEW` |
| POST | `/tcc/{id}/evaluate` | `TCC_EVALUATE` |
| ~~POST~~ | ~~`/tcc/{id}/grades`~~ | removido (substituído pela defesa) |
| PUT | `/tcc/{id}/defense` ✱ | `DEFENSE_SCHEDULE`, body `{scheduledAt, location?, meetingUrl?}` |
| PUT | `/tcc/{id}/defense/grade` ✱ | `DEFENSE_GRADE`, body `{projectGrade, presentationGrade, comment?}` |
| POST | `/tcc/{id}/decision` ✱ | `TCC_DECIDE`, body `{action: "new_defense"\|"reject", comment}` |
| PUT | `/tcc/{id}/publication-authorization` ✱ | `TCC_AUTHORIZE_PUBLICATION`, body `{authorized}` |
| POST · DELETE | `/tcc/{id}/publish` ✱ | `TCC_PUBLISH` |

**Repositório e notificações**

| Método | Rota | Acesso |
|--------|------|--------|
| GET | `/repository?q=&area=&keyword=&year=&page=` ✱ | autenticado. Só TCCs publicados |
| GET | `/repository/{id}` ✱ · `/repository/{id}/download` ✱ | autenticado. Baixa a versão final |
| GET | `/notifications?unread=1` ✱ | autenticado |
| GET | `/notifications/unread-count` ✱ | autenticado (usado no polling) |
| POST | `/notifications/{id}/read` ✱ · `/notifications/read-all` ✱ | autenticado |

**Erros:** `{"statusCode": 4xx, "message": "…"}`, igual ao NestJS. Validação devolve `message: [...]`. Transição inválida do workflow vira **409** com a mensagem do guard.

---

## 9. Notificações

**Hoje (Fase 6, sem e-mail):** os avisos do sininho são gravados pelo `App\Notification\Notifier` **na mesma transação da ação** (dentro do `TccLocker`): aviso e mudança são gravados juntos ou não são gravados. Não há fila nem `worker`. Quem fez a ação não recebe aviso do que ele mesmo fez, exceto quando o aviso é uma tarefa nova para ele (`DECISION_PENDING`).

```
Workflow completed (TccNotificationSubscriber) / TccService::assignBoard / DefenseService::schedule
        └→ Notifier::notify(type, tcc, destinatários, título, texto) → INSERT notifications (sininho)
```

| Tipo | Quando | Para |
|------|--------|------|
| `TCC_SUBMITTED` | `submit` / `resubmit` | Orientador |
| `BOARD_ASSIGNED` | Incluído numa banca (só os novos) | Avaliador |
| `EVALUATION_OPEN` | `SENT_TO_EVALUATION` | Banca e aluno |
| `CORRECTIONS_REQUESTED` | Volta para o aluno | Aluno (pela banca: aponta os pareceres; pelo orientador: com o comentário dele) |
| `FINAL_REVIEW_PENDING` | Banca aprovou | Orientador |
| `DEFENSE_PENDING` | Aprovado para a defesa (aluno) ou nova defesa liberada (aluno e orientador) | Aluno / orientador |
| `DEFENSE_SCHEDULED` | Defesa agendada ou reagendada | Aluno, orientador, banca |
| `DECISION_PENDING` | Média abaixo do mínimo | Todos com papel de coordenação, inclusive quem deu a última nota (é uma tarefa nova) |
| `TCC_APPROVED` / `TCC_REJECTED` | Estado final | Aluno, orientador |
| `GRADES_PENDING` | Horário da defesa chegou | *Adiado (precisa do agendador, entra com o e-mail)* |
| `PUBLICATION_REQUESTED` / `PUBLICATION_WITHDRAWN` | Aluno autorizou / retirou a autorização de um TCC publicado | Coordenação |
| `TCC_PUBLISHED` / `TCC_UNPUBLISHED` | Coordenação publicou / tirou do repositório | Aluno |

**API:** `GET /api/notifications[?unread=1]` (30 mais recentes + `unreadCount`), `GET /api/notifications/unread-count`, `POST /api/notifications/{id}/read`, `POST /api/notifications/read-all`. Cada aviso traz `as`: o papel com que o destinatário abre o TCC (aluno, orientador, avaliador ou coordenação), usado pelo frontend para ir à tela certa.

**Frontend:** `components/NotificationBell.tsx` no topo. O contador é consultado a cada 30 s; quando aumenta, as telas de TCC abertas recarregam.

**Depois (e-mail, no backlog):** o `Notifier` passa também a despachar uma mensagem para a fila do Messenger (transporte Doctrine), consumida pelo serviço `worker`, que envia o e-mail com o mesmo título e texto. O lembrete `GRADES_PENDING` roda no `worker` via Symfony Scheduler.


---

## 10. Segurança

| Item | Implementação |
|------|---------------|
| Senhas | `password_hashers: auto` |
| JWT | LexikJWT RS256, 15 min, `{sub, email, roles}` |
| Refresh | `gesdinet/jwt-refresh-token-bundle`, cookie `httpOnly; SameSite=Lax`, 7 dias, rotação |
| Rate limit | `/auth/login` e `/auth/register` (5/min por IP+e-mail) |
| Uploads | Constraint `File(mimeTypes: ['application/pdf'], maxSize: '20M')` (lê o conteúdo real), nome por UUID, fora de `public/`, `sha256` gravado |
| Download | URL assinada (`UriSigner`) com 5 min de validade, gerada após checar o Voter |
| Avaliação cega | Aplicada no serializer de saída, com teste dedicado |

---

## 11. Frontend: mudanças necessárias

| Mudança | Onde |
|---------|------|
| Token em memória + interceptor 401 → `/auth/refresh` | `api/client.ts`, `auth/AuthContext.tsx` |
| `role` → `roles[]` + `activeRole` + seletor "Ver como…" | `AuthContext`, `Layout`, `ProtectedRoute` |
| Listagens passam `?as=activeRole` | `api/hooks.ts` |
| Coordenação: edição de vários papéis (checkboxes com a regra do aluno exclusivo) | `UsersManagement.tsx` |
| Coordenação: tela de **Configurações** (banca mín./máx., nota mínima) | nova `coordination/Settings.tsx` |
| Coordenação: decisão (nova defesa / reprovar) e publicação | `CoordinationTccDetail.tsx` |
| Orientador: agendar defesa; **remover** o formulário de notas | `AdvisorTccDetail.tsx` |
| Avaliador: lançar notas após a defesa | `EvaluatorTccDetail.tsx` |
| Aluno: autorizar publicação, ver versões do PDF, ver defesa agendada | `StudentHome.tsx` |
| Sininho com contador + lista | `components/NotificationBell.tsx` |
| Repositório com busca e filtros | nova `pages/Repository.tsx` |
| Novos status nos badges/timeline | `StatusBadge.tsx`, `StatusHistoryTimeline.tsx` |

---

## 12. Testes (cobertura alta)

| Camada | Ferramenta | O que cobre | Meta |
|--------|-----------|-------------|------|
| Unit | PHPUnit 11 | Cálculo de nota, regras de papéis, guards do workflow, voters (tabela de casos) | 100% em `Service/`, `Security/`, `Workflow/` |
| Integration | KernelTestCase + Postgres real | Repositórios, busca full-text, locks de concorrência, migrations | todos os repositórios |
| Functional | WebTestCase | **Todos** os endpoints: sucesso, 401, 403, 404, 409, 422 | todos os endpoints |
| Cenários E2E de API | WebTestCase | Fluxo completo, ciclo de correção, reprovação → nova defesa, avaliação cega, banca alterada no meio, conflito orientador/avaliador | — |
| Frontend | Vitest + Testing Library | AuthContext/refresh, seletor de papel, sininho, formulários novos | componentes novos |

- **Ferramentas:** `zenstruck/foundry` (fábricas) e `dama/doctrine-test-bundle` (rollback por teste), com banco `hubtcc_test` no mesmo container Postgres.
- **Cobertura:** PCOV no container de dev. O CI **falha se a cobertura do backend cair abaixo de 85%**.
- **Qualidade:** PHPStan nível 8 + extensões symfony/doctrine, PHP-CS-Fixer `@Symfony`, ESLint + `tsc` no frontend.
- **CI (GitHub Actions):** `docker compose build` → `make qa` em cada push.

---

## 13. Ambiente (`.env` de dev)

| Variável | Valor |
|----------|-------|
| `DATABASE_URL` | `postgresql://app:app@database:5432/hubtcc?serverVersion=16&charset=utf8` |
| `JWT_PASSPHRASE` | gerado no 1º `make up` (chaves em `config/jwt/`, fora do git) |
| `MAILER_DSN` | `smtp://mailer:1025` |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default?auto_setup=0` |
| `STORAGE_DIR` | `%kernel.project_dir%/var/storage` |
| `FRONTEND_URL` | `http://localhost:5173` |
| `VITE_API_URL` (web) | `https://localhost/api` |

---

## 14. Ordem de implementação

| Fase | Entrega | Pronto quando |
|------|---------|---------------|
| 0 · Infra ✅ | Compose, Dockerfile, Makefile, Symfony + `GET /api/health`, PHPStan/CS/PHPUnit, CI | `make up` sobe tudo; `make qa` verde |
| 1 · Dados ✅ | Entidades, enums, migrations, fixtures base (Foundry + dama/doctrine-test-bundle) | `make db-reset` cria banco com 1 usuário por papel + 1 professor com 3 papéis |
| 2 · Auth e papéis ✅ | Login, registro, refresh, `/me`, gestão de usuários e papéis, novo visual do login, seletor de papel, Vitest no frontend | Login e troca de papel funcionam no frontend. Os Voters de TCC ficaram para a Fase 4, junto com as rotas que eles protegem |
| 3 · Cadastros ✅ | Áreas, palavras-chave, períodos, configurações | Telas da coordenação funcionando |
| 4 · TCC até a banca ✅ | Workflow, upload com versões, banca (tamanho/conflito), pareceres | Fluxo até `AWAITING_DEFENSE` pelo frontend (testado de ponta a ponta no navegador) |
| 5 · Defesa ✅ | Agendamento, notas, fechamento, decisão da coordenação | Fluxo completo até `APPROVED` e o caminho de reprovação (testado no navegador até Aprovado) |
| 6 · Notificações ✅ (sem e-mail) | Sininho: avisos gravados junto com cada ação, API e contador no frontend. E-mail, `worker` e lembrete ficaram no backlog | Avisos certos para cada papel; clique leva ao TCC (testado no navegador) |
| 7 · Repositório ✅ | Autorização, publicação, busca full-text, download | Busca em português e sem acento; só TCCs publicados (testado no navegador) |
| 8 · Corte ✅ | `make demo`, remover NestJS, `frontend/` → `web/`, README | Repositório só com Symfony; cobertura ≥ 85% exigida na CI (API 94%, web 93%) |

Os testes são escritos **em cada fase** e não ficam para o final. Cada fase só termina com `make qa` verde.

---

## 14.1 Notas da Fase 2

- **Senha mínima de 8 caracteres** no cadastro e na criação pela coordenação. Os usuários das fixtures continuam com `123456` (só desenvolvimento).
- **Painel 3D removido** da tela de login (e as dependências `three`/`@react-three/*`), substituído pelo cartão cinza flutuante. O bundle do frontend caiu para ~300 kB.
- **Login com Google removido** do frontend junto com a rota `/oauth-callback` (segue no backlog).
- **Throttling:** 5 tentativas de login por minuto (por e-mail + IP) e 5 cadastros a cada 10 minutos por IP. Nos testes, cada teste usa um IP aleatório para não esbarrar nos limites.
- **Papel ativo** guardado no `localStorage` (preferência do navegador). O token de acesso fica só em memória; a sessão volta pelo cookie de refresh.

## 14.2 Notas das Fases 3 e 4 (entregues juntas)

- **Prazos:** a coordenação informa só a data; o prazo vale o dia inteiro no fuso da instituição (`app.timezone`, padrão `America/Sao_Paulo`).
- **Catálogos:** nome único sem diferenciar maiúsculas; área ou palavra-chave em uso por algum TCC não pode ser excluída.
- **Permissões** pelo vínculo com o TCC (`TccVoter`). Quem não participa do TCC recebe 404, para nem saber que ele existe. A resposta traz `permissions`, e a tela só mostra os botões permitidos.
- **Códigos de erro**: quando a permissão depende do status (editar, enviar PDF, submeter, avaliar), o Voter barra com **403** e a tela nem mostra o botão; quando a ação é permitida mas uma regra do fluxo impede, a resposta é **409** com a explicação (ex.: "A banca precisa ter de 2 a 3 avaliadores…", "Envie a versão corrigida do PDF antes de reenviar."). Regra de negócio violada nos dados enviados: **422**.
- **Recusar** (orientador reprovando, ou devolvendo na revisão final) e **pedir correções** (avaliador) exigem comentário.
- **Aluno não vê pareceres parciais**: durante a avaliação, os votos aparecem como pendentes até a rodada fechar.
- **Download do PDF**: link assinado de 5 minutos (`UriSigner`), gerado só para quem pode ver o TCC; serve num `<a href>` comum.
- **Entrar em `AWAITING_DEFENSE`** já abre a 1ª tentativa de defesa (sem data); o agendamento e as notas são a Fase 5.

**Ajustes da revisão de código (PR #4):**

- **Concorrência**: toda ação que muda o TCC (enviar PDF, submeter, revisão do orientador, montar banca, parecer) roda numa transação com a linha do TCC travada (`PESSIMISTIC_WRITE`) e **recarrega os votos da banca** antes de decidir — sem isso, dois avaliadores votando juntos podiam deixar o TCC preso em avaliação. Coberto por `BoardConsensusConcurrencyTest`.
- **Parecer é definitivo na rodada**: depois de votar o avaliador vê os colegas, então não pode mais trocar o voto (preserva a avaliação cega). Numa nova rodada (após reenvio) vota de novo.
- **Banca durante a avaliação** mantém o tamanho mínimo das configurações.
- **Reenvio exige PDF novo**: a versão do PDF é registrada quando as correções são pedidas; reenviar exige uma versão mais nova.
- **Um TCC em andamento por aluno**: novo TCC só depois de o anterior ser aprovado ou reprovado.
- **Listagem sem N+1**: as coleções são carregadas em lote (consultas fixas, independentemente do número de TCCs). Coberto por `TccListingQueriesTest`.
- Datas impossíveis (31/02) são rejeitadas; nomes de arquivo longos são encurtados; envios simultâneos respondem 409 em vez de 500.

## 14.3 Notas da Fase 5 (defesa)

| Ação | Quem | Quando |
|------|------|--------|
| Agendar / reagendar (`PUT /api/tcc/{id}/defense`) | Orientador do TCC ou coordenação | `AWAITING_DEFENSE`, antes da primeira nota |
| Lançar / corrigir notas (`PUT /api/tcc/{id}/defense/grade`) | Membro da banca | A partir do horário agendado, até toda a banca lançar |
| Decidir (`POST /api/tcc/{id}/decision`, `new_defense` ou `reject`) | Coordenação | `AWAITING_DECISION`, com justificativa |

- **Cálculo:** média das notas de projeto, média das de apresentação e nota final = média das duas. Tudo em centésimos inteiros (`App\Util\Grade`), com meio centésimo arredondando para cima. Ex.: (7,75 + 9,50) / 2 = 8,625 → **8,63**. As médias parciais aparecem arredondadas, mas só a final é arredondada no cálculo (projeto 5,995 e apresentação 5,99 dão 5,9925 → 5,99, não 6,00). Notas com mais de duas casas são recusadas.
- **Resultado automático** quando o último avaliador lança: final ≥ nota mínima (configurações) → `APPROVED` e a nota fica no TCC; abaixo → `AWAITING_DECISION`.
- **Nova defesa** abre a tentativa seguinte (sem data, sem notas); as anteriores aparecem em `previousDefenses`.
- **Notas às cegas** até a defesa fechar: cada avaliador vê só a própria (mesmo quem também é coordenação); a coordenação de fora da banca acompanha; aluno e orientador veem tudo no resultado.
- **Concorrência:** `TccLocker` centraliza "travar e recarregar" (banca, defesa e notas) para todas as ações do TCC.
- **Horários:** a API manda as datas do TCC no fuso da instituição e a tela mostra exatamente esse horário (`web/src/utils/dates.ts`), sem converter para o fuso do navegador. O campo de agendamento usa `datetime-local`, interpretado no fuso da instituição.

## 14.4 Notas da Fase 7 (repositório)

| Ação | Quem | Quando |
|------|------|--------|
| Autorizar / retirar (`PUT /api/tcc/{id}/publication-authorization`, `{authorized}`) | Aluno autor | TCC `APPROVED`. Retirar tira do repositório na hora |
| Publicar / tirar (`POST` / `DELETE /api/tcc/{id}/publish`) | Coordenação | TCC `APPROVED`; publicar exige a autorização |
| Buscar (`GET /api/repository?q=&area=&keyword=&year=&page=`) | Qualquer usuário logado | Só publicados; 12 por página; devolve também os anos disponíveis |
| Ver (`GET /api/repository/{id}`) | Qualquer usuário logado | 404 se não estiver publicado |

- **Busca:** configuração `pt_unaccent` (dicionário português + `unaccent`): "fraude" acha "fraudes" e "transacoes" acha "transações". Pesos: título (A) > palavras-chave e áreas (B) > resumo (C) > aluno e orientador (D). Usa `websearch_to_tsquery`, então aspas e `-palavra` funcionam. Sem texto, ordena pelos publicados mais recentes.
- **Índice:** atualizado ao publicar, tirar do ar ou retirar a autorização (na mesma transação, via `TccLocker`) e refeito por completo quando a coordenação renomeia uma área ou palavra-chave.
- **O que é público no repositório:** título, resumo, autor, orientador, áreas, palavras-chave, nota final, data de publicação e o PDF final (link assinado de 5 min). Pareceres, notas por avaliador, histórico, defesa e versões anteriores continuam restritos.
- **Ano** = ano da publicação, no fuso da instituição.

## 14.5 Notas da Fase 8 (corte)

- **Removidos:** o backend NestJS (`backend/`, com Prisma e MySQL) e o login com Google, que estava só nele e segue no backlog.
- **Renomeado:** `frontend/` → `web/`, nos composes, na CI, no Makefile e na documentação.
- **`make demo`:** sobe tudo e carrega o grupo de fixtures `demo` (`App\DataFixtures\DemoFixtures`).
  - TCCs de outros alunos: em revisão, na banca com um parecer, com defesa agendada, e quatro aprovados e publicados, com PDF de verdade e índice de busca.
  - Os alunos de teste (`aluno@`, `aluno2@`) ficam sem TCC, para testar o fluxo do zero.
  - `make fixtures` carrega só o grupo `base`.
- **Cobertura mínima de 85%** de linhas, exigida na CI:
  - API: `make test-coverage`, PCOV + `api/bin/coverage-check`.
  - Web: `npm run test:coverage`, thresholds no `vite.config.ts`.
  - `main.tsx` e os utilitários de teste não contam.
- **Ponto de atenção:** a cobertura da API depende do PCOV na imagem de desenvolvimento. Se a instalação via PECL falhar no build (o script tenta 3 vezes), o passo de cobertura da CI falha. Basta rodar a CI de novo quando o PECL voltar.

## 15. Backlog (fora do escopo)

**Ideias vindas de projetos de referência** (§16), em ordem sugerida:

1. **Ata de defesa em PDF.** Gerada automaticamente quando a defesa fecha, com banca, notas, média, resultado e data. Fica anexada ao TCC e disponível para aluno, orientador e coordenação. *Como:* template Twig → PDF (Dompdf ou Gotenberg). Evolução: assinatura digital dos membros da banca (inspirado no gestao-tccs, IFPE).
2. **Registro de reuniões de orientação.** O orientador registra data, assunto e encaminhamentos, e o aluno confirma. Gera um histórico de acompanhamento útil para comprovar a orientação. *Modelo:* `advising_meetings (tcc_id, held_at, topic, notes, next_steps, confirmed_by_student_at)` (inspirado no gtcc e no SIGA-TCC).
3. **Etapas com cronograma.** A coordenação define marcos por período (ex.: projeto, qualificação, versão final) com prazos, e o aluno entrega o PDF em cada etapa, com o orientador dando parecer. *Modelo:* `milestones (period_id, name, due_date, order)` + `milestone_submissions`. Afeta o workflow: as etapas viram pré-requisito do `submit` final (inspirado no gtcc e no ls1intum).

**Outros**


- **Notificações por e-mail** (adiadas na Fase 6): serviço `worker`, fila Doctrine do Messenger, Mailpit em dev, mesmos textos do sininho, e o lembrete `GRADES_PENDING` via Scheduler (ver §9)
- Login com Google
- Recuperação de senha / verificação de e-mail
- Deploy de fato num servidor (a configuração já está pronta — ver `docs/DEPLOY.md`)
- Notificações em tempo real via Mercure
- Armazenamento em S3/MinIO

---

## 16. Projetos de referência

| Projeto | Licença | Uso no hubtcc |
|---------|---------|---------------|
| [dunglas/symfony-docker](https://github.com/dunglas/symfony-docker) | MIT | **Base da infraestrutura** (Dockerfile, Caddyfile, entrypoint) |
| [danilovl/final-work-system](https://github.com/danilovl/final-work-system) | MIT | Referência de código Symfony no mesmo domínio (voters, versões de arquivo, notificações) |
| [ls1intum/thesis-management](https://github.com/ls1intum/thesis-management) | MIT | Matriz de permissões, fluxogramas, compose de demonstração |
| [ifpebj-ti/gestao-tccs](https://github.com/ifpebj-ti/gestao-tccs) | Apache-2.0 | Agendamento de defesa, assinatura de documentos (contexto brasileiro) |
| [Jefferson08/gtcc](https://github.com/Jefferson08/gtcc) | sem licença | **Só ideias** (etapas, sessões de orientação) — não copiar código |
| [arielm11/SIGA-TCC](https://github.com/arielm11/SIGA-TCC) | — | Atas de reunião de orientação |
| [pcalvesUSP/deposito-tcc](https://github.com/pcalvesUSP/deposito-tcc) | — | Inspiração original do fluxo de negócio |

Código de terceiros só é reaproveitado de projetos MIT/Apache, mantendo o aviso de autoria.
