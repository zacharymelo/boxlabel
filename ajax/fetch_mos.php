<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    ajax/fetch_mos.php
 * \ingroup boxlabel
 * \brief   AJAX endpoint returning Manufacturing Orders that produced a given product
 *
 * GET parameters:
 *   fk_product (int) — product ID
 *
 * Returns JSON array:
 *   [{rowid, ref}, ...]
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
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

if ($fk_product <= 0) {
	echo json_encode(array());
	exit;
}

$result = array();

// MOs that produced this product (IN_PROGRESS=2 or PRODUCED=3)
$sql = "SELECT mo.rowid, mo.ref";
$sql .= " FROM ".MAIN_DB_PREFIX."mrp_mo as mo";
$sql .= " WHERE mo.fk_product = ".((int) $fk_product);
$sql .= " AND mo.status IN (2, 3)";
$sql .= " AND mo.entity IN (".getEntity('mo').")";
$sql .= " ORDER BY mo.ref DESC";

$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$result[] = array(
			'rowid' => (int) $obj->rowid,
			'ref' => $obj->ref,
		);
	}
	$db->free($resql);
}

echo json_encode($result);
