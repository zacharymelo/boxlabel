<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    boxlabel_list.php
 * \ingroup boxlabel
 * \brief   List page for BoxLabel objects
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/boxlabel/class/boxlabel.class.php');

$langs->loadLangs(array('boxlabel@boxlabel', 'products', 'other'));

$action = GETPOST('action', 'aZ09');
$massaction = GETPOST('massaction', 'alpha');
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$offset = $limit * $page;

if (empty($sortfield)) {
	$sortfield = 't.rowid';
}
if (empty($sortorder)) {
	$sortorder = 'DESC';
}

// Filters
$search_ref       = GETPOST('search_ref', 'alpha');
$search_batch     = GETPOST('search_batch', 'alpha');
$search_serial    = GETPOST('search_serial', 'alpha');
$search_product   = GETPOST('search_product', 'alpha');
$search_status    = GETPOST('search_status', 'intcomma');

// Access control
restrictedArea($user, 'boxlabel', 0, '', 'boxlabel');

// Purge search criteria
if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$search_ref = '';
	$search_batch = '';
	$search_serial = '';
	$search_product = '';
	$search_status = '';
}


/*
 * VIEW
 */

$form = new Form($db);

$title = $langs->trans('BoxLabelList');
llxHeader('', $title, '');

// Build SQL
$sql = "SELECT t.rowid, t.ref, t.fk_product, t.fk_mo, t.batch, t.serial_number";
$sql .= ", t.product_label, t.date_manufactured, t.qty_labels, t.status, t.date_creation";
$sql .= ", p.ref as product_ref";
$sql .= " FROM ".MAIN_DB_PREFIX."box_label as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = t.fk_product";
$sql .= " WHERE t.entity IN (".getEntity('boxlabel').")";

if (!empty($search_ref)) {
	$sql .= " AND t.ref LIKE '%".$db->escape($search_ref)."%'";
}
if (!empty($search_batch)) {
	$sql .= " AND t.batch LIKE '%".$db->escape($search_batch)."%'";
}
if (!empty($search_serial)) {
	$sql .= " AND t.serial_number LIKE '%".$db->escape($search_serial)."%'";
}
if (!empty($search_product)) {
	$sql .= " AND (t.product_label LIKE '%".$db->escape($search_product)."%' OR p.ref LIKE '%".$db->escape($search_product)."%')";
}
if ($search_status !== '' && $search_status >= 0) {
	$sql .= " AND t.status = ".((int) $search_status);
}

// Count total
$nbtotalofrecords = 0;
$sql_count = preg_replace('/^SELECT .* FROM/', 'SELECT COUNT(t.rowid) as total FROM', $sql);
$sql_count = preg_replace('/ORDER BY.*$/', '', $sql_count);
$resql_count = $db->query($sql_count);
if ($resql_count) {
	$obj_count = $db->fetch_object($resql_count);
	$nbtotalofrecords = $obj_count->total;
}

$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}

$num = $db->num_rows($resql);

$param = '';
if (!empty($search_ref)) { $param .= '&search_ref='.urlencode($search_ref); }
if (!empty($search_batch)) { $param .= '&search_batch='.urlencode($search_batch); }
if (!empty($search_serial)) { $param .= '&search_serial='.urlencode($search_serial); }
if (!empty($search_product)) { $param .= '&search_product='.urlencode($search_product); }
if ($search_status !== '') { $param .= '&search_status='.urlencode($search_status); }

$newcardbutton = '';
if ($user->hasRight('boxlabel', 'boxlabel', 'write')) {
	$newcardbutton = dolGetButtonTitle($langs->trans('NewBoxLabel'), '', 'fa fa-plus-circle', dol_buildpath('/boxlabel/boxlabel_card.php', 1).'?action=create');
}

print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'mrp', 0, $newcardbutton, '', $limit);

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';

print '<div class="div-table-responsive">';
print '<table class="tagtable liste">';

// Header row
print '<tr class="liste_titre">';
print_liste_field_titre('Ref', $_SERVER['PHP_SELF'], 't.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Product', $_SERVER['PHP_SELF'], 'p.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('ProductLabel', $_SERVER['PHP_SELF'], 't.product_label', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('BoxLabelBatch', $_SERVER['PHP_SELF'], 't.batch', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('BoxLabelSerialNumber', $_SERVER['PHP_SELF'], 't.serial_number', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('ManufacturingDate', $_SERVER['PHP_SELF'], 't.date_manufactured', '', $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre('Status', $_SERVER['PHP_SELF'], 't.status', '', $param, '', $sortfield, $sortorder, 'center ');
print '</tr>';

// Filter row
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre"><input type="text" name="search_ref" class="maxwidth75" value="'.dol_escape_htmltag($search_ref).'"></td>';
print '<td class="liste_titre"><input type="text" name="search_product" class="maxwidth100" value="'.dol_escape_htmltag($search_product).'"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"><input type="text" name="search_batch" class="maxwidth100" value="'.dol_escape_htmltag($search_batch).'"></td>';
print '<td class="liste_titre"><input type="text" name="search_serial" class="maxwidth100" value="'.dol_escape_htmltag($search_serial).'"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre center">';
print $form->selectarray('search_status', array('0' => $langs->trans('BoxLabelDraft'), '1' => $langs->trans('BoxLabelGenerated')), $search_status, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100');
print '</td>';
print '<td class="liste_titre center">';
print '<input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans("Search"), 'search.png', '', '', 1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
print '<input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans("RemoveFilter"), 'searchclear.png', '', '', 1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
print '</td>';
print '</tr>';

// Data rows
$i = 0;
while ($i < min($num, $limit)) {
	$obj = $db->fetch_object($resql);

	$boxlabel = new BoxLabel($db);
	$boxlabel->id = $obj->rowid;
	$boxlabel->ref = $obj->ref;
	$boxlabel->status = $obj->status;

	print '<tr class="oddeven">';

	// Ref
	print '<td class="nowraponall">'.$boxlabel->getNomUrl(1).'</td>';

	// Product ref
	print '<td class="tdoverflowmax150">';
	if ($obj->fk_product > 0) {
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		$prod = new Product($db);
		$prod->id = $obj->fk_product;
		$prod->ref = $obj->product_ref;
		print $prod->getNomUrl(1);
	}
	print '</td>';

	// Product label
	print '<td class="tdoverflowmax200">'.dol_escape_htmltag($obj->product_label).'</td>';

	// Batch
	print '<td class="tdoverflowmax100">'.dol_escape_htmltag($obj->batch).'</td>';

	// Serial
	print '<td class="tdoverflowmax100">'.dol_escape_htmltag($obj->serial_number).'</td>';

	// Date
	print '<td class="center">'.dol_print_date($db->jdate($obj->date_manufactured), 'day').'</td>';

	// Status
	print '<td class="center">'.$boxlabel->getLibStatut(5).'</td>';

	print '</tr>';
	$i++;
}

if ($num == 0) {
	print '<tr class="oddeven"><td colspan="7" class="opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr>';
}

print '</table>';
print '</div>';
print '</form>';

llxFooter();
$db->close();
