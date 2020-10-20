# City of Helsinki - Asuntotuotanto Apartment Search

Initial setup for implementing Apartment Search (React app) to Asuntotuotanto Drupal.
Note! This is not the preferred way to implement applications in to Drupal. Consult hel.fi developers for more preferable way to implement widgets. 

## Requirements

Make sure NodeJS (minimum v12.18) is installed. 

## How to install

Run `composer install`. This will install asuntomyynti-react package from City of Helsinki Github, copy it to assets and build the react app.

## How to update

Update the Asuntomyynti React package by running `composer update vendor/package:version`. For version see: https://github.com/City-of-Helsinki/asuntomyynti-react.  
After updating the package run `composer install`.
Commit the changed files and push them to repository.
