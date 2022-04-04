<?php

/**
 * Class Nod32ms
 */
class Nod32ms
{
    /**
     * @var
     */
    static private $start_time;

    /**
     * @var
     */
    static private $key_valid_file;

    /**
     * @var
     */
    static private $key_invalid_file;

    static private $foundValidKey = false;

    /**
     * Nod32ms constructor.
     * @throws Exception
     * @throws ToolsException
     */
    public function __construct()
    {
        global $VERSION;
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);
        static::$start_time = time();
        static::$key_valid_file = Tools::ds(Config::get('LOG')['dir'], KEY_FILE_VALID);
        static::$key_invalid_file = Tools::ds(Config::get('LOG')['dir'], KEY_FILE_INVALID);
        Log::write_log(Language::t("Run script %s", $VERSION), 0);
        $this->run_script();
    }

    /**
     * @throws phpmailerException
     */
    public function __destruct()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);
        Log::write_log(Language::t("Total working time: %s", Tools::secondsToHumanReadable(time() - static::$start_time)), 0);
        Log::destruct();
        Log::write_log(Language::t("Stop script."), 0);
    }

    /**
     * @param $version
     * @param bool $return_time_stamp
     * @return mixed|null
     */
    private function check_time_stamp($version, $return_time_stamp = false)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, $version);
        $fn = Tools::ds(Config::get('LOG')['dir'], SUCCESSFUL_TIMESTAMP);
        $timestamps = array();

        if (file_exists($fn)) {
            $handle = file_get_contents($fn);
            $content = Parser::parse_line($handle, false, "/(.+:.+)\n/");

            if (isset($content) && count($content)) {
                foreach ($content as $value) {
                    $result = explode(":", $value);
                    $timestamps[$result[0]] = $result[1];
                }
            }

            if (isset($timestamps[$version])) {
                if ($return_time_stamp) {
                    return $timestamps[$version];
                }
            }
        }
        return null;
    }

    /**
     * @param $size
     */
    private function set_database_size($size)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        $fn = Tools::ds(Config::get('LOG')['dir'], DATABASES_SIZE);
        $sizes = [];

        if (file_exists($fn)) {
            $handle = file_get_contents($fn);
            $content = Parser::parse_line($handle, false, "/(.+:.+)\n/");

            if (isset($content) && count($content)) {
                foreach ($content as $value) {
                    $result = explode(":", $value);
                    $sizes[$result[0]] = $result[1];
                }
            }
        }

        $sizes[Mirror::$version] = $size;
        @unlink($fn);

        foreach ($sizes as $key => $name)
            Log::write_to_file($fn, "$key:$name\r\n");
    }

    /**
     * @return array|null
     */
    private function get_databases_size()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        $fn = Tools::ds(Config::get('LOG')['dir'], DATABASES_SIZE);
        $sizes = [];

        if (file_exists($fn)) {
            $handle = file_get_contents($fn);
            $content = Parser::parse_line($handle, false, "/(.+:.+)\n/");

            if (isset($content) && count($content)) {
                foreach ($content as $value) {
                    $result = explode(":", $value);
                    $sizes[$result[0]] = $result[1];
                }
            }
        }

        return (!empty($sizes)) ? $sizes : null;
    }

    /**
     * @param string $directory
     * @return array
     */
    private function get_all_patterns($directory = PATTERN)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);
        $d = dir($directory);
        static $ar_patterns = [];

        while (false !== ($entry = $d->read())) {
            if (($entry == '.') || ($entry == '..'))
                continue;

            if (is_dir(Tools::ds($directory, $entry))) {
                $this->get_all_patterns(Tools::ds($directory, $entry));
                continue;
            }

            $ar_patterns[] = Tools::ds($directory, $entry);
        }

        $d->close();
        return $ar_patterns;
    }

    /**
     * @param $key
     * @return bool
     */
    private function validate_key($key)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        $result = explode(":", $key);

        if ($this->key_exists_in_file($result[0], $result[1], static::$key_invalid_file)) return false;
        Log::write_log(Language::t("Validating key [%s:%s] for version %s", $result[0], $result[1], Mirror::$version), 4, Mirror::$version);

        Mirror::set_key(array($result[0], $result[1]));
        $ret = Mirror::test_key();

        if (is_bool($ret)) {
            if ($ret) {
                static::$foundValidKey = true;
                $this->write_key($result[0], $result[1]);
                return true;
            } else {
                $this->delete_key($result[0], $result[1]);
            }
        } else {
            Log::write_log(Language::t("Unhandled exception [%s]", $ret), 4);
        }
        return false;
    }

    /**
     * @return array|null
     */
    private function read_keys()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);

        if (!file_exists(static::$key_valid_file)) {
            $h = fopen(static::$key_valid_file, 'x');
            fclose($h);
        }

        $keys = Parser::parse_keys(static::$key_valid_file);

        if (!isset($keys) || !count($keys)) {
            Log::write_log(Language::t("Keys file is empty!"), 4, Mirror::$version);
        }

        foreach ($keys as $value) {
            if ($this->validate_key($value)) return explode(":", $value);
        }

        Log::write_log(Language::t("No working keys were found!"), 4, Mirror::$version);
        return null;
    }

    /**
     * @param string $login
     * @param string $password
     */
    private function write_key($login, $password)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        Log::write_log(Language::t("Found valid key [%s:%s]", $login, $password), 4, Mirror::$version);
        ($this->key_exists_in_file($login, $password, static::$key_valid_file) == false) ?
            Log::write_to_file(static::$key_valid_file, "$login:$password:" . Mirror::$version . "\r\n") :
            Log::write_log(Language::t("Key [%s:%s:%s] already exists", $login, $password, Mirror::$version), 4, Mirror::$version);
    }

    /**
     * @param string $login
     * @param string $password
     */
    private function delete_key($login, $password)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        Log::write_log(Language::t("Invalid key [%s:%s]", $login, $password), 4, Mirror::$version);
        //$log_dir = Config::get('LOG')['dir'];

        ($this->key_exists_in_file($login, $password, static::$key_invalid_file) == false) ?
            Log::write_to_file(static::$key_invalid_file, "$login:$password:" . Mirror::$version . "\r\n") :
            Log::write_log(Language::t("Key [%s:%s] already exists", $login, $password), 4, Mirror::$version);

        if (Config::get('FIND')['remove_invalid_keys'] == 1)
            Parser::delete_parse_line_in_file($login . ':' . $password . ':' . Mirror::$version, static::$key_valid_file);
    }

    /**
     * @param string $login
     * @param string $password
     * @param $file
     * @return bool
     */
    private function key_exists_in_file($login, $password, $file)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        $keys = Parser::parse_keys($file);

        if (isset($keys) && count($keys)) {
            foreach ($keys as $value) {
                $result = explode(":", $value);

                if ($result[0] == $login && $result[1] == $password && $result[2] == Mirror::$version)
                    return true;
            }
        }

        return false;
    }

    /**
     * @param string $search
     * @return string
     */
    private function strip_tags_and_css($search)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);
        $document = array(
            "'<script[^>]*?>.*?<\/script>'si",
            "'<[\/\!]*?[^<>]*?>'si",
            "'([\r\n])[\s]+'",
            "'&(quot|#34);'i",
            "'&(amp|#38);'i",
            "'&(lt|#60);'i",
            "'&(gt|#62);'i",
            "'&(nbsp|#160);'i",
            "'&(iexcl|#161);'i",
            "'&(cent|#162);'i",
            "'&(pound|#163);'i",
            "'&(copy|#169);'i",
            "'&#(\d+);'e"
        );
        $replace = array(
            "",
            "",
            "\\1",
            "\"",
            "&",
            "<",
            ">",
            " ",
            chr(161),
            chr(162),
            chr(163),
            chr(169),
            "chr(\\1)"
        );
        return trim(preg_replace($document, $replace, $search));
    }

    /**
     * @param string $this_link
     * @param integer $level
     * @param array $pattern
     * @return bool
     */
    private function parse_www_page($this_link, $level, $pattern)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        static::$foundValidKey = false;
        $search = Tools::download_file(
            ([
                    CURLOPT_URL => $this_link,
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_NOBODY => 0,
                    CURLOPT_USERAGENT => "Mozilla/5.0 (Windows; U; Windows NT 6.1; rv:2.2) Gecko/20110201"
                ] + Config::getConnectionInfo()),
            $headers
        );

        if ($search === false) {
            Log::write_log(Language::t("Link wasn't found [%s]", $this_link), 4, Mirror::$version);
            return false;
        }

        Log::write_log(Language::t("Link was found [%s]", $this_link), 4, Mirror::$version);
        $login = array();
        $password = array();

        if (Config::get('SCRIPT')['debug_html'] == 1) {
            $path_info = pathinfo($this_link);
            $dir = Tools::ds(Config::get('LOG')['dir'], DEBUG_DIR, $path_info['basename']);
            @mkdir($dir, 0755, true);
            $filename = Tools::ds($dir, $path_info['filename'] . ".log");
            file_put_contents($filename, $this->strip_tags_and_css($search));
        }

        foreach ($pattern as $key)
            Parser::parse_template($search, $key, $login, $password);
        $logins = count($login);

        if ($logins > 0) {
            Log::write_log(Language::t("Found keys: %s", $logins), 4, Mirror::$version);

            for ($b = 0; $b < $logins; $b++) {
                if (preg_match("/script|googleuser/i", $password[$b]) and
                    $this->key_exists_in_file($login[$b], $password[$b], static::$key_valid_file)
                )
                    continue;

                if ($this->validate_key($login[$b] . ':' . $password[$b])) {
                    static::$foundValidKey = true;
                    return true;
                }
            }
        }

        if ($level > 1) {
            $links = array();
            preg_match_all('/href *= *"([^\s"]+)/', $search, $results);

            foreach ($results[1] as $result) {
                str_replace('webcache.googleusercontent.com/search?q=cache:', '', $result);

                if (!preg_match("/youtube.com|ocialcomments.org/", $result)) {
                    preg_match('/https?:\/\/(?(?!\&amp).)*/', $result, $res);

                    if (!empty($res[0]))
                        $links[] = $res[0];
                }
            }
            Log::write_log(Language::t("Found links: %s", count($links)), 4, Mirror::$version);

            foreach ($links as $url) {
                $this->parse_www_page($url, $level - 1, $pattern);

                if (static::$foundValidKey)
                    return true;

            }
        }

        return false;
    }

    /**
     * @return bool
     */
    private function get_key_from_server()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        $FIND = Config::get('FIND');

        if ($FIND['use_server']) {
            try {
                $ch = curl_init();
                $opt = [

                    CURLOPT_URL => $FIND['server_url'],
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_RETURNTRANSFER => 1
                ];
                if (strlen($FIND['user']) > 0 ) {
                    $opt[CURLOPT_POST] = true;
                    $opt[CURLOPT_POSTFIELDS] = "user=" . $FIND['user'];
                }
                curl_setopt_array($ch, $opt);
                $key = curl_exec($ch);
                if ($key == false) {
                    Log::write_log(Language::t("Error %s", curl_error($ch)), 5, Mirror::$version);
                    return false;
                }

                $key = json_decode($key, true);
                if ($key && $this->validate_key($key['username'] . ':' . $key['password'])) {
                    static::$foundValidKey = true;
                    return true;
                }
            } catch (Exception $ex)
            {
                Log::write_log(Language::t("Error %s", $ex->getMessage()), 5, Mirror::$version);
            }
        }
        return false;
    }

    /**
     * @return null
     */
    private function find_keys()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        $FIND = Config::get('FIND');

        $attempts = 0;
        while ($attempts < $FIND['number_attempts']) {
            if ($this->get_key_from_server()) return true;
            $attempts++;
        }

        if ($FIND['auto'] != 1)
            return null;

        if (empty($FIND['system'])) {
            $patterns = $this->get_all_patterns();
            shuffle($patterns);
        } else {
            $patterns = [PATTERN . $FIND['system'] . '.pattern'];
        }

        while ($elem = array_shift($patterns)) {
            $pattern_name = pathinfo($elem);
            Log::write_log(Language::t("Begining search at %s", $pattern_name['basename']), 4, Mirror::$version);
            $find = @file_get_contents($elem);

            if (!$find) {
                Log::write_log(Language::t("File %s doesn't exist!", $pattern_name['basename']), 4, Mirror::$version);
                continue;
            }

            $link = Parser::parse_line($find, "link");
            $pageindex = Parser::parse_line($find, "pageindex");
            $pattern = Parser::parse_line($find, "pattern");
            $page_qty = Parser::parse_line($find, "page_qty");
            $recursion_level = Parser::parse_line($find, "recursion_level");

            if (empty($link)) {
                Log::write_log(Language::t("[link] doesn't set up in %s file!", $elem), 4, Mirror::$version);
                continue;
            }

            if (empty($pageindex)) $pageindex[] = $FIND['pageindex'];

            if (empty($pattern)) $pattern[] = $FIND['pattern'];

            if (empty($page_qty)) $page_qty[] = $FIND['page_qty'];

            if (empty($recursion_level)) $recursion_level[] = $FIND['recursion_level'];

            $queries = explode(", ", $FIND['query']);

            foreach ($queries as $query) {
                $pages = substr_count($link[0], "#PAGE#") ? $page_qty[0] : 1;

                for ($i = 0; $i < $pages; $i++) {
                    $this_link = str_replace("#QUERY#", str_replace(" ", "+", trim($query)), $link[0]);
                    $this_link = str_replace("#PAGE#", ($i * $pageindex[0]), $this_link);

                    if ($this->parse_www_page($this_link, $recursion_level[0], $pattern) == true) break(3);
                }
            }
        }

        return null;
    }

    /**
     *
     */
    private function generate_html()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);
        Log::write_log(Language::t("Generating html..."), 0);
        $total_size = $this->get_databases_size();
        $web_dir = Config::get('SCRIPT')['web_dir'];
        $ESET = Config::get('ESET');
        $html_page = '';

        if (Config::get('SCRIPT')['generate_only_table'] == '0') {
            $html_page .= '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">';
            $html_page .= '<html>';
            $html_page .= '<head>';
            $html_page .= '<title>' . Language::t("ESET NOD32 update server") . '</title>';
            $html_page .= '<meta http-equiv="Content-Type" content="text/html; charset=' . Config::get('SCRIPT')['html_codepage'] . '">';
            $html_page .= '<style type="text/css">html,body{height:100%;margin:0;padding:0;width:100%}table#center{border:0;height:100%;width:100%}table td table td{text-align:center;vertical-align:middle;font-weight:bold;padding:10px 15px;border:0}table tr:nth-child(odd){background:#eee}table tr:nth-child(even){background:#fc0}</style>';
            $html_page .= '</head>';
            $html_page .= '<body>';
            $html_page .= '<table id="center">';
            $html_page .= '<tr>';
            $html_page .= '<td align="center">';
        }

        $html_page .= '<table>';
        $html_page .= '<tr><td colspan="4">' . Language::t("ESET NOD32 update server") . '</td></tr>';
        $html_page .= '<tr>';
        $html_page .= '<td>' . Language::t("Version") . '</td>';
        $html_page .= '<td>' . Language::t("Database version") . '</td>';
        $html_page .= '<td>' . Language::t("Database size") . '</td>';
        $html_page .= '<td>' . Language::t("Last update") . '</td>';
        $html_page .= '</tr>';

        global $DIRECTORIES;

        foreach ($DIRECTORIES as $ver => $dir) {
            if (Config::upd_version_is_set($ver) == '1') {
                $update_ver = Tools::ds($web_dir, preg_replace('/eset_upd\/update\.ver/is','eset_upd/v3/update.ver', isset($dir['dll']) && $dir['dll'] ? $dir['dll'] : $dir['file']));
                $version = Mirror::get_DB_version($update_ver);
                $timestamp = $this->check_time_stamp($ver, true);
                $html_page .= '<tr>';
                $html_page .= '<td>' . $dir['name'] . '</td>';
                $html_page .= '<td>' . $version . '</td>';
                $html_page .= '<td>' . (isset($total_size[$ver]) ? Tools::bytesToSize1024($total_size[$ver]) : Language::t("n/a")) . '</td>';
                $html_page .= '<td>' . ($timestamp ? date("Y-m-d, H:i:s", $timestamp) : Language::t("n/a")) . '</td>';
                $html_page .= '</tr>';
            }
        }

        $html_page .= '<tr>';
        $html_page .= '<td colspan="2">' . Language::t("Present platforms") . '</td>';
        $html_page .= '<td colspan="2">' . ($ESET['x32'] == 1 ? '32bit' : '') . ($ESET['x64'] == 1 ? ($ESET['x32'] ? ', 64bit' : '64bit') : '') . '</td>';
        $html_page .= '</tr>';

        $html_page .= '<tr>';
        $html_page .= '<td colspan="2">' . Language::t("Last execution of the script") . '</td>';
        $html_page .= '<td colspan="2">' . (static::$start_time ? date("Y-m-d, H:i:s", static::$start_time) : Language::t("n/a")) . '</td>';
        $html_page .= '</tr>';

        $html_page .= '<tr>';
        $html_page .= '<td colspan="2">Telegram Channel</td>';
        $html_page .= '<td colspan="2"><a href="https://t.me/nod32trialKeys" target="_blank">NOD32 Trial Keys</a></td>';
        $html_page .= '</tr>';

        $html_page .= '<tr>';
        $html_page .= '<td colspan="2">Web Site with trial keys</td>';
        $html_page .= '<td colspan="2"><a href="https://nod32-trial-keys.site/" target="_blank">NOD32 Trial Keys</a></td>';
        $html_page .= '</tr>';

        if (Config::get('SCRIPT')['show_login_password']) {
            if (file_exists(static::$key_valid_file)) {
                $keys = Parser::parse_keys(static::$key_valid_file);


                $html_page .= '<tr>';
                $html_page .= '<td>' . Language::t("Version") . '</td>';
                $html_page .= '<td>' . Language::t("Used login") . '</td>';
                $html_page .= '<td>' . Language::t("Used password") . '</td>';
                $html_page .= '<td>' . Language::t("Expiration date") . '</td>';
                $html_page .= '</tr>';

                foreach ($keys as $k) {
                    $key = explode(":", $k);
                    $html_page .= '<tr>';
                    $html_page .= '<td>' . $key[2] . '</td>';
                    $html_page .= '<td>' . $key[0] . '</td>';
                    $html_page .= '<td>' . $key[1] . '</td>';
                    $html_page .= '<td>' . $key[3] . '</td>';
                    $html_page .= '</tr>';
                }
            }
        }
        $html_page .= '</table>';
        $html_page .= (Config::get('SCRIPT')['generate_only_table'] == '0') ? '</td></tr></table></body></html>' : '';
        $file = Tools::ds($web_dir, Config::get('SCRIPT')['filename_html']);

        if (file_exists($file)) @unlink($file);

        Log::write_to_file($file, Tools::conv($html_page, Config::get('SCRIPT')['html_codepage']));
    }

    /**
     * @throws Exception
     * @throws ToolsException
     */
    private function run_script()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);
        global $DIRECTORIES;
        $total_size = array();
        $total_downloads = array();
        $average_speed = array();


        foreach ($DIRECTORIES as $version => $dir) {
            if (Config::upd_version_is_set($version) == '1') {

                Log::write_log(Language::t("Init Mirror for version %s in %s", $version, $dir['name']), 5, $version);
                Mirror::init($version, $dir);

                static::$foundValidKey = false;
                $this->read_keys();

                if (static::$foundValidKey == false) {
                    $this->find_keys();

                    if (static::$foundValidKey == false) {
                        Log::write_log(Language::t("The script has been stopped!"), 1, Mirror::$version);
                        continue;
                    }
                }

                $old_version = Mirror::get_DB_version(Mirror::$local_update_file);

                if (!empty(Mirror::$mirrors)) {

                    if (!empty(Mirror::$mirrors)) {
                        $mirror = array_shift(Mirror::$mirrors);

                        if ($old_version && $this->compare_versions($old_version, $mirror['db_version'])) {
                            Log::informer(Language::t("Your version of database is relevant %s", $old_version), Mirror::$version, 2);
                            continue;
                        }

                        list($size, $downloads, $speed) = Mirror::download_signature();
                        $this->set_database_size($size);

                        if (!Mirror::$updated && $old_version != 0 && !$this->compare_versions($old_version, $mirror['db_version'])) {
                            Log::informer(Language::t("Your database has not been updated!"), Mirror::$version, 1);
                        } else {
                            $total_size[Mirror::$version] = $size;
                            $total_downloads[Mirror::$version] = $downloads;
                            if (!empty($speed)) {
                                $average_speed[Mirror::$version] = $speed;
                            }

                            if ($old_version && !$this->compare_versions($old_version, $mirror['db_version'])) {
                                Log::informer(Language::t("Your database was successfully updated from %s to %s", $old_version, $mirror['db_version']), Mirror::$version, 2);
                            } else {
                                Log::informer(Language::t("Your database was successfully updated to %s", $mirror['db_version']), Mirror::$version, 2);
                            }
                        }
                    }
                } else {
                    Log::write_log(Language::t("All mirrors is down!"), 1, Mirror::$version);
                }

                Mirror::destruct();
            }
        }

        foreach (glob(Tools::ds(TMP_PATH, '*')) as $folder) {
            static::clear_tmp($folder);
            @rmdir($folder);
        }

        Log::write_log(Language::t("Total size for all databases: %s", Tools::bytesToSize1024(array_sum($total_size))), 3);

        if (array_sum($total_downloads) > 0)
            Log::write_log(Language::t("Total downloaded for all databases: %s", Tools::bytesToSize1024(array_sum($total_downloads))), 3);

        if (array_sum($average_speed) > 0)
            Log::write_log(Language::t("Average speed for all databases: %s/s", Tools::bytesToSize1024(array_sum($average_speed) / count($average_speed))), 3);

        if (Config::get('SCRIPT')['generate_html'] == '1') $this->generate_html();
    }

    private function clear_tmp($path)
    {
        try {
            $iterator = new DirectoryIterator($path);
            foreach ( $iterator as $fileinfo) {
                if($fileinfo->isDot())continue;
                if($fileinfo->isDir()){
                    if(static::clear_tmp($fileinfo->getPathname()))
                        @rmdir($fileinfo->getPathname());
                }
                if($fileinfo->isFile()){
                    @unlink($fileinfo->getPathname());
                }
            }
        } catch ( Exception $e ){
            // write log
            return false;
        }
        return true;
    }

    /**
     * @param $old_version
     * @param $new_version
     * @return bool
     */
    private function compare_versions($old_version, $new_version)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        Log::write_log(Language::t("Compare %s >= %s", $old_version, $new_version), 5, Mirror::$version);
        return (intval($old_version) >= intval($new_version));
    }
}
