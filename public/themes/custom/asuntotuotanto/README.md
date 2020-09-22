All style changes are done in the src/scss folder and generated into css automatically using gulp.
Note!
None of the assets files are included in git, which means less conflicts of compiled css files.
The assets files are compiled when deploying to test/prodcution.

For everything to work correctly you need to install required modules. Run the following:

    npm install

After this you can run the command that compiles your sass files to css. It will compile the css into human-readable form and begins watching the scss/js source folders.

    npm run gulp

or you can run

    npm run gulp development

If you want to test how the css and js is compiled in production (minified), you can run:

    npm run gulp production

