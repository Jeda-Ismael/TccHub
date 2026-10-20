# Arquitetura — hubtcc (Symfony + PostgreSQL + Docker)

Documento de referência para a migração do backend NestJS/Prisma/MySQL para Symfony/Doctrine/PostgreSQL. Define **o que** será construído e **por quê**, antes de escrever código.

> Status: proposta · Versão 1 · 2026-10

---

## 1. Visão geral

```mermaid
flowchart LR
    U[Navegador] -->|HTTPS| P[proxy / Caddy<br/>FrankenPHP]
    P -->|/api/*| A[API Symfony]
    P -->|/*| W[SPA React<br/>build estático]
    A --> DB[(PostgreSQL 16)]
    A --> FS[[Volume de arquivos<br/>privado]]
    A -->|mensagens| DB
    WK[Worker Messenger] --> DB
    WK -->|SMTP| M[Mailpit dev / SMTP prod]
    A -->|OAuth2| G[Google]
```

**Princípios**

1. **Contrato de API preservado.** O Symfony expõe os mesmos endpoints e o mesmo JSON do NestJS. O frontend React continua igual e serve de teste de aceitação.
2. **Regras de negócio declarativas.** O fluxo do TCC é uma máquina de estados (Symfony Workflow), não `if`s espalhados.
3. **Tudo roda no Docker.** Ninguém precisa de PHP, Node ou Postgres instalados na máquina. Um `make up` sobe tudo.
4. **Mesma imagem em dev e prod**, mudando só o *target* do Dockerfile e as variáveis de ambiente.

---

## 2. Decisões principais

| # | Decisão | Alternativas consideradas | Motivo |
|---|---------|---------------------------|--------|
| D1 | **Symfony 7.4 LTS** + PHP 8.4 | Symfony 8.0 | LTS tem suporte até 2028/2029, cobre o ciclo do TCC |
| D2 | **FrankenPHP** (template oficial `dunglas/symfony-docker`) | Nginx + PHP-FPM | 1 container em vez de 2, HTTPS automático (Caddy), *worker mode* rápido; é o setup recomendado na doc do Symfony |
| D3 | **Controllers próprios + Serializer** | API Platform | API Platform impõe JSON-LD/Hydra e convenções de rota; quebraria o contrato atual e o frontend |
| D4 | **Doctrine ORM + Migrations** | DBAL puro | Padrão do ecossistema; migrations versionadas |
| D5 | **UUID v7** como id | cuid (atual), autoincrement | Tipo nativo `uuid` no Postgres, ordenável por tempo, não expõe contagem |
| D6 | **Symfony Workflow** (`state_machine`) | Lógica manual no service | Transições, guards e histórico ficam declarados e testáveis; gera diagrama automático |
| D7 | **JWT curto (15 min) + refresh token em cookie httpOnly** | JWT de 7 dias no `localStorage` (atual) | Reduz impacto de token vazado; mudança de papel passa a valer em minutos |
| D8 | **Messenger com transporte Doctrine** | RabbitMQ / Redis | Reaproveita o Postgres; sem mais um serviço para manter |
| D9 | **Flysystem**, storage local privado | Pasta pública `/uploads` (atual) | Download passa por checagem de permissão; troca para S3/MinIO só por config |
| D10 | **Monorepo** (`api/` + `web/`) | Repositórios separados | Um `docker compose` só, versões de API e front andam juntas |

---

## 3. Containers Docker

### 3.1 Serviços

| Serviço | Imagem | Dev | Prod | Função |
|---------|--------|:---:|:----:|--------|
| `php` | build `api/Dockerfile` (FrankenPHP) | ✅ | ✅ | API Symfony + Caddy (HTTPS, proxy). Em prod também serve o build do React |
| `worker` | mesma imagem do `php` | ✅ | ✅ | `bin/console messenger:consume async` — e-mails e tarefas assíncronas |
| `database` | `postgres:16-alpine` | ✅ | ✅ | Banco de dados |
| `web` | `node:22-alpine` | ✅ | ❌ | Vite dev server com hot reload. Em prod vira um *stage* de build |
| `mailer` | `axllent/mailpit` | ✅ | ❌ | Captura e-mails em dev (UI em `localhost:8025`) |
| `adminer` | `adminer` | opcional | ❌ | Inspecionar o banco pelo navegador |

### 3.2 Arquivos de compose

