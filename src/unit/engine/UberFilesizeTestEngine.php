<?php

/**
* Checks if any of modified/added files that don't match any of excluded
* patterns are larger than the given limit.
* Filesize limit (in bytes) and excluded patterns can be specified as a
* runtime config in ".arcconfig" as following:
* { ...
*   "unit.engine": "UberMultiTestEngine",
*   "unit.engine.multi.engines":
*   [
*     ...,
*     {
*       "engine": "UberFilesizeTestEngine",
*       "max_filesize_limit" : 1000000,
*       "exclude_paths": ["(\\.h$)", "(\\.c$)"]
*     },
*     ...
*   ],
*   ...
* }
*/

final class UberFilesizeTestEngine extends ArcanistUnitTestEngine {

    const MAX_FILESIZE_LIMIT_KEY = 'max_filesize_limit';
    const EXCLUDE_PATHS = 'exclude_paths';

    public function run() {
        return $this->checkFilesizes();
    }

    private function checkFilesizes() {
        $result = new ArcanistUnitTestResult();

        $config_manager = $this->getConfigurationManager();
        $max_filesize_limit = $config_manager
            ->getConfigFromAnySource(self::MAX_FILESIZE_LIMIT_KEY);
        $exclude_paths = $config_manager
            ->getConfigFromAnySource(self::EXCLUDE_PATHS);

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
            // skip deleted files and files matching one of the exclude patterns
            if (file_exists($files[$i]) &&
                !$this->isExcluded($files[$i], $exclude_paths) &&
                filesize($files[$i]) > $max_filesize_limit) {
                array_push($large_files, $files[$i]);
            }
        }

        if (count($large_files) > 0) {
            $result->setResult(ArcanistUnitTestResult::RESULT_FAIL);
            $error_string =
                sprintf('Maximum allowed filesize is %s.',
                    $this->formatBytes($max_filesize_limit));
            $error_string .=  ' Files exceeding the filesize limit:';
            for ($i = 0; $i < count($large_files); $i++) {
                $error_string .= "\n";
                $large_file_size = filesize($large_files[$i]);
                $error_string .= $this->formatBytes($large_file_size);
                $error_string .= ' '.$large_files[$i];
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

    private function isExcluded($file, $exclude_paths) {
        for ($i = 0; $i < count($exclude_paths); $i++) {
            if (preg_match($exclude_paths[$i], $file)) {
                return true;
            }
        }
        return false;
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

    private function formatBytes($size, $precision = 2) {
        $base = log($size, 1024);
        $suffixes = array('', 'K', 'M', 'G', 'T');

        return round(pow(1024, $base - floor($base)), $precision)
            .$suffixes[floor($base)];
    }
}

?>
