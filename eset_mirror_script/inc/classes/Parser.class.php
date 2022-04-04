<?php

/**
 * Class Parser
 */
class Parser
{
    /**
     * @param $handle
     * @param $tag
     * @param bool $pattern
     * @return array
     */
    static public function parse_line($handle, $tag, $pattern = false)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        $arr = [];

        if (preg_match_all(($pattern ? $pattern : "/$tag *=(.+)/"), $handle, $result, PREG_PATTERN_ORDER)) {
            foreach ($result[1] as $key) {
                $arr[] = trim($key);
            }
        }
        return $arr;
    }

    /**
     * @param $file
     * @return array
     */
    static public function parse_keys($file)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        return static::parse_line(@file_get_contents($file), false, "/(.+:.+:.+)\n/");
    }

    /**
     * @param $str_line
     * @param $filename
     */
    static public function delete_parse_line_in_file($str_line, $filename)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        $content = file($filename);
        $count = count($content);

        for ($i = 0; $i < $count; $i++) {
            if (strpos($content[$i], $str_line) !== false)
                unset($content[$i]);
        }

        $content = implode("", $content);
        file_put_contents($filename, $content);
    }

    /**
     * @param $handle
     * @param $template
     * @param $logins
     * @param $passwds
     */
    static public function parse_template($handle, $template, &$logins, &$passwds)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);

        if (preg_match_all("/$template/s", $handle, $result, PREG_PATTERN_ORDER)) {
            $count = count($result[1]);

            for ($i = 0; $i < $count; $i++) {
                if (!empty($result[1][$i]) && !empty($result[3][$i])) {
                    $logins[] = $result[1][$i];
                    $passwds[] = $result[3][$i];
                }
            }
        }
    }

    /**
     * @param $http_response_header
     * @return array
     */
    static public function parse_header($http_response_header)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        $header = [];

        foreach ($http_response_header as $line) {
            if (preg_match("/\:/", $line)) {
                $parse = array_map("trim", explode(":", $line, 2));
                $header = array_merge_recursive($header, array($parse[0] => $parse[1]));
            } else {
                $header = array_merge_recursive($header, array($line));
            }
        }
        return $header;
    }
}
