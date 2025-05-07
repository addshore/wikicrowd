#!/bin/bash

# Get latest code
git -C ~/src pull --recurse-submodules
git -C ~/src submodule sync
git -C ~/src submodule update --init --recursive

# Install dependencies & prep files
webservice php8.2 shell -- composer install --no-dev --ignore-platform-reqs --working-dir=./src
webservice node18 shell -- npm --prefix src install
webservice node18 shell -- npm --prefix src run production

# Copy files to correct location
rsync -av --delete --exclude 'storage/framework/*' --exclude 'storage/logs/*' --exclude 'node_modules/*' -exclude '.git/*' ~/src/ ~/public_html

# migrate in case there are database changes
# Note: This will prompt the user to run the migrations... (just incase bad things get in???)
webservice php8.2 shell -- php ./src/artisan migrate

# Restart the web server
cp ~/src/toolforge/lighttpd.conf ~/.lighttpd.conf
cp ~/src/toolforge/service.template ~/service.template
webservice php8.2 restart

# Udpate jobs
toolforge jobs load ~/src/toolforge/jobs.yaml