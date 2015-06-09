<?php

/**
 * @file controllers/grid/settings/user/UserGridHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserGridHandler
 * @ingroup controllers_grid_settings_user
 *
 * @brief Handle user grid requests.
 */

import('lib.pkp.classes.controllers.grid.GridHandler');
import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');

import('lib.pkp.controllers.grid.settings.user.UserGridRow');
import('lib.pkp.controllers.grid.settings.user.form.UserDetailsForm');

class UserGridHandler extends GridHandler {
	/** integer user id for the user to remove */
	var $_oldUserId;

	/**
	 * Constructor
	 */
	function UserGridHandler() {
		parent::GridHandler();
		$this->addRoleAssignment(array(
			ROLE_ID_MANAGER),
			array('fetchGrid', 'fetchRow', 'editUser', 'updateUser', 'updateUserRoles',
				'editDisableUser', 'disableUser', 'removeUser', 'addUser',
				'editEmail', 'sendEmail')
		);
		/* code changed, Carola Fanselow: let series editors send mail */
		$this->addRoleAssignment(array(ROLE_ID_SUB_EDITOR),array('sendEmail'));

		$this->addRoleAssignment(array(ROLE_ID_SITE_ADMIN), array('mergeUsers'));
	}


	//
	// Implement template methods from PKPHandler.
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.PkpContextAccessPolicy');
		$this->addPolicy(new PkpContextAccessPolicy($request, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * @copydoc PKPHandler::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);

		// Load user-related translations.
		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_USER,
			LOCALE_COMPONENT_PKP_MANAGER,
			LOCALE_COMPONENT_APP_MANAGER
		);

		$this->_oldUserId  = (int) $request->getUserVar('oldUserId');
		// Basic grid configuration.
		$this->setTitle('grid.user.currentUsers');

		// Grid actions.
		$router = $request->getRouter();

		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$this->addAction(
			new LinkAction(
				'addUser',
				new AjaxModal(
					$router->url($request, null, null, 'addUser', null, null),
					__('grid.user.add'),
					'modal_add_user',
					true
					),
				__('grid.user.add'),
				'add_user')
		);

		//
		// Grid columns.
		//

		// First Name.
		$cellProvider = new DataObjectGridCellProvider();
		$this->addColumn(
			new GridColumn(
				'firstName',
				'user.firstName',
				null,
				'controllers/grid/gridCell.tpl',
				$cellProvider
			)
		);

		// Last Name.
		$cellProvider = new DataObjectGridCellProvider();
		$this->addColumn(
			new GridColumn(
				'lastName',
				'user.lastName',
				null,
				'controllers/grid/gridCell.tpl',
				$cellProvider
			)
		);

		// User name.
		$cellProvider = new DataObjectGridCellProvider();
		$this->addColumn(
				new GridColumn(
					'username',
					'user.username',
					null,
					'controllers/grid/gridCell.tpl',
					$cellProvider
				)
		);

