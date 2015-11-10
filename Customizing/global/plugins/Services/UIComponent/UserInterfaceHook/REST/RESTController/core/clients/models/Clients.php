<?php
/**
 * ILIAS REST Plugin for the ILIAS LMS
 *
 * Authors: D.Schaefer and T.Hufschmidt <(schaefer|hufschmidt)@hrz.uni-marburg.de>
 * Since 2014
 */
namespace RESTController\core\clients;

// This allows us to use shortcuts instead of full quantifier
use \RESTController\libs as Libs;


/**
 *
 * Constructor requires $sqlDB.
 */
class Clients extends Libs\RESTModel {
    const MSG_NO_CLIENT_OR_FIELD = 'No client with this api-key (api-id = %id%, field = %fieldName%) found.';
    const MSG_NO_CLIENT = 'No client with this api-key (api-id = %id%) found.';

    /**
     * Will add all permissions given by $perm_json to the ui_uihk_rest_perm table for the api_key with $id.
     *
     * @params $id - The unique id of the api_key those permissions are for (see. ui_uihk_rest_keys.id)
     * @params $perm_json - JSON Array of "pattern" (route), "verb" (HTTP header) pairs of all permission
     *
     * @return NULL
     */
    protected static function setPermissions($id, $perm)
    {
        // Remove old entries
        $sql = Libs\RESTLib::safeSQL('DELETE FROM ui_uihk_rest_perm WHERE api_id = %d', $id);
        self::getDB()->manipulate($sql);

        /*
         * *************************
         * RANT: (rot-13 for sanity)
         * *************************
         *  Fb, V'q yvxr gb vafreg zhyvgcyr ebjf jvgu bar dhrel hfvat whfg gur fvzcyr
         *  VAFREG VAGB <gnoyr> (<pby1, pby2, ...>) INYHRF (<iny1_1, iny1_2, ...>), (<iny2_1, iny2_2, ...>) , ...;
         *  Ohg thrff jung? Jubrire qrfvtarq vyQO qvqa'g nqq fhccbeg sbe guvf gb
         *  vgf vafreg()-zrgubq... oybbql uryy!
         *  Naq AB (!!!) genafnpgvbaf nera'g snfgre guna bar fvatyr vafreg.
         *  uggc://jjj.fjrnerzvcfhz.pbz/?cnentencuf=10&glcr=Ratyvfu&fgnegfjvguyberz=snyfr
         */

        if (is_array($perm) == false) {
            $perm = json_decode(utf8_encode($perm),true);
        }
        if (is_array($perm) && count($perm) > 0) {
            foreach ($perm as $value) {
                $perm_columns = array(
                    'api_id' => array('integer', $id),
                    'pattern' => array('text', $value['pattern']),
                    'verb' => array('text', $value['verb'])
                );
                self::getDB()->insert('ui_uihk_rest_perm', $perm_columns);
            }
        }
    }

    /**
     * Adds a route permission for a rest client specified by its api-key.
     *
     * @param $api_key
     * @param $route_pattern
     * @param $verb
     * @return int
     * @throws Exceptions\MissingApiKey
     */
    public static function addPermission($api_key, $route_pattern, $verb)
    {
        $route_pattern = rtrim($route_pattern, '/');
        // Sanity check, prevent double entries
        $api_key_id = self::getApiIdFromKey($api_key);
        $sql = Libs\RESTLib::safeSQL("SELECT * FROM ui_uihk_rest_perm WHERE api_id = %d AND pattern = %s AND verb = %s", $api_key_id, $route_pattern, $verb);
        $query = self::getDB()->query($sql);
        if (self::getDB()->numRows($query) > 0) {
            return -1;
        }

        // Add permission
        $perm_columns = array(
            'api_id' => array('integer', $api_key_id),
            'pattern' => array('text', $route_pattern),
            'verb' => array('text', $verb)
        );
        self::getDB()->insert('ui_uihk_rest_perm', $perm_columns);
        return intval(self::getDB()->getLastInsertId());
    }

