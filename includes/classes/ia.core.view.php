<?php
/******************************************************************************
 *
 * Subrion - open source content management system
 * Copyright (C) 2015 Intelliants, LLC <http://www.intelliants.com>
 *
 * This file is part of Subrion.
 *
 * Subrion is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Subrion is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Subrion. If not, see <http://www.gnu.org/licenses/>.
 *
 *
 * @link http://www.subrion.org/
 *
 ******************************************************************************/

class iaView extends abstractUtil
{
	const DEFAULT_ACTION = 'index';
	const DEFAULT_HOMEPAGE = 'index';
	const PAGE_ERROR = 'error';

	const TEMPLATE_FILENAME_EXT = '.tpl';

	const SUCCESS = 'success';
	const ERROR = 'error';
	const ALERT = 'info';
	const SYSTEM = 'system';

	const REQUEST_HTML = 2505;
	const REQUEST_JSON = 2506;
	const REQUEST_XML = 2507;

	const NONE = 'none';
	const JSON_MAGIC_KEY = 'JSON_DIRECT_DATA_PLACEHOLDER';

	const ERROR_UNAUTHORIZED = 401;
	const ERROR_FORBIDDEN = 403;
	const ERROR_NOT_FOUND = 404;
	const ERROR_INTERNAL = 500;

	const RESOURCE_ORDER_SYSTEM = 1;
	const RESOURCE_ORDER_REGULAR = 3;

	protected $_layoutEnabled = true;
	protected $_existBlocks = array();
	protected $_menus = array();
	protected $_messages = array();
	protected $_params = array();
	protected $_pageName;
	protected $_requestType = self::REQUEST_HTML;
	protected $_outputValues = array();

	public $resources;

	public $positions = array();

	public $blocks = array();

	public $assetsUrl;
	public $domain = 'localhost';
	public $domainUrl;
	public $extrasUrl;
	public $packageUrl;
	public $language;
	public $homePage;
	public $theme = 'common';
	public $url;

	public $iaSmarty;

	public $manageMode = false;


	public function init()
	{
		parent::init();

		$this->resources = new iaStore(array('css' => new iaStore(), 'js' => new iaStore()));
	}

	public function set($key, $value)
	{
		if (is_array($value))
		{
			$this->_params[$key] = isset($this->_params[$key]) ? array_merge((array)$this->_params[$key], $value) : $value;
		}
		else
		{
			$this->_params[$key] = $value;
		}
	}

	public function get($key, $default = null)
	{
		return (isset($this->_params[$key]) && $this->_params[$key]) ? $this->_params[$key] : $default;
	}

	public function name($value = false)
	{
		if ($value === false)
		{
			if (empty($this->_pageName))
			{
				return $this->homePage;
			}
			return $this->_pageName;
		}
		else
		{
			if ('_home_' == $value)
			{
				$value = $this->homePage;
			}
			$this->_pageName = $value;
		}

		return $this->_pageName;
	}

	public function loadSmarty($force = false)
	{
		if (iaView::REQUEST_HTML == $this->getRequestType() || $force)
		{
			$compileDir = IA_TMP . (iaCore::ACCESS_ADMIN == $this->iaCore->getAccessType() ? 'admin_' : 'front_') . $this->theme . IA_DS;

			$this->iaCore->factory('util');
			iaUtil::makeDirCascade(IA_TMP . 'smartycache' . IA_DS, 0777, true);
			iaUtil::makeDirCascade($compileDir, 0777, true);

			$this->iaSmarty = $this->iaCore->factory('smarty');
			if (iaCore::ACCESS_ADMIN == $this->iaCore->getAccessType())
			{
				$this->iaSmarty->setTemplateDir(IA_ADMIN . 'templates' . IA_DS . $this->theme . IA_DS);
			}
			else
			{
				$this->iaSmarty->setTemplateDir(IA_HOME . 'templates' . IA_DS . $this->theme . IA_DS);
				$this->iaSmarty->addTemplateDir(IA_HOME . 'templates' . IA_DS . 'common' . IA_DS);
			}

			Smarty::$_CHARSET = 'UTF-8';

			$this->iaSmarty->setCompileDir($compileDir);
			$this->iaSmarty->setCacheDir(IA_TMP . 'smartycache' . IA_DS);
			$this->iaSmarty->setPluginsDir(array(IA_SMARTY . 'plugins', IA_SMARTY . 'intelli_plugins'));

			$this->iaSmarty->force_compile = $this->iaCore->get('smarty_cache', false);
			$this->iaSmarty->cache_modified_check = false;
			$this->iaSmarty->debugging = false;
			$this->iaSmarty->compile_check = true;

			// @FIXME: please find a solution instead of suppressing the errors
			$this->iaSmarty->muteExpectedErrors();
		}
	}

	public function blockExists($blockName)
	{
		return (bool)in_array($blockName, $this->_existBlocks);
	}

	public function isHomepage($name = false)
	{
		return (false === $name)
			? ($this->homePage == $this->name())
			: ($this->homePage == $name);
	}

	public function add_js($files, $order = self::RESOURCE_ORDER_REGULAR)
	{
		if (self::REQUEST_HTML == $this->getRequestType())
		{
			$this->iaSmarty->add_js(array('files' => $files, 'order' => $order));
		}
	}

	public function add_css($files, $order = self::RESOURCE_ORDER_REGULAR)
	{
		if (self::REQUEST_HTML == $this->getRequestType())
		{
			$this->iaSmarty->add_css(array('files' => $files, 'order' => $order));
		}
	}

	public function display($body = self::DEFAULT_HOMEPAGE)
	{
		$this->set('body', $body);
	}

	public function title($title = null)
	{
		if (is_null($title))
		{
			return $this->get('title');
		}
		$this->set('title', $title);
	}

	public function caption($key = null)
	{
		$this->set('caption', $key);
	}