```
compose.yaml            # base: php, worker, database (comum a todos os ambientes)
compose.override.yaml   # dev (carregado automaticamente): web, mailer, adminer,
                        # bind mounts do código, Xdebug, portas expostas
compose.prod.yaml       # prod: target "prod" do Dockerfile, sem bind mounts,
                        # secrets via variáveis de ambiente
```

### 3.3 Volumes e rede

| Volume | Montado em | Conteúdo |
|--------|-----------|----------|
| `database_data` | `/var/lib/postgresql/data` | Dados do Postgres |
| `uploads_data` | `/app/var/storage` | PDFs dos TCCs — **fora** da pasta pública |
| `caddy_data`, `caddy_config` | `/data`, `/config` | Certificados HTTPS |

Uma rede bridge padrão. Só o `php` (443/80) é exposto em prod. Em dev também ficam expostos `web` (5173), `database` (5432), `mailer` (8025) e `adminer` (8080).

### 3.4 Dockerfile multi-stage (`api/Dockerfile`)

```
frankenphp_base   → PHP 8.4 + extensões: pdo_pgsql, intl, opcache, zip, apcu
frankenphp_dev    → + Xdebug, composer, config de dev
web_build         → node:22: npm ci && npm run build (frontend)
frankenphp_prod   → composer install --no-dev, cache warmup,
                    copia web_build/dist para /app/public/app
```

### 3.5 Comandos (Makefile)

| Comando | Faz |
|---------|-----|
| `make up` / `make down` | Sobe / derruba o ambiente |
| `make sh` | Shell dentro do container `php` |
| `make db-reset` | Recria o banco, roda migrations e fixtures |
| `make migration` | `doctrine:migrations:diff` |
| `make test` | PHPUnit em banco de teste isolado |
| `make qa` | PHPStan + PHP-CS-Fixer + testes |
| `make logs` | Logs de todos os serviços |

---

## 4. Estrutura do repositório

```
hubtcc/
├── api/                          # Symfony
│   ├── bin/console
│   ├── config/
│   │   ├── packages/
│   │   │   ├── security.yaml
│   │   │   ├── workflow.yaml     # máquina de estados do TCC
│   │   │   ├── messenger.yaml
│   │   │   └── flysystem.yaml
│   │   └── routes.yaml
│   ├── migrations/
│   ├── src/
│   │   ├── Controller/           # finos: recebem DTO, chamam service, devolvem JSON
│   │   ├── Dto/                  # entrada (Request) e saída (Response)
│   │   ├── Entity/
│   │   ├── Enum/                 # Role, TccStatus, EvaluationVerdict, PeriodType
│   │   ├── Repository/
│   │   ├── Security/
│   │   │   ├── Voter/            # TccVoter: VIEW, EDIT, UPLOAD, REVIEW, EVALUATE, GRADE
│   │   │   └── GoogleAuthenticator.php
│   │   ├── Service/
│   │   │   ├── TccService.php
│   │   │   ├── EvaluationService.php   # consenso da banca
│   │   │   ├── DocumentStorage.php
│   │   │   └── PeriodChecker.php
│   │   ├── Workflow/             # guards e listeners do workflow
│   │   ├── Message/ + MessageHandler/  # notificações por e-mail
│   │   ├── EventListener/        # ExceptionListener → JSON de erro padronizado
│   │   └── DataFixtures/
│   ├── templates/email/
│   ├── tests/
│   │   ├── Unit/
│   │   └── Functional/           # WebTestCase cobrindo o fluxo completo
│   ├── Dockerfile
│   └── composer.json
├── web/                          # React atual (pasta frontend/ renomeada)
├── docker/                       # Caddyfile, php.ini, scripts de entrypoint
├── docs/
├── compose.yaml
├── compose.override.yaml
├── compose.prod.yaml
├── Makefile
└── .github/workflows/ci.yaml
```

**Camadas.** `Controller → Service → (Workflow, Repository, Storage) → Entity`. Os controllers não têm regra de negócio, e as entidades não conhecem HTTP. Toda autorização "pertence a este TCC?" fica no `TccVoter`.

---

## 5. Modelo de dados (PostgreSQL)

