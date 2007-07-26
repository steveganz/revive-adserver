<?php

/*
+---------------------------------------------------------------------------+
| Openads v2.3                                                              |
| =================                                                         |
|                                                                           |
| Copyright (c) 2003-2007 Openads Ltd                                       |
| For contact details, see: http://www.openads.org/                         |
|                                                                           |
| This program is free software; you can redistribute it and/or modify      |
| it under the terms of the GNU General Public License as published by      |
| the Free Software Foundation; either version 2 of the License, or         |
| (at your option) any later version.                                       |
|                                                                           |
| This program is distributed in the hope that it will be useful,           |
| but WITHOUT ANY WARRANTY; without even the implied warranty of            |
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             |
| GNU General Public License for more details.                              |
|                                                                           |
| You should have received a copy of the GNU General Public License         |
| along with this program; if not, write to the Free Software               |
| Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA |
+---------------------------------------------------------------------------+
$Id$
*/

/**
 * Openads Upgrade Class
 *
 * @author Monique Szpak <monique.szpak@openads.org>
 *
 */
define('OA_ENV_ERROR_PHP_NOERROR',                    1);
define('OA_ENV_ERROR_PHP_VERSION',                   -1);
define('OA_ENV_ERROR_PHP_MEMORY',                    -2);
define('OA_ENV_ERROR_PHP_SAFEMODE',                  -3);
define('OA_ENV_ERROR_PHP_MAGICQ',                    -4);
define('OA_ENV_ERROR_PHP_TIMEZONE',                  -5);
define('OA_ENV_ERROR_PHP_UPLOADS',                   -6);
define('OA_ENV_ERROR_PHP_ARGC',                      -7);
define('OA_ENV_ERROR_PHP_LIBXML',                    -8);

require_once MAX_PATH.'/lib/OA/DB.php';
require_once MAX_PATH . '/lib/OA/Admin/Config.php';

define('OA_MEMORY_UNLIMITED', 'Unlimited');

class OA_Environment_Manager
{

    var $aInfo = array();

    function OA_Environment_Manager()
    {
        $conf = $GLOBALS['_MAX']['CONF'];

        $this->aInfo['PERMS']['expected'] = array(
                                                  MAX_PATH.'/var',
                                                  MAX_PATH.'/var/cache',
                                                  MAX_PATH.'/var/plugins',
                                                  MAX_PATH.'/var/plugins/cache',
                                                  MAX_PATH.'/var/plugins/config',
                                                  MAX_PATH.'/var/templates_compiled'
                                                 );

        // if CONF file hasn't been created yet, use the default images folder
        if (!empty($conf['store']['webDir'])) {
            $this->aInfo['PERMS']['expected'][] = $conf['store']['webDir'];
        } else {
            $this->aInfo['PERMS']['expected'][] = MAX_PATH.'/www/images';
        }

        if (!empty($conf['delivery']['cachePath'])) {
            $this->aInfo['PERMS']['expected'][] = $conf['delivery']['cachePath'];
        }

        $this->aInfo['PHP']['actual']     = array();
        $this->aInfo['PERMS']['actual']   = array();
        $this->aInfo['FILES']['actual']   = array();

        $this->aInfo['PHP']['expected']['version']              = '4.3.11';
        $this->aInfo['PHP']['expected']['magic_quotes_runtime'] = '0';
        $this->aInfo['PHP']['expected']['safe_mode']            = '0';
        $this->aInfo['PHP']['expected']['file_uploads']         = '1';
        $this->aInfo['PHP']['expected']['register_argc_argv']   = '1';
        $this->aInfo['PHP']['expected']['libxml']               = true;

        $this->aInfo['FILES']['expected'] = array();
    }

    function checkSystem()
    {
        $this->getAllInfo();
        $this->checkCritical();
        return $this->aInfo;
    }

    function getAllInfo()
    {
        $this->aInfo['PHP']['actual']     = $this->getPHPInfo();
        $this->aInfo['PERMS']['actual']   = $this->getFilePermissionErrors();
        $this->aInfo['FILES']['actual']   = $this->getFileIntegInfo();
        return $this->aInfo;
    }

    function getPHPInfo()
    {
        $aResult['version'] = phpversion();

        $aResult['memory_limit'] = getMemorySizeInBytes();
        if ($aResult['memory_limit'] == -1) {
            $aResult['memory_limit'] = OA_MEMORY_UNLIMITED;
        }
        $aResult['magic_quotes_runtime'] = get_magic_quotes_runtime();
        $aResult['safe_mode']            = ini_get('safe_mode');
        $aResult['date.timezone']        = (ini_get('date.timezone') ? ini_get('date.timezone') : getenv('TZ'));
        $aResult['register_argc_argv']   = ini_get('register_argc_argv');
        $aResult['file_uploads']         = ini_get('file_uploads');
        $aResult['libxml']               = extension_loaded('libxml');

        return $aResult;
    }

