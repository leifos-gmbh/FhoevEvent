<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once './Services/EventHandling/classes/class.ilEventHookPlugin.php';
include_once './Services/Membership/classes/class.ilParticipants.php';

/**
 * Fhoev event plugin base class
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 */
class ilFhoevEventPlugin extends ilEventHookPlugin
{
	private static $instance = null;

	const PNAME = 'FhoevEvent';
	const CTYPE = 'Services';
	const CNAME = 'EventHandling';
	const SLOT_ID = 'evhk';

	/**
	 * Get singelton instance
	 * @global ilPluginAdmin $ilPluginAdmin
	 * @return ilFhoevImportPlugin
	 */
	public static function getInstance()
	{
		global $ilPluginAdmin;

		if (self::$instance)
		{
			return self::$instance;
		}
		include_once './Services/Component/classes/class.ilPluginAdmin.php';
		return self::$instance = ilPluginAdmin::getPluginObject(
					self::CTYPE, 
					self::CNAME, 
					self::SLOT_ID, 
					self::PNAME
		);
	}
	
	/**
	 * Handle event
	 * @param type $a_component
	 * @param type $a_event
	 * @param type $a_parameter
	 */
	public function handleEvent($a_component, $a_event, $a_parameter)
	{
		ilLoggerFactory::getLogger('fhoevevent')->debug('Handling event: '. $a_component.' '. $a_event);
		
		if($a_component == 'Modules/Group' && $a_event == 'addParticipant')
		{
			return $this->handleGroupAssignMember($a_parameter);
		}
		if($a_component == 'Modules/Group' && $a_event == 'deleteParticipant')
		{
			return $this->handleGroupDeassignMember($a_parameter);
		}

		if(
			$a_component == 'Modules/Course' &&
			(($a_event == 'addParticipant') || ($a_event == 'deleteParticipant'))
		)
		{		
			$ref_id = $this->lookupRefId($a_parameter['obj_id']);
			if(!$this->isMainCourse($ref_id))
			{
				return;
			}
			if($a_component == 'Modules/Course' && $a_event == 'addParticipant')
			{
				return $this->handleCourseAssignMember($a_parameter);
			}
			if($a_component == 'Modules/Course' && $a_event == 'deleteParticipant')
			{
				return $this->handleCourseDeassignMember($a_parameter);
			}
		}
		
		if($a_component == 'Modules/Group' && $a_event == 'afterCreation')
		{
			$GLOBALS['ilLog']->write(__METHOD__.': Listening to event "afteCreation" for component Modules.');
			$this->handleGroupCreation($a_parameter['ref_id'], $a_parameter['obj_id'], $a_parameter['obj_type']);
		}
	}
	
	/**
	 * Handle group creation inside main course
	 * @param type $a_obj_id
	 */
	protected function handleGroupCreation($a_node_ref_id, $a_node_obj_id, $a_node_obj_type)
	{
		if($a_node_obj_type != 'grp')
		{
			$GLOBALS['ilLog']->write(__METHOD__.': New node not of type grp for ref_id ' . $a_node_ref_id);
			return false;
		}
		
		if(!$a_node_ref_id)
		{
			$GLOBALS['ilLog']->write(__METHOD__.': No ref_id given. Aborting!');
			return false;
		}
		
		$parent_id = $GLOBALS['tree']->getParentId($a_node_ref_id);
		if(ilObject::_lookupType($parent_id, true) != 'crs')
		{
			$GLOBALS['ilLog']->write(__METHOD__.': Parent type is not of type "crs"');
			return false;
		}
		if(!$this->isMainCourse($parent_id))
		{
			$GLOBALS['ilLog']->write(__METHOD__.': Parent course is not main course for group ref_id ' . $a_node_ref_id);
			return false;
		}
		
		// determine default group role
		$group_role_id = 0;
		foreach($GLOBALS['rbacreview']->getRolesOfObject($a_node_ref_id,TRUE) as $role_id)
		{
			$role_title = ilObject::_lookupTitle($role_id);
			if(substr($role_title, 0, 8) == 'il_grp_m')
			{
				$group_role_id = $role_id;
			}
		}
		
		if(!$group_role_id)
		{
			$GLOBALS['ilLog']->write(__METHOD__.': Did not found default group member role for group obj_id ' . $a_node_obj_id);
			return false;
		}
		
		// now assign all members of the parent course to the new group
		include_once './Modules/Course/classes/class.ilCourseParticipants.php';
		$part = ilCourseParticipants::getInstanceByObjId(ilObject::_lookupObjectId($parent_id));
		foreach($part->getMembers() as $member_id)
		{
			// do not assign user if he/she is admin or tutor
			if($part->isAdmin($member_id) || $part->isTutor($member_id))
			{
				continue;
			}
			$GLOBALS['rbacadmin']->assignUser($group_role_id, $member_id);
		}
		return true;
	}
	
