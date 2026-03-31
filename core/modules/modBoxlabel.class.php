<?php
/* Copyright (C) 2026 DPG Supply
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    core/modules/modBoxlabel.class.php
 * \ingroup boxlabel
 * \brief   Description and activation file for Box Label module
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module BoxLabel
 */
class modBoxlabel extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		$this->numero = 500010;

		$this->family = "products";
		$this->module_position = '90';

		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Generate and print 4x6 box labels with product, batch, and serial information after manufacturing";
		$this->version = '1.4.1';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'mrp';

		$this->module_parts = array(
			'triggers' => 1,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'hooks' => array('data' => array('elementproperties', 'mocard', 'productcard'), 'entity' => '0'),
			'models' => 1,
		);

		$this->dirs = array(
			"/boxlabel/temp",
		);

		$this->config_page_url = array("setup.php@boxlabel");

		$this->hidden = false;
		$this->depends = array('modProduct', 'modMrp', 'modStock');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array("boxlabel@boxlabel");
		$this->phpmin = array(7, 0);
		$this->need_dolibarr_version = array(16, 0);
		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();

		// Constants
		$this->const = array(
			0 => array('BOXLABEL_ADDON', 'chaine', 'mod_boxlabel_standard', 'Numbering rule for box labels', 0),
			1 => array('BOXLABEL_ADDON_PDF', 'chaine', 'pdf_boxlabel_standard', 'PDF model for box labels', 0),
		);

		// Tabs on native object cards
		$this->tabs = array();
		$this->tabs[] = array('data' => 'mo@mrp:+boxlabel:BoxLabels,mrp,/boxlabel/class/boxlabel.class.php,countForMo:boxlabel@boxlabel:$user->hasRight(\'boxlabel\', \'boxlabel\', \'read\'):/boxlabel/mo_boxlabel.php?fk_mo=__ID__');
		$this->tabs[] = array('data' => 'product:+boxlabel_template:LabelTemplate:boxlabel@boxlabel:$user->hasRight(\'boxlabel\', \'boxlabel\', \'read\'):/boxlabel/product_template.php?id=__ID__');

		// Dictionaries
		$this->dictionaries = array();

		// Boxes / Widgets
		$this->boxes = array();

		// Cronjobs
		$this->cronjobs = array();

		// Permissions
		$this->rights = array();
		$this->rights_class = 'boxlabel';

		$r = 0;

		$r++;
		$this->rights[$r][0] = 500011;
		$this->rights[$r][1] = 'Read box labels';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'boxlabel';
		$this->rights[$r][5] = 'read';

		$r++;
		$this->rights[$r][0] = 500012;
		$this->rights[$r][1] = 'Create and edit box labels';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'boxlabel';
		$this->rights[$r][5] = 'write';

		$r++;
		$this->rights[$r][0] = 500013;
		$this->rights[$r][1] = 'Delete box labels';
		$this->rights[$r][2] = 'd';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'boxlabel';
		$this->rights[$r][5] = 'delete';

		// Main menu entries — placed under MRP left menu
		$this->menu = array();
		$r = 0;

		// Box Labels header in MRP left menu (level 0 = section header)
		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=mrp',
			'type'     => 'left',
			'titre'    => 'BoxLabels',
			'prefix'   => img_picto('', 'mrp', 'class="paddingright pictofixedwidth"'),
			'mainmenu' => 'mrp',
			'leftmenu' => 'boxlabel',
			'url'      => '/boxlabel/boxlabel_list.php',
			'langs'    => 'boxlabel@boxlabel',
			'position' => 300,
			'enabled'  => 'isModEnabled("boxlabel")',
			'perms'    => '$user->hasRight("boxlabel", "boxlabel", "read")',
			'target'   => '',
			'user'     => 0,
		);
		$r++;

		// New Box Label under the header
		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=mrp,fk_leftmenu=boxlabel',
			'type'     => 'left',
			'titre'    => 'NewBoxLabel',
			'prefix'   => img_picto('', 'add', 'class="paddingright pictofixedwidth"'),
			'mainmenu' => 'mrp',
			'leftmenu' => 'boxlabel_new',
			'url'      => '/boxlabel/boxlabel_card.php?action=create',
			'langs'    => 'boxlabel@boxlabel',
			'position' => 310,
			'enabled'  => 'isModEnabled("boxlabel")',
			'perms'    => '$user->hasRight("boxlabel", "boxlabel", "write")',
			'target'   => '',
			'user'     => 0,
		);
	}

	/**
	 * Function called when module is enabled.
	 *
	 * @param  string $options Options
	 * @return int             1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		$result = $this->_load_tables('/boxlabel/sql/');
		if ($result < 0) {
			return -1;
		}

		$this->delete_menus();

		return $this->_init(array(), $options);
	}

	/**
	 * Function called when module is disabled.
	 *
	 * @param  string $options Options
	 * @return int             1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
