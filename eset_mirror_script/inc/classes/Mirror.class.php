<?php

/**
 * Class Mirror
 */
class Mirror
{
    /**
     * @var int
     */
    static public $total_downloads = 0;

    /**
     * @var
     */
    static public $version = null;

    /**
     * @var
     */
    static public $tmp_update_file = null;

    static public $local_update_file = null;

    /**
     * @var null
     */
    static public $source_update_file = null;

    /**
     * @var null
     */
    static public $name = null;

    /**
     * @var array
     */
    static public $mirrors = array();

    /**
     * @var array
     */
    static public $key = array();

    /**
     * @var bool
     */
    static public $updated = false;

    /**
     * @var array
     */
    static private $ESET;


    static public $unAuthorized = false;

    /**
     *
     */
    static private function fix_time_stamp()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $fn = Tools::ds(Config::get('LOG')['dir'], SUCCESSFUL_TIMESTAMP);
        $timestamps = [];

        if (file_exists($fn)) {
            $handle = file_get_contents($fn);
            $content = Parser::parse_line($handle, false, "/(.+:.+)\n/");

            if (isset($content) && count($content)) {
                foreach ($content as $value) {
                    $result = explode(":", $value);
                    $timestamps[$result[0]] = $result[1];
                }
            }
        }

        $timestamps[static::$version] = time();
        @unlink($fn);

