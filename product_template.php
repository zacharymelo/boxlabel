<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    product_template.php
 * \ingroup boxlabel
 * \brief   Per-product label template — configure which fields appear on box labels
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';

$langs->loadLangs(array('boxlabel@boxlabel', 'products'));

$id     = GETPOSTINT('id');
$ref    = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');

// Access control
restrictedArea($user, 'boxlabel', 0, '', 'boxlabel');

$permread  = $user->hasRight('boxlabel', 'boxlabel', 'read');
$permwrite = $user->hasRight('boxlabel', 'boxlabel', 'write');

// Fetch product
$product = new Product($db);
if ($id > 0 || !empty($ref)) {
	$result = $product->fetch($id, $ref);
	if ($result <= 0) {
		dol_print_error($db, $product->error);
		exit;
	}
	$id = $product->id; // Ensure id is set even when fetched by ref
}

// Build available fields from core product attributes + extrafields
// Show ALL possible fields (populated or not) so user can select what to display
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

$availableFields = array();

// Core product fields — always available for selection
$availableFields['weight']      = array('label' => 'FieldWeight',     'desc' => $langs->trans('FieldWeightDesc'),     'value' => (!empty($product->weight) && $product->weight > 0) ? $product->weight.' '.measuring_units_string($product->weight_units, 'weight', 0, 1) : '');
$availableFields['dimensions']  = array('label' => 'FieldDimensions', 'desc' => $langs->trans('FieldDimensionsDesc'), 'value' => '');
$availableFields['volume']      = array('label' => 'FieldVolume',     'desc' => $langs->trans('FieldVolumeDesc'),     'value' => (!empty($product->volume) && $product->volume > 0) ? $product->volume.' '.measuring_units_string($product->volume_units, 'volume', 0, 1) : '');
$availableFields['country']     = array('label' => 'FieldCountry',    'desc' => $langs->trans('FieldCountryDesc'),    'value' => '');
$availableFields['hs_code']     = array('label' => 'FieldHSCode',     'desc' => $langs->trans('FieldHSCodeDesc'),     'value' => $product->customcode);

// Build dimensions value
$dimParts = array();
if (!empty($product->length) && $product->length > 0) { $dimParts[] = $product->length; }
if (!empty($product->width) && $product->width > 0) { $dimParts[] = $product->width; }
if (!empty($product->height) && $product->height > 0) { $dimParts[] = $product->height; }
if (count($dimParts) > 0) {
	$dimUnit = (!empty($product->length_units)) ? ' '.measuring_units_string($product->length_units, 'size', 0, 1) : '';
	$availableFields['dimensions']['value'] = implode(' x ', $dimParts).$dimUnit;
}

// Country value
if (!empty($product->country_id) && $product->country_id > 0) {
	$countryLabel = getCountry($product->country_id, 'all', $db);
	if (is_object($countryLabel)) { $countryLabel = $countryLabel->label; } elseif (is_array($countryLabel)) { $countryLabel = isset($countryLabel['label']) ? $countryLabel['label'] : ''; }
	$availableFields['country']['value'] = $countryLabel;
}

// Product extrafields — show all defined extrafields for products
$extrafields_obj = new ExtraFields($db);
$extrafields_obj->fetch_name_optionals_label('product');
if (!empty($extrafields_obj->attributes['product']['label'])) {
	foreach ($extrafields_obj->attributes['product']['label'] as $attrname => $extralabel) {
		$val = isset($product->array_options['options_'.$attrname]) ? $product->array_options['options_'.$attrname] : '';
		$availableFields['extra_'.$attrname] = array('label' => $extralabel, 'desc' => $langs->trans('ExtraField'), 'value' => $val);
	}
}

// All valid field keys for save validation
$validFieldKeys = array_keys($availableFields);


/*
 * ACTIONS
 */

