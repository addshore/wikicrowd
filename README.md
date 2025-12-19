# wikicrowd

The [currently deployed web app](https://wikicrowd.toolforge.org) built using Laravel.

![](https://upload.wikimedia.org/wikipedia/commons/7/70/WikiCrowd_screenshot_26_May_2025.png)

`toolforge` contains documentation and resources for deploying the web app.

## Question generation

Question generation is done using Laravel jobs.

For the primary questions (depicts), checkout the `spec` folder the the yaml that is used to generate the questions.

## Development

To get the dependencies you'll need to do a composer install...

```sh
composer install
```

If you have docker you should be able to use sail to run the development system, `sail up -d` for the backend and `npm run watch` for the frontend.

```sh
./vendor/bin/sail up -d
npm run watch
```

On first setup you'll need to create the backend databases.

```sh
./vendor/bin/sail artisan migrate
```

You should then be ready to go in terms of using the UI.

Find the site at http://localhost

Note: In order to have the API fully setup you'll need to make your own `.env` file, including your own mediawiki oauth details.

**First questions generation?**

You can then generate questions using the YAML that is on commons in the queue...

https://commons.wikimedia.org/wiki/User:Addshore/wikicrowd.yaml

```sh
./vendor/bin/sail artisan job:dispatchNow GenerateDepictsQuestionsFromYaml
```

You can also apply an item filter, and generate questions in the same process...

```sh
./vendor/bin/sail artisan job:dispatchNow GenerateDepictsQuestionsFromYaml Q133585 "" 1 true
```

And also manually generate specific questions...

```sh
./vendor/bin/sail artisan job:dispatchNow GenerateDepictsQuestions "Bicycles" "[\"foo\",\"bar\"]" "/(Videos)/i" Q11442 "Bicycle (pedal-driven two-wheel)" 10 true
```

### Other commands

You can run basic php linting:

```sh
composer run lint
```

## Deployment

You need to ssh into the tool, and then run `./src/toolforge/update.sh`.
