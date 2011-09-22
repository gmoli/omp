<?php

/**
 * @file pages/admin/AdminFunctionsHandler.inc.php
 *
 * Copyright (c) 2003-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AdminFunctionsHandler
 * @ingroup pages_admin
 *
 * @brief Handle requests for site administrative/maintenance functions. 
 */



import('lib.pkp.classes.site.Version');
import('lib.pkp.classes.site.VersionDAO');
import('lib.pkp.classes.site.VersionCheck');
import('pages.admin.AdminHandler');

class AdminFunctionsHandler extends AdminHandler {

	/**
	 * Show system information summary.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function systemInfo($args, &$request) {
		$this->validate();
		$this->setupTemplate($request, true);

		$configData =& Config::getData();

		$dbconn =& DBConnection::getConn();
		$dbServerInfo = $dbconn->ServerInfo();

		$versionDao =& DAORegistry::getDAO('VersionDAO');
		$currentVersion =& $versionDao->getCurrentVersion();
		$versionHistory =& $versionDao->getVersionHistory();

		$serverInfo = array(
			'admin.server.platform' => Core::serverPHPOS(),
			'admin.server.phpVersion' => Core::serverPHPVersion(),
			'admin.server.apacheVersion' => (function_exists('apache_get_version') ? apache_get_version() : Locale::translate('common.notAvailable')),
			'admin.server.dbDriver' => Config::getVar('database', 'driver'),
			'admin.server.dbVersion' => (empty($dbServerInfo['description']) ? $dbServerInfo['version'] : $dbServerInfo['description'])
		);

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign_by_ref('currentVersion', $currentVersion);
		$templateMgr->assign_by_ref('versionHistory', $versionHistory);
		$templateMgr->assign_by_ref('configData', $configData);
		$templateMgr->assign_by_ref('serverInfo', $serverInfo);
		if ($request->getUserVar('versionCheck')) {
			$latestVersionInfo =& VersionCheck::getLatestVersion();
			$latestVersionInfo['patch'] = VersionCheck::getPatch($latestVersionInfo);
			$templateMgr->assign_by_ref('latestVersionInfo', $latestVersionInfo);
		}
		$templateMgr->assign('helpTopicId', 'site.administrativeFunctions');
		$templateMgr->display('admin/systemInfo.tpl');
	}

	/**
	 * Edit the system configuration settings.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function editSystemConfig($args, &$request) {
		$this->validate();
		$this->setupTemplate($request, true);

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->append('pageHierarchy', array($request->url(null, 'admin', 'systemInfo'), 'admin.systemInformation'));

		$configData =& Config::getData();

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign_by_ref('configData', $configData);
		$templateMgr->assign('helpTopicId', 'site.administrativeFunctions');
		$templateMgr->display('admin/systemConfig.tpl');
	}

	/**
	 * Save modified system configuration settings.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function saveSystemConfig($args, &$request) {
		$this->validate();
		$this->setupTemplate($request, true);

		$configData =& Config::getData();

		// Update configuration based on user-supplied data
		foreach ($configData as $sectionName => $sectionData) {
			$newData = $request->getUserVar($sectionName);
			foreach ($sectionData as $settingName => $settingValue) {
				if (isset($newData[$settingName])) {
					$newValue = $newData[$settingName];
					if (strtolower($newValue) == "true" || strtolower($newValue) == "on") {
						$newValue = "On";
					} else if (strtolower($newValue) == "false" || strtolower($newValue) == "off") {
						$newValue = "Off";
					}
					$configData[$sectionName][$settingName] = $newValue;
				}
			}
		}

		$templateMgr =& TemplateManager::getManager();

		// Update contents of configuration file
		$configParser = new ConfigParser();
		if (!$configParser->updateConfig(Config::getConfigFileName(), $configData)) {
			// Error reading config file (this should never happen)
			$templateMgr->assign('errorMsg', 'admin.systemConfigFileReadError');
			$templateMgr->assign('backLink', $request->url(null, null, 'systemInfo'));
			$templateMgr->assign('backLinkLabel', 'admin.systemInformation');
			$templateMgr->display('common/error.tpl');

		} else {
			$writeConfigFailed = false;
			$displayConfigContents = $request->getUserVar('display') == null ? false : true;
			$configFileContents = $configParser->getFileContents();

			if (!$displayConfigContents) {
				if (!$configParser->writeConfig(Config::getConfigFileName())) {
					$writeConfigFailed = true;
				}
			}

			// Display confirmation
			$templateMgr->assign('writeConfigFailed', $writeConfigFailed);
			$templateMgr->assign('displayConfigContents', $displayConfigContents);
			$templateMgr->assign('configFileContents', $configFileContents);
			$templateMgr->assign('helpTopicId', 'site.administrativeFunctions');
			$templateMgr->display('admin/systemConfigUpdated.tpl');
		}
	}

	/**
	 * Show full PHP configuration information.
	 */
	function phpinfo() {
		$this->validate();
		phpinfo();
	}

	/**
	 * Expire all user sessions (will log out all users currently logged in).
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function expireSessions($args, &$request) {
		$this->validate();
		$sessionDao =& DAORegistry::getDAO('SessionDAO');
		$sessionDao->deleteAllSessions();
		$request->redirect(null, 'admin');
	}

	/**
	 * Clear compiled templates.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function clearTemplateCache($args, &$request) {
		$this->validate();
		$templateMgr =& TemplateManager::getManager();
		$templateMgr->clearTemplateCache();
		$request->redirect(null, 'admin');
	}

	/**
	 * Clear the data cache.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function clearDataCache($args, &$request) {
		$this->validate();

		// Clear the CacheManager's caches
		$cacheManager =& CacheManager::getManager();
		$cacheManager->flush();

		// Clear ADODB's cache
		$userDao =& DAORegistry::getDAO('UserDAO'); // As good as any
		$userDao->flushCache();

		$request->redirect(null, 'admin');
	}
}

?>