    /**
     * Removes permission given by the unique permission id.
     *
     * @param $perm_id
     * @return mixed
     */
    public static function deletePermission($perm_id)
    {
        $sql = Libs\RESTLib::safeSQL('DELETE FROM ui_uihk_rest_perm WHERE id = %d', $perm_id);
        $numAffRows = self::getDB()->manipulate($sql);
        return $numAffRows;
    }

    /**
     * Returns a permission statement (i.e. route-pattern + verb) given a unique permission id.
     *
     * @param $perm_id
     * @return array
     */
    public static function getPermissionByPermId($perm_id)
    {
        $sql = Libs\RESTLib::safeSQL("SELECT * FROM ui_uihk_rest_perm WHERE id = %d", $perm_id);
        $query = self::getDB()->query($sql);
        if (self::getDB()->numRows($query) > 0) {
            $row = self::getDB()->fetchAssoc($query);
            return $row;
        }
        return array();
    }

    /**
     * Returns all permissions for a rest client specified by its api-key.
     *
     * @param $api_key
     * @return array
     * @throws Exceptions\MissingApiKey
     */
    public static function getPermissionsForApiKey($api_key)
    {
        $api_key_id = self::getApiIdFromKey($api_key);
        $sql = Libs\RESTLib::safeSQL("SELECT * FROM ui_uihk_rest_perm WHERE api_id = %d", $api_key_id);
        $query = self::getDB()->query($sql);
        $aPermissions = array();
        while($row = self::getDB()->fetchAssoc($query)) {
            $aPermissions[] = $row;
        }
        return $aPermissions;
    }

    /**
     * Given a api_key ID and an array of user id numbers, this function writes the mapping to the table 'ui_uihk_rest_key2user'.
     * Note: Old entries will be deleted.
     *
     * @param $api_key_id
     * @param $a_user_csv
     */
    protected static function fillApikeyUserMap($api_key_id, $a_user_csv = NULL)
    {
        // Remove old entries
        $sql = Libs\RESTLib::safeSQL('DELETE FROM ui_uihk_rest_key2user WHERE api_id = %d', $api_key_id);
        self::getDB()->manipulate($sql);

        // Add new entries
        if (is_array($a_user_csv) && count($a_user_csv) > 0)
            foreach ($a_user_csv as $user_id) {
                $a_columns = array(
                    'api_id' => array('integer', $api_key_id),
                    'user_id' => array('integer', $user_id)
                );
                self::getDB()->insert('ui_uihk_rest_key2user', $a_columns);
            }
    }

    /**
     * Given a api_key ID and an array of ip address strings, this function writes the mapping to the table 'ui_uihk_rest_key2ip'.
     * Note: Old entries will be deleted.
     *
     * @param $api_key_id
     * @param $a_ip_csv
     */
    protected static function fillApikeyIpMap($api_key_id, $a_ip_csv = NULL)
    {
        // Remove old entries
        $sql = Libs\RESTLib::safeSQL('DELETE FROM ui_uihk_rest_key2ip WHERE api_id = %d', $api_key_id);
        self::getDB()->manipulate($sql);

        // Add new entries
        if (is_array($a_ip_csv) && count($a_ip_csv) > 0)
            foreach ($a_ip_csv as $ip_addr_str) {
                $a_columns = array(
                    'api_id' => array('integer', $api_key_id),
                    'ip' => array('text', trim($ip_addr_str))
                );
                self::getDB()->insert('ui_uihk_rest_key2ip', $a_columns);
            }
    }