	public function setMessages($message, $type = self::ERROR)
	{
		if (empty($message))
		{
			return false;
		}

		if (is_array($message))
		{
			foreach ($message as $entry)
			{
				$this->setMessages($entry, $type);
			}
		}
		else
		{
			if (!isset($this->_messages[$type]))
			{
				$this->_messages[$type] = array();
			}

			if (!in_array($message, $this->_messages[$type]))
			{
				$this->_messages[$type][] = $message;
			}
		}
	}

	public function getMessages()
	{
		return $this->_messages;
	}

	public function getRequestType()
	{
		return $this->_requestType;
	}

	public function setRequestType($requestType)
	{
		$this->_requestType = $requestType;
	}

	public function getAdminMenu()
	{
		$iaDb = &$this->iaCore->iaDb;

		$result = array();
		$menuGroups = array();
		$extras = $this->iaCore->get('extras');

		$stmt = "`extras` IN ('', '" . implode("','", $extras) . "')";
		$rows = $iaDb->all(array('id', 'name', 'title'), $stmt . ' ORDER BY `order`', null, null, 'admin_pages_groups');
		foreach ($rows as $row)
		{
			$menuGroups[$row['id']] = array_merge($row, array('items' => array()));
		}

		$this->iaCore->factory('item');

		$sql = 'SELECT g.`name` `config`, e.`type`, '
				. 'p.`id`, p.`group`, p.`name`, p.`parent`, p.`title`, p.`attr`, p.`alias`, p.`extras` '
			. 'FROM `:prefix:table_admin_pages` p '
			. 'LEFT JOIN `:prefix:table_config_groups` g ON '
				. "(p.`extras` IN (':extras') AND p.`extras` = g.`extras`) "
			. 'LEFT JOIN `:prefix:table_extras` e ON '
				. "(p.`extras` = e.`name`) "
			. 'WHERE p.`group` IN (:groups) '
				. "AND FIND_IN_SET('menu', p.`menus`) "
				. "AND p.`status` = ':status' "
				. "AND p.`extras` IN ('',':extras') "
			. 'ORDER BY p.`order`';
		$sql = iaDb::printf($sql, array(
			'prefix' => $iaDb->prefix,
			'table_admin_pages' => 'admin_pages',
			'table_config_groups' => iaCore::getConfigGroupsTable(),
			'table_extras' => iaItem::getTable(),
			'groups' => implode(',', array_keys($menuGroups)),
			'status' => iaCore::STATUS_ACTIVE,
			'extras' => implode("','", $extras)
		));
		$rows = $iaDb->getAll($sql);
		foreach ($rows as $row)
		{
			$menuGroups[$row['group']]['items'][] = $row;
		}

		$iaAcl = $this->iaCore->factory('acl');

		// config groups to be included as menu items
		$rows = $iaDb->all(array('name', 'title', 'extras'), "`name` != 'email_templates' AND " . $stmt . ' ORDER BY `order`', null, null, iaCore::getConfigGroupsTable());
		$configGroups = array();
		$templateName = $this->iaCore->get('tmpl');

		foreach ($rows as $row)
		{
			switch (true)
			{
				case ($templateName == $row['extras']):
					$configGroups['template'] = $row['name'];

					break;

				case ($row['extras']):
					$row['config'] = $row['name'];

					$configGroups['plugins'][$row['extras']] = $row;

					break;

				default:
					$row['url'] = 'configuration' . IA_URL_DELIMITER . $row['name'] . IA_URL_DELIMITER;
					$row['name'] = 'configuration_' . $row['name'];

					$configGroups['common'][] = $row;
			}
		}
		//

		foreach ($menuGroups as $group)
		{
			if (!$group['items'])
			{
				continue;
			}

			$menuEntry = $group;
			$menuEntry['items'] = array();

			if (1 == $group['id']) // the group 'System'
			{
				$menuEntry['items'] = $configGroups['common'];
			}

			foreach ($group['items'] as $item)
			{
				if ($iaAcl->checkAccess(iaAcl::OBJECT_ADMIN_PAGE . iaAcl::SEPARATOR . iaCore::ACTION_READ, $item['name']))
				{
					$title = iaLanguage::get($item['title'], $item['title']);
					$data = array(
						'name' => $item['name'],
						'parent' => isset($item['parent']) ? $item['parent'] : null,
						'title' => $title
					);

					if ($item['alias'])
					{
						$data['url'] = IA_ADMIN_URL . $item['alias'];
					}
					if (isset($item['attr']) && $item['attr'])
					{
						$data['attr'] = $item['attr'];
					}
					if ($item['type'] != iaItem::TYPE_PACKAGE
						&& isset($item['config']) && $item['config'])
					{
						$data['config'] = $item['config'];
					}
					if ('templates' == $item['name'] && isset($configGroups['template'])) // custom processing for template configuration
					{
						$data['config'] = $configGroups['template'];
					}

					if (isset($configGroups['plugins'][$item['extras']]))
					{
						unset($configGroups['plugins'][$item['extras']]);
					}

					$menuEntry['items'][] = $data;
				}
			}

			if (isset($menuEntry['items'][0]['name']) && $menuEntry['items'][0]['name'])
			{
				$menuHeading = array('name' => null, 'title' => iaLanguage::get('global'));
				if (iaItem::TYPE_PACKAGE == $item['type'])
				{
					$menuHeading['config'] = $item['extras'];
				}
				array_unshift($menuEntry['items'], $menuHeading);
			}

			$result[$group['name']] = $menuEntry;
		}

		if (!empty($configGroups['plugins']))
		{
			$result['extensions']['items'] = array_merge($result['extensions']['items'], array_values($configGroups['plugins']));
		}

		return $result;
	}

