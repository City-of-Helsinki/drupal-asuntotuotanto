const isDev = process.env.NODE_ENV !== "production";

module.exports = {
  // You can specify any options from http://api.postcss.org/global.html#processOptions here
  // parser: "sugarss",
  plugins: [
    // Plugins for PostCSS
    ["autoprefixer", { sourceMap: isDev }],
    "postcss-preset-env",
    "postcss-import-ext-glob",
    "postcss-easy-import",
    "postcss-extend",
    "postcss-nested",
    "postcss-combine-media-query",
  ],
};
