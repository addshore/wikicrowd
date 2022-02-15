For developemnt

```sh
./vendor/bin/sail up
npm run watch
```

You'll need to create the databases.

```sh
./vendor/bin/sail artisan migrate
```

You can then generate a couple of questions (just exit the command early so it doesnt run forever)

```sh
./vendor/bin/sail artisan job:dispatchNow GenerateDepictsQuestionsYaml
```

Then find the site at http://localhost
