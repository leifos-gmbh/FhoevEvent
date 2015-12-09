<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once './Services/Component/classes/class.ilPluginConfigGUI.php';

/**
 * Description of class
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 */
class ilFhoevEventConfigGUI extends ilPluginConfigGUI
{
	/**
	* Handles all commmands, default is "configure"
	*/
	public function performCommand($cmd)
	{
		global $ilCtrl;
		global $ilTabs;
		
		$ilCtrl->saveParameter($this, "menu_id");
		
		switch ($cmd)
		{
			default:
				$this->$cmd();
				break;

		}
	}
	
	/**
	 * Show settings screen
	 * @global type $tpl
	 * @global type $ilTabs 
	 */
	protected function configure(ilPropertyFormGUI $form = null)
	{
		global $tpl, $ilTabs;

		$ilTabs->activateTab('settings');
		
		$ilTabs->addTab(
			'settings',
			ilFhoevEventPlugin::getInstance()->txt('tab_settings'),
			$GLOBALS['ilCtrl']->getLinkTarget($this,'configure')
		);
		

		if(!$form instanceof ilPropertyFormGUI)
		{
			$form = $this->initConfigurationForm();
		}
		$tpl->setContent($form->getHTML());
	}
	
	/**
	 * Init configuration form
	 * @global type $ilCtrl 
	 */
	protected function initConfigurationForm()
	{
		global $ilCtrl, $lng;
		
		$settings = ilFhoevEventSettings::getInstance();
		
		include_once './Services/Form/classes/class.ilPropertyFormGUI.php';
		
		$form = new ilPropertyFormGUI();
		$form->setTitle($this->getPluginObject()->txt('tbl_fhoev_event_settings'));
		$form->setFormAction($ilCtrl->getFormAction($this));
		$form->addCommandButton('save', $lng->txt('save'));
		$form->setShowTopButtons(false);
		
		$lock = new ilCheckboxInputGUI($this->getPluginObject()->txt('tbl_settings_active'),'active');
		$lock->setValue(1);
		$lock->setChecked($settings->isActive());
		$form->addItem($lock);
		
		$dtpl = new ilNumberInputGUI($this->getPluginObject()->txt('tbl_settings_dtpl'), 'dtpl');
		$dtpl->setSize(8);
		$dtpl->setValue($settings->getTemplateId());
		$dtpl->setRequired(TRUE);
		$dtpl->setMinValue(1);
		$form->addItem($dtpl);
		
		return $form;
	}
	
	/**
	 * Save settings
	 */
	protected function save()
	{
		global $lng, $ilCtrl;
		
		$form = $this->initConfigurationForm();
		$settings = ilFhoevEventSettings::getInstance();
		
		if($form->checkInput())
		{
			$settings->setActive($form->getInput('active'));
			$settings->setTemplateId($form->getInput('dtpl'));
			$settings->save();
				
			ilUtil::sendSuccess($lng->txt('settings_saved'),true);
			$ilCtrl->redirect($this,'configure');
		}
		
		$error = $lng->txt('err_check_input');
		$form->setValuesByPost();
		ilUtil::sendFailure($e);
		$this->configure($form);
	}
	
}
?>