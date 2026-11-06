# React + TypeScript + Vite

This template provides a minimal setup to get React working in Vite with HMR and some ESLint rules.

## Development and Production

Create `.env` file and copy `.env.example` contents to it. Modify variables as needed.

## Available Scripts

In the project directory, you can run:

### `npm run dev`

Runs the app in the development mode.<br />
Open [http://localhost:5173](http://localhost:5173) to view it in the browser.

The dev server proxies backend requests (e.g. `/fi/initialize`) via `VITE_BACKEND_ORIGIN` from your `.env`.

and then run `npm run dev` normally

### `npm run test`

Launches the test runner in the interactive watch mode.<br />
See the section about [running tests](https://facebook.github.io/create-react-app/docs/running-tests) for more information.

### `npm run build`

Builds the app for production to the `build` folder.<br />
It correctly bundles React in production mode and optimizes the build for the best performance.

The build is minified and the filenames include the hashes.<br />
Your app is ready to be deployed!

See the section about [deployment](https://facebook.github.io/create-react-app/docs/deployment) for more information.

### `npm run dist`

**_A custom script that builds four different versions of the application and creates a .zip file for creating releases._**

Before creating the dist package, make sure that you have your `.env` variables set. _Note: (`VITE_SHOW_UPCOMING_ONLY` and `VITE_PROJECT_OWNERSHIP_TYPE` values don't need to be changed in the dist build, they are altered automatically)_

This script goes through each of the four application variations and runs `yarn build` with the pre-set environment variables.
After building each variation, it copies the files under the `dist/react/` folder according to the variation type.

These copied files will always have the same filename, so that it's easier to embed them into other web applications without the need of worrying hashes in filenames. (This will also copy `.map` files and it keeps their original filename, as they are referenced in this way in the generated files.)

We are using `asu_react_` as a prefix in the filenames to avoid naming collisions.

Generated files are:

```
/dist/react/
    hitas/
        asu_react_main.js
        asu_react_runtime-main.js
        asu_react_vendors.js
        asu_react_main.css
        asu_react_vendors.css
        *.map
    hitas_upcoming/
        asu_react_main.js
        asu_react_runtime-main.js
        asu_react_vendors.js
        asu_react_main.css
        asu_react_vendors.css
        *.map
    haso/
        asu_react_main.js
        asu_react_runtime-main.js
        asu_react_vendors.js
        asu_react_main.css
        asu_react_vendors.css
        *.map
    haso_upcoming/
        asu_react_main.js
        asu_react_runtime-main.js
        asu_react_vendors.js
        asu_react_main.css
        asu_react_vendors.css
        *.map
```

**The order of running these files does matter.** Example usage of the application:

```
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>

    <link rel="stylesheet" href="asu_react_vendors.css?v=x.x.x">
    <link rel="stylesheet" href="asu_react_main.css?v=x.x.x">
</head>
<body>
    <div id="asu_react_search"></div>

    <script src="asu_react_runtime-main.js?v=x.x.x"></script>
    <script src="asu_react_vendors.js?v=x.x.x"></script>
    <script src="asu_react_main.js?v=x.x.x"></script>
</body>
</html>
```