		// Email.
		$cellProvider = new DataObjectGridCellProvider();
		$this->addColumn(
			new GridColumn(
				'email',
				'user.email',
				null,
				'controllers/grid/gridCell.tpl',
				$cellProvider
			)
		);
	}


	//
	// Implement methods from GridHandler.
	//
	/**
	 * @copydoc GridHandler::getRowInstance()
	 * @return UserGridRow
	 */
	function getRowInstance() {
		return new UserGridRow($this->_oldUserId);
	}

	/**
	 * @copydoc GridHandler::initFeatures()
	 */
	function initFeatures($request, $args) {
		import('lib.pkp.classes.controllers.grid.feature.PagingFeature');
		return array(new PagingFeature());
	}

	/**
	 * @copydoc GridHandler::loadData()
	 * @param $request PKPRequest
	 * @return array Grid data.
	 */
	function loadData($request, $filter) {
		// Get the context.
		$context = $request->getContext();

		// Get all users for this context that match search criteria.
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$rangeInfo = $this->getGridRangeInfo($request, $this->getId());

		return $users = $userGroupDao->getUsersById(
			$filter['userGroup'],
			$filter['includeNoRole']?null:$context->getId(),
			$filter['searchField'],
			$filter['search']?$filter['search']:null,
			$filter['searchMatch'],
			$rangeInfo
		);
	}

	/**
	 * @copydoc GridHandler::renderFilter()
	 */
	function renderFilter($request) {
		$context = $request->getContext();
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$userGroups = $userGroupDao->getByContextId($context->getId());
		$userGroupOptions = array('' => __('grid.user.allRoles'));
		while ($userGroup = $userGroups->next()) {
			$userGroupOptions[$userGroup->getId()] = $userGroup->getLocalizedName();
		}

		// Import PKPUserDAO to define the USER_FIELD_* constants.
		import('lib.pkp.classes.user.PKPUserDAO');
		$fieldOptions = array(
			USER_FIELD_FIRSTNAME => 'user.firstName',
			USER_FIELD_LASTNAME => 'user.lastName',
			USER_FIELD_USERNAME => 'user.username',
			USER_FIELD_EMAIL => 'user.email'
		);

		$matchOptions = array(
			'contains' => 'form.contains',
			'is' => 'form.is'
		);

		$filterData = array(
			'userGroupOptions' => $userGroupOptions,
			'fieldOptions' => $fieldOptions,
			'matchOptions' => $matchOptions
		);

		return parent::renderFilter($request, $filterData);
	}

	/**
	 * @copydoc GridHandler::getFilterSelectionData()
	 * @return array Filter selection data.
	 */
	function getFilterSelectionData($request) {
		// Get the search terms.
		$includeNoRole = $request->getUserVar('includeNoRole') ? (int) $request->getUserVar('includeNoRole') : null;
		$userGroup = $request->getUserVar('userGroup') ? (int)$request->getUserVar('userGroup') : null;
		$searchField = $request->getUserVar('searchField');
		$searchMatch = $request->getUserVar('searchMatch');
		$search = $request->getUserVar('search');

		return $filterSelectionData = array(
			'includeNoRole' => $includeNoRole,
			'userGroup' => $userGroup,
			'searchField' => $searchField,
			'searchMatch' => $searchMatch,
			'search' => $search ? $search : ''
		);
	}

	/**
	 * @copydoc GridHandler::getFilterForm()
	 * @return string Filter template.
	 */
	function getFilterForm() {
		return 'controllers/grid/settings/user/userGridFilter.tpl';
	}


	//
	// Public grid actions.
	//
	/**
	 * Add a new user.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function addUser($args, $request) {
		// Calling editUser with an empty row id will add a new user.
		return $this->editUser($args, $request);
	}

	/**
	 * Edit an existing user.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function editUser($args, $request) {
		// Identify the user Id.
		$userId = $request->getUserVar('rowId');
		if (!$userId) $userId = $request->getUserVar('userId');

		$user = $request->getUser();
		if ($userId !== null && !Validation::canAdminister($userId, $user->getId())) {
			// We don't have administrative rights over this user.
			$json = new JSONMessage(false, __('grid.user.cannotAdminister'));
		} else {
			// Form handling.
			$userForm = new UserDetailsForm($request, $userId);
			$userForm->initData($args, $request);

			$json = new JSONMessage(true, $userForm->display($args, $request));
		}
		return $json->getString();
	}

	/**
	 * Update an existing user.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function updateUser($args, $request) {
		$user = $request->getUser();

		// Identify the user Id.
		$userId = $request->getUserVar('userId');

		if ($userId !== null && !Validation::canAdminister($userId, $user->getId())) {
			// We don't have administrative rights over this user.
			$json = new JSONMessage(false, __('grid.user.cannotAdminister'));
		} else {
			// Form handling.
			$userForm = new UserDetailsForm($request, $userId);
			$userForm->readInputData();

			if ($userForm->validate()) {
				$user = $userForm->execute($args, $request);

				// If this is a newly created user, show role management form.
				if (!$userId) {
					import('lib.pkp.controllers.grid.settings.user.form.UserRoleForm');
					$userRoleForm = new UserRoleForm($user->getId(), $user->getFullName());
					$userRoleForm->initData($args, $request);
					$json = new JSONMessage(true, $userRoleForm->display($args, $request));
				} else {

					// Successful edit of an existing user.
					$notificationManager = new NotificationManager();
					$user = $request->getUser();
					$notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.editedUser')));

					// Prepare the grid row data.
					return DAO::getDataChangedEvent($userId);
				}
			} else {
				$json = new JSONMessage(false);
			}
		}
		return $json->getString();
	}

	/**
	 * Update a newly created user's roles
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function updateUserRoles($args, $request) {
		$user = $request->getUser();

		// Identify the user Id.
		$userId = $request->getUserVar('userId');

		if ($userId !== null && !Validation::canAdminister($userId, $user->getId())) {
			// We don't have administrative rights over this user.
			$json = new JSONMessage(false, __('grid.user.cannotAdminister'));
		} else {
			// Form handling.
			import('lib.pkp.controllers.grid.settings.user.form.UserRoleForm');
			$userRoleForm = new UserRoleForm($userId, $user->getFullName());
			$userRoleForm->readInputData();

			if ($userRoleForm->validate()) {
				$userRoleForm->execute($args, $request);

				// Successfully managed newly created user's roles.
				return DAO::getDataChangedEvent($userId);
			} else {
				$json = new JSONMessage(false);
			}
		}
		return $json->getString();
	}

	/**
	 * Edit enable/disable user form
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function editDisableUser($args, $request) {
		$user = $request->getUser();

		// Identify the user Id.
		$userId = $request->getUserVar('rowId');
		if (!$userId) $userId = $request->getUserVar('userId');

		// Are we enabling or disabling this user.
		$enable = isset($args['enable']) ? (bool) $args['enable'] : false;

		if ($userId !== null && !Validation::canAdminister($userId, $user->getId())) {
			// We don't have administrative rights over this user.
			$json = new JSONMessage(false, __('grid.user.cannotAdminister'));
		} else {
			// Form handling
			import('lib.pkp.controllers.grid.settings.user.form.UserDisableForm');
			$userForm = new UserDisableForm($userId, $enable);

			$userForm->initData($args, $request);

			$json = new JSONMessage(true, $userForm->display($args, $request));
		}
		return $json->getString();
	}

	/**
	 * Enable/Disable an existing user
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function disableUser($args, $request) {
		$user = $request->getUser();

		// Identify the user Id.
		$userId = $request->getUserVar('userId');

		// Are we enabling or disabling this user.
		$enable = (bool) $request->getUserVar('enable');

		if ($userId !== null && !Validation::canAdminister($userId, $user->getId())) {
			// We don't have administrative rights over this user.
			$json = new JSONMessage(false, __('grid.user.cannotAdminister'));
		} else {
			// Form handling.
			import('lib.pkp.controllers.grid.settings.user.form.UserDisableForm');
			$userForm = new UserDisableForm($userId, $enable);

			$userForm->readInputData();

			if ($userForm->validate()) {
				$user = $userForm->execute($args, $request);

				// Successful enable/disable of an existing user.
				// Update grid data.
				return DAO::getDataChangedEvent($userId);

			} else {
				$json = new JSONMessage(false, $userForm->display($args, $request));
			}
		}
		return $json->getString();
	}

	/**
	 * Remove all user group assignments for a context for a given user.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function removeUser($args, $request) {
		$context = $request->getContext();
		$user = $request->getUser();

		// Identify the user Id.
		$userId = $request->getUserVar('rowId');

		if ($userId !== null && !Validation::canAdminister($userId, $user->getId())) {
			// We don't have administrative rights over this user.
			$json = new JSONMessage(false, __('grid.user.cannotAdminister'));
		} else {
			// Remove user from all user group assignments for this context.
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');

			// Check if this user has any user group assignments for this context.
			if (!$userGroupDao->userInAnyGroup($userId, $context->getId())) {
				$json = new JSONMessage(false, __('grid.user.userNoRoles'));
			} else {
				$userGroupDao->deleteAssignmentsByContextId($context->getId(), $userId);
				return DAO::getDataChangedEvent($userId);
			}
		}
		return $json->getString();
	}

	/**
	 * Displays a modal to edit an email message to the user.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function editEmail($args, $request) {
		$user = $request->getUser();

		// Identify the user Id.
		$userId = $request->getUserVar('rowId');

		if ($userId !== null && !Validation::canAdminister($userId, $user->getId())) {
			// We don't have administrative rights over this user.
			$json = new JSONMessage(false, __('grid.user.cannotAdminister'));
		} else {
			// Form handling.
			import('lib.pkp.controllers.grid.settings.user.form.UserEmailForm');
			$userEmailForm = new UserEmailForm($userId);
			$userEmailForm->initData($args, $request);

			$json = new JSONMessage(true, $userEmailForm->display($args, $request));
		}
		return $json->getString();
	}

	/**
	 * Send the user email and close the modal.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function sendEmail($args, $request) {
		$user = $request->getUser();

		// Identify the user Id.
		$userId = $request->getUserVar('userId');

		/* code changed, Carola Fanselow: let series editors send mail: remove canAdminister-check temporarily */

		//if ($userId !== null && !Validation::canAdminister($userId, $user->getId())) {
			// We don't have administrative rights over this user.
		//	$json = new JSONMessage(false, __('grid.user.cannotAdminister'));
		//} else {
			// Form handling.
			import('lib.pkp.controllers.grid.settings.user.form.UserEmailForm');
			$userEmailForm = new UserEmailForm($userId);
			$userEmailForm->readInputData();

			if ($userEmailForm->validate()) {
				$userEmailForm->execute($args, $request);
				$json = new JSONMessage(true);
			} else {
				$json = new JSONMessage(false, $userEmailForm->display($args, $request));
			}
		//}
		return $json->getString();
	}

	/**
	 * Allow the Site Administrator to merge user accounts, including attributed submissions etc.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function mergeUsers($args, $request) {

		// if there is a $newUserId, this is the second time through, so merge the users.
		$newUserId =  (int) $request->getUserVar('newUserId');
		$oldUserId = (int) $request->getUserVar('oldUserId');
		if ($newUserId > 0 && $oldUserId > 0) {
			import('classes.user.UserAction');
			$userAction = new UserAction();
			$userAction->mergeUsers($oldUserId, $newUserId);
			return DAO::getDataChangedEvent();
		} else {
			// this shouldn't happen since the first time this action is
			// selected on the grid there is no call to the handler.
			$json = new JSONMessage(false, __('grid.user.cannotAdminister'));
			return $json->getString();
		}
	}

	/**
	 * @see GridHandler::getRequestArgs()
	 */
	function getRequestArgs() {
		$requestArgs = (array) parent::getRequestArgs();
		$requestArgs['oldUserId'] = $this->_oldUserId;
		return $requestArgs;
	}
}

?>
