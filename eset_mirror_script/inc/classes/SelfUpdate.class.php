<?php

/**
 * Class SelfUpdate
 */
class SelfUpdate
{
    /**
     * @var
     */
    static private $list_to_update;

    /**
     * @var
     */
    static private $CONF;

    /**
     * @var
     */
    static private $CONNECTION;

    /**
     * @return bool
     */
    static public function is_need_to_update()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);
        return !empty(static::$list_to_update);
    }

    /**
     * @return array
     */
    static private function get_hashes_from_server()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);
        $content = Tools::download_file(
            ([
                    CURLOPT_URL => "http://" . static::$CONF['server'] . "/" . static::$CONF['dir'] . "/" . static::$CONF['file'],
                    CURLOPT_PORT => static::$CONF['port'],
                    CURLOPT_RETURNTRANSFER => 1
                ] + static::getConnectionInfo()),
            $headers);
        $arr = [];

        if (preg_match_all("/(.+)=(.+)=(.+)/", $content, $result, PREG_OFFSET_CAPTURE))
            foreach ($result[1] as $num => $res)
                $arr[trim($result[1][$num][0])] = [$result[2][$num][0], $result[3][$num][0]];

        return $arr;
    }

    /**
     * @param string $directory
     * @return array
     */
    static private function get_hashes_from_local($directory = "./")
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);
        $hashes = [];
        $d = dir($directory);

        while (false !== ($entry = $d->read())) {
            if (($entry == '.') || ($entry == '..') || ($entry == '.git') || ($entry == 'log') || ($entry == 'www'))
                continue;

            (is_dir($directory . $entry)) ?
                $hashes = array_merge(static::get_hashes_from_local($directory . $entry . DS), $hashes)
                :
                $hashes[str_replace(DS, "/", $directory . $entry)] = [
                    md5_file($directory . $entry),
                    filesize($directory . $entry)
                ];
        }
        $d->close();
        return $hashes;
    }

    /**
     * @return string
     */
    static public function get_version_on_server()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);
        $response = Tools::download_file(
            ([
                    CURLOPT_URL => "http://" . static::$CONF['server'] . "/" . static::$CONF['dir'] . "/" . static::$CONF['version'],
                    CURLOPT_PORT => static::$CONF['port'],
                    CURLOPT_RETURNTRANSFER => 1
                ] + static::getConnectionInfo()),
            $headers);
        return trim($response);
    }

    /**
     * @throws SelfUpdateException
     */
    static public function start_to_update()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);

        foreach (static::$list_to_update as $filename => $info) {
            $fs_filename = str_replace("/", DS, str_replace("./", "", $filename));
            $remote_full_path = sprintf("http://%s/%s/%s", static::$CONF['server'], static::$CONF['dir'], $filename);
            Log::write_log(Language::t("Downloading %s [%s Bytes]", basename($filename), $info), 0);
            Tools::download_file(
                ([
                        CURLOPT_URL => $remote_full_path,
                        CURLOPT_PORT => static::$CONF['port'],
                        CURLOPT_FILE => $fs_filename
                    ] + static::getConnectionInfo()),
                $headers);

            if (is_string($headers))
                //Log::write_log(Language::t("Error while downloading file %s [%s]", basename($filename), $headers), 0);
                throw new SelfUpdateException("Error while downloading file %s [%s]", basename($filename), $headers);
        }
    }

    /**
     * @return int
     * @throws SelfUpdateException
     */
    static public function init()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);

        if (!file_exists(CONF_FILE))
            throw new SelfUpdateException("Config file does not exist!");

        if (!is_readable(CONF_FILE))
            throw new SelfUpdateException("Can't read config file! Check the file and its permissions!");

        $ini = (parse_ini_file(CONF_FILE, true));
        static::$CONF = $ini['SELFUPDATE'];
        static::$CONNECTION = $ini['CONNECTION'];

        if (empty(static::$CONF))
            throw new SelfUpdateException("SelfUpdate parameters don't set!");

        if (empty(static::$CONNECTION))
            throw new SelfUpdateException("Connection parameters don't set!");

        if (static::$CONF['enabled'] > 0) {
            if (static::ping() === true) {
                $remote_hashes = static::get_hashes_from_server();
                $local_hashes = static::get_hashes_from_local();

                foreach ($remote_hashes as $filename => $info)
                    if (!isset($local_hashes[$filename]) || $local_hashes[$filename][0] !== $remote_hashes[$filename][0])
                        static::$list_to_update[$filename] = $info[1];

                if (static::is_need_to_update()) {
                    Log::informer(Language::t("New version is available on server [%s]!", static::get_version_on_server()), null, 0);

                    if (static::$CONF['enabled'] > 1) {
                        static::start_to_update();
                        Log::informer(Language::t("Your script has been successfully updated to version %s!", static::get_version_on_server()), null, 0);
                        return 1;
                    }
                } else
                    Log::write_log(Language::t("You already have actual version of script! No need to update!"), 0);
            } else
                Log::write_log(Language::t("Update server is down!"), 0);
        }

        return 0;
    }

    /**
     * @return bool
     */
    static public function ping()
    {
        return Tools::ping(static::getConnectionInfo(), static::$CONF['server'], static::$CONF['port'], static::$CONF['dir'] . '/' . static::$CONF['file']);
    }

    /**
     * @return array
     */
    static public function getConnectionInfo()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5);

        $options = [
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => static::$CONNECTION['timeout'],
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
        ];

        if (static::$CONNECTION['download_speed_limit'] != 0) {
            $options[CURLOPT_MAX_RECV_SPEED_LARGE] = static::$CONNECTION['download_speed_limit'];
        }

        if (static::$CONNECTION['proxy'] != 0) {
            $options[CURLOPT_PROXY] = static::$CONNECTION['server'];
            $options[CURLOPT_PROXYPORT] = static::$CONNECTION['port'];

            switch (static::$CONNECTION['type']) {
                case 'socks4':
                    $options[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS4;
                    break;
                case 'socks4a':
                    $options[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS4A;
                    break;
                case 'socks5':
                    $options[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
                    break;
                case 'http':
                default:
                    $options[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
                    break;
            }

            if (!empty(static::$CONNECTION['user'])) {
                $options[CURLOPT_PROXYUSERNAME] = static::$CONNECTION['user'];
                $options[CURLOPT_PROXYPASSWORD] = static::$CONNECTION['password'];
            }
        }

        return $options;
    }
}