    /**
     * Checks if a grant type is enabled for the specified API KEY.
     *
     * @param $api_key
     * @param $grant_type
     * @return bool
     */
    protected static function is_oauth2_grant_type_enabled($api_key, $grant_type)
    {
        // Check if given grant_type is enabled
        // TODO: remove sprintf after safeSQL is fixed
        $sql = Libs\RESTLib::safeSQL("SELECT $grant_type FROM ui_uihk_rest_keys WHERE api_key = %s", $api_key);
        $query = self::getDB()->query($sql);
        if (self::getDB()->numRows($query) > 0) {
            $row = self::getDB()->fetchAssoc($query);
            if ($row[$grant_type] == 1)
                return true;
        }
        return false;
    }


    /**
     * Returns all REST clients available in the system.
     *
     * @return bool
     */
    public static  function getClients()
    {
        // Will store result
        $res = array();

        // Query all api-keys
        $sqlKeys = 'SELECT * FROM ui_uihk_rest_keys ORDER BY id';
        $queryKeys = self::getDB()->query($sqlKeys);
        while($rowKeys = self::getDB()->fetchAssoc($queryKeys)) {
            $id = intval($rowKeys['id']);

            // Will store permission
            $perm = array();

            // Query api-key permissions
            $sqlPerm = Libs\RESTLib::safeSQL('SELECT pattern, verb FROM ui_uihk_rest_perm WHERE api_id = %d', $id);
            //\RESTController\RESTController::getInstance()->log->debug($id);
            $queryPerm = self::getDB()->query($sqlPerm);
            while($rowPerm = self::getDB()->fetchAssoc($queryPerm))
                $perm[] = $rowPerm;
            $rowKeys['permissions'] = $perm;

            // Will store allowd users
            $csv = array();

            // fetch allowed users for api-key
            $sqlCSV = Libs\RESTLib::safeSQL('SELECT user_id FROM ui_uihk_rest_key2user WHERE api_id = %d', $id);
            $queryCSV = self::getDB()->query($sqlCSV);
            while($rowCSV = self::getDB()->fetchAssoc($queryCSV)) {
                $csv[] = $rowCSV['user_id'];
            }
            $csv_string = implode(',',$csv);
            $rowKeys['access_user_csv'] = $csv_string;

            $csv = array();
            // fetch allowed users for api-key
            $sqlCSV = Libs\RESTLib::safeSQL('SELECT ip FROM ui_uihk_rest_key2ip WHERE api_id = %d', $id);
            $queryCSV = self::getDB()->query($sqlCSV);
            while($rowCSV = self::getDB()->fetchAssoc($queryCSV)) {
                $csv[] = $rowCSV['ip'];
            }
            $csv_string = implode(',',$csv);
            $rowKeys['access_ip_csv'] = $csv_string;

            // Add entry to result
            $res[] = $rowKeys;
        }

        // Return result
        return $res;
    }


