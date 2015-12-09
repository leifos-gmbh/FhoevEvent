<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Fhoev event settings
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 */
class ilFhoevEventSettings
{
	private static $instance = null;
	
	private $storage = null;
	
	private $active = FALSE;
	private $template_id = 0;

	/**
	 * Singelton constructor
	 */
	protected function __construct()
	{
		include_once './Services/Administration/classes/class.ilSetting.php';
		$this->storage = new ilSetting('xfhoev_settings');
		$this->read();
	}
	
	/**
	 * Get Instance
	 * @return ilFhoevEventSettings
	 */
	public static function getInstance()
	{
		if(self::$instance)
		{
			return self::$instance;
		}
		return self::$instance = new self();
	}
	
	/**
	 * Get storage
	 * @return ilSetting
	 */
	protected function getStorage()
	{
		return $this->storage;
	}

	/**
	 * 
	 * @return typeCheck if member handling is active
	 */
	public function isActive()
	{
		return $this->active;
	}
	
	/**
	 * Set active
	 * @param type $a_status
	 */
	public function setActive($a_status)
	{
		$this->active = $a_status;
	}
	
	/**
	 * Get dtpl id
	 * @return type
	 */
	public function getTemplateId()
	{
		return $this->template_id;
	}
	
	/**
	 * Set tremplate id
	 * @param type $a_dtpl_id
	 */
	public function setTemplateId($a_dtpl_id)
	{
		$this->template_id = $a_dtpl_id;
	}
	
	/**
	 * Save settings
	 */
	public function save()
	{
		$this->getStorage()->set('active', $this->isActive() ? 1 : 0);
		$this->getStorage()->set('dtpl_id', $this->getTemplateId());
	}
	
	/**
	 * Read settings
	 */
	public function read()
	{
		$this->setActive($this->getStorage()->get('active'), $this->isActive());
		$this->setTemplateId($this->getStorage()->get('dtpl_id'), $this->getTemplateId());
	}
}
?>