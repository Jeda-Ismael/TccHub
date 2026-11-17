# hubtcc — Sistema de Cadastro de TCC

Sistema de cadastro e acompanhamento de Trabalhos de Conclusão de Curso, do envio do PDF até a defesa e a publicação num repositório. Inspirado no fluxo de negócio do [deposito-tcc](https://github.com/pcalvesUSP/deposito-tcc) (USP), reimplementado sem dependência de infraestrutura institucional.

**Papéis:** Aluno, Orientador, Avaliador (banca) e Coordenação. Um professor pode acumular Orientador, Avaliador e Coordenação e trocar de papel no topo da tela ("Ver como").

**Fluxo:**

1. O aluno cadastra o TCC, envia o PDF e submete.
2. A coordenação monta a banca.
3. O orientador aprova o envio à banca.
4. Cada avaliador dá o parecer às cegas. O TCC só avança quando **todos** se manifestam; se alguém pedir correções, o aluno reenvia e a rodada recomeça.
5. O orientador faz a revisão final e agenda a defesa.
6. Depois da defesa, cada avaliador lança as notas de projeto e de apresentação. Com média mínima (configurável, 6,0), o TCC é aprovado; abaixo dela, a coordenação decide entre uma nova defesa e a reprovação.
7. Com o TCC aprovado, o aluno autoriza a publicação e a coordenação publica no **repositório**, com busca para qualquer usuário logado.

Avisos de cada etapa chegam no **sininho** do topo da tela.

## Stack

- **API** (`api/`): Symfony 7.4 + Doctrine + PostgreSQL 16, servida por FrankenPHP (Caddy) no Docker.
  - Workflow do Symfony para o ciclo do TCC e Voters para as permissões.
  - Login com JWT e refresh em cookie httpOnly.
- **Web** (`web/`): React 18 + Vite + TypeScript + React Query + React Router.
- **Qualidade:** PHPUnit, PHPStan nível 8, PHP-CS-Fixer, Vitest e Testing Library. A CI exige no mínimo 85% de cobertura na API e na web.

Arquitetura e decisões: [`docs/ARQUITETURA.md`](docs/ARQUITETURA.md). Como subir num servidor: [`docs/DEPLOY.md`](docs/DEPLOY.md).

## Rodando no computador

Pré-requisito: [Docker Desktop](https://www.docker.com/products/docker-desktop/) (ou Docker com Compose v2.10+). O `make` é opcional; cada comando abaixo também está em versão `docker compose`, que funciona no Windows sem `make`.

### Demonstração rápida (com dados de exemplo)

| Com `make` | Sem `make` |
|---|---|
| `make demo` | `docker compose up -d --wait`<br>`docker compose exec php bin/console doctrine:migrations:migrate -n`<br>`docker compose exec php bin/console doctrine:fixtures:load -n --group=demo` |

Isso sobe tudo e carrega:
- usuários de teste;
- TCCs de outros alunos em vários pontos do fluxo (em revisão, na banca, com defesa agendada);
- quatro TCCs aprovados e já **publicados no repositório**.

⚠️ A carga **apaga os dados atuais** do banco.

Abra **http://localhost:5173** e entre com uma das contas (senha `123456` para todas):

| Conta | Papel |
|---|---|
| `aluno@hubtcc.dev` · `aluno2@hubtcc.dev` | Aluno, sem TCC (para testar o fluxo do zero) |
| `orientador@hubtcc.dev` | Orientador |
| `avaliador1@hubtcc.dev` · `avaliador2@hubtcc.dev` | Avaliador |
| `coordenacao@hubtcc.dev` | Coordenação |
| `professor@hubtcc.dev` | Orientador + Avaliador + Coordenação (seletor "Ver como") |

Para começar só com os usuários, sem TCCs, use `make fixtures`, ou `docker compose exec php bin/console doctrine:fixtures:load -n --group=base`.

### Endereços

| Serviço | Endereço |
|---|---|
| Sistema | http://localhost:5173 |
| API | https://localhost/api/health (aceite o certificado local na primeira vez) |
| Documentação da API (Swagger) | https://localhost/api/docs · o JSON do OpenAPI fica em `/api/docs.json` |
| PostgreSQL | `localhost:5432` · usuário `app` · senha `app` · banco `hubtcc` |
| Adminer (opcional) | `docker compose --profile tools up -d adminer` → http://localhost:8080 |
| Mailpit (caixa de entrada dos e-mails de teste) | http://localhost:8025 |

### Roteiro para testar o fluxo completo

1. Como `aluno@`, cadastre o TCC, envie um PDF e submeta.
2. Como `coordenacao@`, abra o TCC e monte a banca com Arthur e Alice.
3. Como `orientador@`, aprove e envie à banca.
4. Como `avaliador1@` e `avaliador2@`, dê os pareceres.
5. Se alguém pediu correções, o TCC volta ao aluno. Se todos aprovaram, vai para a revisão final do orientador e depois para "Aguardando defesa".
6. O orientador agenda a defesa. Para testar na hora, marque um horário que já passou.
7. Cada avaliador lança as notas de projeto e apresentação. A média sai sozinha: acima da mínima, o TCC é **aprovado**; abaixo, a coordenação decide.
8. O aluno clica em **Autorizar publicação**. A coordenação recebe o aviso no sininho e clica em **Publicar no repositório**.
9. Qualquer usuário encontra o TCC em **Repositório**, no topo. A busca entende português ("fraude" acha "fraudes") e ignora acentos.

### Ligar sozinho junto com o computador

Depois da primeira subida, os containers voltam sozinhos sempre que o Docker inicia:

1. Docker Desktop → **Settings** → **General** → marque **"Start Docker Desktop when you sign in to your computer"**.
2. Ao ligar o PC, em cerca de 1 minuto o http://localhost:5173 já está no ar.

Para parar: `docker compose down`. Os dados continuam salvos. Para voltar: `docker compose up -d`.

### Depois de um `git pull`

Se vieram bibliotecas ou migrations novas:

```bash
docker compose up -d --force-recreate php
docker compose exec php bin/console doctrine:migrations:migrate -n
```

## Desenvolvimento

`make help` lista todos os comandos. Os principais:

| Comando | O que faz |
|---|---|
| `make up` / `make down` | Sobe / derruba o ambiente |
| `make sh` | Shell no container da API |
| `make db-reset` | Recria o banco com os dados base |
| `make test` | Testes da API |
| `make coverage` | Testes da API com relatório HTML, copiado para `api/var/coverage/index.html` |
| `make web-test` | Tipos e testes da web |
| `make qa` | Tudo o que a CI roda: estilo, PHPStan, testes e cobertura mínima (API e web) |

## Estrutura

```
api/     Symfony: entidades, workflow do TCC, voters, serviços, busca e testes
web/     React + Vite: telas de cada papel, sininho e repositório
docs/    ARQUITETURA.md (decisões, modelo, fluxo, fases) e DEPLOY.md (servidor)
```

Telas por papel: `web/src/pages/{student,advisor,evaluator,coordination,repository}`.
