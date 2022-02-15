# wikicrowd API

This API is build on laravel.

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

On first setup tou'll need to create the databases.

```sh
./vendor/bin/sail artisan migrate
```

You can then generate a couple of questions (just exit the command early so it doesnt run forever)

```sh
./vendor/bin/sail artisan job:dispatchNow GenerateDepictsQuestionsYaml
```

Then find the site at http://localhost

In order to have the API fully setup you'll need to make your own `.env` file, including your own mediawiki oauth details.
