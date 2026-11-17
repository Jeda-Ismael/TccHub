# Colocando o hubtcc num servidor

Este guia é para colocar o sistema num servidor na internet. A configuração já está pronta e é testada a cada push na CI (job **Produção**). Falta só o servidor e um domínio.

## Como a produção é montada

Um único container (`php`) entrega tudo pelo mesmo domínio, com HTTPS automático:

| Endereço | O que responde |
|----------|----------------|
| `https://seu-dominio/api/...` | API Symfony |
| `https://seu-dominio/...` | Frontend React já compilado (o build entra na imagem) |

Diferenças em relação ao ambiente local:

- **Sem Vite, sem Mailpit e sem código montado do disco:** o código e o frontend compilado ficam dentro da imagem.
- **HTTPS automático:** o Caddy, embutido no FrankenPHP, pede o certificado ao Let's Encrypt usando o `SERVER_NAME`.
- **Segredos fora do código:** ficam no arquivo `.env.prod`, que nunca vai para o git.
- **Dados em volumes do Docker**, que sobrevivem a atualizações:

| Volume | Conteúdo |
|--------|----------|
| `database_data` | banco PostgreSQL |
| `uploads_data` | PDFs dos TCCs |
| `jwt_keys` | chaves de login |
| `caddy_data` | certificados HTTPS |

## Pré-requisitos

- Um servidor Linux com **Docker** e o plugin **Compose**: VPS (Hetzner, DigitalOcean, Oracle Cloud Free Tier) ou uma máquina da faculdade.
- Um **domínio ou subdomínio** com registro DNS tipo **A** apontando para o IP do servidor.
- As portas **80 e 443** liberadas no firewall.

## Primeira instalação

```bash
git clone https://github.com/Jeda-Ismael/TccHub.git
cd TccHub

cp .env.prod.example .env.prod
nano .env.prod        # preencha domínio e segredos (openssl rand -hex 32 gera valores)

make prod-up          # ou: docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod up -d --build --wait
```

A primeira subida compila o frontend, instala as dependências e roda as migrations. Leva alguns minutos.

Em produção **não há usuários de teste**. Crie o primeiro coordenador; ele cadastra o resto pela tela:

```bash
make prod-user c='"Carla Souza" carla@faculdade.edu.br --role=COORDINATION'
# a senha é perguntada sem aparecer na tela
```

Pronto: acesse `https://seu-dominio`.

## Atualizar para uma nova versão

```bash
git pull
make prod-up
```

As migrations novas rodam sozinhas na subida, e os dados nos volumes são mantidos.

## Backup

```bash
# Banco
docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod exec -T database \
  pg_dump -U app hubtcc > backup-$(date +%F).sql

# PDFs
docker run --rm -v tcchub_uploads_data:/dados -v "$PWD":/backup alpine \
  tar czf /backup/uploads-$(date +%F).tar.gz -C /dados .
```

Guarde os dois arquivos fora do servidor.

O mesmo num comando só: `make prod-backup` grava `backups/banco-AAAA-MM-DD-HHMM.sql.gz` e `backups/pdfs-AAAA-MM-DD-HHMM.tar.gz`. Para rodar todo dia às 3h, ponha no `crontab -e` do servidor:

```
0 3 * * * cd /home/deploy/TccHub && make prod-backup >/dev/null 2>&1
```

Para restaurar o banco:

```bash
gunzip -c backups/banco-AAAA-MM-DD-HHMM.sql.gz | docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod \
  exec -T database sh -c 'psql -U "$POSTGRES_USER" "$POSTGRES_DB"'
```

## Deploy automático (opcional)

O workflow `.github/workflows/deploy.yaml` atualiza o servidor sozinho sempre que a CI passa na `main`: faz um backup, puxa o código, roda `make prod-up` e no fim confere `/api/health`. Ele fica desligado até você configurar:

1. No servidor, crie um usuário só para o deploy (ex.: `deploy`) com acesso ao Docker, e faça a primeira instalação acima com ele.
2. Gere uma chave só para isso: `ssh-keygen -t ed25519 -f deploy_key -N ""`. Ponha o conteúdo de `deploy_key.pub` no `~/.ssh/authorized_keys` do usuário `deploy`.
3. No GitHub, em **Settings > Secrets and variables > Actions**:
   - **Variables:** `DEPLOY_HOST` (o domínio), `DEPLOY_USER` (`deploy`) e `DEPLOY_PATH` (pasta do clone, ex.: `/home/deploy/TccHub`).
   - **Secrets:** `DEPLOY_SSH_KEY` (o conteúdo de `deploy_key`) e `DEPLOY_KNOWN_HOSTS` (a saída de `ssh-keyscan seu-dominio`).
4. Em **Settings > Environments**, crie o ambiente `production`. Se quiser aprovar cada deploy antes de ele rodar, marque *Required reviewers*.

Para disparar à mão: **Actions > Deploy > Run workflow**.

## Comandos úteis

| Comando | Faz |
|---------|-----|
| `make prod-logs` | acompanha os logs |
| `make prod-backup` | copia o banco e os PDFs para `backups/` |
| `make prod-down` | derruba (os dados ficam nos volumes) |
| `make prod-user c='...'` | cria usuário pelo terminal |

## E-mails

Os avisos do sininho também vão por e-mail, junto com o link de "esqueci minha senha" e o lembrete de notas da defesa. Quem envia é o serviço `worker`, que sobe junto com a API e consome a fila (tabela `messenger_messages` no próprio PostgreSQL).

No `.env.prod`, configure:

| Variável | Exemplo |
|----------|---------|
| `MAILER_DSN` | `smtp://usuario:senha@smtp-relay.brevo.com:587` (Brevo, Mailgun, o SMTP da faculdade...) |
| `MAILER_FROM` | `hubtcc <nao-responda@suafaculdade.edu.br>` |

Sem `MAILER_DSN`, os e-mails são descartados (o sininho continua funcionando). Um e-mail que falha é tentado de novo 3 vezes; depois disso fica na fila `failed`. Para ver e reenviar: `docker compose exec worker php bin/console messenger:failed:show` e `messenger:failed:retry`.
