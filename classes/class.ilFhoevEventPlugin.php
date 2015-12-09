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
		if($a_component == 'Modules/Group' && $a_event == 'addParticipant')
		{
			return $this->handleGroupAssignMember($a_parameter);
		}
		if($a_component == 'Modules/Group' && $a_event == 'deleteParticipant')
		{
			return $this->handleGroupDeassignMember($a_parameter);
		}

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
	
	/**
	 * Assign user to 
	 * @param type $a_parameter
	 */
	protected function handleCourseAssignMember($a_parameter)
	{
		$GLOBALS['ilLog']->write(__METHOD__.': Listening to event add participant from course!');
		
		// admin or tutor role => nothing to do
		$GLOBALS['ilLog']->write(__METHOD__.': Handling role assignment to role: '. $a_parameter['role_id']);
		
		if($a_parameter['role_id'] == IL_CRS_ADMIN)
		{
			$GLOBALS['ilLog']->write(__METHOD__.': Nothing to do for course role admin');
			return TRUE;
		}
		if($a_parameter['role_id'] == IL_CRS_TUTOR)
		{
			$GLOBALS['ilLog']->write(__METHOD__.': Nothing to do for course role tutor');
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
					$GLOBALS['ilLog']->write(__METHOD__.': Assigning user '.$a_parameter['usr_id'].' to group ' . ilObject::_lookupTitle($node['obj_id']));
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

	protected function handleGroupAssignMember($a_parameter)
	{
		$GLOBALS['ilLog']->write(__METHOD__.': Listening to event add participant from group!');
		$ref_id = $this->lookupRefId($a_parameter['obj_id']);
		
		$parent_course_ref = $GLOBALS['tree']->checkForParentType($ref_id,'crs');
		if(!$parent_course_ref)
		{
			$GLOBALS['ilLog']->write('No parent course found => nothing to do!');
			return FALSE;
		}
		
		if(!$this->isMainCourse($parent_course_ref))
		{
			return FALSE;
		}

		foreach($GLOBALS['rbacreview']->getRolesOfObject($parent_course_ref,TRUE) as $role_id)
		{
			// assign as course member
			$role_title = ilObject::_lookupTitle($role_id);
			if(substr($role_title, 0, 8 ) == 'il_crs_m')
			{
				if(!$GLOBALS['rbacreview']->isAssigned($a_parameter['usr_id'], $role_id))
				{
					$GLOBALS['rbacadmin']->assignUser($role_id, $a_parameter['usr_id']);
					$GLOBALS['ilLog']->write(__METHOD__.': Assigning user '.$a_parameter['usr_id'].
							' to course ' . ilObject::_lookupTitle(ilObject::_lookupObjId($parent_course_ref, TRUE)));
				}
			}
		}
	}
	
	protected function handleGroupDeassignMember($a_parameter)
	{
		$GLOBALS['ilLog']->write(__METHOD__.': Listening to event delete participant from group! Nothing to do.');
	}
	

	/**
	 * check if course is of type main course
	 * @param type $a_parameter
	 * @return boolean
	 */
	protected function isMainCourse($a_course_ref_id)
	{
		if(!ilFhoevEventSettings::getInstance()->isActive())
		{
			$GLOBALS['ilLog']->write(__METHOD__.': Plugin deactivated');
		}
		$dtpl = ilFhoevEventSettings::getInstance()->getTemplateId();
		if(!$dtpl)
		{
			$GLOBALS['ilLog']->write(__METHOD__.': No template id given');
		}
		
		include_once './Services/DidacticTemplate/classes/class.ilDidacticTemplateObjSettings.php';
		
		$current_dtpl_id = ilDidacticTemplateObjSettings::lookupTemplateId($a_course_ref_id);
		$GLOBALS['ilLog']->write(__METHOD__.': Current dtpl id is ' . $current_dtpl_id);
		$GLOBALS['ilLog']->write(__METHOD__.': Current dtpl is ' . $dtpl);
		$GLOBALS['ilLog']->write(__METHOD__.': Current ref_id ' . $a_course_ref_id);
		if($current_dtpl_id != $dtpl)
		{
			$GLOBALS['ilLog']->write(__METHOD__.': Not main course');
			return FALSE;
		}
		
		$GLOBALS['ilLog']->write(__METHOD__.': ... is main course');
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