    /**
     * Creates a new REST client entry
     */
    public static function createClient(
        $api_key,
        $api_secret,
        $oauth2_redirection_uri,
        $oauth2_consent_message,
        $oauth2_consent_message_active,
        $permissions,
        $oauth2_gt_client_active,
        $oauth2_gt_authcode_active,
        $oauth2_gt_implicit_active,
        $oauth2_gt_resourceowner_active,
        $oauth2_user_restriction_active,
        $oauth2_gt_client_user,
        $access_user_csv,
        $ip_restriction_active,
        $description,
        $access_ip_csv,
        $oauth2_authcode_refresh_active,
        $oauth2_resource_refresh_active
    )
    {
        // Add client with given settings
        $a_columns = array(
            'api_key' => array('text', $api_key),
            'api_secret' => array('text', $api_secret),
            'oauth2_redirection_uri' => array('text', $oauth2_redirection_uri),
            'oauth2_consent_message' => array('text', $oauth2_consent_message),
            'oauth2_gt_client_active' => array('integer', $oauth2_gt_client_active),
            'oauth2_gt_authcode_active' => array('integer', $oauth2_gt_authcode_active),
            'oauth2_gt_implicit_active' => array('integer', $oauth2_gt_implicit_active),
            'oauth2_gt_resourceowner_active' => array('integer', $oauth2_gt_resourceowner_active),
            'oauth2_gt_client_user' => array('integer', $oauth2_gt_client_user),
            'oauth2_user_restriction_active' => array('integer', $oauth2_user_restriction_active),
            'oauth2_consent_message_active' => array('integer', $oauth2_consent_message_active),
            'oauth2_authcode_refresh_active' => array('integer', $oauth2_authcode_refresh_active),
            'oauth2_resource_refresh_active' => array('integer', $oauth2_resource_refresh_active),
            'ip_restriction_active' => array('integer', $ip_restriction_active),
            'description' => array('text', $description)
        );
        self::getDB()->insert('ui_uihk_rest_keys', $a_columns);
        $insertId = intval(self::getDB()->getLastInsertId());

        // Add permissions to separate table
        self::setPermissions($insertId, $permissions);

        // Updated list of allowed users
        if (is_string($access_user_csv) && strlen($access_user_csv) > 0) {
            $csvArray = explode(',', $access_user_csv);
            self::fillApikeyUserMap($insertId, $csvArray);
        } else {
            self::fillApikeyUserMap($insertId);
        }

        // Updated list of allowed users
        if (is_string($access_ip_csv) && strlen($access_ip_csv) > 0) {
            $csvArray = explode(',', $access_ip_csv);
            self::fillApikeyIpMap($insertId, $csvArray);
        } else {
            self::fillApikeyIpMap($insertId);
        }

        // Return new api_id
        return $insertId;
    }


    /**
     * Updates an item
     *
     * @param $id - API-Key-ID
     * @param $fieldname
     * @param $newval
     * @return mixed
     * @throws Exceptions\UpdateFailed
     */
    public static function updateClient($id, $fieldname, $newval)  {

        if (strtolower($fieldname) == 'permissions') {
            self::setPermissions($id, $newval);
        }

        // Update allowed users? (Separate table)
        else if (strtolower($fieldname) == 'access_user_csv') {
            // Updated list of allowed users
            if (is_string($newval) && strlen($newval) > 0) {
                $csvArray = explode(',', $newval);
                self::fillApikeyUserMap($id, $csvArray);
            } else {
                self::fillApikeyUserMap($id);
            }
        }

        // Update allowed users? (Separate table)
        else if (strtolower($fieldname) == 'access_ip_csv') {
            // Updated list of allowed users
            if (is_string($newval) && strlen($newval) > 0) {
                $csvArray = explode(',', $newval);
                self::fillApikeyIpMap($id, $csvArray);
            } else {
                self::fillApikeyIpMap($id);
            }
        }
        // Update any other field...
        // Note: for now, we take it for granted that this update is prone to sql-injections. Only admins should be able to use this method / corresponding route.
        else {
            if (is_numeric($newval)) {
                $sql = Libs\RESTLib::safeSQL("UPDATE ui_uihk_rest_keys SET $fieldname = %d WHERE id = %d", $newval, $id);
            } else {
                $sql = Libs\RESTLib::safeSQL("UPDATE ui_uihk_rest_keys SET $fieldname = %s WHERE id = %d", $newval, $id);
            }
            $numAffRows = self::getDB()->manipulate($sql);

            if ($numAffRows === false)
                throw new Exceptions\UpdateFailed(self::MSG_NO_CLIENT_OR_FIELD, $id, $fieldname);
        }
    }


