This tool is deployed to toolforge.

https://wikicrowd.toolforge.org/

With an OAuth consumer to Wikimedia production

https://meta.wikimedia.org/w/index.php?title=Special:OAuthListConsumers/view/b7e6badde4982d44e053ca0a4fdde3ca&name=&publisher=&stage=0

## Updates

```sh
git -C ~/src pull
webservice php7.4 shell -- composer install --working-dir=./src/api
webservice node12 shell -- npm --prefix src/api install
webservice node12 shell -- npm --prefix src/api run production
cp ~/src/toolforge/lighttpd.conf ~/.lighttpd.conf
cp ~/src/toolforge/service.template ~/service.template
rsync -av --delete ~/src/api/ ~/public_html
# TODO migrate if needed
webservice restart
```

## Initial setup

```sh
ssh login-toolforge.org
become wikicrowd
git clone https://github.com/addshore/wikicrowd.git ~/src/
cp ~/src/toolforge/lighttpd.conf ~/.lighttpd.conf
cp ~/src/toolforge/service.template ~/service.template
webservice php7.4 shell -- composer install --working-dir=./src/api
```

You'll also need to manually create a and configure a `.env.web` file.

```sh
cp ~/src/api/.env.example ~/src/api/.env
```

Once code is setup You'll also want to generate a key:

```sh
webservice php7.4 shell -- php ./src/api/artisan key:generate
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
rsync -av --delete ~/src/api/ ~/public_html
```

And start or restart the service

```
webservice restart
```
