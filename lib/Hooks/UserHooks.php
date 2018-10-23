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

namespace OCA\UserCAS\Hooks;

use OCA\UserCAS\Exception\PhpCas\PhpUserCasLibraryNotFoundException;
use \OCP\IUserManager;
use \OCP\IUserSession;
use \OCP\IConfig;

use OCA\UserCAS\Service\LoggingService;
use OCA\UserCAS\Service\UserService;
use OCA\UserCAS\Service\AppService;

/**
 * Class UserCAS_Hooks
 *
 * @package OCA\UserCAS\Hooks
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp <kontakt@felixrupp.com>
 *
 * @since 1.4.0
 */
class UserHooks
{

    /**
     * @var string
     */
    private $appName;

    /**
     * @var \OCP\IUserManager $userManager
     */
    private $userManager;

    /**
     * @var \OCP\IUserSession $userSession
     */
    private $userSession;

    /**
     * @var \OCP\IConfig
     */
    private $config;

    /**
     * @var \OCA\UserCAS\Service\UserService $userService
     */
    private $userService;

    /**
     * @var \OCA\UserCAS\Service\AppService $appService
     */
    private $appService;

    /**
     * @var \OCA\UserCAS\Service\LoggingService
     */
    private $loggingService;


    /**
     * UserHooks constructor.
     *
     * @param string $appName
     * @param \OCP\IUserManager $userManager
     * @param \OCP\IUserSession $userSession
     * @param \OCP\IConfig $config
     * @param \OCA\UserCAS\Service\UserService $userService
     * @param \OCA\UserCAS\Service\AppService $appService
     * @param \OCA\UserCAS\Service\LoggingService $loggingService
     */
    public function __construct($appName, IUserManager $userManager, IUserSession $userSession, IConfig $config, UserService $userService, AppService $appService, LoggingService $loggingService)
    {
        $this->appName = $appName;
        $this->userManager = $userManager;
        $this->userSession = $userSession;
        $this->config = $config;
        $this->userService = $userService;
        $this->appService = $appService;
        $this->loggingService = $loggingService;
    }

    /**
     * Register method.
     */
    public function register()
    {
        $this->userSession->listen('\OC\User', 'preLogin', array($this, 'preLogin'));
        $this->userSession->listen('\OC\User', 'postLogin', array($this, 'postLogin'));
        $this->userSession->listen('\OC\User', 'postLogout', array($this, 'postLogout'));
    }


