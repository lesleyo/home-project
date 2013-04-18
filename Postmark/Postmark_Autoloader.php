<?php

include('Postmark_Inbound.php');
class Postmark_Autoloader
{
    static public function register()
    {
        ini_set('unserialize_callback_func', 'spl_autoload_call');
        spl_autoload_register(array(new self, 'autoload'));
    }

    static public function autoload($class)
    {
        echo $class; exit();
        if (0 !== strpos($class, 'Postmark_Inbound'))
        {
            return;
        }
        if (file_exists($file = dirname(__FILE__) . '/' . preg_replace("{\\\}", "/",($class)) . '.php'))
        {
            require $file;
        }
    }
}