	protected function _getAdminHeaderMenu()
	{
		$result = array();

		if ($rows = $this->iaCore->iaDb->all(array('name', 'title', 'alias', 'attr'), "FIND_IN_SET('header', `menus`) AND `status` = 'active' ORDER BY `order`", null, null, 'admin_pages'))
		{
			$iaAcl = $this->iaCore->factory('acl');

			foreach ($rows as $entry)
			{
				if ($iaAcl->checkAccess(iaAcl::OBJECT_ADMIN_PAGE . iaAcl::SEPARATOR . iaCore::ACTION_READ, $entry['name']))
				{
					$result[] = array(
						'name' => $entry['name'],
						'title' => $entry['title'],
						'url' => IA_ADMIN_URL . ($entry['alias'] ? $entry['alias'] : $entry['name'] . IA_URL_DELIMITER),
						'attr' => $entry['attr']
					);
				}
			}
		}

		return $result;
	}

	protected function _getDisabledPositions()
	{
		$sql = "SELECT `object` `name`, `access` FROM `{$this->iaCore->iaDb->prefix}objects_pages` ";
		$sql .= "WHERE (`object_type` = 'positions' && `page_name` = '" . $this->name() . "') || (`object_type` = 'positions' && `page_name` = '' && `access` = 0) ORDER BY `access` DESC";
		$related = $this->iaCore->iaDb->getAssoc($sql, false);

		$return = array();
		foreach ($related as $position => $value)
		{
			if (!$value[0]['access'])
			{
				$return[] = $position;
			}
		}

		return $return;
	}

	protected function _getDisabledBlocks()
	{
		$table = $this->iaCore->iaDb->prefix . 'objects_pages';
		$page = $this->name();

		$sql = <<<SQL
SELECT `object` FROM `{$table}`
	WHERE `object_type` = 'blocks' && `page_name` = '' && `access` = 0
	&& `object` NOT IN (SELECT `object` FROM `{$table}` WHERE `object_type` = 'blocks' && `page_name` = '{$page}' && `access` = 1)
UNION ALL
SELECT `object` FROM `{$table}`
	WHERE `object_type` = 'blocks' && `page_name` = '{$page}' && `access` = 0
UNION ALL
SELECT `object` FROM `{$table}`
	WHERE `object_type` = 'blocks' && `page_name` != '{$page}' && `access` = 1
	&& `object` NOT IN (
		SELECT `object` FROM `{$table}`
			WHERE `object_type` = 'blocks' && `page_name` = '' && `access` = 0
	)
	GROUP BY `object`
SQL;

		$disabledBlocks = $this->iaCore->iaDb->getAssoc($sql, true);

		return array_keys($disabledBlocks);
	}

	protected function _setBlocks()
	{
		$positions = $this->iaCore->iaDb->assoc(array('name', 'menu', 'movable'), null, 'positions');
		$this->positions = array_keys($positions);

		$disabledPositions = $this->_getDisabledPositions();

		$blocks = $this->iaCore->iaDb->all(iaDb::ALL_COLUMNS_SELECTION,
			"`status` = 'active' AND `extras` IN ('', '" . implode("','", $this->iaCore->get('extras')) . "') ORDER BY `order`",
			null, null, 'blocks');
		$disabledBlocks = $this->_getDisabledBlocks();

		$iaAcl = $this->iaCore->factory('acl');

		foreach ($blocks as $block)
		{
			// get rid of disabled blocks
			$disabledBlock = (int)in_array($block['id'], $disabledBlocks);
			if (!$this->manageMode && $disabledBlock)
			{
				continue;
			}

			// get rid of blocks in disabled positions
			if (!$this->manageMode && in_array($block['position'], $disabledPositions))
			{
				continue;
			}

			if (!$iaAcl->checkAccess('menu' == $block['type'] ? 'menu' : 'block', $block['name']))
			{
				continue;
			}

			if ('menu' == $block['type'])
			{
				$block['contents'] = $this->_getMenuItems($block['id']);
			}
			else
			{
				if (!$block['multilingual'])
				{
					$block['contents'] = iaLanguage::get('block_content_blc' . $block['id']);
					$block['title'] = iaLanguage::get('block_title_blc' . $block['id']);
				}
			}
			$block['display'] = !isset($_COOKIE['box_content_' . $block['name']]) || $_COOKIE['box_content_' . $block['name']] != 'none';
			$block['hidden'] = $disabledBlock;

			$this->blocks[$block['position']][] = $block;
			$this->_existBlocks[] = $block['name'];
		}

		if ($this->manageMode && $this->positions)
		{
			foreach ($this->positions as $position)
			{
				if (!in_array($position, array_keys($this->blocks)))
				{
					$this->blocks[$position] = array();
				}

				$positions[$position]['hidden'] = (int)in_array($position, $disabledPositions);
			}

			$this->iaSmarty->assign('iaPositions', $positions);
		}

		$this->iaCore->startHook('phpCoreSmartyAfterBlockGenerated', array('blocks' => &$this->blocks));
		$this->iaSmarty->assignGlobal('iaBlocks', $this->blocks);
	}

