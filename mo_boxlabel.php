<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    mo_boxlabel.php
 * \ingroup boxlabel
 * \brief   Tab page on Manufacturing Order cards — list and generate box labels
 */

$res = 0;
if (!$res && file_exists("../main.inc.php"))       { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php"))    { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/mrp/class/mo.class.php';
require_once DOL_DOCUMENT_ROOT.'/mrp/lib/mrp_mo.lib.php';
dol_include_once('/boxlabel/class/boxlabel.class.php');

$langs->loadLangs(array('boxlabel@boxlabel', 'mrp', 'products'));

$fk_mo  = GETPOSTINT('fk_mo');
$id     = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

// Support both ?fk_mo=X (from tab) and ?id=X
$mo_id = $fk_mo > 0 ? $fk_mo : $id;

$permread  = $user->hasRight('boxlabel', 'boxlabel', 'read');
$permwrite = $user->hasRight('boxlabel', 'boxlabel', 'write');

if (!$permread) {
	accessforbidden();
}

// Fetch the MO
$mo = new Mo($db);
if ($mo_id > 0) {
	$result = $mo->fetch($mo_id);
	if ($result <= 0) {
		dol_print_error($db, $mo->error);
		exit;
	}
}

/*
 * ACTIONS
 */

if ($action == 'generate' && $permwrite && GETPOST('confirm', 'alpha') == 'yes') {
	$boxlabel = new BoxLabel($db);
	$count = $boxlabel->generateFromMo($mo->id, $user);

	if ($count > 0) {
		// Auto-generate PDFs for all newly created labels
		$sql_new = "SELECT rowid FROM ".MAIN_DB_PREFIX."box_label";
		$sql_new .= " WHERE fk_mo = ".((int) $mo->id);
		$sql_new .= " AND status = 0";
		$sql_new .= " AND entity IN (".getEntity('boxlabel').")";
		$res_new = $db->query($sql_new);
		if ($res_new) {
			while ($obj_new = $db->fetch_object($res_new)) {
				$lbl = new BoxLabel($db);
				if ($lbl->fetch($obj_new->rowid) > 0) {
					$lbl->buildLabelPdf($langs);
					$lbl->validate($user, 1);
				}
			}
		}
		setEventMessages($langs->trans('BoxLabelCreatedFromMO', $count, $mo->ref), null, 'mesgs');
	} elseif ($count == 0) {
		setEventMessages($langs->trans('LabelsAlreadyExist'), null, 'warnings');
	} else {
		setEventMessages($boxlabel->error, $boxlabel->errors, 'errors');
	}

	header("Location: ".$_SERVER['PHP_SELF'].'?fk_mo='.$mo->id);
	exit;
}

// Generate PDF for a specific label
if ($action == 'builddoc' && $permread) {
	$label_id = GETPOSTINT('label_id');
	if ($label_id > 0) {
		$label = new BoxLabel($db);
		if ($label->fetch($label_id) > 0) {
			$result = $label->buildLabelPdf($langs);
			if ($result > 0) {
				if ($label->status == BoxLabel::STATUS_DRAFT) {
					$label->validate($user);
				}
				setEventMessages($langs->trans('LabelGeneratedSuccess'), null, 'mesgs');
			} else {
				setEventMessages($label->error, $label->errors, 'errors');
			}
		}
	}
	header("Location: ".$_SERVER['PHP_SELF'].'?fk_mo='.$mo->id);
	exit;
}

// Generate all PDFs for this MO
if ($action == 'builddoc_all' && $permread) {
	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."box_label";
	$sql .= " WHERE fk_mo = ".((int) $mo->id);
	$sql .= " AND entity IN (".getEntity('boxlabel').")";
	$sql .= " ORDER BY rowid";

	$resql = $db->query($sql);
	if ($resql) {
		$generated = 0;
		while ($obj = $db->fetch_object($resql)) {
			$label = new BoxLabel($db);
			if ($label->fetch($obj->rowid) > 0) {
				$result = $label->buildLabelPdf($langs);
				if ($result > 0) {
					if ($label->status == BoxLabel::STATUS_DRAFT) {
						$label->validate($user);
					}
					$generated++;
				}
			}
		}
		if ($generated > 0) {
			setEventMessages($langs->trans('LabelGeneratedSuccess').' ('.$generated.')', null, 'mesgs');
		}
	}
	header("Location: ".$_SERVER['PHP_SELF'].'?fk_mo='.$mo->id);
	exit;
}

// Print all labels — generate combined multi-page PDF and redirect to download
if ($action == 'printall' && $permread) {
	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."box_label";
	$sql .= " WHERE fk_mo = ".((int) $mo->id);
	$sql .= " AND entity IN (".getEntity('boxlabel').")";
	$sql .= " ORDER BY rowid";

	$labels = array();
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$label = new BoxLabel($db);
			if ($label->fetch($obj->rowid) > 0) {
				$labels[] = $label;
			}
		}
	}

	if (!empty($labels)) {
		$modelname = getDolGlobalString('BOXLABEL_ADDON_PDF', 'pdf_boxlabel_standard');
		$modelfile = DOL_DOCUMENT_ROOT.'/custom/boxlabel/core/modules/boxlabel/doc/'.$modelname.'.modules.php';
		require_once $modelfile;
		$pdfmodel = new $modelname($db);

		$filepath = $pdfmodel->write_file_multi($labels, $langs, $mo->ref);

		if (!empty($filepath) && file_exists($filepath)) {
			// Stream the file directly to the browser for printing
			header('Content-Type: application/pdf');
			header('Content-Disposition: inline; filename="'.basename($filepath).'"');
			header('Content-Length: '.filesize($filepath));
			header('Cache-Control: private, max-age=0, must-revalidate');
			readfile($filepath);
			exit;
		} else {
			setEventMessages('Failed to generate combined PDF', null, 'errors');
		}
	} else {
		setEventMessages($langs->trans('NoLabelsForThisMO'), null, 'warnings');
	}

	header("Location: ".$_SERVER['PHP_SELF'].'?fk_mo='.$mo->id);
	exit;
}


