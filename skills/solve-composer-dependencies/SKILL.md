---
name: solve-composer-dependencies
description: Installs and updates packages, solves dependency issues when installing packages when user asks to install or update a Composer package.
---

## Overview
This guide covers the installation and updating of Composer packages along with how to solve dependency issues.

## Basic command usage
- If development is done inside a container, use `docker exec <container name> sh -c "<command>"` to run `drush` commands
  - Example: `docker exec asuntotuotanto-app sh -c "drush require drupal/encrypt:^3.3"`

## Installing/updating packages
- Check if installing a package would encounter problems with `composer require <package_name>:<version> --dry-run`
  - Example: `composer require drupal/simple_oauth:^6.1.0 --dry-run`
- You might encounter a lengthy error message such as below

```bash
$ composer require drupal/simple_oauth:^6.1.0 --dry-run
./composer.json has been updated
Running composer update drupal/simple_oauth
Loading composer repositories with package information
Updating dependencies
Your requirements could not be resolved to an installable set of packages.

  Problem 1
    - Root composer.json requires drupal/simple_oauth ^6.1.0 -> satisfiable by drupal/simple_oauth[6.1.0, 6.1.x-dev, 6.x-dev (alias of dev-6.x)].
    - drupal/simple_oauth[6.1.0, ..., 6.1.x-dev] require league/oauth2-server ^9.0 -> found league/oauth2-server[9.0.0-RC1, ..., 9.3.0] but the package is fixed to 8.5.5 (lock file version) by a partial update and that version does not match. Make sure you list it as an argument for the update command.
    - drupal/simple_oauth dev-6.x requires drupal/core ^8 || ^9 -> found drupal/core[8.0.0-beta6, ..., 8.9.x-dev, 9.0.0-alpha1, ..., 9.5.x-dev] but it conflicts with your root composer.json require (~11.2.0).
    - drupal/simple_oauth 6.x-dev is an alias of drupal/simple_oauth dev-6.x and thus requires it to be installed too.

Use the option --with-all-dependencies (-W) to allow upgrades, downgrades and removals for packages currently locked to specific versions.

Installation failed, reverting ./composer.json and ./composer.lock to their original content.```

- You should then use the `composer why-not <package> <version>` command to get further information on why some dependencies for the package could not be installed
    - Example: The packages mentioned in the error messages are `drupal/simple_oauth:^6.1.0` and `league/oauth2-server:^9.0`
        - Call `composer why-not drupal/simple_oauth ^6.1.0` and `composer why-not league/oauth2-server ^9.0` to find further information on what keeps them from being installed
        ```bash
        $ composer why-not drupal/simple_oauth ^6.1.0 
        drupal/simple_oauth             6.1.0       requires         league/oauth2-server (^9.0) 
        ```
- Utilize the hints given by `composer why-not` and `composer require --dry-run` to figure out a combination of packages that work together and then install them
- You can use `composer show <package> <version>` to see which dependencies a  