    /**
     * postLogin method to update user data.
     *
     * @param $uid
     * @param $password
     * @return bool
     * @throws \Exception
     */
    public function preLogin($uid, $password)
    {

        if (!$this->appService->isCasInitialized()) {

            try {

                $this->appService->init();

                if ($uid instanceof \OCP\IUser) {

                    $uid = $uid->getUID();
                }

                if (\phpCAS::isAuthenticated() && !$this->userSession->isLoggedIn()) {

                    if (boolval($this->config->getAppValue($this->appName, 'cas_autocreate'))) {

                        $this->loggingService->write(\OCP\Util::DEBUG, 'phpCas pre login hook triggered. User: ' . $uid);

                        $casUid = \phpCAS::getUser();

                        if ($casUid === $uid) {

                            $oldUserObject = $this->userManager->get($uid);

                            // Autocreate user if needed or create a new account in CAS Backend
                            if (is_null($oldUserObject)) {

                                // create users if they do not exist
                                if (preg_match('/[^a-zA-Z0-9 _\.@\-]/', $uid)) {

                                    $this->loggingService->write(\OCP\Util::DEBUG, 'Invalid username "' . $uid . '", allowed chars "a-zA-Z0-9" and "_.@-" ');

                                    return FALSE;
                                } else {

                                    $this->loggingService->write(\OCP\Util::DEBUG, 'phpCAS creating a new user with UID: ' . $uid);

                                    /** @var bool|\OCP\IUser the created user or false $uid */
                                    $oldUserObject = $this->userService->create($uid);

                                    if ($oldUserObject instanceof \OCP\IUser) {

                                        $this->loggingService->write(\OCP\Util::DEBUG, 'phpCAS created new user with UID: ' . $uid);
                                    }
                                }
                            } else {

                                $this->loggingService->write(\OCP\Util::DEBUG, 'phpCAS no new user has been created.');
                            }

                            // Don’t do that for Cextcloud
                            /** @var \OCP\Defaults $defaults */
                            $defaults = new \OCP\Defaults();

                            if (strpos(strtolower($defaults->getName()), 'next') === FALSE) {

                                if (!is_null($oldUserObject) && ($oldUserObject->getBackendClassName() === "OC\\User\\Database" || $oldUserObject->getBackendClassName() === "Database")) {

                                    $query = \OC_DB::prepare('UPDATE `*PREFIX*accounts` SET `backend` = ? WHERE LOWER(`user_id`) = LOWER(?)');
                                    $result = $query->execute([get_class($this->userService->getBackend()), $uid]);

                                    $this->loggingService->write(\OCP\Util::DEBUG, 'phpCAS user existing in database backend, move to CAS-Backend with result: ' . $result);
                                }
                            }
                        }
                    }
                } else {

                    $this->loggingService->write(\OCP\Util::DEBUG, 'phpCas pre login hook NOT triggered. User: ' . $uid);
                }
            } catch (PhpUserCasLibraryNotFoundException $e) {

                $this->loggingService->write(\OCP\Util::FATAL, 'Fatal error with code: ' . $e->getCode() . ' and message: ' . $e->getMessage());

                return FALSE;
            }
        };

        return TRUE;
    }


    /**
     * postLogin method to update user data.
     *
     * @param \OCP\IUser $user
     * @return bool
     */
    public function postLogin(\OCP\IUser $user, $password)
    {

        if (!$this->appService->isCasInitialized()) {

            try {

                $this->appService->init();

                $uid = $user->getUID();

                if (\phpCAS::isAuthenticated() && $this->userSession->isLoggedIn()) {

                    if (boolval($this->config->getAppValue($this->appName, 'cas_update_user_data'))) {

                        $this->loggingService->write(\OCP\Util::DEBUG, 'phpCas post login hook triggered. User: ' . $uid);

                        // $cas_attributes may vary in name, therefore attributes are fetched to $attributes

                        $casUid = \phpCAS::getUser();

                        if ($casUid === $uid) {

                            $casAttributes = \phpCAS::getAttributes();

                            # Test if an attribute parser added a new dimension to our attributes array
                            if (array_key_exists('attributes', $casAttributes)) {

                                $newAttributes = $casAttributes['attributes'];

                                unset($casAttributes['attributes']);

                                $casAttributes = array_merge($casAttributes, $newAttributes);
                            }

                            $casAttributesString = '';
                            foreach ($casAttributes as $key => $attribute) {

                                $attributeString = $this->convertArrayAttributeValuesForDebug($attribute);

                                $casAttributesString .= $key . ': ' . $attributeString . '; ';
                            }

                            // parameters
                            $attributes = array();
                            $this->loggingService->write(\OCP\Util::DEBUG, 'Attributes for the user: ' . $uid . ' => ' . $casAttributesString);


                            // DisplayName
                            $displayNameMapping = $this->config->getAppValue($this->appName, 'cas_displayName_mapping');

                            $displayNameMappingArray = explode("+", $displayNameMapping);

                            $attributes['cas_name'] = '';

                            foreach ($displayNameMappingArray as $displayNameMapping) {

                                if (array_key_exists($displayNameMapping, $casAttributes)) {

                                    $attributes['cas_name'] .= $casAttributes[$displayNameMapping] . " ";
                                }
                            }

                            $attributes['cas_name'] = trim($attributes['cas_name']);

                            if ($attributes['cas_name'] === '' && array_key_exists('displayName', $casAttributes)) {

                                $attributes['cas_name'] = $casAttributes['displayName'];
                            }


                            // E-Mail
                            $mailMapping = $this->config->getAppValue($this->appName, 'cas_email_mapping');
                            if (array_key_exists($mailMapping, $casAttributes)) {

                                $attributes['cas_email'] = $casAttributes[$mailMapping];
                            } else if (array_key_exists('mail', $casAttributes)) {

                                $attributes['cas_email'] = $casAttributes['mail'];
                            }


                            // Group handling
                            $groupMapping = $this->config->getAppValue($this->appName, 'cas_group_mapping');
                            $defaultGroup = $this->config->getAppValue($this->appName, 'cas_default_group');
                            # Test for mapped attribute from settings
                            if (array_key_exists($groupMapping, $casAttributes)) {

                                $attributes['cas_groups'] = $casAttributes[$groupMapping];
                            } # Test for standard 'groups' attribute
                            else if (array_key_exists('groups', $casAttributes)) {

                                $attributes['cas_groups'] = $casAttributes['groups'];
                            } else if (is_string($defaultGroup) && strlen($defaultGroup) > 0) {

                                $attributes['cas_groups'] = array($defaultGroup);

                                $this->loggingService->write(\OCP\Util::DEBUG, 'Using default group "' . $defaultGroup . '" for the user: ' . $uid);
                            }

                            // Group Quota handling
                            $groupQuotas = $this->config->getAppValue($this->appName, 'cas_access_group_quotas');
                            $groupQuotas = explode(",", $groupQuotas);

                            foreach ($groupQuotas as $groupQuota) {

                                $groupQuota = explode(":", $groupQuota);

                                if (is_array($groupQuota) && count($groupQuota) === 2) {

                                    $attributes['cas_group_quota'][$groupQuota[0]] = $groupQuota[1];
                                }
                            }

                            // Try to update user attributes
                            $this->userService->updateUser($user, $attributes);
                        }

                        $this->loggingService->write(\OCP\Util::DEBUG, 'phpCas post login hook finished.');
                    }
                } else {

                    $this->loggingService->write(\OCP\Util::DEBUG, 'phpCas post login hook NOT triggered. User: ' . $uid);
                }
            } catch (PhpUserCasLibraryNotFoundException $e) {

                $this->loggingService->write(\OCP\Util::FATAL, 'Fatal error with code: ' . $e->getCode() . ' and message: ' . $e->getMessage());
            }
        };

        return TRUE;
    }

