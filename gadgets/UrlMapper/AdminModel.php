<?php
require_once JAWS_PATH . 'gadgets/UrlMapper/Model.php';
/**
 * UrlMapper Core Gadget
 *
 * @category   GadgetModel
 * @package    UrlMapper
 * @author     Pablo Fischer <pablo@pablo.com.mx>
 * @author     Ali Fazelzadeh <afz@php.net>
 * @author     Mojtaba Ebrahimi <ebrahimi@zehneziba.ir>
 * @copyright  2006-2013 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/lesser.html
 */
class UrlMapper_AdminModel extends UrlMapper_Model
{
    /**
     * Returns only the map route of a certain map
     *
     * @access  public
     * @param   int     $id Map's ID
     * @return  string  Map route
     */
    function GetMap($id)
    {
        $mapsTable = Jaws_ORM::getInstance()->table('url_maps');
        $mapsTable->select('map', 'regexp', 'extension', 'vars_regexps', 'custom_map', 'custom_regexp', 'order');
        $result = $mapsTable->where('id', $id)->getRow();
        if (Jaws_Error::IsError($result)) {
            return $result;
        }

        return $result;
    }

    /**
     * Returns only the map route of given params
     *
     * @access  public
     * @param   string  $gadget Gadget name
     * @param   string  $action Action name
     * @param   string  $map    Action map
     * @return  mixed   Map route or Jaws_Error on error
     */
    function GetMapByParams($gadget, $action, $map)
    {
        $mapsTable = Jaws_ORM::getInstance()->table('url_maps');
        $mapsTable->select(
            'id:integer', 'gadget', 'map', 'regexp', 'extension', 'vars_regexps',
            'custom_map', 'custom_regexp');
        $mapsTable->where('gadget', $gadget)->and()->where('action', $action)->and()->where('action', $action);
        $result = $mapsTable->and()->where('map', $map)->getRow();
        if (Jaws_Error::IsError($result)) {
            return $result;
        }

        return $result;
    }

    /**
     * Returns maps of a certain gadget/action stored in DB
     *
     * @access  public
     * @param   string  $gadget   Gadget's name (FS name)
     * @param   string  $action   Gadget's action to use
     * @return  array   List of custom maps
     */
    function GetActionMaps($gadget, $action)
    {
        $mapsTable = Jaws_ORM::getInstance()->table('url_maps');
        $mapsTable->select('id:integer', 'map', 'extension');
        $result = $mapsTable->where('gadget', $gadget)->and()->where('action', $action)->orderBy('order ASC')->getAll();
        if (Jaws_Error::IsError($result)) {
            return array();
        }

        return $result;
    }

    /**
     * Returns mapped actions of a certain gadget
     *
     * @access  public
     * @param   string  $gadget  Gadget name
     * @return  array   List of actions
     */
    function GetGadgetActions($gadget)
    {
        $params = array();
        $params['gadget'] = $gadget;

        $sql = '
            SELECT [gadget], [action]
            FROM [[url_maps]]
            WHERE [gadget] = {gadget}
            GROUP BY [gadget], [action]
            ORDER BY [gadget], [action]';

        $result = $GLOBALS['db']->queryCol($sql, $params, null, 1);
        if (Jaws_Error::IsError($result)) {
            return array();
        }

        return $result;
    }

    /**
     * Adds all of gadget maps
     *
     * @access  public
     * @param   string  $gadget  Gadget name
     * @return  mixed   True on success, Jaws_Error otherwise
     */
    function AddGadgetMaps($gadget)
    {
        $file = JAWS_PATH . 'gadgets/' . $gadget . '/Map.php';
        if (file_exists($file)) {
            $maps = array();
            include_once $file;
            foreach ($maps as $order => $map) {
                $vars_regexps = array();
                $vars_regexps = isset($map[2])? $map[2] : $vars_regexps;
                if (preg_match_all('#{(\w+)}#si', $map[1], $matches)) {
                    foreach ($matches[1] as $m) {
                        if (!isset($vars_regexps[$m])) {
                            $vars_regexps[$m] = '\w+';
                        }
                    }
                }

                $res = $this->AddMap($gadget,
                                     $map[0],
                                     $map[1],
                                     isset($map[3])? $map[3] : '.',
                                     $vars_regexps,
                                     $order + 1);
                if (Jaws_Error::IsError($res)) {
                    return $res;
                }
            }

            unset($GLOBALS['maps']);
        }

        return true;
    }

