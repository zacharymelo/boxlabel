<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    lib/boxlabel.lib.php
 * \ingroup boxlabel
 * \brief   Library functions for boxlabel module
 */

/**
 * Prepare admin pages header (Setup tab)
 *
 * @return array Array of head tabs
 */
function boxlabel_admin_prepare_head()
{
	global $langs, $conf;
	$langs->load('boxlabel@boxlabel');

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/boxlabel/admin/setup.php', 1);
	$head[$h][1] = $langs->trans('Settings');
	$head[$h][2] = 'settings';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'boxlabel@boxlabel');

	return $head;
}

/**
 * Prepare head tabs for a BoxLabel card
 *
 * @param  BoxLabel $object Object
 * @return array            Array of head tabs
 */
function boxlabel_prepare_head($object)
{
	global $langs, $conf;
	$langs->load('boxlabel@boxlabel');

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/boxlabel/boxlabel_card.php', 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans('Card');
	$head[$h][2] = 'card';
	$h++;

	$head[$h][0] = dol_buildpath('/boxlabel/boxlabel_note.php', 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans('Notes');
	if (!empty($object->note_private) || !empty($object->note_public)) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">...</span>';
	}
	$head[$h][2] = 'note';
	$h++;

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'boxlabel@boxlabel');

	return $head;
}
