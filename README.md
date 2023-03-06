# wikicrowd

The [currently deployed web app](https://wikicrowd.toolforge.org) built using Laravel.

![](https://i.imgur.com/mn5wRiQ.png)

`toolforge` contains documentation and resources for deploying the web app.

## Question generation

Question genertation is done using Laravel jobs.

For the primary questions (depicts), checkout the `spec` folder the the yaml that is used to generate the questions.

## Development

To get the dependencies you'll need to do a composer install...

```sh
composer install
```

If you have docker you should be able to use sail to run the development system.

```sh
./vendor/bin/sail up -d
npm run watch
```

On first setup you'll need to create the databases.

```sh
./vendor/bin/sail artisan migrate
```

You can then generate a couple of questions (just exit the command early so it doesnt run forever)

```sh
./vendor/bin/sail artisan job:dispatchNow GenerateDepictsQuestionsYaml
webservice php7.4 shell -- php ./src/artisan job:dispatchNow GenerateAliasQuestions enwiki 10
```

Then find the site at http://localhost

In order to have the API fully setup you'll need to make your own `.env` file, including your own mediawiki oauth details.

### Other commands

You can run basic php linting:

```sh
composer run lint
```

## Deployment

You need to ssh into the tool, and then run `./src/toolforge/update.sh`.