    /**
     * Deletes a REST client entry.
     *
     * @param $id
     * @return mixed
     * @throws Exceptions\DeleteFailed
     */
    public static  function deleteClient($id)
    {
        // Delete acutal client
        $sql = Libs\RESTLib::safeSQL('DELETE FROM ui_uihk_rest_keys WHERE id = %d', $id);
        $numAffRows = self::getDB()->manipulate($sql);

        // Delete all his permissions
        $sql = Libs\RESTLib::safeSQL('DELETE FROM ui_uihk_rest_perm WHERE api_id = %d', $id);
        self::getDB()->manipulate($sql);

        // Delete list of allowed users
        $sql = Libs\RESTLib::safeSQL('DELETE FROM ui_uihk_rest_key2user WHERE api_id = %d', $id);
        self::getDB()->manipulate($sql);

        // Delete oauth tokens
        $sql = Libs\RESTLib::safeSQL('DELETE FROM ui_uihk_rest_oauth2 WHERE api_id = %d', $id);
        self::getDB()->manipulate($sql);

        if ($numAffRows === false)
            throw new Exceptions\DeleteFailed(self::MSG_NO_CLIENT, $id);
    }


    /**
     * Returns the ILIAS user id associated with the grant type: client credentials.
     *
     * @param $api_key
     * @return mixed
     */
    public static  function getClientCredentialsUser($api_key)
    {
        // Fetch client-credentials for api-key
        $sql = Libs\RESTLib::safeSQL('SELECT id, oauth2_gt_client_user FROM ui_uihk_rest_keys WHERE api_key = %s', $api_key);
        $query = self::getDB()->query($sql);
        $row = self::getDB()->fetchAssoc($query);
        return $row['oauth2_gt_client_user'];
    }


    /**
     * Retrieves an array of ILIAS user ids that are allowed to use the grant types:
     * authcode, implicit and resource owner credentials
     *
     * @param $api_key
     * @return array
     */
    public static function getAllowedUsersForApiKey($api_key)
    {
        // Fetch api_id for api-key
        $sql = Libs\RESTLib::safeSQL('SELECT id, oauth2_user_restriction_active FROM ui_uihk_rest_keys WHERE api_key = %s', $api_key);
        $query = self::getDB()->query($sql);
        $row = self::getDB()->fetchAssoc($query);
        $id = intval($row['id']);

        // Check restrictions
        if ($row['oauth2_user_restriction_active'] == 1) {
            // Stores allowed users
            $a_user_ids = array();

            // Fetch allowed users
            $sql2 = Libs\RESTLib::safeSQL('SELECT user_id FROM ui_uihk_rest_key2user WHERE api_id = %s', $id);
            $query2 = self::getDB()->query($sql2);
            while($row2 = self::getDB()->fetchAssoc($query2))
                $a_user_ids[] = (int)$row2['user_id'];

            // Return allowed users
            return $a_user_ids;
        }

        // No restriction in place
        return array(-1);
    }


    /**
     * Checks if the resource owner grant type is enabled for the specified API KEY.
     *
     * @param $api_key
     * @return bool
     */
    public static function is_oauth2_gt_resourceowner_enabled($api_key)
    {
        return self::is_oauth2_grant_type_enabled($api_key, 'oauth2_gt_resourceowner_active');
    }


    /**
     * Checks if the implicit grant type is enabled for the specified API KEY.
     *
     * @param $api_key
     * @return bool
     */
    public static function is_oauth2_gt_implicit_enabled($api_key)
    {
        return self::is_oauth2_grant_type_enabled($api_key, 'oauth2_gt_implicit_active');
    }


    /**
     * Checks if the authcode grant type is enabled for the specified API KEY.
     *
     * @param $api_key
     * @return bool
     */
    public function is_oauth2_gt_authcode_enabled($api_key)
    {
        return self::is_oauth2_grant_type_enabled($api_key, 'oauth2_gt_authcode_active');
    }


    /**
     * Checks if the client credentials grant type is enabled for the specified API KEY.
     *
     * @param $api_key
     * @return bool
     */
    public static function is_oauth2_gt_clientcredentials_enabled($api_key)
    {
        return self::is_oauth2_grant_type_enabled($api_key, 'oauth2_gt_client_active');
    }


