<?php

/**
 * Class Tools
 */
class Tools
{
    /**
     * @param array $options
     * @param $headers
     * @return mixed
     */
    static public function download_file($options = array(), &$headers)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        $out = FALSE;

        if (key_exists(CURLOPT_FILE, $options)) {
            $dir = dirname($options[CURLOPT_FILE]);
            if (!@file_exists($dir)) @mkdir($dir, 0755, true);
            $out = fopen($options[CURLOPT_FILE], "wb");
	        if (!is_resource($out)) return false;
            $options[CURLOPT_FILE] = $out;
        }

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $res = curl_exec($ch);

        $headers = curl_getinfo($ch);
        if ($out) @fclose($out);
        curl_close($ch);

        if (key_exists(CURLOPT_RETURNTRANSFER, $options)) {
            if ($options[CURLOPT_RETURNTRANSFER] == 1) return $res;
        }

        return false;
    }

    /**
     * @return string
     */
    static public function get_archive_extension()
    {
        return ".gz";
    }

    /**
     * @param $file
     * @return string
     */
    static public function get_file_mimetype($file)
    {
        $f = new finfo();
        $info = $f->file($file, FILEINFO_MIME_TYPE);
        return $info;
    }

    /**
     * @param $file
     */
    static public function archive_file($file)
    {
        $fp = gzopen($file . ".1.gz", 'w9');
        gzwrite($fp, file_get_contents($file));
        gzclose($fp);
        unlink($file);
    }

    /**
     * @param $unrar_binary
     * @param $source
     * @param $destination
     * @throws ToolsException
     */
    static public function extract_file($unrar_binary, $source, $destination)
    {
        if (PHP_OS != 'WINNT')
            $unrar_binary = exec('which unrar');

        if (!file_exists($unrar_binary))
            throw new ToolsException("Unrar not exists at %s", $unrar_binary);

        if (!is_executable($unrar_binary))
            throw new ToolsException("Unrar not executable at %s", $unrar_binary);

        switch (PHP_OS) {
            case "Darwin":
            case "Linux":
            case "FreeBSD":
            case "OpenBSD":
                exec(sprintf("%s x -inul -y %s %s", $unrar_binary, $source, $destination));
                break;
            case "WINNT":
                shell_exec(sprintf("%s e -y %s %s", $unrar_binary, $source, $destination));
                break;
        }
    }

    /**
     * @param array $options
     * @param $hostname
     * @param int $port
     * @param null $file
     * @return bool
     */
    static public function ping(array $options, $hostname, $port = 80, $file = NULL)
    {
        static::download_file(
            ([
                    CURLOPT_URL => "http://" . $hostname . "/" . $file,
                    CURLOPT_PORT => $port,
                    CURLOPT_NOBODY => 1
                ] + $options),
            $headers
        );
        return (is_array($headers)) ? true : false;
    }

    /**
     * @param $bytes
     * @param int $precision
     * @return string
     */
    static public function bytesToSize1024($bytes, $precision = 2)
    {
        $unit = ['Bytes', 'KBytes', 'MBytes', 'GBytes', 'TBytes', 'PBytes', 'EBytes'];
        return $bytes > 0 ? @round($bytes / pow(1024, ($i = floor(log($bytes, 1024)))), $precision) . ' ' . $unit[intval($i)] :  '0 ' . $unit[intval(0)];
    }

    /**
     * @param $secs
     * @return false|string
     */
    static public function secondsToHumanReadable($secs)
    {
        return ($secs > 60 * 60 * 24) ? gmdate("H:i:s", $secs) : gmdate("i:s", $secs);
    }

    /**
     * @return mixed
     */
    static public function ds()
    {
        return preg_replace('/[\/\\\\]+/', DIRECTORY_SEPARATOR, implode('/', func_get_args()));
    }

    /**
     * @param $text
     * @param $to_encoding
     * @return mixed|string
     */
    static public function conv($text, $to_encoding)
    {
        if (preg_match("/utf-8/i", $to_encoding))
            return $text;
        elseif (function_exists('mb_convert_encoding'))
            return mb_convert_encoding($text, 'UTF-8', $to_encoding);
        elseif (function_exists('iconv'))
            return iconv('UTF-8', $to_encoding, $text);
        else {
            $conv = array();

            for ($x = 128; $x <= 143; $x++) {
                $conv['u'][] = chr(209) . chr($x);
                $conv['w'][] = chr($x + 112);

            }

            for ($x = 144; $x <= 191; $x++) {
                $conv['u'][] = chr(208) . chr($x);
                $conv['w'][] = chr($x + 48);
            }

            $conv['u'][] = chr(208) . chr(129);
            $conv['w'][] = chr(168);
            $conv['u'][] = chr(209) . chr(145);
            $conv['w'][] = chr(184);
            $conv['u'][] = chr(208) . chr(135);
            $conv['w'][] = chr(175);
            $conv['u'][] = chr(209) . chr(151);
            $conv['w'][] = chr(191);
            $conv['u'][] = chr(208) . chr(134);
            $conv['w'][] = chr(178);
            $conv['u'][] = chr(209) . chr(150);
            $conv['w'][] = chr(179);
            $conv['u'][] = chr(210) . chr(144);
            $conv['w'][] = chr(165);
            $conv['u'][] = chr(210) . chr(145);
            $conv['w'][] = chr(180);
            $conv['u'][] = chr(208) . chr(132);
            $conv['w'][] = chr(170);
            $conv['u'][] = chr(209) . chr(148);
            $conv['w'][] = chr(186);
            $conv['u'][] = chr(226) . chr(132) . chr(150);
            $conv['w'][] = chr(185);
            $win = str_replace($conv['u'], $conv['w'], $text);

            if (preg_match("/1251/i", $to_encoding))
                return $win;
            elseif (preg_match("/koi8/i", $to_encoding))
                return convert_cyr_string($win, 'w', 'k');
            elseif (preg_match("/866/i", $to_encoding))
                return convert_cyr_string($win, 'w', 'a');
            elseif (preg_match("/mac/i", $to_encoding))
                return convert_cyr_string($win, 'w', 'm');
            else
                return $text;
        }
    }

    /**
     * @param $resource
     * @return bool|mixed
     */
    static public function get_resource_id($resource)
    {
        return (!is_resource($resource)) ? false : @end(explode('#', (string)$resource));
    }

    /**
     * @param $file1
     * @param $file2
     * @return bool
     */
    static public function compare_files($file1, $file2)
    {
        return ($file1['size'] == $file2['size']);
    }

    /**
     * @param $str string
     * @return int
     * @throws Exception
     */
    static public function human2bytes($str)
    {
        $n = null;

        if (preg_match_all("/([0-9]+)([BKMG])/i", $str, $result, PREG_PATTERN_ORDER)) {
            $str = intval(trim($result[1][0]));

            if (count($result) != 3 || $str < 1 || empty($result[1][0]) || empty($result[2][0]))
                throw new Exception("Please, check set up of rotate_size in your config file!");

            switch (trim($result[2][0])) {
                case "g":
                case "G":
                    $n = $str << 30;
                    break;
                case "m":
                case "M":
                    $n = $str << 20;
                    break;
                case "k":
                case "K":
                    $n = $str << 10;
                    break;
            }
        }
        return $n;
    }
}
