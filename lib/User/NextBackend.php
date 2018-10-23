<?php

/**
 * ownCloud - user_cas
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp <kontakt@felixrupp.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\UserCAS\User;

use OCA\UserCAS\Exception\PhpCas\PhpUserCasLibraryNotFoundException;
use OCA\UserCAS\Service\AppService;
use OCA\UserCAS\Service\LoggingService;

use OC\User\Backend;
use OCP\IConfig;
use OCP\IUserBackend;
use OCP\User\Backend\ICheckPasswordBackend;
use OCP\User\Backend\IGetDisplayNameBackend;
use OCP\User\Backend\IGetHomeBackend;



/**
 * Class Backend
 *
 * @package OCA\UserCAS\User
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp <kontakt@felixrupp.com>
 *
 * @since 1.4.0
 */
class NextBackend extends Backend implements IUserBackend, ICheckPasswordBackend, IGetDisplayNameBackend, IGetHomeBackend, UserCasBackendInterface
{

    /**
     * @var \OCA\UserCAS\Service\LoggingService $loggingService
     */
    private $loggingService;

    /**
     * @var \OCA\UserCAS\Service\AppService $appService
     */
    private $appService;

    /**
     * @var IConfig
     */
    private $config;

    /**
     * @var string
     */
    private $appName;

    /**
     * Backend constructor.
     * @param $appName
     * @param IConfig $config
     * @param LoggingService $loggingService
     * @param AppService $appService
     */
    public function __construct($appName, IConfig $config, LoggingService $loggingService, AppService $appService)
    {

        //parent::__construct();
        $this->appName = $appName;
        $this->config = $config;
        $this->loggingService = $loggingService;
        $this->appService = $appService;
    }


    /**
     * Backend name to be shown in user management
     * @return string the name of the backend to be shown
     */
    public function getBackendName()
    {

        return "CAS";
    }


    /**
     * @param string $loginName
     * @param string $password
     * @return string|bool The users UID or false
     */
    public function checkPassword(string $loginName, string $password)
    {

        if (!$this->appService->isCasInitialized()) {

            try {

                $this->appService->init();
            } catch (PhpUserCasLibraryNotFoundException $e) {

                $this->loggingService->write(\OCP\Util::ERROR, 'Fatal error with code: ' . $e->getCode() . ' and message: ' . $e->getMessage());
            }
        }

        if($this->appService->isCasInitialized()) {
            if (\phpCAS::isInitialized()) {

                if ($loginName === FALSE) {

                    $this->loggingService->write(\OCP\Util::ERROR, 'phpCAS returned no user.');
                    #\OCP\Util::writeLog('cas', 'phpCAS returned no user.', \OCP\Util::ERROR);
                }

                if (\phpCAS::checkAuthentication()) {

                    $casUid = \phpCAS::getUser();

                    if ($casUid === $loginName) {

                        $this->loggingService->write(\OCP\Util::DEBUG, 'phpCAS user password has been checked.');
                        #\OCP\Util::writeLog('cas', 'phpCAS user password has been checked.', \OCP\Util::ERROR);

                        return $loginName;
                    }
                }
            } else {

                $this->loggingService->write(\OCP\Util::ERROR, 'phpCAS has not been initialized.');
                #\OCP\Util::writeLog('cas', 'phpCAS has not been initialized.', \OCP\Util::ERROR);
            }
        }

        return FALSE;
    }

    /**
     * @param string $uid
     * @return string
     */
    public function getDisplayName($uid) {

        if (!$this->appService->isCasInitialized()) {

            try {

                $this->appService->init();
            } catch (PhpUserCasLibraryNotFoundException $e) {

                $this->loggingService->write(\OCP\Util::ERROR, 'Fatal error with code: ' . $e->getCode() . ' and message: ' . $e->getMessage());
            }
        }


        if ($this->appService->isCasInitialized()) {

            if (\phpCAS::checkAuthentication()) {

                $casAttributes = \phpCAS::getAttributes();

                # Test if an attribute parser added a new dimension to our attributes array
                if (array_key_exists('attributes', $casAttributes)) {

                    $newAttributes = $casAttributes['attributes'];

                    unset($casAttributes['attributes']);

                    $casAttributes = array_merge($casAttributes, $newAttributes);
                }

                // DisplayName
                $displayNameMapping = $this->config->getAppValue($this->appName, 'cas_displayName_mapping');

                $displayNameMappingArray = explode("+", $displayNameMapping);

                $casDisplayName = '';

                foreach ($displayNameMappingArray as $displayNameMapping) {

                    if (array_key_exists($displayNameMapping, $casAttributes)) {

                        $casDisplayName .= $casAttributes[$displayNameMapping] . " ";
                    }
                }

                $casDisplayName = trim($casDisplayName);

                if ($casDisplayName === '' && array_key_exists('displayName', $casAttributes)) {

                    $casDisplayName = $casAttributes['displayName'];
                }

                return $casDisplayName;
            }
        }

        return $uid;
    }

    /**
     * get the user's home directory
     *
     * @param string $uid the username
     * @return string|false
     */
    public function getHome(string $uid) {
        if ($this->userExists($uid)) {
            return \OC::$server->getConfig()->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data') . '/' . $uid;
        }

        return false;
    }
}