    function getFileIntegInfo()
    {
        return false;
    }

    /**
     * Check access to an array of requried files/folders
     *
     * @return array of error messages
     */
    function getFilePermissionErrors()
    {
        $aErrors = array();

        // Test that all of the required files/directories can
        // be written to by the webserver
        foreach ($this->aInfo['PERMS']['expected'] as $file)
        {
            if (empty($file))
            {
                continue;
            }
            $aErrors[$file] = 'OK';
            if (!file_exists($file))
            {
                $aErrors[$file] = 'NOT writeable';
            }
            elseif (!is_writable($file))
            {
                $aErrors[$file] = 'NOT writeable';
            }
        }

        // If upgrading, must also be able to write to:
        //  - The configuration file (if the web hosts is the same as
        //    it was, the user cannot have the config file locked, as
        //    new items might need to be merged into the config).
        //  - The INSTALLED file needs to be able to be "touched",
        //    as this is done for all upgrades/installs.
        if (OA_INSTALLATION_STATUS != OA_INSTALLATION_STATUS_INSTALLED) {
            $configFile = MAX_PATH . '/var/' . getHostName() . '.conf.php';
            if (file_exists($configFile)) {
                if (!OA_Admin_Config::isConfigWritable($configFile)) {
                    $aErrors[$configFile] = 'NOT writeable';
                }
            }
            $installerFile = MAX_PATH . '/var/INSTALLED';
            if (file_exists($installerFile)) {
                if (!is_writable($installerFile)) {
                    $aErrors[$installerFile] = 'NOT writeable';
                }
            }
        }

        if (count($aErrors))
        {
            return $aErrors;
        }
        return false;
    }

    function checkCritical()
    {
        $this->_checkCriticalPHP();
        $this->_checkCriticalFilePermissions();
        $this->_checkCriticalFiles();
        return $this->aInfo;
    }

    /**
     * Check if amount of memory is enough for our application
     *
     * @return boolean  True if amount of memory is enough, else false
     */
    function checkMemory()
    {
        $memlim = $this->aInfo['PHP']['actual']['memory_limit'];
        $expected = getMinimumRequiredMemory();
        if ($memlim != OA_MEMORY_UNLIMITED && ($memlim > 0) && ($memlim < $expected))
        {
            return false;
        }
        return true;
    }

