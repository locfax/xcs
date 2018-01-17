<?php

namespace Xcs;

class Rbac {

    const ACL_EVERYONE = 'ACL_EVERYONE';
    const ACL_HAS_ROLE = 'ACL_HAS_ROLE';
    const ACL_NO_ROLE = 'ACL_NO_ROLE';
    const ACL_NULL = 'ACL_NULL';


    /**
     * @param $controllerName
     * @param null $actionName
     * @param string $auth
     * @return bool
     */
    public static function check($controllerName, $actionName = null, $auth = 'general') {
        $_controllerName = strtoupper($controllerName);
        $ACL = self::_getACL($_controllerName);

        //if controller offer empty AC, authtype 'general' then allow
        if ('general' == $auth) {
            if (is_null($ACL) || empty($ACL)) {
                return true;
            }
        } else {
            if (is_null($ACL) || empty($ACL)) {
                return false;
            }
        }

        // get user rolearray
        $roles = User::getRolesArray();

        // 1, check user's role whether allow to call controller
        if (!self::_check($roles, $ACL)) {
            return false;
        }

        // 2, check user's role whether allow to call action
        if (!is_null($actionName)) {
            $_actionName = strtoupper($actionName);
            if (isset($ACL['actions'][$_actionName])) {
                if (!self::_check($roles, $ACL['actions'][$_actionName])) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @param $_roles
     * @param $ACL
     * @return bool
     */
    private static function _check($_roles, $ACL) {
        $roles = array_map('strtoupper', $_roles);
        if ($ACL['allow'] == self::ACL_EVERYONE) {

            //if allow all role ,and deny is't set ,then allow
            if ($ACL['deny'] == self::ACL_NULL) {
                return true;
            }

            //if deny is AC_NO_ROLE ,then user has role, allow
            if ($ACL['deny'] == self::ACL_NO_ROLE) {
                if (empty($roles)) {
                    return false;
                }
                return true;
            }

            //if deny is ACL_HAS_ROLE ,then user's role is empty , allow
            if ($ACL['deny'] == self::ACL_HAS_ROLE) {
                if (empty($roles)) {
                    return true;
                }
                return false;
            }

            //if deny is ACL_EVERYONE ,then AC is false
            if ($ACL['deny'] == self::ACL_EVERYONE) {
                return false;
            }

            //if deny has't the role of user's roles , allow
            foreach ($roles as $role) {
                if (in_array($role, $ACL['deny'], true)) {
                    return false;
                }
            }
            return true;
        }

        do {
            //if allow request role , user's role has't the role , deny
            if ($ACL['allow'] == self::ACL_HAS_ROLE) {
                if (!empty($roles)) {
                    break;
                }
                return false;
            }
            //if allow request user's role is empty , but user's role is not empty , deny
            if ($ACL['allow'] == self::ACL_NO_ROLE) {
                if (empty($roles)) {
                    break;
                }
                return false;
            }
            if ($ACL['allow'] != self::ACL_NULL) {
                //if allow request the rolename , then check
                $passed = false;
                foreach ($roles as $role) {
                    if (in_array($role, $ACL['allow'], true)) {
                        $passed = true;
                        break;
                    }
                }
                if (!$passed) {
                    return false;
                }
            }
        } while (false);

        //if deny is't set , allow
        if ($ACL['deny'] == self::ACL_NULL) {
            return true;
        }
        //if deny is ACL_NO_ROEL, user'role is't empty , allow
        if ($ACL['deny'] == self::ACL_NO_ROLE) {
            if (empty($roles)) {
                return false;
            }
            return true;
        }
        //if deny is ACL_HAS_ROLE, user's role is empty ,allow
        if ($ACL['deny'] == self::ACL_HAS_ROLE) {
            if (empty($roles)) {
                return true;
            }
            return false;
        }
        //if deny is ACL_EVERYONE, then deny all
        if ($ACL['deny'] == self::ACL_EVERYONE) {
            return false;
        }
        //only deny hasn't the role of user's role ,allow
        foreach ($roles as $role) {
            if (in_array($role, $ACL['deny'], true)) {
                return false;
            }
        }
        return true;
    }


    /**
     * @param $controllerName
     * @return null
     */
    private static function _getACL($controllerName) {
        static $globalAcl = array();
        if (empty($globalAcl)) {
            $globalAcl = SysCache::loadcache('acl' . APPKEY);
            if (empty($globalAcl)) {
                return array();
            }
        }
        if (isset($globalAcl[$controllerName])) {
            return $globalAcl[$controllerName];
        }
        return null;
    }

}
