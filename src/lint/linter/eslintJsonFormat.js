// https://github.com/sindresorhus/eslint-json/blob/master/json.js
module.exports = function(results, config) {
  return JSON.stringify({
    config: config,
    results: results
  }, function(key, val) {
    // filter away the Esprima AST
    if (key !== 'node') {
      return val;
    }
  });
};
