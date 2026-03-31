<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    ajax/fetch_serials.php
 * \ingroup boxlabel
 * \brief   AJAX endpoint returning in-stock serials for a product + product metadata
 *
 * GET parameters:
 *   fk_product (int) — product ID
 *
 * Returns JSON:
 *   { "product": {"label":"...", "description":"..."}, "serials": [{lot_id, batch, manufacturing_date, mfg_day, mfg_month, mfg_year, total_qty}, ...] }
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))      { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php"))    { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists("../../../../main.inc.php")) { $res = @include "../../../../main.inc.php"; }
if (!$res) { http_response_code(500); exit; }

header('Content-Type: application/json; charset=utf-8');

// Permission check
if (empty($user->id) || !$user->hasRight('boxlabel', 'boxlabel', 'read')) {
	http_response_code(403);
	echo json_encode(array('error' => 'Permission denied'));
	exit;
}

$fk_product = GETPOSTINT('fk_product');
$fk_mo = GETPOSTINT('fk_mo');

if ($fk_product <= 0) {
	echo json_encode(array('product' => null, 'serials' => array()));
	exit;
}

$result = array('product' => null, 'serials' => array());

// Fetch product info
$sql_prod = "SELECT p.label, p.description";
$sql_prod .= " FROM ".MAIN_DB_PREFIX."product as p";
$sql_prod .= " WHERE p.rowid = ".((int) $fk_product);
$sql_prod .= " AND p.entity IN (".getEntity('product').")";

$res_prod = $db->query($sql_prod);
if ($res_prod) {
	$obj_prod = $db->fetch_object($res_prod);
	if ($obj_prod) {
		$result['product'] = array(
			'label' => $obj_prod->label,
			'description' => dol_string_nohtmltag($obj_prod->description, 1),
		);
	}
}

// Fetch in-stock serials (qty > 0 across all warehouses)
// Coalesce manufacturing_date from multiple sources:
//   1. llx_product_lot.manufacturing_date (explicit lot date)
//   2. llx_mrp_production produced line date (when the MO produced this batch)
//   3. Earliest stock movement date for this batch (first time it entered inventory)
$sql = "SELECT pl.rowid as lot_id, pl.batch, pl.manufacturing_date";
$sql .= ", SUM(pb.qty) as total_qty";
$sql .= ", (SELECT mp.date_creation FROM ".MAIN_DB_PREFIX."mrp_production as mp";
$sql .= "   WHERE mp.batch = pl.batch AND mp.fk_product = pl.fk_product";
$sql .= "   AND mp.role = 'produced' ORDER BY mp.date_creation DESC LIMIT 1) as mo_prod_date";
$sql .= ", (SELECT sm.datem FROM ".MAIN_DB_PREFIX."stock_mouvement as sm";
$sql .= "   WHERE sm.batch = pl.batch AND sm.fk_product = pl.fk_product";
$sql .= "   AND sm.value > 0 ORDER BY sm.datem ASC LIMIT 1) as first_stock_date";
$sql .= " FROM ".MAIN_DB_PREFIX."product_lot as pl";
$sql .= " JOIN ".MAIN_DB_PREFIX."product_batch as pb ON pb.batch = pl.batch";
$sql .= " JOIN ".MAIN_DB_PREFIX."product_stock as ps ON ps.rowid = pb.fk_product_stock AND ps.fk_product = pl.fk_product";
// When MO is specified, only show serials produced by that MO
if ($fk_mo > 0) {
	$sql .= " JOIN ".MAIN_DB_PREFIX."mrp_production as mop ON mop.batch = pl.batch AND mop.fk_product = pl.fk_product AND mop.fk_mo = ".((int) $fk_mo)." AND mop.role = 'produced'";
}
$sql .= " WHERE pl.fk_product = ".((int) $fk_product);
$sql .= " AND pl.entity IN (".getEntity('productlot').")";
$sql .= " GROUP BY pl.rowid, pl.batch, pl.manufacturing_date";
$sql .= " HAVING SUM(pb.qty) > 0";
$sql .= " ORDER BY pl.batch";

$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$mfg_day = '';
		$mfg_month = '';
		$mfg_year = '';
		$mfg_display = '';

		// Coalesce: lot date → MO production date → first stock movement date
		$raw_date = $obj->manufacturing_date;
		if (empty($raw_date) && !empty($obj->mo_prod_date)) {
			$raw_date = $obj->mo_prod_date;
		}
		if (empty($raw_date) && !empty($obj->first_stock_date)) {
			$raw_date = $obj->first_stock_date;
		}

		if (!empty($raw_date)) {
			$mfg_ts = $db->jdate($raw_date);
			$mfg_display = dol_print_date($mfg_ts, 'day');
			$mfg_day = dol_print_date($mfg_ts, '%d');
			$mfg_month = dol_print_date($mfg_ts, '%m');
			$mfg_year = dol_print_date($mfg_ts, '%Y');
		}

		$result['serials'][] = array(
			'lot_id' => (int) $obj->lot_id,
			'batch' => $obj->batch,
			'manufacturing_date' => $mfg_display,
			'mfg_day' => $mfg_day,
			'mfg_month' => $mfg_month,
			'mfg_year' => $mfg_year,
			'total_qty' => (float) $obj->total_qty,
		);
	}
	$db->free($resql);
}

echo json_encode($result);
