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

	// Label header settings
	dolibarr_set_const($db, 'BOXLABEL_HEADER_TITLE', GETPOST('BOXLABEL_HEADER_TITLE', 'nohtml'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'BOXLABEL_HEADER_SUBTITLE', GETPOST('BOXLABEL_HEADER_SUBTITLE', 'nohtml'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'BOXLABEL_HEADER_LOGO', GETPOST('BOXLABEL_HEADER_LOGO', 'nohtml'), 'chaine', 0, '', $conf->entity);

	// Retention days
	$retDays = GETPOSTINT('BOXLABEL_RETENTION_DAYS');
	if ($retDays > 0) {
		dolibarr_set_const($db, 'BOXLABEL_RETENTION_DAYS', $retDays, 'chaine', 0, '', $conf->entity);
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

// Label Header Appearance
print '<tr class="liste_titre"><td colspan="3">'.$langs->trans('LabelHeaderSettings').'</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('LabelHeaderTitle').'</td>';
print '<td>';
$headerTitle = getDolGlobalString('BOXLABEL_HEADER_TITLE', $mysoc->name);
print '<input type="text" name="BOXLABEL_HEADER_TITLE" value="'.dol_escape_htmltag($headerTitle).'" class="maxwidth400">';
print '</td>';
print '<td class="opacitymedium">'.$langs->trans('LabelHeaderTitleDesc').'</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('LabelHeaderSubtitle').'</td>';
print '<td>';
$headerSubtitle = getDolGlobalString('BOXLABEL_HEADER_SUBTITLE', '');
print '<input type="text" name="BOXLABEL_HEADER_SUBTITLE" value="'.dol_escape_htmltag($headerSubtitle).'" class="maxwidth400">';
print '</td>';
print '<td class="opacitymedium">'.$langs->trans('LabelHeaderSubtitleDesc').'</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('LabelHeaderLogo').'</td>';
print '<td>';
$headerLogo = getDolGlobalString('BOXLABEL_HEADER_LOGO', '');
print '<select name="BOXLABEL_HEADER_LOGO" class="maxwidth300">';
print '<option value="">'.$langs->trans('CompanyLogo').' ('.$langs->trans('Default').')</option>';
print '<option value="none"'.($headerLogo === 'none' ? ' selected' : '').'>'.$langs->trans('NoLogo').'</option>';
// List available logos from mycompany dir
$logoDir = $conf->mycompany->dir_output.'/logos';
if (is_dir($logoDir)) {
	$logoFiles = scandir($logoDir);
	foreach ($logoFiles as $lf) {
		if (preg_match('/\.(png|jpg|jpeg|gif|svg)$/i', $lf)) {
			$selected = ($headerLogo === $lf) ? ' selected' : '';
			print '<option value="'.dol_escape_htmltag($lf).'"'.$selected.'>'.dol_escape_htmltag($lf).'</option>';
		}
	}
}
print '</select>';
print '</td>';
print '<td class="opacitymedium">'.$langs->trans('LabelHeaderLogoDesc').'</td></tr>';

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

// Auto-generate labels on MO production
print '<tr class="liste_titre"><td colspan="3">'.$langs->trans('AutoGenerateLabels').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('AutoGenerateLabels').'</td>';
print '<td>';
print ajax_constantonoff('BOXLABEL_AUTO_GENERATE');
print '</td>';
print '<td class="opacitymedium">'.$langs->trans('AutoGenerateLabelsDesc').'</td></tr>';

// Label Archiving
print '<tr class="liste_titre"><td colspan="3">'.$langs->trans('LabelArchiving').'</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('AutoArchiveOnShipment').'</td>';
print '<td>';
print ajax_constantonoff('BOXLABEL_AUTO_ARCHIVE');
print '</td>';
print '<td class="opacitymedium">'.$langs->trans('AutoArchiveOnShipmentDesc').'</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('RetentionDays').'</td>';
print '<td>';
$retentionDays = getDolGlobalInt('BOXLABEL_RETENTION_DAYS', 90);
print '<input type="number" name="BOXLABEL_RETENTION_DAYS" value="'.$retentionDays.'" class="maxwidth75" min="1"> '.$langs->trans('days');
print '</td>';
print '<td class="opacitymedium">'.$langs->trans('RetentionDaysDesc').'</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('AutoDeleteAfterRetention').'</td>';
print '<td>';
print ajax_constantonoff('BOXLABEL_AUTO_DELETE');
print '</td>';
print '<td class="opacitymedium">'.$langs->trans('AutoDeleteAfterRetentionDesc').'</td></tr>';

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