if ($action == 'save_template' && $permwrite) {
	$posted_fields = GETPOST('fields', 'array');

	// Filter against valid field keys
	$checked = array();
	if (is_array($posted_fields)) {
		foreach ($posted_fields as $f) {
			if (in_array($f, $validFieldKeys)) {
				$checked[] = $f;
			}
		}
	}

	$enabledStr = implode(',', $checked);

	// Atomic: delete then insert
	$sql_del = "DELETE FROM ".MAIN_DB_PREFIX."boxlabel_product_template";
	$sql_del .= " WHERE fk_product = ".((int) $product->id);
	$sql_del .= " AND entity = ".((int) $conf->entity);
	$db->query($sql_del);

	$now = dol_now();
	$sql_ins = "INSERT INTO ".MAIN_DB_PREFIX."boxlabel_product_template";
	$sql_ins .= " (fk_product, entity, enabled_fields, date_creation, fk_user_creat)";
	$sql_ins .= " VALUES (";
	$sql_ins .= ((int) $product->id);
	$sql_ins .= ", ".((int) $conf->entity);
	$sql_ins .= ", '".$db->escape($enabledStr)."'";
	$sql_ins .= ", '".$db->idate($now)."'";
	$sql_ins .= ", ".((int) $user->id);
	$sql_ins .= ")";
	$db->query($sql_ins);

	setEventMessages($langs->trans('LabelTemplateSaved'), null, 'mesgs');
	header("Location: ".$_SERVER['PHP_SELF'].'?id='.$product->id);
	exit;
}

// Copy template from another product
if ($action == 'copy_template' && $permwrite) {
	$source_product_id = GETPOSTINT('source_product_id');
	if ($source_product_id > 0 && $source_product_id != $product->id) {
		$sqlSrc = "SELECT enabled_fields FROM ".MAIN_DB_PREFIX."boxlabel_product_template";
		$sqlSrc .= " WHERE fk_product = ".((int) $source_product_id);
		$sqlSrc .= " AND entity = ".((int) $conf->entity);
		$sqlSrc .= " LIMIT 1";
		$resSrc = $db->query($sqlSrc);
		if ($resSrc && ($objSrc = $db->fetch_object($resSrc))) {
			// Delete current template
			$sql_del = "DELETE FROM ".MAIN_DB_PREFIX."boxlabel_product_template";
			$sql_del .= " WHERE fk_product = ".((int) $product->id);
			$sql_del .= " AND entity = ".((int) $conf->entity);
			$db->query($sql_del);

			// Insert copy
			$now = dol_now();
			$sql_ins = "INSERT INTO ".MAIN_DB_PREFIX."boxlabel_product_template";
			$sql_ins .= " (fk_product, entity, enabled_fields, date_creation, fk_user_creat)";
			$sql_ins .= " VALUES (";
			$sql_ins .= ((int) $product->id);
			$sql_ins .= ", ".((int) $conf->entity);
			$sql_ins .= ", '".$db->escape($objSrc->enabled_fields)."'";
			$sql_ins .= ", '".$db->idate($now)."'";
			$sql_ins .= ", ".((int) $user->id);
			$sql_ins .= ")";
			$db->query($sql_ins);

			setEventMessages($langs->trans('LabelTemplateCopied'), null, 'mesgs');
		} else {
			setEventMessages($langs->trans('LabelTemplateNotFound'), null, 'errors');
		}
	}
	header("Location: ".$_SERVER['PHP_SELF'].'?id='.$product->id);
	exit;
}


/*
 * VIEW
 */

$form = new Form($db);

$title = $product->ref.' - '.$langs->trans('LabelTemplate');
$helpurl = '';
$shownav = 1;

llxHeader('', $title, $helpurl);

$head = product_prepare_head($product);
print dol_get_fiche_head($head, 'boxlabel_template', $langs->trans($product->isService() ? 'Service' : 'Product'), -1, $product->picto);

$linkback = '<a href="'.DOL_URL_ROOT.'/product/list.php?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($product, 'ref', $linkback, $shownav, 'ref');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

// Load current template — with parent inheritance for variants
$enabledFields = null; // null = no template
$inherited = false;
$parentRef = '';

// Check this product's own template
$sql = "SELECT enabled_fields FROM ".MAIN_DB_PREFIX."boxlabel_product_template";
$sql .= " WHERE fk_product = ".((int) $product->id);
$sql .= " AND entity = ".((int) $conf->entity);
$sql .= " LIMIT 1";

$resql = $db->query($sql);
if ($resql && ($obj = $db->fetch_object($resql))) {
	$enabledFields = array_filter(array_map('trim', explode(',', $obj->enabled_fields)));
}

