<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    core/modules/boxlabel/mod_boxlabel_standard.php
 * \ingroup boxlabel
 * \brief   Standard numbering rule for Box Labels
 */

require_once DOL_DOCUMENT_ROOT.'/custom/boxlabel/core/modules/boxlabel/modules_boxlabel.php';

/**
 * Standard numbering rule: BXL-YYYYMMDD-NNNN
 */
class mod_boxlabel_standard extends ModeleNumRefBoxlabel
{
	/** @var string Version */
	public $version = '1.0.0';

	/** @var string Name */
	public $name = 'standard';

	/** @var string Prefix */
	public $prefix = 'BXL';

	/**
	 * Return an example of numbering
	 *
	 * @return string Example
	 */
	public function getExample()
	{
		return $this->prefix.'-20260330-0001';
	}

	/**
	 * Return next free value
	 *
	 * @param  Societe|string   $objsoc  Thirdparty object
	 * @param  BoxLabel|string  $object  Object we need next value for
	 * @return string                    Next value
	 */
	public function getNextValue($objsoc = '', $object = '')
	{
		global $db, $conf;

		$ymd = dol_print_date(dol_now(), '%Y%m%d');
		$posidx = strlen($this->prefix) + 10; // BXL- = 4, YYYYMMDD = 8, - = 1, total prefix+date = 13, index starts at 14

		$sql = "SELECT MAX(CAST(SUBSTRING(ref FROM ".$posidx.") AS SIGNED)) as max_num";
		$sql .= " FROM ".MAIN_DB_PREFIX."box_label";
		$sql .= " WHERE ref LIKE '".$db->escape($this->prefix)."-".$ymd."-%'";
		$sql .= " AND entity = ".((int) $conf->entity);

		$max = 0;
		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			if ($obj) {
				$max = intval($obj->max_num);
			}
		}

		return $this->prefix.'-'.$ymd.'-'.sprintf('%04d', $max + 1);
	}
}
