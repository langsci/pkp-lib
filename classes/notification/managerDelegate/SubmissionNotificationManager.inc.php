<?php

/**
 * @file classes/notification/managerDelegate/SubmissionNotificationManager.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionNotificationManager
 * @ingroup managerDelegate
 *
 * @brief Submission notification types manager delegate.
 */

import('lib.pkp.classes.notification.NotificationManagerDelegate');
// Carola Fanselow: imports added
import('classes.monograph.MonographDAO');
import('classes.press.SeriesDAO');

class SubmissionNotificationManager extends NotificationManagerDelegate {

	/**
	 * Constructor.
	 * @param $notificationType int NOTIFICATION_TYPE_...
	 */
	function SubmissionNotificationManager($notificationType) {
		parent::NotificationManagerDelegate($notificationType);
	}

	/**
	 * @copydoc PKPNotificationOperationManager::getNotificationMessage()
	 */
	public function getNotificationMessage($request, $notification) {
		assert($notification->getAssocType() == ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($notification->getAssocId()); /* @var $submission Submission */

		// Carola Fanselow: series title added to template for notifications about new submissions
		$monographDAO = new MonographDAO;
		$seriesDAO = new SeriesDAO;
		$monograph = $monographDAO -> getById($notification->getAssocId());
		$series = $seriesDAO -> getById($monograph->getSeriesId());

		switch ($notification->getType()) {
			case NOTIFICATION_TYPE_SUBMISSION_SUBMITTED:
				return __('notification.type.submissionSubmitted', array('title' => $submission->getLocalizedTitle(),'series' => $series ->getLocalizedFullTitle()));
			case NOTIFICATION_TYPE_METADATA_MODIFIED:
				return __('notification.type.metadataModified', array('title' => $submission->getLocalizedTitle()));
			case NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED:
				return __('notification.type.editorAssignmentTask');
			default:
				assert(false);
		}
	}



    public function getNotificationMessage($request, $notification) {
        assert($notification->getAssocType() == ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
        $submissionDao = Application::getSubmissionDAO();
        $submission = $submissionDao->getById($notification->getAssocId()); /* @var $submission Submission */


        import('classes.monograph.MonographDAO');
        $monographDAO = new MonographDAO;
        $monograph = $monographDAO -> getById($submission->getId());
        
        switch ($notification->getType()) {
            case NOTIFICATION_TYPE_SUBMISSION_SUBMITTED:
                return __('notification.type.submissionSubmitted', array('title' => $submission->getLocalizedTitle(),'series' => $monograph->getSeriesTitle()));
            case NOTIFICATION_TYPE_METADATA_MODIFIED:
                return __('notification.type.metadataModified', array('title' => $submission->getLocalizedTitle()));
            case NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED:
                return __('notification.type.editorAssignmentTask');
            default:
                assert(false);
        }
    }





	/**
	 * @copydoc PKPNotificationOperationManager::getNotificationUrl()
	 */
	public function getNotificationUrl($request, $notification) {
		$router = $request->getRouter();
		$dispatcher = $router->getDispatcher();

		assert($notification->getAssocType() == ASSOC_TYPE_SUBMISSION && is_numeric($notification->getAssocId()));
		switch ($notification->getType()) {
			case NOTIFICATION_TYPE_SUBMISSION_SUBMITTED:
			case NOTIFICATION_TYPE_METADATA_MODIFIED:
			case NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED:
				$contextDao = Application::getContextDAO();
				$context = $contextDao->getById($notification->getContextId());
				return $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), 'workflow', 'submission', $notification->getAssocId());
			default:
				assert(false);
		}
	}

	/**
	 * @copydoc PKPNotificationManager::getIconClass()
	 */
	public function getIconClass($notification) {
		switch ($notification->getType()) {
			case NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED:
				return 'notifyIconPageAlert';
			case NOTIFICATION_TYPE_SUBMISSION_SUBMITTED:
				return 'notifyIconNewPage';
			case NOTIFICATION_TYPE_METADATA_MODIFIED:
				return 'notifyIconEdit';
			default:
				assert(false);
		}
	}

	/**
	 * @copydoc PKPNotificationManager::getStyleClass()
	 */
	public function getStyleClass($notification) {
		switch ($notification->getType()) {
			case NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED:
				return NOTIFICATION_STYLE_CLASS_INFORMATION;
			case NOTIFICATION_TYPE_SUBMISSION_SUBMITTED:
			case NOTIFICATION_TYPE_METADATA_MODIFIED:
				return '';
			default:
				assert(false);
		}
	}
}

?>