```mermaid
erDiagram
    users ||--o{ tccs : "aluno"
    users ||--o{ tccs : "orientador"
    users ||--o{ tcc_evaluators : "avalia"
    users ||--o{ tcc_status_history : "alterou"
    users ||--o{ refresh_tokens : ""
    tccs ||--o{ tcc_evaluators : ""
    tccs ||--o{ tcc_documents : "versões"
    tccs ||--o{ tcc_status_history : ""
    tccs }o--o{ thematic_areas : "tcc_thematic_areas"
    tccs }o--o{ keywords : "tcc_keywords"

    users {
        uuid id PK
        varchar name
        varchar email UK
        varchar password_hash "nullable"
        varchar google_id UK "nullable"
        varchar role
        timestamptz created_at
        timestamptz updated_at
    }
    tccs {
        uuid id PK
        varchar title
        text abstract
        varchar status
        boolean publishable
        numeric project_grade "4,2 nullable"
        numeric presentation_grade "4,2 nullable"
        uuid student_id FK
        uuid advisor_id FK "nullable"
        uuid current_document_id FK "nullable"
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
        timestamptz uploaded_at
    }
    tcc_evaluators {
        uuid id PK
        uuid tcc_id FK
        uuid evaluator_id FK
        varchar verdict
        text comment
        timestamptz updated_at
    }
    tcc_status_history {
        uuid id PK
        uuid tcc_id FK
        varchar from_status "nullable"
        varchar to_status
        varchar transition
        text comment
        uuid changed_by_id FK "nullable"
        timestamptz created_at
    }
    submission_periods {
        uuid id PK
        varchar type
        varchar label
        timestamptz start_date
        timestamptz end_date
    }
    thematic_areas {
        uuid id PK
        varchar name UK
    }
    keywords {
        uuid id PK
        varchar name UK
    }
    refresh_tokens {
        int id PK
        varchar refresh_token UK
        varchar username
        timestamptz valid
    }
```

**Mudanças em relação ao schema atual**

| Mudança | Por quê |
|---------|---------|
| `tcc_documents` (nova) substitui `documentUrl` + `documentVersion` | Mantém as versões anteriores do PDF; a banca pode comparar versões |
| Notas `numeric(4,2)` em vez de `float` | Evita erro de arredondamento |
| `timestamptz` em todas as datas | Períodos de submissão comparados corretamente com fuso horário |
| Enums como `varchar` + PHP `enum` (Doctrine `enumType`) | Adicionar status não exige `ALTER TYPE` no Postgres |
| Status `ADVISOR_REVIEW` removido | Existe no enum atual mas nunca é usado |
| `transition` no histórico | Registra qual ação gerou a mudança (útil para auditoria) |
| `refresh_tokens` (nova) | Suporte ao refresh token (D7) |
| Índices em `tccs(student_id)`, `tccs(advisor_id)`, `tccs(status)`, `tcc_evaluators(evaluator_id)`, `submission_periods(type, start_date, end_date)` | Listagens por papel e checagem de período |

`ON DELETE CASCADE` mantido onde já existe (relações N:N, avaliadores, histórico). Usuário com TCC/avaliação/histórico **não** é excluído (FK `RESTRICT`), mesma regra de hoje.

---

## 6. Máquina de estados do TCC

```mermaid
stateDiagram-v2
    [*] --> DRAFT
    DRAFT --> SUBMITTED : submit
    SUBMITTED --> SENT_TO_EVALUATION : advisor_approve
    SUBMITTED --> REJECTED : advisor_reject
    RESUBMITTED --> SENT_TO_EVALUATION : advisor_approve
    RESUBMITTED --> REJECTED : advisor_reject
    SENT_TO_EVALUATION --> ADVISOR_FINAL_REVIEW : bank_approve
    SENT_TO_EVALUATION --> CORRECTIONS_REQUESTED : bank_request_corrections
    CORRECTIONS_REQUESTED --> RESUBMITTED : resubmit
    ADVISOR_FINAL_REVIEW --> APPROVED : final_approve
    ADVISOR_FINAL_REVIEW --> CORRECTIONS_REQUESTED : final_request_corrections
    APPROVED --> [*]
    REJECTED --> [*]
```

| Transição | Quem dispara | Guard (condição) |
|-----------|-------------|------------------|
| `submit` / `resubmit` | Aluno autor | Tem PDF **e** orientador definido |
| `advisor_approve` | Orientador do TCC | **Banca com ≥ 1 avaliador** *(novo — hoje o TCC trava sem banca)* |
| `advisor_reject` | Orientador do TCC | — |
| `bank_approve` | Sistema | Todos os avaliadores votaram e todos aprovaram |
| `bank_request_corrections` | Sistema | Todos votaram e ≥ 1 pediu correção |
| `final_approve` / `final_request_corrections` | Orientador do TCC | — |

