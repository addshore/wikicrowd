#!/bin/bash

# Get latest code
git -C ~/src pull

# Install dependencies & prep files
webservice php7.4 shell -- composer install --no-dev --working-dir=./src
webservice node12 shell -- npm --prefix src install
webservice node12 shell -- npm --prefix src run production

# Copy files to correct location
rsync -av --delete --exclude 'storage/framework/*' --exclude 'node_modules/*' -exclude '.git/*' ~/src/ ~/public_html

# migrate in case there are database changes
# Note: This will prompt the user to run the migrations... (just incase bad things get in???)
webservice php7.4 shell -- php ./src/artisan migrate

# Restart the web server
cp ~/src/toolforge/lighttpd.conf ~/.lighttpd.conf
cp ~/src/toolforge/service.template ~/service.template
webservice restart

# Re apply k8s Deployments
kubectl delete -f ~/deployment.yaml
cp ~/src/toolforge/deployment.yaml ~/deployment.yaml
kubectl create --validate=true -f ~/deployment.yaml
