<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    class/actions_boxlabel.class.php
 * \ingroup boxlabel
 * \brief   Hook actions class for BoxLabel module
 */

/**
 * Class ActionsBoxlabel — handles hook callbacks for element registration and MO integration
 */
class ActionsBoxlabel
{
	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var array */
	public $results = array();

	/** @var string */
	public $resprints = '';


	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Register element properties for BoxLabel objects.
	 * Called by Dolibarr when resolving element types for linked objects.
	 *
	 * @param  array          $parameters Hook parameters
	 * @param  CommonObject   $object     Current object
	 * @param  string         $action     Current action
	 * @param  HookManager    $hookmanager Hook manager
	 * @return int                        0=OK
	 */
	public function getElementProperties($parameters, &$object, &$action, $hookmanager)
	{
		$elementType = isset($parameters['elementType']) ? $parameters['elementType'] : '';

		if ($elementType === 'boxlabel' || $elementType === 'boxlabel_boxlabel') {
			$this->results = array(
				'module'        => 'boxlabel',
				'element'       => 'boxlabel',
				'table_element' => 'box_label',
				'subelement'    => 'boxlabel',
				'classpath'     => 'boxlabel/class',
				'classfile'     => 'boxlabel',
				'classname'     => 'BoxLabel',
			);
		}

		return 0;
	}

	/**
	 * Show link to BoxLabel in the "Link to..." dropdown on other object cards
	 *
	 * @param  array          $parameters Hook parameters
	 * @param  CommonObject   $object     Current object
	 * @param  string         $action     Current action
	 * @param  HookManager    $hookmanager Hook manager
	 * @return int                        0=OK
	 */
	public function showLinkToObjectBlock($parameters, &$object, &$action, $hookmanager)
	{
		global $user;

		if (!isModEnabled('boxlabel')) {
			return 0;
		}

		$this->results = array();

		return 0;
	}

	/**
	 * Inject form elements on hooked pages
	 *
	 * @param  array          $parameters Hook parameters
	 * @param  CommonObject   $object     Current object
	 * @param  string         $action     Current action
	 * @param  HookManager    $hookmanager Hook manager
	 * @return int                        0=OK
	 */
	public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		if (!isModEnabled('boxlabel')) {
			return 0;
		}

		return 0;
	}

	/**
	 * Handle form submissions from hooked pages
	 *
	 * @param  array          $parameters Hook parameters
	 * @param  CommonObject   $object     Current object
	 * @param  string         $action     Current action
	 * @param  HookManager    $hookmanager Hook manager
	 * @return int                        0=OK
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		if (!isModEnabled('boxlabel')) {
			return 0;
		}

		return 0;
	}
}
