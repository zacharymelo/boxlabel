<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    boxlabel_note.php
 * \ingroup boxlabel
 * \brief   Notes tab for BoxLabel object
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
dol_include_once('/boxlabel/class/boxlabel.class.php');
dol_include_once('/boxlabel/lib/boxlabel.lib.php');

$langs->loadLangs(array('boxlabel@boxlabel', 'companies'));

$id     = GETPOST('id', 'int');
$ref    = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');

$object = new BoxLabel($db);
if ($id > 0 || $ref) {
	$result = $object->fetch($id, $ref);
	if ($result <= 0) {
		dol_print_error($db, $object->error);
		exit;
	}
}

// Access control
restrictedArea($user, 'boxlabel', $object->id, 'box_label', 'boxlabel', '', 'rowid');

$permissionnote = $user->hasRight('boxlabel', 'boxlabel', 'write');

// Actions
include DOL_DOCUMENT_ROOT.'/core/actions_setnotes.inc.php';


/*
 * VIEW
 */

llxHeader('', $object->ref.' - '.$langs->trans('Notes'), '');

$head = boxlabel_prepare_head($object);
print dol_get_fiche_head($head, 'note', $langs->trans('BoxLabel'), -1, 'mrp');

$linkback = '<a href="'.dol_buildpath('/boxlabel/boxlabel_list.php', 1).'">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'id', $linkback, 1, 'rowid', 'ref');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

include DOL_DOCUMENT_ROOT.'/core/tpl/notes.tpl.php';

print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
