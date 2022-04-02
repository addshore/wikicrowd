#!/bin/bash
git -C ~/src pull
webservice php7.4 shell -- composer install --working-dir=./src
webservice node12 shell -- npm --prefix src install
webservice node12 shell -- npm --prefix src run production
cp ~/src/toolforge/lighttpd.conf ~/.lighttpd.conf
cp ~/src/toolforge/service.template ~/service.template
cp ~/src/toolforge/deployment.yaml ~/deployment.yaml
rsync -av --delete --exclude 'storage/framework/*' --exclude 'node_modules/*' -exclude '.git/*' ~/src/ ~/public_html
# TODO migrate if needed
webservice restart
kubectl delete deployment laravel.queue
kubectl create --validate=true -f ~/deployment.yaml
