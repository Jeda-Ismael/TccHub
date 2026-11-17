# Colocando o hubtcc num servidor

Hoje o sistema roda só no computador local. Este guia é para quando ele for para um servidor na internet. A configuração já está pronta e é testada a cada push na CI (job **Produção**). Falta só o servidor e um domínio.

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

Guarde os dois arquivos fora do servidor. Um `cron` diário com esses comandos já resolve.

## Comandos úteis

| Comando | Faz |
|---------|-----|
| `make prod-logs` | acompanha os logs |
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
