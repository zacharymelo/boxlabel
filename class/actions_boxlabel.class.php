<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    class/actions_boxlabel.class.php
 * \ingroup boxlabel
 * \brief   Hook actions class for BoxLabel module
 */

/**
 * Class ActionsBoxlabel — handles hook callbacks for element registration,
 * MO integration, and per-product auto-label settings on product cards
 */
class ActionsBoxlabel
{
	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var array */
	public $errors = array();

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
	 * Inject form elements on hooked pages.
	 * On productcard: renders the "Auto-generate box labels" checkbox.
	 *
	 * @param  array          $parameters Hook parameters
	 * @param  CommonObject   $object     Current object
	 * @param  string         $action     Current action
	 * @param  HookManager    $hookmanager Hook manager
	 * @return int                        0=OK
	 */
	public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs, $user;

		if (!isModEnabled('boxlabel')) {
			return 0;
		}

		// Only inject on product cards
		if (!isset($object->element) || $object->element !== 'product') {
			return 0;
		}
		if (empty($object->id) || $object->id <= 0) {
			return 0;
		}

		$langs->load('boxlabel@boxlabel');

		// Query current auto-label setting for this product
		$auto_label = 0;
		$sql = "SELECT auto_label FROM ".MAIN_DB_PREFIX."boxlabel_product_auto";
		$sql .= " WHERE fk_product = ".((int) $object->id);
		$sql .= " AND entity = ".((int) $conf->entity);
		$sql .= " LIMIT 1";

		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			$auto_label = (int) $obj->auto_label;
		}

		// Render on the product card
		if ($action === 'edit' || $action === 'create') {
			// Edit mode — show checkbox
			$checked = $auto_label ? ' checked' : '';
			$this->resprints = '<tr><td>'.$langs->trans('AutoLabelProduct').'</td><td>';
			$this->resprints .= '<input type="checkbox" name="boxlabel_auto_label" value="1"'.$checked.'>';
			$this->resprints .= ' <span class="opacitymedium small">'.$langs->trans('AutoLabelProductDesc').'</span>';
			$this->resprints .= '</td></tr>';
		} else {
			// View mode — show status
			if ($auto_label) {
				$status_html = '<span class="badge badge-status4">'.$langs->trans('AutoLabelEnabled').'</span>';
			} else {
				$status_html = '<span class="opacitymedium">'.$langs->trans('AutoLabelNotSet').'</span>';
			}
			$this->resprints = '<tr><td>'.$langs->trans('AutoLabelProduct').'</td><td>';
			$this->resprints .= $status_html;
			$this->resprints .= '</td></tr>';
		}

		return 0;
	}

	/**
	 * Handle form submissions from hooked pages.
	 * On productcard update: save auto-label setting.
	 *
	 * @param  array          $parameters Hook parameters
	 * @param  CommonObject   $object     Current object
	 * @param  string         $action     Current action
	 * @param  HookManager    $hookmanager Hook manager
	 * @return int                        0=OK
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user;

		if (!isModEnabled('boxlabel')) {
			return 0;
		}

		// Only handle product card updates
		if (!isset($object->element) || $object->element !== 'product') {
			return 0;
		}
		if ($action !== 'update' || empty($object->id)) {
			return 0;
		}
		if (!$user->hasRight('boxlabel', 'boxlabel', 'write')) {
			return 0;
		}

		$auto_label = GETPOSTINT('boxlabel_auto_label');

		// Atomic: delete existing, then insert if checked
		$sql_del = "DELETE FROM ".MAIN_DB_PREFIX."boxlabel_product_auto";
		$sql_del .= " WHERE fk_product = ".((int) $object->id);
		$sql_del .= " AND entity = ".((int) $conf->entity);
		$this->db->query($sql_del);

		if ($auto_label > 0) {
			$now = dol_now();
			$sql_ins = "INSERT INTO ".MAIN_DB_PREFIX."boxlabel_product_auto";
			$sql_ins .= " (fk_product, entity, auto_label, date_creation, fk_user_creat)";
			$sql_ins .= " VALUES (";
			$sql_ins .= ((int) $object->id);
			$sql_ins .= ", ".((int) $conf->entity);
			$sql_ins .= ", 1";
			$sql_ins .= ", '".$this->db->idate($now)."'";
			$sql_ins .= ", ".((int) $user->id);
			$sql_ins .= ")";
			$this->db->query($sql_ins);
		}

		return 0;
	}
}