	protected function _getMenuItems($menuId, $pid = false)
	{
		static $pages;

		if (is_null($pages))
		{
			$condition = " AND `extras` IN ('', '" . implode("','", $this->iaCore->get('extras')) . "')";

			$rows = $this->iaCore->iaDb->all(array('alias', 'custom_url', 'name'), "`status` = 'active'" . $condition, null, null, 'pages');
			foreach ($rows as $row)
			{
				if ('members' == $row['name'] && !$this->iaCore->get('members_enabled'))
				{
					continue;
				}

				switch (true)
				{
					case $row['custom_url']:
						$url = $row['custom_url'];
						break;
					case $row['alias']:
						$url = $row['alias'];
						break;
					default:
						$url = $row['name'] . IA_URL_DELIMITER;
				}

				if ($this->isHomepage($row['name']))
				{
					$url = '';
				}

				$pages[$row['name']] = $url;
			}
		}

		if (!isset($this->_menus[$menuId]))
		{
			if ($cache = $this->iaCore->iaCache->get('menu_' . $menuId, 0, true))
			{
				$rows = $cache;
			}
			else
			{
				$sql =
					'SELECT m.*, p.`nofollow`, p.`new_window`, p.`action`, p.`custom_url` ' .
					'FROM `:prefixmenus` m ' .
					'LEFT JOIN `:prefixpages` p ON (p.`name` = m.`page_name`) ' .
					'WHERE m.`menu_id` = :menu ORDER BY m.`level`, m.`id`';
				$sql = iaDb::printf($sql, array(
					'prefix' => $this->iaCore->iaDb->prefix,
					'menu' => $menuId
				));
				$rows = $this->iaCore->iaDb->getAll($sql);
			}

			$list = array();
			foreach ($rows as $row)
			{
				$pageName = $row['page_name'];
				$title = iaLanguage::get('page_title_' . $row['el_id'], self::NONE);

				if ($title == self::NONE)
				{
					$title = iaLanguage::get('page_title_' . $pageName, self::NONE);
				}
				if ($title != self::NONE)
				{
					$row['active'] = ($this->name() == $pageName);
					$row['text'] = $title;
					$row['url'] = '';

					if ($pageName != 'node' && isset($pages[$pageName]))
					{
						$row['url'] = $this->isHomepage($pageName) ? IA_URL : ($row['custom_url'] ? $pages[$pageName] : IA_URL . $pages[$pageName]);
						$list[$row['parent_id']][$row['id']] = $row;
					}
				}
			}

			$iaAcl = $this->iaCore->factory('acl');
			foreach ($rows as $row)
			{
				if (!$iaAcl->isAccessible($row['page_name'], $row['action'], iaAcl::OBJECT_PAGE))
				{
					if (isset($list[$row['id']]))
					{
						$list[$row['parent_id']][$row['id']]['url'] = false;
					}
					else
					{
						unset($list[$row['parent_id']][$row['id']]);
					}
				}
			}

			$this->_menus[$menuId] = $list;

			$this->iaCore->iaCache->write('menu_' . $menuId, $rows);
		}

		if ($pid !== false)
		{
			return isset($this->_menus[$menuId][$pid]) ? $this->_menus[$menuId][$pid] : false;
		}

		return $this->_menus[$menuId];
	}

	protected function _setBlocksBySubPage()
	{
		if (empty($this->blocks))
		{
			return;
		}

		$pageName = $this->name();
		$subPage = $this->get('subpage');

		foreach ($this->blocks as $pos => $list)
		{
			foreach ($list as $index => $b)
			{
				$subpages = true;
				if ($b['subpages'])
				{
					$b['subpages'] = unserialize($b['subpages']);
					if (isset($b['subpages'][$pageName]) && $b['subpages'][$pageName])
					{
						$subpages = false;
						$b['subpages'] = explode('-', $b['subpages'][$pageName]);
						if ($subPage && in_array($subPage, $b['subpages']))
						{
							$subpages = true;
						}
					}
				}
				if (empty($subpages))
				{
					unset($this->blocks[$pos][$index]);
					if (isset($this->blocks[$b['id']]))
					{
						unset($this->blocks[$b['id']]);
					}
				}
			}
		}
	}