	/**
	 * Assign user to 
	 * @param type $a_parameter
	 */
	protected function handleCourseAssignMember($a_parameter)
	{
		// admin or tutor role => nothing to do
		if($a_parameter['role_id'] == IL_CRS_ADMIN)
		{
			ilLoggerFactory::getLogger('fhoevevent')->debug('Nothing todo for course role admin');
			return TRUE;
		}
		if($a_parameter['role_id'] == IL_CRS_TUTOR)
		{
			ilLoggerFactory::getLogger('fhoevevent')->debug('Nothing todo for course role tutor');
			return TRUE;
		}
		
		$ref_id = $this->lookupRefId($a_parameter['obj_id']);
		foreach($GLOBALS['tree']->getChildsByType($ref_id,'grp') as $node)
		{
			foreach($GLOBALS['rbacreview']->getRolesOfObject($node['child'],TRUE) as $role_id)
			{
				$role_title = ilObject::_lookupTitle($role_id);
				if(substr($role_title, 0, 8) == 'il_grp_m')
				{
					ilLoggerFactory::getLogger('fhoevevent')->info('Assigning user ' . $a_parameter['usr_id'].' to group '.
						ilObject::_lookupTitle($node['obj_id'])
					);
					$GLOBALS['rbacadmin']->assignUser($role_id, $a_parameter['usr_id']);
				}
			}
		}
	}
	
	/**
	 * Course deassign user
	 * @param type $a_parameter
	 */
	protected function handleCourseDeassignMember($a_parameter)
	{
		$GLOBALS['ilLog']->write(__METHOD__.': Listening to event delete participant from course!');
		$ref_id = $this->lookupRefId($a_parameter['obj_id']);
		foreach($GLOBALS['tree']->getChildsByType($ref_id,'grp') as $node)
		{
			foreach($GLOBALS['rbacreview']->getRolesOfObject($node['child'],TRUE) as $role_id)
			{
				// deassigning from group
				if($GLOBALS['rbacreview']->isAssigned($a_parameter['usr_id'], $role_id))
				{
					$GLOBALS['rbacadmin']->deassignUser($role_id, $a_parameter['usr_id']);
					$GLOBALS['ilLog']->write(__METHOD__.': Deassigning user '.$a_parameter['usr_id'].' from group '. ilObject::_lookupTitle($node['obj_id']));
				}
			}
		}
	}

