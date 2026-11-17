# hubtcc — Sistema de Cadastro de TCC

Sistema de cadastro e acompanhamento de Trabalhos de Conclusão de Curso, inspirado no fluxo de negócio do [deposito-tcc](https://github.com/pcalvesUSP/deposito-tcc) (USP), reimplementado sem nenhuma dependência de infraestrutura institucional.

Papéis: **Aluno**, **Orientador**, **Avaliador** (banca) e **Coordenação**. Fluxo: o aluno cadastra o TCC e envia o PDF, o orientador aprova para a banca, cada avaliador dá seu parecer (o sistema só avança de status depois que **todos** os avaliadores se manifestarem — se algum pedir correção, o aluno reenvia e o ciclo se repete), e o orientador faz a aprovação final e lança as notas.

## Stack

> **Em migração:** o backend está sendo reescrito em **Symfony 7.4 + PostgreSQL**, rodando todo no Docker (pasta `api/`). O plano completo está em [`docs/ARQUITETURA.md`](docs/ARQUITETURA.md). Enquanto a migração não termina, o backend NestJS (`backend/`) continua funcionando como antes.

- **Backend (novo)**: Symfony 7.4 + Doctrine + PostgreSQL 16, servido por FrankenPHP
- **Backend (atual)**: NestJS + TypeScript + Prisma + MySQL, autenticação JWT + Google OAuth
- **Frontend**: React (Vite) + TypeScript + React Query + React Router

## Rodando o novo backend (Docker)

Pré-requisitos: Docker (com Compose v2.10+) e `make`.

```bash
make build   # constrói as imagens (só na primeira vez ou quando o Dockerfile mudar)
make up      # sobe API, PostgreSQL, frontend e Mailpit
```

| Serviço | Endereço |
|---------|----------|
| API | https://localhost/api/health (aceite o certificado local na primeira vez) |
| Frontend | http://localhost:5173 |
| Mailpit (e-mails de teste) | http://localhost:8025 |
| PostgreSQL | `localhost:5432` · usuário `app` · senha `app` · banco `hubtcc` |
| Adminer (opcional) | `docker compose --profile tools up -d adminer` → http://localhost:8080 |

> As telas do frontend dependem dos endpoints que serão migrados nas próximas fases; até a Fase 2 (autenticação), use o backend NestJS para testar o sistema.

Comandos úteis: `make help` lista tudo; `make qa` roda estilo, PHPStan e testes; `make sh` abre um shell na API.

## Rodando o backend atual (NestJS)

### 1. Banco de dados

```bash
docker compose -f backend/docker-compose.yml up -d
```

Sobe um MySQL em `localhost:3306` (usuário `root`, senha `root`, banco `hubtcc`).

### 2. Backend

```bash
cd backend
cp .env.example .env
npm install
npx prisma migrate dev
npx prisma db seed
npm run start:dev
```

O backend sobe em `http://localhost:3000`. O seed cria um usuário de cada papel (senha `123456` para todos):

- `aluno@hubtcc.dev`
- `orientador@hubtcc.dev`
- `avaliador1@hubtcc.dev` / `avaliador2@hubtcc.dev`
- `coordenacao@hubtcc.dev`

Para o login com Google funcionar de verdade, preencha `GOOGLE_CLIENT_ID` e `GOOGLE_CLIENT_SECRET` no `.env` com credenciais de um projeto no Google Cloud Console (OAuth consent screen + credencial "Web application", com redirect URI `http://localhost:3000/auth/google/callback`).

### 3. Frontend

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```

Sobe em `http://localhost:5173`.

## Fluxo de teste sugerido

1. Login como aluno → cadastrar TCC → enviar PDF → submeter
2. Login como orientador → aprovar (o TCC vai para "em avaliação pela banca")
3. Login como coordenação → aba "TCCs" → abrir o TCC → montar a banca avaliadora
4. Login como cada avaliador → dar parecer (aprovar / solicitar correção)
5. Quando todos derem parecer: se algum pediu correção, o TCC volta para o aluno com os comentários; se todos aprovarem, vai para a revisão final do orientador
6. Orientador dá aprovação final e lança as notas

## Estrutura

```
api/        Symfony (novo backend, em construção)
backend/    NestJS (API REST, Prisma, upload de PDF em disco local)
frontend/   React + Vite (SPA consumindo a API)
docs/       Arquitetura e decisões
```

Veja `backend/src` para os módulos (`auth`, `users`, `tcc`, `storage`, `thematic-areas`, `keywords`, `periods`) e `frontend/src/pages` para as telas de cada papel.
