const AmdWebpackPlugin = require('amd-webpack-plugin')
const CopyWebpackPlugin = require('copy-webpack-plugin')

module.exports = {
  publicPath: '/widget/',
  outputDir: '../../app/public/widget',
  configureWebpack: {
    output: {
      libraryTarget: 'amd',
      filename: 'app.js'
    },
    plugins: [
      new AmdWebpackPlugin(),
      new CopyWebpackPlugin({
        patterns: [{
          from: 'src/widget/',
          to: '.'
        }]
      })
    ]
  },
  filenameHashing: false,
  chainWebpack: config => {
    config.plugins.delete('html')
    config.plugins.delete('preload')
    config.plugins.delete('prefetch')
    config.optimization.delete('splitChunks')
  }
}