	public function definePage()
	{
		$this->homePage = (iaCore::ACCESS_FRONT == $this->iaCore->getAccessType()) ? $this->iaCore->get('home_page') : self::DEFAULT_HOMEPAGE;

		$pageName = $this->name();
		$pageParams = $this->getParams();

		define('IA_FRONT_TEMPLATES', IA_HOME . 'templates' . IA_DS);

		if (iaUsers::hasIdentity() && iaCore::ACCESS_FRONT == $this->iaCore->getAccessType() && self::REQUEST_HTML == $this->getRequestType())
		{
			if (isset($_GET['preview_exit']))
			{
				unset($_SESSION['preview']);
			}
			if (isset($_SESSION['preview']) || isset($_GET['preview']))
			{
				$previewingTemplate = isset($_GET['preview']) ? $_GET['preview'] : $_SESSION['preview'];
				$templates = $this->iaCore->factory('template', iaCore::ADMIN)->getList();
				if (isset($templates[$previewingTemplate]))
				{
					$_SESSION['preview'] = $this->theme = $previewingTemplate;
					$this->assign('previewMode', true);

					$this->iaCore->set('tmpl', $previewingTemplate);
				}
				else
				{
					unset($_SESSION['preview']);
				}
			}

			if (isset($_GET['manage_exit']))
			{
				unset($_SESSION['manageMode']);
			}

			if (isset($_SESSION['manageMode']))
			{
				$this->manageMode = true;
				$this->assign('manageMode', $this->manageMode);
			}
		}

		$where = iaDb::printf("(p.`name` = ':alias' OR p.`alias` = ':alias:extension' OR p.`alias` LIKE ':alias/%')", array('alias' => $pageName, 'extension' => $this->get('extension')));

		if (iaCore::ACCESS_FRONT == $this->iaCore->getAccessType())
		{
			$where.= " AND p.`custom_url` = ''";

			if (!$this->iaCore->get('frontend', true)
				&& (!iaUsers::hasIdentity() || iaUsers::getIdentity()->usergroup_id != iaUsers::MEMBERSHIP_ADMINISTRATOR))
			{
				$this->set('nodebug', true);
				require_once IA_FRONT_TEMPLATES . 'common' . IA_DS . 'offline.tpl';
				die();
			}
			elseif (!$this->iaCore->get('frontend'))
			{
				$this->setMessages(iaLanguage::get('youre_admin_browsing_disabled_front'));
			}

			if (!$this->iaCore->checkDomain())
			{
				if (self::DEFAULT_HOMEPAGE == $pageName)
				{
					$pageName = '';
				}
				$where = iaDb::printf("p.`name` = ':name' OR p.`alias` LIKE ':domain:name%'", array('name' => $pageName, 'domain' => $this->domainUrl));
			}
		}

		$fields = 'p.`id`, e.`type`, e.`url`, ' . (iaCore::ACCESS_ADMIN == $this->iaCore->getAccessType() ? 'p.`title`, ' : '') . ' p.`name`, '
			. 'p.`alias`, p.`action`, p.`extras`, p.`filename`, p.`parent`, p.`group`'
			. (iaCore::ACCESS_ADMIN == $this->iaCore->getAccessType() ? ' ' : ', p.`meta_description` `description`, p.`meta_keywords` `keywords` ');
		$sql = 'SELECT :fields'
			. 'FROM `:prefix' . (iaCore::ACCESS_ADMIN == $this->iaCore->getAccessType() ? 'admin_' : '') . 'pages` p '
			. 'LEFT JOIN `:prefixextras` e ON (e.`name` = p.`extras`) '
			. "WHERE :stmt AND p.`status` = ':status' "
			. "AND (e.`status` = ':status' OR e.`status` IS NULL) "
			. 'ORDER BY LENGTH(p.`alias`) DESC, p.`extras` DESC';
		$sql = iaDb::printf($sql, array(
			'fields' => $fields,
			'prefix' => $this->iaCore->iaDb->prefix,
			'stmt' => $where,
			'status' => iaCore::STATUS_ACTIVE
		));

		$pages = $this->iaCore->iaDb->getAll($sql);
		$this->iaCore->startHook('phpCoreDefineAfterGetPages');

		$baseUrl = $this->iaCore->get('baseurl', $this->domainUrl);
		$page404 = true;

		if ($pages)
		{
			$pageExtension = $this->get('extension');
			if (self::REQUEST_HTML != $this->getRequestType()
				|| (self::REQUEST_HTML == $this->getRequestType() && $pageExtension == IA_URL_DELIMITER))
			{
				$pageExtension = '';
			}

			$requestPath = $this->iaCore->requestPath;
			array_unshift($requestPath, $pageName);
			$requestPath[count($requestPath) - 1] .= $pageExtension;

			foreach ($pages as $page)
			{
				$found = true;
				$requestChunks = $requestPath;
				$index = 0;
				$url = $this->isHomepage($page['name'])
					? array($page['name'])
					: explode(IA_URL_DELIMITER, trim(str_replace(array($this->domainUrl, $baseUrl), 'domain/', $page['alias']), IA_URL_DELIMITER));

				foreach ($url as $urlChunk)
				{
					if (trim($urlChunk) && $found)
					{
						$found = isset($requestChunks[$index])
							? ($requestChunks[$index] == $urlChunk)
							: false;
						unset($requestChunks[$index]);
						$index++;
					}
				}

				if ($found)
				{
					$page404 = false;

					$this->name($page['name']);
					$pageParams = $page;

					$requestChunks = $requestChunks ? array_values($requestChunks) : array();
					empty($requestChunks) || $requestChunks[count($requestChunks) - 1] = str_replace($pageExtension, '', $requestChunks[count($requestChunks) - 1]);
					$this->iaCore->requestPath = $requestChunks;

					break;
				}
			}
		}

		if (iaCore::ACCESS_FRONT == $this->iaCore->getAccessType())
		{
			$this->iaCore->setPackagesData();
		}

		if ($page404)
		{
			return self::errorPage(self::ERROR_NOT_FOUND);
		}

		if (!isset($pageParams['title'])) // frontend page
		{
			$pageParams['title'] = iaLanguage::get(sprintf('page_title_%s', $pageParams['name']));
		}
		if (!isset($pageParams['body']))
		{
			$pageParams['body'] = isset($pageParams['name']) ? $pageParams['name'] : self::DEFAULT_HOMEPAGE;
		}

		if (isset($this->iaCore->requestPath[0]) && in_array($this->iaCore->requestPath[0], array(iaCore::ACTION_ADD, iaCore::ACTION_EDIT, iaCore::ACTION_DELETE)))
		{
			$pageParams['action'] = array_shift($this->iaCore->requestPath);
		}

		$this->extrasUrl = !isset($pageParams['url']) || $pageParams['url'] == IA_URL_DELIMITER ? '' : $pageParams['url'];
		$this->_setParams($pageParams);

		if (!$this->iaCore->checkDomain())
		{
			$this->packageUrl = $this->domainUrl;
			$this->domainUrl = $baseUrl;
		}
		elseif (strpos($this->extrasUrl, 'http://') !== false)
		{
			$this->packageUrl = $this->extrasUrl;
		}
		elseif ($this->iaCore->checkDomain())
		{
			$this->domainUrl = $baseUrl;
		}
	}