        foreach ($timestamps as $key => $name)
            Log::write_to_file($fn, "$key:$name\r\n");
    }

    /**
     * @return bool
     */
    static public function test_key()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        Log::write_log(Language::t("Testing key [%s:%s]", static::$key[0], static::$key[1]), 4, static::$version);

        $test_mirrors = [];
        foreach (static::$ESET['mirror'] as $mirror) {

            Tools::download_file(
                [
                    CURLOPT_USERPWD => static::$key[0] . ":" . static::$key[1],
                    CURLOPT_URL => "http://" . $mirror . "/" . static::$source_update_file,
                    CURLOPT_NOBODY => 1,
                    CURLOPT_TIMEOUT => Config::get('CONNECTION')['timeout']
                ],
                $headers
            );

            if ($headers['http_code'] == 200) {
                $test_mirrors[$mirror] = round($headers['total_time'] * 1000);
            }
        }

        asort($test_mirrors);
        static::$mirrors = [];
        $maxVersion = 0;
        $sameMirrors = [];

        foreach ($test_mirrors as $mirror => $time) {
            $version = static::check_mirror($mirror);
            if ($version) {
                $maxVersion = $version > $maxVersion ? $version : $maxVersion;
                $sameMirrors[] = ['host' => $mirror, 'db_version' => $version];
            } else
                return false;
        }

        static::$mirrors = array_filter($sameMirrors, function ($v, $k) use ($maxVersion) {
            return $v['db_version'] == $maxVersion;
        }, ARRAY_FILTER_USE_BOTH);

        return count(static::$mirrors) > 0;
    }

    /**
     * @throws ToolsException
     */
    static public function find_best_mirrors()
    {
        /*Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $test_mirrors = [];

        foreach (static::$ESET['mirror'] as $mirror) {
            Tools::download_file(
                [
                    CURLOPT_USERPWD => static::$key[0] . ":" . static::$key[1],
                    CURLOPT_URL => "http://" . $mirror . "/" . static::$source_update_file,
                    CURLOPT_NOBODY => 1
                ],
                $headers
            );

            if ($headers['http_code'] == 200) {
                $test_mirrors[$mirror] = round($headers['total_time'] * 1000);
                Log::write_log(Language::t("Mirror %s active", $mirror), 3, static::$version);
            } else Log::write_log(Language::t("Mirror %s inactive", $mirror), 3, static::$version);
        }
        asort($test_mirrors);

        foreach ($test_mirrors as $mirror => $time)
            static::$mirrors[] = ['host' => $mirror, 'db_version' => static::check_mirror($mirror)];*/
    }

    /**
     * @param $mirror
     * @return int|null
     * @throws ToolsException
     */
    static public function check_mirror($mirror)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $new_version = null;
        $file = static::$tmp_update_file;
        Log::write_log(Language::t("Checking mirror %s with key [%s:%s]", $mirror, static::$key[0], static::$key[1]), 4, static::$version);
        static::download_update_ver($mirror, true);
        $new_version = static::get_DB_version($file);
        @unlink($file);

        return $new_version;
    }

    /**
     * @param $mirror
     * @throws ToolsException
     */
    static public function download_update_ver($mirror, $downloadRandomFile = false)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $tmp_path = dirname(static::$tmp_update_file);
        $archive = Tools::ds($tmp_path, 'update.rar');
        $extracted = Tools::ds($tmp_path, 'update.ver');
        Tools::download_file(
            [
                CURLOPT_USERPWD => static::$key[0] . ":" . static::$key[1],
                CURLOPT_URL => "http://" . "$mirror/" . static::$source_update_file,
                CURLOPT_FILE => $archive
            ],
            $headers
        );

        if (is_array($headers) and $headers['http_code'] == 200) {
            if (preg_match("/rar/", Tools::get_file_mimetype($archive))) {
                Log::write_log(Language::t("Extracting file %s to %s", $archive, $tmp_path), 5, static::$version);
                Tools::extract_file(Config::get('SCRIPT')['unrar_binary'], $archive, $tmp_path);
                @unlink($archive);
                if (Config::get('SCRIPT')['debug_update'] == 1) {
                    $date = date("Y-m-d-H-i-s-") . explode('.', microtime(1))[1];
                    copy("${tmp_path}/update.ver", "${tmp_path}/update_${mirror}_${date}.ver");
                }
            } else {
                rename($archive, $extracted);
            }
            if (!$downloadRandomFile) return;
            $content = @file_get_contents($extracted);
            if (preg_match_all('#\[\w+\][^\[]+#', $content, $matches))
            {
                list($new_files, $total_size, $new_content) = static::parse_update_file($matches[0]);
                $new_files = array_filter($new_files, function($v, $k) {
                    return $v['size'] <= 1024 * 1024;
                }, ARRAY_FILTER_USE_BOTH);
                shuffle($new_files);
                $file = array_shift($new_files);
                static::download([$file], true, $mirror);
            }
        }
    }

    /**
     * @return array
     * @throws Exception
     * @throws ToolsException
     */
    static public function download_signature()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        static::download_update_ver(current(static::$mirrors)['host']);
        $web_dir = Config::get('SCRIPT')['web_dir'];
        $cur_update_ver = static::$local_update_file;
        $tmp_update_ver = static::$tmp_update_file;
        $content = @file_get_contents($tmp_update_ver);
        $start_time = microtime(true);
        preg_match_all('#\[\w+\][^\[]+#', $content, $matches);
        $total_size = null;
        $average_speed = null;

        if (!empty($matches)) {
            // Parse files from .ver file
            list($new_files, $total_size, $new_content) = static::parse_update_file($matches[0]);

            // Create hardlinks/copy file for empty needed files (name, size)
            list($download_files, $needed_files) = static::create_links($web_dir, $new_files);

            // Download files
            if (!empty($download_files)) {
                static::download_files($download_files);
                static::$updated = !static::$unAuthorized;
            }
            $version = static::$version == 'v5' ? 'ep5' : static::$version;
            // Delete not needed files
            foreach (glob(Tools::ds($web_dir, $version . "-*"), GLOB_ONLYDIR) as $file) {
                $del_files = static::del_files($file, $needed_files);
                if ($del_files > 0) {
                    static::$updated = true;
                    Log::write_log(Language::t("Deleted files: %s", $del_files), 3, static::$version);
                }
            }

            // Delete empty folders
            foreach (glob(Tools::ds($web_dir, $version . "-*"), GLOB_ONLYDIR) as $folder) {
                $del_folders = static::del_folders($folder);
                if ($del_folders > 0) {
                    static::$updated = true;
                    Log::write_log(Language::t("Deleted folders: %s", $del_folders), 3, static::$version);
                }
            }

            //if (!file_exists(dirname($cur_update_ver))) @mkdir(dirname($cur_update_ver), 0755, true);
            @file_put_contents($cur_update_ver, $new_content);

            Log::write_log(Language::t("Total size database: %s", Tools::bytesToSize1024($total_size)), 3, static::$version);

            if (count($download_files) > 0) {
                $average_speed = round(static::$total_downloads / (microtime(true) - $start_time));
                Log::write_log(Language::t("Total downloaded: %s", Tools::bytesToSize1024(static::$total_downloads)), 3, static::$version);
                Log::write_log(Language::t("Average speed: %s/s", Tools::bytesToSize1024($average_speed)), 3, static::$version);
            }

            if (static::$updated) static::fix_time_stamp();
        } else {
            Log::write_log(Language::t("Error while parsing update.ver from %s", current(static::$mirrors)['host']), 3, static::$version);
        }
        @unlink($tmp_update_ver);
        return array($total_size, static::$total_downloads, $average_speed);
    }


    static protected function multiple_download($download_files, $onlyCheck = false, $checkedMirror = null)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $web_dir = $onlyCheck ? Tools::ds(TMP_PATH) : Config::get('SCRIPT')['web_dir'];
        $CONNECTION = Config::get('CONNECTION');
        $options = Config::getConnectionInfo();

        $mirrorList = static::$mirrors;
        if ($onlyCheck && $checkedMirror) $mirrorList = [['host' => $checkedMirror]];
        $curlOpt = $options + [
            CURLOPT_USERPWD => static::$key[0] . ":" . static::$key[1]
        ];
        $mh = curl_multi_init();

        $max_threads = !empty($CONNECTION['download_threads']) ? $CONNECTION['download_threads'] : count($mirrorList);

        $chunks = array_chunk($download_files, $max_threads);

        foreach ($chunks as $key => $files)
        {
            $handlers = [];
            foreach ($files as $idx => $file)
            {
                $out = Tools::ds($web_dir, $file['file']);
                $dir = dirname($out);

                if (!@file_exists($dir)) @mkdir($dir, 0755, true);
                $fh = fopen($out, "wb");
                $ch = curl_init();
                $mirror = $mirrorList[array_rand($mirrorList)];
                $options = $curlOpt + [
                    CURLOPT_URL => "http://" . $mirror['host'] . $file['file'],
                    CURLOPT_FILE => $fh
                ];

                curl_setopt_array($ch, $options);
                curl_multi_add_handle($mh,$ch);

                $handlers[$idx] = [
                    'curlH' => $ch,
                    'fileH' => $fh,
                    'file' => $file,
                    'mirror' => $mirror,
                    'out' => $out
                ];
            }

            do {
                $status = curl_multi_exec($mh, $active);
                if ($active) {
                    curl_multi_select($mh);
                }
            } while ($active && $status == CURLM_OK);

            foreach ($handlers as $tmp1) {
                @fclose($tmp1['fileH']);
            }

            foreach ($handlers as $tmp2)
            {

                $header = curl_getinfo($tmp2['curlH']);

                if (is_array($header) and $header['http_code'] == 200 and $header['size_download'] == $tmp2['file']['size']) {

                    static::$total_downloads += $header['size_download'];
                    Log::write_log(Language::t("From %s downloaded %s [%s] [%s/s]", $tmp2['mirror']['host'], basename($tmp2['file']['file']),
                        Tools::bytesToSize1024($header['size_download']),
                        Tools::bytesToSize1024($header['size_download'] / $header['total_time'])),
                        3,
                        static::$version
                    );
                    static::$total_downloads += $header['size_download'];
                } else {
                    @unlink($tmp2['out']);
                }
                curl_multi_remove_handle($mh, $tmp2['curlH']);
            }

        }

        curl_multi_close($mh);
    }

    /**
     * @param $download_files
     * @param false $onlyCheck
     * @param null $checkedMirror
     */
    static protected function single_download($download_files, $onlyCheck = false, $checkedMirror = null)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $web_dir = $onlyCheck ? Tools::ds(TMP_PATH) : Config::get('SCRIPT')['web_dir'];
        $mirrorList = static::$mirrors;
        if ($onlyCheck && $checkedMirror) $mirrorList = [['host' => $checkedMirror]];

        foreach ($download_files as $file) {
            foreach ($mirrorList as $id => $mirror) {

                $time = microtime(true);
                Log::write_log(Language::t("Trying download file %s from %s", $file['file'], $mirror['host']), 3, static::$version);
                $out = Tools::ds($web_dir, $file['file']);
                Tools::download_file(
                    [
                        CURLOPT_USERPWD => static::$key[0] . ":" . static::$key[1],
                        CURLOPT_URL => "http://" . $mirror['host'] . $file['file'],
                        CURLOPT_FILE => $out
                    ],
                    $header
                );

                if (is_array($header) and $header['http_code'] == 200 and $header['size_download'] == $file['size']) {
                    if ($onlyCheck) {
                        @unlink($out);
                        return;
                    }
                    static::$total_downloads += $header['size_download'];
                    Log::write_log(Language::t("From %s downloaded %s [%s] [%s/s]", $mirror['host'], basename($file['file']),
                        Tools::bytesToSize1024($header['size_download']),
                        Tools::bytesToSize1024($header['size_download'] / (microtime(true) - $time))),
                        3,
                        static::$version
                    );
                    static::$total_downloads += $header['size_download'];
                    break;
                } else {
                    if ($onlyCheck) @unlink(static::$tmp_update_file);
                    @unlink($out);
                }
            }
        }
    }

    /**
     * @param $download_files
     * @param false $onlyCheck
     * @param null $checkedMirror
     */
    static protected function download($download_files, $onlyCheck = false, $checkedMirror = null)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);

        switch (function_exists('curl_multi_init') && (bool)(Config::get('CONNECTION')['use_multidownload']) && !$onlyCheck) {
            case true:
                static::multiple_download($download_files, $onlyCheck, $checkedMirror);
                break;
            default:
                static::single_download($download_files, $onlyCheck, $checkedMirror);
                break;
        }
    }

    /**
     * @param $matches
     * @return array
     */
    static protected function parse_update_file($matches)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $new_content = '';
        $new_files = array();
        $total_size = 0;

        foreach ($matches as $container) {

            $parsed_container = parse_ini_string(
                preg_replace(
                    "/version=(.*?)\n/i",
                    "version=\"\${1}\"\n",
                    str_replace(
                        "\r\n",
                        "\n",
                        $container
                    )
                ),
                true);
            $output = array_shift($parsed_container);

            if (empty($output['file']) or empty($output['size']) or
                (static::$ESET['x32'] != 1 and preg_match("/32|86/", $output['platform'])) or
                (static::$ESET['x64'] != 1 and preg_match("/64/", $output['platform']))
            ) continue;
            $new_files[] = $output;
            $total_size += $output['size'];
            $new_content .= $container;
        }

        return array($new_files, $total_size, $new_content);
    }

    /**
     * @param $download_files
     * @throws ToolsException
     */
    static protected function download_files($download_files)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        shuffle($download_files);
        Log::write_log(Language::t("Downloading %d files", count($download_files)), 3, static::$version);

        static::download($download_files);
    }

    /**
     * @param $version
     * @param $dir
     */
    static public function init($version, $dir)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, $version);
        register_shutdown_function(array('Mirror', 'destruct'));
        static::$total_downloads = 0;
        static::$version = $version;
        static::$name = $dir['name'];
        static::$source_update_file = isset($dir['dll']) && $dir['dll'] ? $dir['dll'] : $dir['file'];
        static::$updated = false;
        static::$ESET = Config::get('ESET');

        $fixed_update_file = preg_replace('/eset_upd\/update\.ver/is','eset_upd/v3/update.ver', static::$source_update_file);

        static::$tmp_update_file = Tools::ds(TMP_PATH, $fixed_update_file);
        static::$local_update_file = Tools::ds(Config::get('SCRIPT')['web_dir'], $fixed_update_file);

        @mkdir(dirname(static::$tmp_update_file), 0755, true);
        @mkdir(dirname(static::$local_update_file), 0755, true);

        Log::write_log(Language::t("Mirror for %s initiliazed with update_file %s", static::$name, static::$source_update_file), 5, static::$version);
    }

    /**
     * @param $key
     */
    static public function set_key($key)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        static::$key = $key;
    }

    /**
     *
     */
    static public function destruct()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);

        static::$total_downloads = 0;
        static::$version = null;
        static::$source_update_file = null;
        static::$name = null;
        static::$mirrors = array();
        static::$key = array();
        static::$updated = false;
        static::$unAuthorized = false;
    }


    /**
     * @param $folder
     * @return int
     */
    static public function del_folders($folder)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $del_folders_count = 0;
        $directory = new RecursiveDirectoryIterator($folder);

        foreach ($directory as $fileObject) {
            $test_folder = $fileObject->getPathname();

            if (count(glob(Tools::ds($test_folder, '*'))) === 0) {
                @rmdir($test_folder);
                $del_folders_count++;
            }
        }

        if (count(glob(Tools::ds($folder, '*'))) === 0) {
            @rmdir($folder);
            $del_folders_count++;
        }

        return $del_folders_count;
    }

    /**
     * @param $file
     * @param $needed_files
     * @return int
     */
    static public function del_files($file, $needed_files)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $del_files_count = 0;
        $directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($file), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($directory as $fileObject) {
            if (!$fileObject->isDir()) {
                $test_file = $fileObject->getPathname();

                if (!in_array($test_file, $needed_files)) {
                    @unlink($test_file);
                    $del_files_count++;
                }
            }
        }

        return $del_files_count;
    }

    /**
     * @param $dir
     * @param $new_files
     * @return array
     */
    static public function create_links($dir, $new_files)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $old_files = [];
        $needed_files = [];
        $download_files = [];
        $preg_pattern = '/([v|ep]+)(\d+)/is';
        $iterator = new RegexIterator(
            new RecursiveIteratorIterator(
                new RecursiveRegexIterator(
                    new RecursiveDirectoryIterator($dir),
                    '/[v|ep]+\d+[-]+/i'
                )
            ),
            '/\.nup$/i'
        );

        $version = false;
        preg_match($preg_pattern, static::$version,$version);
        /** @var RegexIterator $file */
        foreach ($iterator as $file) {
            $pathVersion = false;
            $filepath = $file->getPathname();
            if (is_link($filepath)) continue;
            preg_match($preg_pattern, $filepath, $pathVersion);
            if ($version && is_array($pathVersion) && /*$version[1] == $pathVersion[1] &&*/ (int)$version[2] > (int)$pathVersion[2]) $old_files[] = $filepath;
        }

        foreach ($new_files as $array) {
            $path = Tools::ds($dir, $array['file']);
            $needed_files[] = $path;

            if (file_exists($path) && !Tools::compare_files(@stat($path), $array)) unlink($path);

            if (!file_exists($path)) {
                $results = preg_grep('/' . basename($array['file']) . '$/', $old_files);

                if (!empty($results)) {
                    foreach ($results as $result) {
                        if (Tools::compare_files(@stat($result), $array)) {
                            $res = dirname($path);

                            if (!file_exists($res)) mkdir($res, 0755, true);

                            switch (Config::get('create_hard_links')) {
                                case 'link':
                                    symlink($result, $path);
                                    Log::write_log(Language::t("Created hard link for %s", basename($array['file'])), 3, static::$version);
                                    break;
                                case 'fsutil':
                                    shell_exec(sprintf("fsutil hardlink create %s %s", $path, $result));
                                    Log::write_log(Language::t("Created hard link for %s", basename($array['file'])), 3, static::$version);
                                    break;
                                case 'copy':
                                default:
                                    copy($result, $path);
                                    Log::write_log(Language::t("Copied file %s", basename($array['file'])), 3, static::$version);
                                    break;
                            }

                            static::$updated = true;

                            break;
                        }
                    }
                    if (!file_exists($path) && !array_search($array['file'], $download_files)) $download_files[] = $array;
                } else $download_files[] = $array;
            }
        }
        return [$download_files, $needed_files];
    }

    /**
     * @param $file
     * @return int|null
     */
    static public function get_DB_version($file)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);

        if (!file_exists($file)) return null;

        $content = file_get_contents($file);
        $upd = Parser::parse_line($content, "versionid");
        $max = 0;

        if (isset($upd) && count($upd) > 0)
            foreach ($upd as $key) $max = $max < intval($key) ? $key : $max;

        return $max;
    }
}
