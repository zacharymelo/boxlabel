<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    boxlabel_card.php
 * \ingroup boxlabel
 * \brief   Card page for BoxLabel object — cascading FK-driven form
 */

$res = 0;
if (!$res && file_exists("../main.inc.php"))       { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php"))    { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/boxlabel/class/boxlabel.class.php');
dol_include_once('/boxlabel/lib/boxlabel.lib.php');

$langs->loadLangs(array('boxlabel@boxlabel', 'products', 'mrp', 'other'));

$id     = GETPOSTINT('id');
$ref    = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

$object = new BoxLabel($db);

// Extrafields
$extrafields = new ExtraFields($db);
$extrafields->fetch_name_optionals_label($object->table_element);

// Permissions
$permread   = $user->hasRight('boxlabel', 'boxlabel', 'read');
$permwrite  = $user->hasRight('boxlabel', 'boxlabel', 'write');
$permdelete = $user->hasRight('boxlabel', 'boxlabel', 'delete');

if (!$permread) {
	accessforbidden();
}

// Fetch object
if ($id > 0 || !empty($ref)) {
	$result = $object->fetch($id, $ref);
	if ($result <= 0) {
		dol_print_error($db, $object->error);
		exit;
	}
}


/*
 * ACTIONS
 */

if ($cancel) {
	if (!empty($backtopage)) {
		header("Location: ".$backtopage);
		exit;
	}
	if ($id > 0) {
		header("Location: ".$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	}
	$action = '';
}

// Create
if ($action == 'add' && $permwrite) {
	$object->fk_product          = GETPOSTINT('fk_product');
	$object->fk_mo               = GETPOSTINT('fk_mo');
	$object->fk_product_lot      = GETPOSTINT('fk_product_lot');
	$object->batch               = GETPOST('batch', 'alpha');
	$object->serial_number       = GETPOST('serial_number', 'alpha');
	$object->product_label       = GETPOST('product_label', 'alpha');
	$object->product_description = GETPOST('product_description', 'restricthtml');
	$object->date_manufactured   = dol_mktime(0, 0, 0, GETPOSTINT('date_manufacturedmonth'), GETPOSTINT('date_manufacturedday'), GETPOSTINT('date_manufacturedyear'));
	$object->qty_labels          = GETPOSTINT('qty_labels');
	$object->note_private        = GETPOST('note_private', 'restricthtml');
	$object->note_public         = GETPOST('note_public', 'restricthtml');

	$ret = $extrafields->setOptionalsFromPost(null, $object);

	// Auto-fill product label if not provided
	if (empty($object->product_label) && $object->fk_product > 0) {
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		$prod = new Product($db);
		if ($prod->fetch($object->fk_product) > 0) {
			$object->product_label = $prod->label;
			if (empty($object->product_description)) {
				$object->product_description = $prod->description;
			}
		}
	}

	if (empty($object->fk_product) || $object->fk_product <= 0) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Product')), null, 'errors');
		$action = 'create';
	} else {
		$result = $object->create($user);
		if ($result > 0) {
			if (!empty($backtopage)) {
				header("Location: ".$backtopage);
				exit;
			}
			header("Location: ".$_SERVER['PHP_SELF'].'?id='.$object->id);
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = 'create';
		}
	}
}

// Update
if ($action == 'update' && $permwrite) {
	$object->fk_product          = GETPOSTINT('fk_product');
	$object->fk_mo               = GETPOSTINT('fk_mo');
	$object->fk_product_lot      = GETPOSTINT('fk_product_lot');
	$object->batch               = GETPOST('batch', 'alpha');
	$object->serial_number       = GETPOST('serial_number', 'alpha');
	$object->product_label       = GETPOST('product_label', 'alpha');
	$object->product_description = GETPOST('product_description', 'restricthtml');
	$object->date_manufactured   = dol_mktime(0, 0, 0, GETPOSTINT('date_manufacturedmonth'), GETPOSTINT('date_manufacturedday'), GETPOSTINT('date_manufacturedyear'));
	$object->qty_labels          = GETPOSTINT('qty_labels');

	$ret = $extrafields->setOptionalsFromPost(null, $object);

	$result = $object->update($user);
	if ($result > 0) {
		header("Location: ".$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
		$action = 'edit';
	}
}

// Delete
if ($action == 'confirm_delete' && GETPOST('confirm', 'alpha') == 'yes' && $permdelete) {
	$result = $object->delete($user);
	if ($result > 0) {
		header("Location: boxlabel_list.php");
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

// Generate PDF
if ($action == 'builddoc' && $permread) {
	$modelname = GETPOST('model', 'alpha');
	if (empty($modelname)) {
		$modelname = getDolGlobalString('BOXLABEL_ADDON_PDF', 'pdf_boxlabel_standard');
	}

	$outputlangs = $langs;

	$result = $object->buildLabelPdf($outputlangs, $modelname);
	if ($result > 0) {
		if ($object->status == BoxLabel::STATUS_DRAFT) {
			$object->validate($user);
		}
		setEventMessages($langs->trans('LabelGeneratedSuccess'), null, 'mesgs');
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}

	header("Location: ".$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}


/*
 * VIEW
 */

$form = new Form($db);
$formfile = new FormFile($db);

$title = $langs->trans('BoxLabel');
llxHeader('', $title, '');


// Create mode
if ($action == 'create') {
	print load_fiche_titre($langs->trans('NewBoxLabel'), '', 'mrp');

	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" id="boxlabel_create_form">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	print '<input type="hidden" name="fk_product_lot" id="fk_product_lot" value="">';
	if (!empty($backtopage)) {
		print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">';
	}

	print dol_get_fiche_head(array(), '');

	print '<table class="border centpercent tableforfieldcreate">';

	// Product (existing select_produits with Select2)
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('Product').'</td><td>';
	print $form->select_produits(GETPOSTINT('fk_product'), 'fk_product', '', 0, 0, -1, 2, '', 0, array(), 0, 'maxwidth500');
	print '</td></tr>';

	// Serial/Batch — cascading select populated via AJAX
	print '<tr><td>'.$langs->trans('SerialNumber').'</td><td>';
	print '<select name="serial_number" id="serial_number" class="maxwidth400">';
	print '<option value="">'.dol_escape_htmltag($langs->trans('SelectProductFirst')).'</option>';
	print '</select>';
	print '</td></tr>';

	// Batch — auto-filled from serial, read-only
	print '<tr><td>'.$langs->trans('Batch').'</td><td>';
	print '<input type="text" name="batch" id="batch" class="maxwidth300" value="" readonly>';
	print '</td></tr>';

	// Manufacturing Order — cascading select populated via AJAX
	print '<tr><td>'.$langs->trans('ManufacturingOrder').'</td><td>';
	print '<select name="fk_mo" id="fk_mo" class="maxwidth400">';
	print '<option value="">'.dol_escape_htmltag($langs->trans('SelectProductFirst')).'</option>';
	print '</select>';
	print '</td></tr>';

	// Product Label — auto-filled, read-only
	print '<tr><td>'.$langs->trans('ProductLabel').'</td><td>';
	print '<input type="text" name="product_label" id="product_label" class="maxwidth500" value="" readonly>';
	print '</td></tr>';

	// Product Description — auto-filled, read-only
	print '<tr><td>'.$langs->trans('Description').'</td><td>';
	print '<textarea name="product_description" id="product_description" class="quatrevingtpercent" rows="3" readonly></textarea>';
	print '</td></tr>';

	// Manufacturing Date — auto-filled from serial, but user can override
	print '<tr><td>'.$langs->trans('ManufacturingDate').'</td><td>';
	print $form->selectDate('', 'date_manufactured', 0, 0, 1, 'create', 1, 1);
	print '</td></tr>';

	// Number of Copies
	print '<tr><td>'.$langs->trans('LabelQuantity').'</td><td>';
	print '<input type="number" name="qty_labels" class="maxwidth75" value="'.(GETPOSTINT('qty_labels') > 0 ? GETPOSTINT('qty_labels') : 1).'" min="1">';
	print '</td></tr>';

	// Extrafields
	print $object->showOptionals($extrafields, 'create');

	print '</table>';

	print dol_get_fiche_end();

	print '<div class="center">';
	print '<input type="submit" class="button" value="'.$langs->trans('Create').'">';
	print ' &nbsp; <input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'">';
	print '</div>';

	print '</form>';

	// Cascade JavaScript for create mode
	print "\n".'<script>'."\n";
	print _boxlabel_cascade_js(0, '', '', 0);
	print '</script>'."\n";

} elseif ($object->id > 0) {
	// View / Edit mode

	// Delete confirmation
	if ($action == 'delete') {
		print $form->formconfirm(
			$_SERVER['PHP_SELF'].'?id='.$object->id,
			$langs->trans('Delete'),
			$langs->trans('ConfirmDelete'),
			'confirm_delete',
			'',
			0,
			1
		);
	}

	$head = boxlabel_prepare_head($object);
	print dol_get_fiche_head($head, 'card', $langs->trans('BoxLabel'), -1, 'mrp');

	// Linkback
	$linkback = '<a href="'.dol_buildpath('/boxlabel/boxlabel_list.php', 1).'">'.$langs->trans('BackToList').'</a>';

	dol_banner_tab($object, 'id', $linkback, 1, 'rowid', 'ref');

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';

	if ($action == 'edit' && $permwrite) {
		// Edit form
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'" id="boxlabel_edit_form">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update">';
		print '<input type="hidden" name="fk_product_lot" id="fk_product_lot" value="'.((int) $object->fk_product_lot).'">';

		print '<table class="border centpercent tableforfield">';

		// Product
		print '<tr><td class="titlefield fieldrequired">'.$langs->trans('Product').'</td><td>';
		print $form->select_produits($object->fk_product, 'fk_product', '', 0, 0, -1, 2, '', 0, array(), 0, 'maxwidth500');
		print '</td></tr>';

		// Serial/Batch — select with pre-selection
		print '<tr><td>'.$langs->trans('SerialNumber').'</td><td>';
		print '<select name="serial_number" id="serial_number" class="maxwidth400">';
		print '<option value="">'.dol_escape_htmltag($langs->trans('SelectProductFirst')).'</option>';
		print '</select>';
		print '</td></tr>';

		// Batch — auto-filled
		print '<tr><td>'.$langs->trans('Batch').'</td><td>';
		print '<input type="text" name="batch" id="batch" class="maxwidth300" value="'.dol_escape_htmltag($object->batch).'" readonly>';
		print '</td></tr>';

		// MO — select with pre-selection
		print '<tr><td>'.$langs->trans('ManufacturingOrder').'</td><td>';
		print '<select name="fk_mo" id="fk_mo" class="maxwidth400">';
		print '<option value="">'.dol_escape_htmltag($langs->trans('SelectProductFirst')).'</option>';
		print '</select>';
		print '</td></tr>';

		// Product Label
		print '<tr><td>'.$langs->trans('ProductLabel').'</td><td>';
		print '<input type="text" name="product_label" id="product_label" class="maxwidth500" value="'.dol_escape_htmltag($object->product_label).'" readonly>';
		print '</td></tr>';

		// Product Description
		print '<tr><td>'.$langs->trans('Description').'</td><td>';
		print '<textarea name="product_description" id="product_description" class="quatrevingtpercent" rows="3" readonly>'.dol_escape_htmltag($object->product_description, 0, 1).'</textarea>';
		print '</td></tr>';

		// Manufacturing Date
		print '<tr><td>'.$langs->trans('ManufacturingDate').'</td><td>';
		print $form->selectDate($object->date_manufactured, 'date_manufactured', 0, 0, 1, 'edit', 1, 1);
		print '</td></tr>';

		// Number of Copies
		print '<tr><td>'.$langs->trans('LabelQuantity').'</td><td>';
		print '<input type="number" name="qty_labels" class="maxwidth75" value="'.$object->qty_labels.'" min="1">';
		print '</td></tr>';

		// Extrafields
		print $object->showOptionals($extrafields, 'edit');

		print '</table>';
		print '</div>';
		print dol_get_fiche_end();

		print '<div class="center">';
		print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';
		print ' &nbsp; <input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'">';
		print '</div>';

		print '</form>';

		// Cascade JavaScript for edit mode — pre-select current values
		print "\n".'<script>'."\n";
		print _boxlabel_cascade_js(
			(int) $object->fk_product,
			dol_escape_js($object->serial_number),
			'',
			(int) $object->fk_mo
		);
		print '</script>'."\n";

	} else {
		// View mode
		print '<table class="border centpercent tableforfield">';

		// Product
		print '<tr><td class="titlefield">'.$langs->trans('Product').'</td><td>';
		if ($object->fk_product > 0) {
			require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
			$prod = new Product($db);
			if ($prod->fetch($object->fk_product) > 0) {
				print $prod->getNomUrl(1);
			}
		}
		print '</td></tr>';

		// Serial Number
		print '<tr><td>'.$langs->trans('SerialNumber').'</td><td>'.dol_escape_htmltag($object->serial_number).'</td></tr>';

		// Batch
		print '<tr><td>'.$langs->trans('Batch').'</td><td>'.dol_escape_htmltag($object->batch).'</td></tr>';

		// MO
		print '<tr><td>'.$langs->trans('ManufacturingOrder').'</td><td>';
		if ($object->fk_mo > 0) {
			require_once DOL_DOCUMENT_ROOT.'/mrp/class/mo.class.php';
			$mo = new Mo($db);
			if ($mo->fetch($object->fk_mo) > 0) {
				print $mo->getNomUrl(1);
			}
		}
		print '</td></tr>';

		// Product Label
		print '<tr><td>'.$langs->trans('ProductLabel').'</td><td>'.dol_escape_htmltag($object->product_label).'</td></tr>';

		// Product Description
		print '<tr><td>'.$langs->trans('Description').'</td><td>'.dol_string_onlythesehtmltags(dol_htmlentitiesbr($object->product_description)).'</td></tr>';

		// Manufacturing Date
		print '<tr><td>'.$langs->trans('ManufacturingDate').'</td><td>'.dol_print_date($object->date_manufactured, 'day').'</td></tr>';

		// Number of Copies
		print '<tr><td>'.$langs->trans('LabelQuantity').'</td><td>'.$object->qty_labels.'</td></tr>';

		// Status
		print '<tr><td>'.$langs->trans('Status').'</td><td>'.$object->getLibStatut(5).'</td></tr>';

		// Extrafields
		print $object->showOptionals($extrafields, 'view');

		print '</table>';
		print '</div>';
		print dol_get_fiche_end();

		// Action buttons
		print '<div class="tabsAction">';

		if ($permwrite) {
			print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit&token='.newToken().'">'.$langs->trans('Modify').'</a>';
		}

		// Generate PDF button
		if ($permread) {
			print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=builddoc&token='.newToken().'">'.$langs->trans('GeneratePDF').'</a>';
		}

		if ($permdelete) {
			print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=delete&token='.newToken().'">'.$langs->trans('Delete').'</a>';
		}

		print '</div>';

		// Built documents
		$objref = dol_sanitizeFileName($object->ref);
		$filedir = $conf->boxlabel->dir_output.'/'.$objref;
		$urlsource = $_SERVER['PHP_SELF'].'?id='.$object->id;

		print $formfile->showdocuments(
			'boxlabel',
			$objref,
			$filedir,
			$urlsource,
			$permwrite,
			$permdelete,
			$object->model_pdf,
			1,
			0,
			0,
			28,
			0,
			'',
			'',
			'',
			''
		);
	}
}

llxFooter();
$db->close();


/**
 * Generate the cascade JavaScript for the BoxLabel form.
 * Used in both create and edit modes.
 *
 * @param  int    $preProduct  Pre-selected product ID (edit mode)
 * @param  string $preSerial   Pre-selected serial (edit mode)
 * @param  string $preBatch    Pre-selected batch (edit mode)
 * @param  int    $preMo       Pre-selected MO ID (edit mode)
 * @return string              JavaScript code (without <script> tags)
 */
function _boxlabel_cascade_js($preProduct = 0, $preSerial = '', $preBatch = '', $preMo = 0)
{
	global $langs;

	$ajaxBase = dol_buildpath('/boxlabel/ajax/', 1);
	$strSelectFirst  = dol_escape_js($langs->trans('SelectProductFirst'));
	$strSelectSerial = dol_escape_js($langs->trans('SelectSerial'));
	$strNoSerials    = dol_escape_js($langs->trans('NoSerialsFound'));
	$strSelectMO     = dol_escape_js($langs->trans('SelectMO'));
	$strNoMOs        = dol_escape_js($langs->trans('NoMOsFound'));
	$strLoading      = dol_escape_js($langs->trans('Loading'));

	$js = <<<JSEOF
(function(){
	var ajaxBase = '{$ajaxBase}';
	var preProduct = {$preProduct};
	var preSerial  = '{$preSerial}';
	var preMo      = {$preMo};

	var selSerial = document.getElementById('serial_number');
	var selMo     = document.getElementById('fk_mo');
	var inpBatch  = document.getElementById('batch');
	var inpLabel  = document.getElementById('product_label');
	var inpDesc   = document.getElementById('product_description');
	var inpLotId  = document.getElementById('fk_product_lot');

	// Dolibarr selectDate sub-fields
	var dateDayEl   = document.querySelector('[name=date_manufacturedday]');
	var dateMonthEl = document.querySelector('[name=date_manufacturedmonth]');
	var dateYearEl  = document.querySelector('[name=date_manufacturedyear]');

	function setSelectOption(sel, value, text, disabled) {
		sel.innerHTML = '';
		var opt = document.createElement('option');
		opt.value = value || '';
		opt.textContent = text;
		sel.appendChild(opt);
		sel.disabled = !!disabled;
	}

	// ---- Serial loading ----
	function loadSerials(pid) {
		pid = parseInt(pid, 10);
		if (!pid || pid <= 0) {
			setSelectOption(selSerial, '', '{$strSelectFirst}', true);
			clearAutoFields();
			return;
		}
		setSelectOption(selSerial, '', '{$strLoading}...', true);

		fetch(ajaxBase + 'fetch_serials.php?fk_product=' + pid, {credentials: 'same-origin'})
		.then(function(r){ return r.json(); })
		.then(function(data){
			selSerial.innerHTML = '';

			// Auto-fill product info
			if (data.product) {
				if (inpLabel) inpLabel.value = data.product.label || '';
				if (inpDesc) inpDesc.value = data.product.description || '';
			}

			if (!data.serials || !data.serials.length) {
				setSelectOption(selSerial, '', '{$strNoSerials}', true);
				return;
			}

			var blank = document.createElement('option');
			blank.value = '';
			blank.textContent = '— {$strSelectSerial} —';
			selSerial.appendChild(blank);

			data.serials.forEach(function(s){
				var opt = document.createElement('option');
				opt.value = s.batch;
				opt.textContent = s.batch + ' (qty: ' + s.total_qty + ')';
				opt.dataset.lotId    = s.lot_id;
				opt.dataset.mfgDay   = s.mfg_day;
				opt.dataset.mfgMonth = s.mfg_month;
				opt.dataset.mfgYear  = s.mfg_year;
				opt.dataset.mfgDate  = s.manufacturing_date;
				selSerial.appendChild(opt);
			});
			selSerial.disabled = false;

			// Pre-select if editing
			if (preSerial) {
				selSerial.value = preSerial;
				preSerial = '';
				onSerialChange();
			}
		})
		.catch(function(){
			setSelectOption(selSerial, '', '{$strNoSerials}', true);
		});
	}

	// ---- MO loading ----
	function loadMos(pid) {
		pid = parseInt(pid, 10);
		if (!pid || pid <= 0) {
			setSelectOption(selMo, '', '{$strSelectFirst}', true);
			return;
		}
		setSelectOption(selMo, '', '{$strLoading}...', true);

		fetch(ajaxBase + 'fetch_mos.php?fk_product=' + pid, {credentials: 'same-origin'})
		.then(function(r){ return r.json(); })
		.then(function(mos){
			selMo.innerHTML = '';

			if (!mos.length) {
				setSelectOption(selMo, '', '{$strNoMOs}', true);
				return;
			}

			var blank = document.createElement('option');
			blank.value = '';
			blank.textContent = '— {$strSelectMO} —';
			selMo.appendChild(blank);

			mos.forEach(function(m){
				var opt = document.createElement('option');
				opt.value = m.rowid;
				opt.textContent = m.ref;
				selMo.appendChild(opt);
			});
			selMo.disabled = false;

			// Pre-select if editing
			if (preMo) {
				selMo.value = preMo;
				preMo = 0;
			}
		})
		.catch(function(){
			setSelectOption(selMo, '', '{$strNoMOs}', true);
		});
	}

	function clearAutoFields() {
		if (inpBatch) inpBatch.value = '';
		if (inpLotId) inpLotId.value = '';
		if (inpLabel) inpLabel.value = '';
		if (inpDesc) inpDesc.value = '';
	}

	// ---- Serial change handler ----
	function onSerialChange() {
		var opt = selSerial.options[selSerial.selectedIndex];
		if (!opt || !opt.value) {
			if (inpBatch) inpBatch.value = '';
			if (inpLotId) inpLotId.value = '';
			return;
		}
		// Auto-fill batch
		if (inpBatch) inpBatch.value = opt.value;
		// Auto-fill lot ID
		if (inpLotId) inpLotId.value = opt.dataset.lotId || '';
		// Auto-fill manufacturing date
		if (opt.dataset.mfgDay && dateDayEl) dateDayEl.value = parseInt(opt.dataset.mfgDay, 10);
		if (opt.dataset.mfgMonth && dateMonthEl) dateMonthEl.value = parseInt(opt.dataset.mfgMonth, 10);
		if (opt.dataset.mfgYear && dateYearEl) dateYearEl.value = parseInt(opt.dataset.mfgYear, 10);
	}

	selSerial.addEventListener('change', onSerialChange);

	// ---- Product change handler ----
	function onProductChange() {
		var el = document.querySelector('[name=fk_product]');
		var pid = el ? parseInt(el.value, 10) : 0;
		loadSerials(pid);
		loadMos(pid);
	}

	// Bind to Select2 events (Dolibarr wraps select_produits in Select2)
	if (typeof jQuery !== 'undefined') {
		jQuery(document).on('select2:select', '[name=fk_product]', onProductChange);
		jQuery(document).on('select2:clear', '[name=fk_product]', function(){
			setSelectOption(selSerial, '', '{$strSelectFirst}', true);
			setSelectOption(selMo, '', '{$strSelectFirst}', true);
			clearAutoFields();
		});
	}
	// Native fallback
	var prodEl = document.querySelector('[name=fk_product]');
	if (prodEl) prodEl.addEventListener('change', onProductChange);

	// ---- Init: load if product is pre-selected ----
	if (preProduct > 0) {
		loadSerials(preProduct);
		loadMos(preProduct);
	}
})();
JSEOF;

	return $js;
}