    /**
     * A private method to test the configuration of the user's PHP environment.#
     *
     * Tests the following values, and in the event of a fatal error or a
     * warning, the value set is listed below:
     *
     *  - The PHP version
     *      Sets: $this->aInfo['PHP']['warning'][OA_ENV_ERROR_PHP_VERSION]
     *
     *  - The PHP configuration's memory_limit value
     *      Sets: $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_MEMORY]
     *
     *  - The PHP configuration's safe_mode value
     *      Sets: $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_SAFEMODE]
     *
     *  - The PHP configuration's magic_quotes_runtime value
     *      Sets: $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_MAGICQ]
     *
     *  - The PHP configuration's file_uploads value
     *      Sets: $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_UPLOADS]
     *
     * Otherwise, if there are no errors or warnings, then $this->aInfo['PHP']['error']
     * is set to "false".
     *
     * @access private
     * @return void
     */
    function _checkCriticalPHP()
    {
        // Test the PHP version
        if (function_exists('version_compare'))
        {
            $result = version_compare(
                $this->aInfo['PHP']['actual']['version'],
                $this->aInfo['PHP']['expected']['version'],
                "<"
            );
            // Carry on and test if this is PHP 4.3.10
            $result4310 = version_compare(
                $this->aInfo['PHP']['actual']['version'],
                '4.3.10',
                "=="
            );
            // Carry on and test if this is PHP 4.4.1
            $result441 = version_compare(
                $this->aInfo['PHP']['actual']['version'],
                '4.4.1',
                "=="
            );
            if ($result || $result4310 || $result441) {
                $result = OA_ENV_ERROR_PHP_VERSION;
            } else {
                $result = OA_ENV_ERROR_PHP_NOERROR;
            }
        }
        else
        {
            // The user's PHP version is well old - it doesn't
            // even have the version_compare() function!
            $result = OA_ENV_ERROR_PHP_VERSION;
        }
        if ($result == OA_ENV_ERROR_PHP_VERSION)
        {
            if (!empty($result4310))
            {
                // Uh oh! Cannot allow install on PHP 4.3.10 - DB_DataObjects will cause PHP to crash!
                $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_VERSION] =
                    "Version {$this->aInfo['PHP']['actual']['version']} is below the minimum supported version of {$this->aInfo['PHP']['expected']['version']}." .
                    "<br />It is not possible to install Openads with PHP 4.3.10, due to a bug in PHP! " .
                    "Please see the <a href='http://docs.openads.org/help/2.3/faq/'>FAQ</a> for more information.";
            }
            else if (!empty($result441))
            {
                // Uh oh! Cannot allow install on PHP 4.4.1 - error use of with next() in looping!
                $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_VERSION] =
                    "Version {$this->aInfo['PHP']['actual']['version']} is above the minimum supported version of {$this->aInfo['PHP']['expected']['version']}," .
                    "<br />However, it is not possible to install Openads with PHP 4.4.1, due to a bug in PHP! " .
                    "Please see the <a href='http://docs.openads.org/help/2.3/faq/'>FAQ</a> for more information.";
            }
            else
            {
                $this->aInfo['PHP']['warning'][OA_ENV_ERROR_PHP_VERSION] =
                    "Version {$this->aInfo['PHP']['actual']['version']} is below the minimum supported version of {$this->aInfo['PHP']['expected']['version']}." .
                    "<br />Although you can install Openads, this is not a supported version, and it is not possible to guarantee that everything will work correctly. " .
                    "Please see the <a href='http://docs.openads.org/help/2.3/faq/'>FAQ</a> for more information.";
            }
        }
        else
        {
            $this->aInfo['PHP']['error'] = false;
        }

        // Test the PHP configuration's memory_limit value
        if (!$this->checkMemory())
        {
            $result = OA_ENV_ERROR_PHP_MEMORY;
            $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_MEMORY] = 'memory_limit needs to be increased';
        }

        // Test the PHP configuration's safe_mode value
        if ($this->aInfo['PHP']['actual']['safe_mode'])
        {
            $result = OA_ENV_ERROR_PHP_SAFEMODE;
            $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_SAFEMODE] = 'safe_mode must be OFF';
        }

        // Test the PHP configuration's magic_quotes_runtime value
        if ($this->aInfo['PHP']['actual']['magic_quotes_runtime'])
        {
            $result = OA_ENV_ERROR_PHP_MAGICQ;
            $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_MAGICQ] = 'magic_quotes_runtime must be OFF';
        }

        // Test the PHP configuration's file_uploads value
        if (!$this->aInfo['PHP']['actual']['file_uploads']) {
            $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_UPLOADS] = 'file_uploads must be ON';
        }
        
        // Test the libxml extension is loaded
        if (!$this->aInfo['PHP']['actual']['libxml']) {
            $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_LIBXML] = 'libxml extension must be loaded';
        }
        
        return $result;
    }

    /**
     * A private method to test for any critial errors resulting from "bad"
     * file or directory permissions.
     *
     * Sets $this->aInfo['PERMS']['error'] to the boolean false if all
     * permissions are acceptable, otherwise, it is set to a string containing
     * an appropriate error message to show to the user on the system check
     * page.
     *
     * @return boolean True when all permissions are okay, false otherwise.
     */
    function _checkCriticalFilePermissions()
    {
        // Test to see if there were any file/directory permission errors
        unset($this->aInfo['PERMS']['error']['filePerms']);
        foreach ($this->aInfo['PERMS']['actual'] AS $k => $v)
        {
            if ($v != 'OK')
            {
                if (is_null($this->aInfo['PERMS']['error']['filePerms']))
                {
                    $this->aInfo['PERMS']['error']['filePerms'] = $GLOBALS['strErrorWritePermissions'];
                }
                $this->aInfo['PERMS']['error']['filePerms'] .= "<br />" . sprintf($GLOBALS['strErrorFixPermissionsCommand'], $k);
            }
        }
        if (!is_null($this->aInfo['PERMS']['error']['filePerms']))
        {
            $this->aInfo['PERMS']['error']['filePerms'] .= "<br />" . $GLOBALS['strCheckDocumentation'];
            return false;
        }
        $this->aInfo['PERMS']['error'] = false;
        return true;
    }

    function _checkCriticalFiles()
    {
        $this->aInfo['FILES']['error'] = false;
        return true;
    }

}

?>