	public function defineOutput()
	{
		$this->_setBreadcrumb();

		if (self::REQUEST_HTML == $this->getRequestType())
		{
			if (iaCore::ACCESS_ADMIN == $this->iaCore->getAccessType())
			{
				if (preg_match('/MSIE 7/', $_SERVER['HTTP_USER_AGENT']))
				{
					$this->setMessages(iaLanguage::get('ie_update_warning'), self::ALERT);
				}

				$installerPath = 'install/modules/module.install.php';
				if (file_exists(IA_HOME . $installerPath))
				{
					$this->setMessages(iaLanguage::getf('install_not_deleted', array('file' => $installerPath)), self::SYSTEM);
				}

				if (version_compare(IA_VERSION, $this->iaCore->get('version'), '>'))
				{
					$this->setMessages(iaLanguage::get('core_and_db_versions_mismatch'), self::SYSTEM);
				}

				if (!is_writable(IA_UPLOADS))
				{
					$this->setMessages(iaLanguage::get('upload_writable_permission'), self::SYSTEM);
				}

				if (0 == $this->get('group'))// here we do populate the dashboard quick links
				{
					$quickLinks = $this->iaCore->iaDb->all(iaDb::ALL_COLUMNS_SELECTION, "`type` = 'dashboard' ORDER BY `order` DESC", null, null, 'admin_actions');
					$this->assign('dashboard', $quickLinks);
				}

				// quick search block
				$items = array('users' => array('title' => iaLanguage::get('users'), 'url' => 'members/'));
				$this->iaCore->startHook('adminQuickSearch', array('items' => &$items));
				$currentItem = $this->getValues('quick_search_item');
				$currentItem = isset($items[$currentItem]) ? $currentItem : 'users';

				$this->assign('quickSearch', $items);
				$this->assign('quickSearchItem', $currentItem);
				//

				$adminActions = array();
				if (self::PAGE_ERROR != $this->name())
				{
					$adminActions = $this->_getToolbarActions();
				}

				$this->set('toolbarActions', $adminActions);
				$this->set('headerMenu', $this->_getAdminHeaderMenu());
				$this->set('menu', $this->getAdminMenu());
			}
			else
			{
				$this->_existBlocks || $this->_setBlocks();

				// get rid of inactive languages
				foreach ($this->iaCore->languages as $key => $language)
				{
					if (iaCore::STATUS_INACTIVE == $language['status']) unset($this->iaCore->languages[$key]);
				}
			}

			// aliases
			$this->assign('img', IA_TPL_URL . 'img/');
			$this->assign('pageAction', $this->get('action'));

			// TODO: obsolete not used in 3.3.0, kept for minor compatibility
			$this->assign('nonProtocolUrl', $this->assetsUrl);
			$this->assign('languages', $this->iaCore->languages);
			$this->assign('url', (iaCore::ACCESS_ADMIN == $this->iaCore->getAccessType() ? IA_ADMIN_URL : IA_URL));

			if (isset($_SESSION['msg']) && is_array($_SESSION['msg']))
			{
				foreach ($_SESSION['msg'] as $type => $text)
				{
					$this->setMessages($text, $type);
				}
				unset($_SESSION['msg']);
			}

			$this->_setBlocksBySubPage();
		}
	}

	public function output()
	{
		$outputValues = $this->getValues();

		switch ($this->getRequestType())
		{
			case self::REQUEST_JSON:
				header('Content-Type: application/json');

				$iaUtil = $this->iaCore->factory('util');

				if (isset($outputValues[self::JSON_MAGIC_KEY]) && 1 == count($outputValues))
				{
					$outputValues = array_values($outputValues[self::JSON_MAGIC_KEY]);
				}

				echo $iaUtil->jsonEncode($outputValues);

				break;

			case self::REQUEST_HTML:
				header('Content-Type: text/html');

				$iaSmarty = &$this->iaSmarty;
				foreach ($outputValues as $key => $value)
				{
					$iaSmarty->assign($key, $value);
				}

				// set page notifications
				$messages = $this->getMessages();
				$notifications = array();
				foreach (array(self::ERROR, self::SUCCESS, self::ALERT, self::SYSTEM) as $type)
				{
					empty($messages[$type]) || $notifications[$type] = (is_array($messages[$type]) ? $messages[$type] : array($messages[$type]));
				}

				$pageName = $this->name();

				$iaSmarty->assign('config', $this->iaCore->getConfig());
				$iaSmarty->assign('member', iaUsers::hasIdentity() ? iaUsers::getIdentity(true) : array());

				// TODO: obsolete not used in 3.3.0, kept for minor compatibility
				$iaSmarty->assign('page', $this->getParams());

				// define smarty super global $core
				$core = array(
					'config' => $this->iaCore->getConfig(),
					'customConfig' => $this->iaCore->getCustomConfig(),
					'language' => $this->iaCore->languages[$this->language],
					'languages' => $this->iaCore->languages,
					'notifications' => $notifications,
					'page' => array(
						'breadcrumb' => iaBreadcrumb::render(),
						'info' => $this->getParams(),
						'nonProtocolUrl' => $this->assetsUrl,
						'name' => $pageName,
						'title' => $this->get('caption', $this->get('title', 'Subrion CMS')),
					),
				);

				if (iaCore::ACCESS_FRONT == $this->iaCore->getAccessType())
				{
					// get meta-description
					$value = $this->get('description');
					$metaDescription = (empty($value) && iaLanguage::exists('page_metadescr_' . $pageName))
						? iaLanguage::get('page_metadescr_' . $pageName)
						: $value;
					$core['page']['meta-description'] = iaSanitize::html($metaDescription);

					// get meta-keywords
					$value = $this->get('keywords');
					$metaKeywords = (empty($value) && iaLanguage::exists('page_metakeyword_' . $pageName))
						? iaLanguage::get('page_metakeyword_' . $pageName)
						: $value;
					$core['page']['meta-keywords'] = iaSanitize::html($metaKeywords);

					$this->_logStatistics();

					header('X-Powered-CMS: Subrion CMS');
				}
				$iaSmarty->assignByRef('core', $core);

				$this->iaCore->startHook('phpCoreDisplayBeforeShowBody');

				$content = '';

				if ($this->get('body', self::NONE) != self::NONE)
				{
					$resource = $iaSmarty->ia_template($this->get('body') . self::TEMPLATE_FILENAME_EXT);
					$content = $iaSmarty->fetch($resource);
				}

				if ($this->_layoutEnabled)
				{
					$iaSmarty->assign('_content_', $content);
					$content = $iaSmarty->fetch('layout' . self::TEMPLATE_FILENAME_EXT);
				}

				echo $content;

				break;

			case self::REQUEST_XML:
				header('Content-Type: text/xml');

				function htmldecode($text)
				{
					$text = html_entity_decode($text);
					$text = htmlspecialchars($text);

					return $text;
				}

				function xmlEncode(array $array, &$parentObject)
				{
					static $section;
					foreach ($array as $key => $value)
					{
						switch (true)
						{
							case is_array($array[key($array)]):
								if (!is_numeric($key))
								{
									$node = $parentObject->addChild($key);
									xmlEncode($value, $node);
								}
								else
								{
									$node = $parentObject->addChild($section);
									foreach ($value as $k => $v)
									{
										$node->addChild($k, htmldecode($v));
									}
								}
								break;
							case is_array($value):
								$section = $key;
								xmlEncode($value, $parentObject);
								break;
							default:
								$parentObject->addChild($key, htmldecode($value));
						}
					}
				}

				$xmlObject = new SimpleXMLElement('<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/"></rss>');
				xmlEncode($outputValues, $xmlObject);

				echo $xmlObject->asXML();

				break;

			default:
				header('HTTP/1.1 501');
				exit;
		}
	}

