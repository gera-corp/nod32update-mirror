<?php

/**
 * Class Config
 */
class Config
{
    /**
     * @var
     */
    static private $CONF;

    /**
     * @var array
     */
    static private $LCID = [
        'bgr' => 1026,
        'chs' => 2052,
        'cht' => 1028,
        'csy' => 1029,
        'dan' => 1030,
        'deu' => 1031,
        'enu' => 1033,
        'esl' => 13322,
        'esn' => 3082,
        'eti' => 1061,
        'fin' => 1035,
        'fra' => 1036,
        'frc' => 3084,
        'hrv' => 1050,
        'hun' => 1038,
        'ita' => 1040,
        'kor' => 1042,
        'lth' => 1063,
        'nld' => 1043,
        'nor' => 1044,
        'plk' => 1045,
        'ptb' => 1046,
        'rom' => 1048,
        'rus' => 1049,
        'sky' => 1051,
        'slv' => 1060,
        'sve' => 1053,
        'tha' => 1054,
        'trk' => 1055,
        'ukr' => 1058
    ];

    /**
     * @throws ConfigException
     * @throws Exception
     */
    static public function init()
    {
        if (!file_exists(CONF_FILE)) throw new ConfigException("Config file does not exist!");

        if (!is_readable(CONF_FILE)) throw new ConfigException("Can't read config file! Check the file and its permissions!");

        static::$CONF = parse_ini_file(CONF_FILE, true);

        // Parse mirrors
        if (empty(static::$CONF['ESET']['mirror'])) static::$CONF['ESET']['mirror'] = 'update.eset.com';

        static::$CONF['ESET']['mirror'] = array_map("trim", (explode(",", static::$CONF['ESET']['mirror'])));

        if (preg_match("/^win/i", PHP_OS) == false) {
            if (substr(static::$CONF['SCRIPT']['web_dir'], 0, 1) != DS) {
                static::$CONF['SCRIPT']['web_dir'] = Tools::ds(SELF, static::$CONF['SCRIPT']['web_dir']);
            }
        }
        static::check_config();
    }

    /**
     * @param $nm
     * @return mixed|null
     */
    static function get($nm)
    {
        return isset(static::$CONF[$nm]) ? static::$CONF[$nm] : null;
    }

    /**
     * @param $i
     * @return int
     */
    static public function upd_version_is_set($i)
    {
        return (isset(static::$CONF['ESET']['version' . strval($i)]) ? static::$CONF['ESET']['version' . strval($i)] : 0);
    }