    /**
     * Updates all of gadget maps
     *
     * @access  public
     * @param   string  $gadget  Gadget name
     * @return  mixed   True on success, Jaws_Error otherwise
     */
    function UpdateGadgetMaps($gadget)
    {
        $file = JAWS_PATH. "gadgets/$gadget/Map.php";
        $maps = array();
        if (@include($file)) {
            $now = $GLOBALS['db']->Date();
            foreach ($maps as $order => $map) {
                $eMap = $this->GetMapByParams($gadget, $map[0], $map[1]);
                if (Jaws_Error::IsError($eMap)) {
                    return $eMap;
                }

                $vars_regexps = array();
                $vars_regexps = isset($map[2])? $map[2] : $vars_regexps;
                if (preg_match_all('#{(\w+)}#si', $map[1], $matches)) {
                    foreach ($matches[1] as $m) {
                        if (!isset($vars_regexps[$m])) {
                            $vars_regexps[$m] = '\w+';
                        }
                    }
                }

                if (empty($eMap)) {
                    $res = $this->AddMap($gadget,
                                         $map[0],
                                         $map[1],
                                         isset($map[3])? $map[3] : '.',
                                         $vars_regexps,
                                         $order + 1,
                                         $now);
                    if (Jaws_Error::IsError($res)) {
                        return $res;
                    }
                } else {
                    $res = $this->UpdateMap($eMap['id'],
                                            $eMap['custom_map'],
                                            $vars_regexps,
                                            $order + 1,
                                            $map[1],
                                            isset($map[3])? $map[3] : '.',
                                            $now);
                    if (Jaws_Error::IsError($res)) {
                        return $res;
                    }
                }
            }

            // remove outdated maps
            $res = $this->DeleteGadgetMaps($gadget, $now);
            if (Jaws_Error::IsError($res)) {
                return $res;
            }
        }

        return true;
    }

    /**
     * Gets regular expression to detect map
     *
     * @access  public
     * @param   string  $map            Map to use (foo/bar/{param}/{param2}...)
     * @param   array   $vars_regexps   Array of regexp validators
     * @return  string  Regular expression
     */
    function GetMapRegExp($map, $vars_regexps)
    {
        $regexp = str_replace('/', '\/', $map);
        if (!empty($regexp)) {
            // generate regular expression for optional part
            while(preg_match('@\[([^\]\[]+)\]@', $regexp)) {
                $regexp = preg_replace('@\[([^\]\[]+)\]@','(?:$1|)', $regexp);
            }

            if (is_array($vars_regexps) && !empty($vars_regexps)) {
                foreach ($vars_regexps as $k => $v) {
                    $regexp = str_replace('{' . $k . '}', '(' . $v . ')', $regexp);
                }
            }

            // Adding delimiter to regular expression
            $regexp = str_replace('@', '\\@', $regexp);
            $regexp = '@^' . $regexp . '$@u';
        }

        return $regexp;
    }

    /**
     * Adds a new custom map
     *
     * @access  public
     * @param   string  $gadget         Gadget name (FS name)
     * @param   string  $action         Gadget action to use
     * @param   string  $map            Map to use (foo/bar/{param}/{param2}...)
     * @param   string  $extension      Extension of map
     * @param   array   $vars_regexps   Array of regexp validators
     * @param   int     $order          Sequence number of the map
     * @param   string  $time           Create/Update time
     * @return  mixed   True on success, Jaws_Error otherwise
     */
    function AddMap($gadget, $action, $map, $extension = '.', $vars_regexps = null, $order = 0, $time = '')
    {
        //for compatible with old versions
        $extension = ($extension == 'index.php')? '' : $extension;
        if (!empty($extension) && $extension{0} != '.') {
            $extension = '.'.$extension;
        }

        if ($this->MapExists($gadget, $action, $map, $extension)) {
            return true;
        }

        // map's regular expression
        $regexp = $this->GetMapRegExp($map, $vars_regexps);

        $params = array();
        $params['gadget']    = $gadget;
        $params['action']    = $action;
        $params['map']       = $map;
        $params['regexp']    = $regexp;
        $params['extension'] = $extension;
        $params['vars_regexps'] = serialize($vars_regexps);
        $params['order']      = $order;
        $params['createtime'] = empty($time)? $GLOBALS['db']->Date() : $time;
        $params['updatetime'] = $params['createtime'];

        $mapsTable = Jaws_ORM::getInstance()->table('url_maps');
        $result = $mapsTable->insert($params)->exec();
        if (Jaws_Error::IsError($result)) {
            return new Jaws_Error(_t('URLMAPPER_ERROR_MAP_NOT_ADDED'), _t('URLMAPPER_NAME'));
        }

        return true;
    }

