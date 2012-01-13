module.exports = {
    reporter: function (results) {
        var report = [];

        results.forEach(function (result) {
            var error = result.error;

            report.push({
                'file': result.file,
                'line': error.line,
                'col': error.character,
                'reason': error.reason
            });
        });

        process.stdout.write(JSON.stringify(report));
    }
};
