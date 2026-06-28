# Sistema EBD

Base em PHP para gestao da Escola Biblica Dominical.

## Requisitos

- PHP 8.2+
- Extensoes PHP: `pdo` e `pdo_sqlite`
- Para producao persistente: Postgres com `pdo_pgsql`

## Como rodar localmente

1. Inicialize o banco:

```bash
php scripts/init-db.php
```

2. Suba o servidor local:

```bash
php -S localhost:8000 -t public public/router.php
```

Ou use o atalho:

```bash
./start.sh
```

3. Acesse:

```text
http://localhost:8000
```

Usuario inicial:

- E-mail: `admin@ebd.local`
- Senha: `admin123`

## Transcricao de audio

Para usar gravacao de audio, transcricao e parecer gerado por IA, salve sua chave em:

```text
Configuracoes > OpenAI
```

Tambem e possivel usar `OPENAI_API_KEY` como fallback no terminal, mas nao e mais necessario se a chave estiver salva no sistema.

## Publicacao

### GitHub

Crie um repositorio vazio no GitHub e envie este projeto:

```bash
git remote add origin git@github.com:SEU_USUARIO/sistema-ebd.git
git branch -M main
git push -u origin main
```

### Vercel

O projeto inclui `vercel.json`, `api/index.php` e um workflow em `.github/workflows/vercel.yml` para publicar automaticamente no Vercel quando houver push na branch `main`.

No GitHub, configure estes segredos em `Settings > Secrets and variables > Actions`:

```text
VERCEL_TOKEN
VERCEL_ORG_ID
VERCEL_PROJECT_ID
```

No Vercel, configure estas variaveis de ambiente do projeto:

```text
EBD_JWT_SECRET=um-segredo-longo-e-seguro
```

`OPENAI_API_KEY` e opcional como fallback. Se o banco persistente estiver configurado, a chave pode ser salva diretamente em `Configuracoes > OpenAI`.

Para armazenamento persistente no Vercel, conecte um banco Postgres ao projeto. O sistema usa automaticamente uma destas variaveis quando estiver configurada:

```text
DATABASE_URL
POSTGRES_URL_NON_POOLING
POSTGRES_URL
POSTGRES_PRISMA_URL
```

Sem uma dessas variaveis, o Vercel usa SQLite em `/tmp/ebd.sqlite`, adequado apenas para demo/preview. Nesse modo temporario, dados e configuracoes salvas no sistema podem se perder em redeploys ou reinicializacoes.

## O que ja esta implementado

- Estrutura PHP sem framework.
- Banco SQLite para desenvolvimento.
- Autenticacao JWT.
- Usuario administrador inicial.
- CRUD de cursos.
- CRUD de pessoas.
- Tela de secretaria para registrar aula dominical, professor e presenca dos alunos.
- Relatorios pedagogicos por aluno, com gravacao de audio e transcricao pela OpenAI.
- Padrao visual de listagem com acoes em uma linha e modal para criar/editar.

## Proximos passos da ordem combinada

1. Relatorios pedagogicos.