**Listeners do workflow**

- `workflow.tcc.completed`: grava `tcc_status_history` **na mesma transação** da mudança de status. Hoje são duas escritas separadas.
- `workflow.tcc.entered.SENT_TO_EVALUATION`: zera os pareceres da banca (`PENDING`).
- `workflow.tcc.entered.*`: despacha `TccStatusChanged` no Messenger, que envia e-mail aos envolvidos.

**Consenso da banca (`EvaluationService`)**

```
BEGIN
  SELECT … FROM tccs WHERE id = :id FOR UPDATE      -- lock pessimista
  UPDATE tcc_evaluators SET verdict, comment
  se ninguém PENDING → aplica bank_approve ou bank_request_corrections
COMMIT
```

O mesmo recálculo roda quando a coordenação **altera a banca** (`PUT /tcc/{id}/evaluators`). Hoje, se o avaliador que faltava votar for removido, o TCC fica travado.

**Regras fora do workflow** (validadas no service ou no Voter)

- Criar TCC: só durante período `SUBMISSION` ativo.
- Votar: só durante período `EVALUATION` ativo.
- Editar ou enviar PDF: só em `DRAFT` ou `CORRECTIONS_REQUESTED` (aluno). A coordenação pode editar sempre.
- Lançar notas: só em `ADVISOR_FINAL_REVIEW` ou `APPROVED` *(novo)*.
- Avaliação cega: o avaliador com voto `PENDING` não vê os votos dos outros (feito no serializer de saída).

---

## 7. API

Prefixo `/api` em todas as rotas (o Caddy separa API e SPA no mesmo domínio). Corpo e resposta em JSON, sem envelope, iguais ao contrato atual.

| Método | Rota | Papel | Observação |
|--------|------|-------|-----------|
| POST | `/api/auth/register` | público | Sempre cria `STUDENT` |
| POST | `/api/auth/login` | público | Retorna `accessToken` + cookie de refresh |
| POST | `/api/auth/refresh` | cookie | *novo* |
| POST | `/api/auth/logout` | autenticado | *novo* — invalida refresh |
| GET | `/api/auth/google` · `/api/auth/google/callback` | público | Callback redireciona **sem token na URL**: seta o cookie de refresh e o front chama `/refresh` |
| GET | `/api/users?role=` | autenticado | Sem `role`, só coordenação |
| POST · PATCH `/{id}/role` · DELETE `/{id}` | `/api/users` | COORDINATION | Não remove o último coordenador nem a si mesmo |
| GET · POST · PUT · DELETE | `/api/thematic-areas`, `/api/keywords`, `/api/periods` | leitura: todos · escrita: COORDINATION | |
| GET | `/api/tcc` | autenticado | Filtrado pelo papel; *novo:* `?status=&page=` |
| POST | `/api/tcc` | STUDENT | |
| GET · PATCH | `/api/tcc/{id}` | Voter `VIEW` / `EDIT` | |
| POST | `/api/tcc/{id}/document` | Voter `UPLOAD` | Multipart, PDF até 20 MB, valida *magic bytes* |
| GET | `/api/tcc/{id}/document[/{version}]` | Voter `VIEW` | *novo* — download protegido |
| POST | `/api/tcc/{id}/submit` | Voter `EDIT` | `submit` ou `resubmit` conforme o status |
| PUT | `/api/tcc/{id}/evaluators` | COORDINATION | Recalcula consenso |
| POST | `/api/tcc/{id}/advisor-review` | Voter `REVIEW` | `{ approve, comment }` → transição conforme o status |
| POST | `/api/tcc/{id}/evaluate` | Voter `EVALUATE` | |
| POST | `/api/tcc/{id}/grades` | Voter `GRADE` | |

**Erros:** um `ExceptionListener` converte exceções em `{"statusCode": 400, "message": "…"}`, no formato que o NestJS devolve hoje e o frontend já exibe. Erros de validação retornam `message` como lista.

**Download do PDF.** O frontend hoje abre o PDF com um `<a href>` simples, que não envia o header `Authorization`. Por isso, o campo `documentUrl` da resposta passa a ser uma **URL assinada com validade de 5 minutos** (`UriSigner` do Symfony), gerada a cada leitura do TCC. Assim nada muda no frontend, e o link vazado expira.

---

## 8. Segurança

