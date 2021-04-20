const merge = require("webpack-merge");
const MinifyPlugin = require("babel-minify-webpack-plugin");
const webpackConfig = require("./webpack.config");

module.exports = merge(webpackConfig, {
  mode: "production",
  devtool: "",
  plugins: [
    new MinifyPlugin(
      {},
      {
        comments: false,
        sourceMap: "",
      }
    ),
  ],
});
