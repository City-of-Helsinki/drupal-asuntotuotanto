# Asuntotuotanto Admin Theme

## Introduction

ASU - Admin theme is an extension of Gin theme. Style follows the [BEM methodology](http://getbem.com/) and javascript is written as ES6. The JS and SCSS files are compiled and minified with webpack.

## Requirements

This theme requires Drupal core >= 8.8.0 that includes the Gin theme.

Requirements for developing:
- [NodeJS ( ^ 12.18 )](https://nodejs.org/en/)
- [NPM](https://npmjs.com/) 

## Commands

| Command       | Description                                                         |
| ------------- | ------------------------------------------------------------------- |
| npm i         | Install dependencies and link local packages.                       |
| npm run dev   | Compile styles for development environment. and watch file changes. |
| npm run build | Build packages for production. Minify CSS/JS.                       |

Setup the developing environment by running

    nvm use 
    npm i 

## Structure for files and folders

```
asu_admin
│   README.md
└───src
│   └───scss
│   │   │   common.scss
│   └───js
│       │   common.js
└───media
│   └───icons
│   │   └───component
│   │   │      some-icon.svg
│   │   sprite.svg
│   │   some-media.jpg
└───dist
    └───css
    └───js 
    └───media 
```