    /**
     * @throws ConfigException
     * @throws Exception
     */
    static private function check_config()
    {
        if (array_search(PHP_OS, array("Darwin", "Linux", "FreeBSD", "OpenBSD", "WINNT")) === false)
            throw new ConfigException("This script doesn't support your OS. Please, contact developer!");

        if (function_exists("date_default_timezone_set") and function_exists("date_default_timezone_get")) {
            if (empty(static::$CONF['SCRIPT']['timezone'])) {
                date_default_timezone_set(@date_default_timezone_get());
            } else {
                if (@date_default_timezone_set(static::$CONF['SCRIPT']['timezone']) === false) {
                    static::$CONF['LOG']['rotate'] = 0;
                    throw new ConfigException("Error in timezone settings! Please, check your config file!");
                }
            }
        }

        if (static::$CONF['LOG']['rotate'] == 1) {
            static::$CONF['LOG']['rotate_size'] = Tools::human2bytes(static::$CONF['LOG']['rotate_size']);

            if (intval(static::$CONF['LOG']['rotate_qty']) < 1) {
                throw new ConfigException("Please, check set up of rotate_qty in your config file!");
            } else {
                static::$CONF['LOG']['rotate_qty'] = intval(static::$CONF['LOG']['rotate_qty']);
            }

            if (intval(static::$CONF['LOG']['type']) < 0 || intval(static::$CONF['LOG']['type']) > 3)
                throw new ConfigException("Please, check set up of type in your config file!");
        }

        if (empty(static::$CONF['SCRIPT']['web_dir']))
            throw new ConfigException("Please, check set up of WWW directory in your config file!");

        while (substr(static::$CONF['SCRIPT']['web_dir'], -1) == DS)
            static::$CONF['SCRIPT']['web_dir'] = substr(static::$CONF['SCRIPT']['web_dir'], 0, -1);

        while (substr(static::$CONF['LOG']['dir'], -1) == DS)
            static::$CONF['LOG']['dir'] = substr(static::$CONF['LOG']['dir'], 0, -1);

        @mkdir(PATTERN, 0755, true);
        @mkdir(static::$CONF['LOG']['dir'], 0755, true);
        @mkdir(static::$CONF['SCRIPT']['web_dir'], 0755, true);
        @mkdir(TMP_PATH, 0755, true);

        if (static::$CONF['SCRIPT']['debug_html'] == 1)
            @mkdir(Tools::ds(static::$CONF['LOG']['dir'], DEBUG_DIR), 0755, true);

        if (static::$CONF['MAILER']['enable'] == 1) {
            if (empty(static::$CONF['MAILER']['sender']) ||
                strpos(static::$CONF['MAILER']['sender'], "@") === FALSE ||
                empty(static::$CONF['MAILER']['recipient']) ||
                strpos(static::$CONF['MAILER']['recipient'], "@") === FALSE
            )
                throw new ConfigException("You didn't set up email address of sender/recipient or it is wrong.Please, check your config file.");

            if (static::$CONF['MAILER']['smtp'] == 1) {
                if (empty(static::$CONF['MAILER']['host']) ||
                    empty(static::$CONF['MAILER']['port'])
                )
                    throw new ConfigException("Please, check SMTP host/port for using SMTP server in your config file.Or disable SMTP server if you don't wanna use it.");

                if (static::$CONF['MAILER']['auth'] == 1) {
                    if (empty(static::$CONF['MAILER']['login']) ||
                        empty(static::$CONF['MAILER']['password'])
                    )
                        throw new ConfigException("Please, check login/password for using SMTP authorization.");
                }
            }
        }

        if (intval(static::$CONF['FIND']['errors_quantity']) <= 0) static::$CONF['FIND']['errors_quantity'] = 1;

        if (!is_readable(PATTERN)) throw new ConfigException("Pattern directory is not readable. Check your permissions!");

        if (!is_writable(static::$CONF['LOG']['dir'])) throw new ConfigException("Log directory is not writable. Check your permissions!");

        if (!is_writable(static::$CONF['SCRIPT']['web_dir'])) throw new ConfigException("Web directory is not writable. Check your permissions!");

        // Link test
        $linktestfile = Tools::ds(static::$CONF['LOG']['dir'], LINKTEST);
        $test = false;
        $status = false;

        if (file_exists($linktestfile)) {
            $status = file_get_contents($linktestfile);

            if (preg_match("/link|fsutil|false/", $status)) $test = true;
        }
        if ($test == false) {
            file_put_contents(Tools::ds(static::$CONF['SCRIPT']['web_dir'], 'linktest'), '');

            if (
                function_exists('link') &&
                symlink(
                    Tools::ds(static::$CONF['SCRIPT']['web_dir'], 'linktest'),
                    Tools::ds(static::$CONF['SCRIPT']['web_dir'], 'linktest2')
                )
            ) {
                $status = 'link';
            } elseif (
                preg_match("/^win/i", PHP_OS) &&
                shell_exec(
                    sprintf(
                        "fsutil hardlink create %s %s",
                        Tools::ds(static::$CONF['SCRIPT']['web_dir'], 'linktest'),
                        Tools::ds(static::$CONF['SCRIPT']['web_dir'], 'linktest2'))
                ) != 0
            ) {
                $status = 'fsutil';
            } else $status = 'false';

            if ($status) unlink(Tools::ds(static::$CONF['SCRIPT']['web_dir'], 'linktest2'));

            unlink(Tools::ds(static::$CONF['SCRIPT']['web_dir'], 'linktest'));
            @file_put_contents($linktestfile, $status);
        }
        static::$CONF['create_hard_links'] = ($status != 'false' ? $status : false);
    }

    /**
     * @return array
     */
    static public function getConnectionInfo()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5);

        $options = [
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => static::$CONF['CONNECTION']['timeout'],
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
        ];

        if (static::$CONF['CONNECTION']['download_speed_limit'] != 0) {
            $options[CURLOPT_MAX_RECV_SPEED_LARGE] = static::$CONF['CONNECTION']['download_speed_limit'];
        }

        if (static::$CONF['CONNECTION']['proxy'] != 0) {
            $options[CURLOPT_PROXY] = static::$CONF['CONNECTION']['server'];
            $options[CURLOPT_PROXYPORT] = static::$CONF['CONNECTION']['port'];

            switch (static::$CONF['CONNECTION']['type']) {
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

            if (!empty(static::$CONF['CONNECTION']['user'])) {
                $options[CURLOPT_PROXYUSERNAME] = static::$CONF['CONNECTION']['user'];
                $options[CURLOPT_PROXYPASSWORD] = static::$CONF['CONNECTION']['password'];
            }
        }

        return $options;
    }
}
