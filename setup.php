if (!defined('GLPI_ROOT')) {
    include('../../../inc/includes.php');
}
<?php

define('PLUGIN_DEV_VERSION', '2.1.0');
define('PLUGIN_DEV_MIN_GLPI', '11.0.0');
define('PLUGIN_DEV_MAX_GLPI', '12.0.0');

function plugin_init_dev()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['dev'] = true;

   if (!isset($_SERVER['SCRIPT_NAME'], $_SERVER['HTTP_REFERER']) || str_contains($_SERVER['SCRIPT_NAME'], '/plugins/dev/') || str_contains($_SERVER['SCRIPT_NAME'], '/marketplace/dev/') ||
      str_contains($_SERVER['HTTP_REFERER'], '/plugins/dev/') || str_contains($_SERVER['HTTP_REFERER'], '/marketplace/dev/')) {
      PluginDevProfiler::$disabled = true;
   }

    if (php_sapi_name() !== 'cli' && isset($_SESSION['glpipalette']) && $_SESSION['glpipalette'] === 'darker') {
        $PLUGIN_HOOKS['add_css']['dev'][] = 'css/dev-dark.css';
    } else {
        $PLUGIN_HOOKS['add_css']['dev'][] = 'css/dev.css';
    }
    $PLUGIN_HOOKS['add_javascript']['dev'][] = 'js/dev.js';
    $PLUGIN_HOOKS['add_javascript']['dev'][] = 'js/dom_validation.js';

    if ($_SESSION['glpi_use_mode'] == Session::DEBUG) {
        $PLUGIN_HOOKS['menu_toadd']['dev'] = ['plugins' => 'PluginDevMenu'];
        $PLUGIN_HOOKS['helpdesk_menu_entry']['dev'] = '#';
        $PLUGIN_HOOKS['helpdesk_menu_entry_icon']['dev'] = 'fas fa-tools';
        Plugin::registerClass(PluginDevDbschema::class, [
            'addtabon' => get_declared_classes()
        ]);
        Plugin::registerClass(PluginDevClassviewer::class, [
            'addtabon' => get_declared_classes()
        ]);
        // Add debug tabs here if needed, using standard GLPI plugin hooks
    }
}

function plugin_version_dev()
{

    return [
        'name' => __("GLPI Development Helper", 'dev'),
        'version' => PLUGIN_DEV_VERSION,
        'author' => 'Curtis Conard',
        'license' => 'GPLv2',
        'homepage' => 'https://github.com/cconard96/',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_DEV_MIN_GLPI,
                'max' => PLUGIN_DEV_MAX_GLPI
            ],
            'php' => [
                'min' => '8.4.0'
            ]
        ]
    ];
}

function plugin_dev_check_prerequisites()
{
    if (!method_exists('Plugin', 'checkGlpiVersion')) {
        $version = preg_replace('/^((\d+\.?)+).*$/', '$1', GLPI_VERSION);
        $matchMinGlpiReq = version_compare($version, PLUGIN_DEV_MIN_GLPI, '>=');
        $matchMaxGlpiReq = version_compare($version, PLUGIN_DEV_MAX_GLPI, '<');
        // PHP version check
        if (version_compare(PHP_VERSION, '8.4.0', '<')) {
            echo 'This plugin requires PHP >= 8.4.0.';
            return false;
        }
        if (!$matchMinGlpiReq || !$matchMaxGlpiReq) {
            echo vsprintf(
                'This plugin requires GLPI >= %1$s and < %2$s.',
                [
                    PLUGIN_DEV_MIN_GLPI,
                    PLUGIN_DEV_MAX_GLPI,
                ]
            );
            return false;
        }
    }

    if (!is_readable(__DIR__ . '/vendor/autoload.php') || !is_file(__DIR__ . '/vendor/autoload.php')) {
        echo "Run composer install --no-dev in the dev plugin directory<br>";
        return false;
    }

    return true;
}

function plugin_dev_check_config()
{
    return true;
}