    /**
     * Updates map route of the map
     *
     * @access  public
     * @param   int     $id             Map ID
     * @param   string  $custom_map     Custom_map to use (foo/bar/{param}/{param2}...)
     * @param   array   $vars_regexps   Array of regexp validators
     * @param   int     $order          Sequence number of the map
     * @param   string  $map            Map to use (foo/bar/{param}/{param2}...)
     * @param   string  $extension      Extension of default map
     * @param   string  $time           Create/Update time
     * @return  mixed   True on success, Jaws_Error otherwise
     */
    function UpdateMap($id, $custom_map, $vars_regexps, $order,
        $map = '', $map_extension = '.', $time = '')
    {
        if (!empty($map_extension) && $map_extension{0} != '.') {
            $map_extension = '.'.$map_extension;
        }

        if (is_null($vars_regexps)) {
            $result = $this->GetMap($id);
            if (Jaws_Error::IsError($result)) {
                return $result;
            }

            if (empty($result)) {
                return Jaws_Error::raiseError(_t('URLMAPPER_NO_MAPS'),  __FUNCTION__);
            }

            $vars_regexps = unserialize($result['vars_regexps']);
        }

        $params = array();
        if (!empty($map)) {
            $params['regexp'] = $this->GetMapRegExp($map, $vars_regexps);
            $params['extension'] = $map_extension;
        }

        $params['custom_map']    = $custom_map;
        $params['custom_regexp'] = $this->GetMapRegExp($custom_map, $vars_regexps);
        $params['vars_regexps']  = serialize($vars_regexps);
        $params['order']         = $order;
        $params['updatetime']    = empty($time)? $GLOBALS['db']->Date() : $time;

        $mapsTable = Jaws_ORM::getInstance()->table('url_maps');
        return $mapsTable->update($params)->where('id', (int)$id)->exec();
    }

