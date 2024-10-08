This tool is deployed to toolforge.

https://wikicrowd.toolforge.org/

With an OAuth consumer to Wikimedia production

https://meta.wikimedia.org/w/index.php?title=Special:OAuthListConsumers/view/b7e6badde4982d44e053ca0a4fdde3ca&name=&publisher=&stage=0

We recommend that you use the mwcli tool for convenience :)
See https://www.mediawiki.org/wiki/Cli/ref/mw_tools

## Updating code

You can run the update script from your machine using mwcli.

```sh
mw tools exec --tool=wikicrowd ./src/toolforge/update.sh
```

## Live debugging

If you cant find things in the logs, consider changing the Laravel config to show debug mode in `~/public_html/config`.

## Running scripts

You can run artisan scripts from your local machine using mwcli.

```sh
mw tools exec --tool=wikicrowd -- webservice php8.2 shell -- php ./src/artisan
```

### Removing questions

```sh
mw tools exec --tool=wikicrowd -- webservice php8.2 shell -- php ./src/artisan job:dispatchNow RemoveUnansweredQuestions depicts/Q34486
```

### Question generation

General question generation is done by an built in CRON.

You can also trigger some question generation manually.

```sh
mw tools exec --tool=wikicrowd -- webservice php8.2 shell -- php ./src/artisan job:dispatchNow GenerateAliasQuestions enwiki 200
mw tools exec --tool=wikicrowd -- webservice php8.2 shell -- php ./src/artisan job:dispatchNow GenerateAliasQuestions dewiki 100
mw tools exec --tool=wikicrowd -- webservice php8.2 shell -- php ./src/artisan job:dispatchNow GenerateAliasQuestions plwiki 100
```

For depicts right now you need to get a bunch of info from the YAML files in the spec dir...

```sh
mw tools exec --tool=wikicrowd -- webservice php8.2 shell -- php ./src/artisan job:dispatchNow GenerateDepictsQuestions Category:Gliders Category:Motorgliders "/(Videos|art|drawings|Models|engines|components|landing gear|views from|Orthophotos)/i" Q2165278 Glider 3
```

## Initial setup

This only has to be run the very first time the tool is setup, thus it is already done!

```sh
ssh login.toolforge.org
become wikicrowd
git clone https://github.com/addshore/wikicrowd.git ~/src/
cp ~/src/toolforge/lighttpd.conf ~/.lighttpd.conf
cp ~/src/toolforge/service.template ~/service.template
webservice php8.2 shell -- composer install --working-dir=./src
```

You'll also need to manually create a and configure a `.env.web` file.

```sh
cp ~/src/.env.example ~/src/.env
```

Once code is setup You'll also want to generate a key:

```sh
webservice php8.2 shell -- php ./src/artisan key:generate
```

And modify the other needed env vars!!!

https://wikitech.wikimedia.org/wiki/Help:Toolforge/Database
https://wikitech.wikimedia.org/wiki/Help:Toolforge/Redis_for_Toolforge

### Database

SQL is primiarly used.

```sh
mw tools exec --tool=wikicrowd sql tools
```

Then

```sql
CREATE DATABASE <user>__laravel;
```

You should then be able to make Laravel install the tables using `artisan migrate`.

Note: You'll need to run this from within an actual shell session due to confirmations.

### Webservice

Setup the hosted directory

```sh
mkdir -p ~/public_html
rsync -av --delete ~/src/ ~/public_html
```

And start or restart the service

```
webservice restart
```

### Queue

To make the deployment:

```sh
kubectl create --validate=true -f ~/deployment.yaml
```

To stop it

```sh
kubectl delete deployment laravel.queue
```
