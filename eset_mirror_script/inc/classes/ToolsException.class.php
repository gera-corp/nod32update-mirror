<?php
/**
 * Created by PhpStorm.
 * User: d.razumov
 * Date: 05.03.2018
 * Time: 12:00
 */

class ToolsException extends Exception
{
    /**
     * ToolsException constructor.
     */
    public function __construct()
    {
        $message = func_get_arg(0);
        $params = func_get_args();
        @array_shift($params);
        parent::__construct(Language::t($message, $params));
    }

    /**
     * @return string
     */
    public function ErrorMessage()
    {
        return $this->getMessage();
    }
}
