This tool is deployed to toolforge.

https://wikicrowd.toolforge.org/

With an OAuth consumer to Wikimedia production

https://meta.wikimedia.org/w/index.php?title=Special:OAuthListConsumers/view/b7e6badde4982d44e053ca0a4fdde3ca&name=&publisher=&stage=0

## Question generation

I should seutp a cron for this...
But for now I'll need to login and do the following every now and again...

```sh
webservice php7.4 shell -- php ./src/artisan job:dispatchNow GenerateAliasQuestions enwiki 200
webservice php7.4 shell -- php ./src/artisan job:dispatchNow GenerateAliasQuestions dewiki 100
webservice php7.4 shell -- php ./src/artisan job:dispatchNow GenerateAliasQuestions plwiki 100
webservice php7.4 shell -- php ./src/artisan job:dispatchNow GenerateDepictsQuestionsYaml
```

You can also target specific yaml files for depicts...

```sh
webservice php7.4 shell -- php ./src/artisan job:dispatchNow GenerateDepictsQuestionsYaml ./src/spec/depicts/food/burger.yaml
```

## Updates

```sh
git -C ~/src pull
webservice php7.4 shell -- composer install --working-dir=./src
webservice node12 shell -- npm --prefix src install
webservice node12 shell -- npm --prefix src run production
cp ~/src/toolforge/lighttpd.conf ~/.lighttpd.conf
cp ~/src/toolforge/service.template ~/service.template
cp ~/src/toolforge/deployment.yaml ~/deployment.yaml
rsync -av --delete --exclude 'storage/framework/*' --exclude 'node_modules/*' ~/src/ ~/public_html
# TODO migrate if needed
webservice restart
kubectl delete deployment laravel.queue
kubectl create --validate=true -f ~/deployment.yaml
```

## Initial setup

```sh
ssh login-toolforge.org
become wikicrowd
git clone https://github.com/addshore/wikicrowd.git ~/src/
cp ~/src/toolforge/lighttpd.conf ~/.lighttpd.conf
cp ~/src/toolforge/service.template ~/service.template
webservice php7.4 shell -- composer install --working-dir=./src
```

You'll also need to manually create a and configure a `.env.web` file.

```sh
cp ~/src/.env.example ~/src/.env
```

Once code is setup You'll also want to generate a key:

```sh
webservice php7.4 shell -- php ./src/artisan key:generate
```

And modify the other needed env vars!!!

https://wikitech.wikimedia.org/wiki/Help:Toolforge/Database
https://wikitech.wikimedia.org/wiki/Help:Toolforge/Redis_for_Toolforge

### Database

SQL is primiarly used.

```sh
sql tools
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
