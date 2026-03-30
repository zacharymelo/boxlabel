<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    core/modules/boxlabel/modules_boxlabel.php
 * \ingroup boxlabel
 * \brief   Abstract parent classes for numbering and PDF models
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commondocgenerator.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/commonnumrefgenerator.class.php';


/**
 * Parent class for Box Label PDF document templates
 */
abstract class ModelePDFBoxLabel extends CommonDocGenerator
{
	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * Return list of active PDF generation modules
	 *
	 * @param  DoliDB $db                Database handler
	 * @param  int    $maxfilenamelength Max length of value to show
	 * @return array                     List of templates
	 */
	public static function liste_modeles($db, $maxfilenamelength = 0)
	{
		// phpcs:enable
		include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
		return getListOfModels($db, 'boxlabel', $maxfilenamelength);
	}

	/**
	 * Build PDF to disk
	 *
	 * @param  BoxLabel  $object          Object to generate
	 * @param  Translate $outputlangs     Lang output object
	 * @param  string    $srctemplatepath Template path
	 * @param  int       $hidedetails     Do not show line details
	 * @param  int       $hidedesc        Do not show desc
	 * @param  int       $hideref         Do not show ref
	 * @return int                         1=OK, 0=KO
	 */
	abstract public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0);
}


/**
 * Parent class for Box Label numbering models
 */
abstract class ModeleNumRefBoxlabel extends CommonNumRefGenerator
{
	/**
	 * Return next free value
	 *
	 * @param  Societe|string   $objsoc  Thirdparty object
	 * @param  BoxLabel|string  $object  Object we need next value for
	 * @return string|int                Next value if OK, <=0 if KO
	 */
	abstract public function getNextValue($objsoc = '', $object = '');

	/**
	 * Return an example of numbering
	 *
	 * @return string Example
	 */
	abstract public function getExample();
}