	/**
	 * Group assignmnents
	 * @param type $a_parameter
	 * @return boolean
	 */
	protected function handleGroupAssignMember($a_parameter)
	{
		ilLoggerFactory::getLogger('fhoevevent')->debug('Listening to event add participant from group!');
		$ref_id = $this->lookupRefId($a_parameter['obj_id']);
		
		$parent_course_ref = $GLOBALS['tree']->checkForParentType($ref_id,'crs');
		if(!$parent_course_ref)
		{
			ilLoggerFactory::getLogger('fhoevevent')->debug('No parent course found.');
			return FALSE;
		}
		
		if(!$this->isMainCourse($parent_course_ref))
		{
			return FALSE;
		}
		
		include_once './Modules/Course/classes/class.ilCourseParticipant.php';
		$part = ilCourseParticipant::_getInstanceByObjId(ilObject::_lookupObjId($parent_course_ref), $a_parameter['usr_id']);
		if($part->isParticipant())
		{
			ilLoggerFactory::getLogger('fhoevevent')->debug('User is already assigned to main course.');
			return false;
		}
		
		// Assign to dtpl local role
		foreach($GLOBALS['rbacreview']->getRolesOfObject($parent_course_ref,TRUE) as $role_id)
		{
			// assign as course member
			$role_title = ilObject::_lookupTitle($role_id);
			if(substr($role_title, 0, 4 ) == 'DTPL')
			{
				if(!$GLOBALS['rbacreview']->isAssigned($a_parameter['usr_id'], $role_id))
				{
					$GLOBALS['rbacadmin']->assignUser($role_id, $a_parameter['usr_id']);
					
					ilLoggerFactory::getLogger('fhoevevent')->info('Assigning user ' . $a_parameter['usr_id'] . 
						'to course '. ilObject::_lookupTitle(ilObject::_lookupObjId($parent_course_ref, true))
					);
				}
			}
		}
	}
	
	/**
	 * Handle group deassign member
	 * @param array $a_parameter
	 */
	protected function handleGroupDeassignMember($a_parameter)
	{
		ilLoggerFactory::getLogger('fhoevevent')->debug('Listening to event delete participant from group! Nothing todo.');
	}
	

	/**
	 * check if course is of type main course
	 * @param int ref_id
	 * @return boolean
	 */
	protected function isMainCourse($a_course_ref_id)
	{
		if(!ilFhoevEventSettings::getInstance()->isActive())
		{
			ilLoggerFactory::getLogger('fhoevevent')->debug('Plugin deactivated');
		}
		$dtpl = ilFhoevEventSettings::getInstance()->getTemplateId();
		if(!$dtpl)
		{
			ilLoggerFactory::getLogger('fhoevevent')->debug('no templated id given');
		}
		
		include_once './Services/DidacticTemplate/classes/class.ilDidacticTemplateObjSettings.php';
		
		$current_dtpl_id = ilDidacticTemplateObjSettings::lookupTemplateId($a_course_ref_id);
		ilLoggerFactory::getLogger('fhoevevent')->debug('Current dtpl id is: ' . $current_dtpl_id);
		ilLoggerFactory::getLogger('fhoevevent')->debug('Current dtpl is: ' . $dtpl);
		ilLoggerFactory::getLogger('fhoevevent')->debug('Current ref_id is: ' . $a_course_ref_id);
		if($current_dtpl_id != $dtpl)
		{
			ilLoggerFactory::getLogger('fhoevevent')->debug('Not main course');
			return FALSE;
		}
		ilLoggerFactory::getLogger('fhoevevent')->debug('... is main course');
		return TRUE;
	}

	/**
	 * Get plugin name
	 * @return string
	 */
	public function getPluginName()
	{
		return self::PNAME;
	}

	/**
	 * Init auto load
	 */
	protected function init()
	{
		$this->initAutoLoad();
	}

	/**
	 * Init auto loader
	 * @return void
	 */
	protected function initAutoLoad()
	{
		spl_autoload_register(
				array($this, 'autoLoad')
		);
	}
	
	

	/**
	 * Auto load implementation
	 *
	 * @param string class name
	 */
	private final function autoLoad($a_classname)
	{
		$class_file = $this->getClassesDirectory() . '/class.' . $a_classname . '.php';
		if (@include_once($class_file))
		{
			return;
		}
	}
	
	/**
	 * Lookup ref_id
	 * @param type $a_obj_id
	 * @return type
	 */
	public function lookupRefId($a_obj_id)
	{
		$refs = ilObject::_getAllReferences($a_obj_id);
		return end($refs);
	}

}
?>