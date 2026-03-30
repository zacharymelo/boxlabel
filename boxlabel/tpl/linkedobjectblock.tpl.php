<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    boxlabel/tpl/linkedobjectblock.tpl.php
 * \ingroup boxlabel
 * \brief   Template for linked object block rendering of BoxLabel records
 */

if (empty($conf) || !is_object($conf)) { print "Error"; exit(1); }
print "<!-- BEGIN PHP TEMPLATE boxlabel/boxlabel/tpl/linkedobjectblock.tpl.php -->\n";
$langs->load("boxlabel@boxlabel");

$ilink = 0;
foreach ($linkedObjectBlock as $key => $objectlink) {
	$ilink++;
	$trclass = 'oddeven';
	if ($ilink == count($linkedObjectBlock) && empty($noMoreLinkedObjectBlockAfter) && count($linkedObjectBlock) <= 1) {
		$trclass .= ' liste_sub_total';
	}
	print '<tr class="'.$trclass.'">';
	print '<td class="linkedcol-element tdoverflowmax100">'.$langs->trans("BoxLabel").'</td>';
	print '<td class="linkedcol-name tdoverflowmax150">'.$objectlink->getNomUrl(1).'</td>';
	print '<td class="linkedcol-ref tdoverflowmax150">'.dol_escape_htmltag($objectlink->serial_number).'</td>';
	print '<td class="linkedcol-date center">'.dol_print_date($objectlink->date_creation, 'day').'</td>';
	print '<td class="linkedcol-amount right"></td>';
	print '<td class="linkedcol-statut right">'.$objectlink->getLibStatut(3).'</td>';
	print '<td class="linkedcol-action right"><a class="reposition" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=dellink&token='.newToken().'&dellinkid='.$key.'">'.img_picto($langs->transnoentitiesnoconv("RemoveLink"), 'unlink').'</a></td>';
	print "</tr>\n";
}
print "<!-- END PHP TEMPLATE -->\n";
