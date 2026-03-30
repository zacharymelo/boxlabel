<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    admin/setup.php
 * \ingroup boxlabel
 * \brief   Box Label module setup page
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))     { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php"))   { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists("../../../../main.inc.php")){ $res = @include "../../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/boxlabel/lib/boxlabel.lib.php');

$langs->loadLangs(array('admin', 'boxlabel@boxlabel'));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

/*
 * ACTIONS
 */

if ($action == 'update') {
	$addon = GETPOST('BOXLABEL_ADDON', 'alpha');
	if (!empty($addon)) {
		dolibarr_set_const($db, 'BOXLABEL_ADDON', $addon, 'chaine', 0, '', $conf->entity);
	}

	$addon_pdf = GETPOST('BOXLABEL_ADDON_PDF', 'alpha');
	if (!empty($addon_pdf)) {
		dolibarr_set_const($db, 'BOXLABEL_ADDON_PDF', $addon_pdf, 'chaine', 0, '', $conf->entity);
	}

	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	header("Location: ".$_SERVER['PHP_SELF']);
	exit;
}

/*
 * VIEW
 */

llxHeader('', $langs->trans('BoxLabelSetup'), '');

$head = boxlabel_admin_prepare_head();
print dol_get_fiche_head($head, 'settings', $langs->trans('BoxLabelSetup'), -1, 'mrp');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';

// Numbering rule
print '<tr class="liste_titre"><td colspan="3">'.$langs->trans('NumberingRule').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('NumberingRule').'</td>';
print '<td>';
$current_addon = getDolGlobalString('BOXLABEL_ADDON', 'mod_boxlabel_standard');
print '<input type="text" name="BOXLABEL_ADDON" value="'.dol_escape_htmltag($current_addon).'" class="maxwidth300">';
print '</td>';
print '<td class="opacitymedium">mod_boxlabel_standard generates: BXL-YYYYMMDD-NNNN</td></tr>';

// PDF model
print '<tr class="liste_titre"><td colspan="3">'.$langs->trans('PDFModel').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PDFModel').'</td>';
print '<td>';
$current_pdf = getDolGlobalString('BOXLABEL_ADDON_PDF', 'pdf_boxlabel_standard');
print '<input type="text" name="BOXLABEL_ADDON_PDF" value="'.dol_escape_htmltag($current_pdf).'" class="maxwidth300">';
print '</td>';
print '<td class="opacitymedium">pdf_boxlabel_standard generates 4x6 inch labels</td></tr>';

// Debug mode
print '<tr class="liste_titre"><td colspan="3">'.$langs->trans('Other').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DebugMode').'</td>';
print '<td>';
print ajax_constantonoff('BOXLABEL_DEBUG_MODE');
print '</td>';
print '<td class="opacitymedium">'.$langs->trans('DebugModeDesc').'</td></tr>';

print '</table>';

print '<div class="center">';
print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';
print '</div>';

print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
