<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    core/triggers/interface_99_modBoxlabel_BoxlabelTrigger.class.php
 * \ingroup boxlabel
 * \brief   Trigger class for BoxLabel module
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Class InterfaceBoxlabelTrigger — handles trigger events for BoxLabel module
 */
class InterfaceBoxlabelTrigger extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = 'products';
		$this->description = 'Triggers for BoxLabel module';
		$this->version = '1.0.0';
		$this->picto = 'mrp';
	}

	/**
	 * Return name of trigger file
	 *
	 * @return string Name of trigger
	 */
	public function getName()
	{
		return 'BoxlabelTrigger';
	}

	/**
	 * Return description of trigger file
	 *
	 * @return string Description
	 */
	public function getDesc()
	{
		return 'Triggers for BoxLabel module';
	}

	/**
	 * Function called when a Dolibarr business event is done
	 *
	 * @param  string    $action Event code
	 * @param  object    $object Object affected
	 * @param  User      $user   User performing action
	 * @param  Translate $langs  Language object
	 * @param  Conf      $conf   Configuration object
	 * @return int               0=OK, <0=KO
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('boxlabel')) {
			return 0;
		}

		// Handle own object triggers
		switch ($action) {
			case 'BOXLABEL_BOXLABEL_CREATE':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;

			case 'BOXLABEL_BOXLABEL_MODIFY':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;

			case 'BOXLABEL_BOXLABEL_DELETE':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;

			case 'BOXLABEL_BOXLABEL_VALIDATE':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;
		}

		return 0;
	}
}
