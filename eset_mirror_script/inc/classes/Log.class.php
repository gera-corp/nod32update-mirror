<?php

/**
 * Class Log
 */
class Log
{
    /**
     * @var array
     */
    static private $log = array();

    /**
     * @var string
     */
    static private $mailer_log = "";

    /**
     * @var array
     */
    static private $CONF;

    /**
     * @throws phpmailerException
     */
    static public function destruct()
    {
        if (!empty(static::$mailer_log) && !empty(static::$CONF['MAILER']) && static::$CONF['MAILER']['enable'] == '1') {
            $mailer = new PHPMailer;
            $mailer->CharSet = static::$CONF['MAILER']['codepage'];

            if (static::$CONF['MAILER']['smtp'] == '1') {
                $mailer->Host = static::$CONF['MAILER']['host'];
                $mailer->Port = static::$CONF['MAILER']['port'];
                $mailer->Mailer = "smtp";

                if (static::$CONF['MAILER']['auth'] == '1') {
                    $mailer->SMTPAuth = true;
                    $mailer->SMTPSecure = static::$CONF['MAILER']['secure'];
                    $mailer->Username = static::$CONF['MAILER']['login'];
                    $mailer->Password = static::$CONF['MAILER']['password'];
                } else {
                    $mailer->SMTPAuth = false;
                }
            }

            $mailer->Priority = 3;
            $mailer->Subject = Tools::conv(static::$CONF['MAILER']['subject'], $mailer->CharSet);

            if (static::$CONF['MAILER']['level'] == '3')
                static::$mailer_log = implode("\r\n", static::$log);

            $mailer->Body = Tools::conv(static::$mailer_log, $mailer->CharSet);
            $mailer->SetFrom(static::$CONF['MAILER']['sender'], "NOD32 mirror script");
            $mailer->AddAddress(static::$CONF['MAILER']['recipient'], "Admin");
            $mailer->SMTPDebug = 1;

            if (!$mailer->Send())
                static::write_log($mailer->ErrorInfo, 0);

            $mailer->ClearAddresses();
            $mailer->ClearAttachments();
        }
    }

    /**
     * @param $filename
     * @param $text
     */
    static public function write_to_file($filename, $text)
    {
        $f = fopen($filename, "a+");

        if (!feof($f)) fwrite($f, $text);

        fflush($f);
        fclose($f);
        clearstatcache();
    }

    /**
     * @param $str
     * @param $ver
     * @param int $level
     */
    static public function informer($str, $ver, $level = 0)
    {
        static::write_log($str, $level, $ver);

        if (static::$CONF['MAILER']['log_level'] >= $level)
            static::$mailer_log .= sprintf("[%s] [%s] %s%s", date("Y-m-d"), date("H:i:s"), ($ver ? '[ver. ' . strval($ver) . '] ' : ''), $str) . chr(10);
    }

    /**
     * @param $text
     * @param $level
     * @param null $version
     * @param bool $ignore_rotate
     * @return null
     */
    static public function write_log($text, $level, $version = null, $ignore_rotate = false)
    {
        if (empty($text)) return null;

        if (static::$CONF['type'] == '0') return null;

        if (static::$CONF['level'] < $level) return null;

        $fn = Tools::ds(static::$CONF['dir'], LOG_FILE);

        if (static::$CONF['rotate'] == 1) {
            if (file_exists($fn) && !$ignore_rotate) {
                $arch_ext = Tools::get_archive_extension();
                if (filesize($fn) >= static::$CONF['rotate_size']) {
                    static::write_log(Language::t("Log file was cutted due rotation..."), 0, null, true);
                    array_pop(static::$log);

                    for ($i = static::$CONF['rotate_qty']; $i > 1; $i--) {
                        @unlink($fn . "." . strval($i) . $arch_ext);
                        @rename($fn . "." . strval($i - 1) . $arch_ext, $fn . "." . strval($i) . $arch_ext);
                    }

                    @unlink($fn . ".1" . $arch_ext);
                    Tools::archive_file($fn);
                    @unlink($fn);
                    static::write_log(Language::t("Log file was cutted due rotation..."), 0, null, true);
                    array_pop(static::$log);
                }
            }
        }

        if ($level == 1) {
            static::informer($text, $version, 0);
        } else {
            $text = sprintf("[%s] %s%s", date("Y-m-d, H:i:s"), ($version ? '[ver. ' . strval($version) . '] ' : ''), $text);

            if (static::$CONF['type'] == '1' || static::$CONF['type'] == '3')
                static::write_to_file($fn, Tools::conv($text . "\r\n", static::$CONF['codepage']));

            if (static::$CONF['type'] == '2' || static::$CONF['type'] == '3') echo Tools::conv($text, static::$CONF['codepage']) . chr(10);
        }
        static::$log[] = $text;
        return;
    }

    /**
     * @throws ConfigException
     * @throws Exception
     */
    static public function init()
    {
        if (!file_exists(CONF_FILE))
            throw new ConfigException("Config file does not exist!");

        if (!is_readable(CONF_FILE))
            throw new ConfigException("Can't read config file! Check the file and its permissions!");

        $ini = parse_ini_file(CONF_FILE, true);

        if (empty($ini))
            throw new ConfigException("Empty config file!");

        static::$CONF = $ini['LOG'];
        if (!file_exists(static::$CONF['dir']))
            mkdir(static::$CONF['dir']);
        static::$CONF['rotate_size'] = Tools::human2bytes(static::$CONF['rotate_size']);
        static::$CONF['MAILER'] = $ini['MAILER'];
        static::$CONF['codepage'] = $ini['SCRIPT']['codepage'];

        if (empty(static::$CONF))
            throw new ConfigException("Log parameters don't set!");
    }
}
