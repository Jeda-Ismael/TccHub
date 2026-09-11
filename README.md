# hubtcc — Sistema de Cadastro de TCC

Sistema de cadastro e acompanhamento de Trabalhos de Conclusão de Curso, inspirado no fluxo de negócio do [deposito-tcc](https://github.com/pcalvesUSP/deposito-tcc) (USP), reimplementado sem nenhuma dependência de infraestrutura institucional.

Papéis: **Aluno**, **Orientador**, **Avaliador** (banca) e **Coordenação**. Fluxo: o aluno cadastra o TCC e envia o PDF, o orientador aprova para a banca, cada avaliador dá seu parecer (o sistema só avança de status depois que **todos** os avaliadores se manifestarem — se algum pedir correção, o aluno reenvia e o ciclo se repete), e o orientador faz a aprovação final e lança as notas.

## Stack

- **Backend**: NestJS + TypeScript + Prisma + MySQL, autenticação JWT + Google OAuth
- **Frontend**: React (Vite) + TypeScript + React Query + React Router

## Rodando localmente

### 1. Banco de dados

```bash
docker compose up -d
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
backend/    NestJS (API REST, Prisma, upload de PDF em disco local)
frontend/   React + Vite (SPA consumindo a API)
```

Veja `backend/src` para os módulos (`auth`, `users`, `tcc`, `storage`, `thematic-areas`, `keywords`, `periods`) e `frontend/src/pages` para as telas de cada papel.
