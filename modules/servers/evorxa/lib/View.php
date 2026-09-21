<?php

namespace WHMCS\Module\Server\Evorxa;

/**
 * Renders the module's own Smarty templates to a string (admin service tab, addon pages)
 * with WHMCS's Smarty instance, so the same security policy and compile dir apply.
 */
class View
{
    public static function templatesDir()
    {
        return dirname(__DIR__) . '/templates';
    }

    public static function render($template, array $vars, $admin = true)
    {
        $path = $template[0] === '/' ? $template : self::templatesDir() . '/' . $template;
        $smarty = class_exists('\WHMCS\Smarty') ? new \WHMCS\Smarty($admin) : new \Smarty();
        foreach ($vars as $key => $value) {
            $smarty->assign($key, $value);
        }
        return $smarty->fetch($path);
    }
}
