<?php
/**
 * @package    Com_Api
 * @copyright  Copyright (C) 2009-2017 Techjoomla, Tekdi Technologies Pvt. Ltd. All rights reserved.
 * @license    GNU GPLv2 <http://www.gnu.org/licenses/old-licenses/gpl-2.0.html>
 * @link       http://techjoomla.com
 * Work derived from the original RESTful API by Techjoomla (https://github.com/techjoomla/Joomla-REST-API)
 * and the com_api extension by Brian Edgerton (http://www.edgewebworks.com)
 */

defined('_JEXEC') or die;
jimport('joomla.application.component.model');

/**
 * Class for API authetication
 *
 * @since  1.0
 */
abstract class ApiAuthentication extends JObject
{
	protected $auth_method     = null;

	protected $domain_checking = null;

	public static $auth_errors = array();

	/**
	 * Constructor
	 *
	 * @param   object  $params  config
	 *
	 * @since 1.0
	 */
	public function __construct($params)
	{
		parent::__construct();

		$this->set('auth_method', self::getAuthMethod());
		$this->set('domain_checking', $params->get('domain_checking', 1));
	}

	/**
	 * Authenticate
	 *
	 * @return  void
	 *
	 * @since 1.0
	 */
	abstract public function authenticate();

	/**
	 * Authenticate Request
	 *
	 * @return  mixed
	 *
	 * @since 1.0
	 */
	public static function authenticateRequest()
	{
		$params       = JComponentHelper::getParams('com_api');
		$app          = JFactory::getApplication();

		$className    = 'APIAuthentication' . ucwords(self::getAuthMethod());

		$auth_handler = new $className($params);
		$user_id      = $auth_handler->authenticate();

		if ($user_id === false)
		{
			self::setAuthError($auth_handler->getError());

			return false;
		}
		else
		{
			$user = JFactory::getUser($user_id);

			if (!$user->id)
			{
				self::setAuthError(JText::_("COM_API_USER_NOT_FOUND"));

				return false;
			}

			if ($user->block == 1)
			{
				self::setAuthError(JText::_("COM_API_BLOCKED_USER"));

				return false;
			}

			/* V1.8.1 - to set admin info headers
			$log_user = JFactory::getUser(); */
			$isroot = $user->authorise('core.admin');

			if ($isroot)
			{
				JResponse::setHeader('x-api', self::getCom_apiVersion());
				JResponse::setHeader('x-plugins', implode(',', self::getPluginsList()));
			}

			return $user;
		}
	}

	/**
	 * Set Auth Error
	 *
	 * @param   STRING  $msg  Message
	 *
	 * @return  boolean
	 *
	 * @since 1.0
	 */
	public static function setAuthError($msg)
	{
		self::$auth_errors[] = $msg;

		return true;
	}

	/**
	 * Get Auth Error
	 *
	 * @return  mixed
	 *
	 * @since 1.0
	 */
	public static function getAuthError()
	{
		if (empty(self::$auth_errors))
		{
			return false;
		}

		return array_pop(self::$auth_errors);
	}

	/**
	 * Get all api type plugin versions
	 *
	 * @return  mixed
	 *
	 * @since 1.8.1
	 */
	public static function getPluginsList()
	{
		$plugins    = JPluginHelper::getPlugin('api');
		$pluginsArr = array();

		foreach ($plugins as $plg)
		{
			$xml          = JFactory::getXML(JPATH_SITE . '/plugins/api/' . $plg->name . '/' . $plg->name . '.xml');
			$version      = (string) $xml->version;
			$pluginsArr[] = $plg->name . '-' . $version;
		}

		return $pluginsArr;
	}

	/**
	 * Get com_api version
	 *
	 * @return string
	 *
	 * @since 1.0
	 */
	public static function getCom_apiVersion()
	{
		$xml = JFactory::getXML(JPATH_ADMINISTRATOR . '/components/com_api/api.xml');

		return $version = (string) $xml->version;
	}

	/**
	 * Get Auth Method
	 *
	 * @return  string  Auth method
	 *
	 * @since 1.0
	 */
	private static function getAuthMethod()
	{
		$app = JFactory::getApplication();
		$key = $app->input->get('key');

		if (isset($_SERVER['HTTP_X_AUTH']) && $_SERVER['HTTP_X_AUTH'])
		{
			$authMethod = $_SERVER['HTTP_X_AUTH'];
		}
		elseif ($key)
		{
			$authMethod = 'key';
		}
		else
		{
			$authMethod = 'login';
		}

		return $authMethod;
	}
}