// If no template on this product, check parent (variant inheritance)
if ($enabledFields === null) {
	$sqlParent = "SELECT pac.fk_product_parent, p.ref as parent_ref";
	$sqlParent .= " FROM ".MAIN_DB_PREFIX."product_attribute_combination as pac";
	$sqlParent .= " JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = pac.fk_product_parent";
	$sqlParent .= " WHERE pac.fk_product_child = ".((int) $product->id);
	$sqlParent .= " AND pac.entity IN (".getEntity('product').")";
	$sqlParent .= " LIMIT 1";
	$resParent = $db->query($sqlParent);
	if ($resParent && ($objParent = $db->fetch_object($resParent))) {
		$sqlTpl2 = "SELECT enabled_fields FROM ".MAIN_DB_PREFIX."boxlabel_product_template";
		$sqlTpl2 .= " WHERE fk_product = ".((int) $objParent->fk_product_parent);
		$sqlTpl2 .= " AND entity = ".((int) $conf->entity);
		$sqlTpl2 .= " LIMIT 1";
		$resTpl2 = $db->query($sqlTpl2);
		if ($resTpl2 && ($objTpl2 = $db->fetch_object($resTpl2))) {
			$enabledFields = array_filter(array_map('trim', explode(',', $objTpl2->enabled_fields)));
			$inherited = true;
			$parentRef = $objParent->parent_ref;
		}
	}
}

// If still no template (no parent either), default all to enabled
$allKeys = array_keys($availableFields);
if ($enabledFields === null) {
	$enabledFields = $allKeys;
}

print '<br>';

// Show inheritance notice for variants
if ($inherited && !empty($parentRef)) {
	print '<div class="info">';
	print img_picto('', 'info', 'class="pictofixedwidth"');
	print $langs->trans('LabelTemplateInherited', $parentRef);
	print ' <a href="'.$_SERVER['PHP_SELF'].'?id='.$product->id.'">'.$langs->trans('LabelTemplateOverride').'</a>';
	print '</div><br>';
}

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$product->id.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save_template">';

print '<table class="noborder centpercent">';

// Header
print '<tr class="liste_titre">';
print '<td class="center" style="width: 40px;">'.$langs->trans('BoxLabelEnabled').'</td>';
print '<td>'.$langs->trans('BoxLabelField').'</td>';
print '<td>'.$langs->trans('Description').'</td>';
print '</tr>';

// Always-on fields (greyed out, informational)
print '<tr class="oddeven">';
print '<td class="center"><input type="checkbox" checked disabled></td>';
print '<td><strong>'.$langs->trans('BoxLabelBatch').' / '.$langs->trans('BoxLabelSerialNumber').'</strong></td>';
print '<td class="opacitymedium">'.$langs->trans('LabelTemplateAlwaysShown').'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td class="center"><input type="checkbox" checked disabled></td>';
print '<td><strong>'.$langs->trans('ManufacturingDate').'</strong></td>';
print '<td class="opacitymedium">'.$langs->trans('LabelTemplateAlwaysShown').'</td>';
print '</tr>';

// Configurable fields — show current value if populated
foreach ($availableFields as $key => $info) {
	$checked = in_array($key, $enabledFields) ? ' checked' : '';
	$currentVal = !empty($info['value']) ? ' <span class="badge badge-status4">'.dol_escape_htmltag($info['value']).'</span>' : ' <span class="badge badge-status0">'.$langs->trans('BoxLabelEmpty').'</span>';
	print '<tr class="oddeven">';
	print '<td class="center"><input type="checkbox" name="fields[]" value="'.dol_escape_htmltag($key).'"'.$checked.'></td>';
	print '<td><strong>'.$langs->trans($info['label']).'</strong>'.$currentVal.'</td>';
	print '<td class="opacitymedium">'.$info['desc'].'</td>';
	print '</tr>';
}

print '</table>';

if ($permwrite) {
	print '<div class="center" style="margin-top: 10px;">';
	print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';
	print '</div>';
}

print '</form>';

// Copy template from another product
if ($permwrite) {
	print '<br>';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$product->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="copy_template">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('CopyTemplateFrom').'</td></tr>';
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans('CopyTemplateFromDesc').'</td>';
	print '<td>';
	print $form->select_produits('', 'source_product_id', '', 0, 0, -1, 2, '', 0, array(), 0, 'maxwidth400');
	print ' <input type="submit" class="button smallpaddingimp" value="'.$langs->trans('Copy').'">';
	print '</td>';
	print '</tr>';
	print '</table>';
	print '</form>';
}

print '</div>';
print dol_get_fiche_end();

llxFooter();
$db->close();