	public function jsonp($data)
	{
		$this->iaCore->factory('util');

		echo sprintf('%s(%s)', isset($_GET['fn']) ? $_GET['fn'] : '', iaUtil::jsonEncode($data));
		exit;
	}

	public function assign($key, $value = null)
	{
		if (is_array($key))
		{
			foreach ($key as $k => $v)
			{
				$this->assign($k, $v);
			}
		}
		else
		{
			if (is_numeric($key))
			{
				if (!isset($this->_outputValues[self::JSON_MAGIC_KEY]))
				{
					$this->_outputValues[self::JSON_MAGIC_KEY] = array();
				}
				$this->_outputValues[self::JSON_MAGIC_KEY][] = $value;
			}
			else
			{
				$this->_outputValues[$key] = $value;
			}
		}
	}

	public function grid($jsFiles = array())
	{
		$core = array('intelli/intelli.grid');
		is_array($jsFiles) || $jsFiles = array($jsFiles);
		$core = array_merge($core, $jsFiles);

		$this->add_js($core);

		$this->assign('token', $this->iaCore->getSecurityToken());

		$this->display('grid');
	}

	public static function errorPage($errorCode, $message = null)
	{
		if (!in_array($errorCode, array(self::ERROR_UNAUTHORIZED, self::ERROR_FORBIDDEN, self::ERROR_NOT_FOUND, self::ERROR_INTERNAL)) && is_null($message))
		{
			$message = $errorCode;
			$errorCode = self::ERROR_FORBIDDEN;
		}
		elseif (is_null($message))
		{
			$message = iaLanguage::get((string)$errorCode, $errorCode);
		}

		$iaCore = iaCore::instance();
		$iaView = &$iaCore->iaView;

		$iaView->name(self::PAGE_ERROR);
		$iaView->_setParams(array(
			'caption' => iaLanguage::get('error', 'Error page') . ' ' . $errorCode,
			'filename' => null,
			'name' => self::PAGE_ERROR,
			'parent' => '',
			'title' => $errorCode
		));

		switch ($iaView->getRequestType())
		{
			case self::REQUEST_JSON:
				$iaView->assign(array('error' => true, 'message' => $message, 'code' => $errorCode));

				break;

			case self::REQUEST_HTML:
				// http://dev.subrion.com/issues/842
				// some Apache servers stop with Authorization Required error
				// because of enabled DEFLATE directives in the .htaccess file
				// below is the workaround
				if (self::ERROR_UNAUTHORIZED != $errorCode && iaCore::ACCESS_ADMIN != $iaCore->getAccessType())
				{
					header('HTTP/1.0 ' . $errorCode);
				}

				$iaView->setMessages($message);
				$iaView->assign('code', $errorCode);

				$body = self::PAGE_ERROR;

				$positions = &$iaView->blocks;
				unset($positions['left'], $positions['right'], $positions['top'], $positions['bottom'], $positions['user1'], $positions['user2']);

				$iaAcl = $iaCore->factory('acl');
				if (iaCore::ACCESS_ADMIN == $iaCore->getAccessType()
					&& ($errorCode == self::ERROR_FORBIDDEN && !$iaAcl->isAdmin() || !iaUsers::hasIdentity()))
				{
					$iaView->disableLayout();
					if (isset($_SERVER['HTTP_REFERER'])
						&& strpos($_SERVER['HTTP_REFERER'], 'install') === false
						&& !isset($_SESSION['IA_EXIT']))
					{
						$iaView->title(iaLanguage::get('access_denied'));
					}
					else
					{
						$iaView->title(iaLanguage::get('login'));
						if (isset($_SESSION['IA_EXIT']))
						{
							unset($_SESSION['IA_EXIT']);
						}
					}
					$body = 'login';
				}
				elseif (iaCore::ACCESS_FRONT == $iaView->iaCore->getAccessType() && $errorCode == self::ERROR_UNAUTHORIZED && !iaUsers::hasIdentity())
				{
					$body = 'login';
				}

				$iaView->display($body);
		}

		return true;
	}

	public static function accessDenied($message = null)
	{
		return self::errorPage(self::ERROR_FORBIDDEN, $message);
	}

	public function disableLayout($disable = true)
	{
		$this->_layoutEnabled = !$disable;
	}

	public function getValues($key = null)
	{
		if (is_null($key))
		{
			return $this->_outputValues;
		}

		return isset($this->_outputValues[$key])
			? $this->_outputValues[$key]
			: null;
	}

	public function getParams()
	{
		return $this->_params;
	}

	protected function _setParams(array $params)
	{
		$this->_params = array_merge($this->_params, $params);
	}

