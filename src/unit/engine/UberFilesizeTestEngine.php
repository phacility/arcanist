<?php

/**
* Checks if any of modified/added files are larger than the given limit.
* Filesize limit can be specified as a runtime config in ".arcconfig" as
* following:
* { ...
*   "unit.engine": "UberMultiTestEngine",
*   "unit.engine.multi.engines":
*   [
*     ...,
*     {
*       "engine": "UberFilesizeTestEngine",
*       "MAX_FILESIZE_LIMIT" : 1000000
*     },
*     ...
*   ],
*   ...
* }
*/

final class UberFilesizeTestEngine extends ArcanistUnitTestEngine {

    const MAX_FILESIZE_LIMIT_KEY = 'MAX_FILESIZE_LIMIT';

    public function run() {
        return $this->checkFilesizes();
    }

    private function checkFilesizes() {
        $result = new ArcanistUnitTestResult();

        $max_filesize_limit =
            $this->getConfigurationManager()
                 ->getConfigFromAnySource(self::MAX_FILESIZE_LIMIT_KEY);

        if (is_null($max_filesize_limit)) {
            throw new ArcanistUsageException(
                pht(
                    "Test engine '%s' requires a runtime config '%s'.",
                    get_class($this),
                    self::MAX_FILESIZE_LIMIT_KEY));
        }

        if (!is_int($max_filesize_limit)) {
            throw new ArcanistUsageException(
                pht(
                    "Test engine '%s' requires an integer value ".
                    "for runtime config '%s'.",
                    get_class($this),
                    self::MAX_FILESIZE_LIMIT_KEY));
        }

        $files = $this->getModifiedFiles();

        $large_files = [];
        for ($i = 0; $i < count($files); $i++) {
            // deleted files are skipped
            if (file_exists($files[$i])) {
                if (filesize($files[$i]) > $max_filesize_limit) {
                    array_push($large_files, $files[$i]);
                }
            }
        }

        if (count($large_files) > 0) {
            $result->setResult(ArcanistUnitTestResult::RESULT_FAIL);
            $error_string =
                sprintf('Maximum allowed filesize is %d.',
                    $max_filesize_limit);
            $error_string .=  ' Files exceeding the limit:';
            for ($i = 0; $i < count($large_files); $i++) {
                $error_string .= "\n";
                $error_string .= $large_files[$i];
            }
            $result->setName($error_string);
        } else {
            $result->setResult(ArcanistUnitTestResult::RESULT_PASS);
            $result->setName(
                sprintf('All modified files are smaller than '.
                        'the limit (%d).',
                        $max_filesize_limit));
        }

        return array($result);
    }

    private function getModifiedFiles() {
        $output = [];
        $return_code = 0;

        $last_ancestor_sha = 'git merge-base origin/master HEAD';
        // list of files modified in commits after the split from origin/master
        exec(sprintf('git show --pretty="" --name-only `%s`..HEAD',
            $last_ancestor_sha), $output, $return_code);

        return $output;
    }
}

?>
