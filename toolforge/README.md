This tool is deployed to toolforge.

https://wikicrowd.toolforge.org/

With an OAuth consumer to Wikimedia production

https://meta.wikimedia.org/w/index.php?title=Special:OAuthListConsumers/view/b7e6badde4982d44e053ca0a4fdde3ca&name=&publisher=&stage=0

## Code

```sh
ssh login-toolforge.org
become wikicrowd
git clone https://github.com/addshore/wikicrowd.git ~/src/
webservice php7.4 shell -- composer install --working-dir=./src/api
cp ~/src/toolforge/service.template ~/
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