    /**
     * Checks if the oauth2 consent message is enabled, i.e. an additional page for the grant types
     * "authorization code" and "implicit grant".
     *
     * @param $api_key
     * @return bool
     */
    public static function is_oauth2_consent_message_enabled($api_key)
    {
        // Query if client with this aki-key has an oauth2 consent-message set
        $sql = Libs\RESTLib::safeSQL('SELECT oauth2_consent_message_active FROM ui_uihk_rest_keys WHERE api_key = %s', $api_key);
        $query = self::getDB()->query($sql);
        if (self::getDB()->numRows($query) > 0) {
            $row = self::getDB()->fetchAssoc($query);
            if ($row['oauth2_consent_message_active'] == 1)
                return true;
        }
        return false;
    }


    /**
     * Returns the OAuth2 Consent Message
     *
     * @param $api_key
     * @return string
     */
    public static function getOAuth2ConsentMessage($api_key)
    {
        // Fetch ouath2 consent-message for client with given api-key
        $sql = Libs\RESTLib::safeSQL('SELECT oauth2_consent_message FROM ui_uihk_rest_keys WHERE api_key = %s', $api_key);
        $query = self::getDB()->query($sql);
        if (self::getDB()->numRows($query) > 0) {
            $row = self::getDB()->fetchAssoc($query);
            return $row['oauth2_consent_message'];
        }
        return "";
    }


    /**
     * Checks if the refresh token support for the grant type authorization code is enabled or not.
     *
     * @param $api_key
     * @return bool
     */
    public static function is_authcode_refreshtoken_enabled($api_key)
    {
        // Query if client with this aki-key has oauth2 refresh-tokens enabled (for authentification-code)
        $sql = Libs\RESTLib::safeSQL('SELECT oauth2_authcode_refresh_active FROM ui_uihk_rest_keys WHERE api_key = %s', $api_key);
        $query = self::getDB()->query($sql);
        if (self::getDB()->numRows($query) > 0) {
            $row = self::getDB()->fetchAssoc($query);
            if ($row['oauth2_authcode_refresh_active'] == 1)
                return true;
        }
        return false;
    }


    /**
     * Checks if the refresh token support for the grant type resource owner grant is enabled or not.
     *
     * @param $api_key
     * @return bool
     */
    public static function is_resourceowner_refreshtoken_enabled($api_key)
    {
        // Query if client with this aki-key has oauth2 refresh-tokens enabled (for resource-owner)
        $sql = Libs\RESTLib::safeSQL('SELECT oauth2_resource_refresh_active FROM ui_uihk_rest_keys WHERE api_key = %s', $api_key);
        $query = self::getDB()->query($sql);
        if (self::getDB()->numRows($query) > 0) {
            $row = self::getDB()->fetchAssoc($query);
            if ($row['oauth2_resource_refresh_active'] == 1)
                return true;
        }
        return false;
    }


    /**
     * Returns the id given an api_key string.
     * @param $api_key
     * @return int
     * @throws Exceptions\MissingApiKey
     */
    public static function getApiIdFromKey($api_key)
    {
        $sql = Libs\RESTLib::safeSQL('SELECT id FROM ui_uihk_rest_keys WHERE api_key = %s', $api_key);
        $query = self::getDB()->query($sql);

        if ($query != null && $row = self::getDB()->fetchAssoc($query))
            return intval($row['id']);
        else
            throw new Exceptions\MissingApiKey(sprintf(self::MSG_API_KEY, $api_key));
    }


    /**
     * Returns a api_key string given an internal api id.
     * @param $api_id
     * @return string
     * @throws Exceptions\MissingApiKey
     */
    public static function getApiKeyFromId($api_id)
    {
        $sql = Libs\RESTLib::safeSQL('SELECT api_key FROM ui_uihk_rest_keys WHERE id = %d', $api_id);
        $query = self::getDB()->query($sql);

        if ($query != null && $row = self::getDB()->fetchAssoc($query))
            return intval($row['api_key']);
        else
            throw new Exceptions\MissingApiKey(sprintf(self::MSG_API_ID, $api_id));
    }
}