| Item | Implementação |
|------|---------------|
| Senhas | `password_hashers: auto` (bcrypt/argon2id) |
| Access token | LexikJWT, RS256, **15 min**, payload `{sub, email, role}` |
| Refresh token | `gesdinet/jwt-refresh-token-bundle`, cookie `httpOnly; Secure; SameSite=Lax`, 7 dias, rotação a cada uso |
| Google | KnpUOAuth2ClientBundle; só vincula por e-mail se `email_verified = true` |
| Autorização | `#[IsGranted('ROLE_…')]` para papel e `TccVoter` para posse/estado |
| CORS | `nelmio/cors-bundle`; em prod não é necessário (mesmo domínio) |
| Rate limit | `symfony/rate-limiter` em `/auth/login` e `/auth/register` |
| Uploads | Constraint `File(mimeTypes: ['application/pdf'], maxSize: '20M')` (lê o conteúdo real), nome gerado por UUID, armazenado fora de `public/` |
| Secrets | `.env` só com valores de dev; em prod variáveis de ambiente / `secrets:set` |

O frontend precisa de **uma** mudança: um interceptor do axios que, ao receber 401, chama `/auth/refresh` e repete a requisição. Também deixa de guardar o token no `localStorage` e passa a mantê-lo em memória.

---

## 9. Assíncrono e notificações

```
Workflow (entered.*) ──dispatch──▶ TccStatusChanged ──(Doctrine transport)──▶ worker ──▶ Mailer
```

| Evento | Destinatário |
|--------|-------------|
| TCC submetido / reenviado | Orientador |
| Enviado para a banca | Avaliadores |
| Correções solicitadas | Aluno |
| Revisão final pendente | Orientador |
| Aprovado / rejeitado | Aluno |
| Incluído em uma banca | Avaliador |

Falhas vão para o transporte `failed`, com retry 3x e backoff. Em dev, todos os e-mails aparecem no Mailpit.

---

## 10. Qualidade e CI

- **PHPStan** nível 8 + `phpstan-symfony` + `phpstan-doctrine`
- **PHP-CS-Fixer** com regras `@Symfony`
- **PHPUnit 11**, com **zenstruck/foundry** para fábricas e `dama/doctrine-test-bundle` para isolar cada teste numa transação
- **Testes funcionais obrigatórios:** o fluxo completo do README (aluno → orientador → banca com 2 avaliadores → correção → reenvio → aprovação → notas), avaliação cega e os dois casos de TCC travado
- **GitHub Actions:** `docker compose build` → `make qa` em cada push ou PR

---

## 11. Ambientes e variáveis

| Variável | Dev (padrão) | Prod |
|----------|-------------|------|
| `APP_ENV` | `dev` | `prod` |
| `APP_SECRET` | fixo no `.env` | gerado |
| `DATABASE_URL` | `postgresql://app:app@database:5432/hubtcc?serverVersion=16` | secret |
| `JWT_PASSPHRASE` + chaves em `config/jwt/` | geradas no 1º `make up` | secret / volume |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | vazio (login Google desativado) | secret |
| `MAILER_DSN` | `smtp://mailer:1025` | provedor SMTP |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default` | `doctrine://default` |
| `SERVER_NAME` | `localhost` | domínio real |
| `STORAGE_DIR` | `%kernel.project_dir%/var/storage` | idem (volume) |

---

## 12. Ordem de implementação

| Fase | Entrega | Pronto quando |
|------|---------|---------------|
| 0 · Infra | Compose, Dockerfile, Makefile, Symfony vazio, CI | `make up` sobe tudo e `make qa` passa |
| 1 · Dados | Entidades, enums, migrations, fixtures | `make db-reset` cria o banco com os 5 usuários de teste |
| 2 · Auth | Login, registro, JWT + refresh, Google, Voter base | Frontend faz login apontando para o Symfony |
| 3 · Cadastros | users, thematic-areas, keywords, periods | Telas da coordenação funcionam |
| 4 · TCC | Workflow, consenso, upload/download, notas | Fluxo completo do README funciona pelo frontend |
| 5 · Extras | E-mails, rate limit, paginação | Testes funcionais verdes |
| 6 · Corte | Remover NestJS, renomear `frontend/` → `web/`, atualizar README | Só o Symfony no repositório |

---

## 13. Fora do escopo desta migração (backlog)

- Repositório público de TCCs aprovados com `publishable = true`
- Recuperação de senha e verificação de e-mail
- Armazenamento em S3/MinIO (já preparado via Flysystem)
- Revisão do painel 3D (`three.js`) do frontend