	private function _logStatistics()
	{
		if (!$this->blockExists('common_statistics'))
		{
			return;
		}

		$iaDb = &$this->iaCore->iaDb;

		$commonStatistics = array(
			'members' => array(
				array(
					'title' => iaLanguage::get('members'),
					'value' => (int)$iaDb->one_bind(iaDb::STMT_COUNT_ROWS, '`status` = :status', array('status' => iaCore::STATUS_ACTIVE), iaUsers::getTable())
				)
			)
		);

		$this->iaCore->startHook('populateCommonStatisticsBlock', array('statistics' => &$commonStatistics));

		$iaDb->setTable('online');

		$commonStatistics['online'] = array();
		$commonStatistics['online'][] = array(
			'title' => iaLanguage::get('active_users'),
			'value' => (int)$iaDb->one(iaDb::STMT_COUNT_ROWS, "`status` = 'active' AND `is_bot` = 0")
		);
		if ($this->iaCore->get('members_enabled'))
		{
			$commonStatistics['online'][] = array(
				'title' => iaLanguage::get('members'),
				'value' => (int)$iaDb->one(iaDb::STMT_COUNT_ROWS, "`username` != '' AND `status` = 'active' AND `is_bot` = '0'")
			);
			$commonStatistics['online'][] = array(
				'title' => iaLanguage::get('guests'),
				'value' => $commonStatistics['online'][0]['value'] - $commonStatistics['online'][1]['value']
			);
		}
		$commonStatistics['online'][] = array(
			'title' => iaLanguage::get('bots'),
			'value' => (int)$iaDb->one(iaDb::STMT_COUNT_ROWS, "`status` = 'active' AND `is_bot` = 1")
		);
		$commonStatistics['online'][] = array(
			'title' => iaLanguage::get('live_visits'),
			'value' => (int)$iaDb->one(iaDb::STMT_COUNT_ROWS, '`is_bot` = 0 AND `date` + INTERVAL 1 DAY > NOW()')
		);
		$commonStatistics['online'][] = array(
			'title' => iaLanguage::get('bots_visits'),
			'value' => (int)$iaDb->one(iaDb::STMT_COUNT_ROWS, '`is_bot` = 1 AND `date` + INTERVAL 1 DAY > NOW()')
		);

		if ($this->iaCore->get('members_enabled', true))
		{
			$outputHtml = '';
			if ($array = $iaDb->all("`username`, IF(`fullname` != '', `fullname`, `username`) `fullname`, COUNT(`id`) `count`", "`username` != '' AND `status` = 'active' GROUP BY `username`"))
			{
				foreach ($array as $item)
				{
					$outputHtml .= $this->iaSmarty->ia_url(array('item' => iaUsers::getItemName(), 'type' => 'link', 'text' => $item['fullname'], 'data' => $item)) . ', ';
				}
				$outputHtml = substr($outputHtml, 0, -2);
				$commonStatistics['online'][count($commonStatistics['online']) - 1]['html'] = $outputHtml;
			}
		}

		$this->iaSmarty->assignGlobal('common_statistics', $commonStatistics);

		$iaDb->resetTable();
	}

	protected function _setBreadcrumb()
	{
		if (self::REQUEST_HTML != $this->getRequestType())
		{
			return;
		}

		$this->iaCore->factory('breadcrumb');

		if (iaBreadcrumb::total() > 0
			|| ($this->isHomepage() && empty($this->iaCore->requestPath)))
		{
			return;
		}

		(iaCore::ACCESS_FRONT == $this->iaCore->getAccessType())
			? iaBreadcrumb::root($this->iaCore->get('bc_home'), IA_URL)
			: iaBreadcrumb::root(iaLanguage::get('dashboard'), IA_ADMIN_URL);

		$pluginName = $this->get('extras');

		switch ($this->iaCore->getAccessType())
		{
			case iaCore::ACCESS_FRONT:
				$parents = array();

				$iaPage = $this->iaCore->factory('page', iaCore::FRONT);
				$iaPage->getParents($this->get('parent'), $parents);

				if ($parents)
				{
					iaBreadcrumb::addChain($parents);
				}
				elseif ($pluginName && 'package' == $this->get('type') && $pluginName . '_home' != $this->name())
				{
					if ($this->iaCore->get('default_package', false) != $pluginName)
					{
						iaBreadcrumb::add(iaLanguage::get($pluginName), IA_PACKAGE_URL);
					}
				}

				if ($url = $iaPage->getUrlByName($this->name()))
				{
					iaBreadcrumb::toEnd(iaLanguage::get('page_title_' . $this->name(), $this->name()), $url);
				}

				break;

			case iaCore::ACCESS_ADMIN:
				$iaPage = $this->iaCore->factory('page', iaCore::ADMIN);

				if ($pluginName)
				{
					if ('package' == $this->get('type'))
					{
						$title = iaLanguage::get($pluginName . '_package');
						$url = IA_ADMIN_URL . $pluginName . IA_URL_DELIMITER;

						($pluginName . '_stats' != $this->name())
							? iaBreadcrumb::add($title, $url)
							: iaBreadcrumb::replaceEnd($title, $url);
					}
				}

				$url = $iaPage->getUrlByName($this->name());
				iaBreadcrumb::add($this->get('title', $this->name()), $url);

				if (in_array($this->get('action'), array(iaCore::ACTION_ADD, iaCore::ACTION_EDIT)))
				{
					iaBreadcrumb::toEnd(iaLanguage::get($this->get('action')), IA_SELF);
				}
		}
	}

	private function _getToolbarActions()
	{
		$result = array();

		$stmt = "`pages` REGEXP('[[:<:]]:page(::action)?(,|$)') AND `type` = 'regular' ORDER BY `order` DESC";
		$stmt = iaDb::printf($stmt, array(
			'page' => $this->name(),
			'action' => $this->get('action')
		));;

		$iaAcl = $this->iaCore->factory('acl');
		$rows = $this->iaCore->iaDb->all(array('attributes', 'name', 'icon', 'text', 'url'), $stmt, null, null, 'admin_actions');
		foreach ($rows as $entry)
		{
			if ($iaAcl->checkAccess(iaAcl::OBJECT_ADMIN_PAGE, $entry['name']))
			{
				$result[] = array(
					'attributes' => $entry['attributes'],
					'icon' => empty($entry['icon']) ? '' : 'i-' . $entry['icon'],
					'title' => iaLanguage::get($entry['text'], $entry['text']),
					'url' => $entry['url']
				);
			}
		}

		return $result;
	}
}