    /**
     * Logout hook method.
     *
     * @return boolean
     */
    public function postLogout()
    {

        if (!$this->appService->isCasInitialized()) {

            try {

                $this->appService->init();

                $this->loggingService->write(\OCP\Util::DEBUG, 'Logout hook triggered.');

                if (!boolval($this->config->getAppValue($this->appName, 'cas_disable_logout'))) {

                    $this->loggingService->write(\OCP\Util::DEBUG, 'phpCAS logging out.');

                    # Reset cookie
                    setcookie("user_cas_redirect_url", '/', null, '/');

                    \phpCAS::logout(array("url" => $this->appService->getAbsoluteURL('/')));

                } else {

                    $this->loggingService->write(\OCP\Util::DEBUG, 'phpCAS not logging out, because CAS logout was disabled.');
                }
            } catch (PhpUserCasLibraryNotFoundException $e) {

                $this->loggingService->write(\OCP\Util::FATAL, 'Fatal error with code: ' . $e->getCode() . ' and message: ' . $e->getMessage());
            }
        };

        return TRUE;
    }


    /**
     * Convert CAS Attribute values for debug reasons
     *
     * @param $attributes
     * @return string
     */
    private function convertArrayAttributeValuesForDebug($attributes)
    {

        if (is_array($attributes)) {
            $stringValue = '';

            foreach ($attributes as $attribute) {

                if (is_array($attribute)) {

                    $stringValue .= $this->convertArrayAttributeValuesForDebug($attribute);
                } else {

                    $stringValue .= $attribute . ", ";
                }
            }

            return $stringValue;
        }

        return $attributes;
    }
}