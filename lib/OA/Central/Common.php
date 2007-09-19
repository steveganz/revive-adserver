<?php

/*
+---------------------------------------------------------------------------+
| Openads v${RELEASE_MAJOR_MINOR}                                                              |
| ============                                                              |
|                                                                           |
| Copyright (c) 2003-2007 Openads Limited                                   |
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

require_once MAX_PATH . '/lib/OA.php';
require_once MAX_PATH . '/lib/OA/Dal.php';
require_once MAX_PATH . '/lib/OA/Dal/ApplicationVariables.php';
require_once MAX_PATH . '/lib/OA/Dal/Central/Common.php';
require_once MAX_PATH . '/lib/OA/Central/RpcMapper.php';

require_once 'Cache/Lite/Function.php';


/**
 * OAP binding to the common OAC API
 *
 */
class OA_Central_Common
{
    /**
     * @var OA_Central_RpcMapper
     */
    var $oMapper;

    /**
     * @var OA_Dal_Central_AdNetworks
     */
    var $oDal;

    /**
     * @var Cache_Lite
     */
    var $oCache;

    /**
     * Class constructor
     *
     * @return OA_Central_AdNetworks
     */
    function OA_Central_Common()
    {
        $this->oMapper = new OA_Central_RpcMapper();
        $this->oDal = new OA_Dal_Central_Common();
        $this->oCache = new Cache_Lite_Function(array(
            'cacheDir '    => MAX_PATH . '/var/cache',
            'lifeTime'     => 86400,
            'defaultGroup' => get_class($this)
        ));
    }

    /**
     * Refs R-AN-1: Connecting Openads Platform with SSO
     *
     * @todo Need clarification
     *
     * @return boolean True on success
     */
    function connectOAPToOAC()
    {
        $result = $this->oMapper->connectOAPToOAC();

        if (PEAR::isError($result)) {
            return false;
        }

        return true;
    }

    /**
     * A method to retrieve the image data of a captcha image
     *
     * @see R-AN-20: Captcha Validation
     *
     * @return mixed An array with the image content type and binary data if successful,
     *               FALSE otherwise
     */
    function getCaptcha()
    {
        $result = $this->oMapper->getCaptcha();

        if (PEAR::isError($result)) {
            return false;
        }

        return $result;
    }

}

?>