    /**
     * Deletes all maps related to a gadget
     *
     * @access  public
     * @param   string  $gadget Gadget name
     * @param   string  $time   Time condition
     * @return  mixed   True on success, Jaws_Error otherwise
     */
    function DeleteGadgetMaps($gadget, $time = '')
    {
        $mapsTable = Jaws_ORM::getInstance()->table('url_maps');
        $mapsTable->delete()->where('gadget', $gadget);
        if (!empty($time)) {
            $mapsTable->and()->where('updatetime', $time, '<');
        }
        $result = $mapsTable->exec();
        if (Jaws_Error::IsError($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Adds a new alias
     *
     * @access  public
     * @param   string  $alias  Alias value
     * @param   string  $url    Real URL
     * @return  mixed   True on success, Jaws_Error otherwise
     */
    function AddAlias($alias, $url)
    {
        if (trim($alias) == '' || trim($url) == '') {
            $GLOBALS['app']->Session->PushLastResponse(_t('URLMAPPER_ERROR_ALIAS_NOT_ADDED'), RESPONSE_ERROR);
            return new Jaws_Error(_t('URLMAPPER_ERROR_ALIAS_NOT_ADDED'), _t('URLMAPPER_NAME'));
        }

        $data['real_url']    = $url;
        $data['alias_url']   = $alias;
        $data['alias_hash']  = md5($alias);

        if ($this->AliasExists($data['alias_hash'])) {
            $GLOBALS['app']->Session->PushLastResponse(_t('URLMAPPER_ERROR_ALIAS_ALREADY_EXISTS'), RESPONSE_ERROR);
            return new Jaws_Error(_t('URLMAPPER_ERROR_ALIAS_ALREADY_EXISTS'), _t('URLMAPPER_NAME'));
        }


        $aliasesTable = Jaws_ORM::getInstance()->table('url_aliases');
        $result = $aliasesTable->insert($data)->exec();

        if (Jaws_Error::IsError($result)) {
            $GLOBALS['app']->Session->PushLastResponse(_t('URLMAPPER_ERROR_ALIAS_NOT_ADDED'), RESPONSE_ERROR);
            return new Jaws_Error(_t('URLMAPPER_ERROR_ALIAS_NOT_ADDED'), _t('URLMAPPER_NAME'));
        }

        $GLOBALS['app']->Session->PushLastResponse(_t('URLMAPPER_ALIAS_ADDED'), RESPONSE_NOTICE);
        return true;
    }

    /**
     * Updates the alias
     *
     * @access  public
     * @param   int     $id     Alias ID
     * @param   string  $alias  Alias value
     * @param   string  $url    Real URL
     * @return  mixed   True on success, Jaws_Error otherwise
     */
    function UpdateAlias($id, $alias, $url)
    {
        if (trim($alias) == '' || trim($url) == '') {
            $GLOBALS['app']->Session->PushLastResponse(_t('URLMAPPER_ERROR_ALIAS_NOT_UPDATED'), RESPONSE_ERROR);
            return new Jaws_Error(_t('URLMAPPER_ERROR_ALIAS_NOT_UPDATED'), _t('URLMAPPER_NAME'));
        }

        if ($url{0} == '?') {
            $url = substr($url, 1);
        }

        $data['real_url']   = $url;
        $data['alias_url']  = $alias;
        $data['alias_hash'] = md5($alias);

        $aliasesTable = Jaws_ORM::getInstance()->table('url_aliases');
        $result = $aliasesTable->select('alias_hash')->where('id', $id)->getOne();
        if (Jaws_Error::IsError($result)) {
            $GLOBALS['app']->Session->PushLastResponse(_t('URLMAPPER_ERROR_ALIAS_NOT_UPDATED'), RESPONSE_ERROR);
            return new Jaws_Error(_t('URLMAPPER_ERROR_ALIAS_NOT_UPDATED'), _t('URLMAPPER_NAME'));
        }

        if ($result != $data['alias_hash']) {
            if ($this->AliasExists($data['alias_hash'])) {
                $GLOBALS['app']->Session->PushLastResponse(_t('URLMAPPER_ERROR_ALIAS_ALREADY_EXISTS'), RESPONSE_ERROR);
                return new Jaws_Error(_t('URLMAPPER_ERROR_ALIAS_ALREADY_EXISTS'), _t('URLMAPPER_NAME'));
            }
        }

        $aliasesTable = Jaws_ORM::getInstance()->table('url_aliases');
        $result = $aliasesTable->update($data)->where('id', $id)->exec();
        if (Jaws_Error::IsError($result)) {
            $GLOBALS['app']->Session->PushLastResponse(_t('URLMAPPER_ERROR_ALIAS_NOT_UPDATED'), RESPONSE_ERROR);
            return new Jaws_Error(_t('URLMAPPER_ERROR_ALIAS_NOT_UPDATED'), _t('URLMAPPER_NAME'));
        }

        $GLOBALS['app']->Session->PushLastResponse(_t('URLMAPPER_ALIAS_UPDATED'), RESPONSE_NOTICE);
        return true;
    }

    /**
     * Deletes the alias
     *
     * @access  public
     * @param   int     $id  Alias ID
     * @return  mixed   True on success, Jaws_Error otherwise
     */
    function DeleteAlias($id)
    {
        $aliasesTable = Jaws_ORM::getInstance()->table('url_aliases');
        $result = $aliasesTable->delete()->where('id', $id)->exec();
        if (Jaws_Error::IsError($result)) {
            $GLOBALS['app']->Session->PushLastResponse(_t('URLMAPPER_ERROR_ALIAS_NOT_DELETED'), RESPONSE_ERROR);
            return new Jaws_Error(_t('URLMAPPER_ERROR_ALIAS_NOT_DELETED'), _t('URLMAPPER_NAME'));
        }

        $GLOBALS['app']->Session->PushLastResponse(_t('URLMAPPER_ALIAS_DELETED'), RESPONSE_NOTICE);
        return true;
    }

    /**
     * Returns the error map
     *
     * @access  public
     * @param   int     $id Error Map ID
     * @return  mixed   Array of Error Map otherwise Jaws_Error
     */
    function GetErrorMap($id)
    {
        $params = array();
        $params['id'] = $id;

        $errorsTable = Jaws_ORM::getInstance()->table('url_errors');
        $result = $errorsTable->select('url', 'code', 'new_url', 'new_code')->where('id', $id)->getRow();
        if (Jaws_Error::IsError($result)) {
            return $result;
        }

        return $result;
    }

    /**
     * Adds a new error map
     *
     * @access  public
     * @param   string  $url        source url
     * @param   int     $code       code
     * @param   string  $new_url    destination url
     * @param   int     $new_code   new code
     * @return  mixed   True on success, Jaws_Error otherwise
     */
    function AddErrorMap($url, $code, $new_url = '', $new_code = 404)
    {
        $data['url'] = $url;
        $data['url_hash'] = md5($url);
        $data['code'] = $code;
        $data['new_url'] = $new_url;
        $data['new_code'] = $new_code;
        $data['hits'] = 1;
        $data['createtime'] = $GLOBALS['db']->Date();
        $data['updatetime'] = $GLOBALS['db']->Date();

        $errorsTable = Jaws_ORM::getInstance()->table('url_errors');
        $result = $errorsTable->insert($data)->exec();
        if (Jaws_Error::IsError($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Update the error map
     *
     * @access  public
     * @param   int     $id         error map id
     * @param   string  $url        source url
     * @param   string  $code       code
     * @param   string  $new_url    destination url
     * @param   string  $new_code   new code
     * @return  array   Response array (notice or error)
     */
    function UpdateErrorMap($id, $url, $code, $new_url, $new_code)
    {
        $errorsTable = Jaws_ORM::getInstance()->table('url_errors');
        $result = $errorsTable->select('url_hash')->where('id', $id)->getOne();


        $data['url'] = $url;
        $data['url_hash'] = md5($url);
        $data['code'] = $code;
        $data['new_url'] = $new_url;
        $data['new_code'] = $new_code;
        $data['updatetime'] = $GLOBALS['db']->Date();

        if (Jaws_Error::IsError($result)) {
            $GLOBALS['app']->Session->PushLastResponse(_t('URLMAPPER_ERROR_ERRORMAP_NOT_UPDATED'), RESPONSE_ERROR);
            return new Jaws_Error(_t('URLMAPPER_ERROR_ERRORMAP_NOT_UPDATED'), _t('URLMAPPER_NAME'));
        }

        if ($result != $data['url_hash']) {
            if ($this->ErrorMapExists($data['url_hash'])) {
                $GLOBALS['app']->Session->PushLastResponse(_t('URLMAPPER_ERROR_ERRORMAP_ALREADY_EXISTS'), RESPONSE_ERROR);
                return new Jaws_Error(_t('URLMAPPER_ERROR_ERRORMAP_ALREADY_EXISTS'), _t('URLMAPPER_NAME'));
            }
        }

        $errorsTable = Jaws_ORM::getInstance()->table('url_errors');
        $result = $errorsTable->update($data)->where('id', $id)->exec();
        if (Jaws_Error::IsError($result)) {
            $GLOBALS['app']->Session->PushLastResponse(_t('URLMAPPER_ERROR_ERRORMAP_NOT_UPDATED'), RESPONSE_ERROR);
            return new Jaws_Error(_t('URLMAPPER_ERROR_ERRORMAP_NOT_UPDATED'), _t('URLMAPPER_NAME'));
        }

        $GLOBALS['app']->Session->PushLastResponse(_t('URLMAPPER_ERRORMAP_UPDATED'), RESPONSE_NOTICE);
        return true;
    }

    /**
     * Deletes the error map
     *
     * @access  public
     * @param   int     $id     Error map ID
     * @return  array   Response array (notice or error)
     */
    function DeleteErrorMap($id)
    {
        $errorsTable = Jaws_ORM::getInstance()->table('url_errors');
        $result = $errorsTable->delete()->where('id', $id)->exec();
        if (Jaws_Error::IsError($result)) {
            $GLOBALS['app']->Session->PushLastResponse(_t('URLMAPPER_ERROR_ERRORMAP_NOT_DELETED'), RESPONSE_ERROR);
            return new Jaws_Error(_t('URLMAPPER_ERROR_ERRORMAP_NOT_DELETED'), _t('URLMAPPER_NAME'));
        }

        $GLOBALS['app']->Session->PushLastResponse(_t('URLMAPPER_ERRORMAP_DELETED'), RESPONSE_NOTICE);
        return true;
    }

    /**
     * Get list of error maps
     *
     * @access  public
     * @param   int     $limit
     * @param   int     $offset
     * @return  array   Grid data
     */
    function GetErrorMaps($limit, $offset)
    {
        $errorsTable = Jaws_ORM::getInstance()->table('url_errors');
        $errorsTable->select(
            'id:integer', 'url', 'code:integer', 'new_url', 'new_code', 'hits:integer',
            'createtime', 'updatetime');
        $result = $errorsTable->limit($limit, $offset)->orderBy('createtime')->getAll();
        if (Jaws_Error::IsError($result)) {
            return array();
        }

        return $result;
    }

    /**
     * Gets records count for error maps datagrid
     *
     * @access  public
     * @return  int   ErrorMaps row counts
     */
    function GetErrorMapsCount()
    {
        $errorsTable = Jaws_ORM::getInstance()->table('url_errors');
        $res = $errorsTable->select('count([id]):integer')->getOne();
        if (Jaws_Error::IsError($res)) {
            return new Jaws_Error($res->getMessage(), 'SQL');
        }

        return $res;
    }

    /**
     * Get HTTP error of reguested URL
     *
     * @access  public
     * @param   string  $reqURL
     * @param   int     $code
     * @return  mixed   Error data array on success, Jaws_Error otherwise
     */
    function GetHTTPError($reqURL, $code)
    {
        $errorsTable = Jaws_ORM::getInstance()->table('url_errors');
        $errorsTable->select('id:integer', 'new_url as url', 'new_code as code:integer');
        $errorMap = $errorsTable->where('url_hash', md5($reqURL))->getRow();
        if (Jaws_Error::IsError($errorMap) || empty($errorMap)) {
            if (empty($errorMap)) {
                $this->AddErrorMap($reqURL, $code);
            }
        } else {
            $errorsTable = Jaws_ORM::getInstance()->table('url_errors');
            $result = $errorsTable->update(
                array(
                    'hits' => $errorsTable->expr('hits + ?', 1)
                )
            )->where('id', $errorMap['id'])->exec();

            if (Jaws_Error::IsError($result)) {
                // do nothing
            }
        }

        return $errorMap;
    }

    /**
     * Updates settings
     *
     * @access  public
     * @param   bool    $enabled        Should maps be used?
     * @param   bool    $use_aliases    Should aliases be used?
     * @param   bool    $precedence     custom map precedence over default map
     * @param   string  $extension      Extension to use
     * @return  mixed   True on success, Jaws_Error otherwise
     */
    function SaveSettings($enabled, $use_aliases, $precedence, $extension)
    {
        $res = $this->gadget->registry->update('map_enabled', ($enabled === true)? 'true' : 'false');
        $res = $res && $this->gadget->registry->update('map_custom_precedence', ($precedence === true)?  'true' : 'false');
        $res = $res && $this->gadget->registry->update('map_extensions',  $extension);
        $res = $res && $this->gadget->registry->update('map_use_aliases', ($use_aliases === true)? 'true' : 'false');

        if ($res === false) {
            $GLOBALS['app']->Session->PushLastResponse(_t('URLMAPPER_ERROR_SETTINGS_NOT_SAVED'), RESPONSE_ERROR);
            return new Jaws_Error(_t('URLMAPPER_ERROR_SETTINGS_NOT_SAVED'), _t('URLMAPPER_NAME'));
        }

        $GLOBALS['app']->Session->PushLastResponse(_t('URLMAPPER_SETTINGS_SAVED'), RESPONSE_NOTICE);
        return true;
    }

}