/*
 * VIEW
 */

$form = new Form($db);
$formfile = new FormFile($db);

$title = $mo->ref.' - '.$langs->trans('BoxLabels');
llxHeader('', $title, '');

// MO tabs
$head = moPrepareHead($mo);
print dol_get_fiche_head($head, 'boxlabel', $langs->trans('ManufacturingOrder'), -1, $mo->picto);

// MO banner
$linkback = '<a href="'.DOL_URL_ROOT.'/mrp/mo_list.php?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($mo, 'fk_mo', $linkback, 1, 'rowid', 'ref');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

// Confirmation dialog for label generation
if ($action == 'generate') {
	print $form->formconfirm(
		$_SERVER['PHP_SELF'].'?fk_mo='.$mo->id,
		$langs->trans('GenerateBoxLabels'),
		$langs->trans('ConfirmGenerateLabels'),
		'generate',
		'',
		0,
		1
	);
}

// Check for produced lines with batch numbers
$sql_check = "SELECT COUNT(rowid) as cnt FROM ".MAIN_DB_PREFIX."mrp_production";
$sql_check .= " WHERE fk_mo = ".((int) $mo->id);
$sql_check .= " AND role = 'produced'";
$sql_check .= " AND batch IS NOT NULL AND batch != ''";
$has_produced = 0;
$res_check = $db->query($sql_check);
if ($res_check) {
	$obj_check = $db->fetch_object($res_check);
	$has_produced = (int) $obj_check->cnt;
}

// Existing label count
$boxlabel_tmp = new BoxLabel($db);
$label_count = $boxlabel_tmp->countForMo($mo->id);

// Check how many produced serials still need labels (not yet generated)
$unlabeled = 0;
if ($has_produced > 0) {
	$sql_unlabeled = "SELECT COUNT(DISTINCT mp.batch) as cnt FROM ".MAIN_DB_PREFIX."mrp_production as mp";
	$sql_unlabeled .= " WHERE mp.fk_mo = ".((int) $mo->id);
	$sql_unlabeled .= " AND mp.role = 'produced'";
	$sql_unlabeled .= " AND mp.batch IS NOT NULL AND mp.batch != ''";
	$sql_unlabeled .= " AND mp.batch NOT IN (SELECT bl.serial_number FROM ".MAIN_DB_PREFIX."box_label as bl";
	$sql_unlabeled .= "   WHERE bl.fk_mo = ".((int) $mo->id);
	$sql_unlabeled .= "   AND bl.entity IN (".getEntity('boxlabel')."))";
	$res_unlabeled = $db->query($sql_unlabeled);
	if ($res_unlabeled) {
		$obj_unlabeled = $db->fetch_object($res_unlabeled);
		$unlabeled = (int) $obj_unlabeled->cnt;
	}
}

// Action buttons
print '<div class="tabsAction">';

