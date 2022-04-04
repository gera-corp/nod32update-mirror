<?php

/**
 * Class Language
 */
class Language
{
    /**
     * @var null
     */
    static private $language = null;

    /**
     * @var
     */
    static private $language_file = null;

    /**
     * @var
     */
    static private $language_pack = array();

    /**
     * @var
     */
    static private $default_language_pack = array();

    /**
     * @var
     */
    static private $default_language_file = null;

    /**
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

        static::$language = $ini['SCRIPT']['language'];
        static::$language_file = Tools::ds(LANGPACKS_DIR, static::$language . '.lng');
        static::$default_language_file = Tools::ds(LANGPACKS_DIR, 'en.lng');

        if (static::$language != 'en') {
            if (!file_exists(static::$language_file))
                throw new Exception("Language file [" . static::$language . ".lng] does not exist!");
        } else return;

        $tmp = file(static::$language_file);
        static::$default_language_pack = file(static::$default_language_file);

        if (count($tmp) != count(static::$default_language_pack))
            throw new Exception("Language file [" . static::$language . ".lng] is corrupted!");

        for ($i = 0; $i < count($tmp); $i++)
            static::$language_pack[trim($tmp[$i])] = trim(static::$default_language_pack[$i]);
    }

    /**
     * @return string
     */
    static public function t()
    {
        $text = func_get_arg(0);
        $params = func_get_args();
        @array_shift($params);
        $key = array_search($text, static::$language_pack);
        return (array_search($text, static::$language_pack) != FALSE) ? vsprintf($key, $params) : vsprintf($text, $params);
    }
}