if ($permwrite && $has_produced > 0 && $unlabeled > 0) {
	// Generate labels — still have unlabeled serials
	print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?fk_mo='.$mo->id.'&action=generate&token='.newToken().'">'.$langs->trans('GenerateBoxLabels').' ('.$unlabeled.' serials)</a>';
} elseif ($has_produced > 0 && $unlabeled == 0 && $label_count > 0) {
	// All serials already labeled — grey out
	print '<span class="butActionRefused classfortooltip" title="'.$langs->trans('LabelsAlreadyExist').'">'.$langs->trans('GenerateBoxLabels').'</span>';
}

if ($label_count > 0) {
	// Print all labels — combined multi-page PDF for immediate printing
	print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?fk_mo='.$mo->id.'&action=printall&token='.newToken().'" target="_blank">'.$langs->trans('PrintAllLabels').' ('.$label_count.')</a>';

	// Regenerate all PDFs for existing labels
	print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?fk_mo='.$mo->id.'&action=builddoc_all&token='.newToken().'">'.$langs->trans('RegenerateAllPDFs').' ('.$label_count.')</a>';
}

if ($has_produced == 0) {
	print '<span class="butActionRefused classfortooltip" title="'.$langs->trans('NoProducedLinesWithBatch').'">'.$langs->trans('GenerateBoxLabels').'</span>';
}
print '</div>';

// List existing labels for this MO
print '<br>';
print load_fiche_titre($langs->trans('BoxLabels'), '', '');

$sql = "SELECT t.rowid, t.ref, t.batch, t.serial_number, t.product_label";
$sql .= ", t.date_manufactured, t.qty_labels, t.status, t.last_main_doc";
$sql .= " FROM ".MAIN_DB_PREFIX."box_label as t";
$sql .= " WHERE t.fk_mo = ".((int) $mo->id);
$sql .= " AND t.entity IN (".getEntity('boxlabel').")";
$sql .= " ORDER BY t.rowid DESC";

$resql = $db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('Ref').'</td>';
	print '<td>'.$langs->trans('ProductLabel').'</td>';
	print '<td>'.$langs->trans('Batch').'</td>';
	print '<td>'.$langs->trans('SerialNumber').'</td>';
	print '<td class="center">'.$langs->trans('ManufacturingDate').'</td>';
	print '<td class="center">'.$langs->trans('Status').'</td>';
	print '<td class="center">'.$langs->trans('Actions').'</td>';
	print '</tr>';

	if ($num == 0) {
		print '<tr class="oddeven"><td colspan="8" class="opacitymedium">'.$langs->trans('NoLabelsForThisMO').'</td></tr>';
	}

	$i = 0;
	while ($i < $num) {
		$obj = $db->fetch_object($resql);

		$label = new BoxLabel($db);
		$label->id = $obj->rowid;
		$label->ref = $obj->ref;
		$label->status = $obj->status;

		print '<tr class="oddeven">';
		print '<td class="nowraponall">'.$label->getNomUrl(1).'</td>';
		print '<td class="tdoverflowmax200">'.dol_escape_htmltag($obj->product_label).'</td>';
		print '<td>'.dol_escape_htmltag($obj->batch).'</td>';
		print '<td>'.dol_escape_htmltag($obj->serial_number).'</td>';
		print '<td class="center">'.dol_print_date($db->jdate($obj->date_manufactured), 'day').'</td>';
		print '<td class="center">'.$label->getLibStatut(3).'</td>';
		print '<td class="center nowraponall">';

		// Generate/Regenerate PDF
		print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?fk_mo='.$mo->id.'&action=builddoc&label_id='.$obj->rowid.'&token='.newToken().'">';
		print img_picto($langs->trans($obj->status == BoxLabel::STATUS_GENERATED ? 'RegeneratePDF' : 'GeneratePDF'), 'pdf');
		print '</a>';

		// Download link if PDF exists
		if (!empty($obj->last_main_doc)) {
			$filepath = $conf->boxlabel->dir_output.'/'.$obj->last_main_doc;
			if (file_exists($filepath)) {
				$urldown = DOL_URL_ROOT.'/document.php?modulepart=boxlabel&file='.urlencode($obj->last_main_doc);
				print ' <a href="'.$urldown.'" target="_blank">';
				print img_picto($langs->trans('DownloadPDF'), 'download');
				print '</a>';
			}
		}

		print '</td>';
		print '</tr>';
		$i++;
	}

	print '</table>';
} else {
	dol_print_error($db);
}

print '</div>';
print dol_get_fiche_end();

llxFooter();
$db->close();
