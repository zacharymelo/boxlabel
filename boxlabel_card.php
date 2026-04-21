<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    boxlabel_card.php
 * \ingroup boxlabel
 * \brief   Card page for BoxLabel object — cascading FK-driven form
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
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

// Fetch object
if ($id > 0 || !empty($ref)) {
	$result = $object->fetch($id, $ref);
	if ($result <= 0) {
		dol_print_error($db, $object->error);
		exit;
	}
}

// Access control
restrictedArea($user, 'boxlabel', $object->id, 'box_label', 'boxlabel', '', 'rowid');

$permread   = $user->hasRight('boxlabel', 'boxlabel', 'read');
$permwrite  = $user->hasRight('boxlabel', 'boxlabel', 'write');
$permdelete = $user->hasRight('boxlabel', 'boxlabel', 'delete');


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
	$object->qty_labels          = 1;
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
			// Auto-generate PDF immediately
			$object->buildLabelPdf($langs);
			$object->validate($user);

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
	$object->note_public         = GETPOST('note_public', 'restricthtml');

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

	// Manufacturing Order — cascading from product, filters serials
	print '<tr><td>'.$langs->trans('ManufacturingOrder').'</td><td>';
	print '<select name="fk_mo" id="fk_mo" class="maxwidth400">';
	print '<option value="">'.dol_escape_htmltag($langs->trans('SelectProductFirst')).'</option>';
	print '</select>';
	print '</td></tr>';

	// Serial/Batch — cascading from MO (or product if no MO selected)
	print '<tr><td>'.$langs->trans('BoxLabelSerialNumber').'</td><td>';
	print '<select name="serial_number" id="serial_number" class="maxwidth400">';
	print '<option value="">'.dol_escape_htmltag($langs->trans('SelectMOFirst')).'</option>';
	print '</select>';
	print '</td></tr>';

	// Batch — auto-filled from serial, read-only
	print '<tr><td>'.$langs->trans('BoxLabelBatch').'</td><td>';
	print '<input type="text" name="batch" id="batch" class="maxwidth300" value="" readonly>';
	print '</td></tr>';

	// Product Label — auto-filled, read-only
	print '<tr><td>'.$langs->trans('BLProductLabel').'</td><td>';
	print '<input type="text" name="product_label" id="product_label" class="maxwidth500" value="" readonly>';
	print '</td></tr>';

	// Product Description — auto-filled, read-only
	print '<tr><td>'.$langs->trans('Description').'</td><td>';
	print '<textarea name="product_description" id="product_description" class="quatrevingtpercent" rows="3" readonly></textarea>';
	print '</td></tr>';

	// Public note — optional, printed on label if provided
	print '<tr><td>'.$langs->trans('NotePublic').'</td><td>';
	print '<textarea name="note_public" id="note_public" class="quatrevingtpercent" rows="2" placeholder="'.$langs->trans('BoxLabelNotePublicHelp').'"></textarea>';
	print '</td></tr>';

	// Manufacturing Date — auto-filled from serial, but user can override
	print '<tr><td>'.$langs->trans('BLManufacturingDate').'</td><td>';
	print $form->selectDate('', 'date_manufactured', 0, 0, 1, 'create', 1, 1);
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

	// Cascade JavaScript for create mode — preserve selections on error re-render
	print "\n".'<script>'."\n";
	print boxlabelCascadeJs(
		GETPOSTINT('fk_product'),
		dol_escape_js(GETPOST('serial_number', 'alpha')),
		'',
		GETPOSTINT('fk_mo')
	);
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

		// MO — cascading from product, filters serials
		print '<tr><td>'.$langs->trans('ManufacturingOrder').'</td><td>';
		print '<select name="fk_mo" id="fk_mo" class="maxwidth400">';
		print '<option value="">'.dol_escape_htmltag($langs->trans('SelectProductFirst')).'</option>';
		print '</select>';
		print '</td></tr>';

		// Serial/Batch — cascading from MO
		print '<tr><td>'.$langs->trans('BoxLabelSerialNumber').'</td><td>';
		print '<select name="serial_number" id="serial_number" class="maxwidth400">';
		print '<option value="">'.dol_escape_htmltag($langs->trans('SelectMOFirst')).'</option>';
		print '</select>';
		print '</td></tr>';

		// Batch — auto-filled
		print '<tr><td>'.$langs->trans('BoxLabelBatch').'</td><td>';
		print '<input type="text" name="batch" id="batch" class="maxwidth300" value="'.dol_escape_htmltag($object->batch).'" readonly>';
		print '</td></tr>';

		// Product Label
		print '<tr><td>'.$langs->trans('BLProductLabel').'</td><td>';
		print '<input type="text" name="product_label" id="product_label" class="maxwidth500" value="'.dol_escape_htmltag($object->product_label).'" readonly>';
		print '</td></tr>';

		// Product Description
		print '<tr><td>'.$langs->trans('Description').'</td><td>';
		print '<textarea name="product_description" id="product_description" class="quatrevingtpercent" rows="3" readonly>'.dol_escape_htmltag($object->product_description, 0, 1).'</textarea>';
		print '</td></tr>';

		// Public note — printed on label if provided
		print '<tr><td>'.$langs->trans('NotePublic').'</td><td>';
		print '<textarea name="note_public" id="note_public" class="quatrevingtpercent" rows="2">'.dol_escape_htmltag($object->note_public, 0, 1).'</textarea>';
		print '</td></tr>';

		// Manufacturing Date
		print '<tr><td>'.$langs->trans('BLManufacturingDate').'</td><td>';
		print $form->selectDate($object->date_manufactured, 'date_manufactured', 0, 0, 1, 'edit', 1, 1);
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
		print boxlabelCascadeJs(
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
		print '<tr><td>'.$langs->trans('BoxLabelSerialNumber').'</td><td>'.dol_escape_htmltag($object->serial_number).'</td></tr>';

		// Batch
		print '<tr><td>'.$langs->trans('BoxLabelBatch').'</td><td>'.dol_escape_htmltag($object->batch).'</td></tr>';

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
		print '<tr><td>'.$langs->trans('BLProductLabel').'</td><td>'.dol_escape_htmltag($object->product_label).'</td></tr>';

		// Product Description
		print '<tr><td>'.$langs->trans('Description').'</td><td>'.dol_string_onlythesehtmltags(dol_htmlentitiesbr($object->product_description)).'</td></tr>';

		// Public Note
		if (!empty($object->note_public)) {
			print '<tr><td>'.$langs->trans('NotePublic').'</td><td>'.dol_string_onlythesehtmltags(dol_htmlentitiesbr($object->note_public)).'</td></tr>';
		}

		// Manufacturing Date
		print '<tr><td>'.$langs->trans('BLManufacturingDate').'</td><td>'.dol_print_date($object->date_manufactured, 'day').'</td></tr>';

		// Number of Copies

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
function boxlabelCascadeJs($preProduct = 0, $preSerial = '', $preBatch = '', $preMo = 0)
{
	global $langs;

	$ajaxBase = dol_buildpath('/boxlabel/ajax/', 1);
	$strSelectFirst  = dol_escape_js($langs->trans('SelectProductFirst'));
	$strSelectMOFirst = dol_escape_js($langs->trans('SelectMOFirst'));
	$strSelectSerial = dol_escape_js($langs->trans('SelectSerial'));
	$strNoSerials    = dol_escape_js($langs->trans('NoSerialsFound'));
	$strSelectMO     = dol_escape_js($langs->trans('SelectMO'));
	$strNoMOs        = dol_escape_js($langs->trans('NoMOsFound'));
	$strLoading      = dol_escape_js($langs->trans('BoxLabelLoading'));

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

	function setDateField(el, val) {
		if (!el || !val) return;
		var numVal = parseInt(val, 10);
		if (isNaN(numVal)) return;
		if (el.tagName === 'SELECT') {
			for (var i = 0; i < el.options.length; i++) {
				if (parseInt(el.options[i].value, 10) === numVal) {
					el.selectedIndex = i;
					break;
				}
			}
		} else {
			el.value = numVal;
		}
		if (typeof jQuery !== 'undefined') { jQuery(el).trigger('change'); }
	}

	function clearAutoFields() {
		if (inpBatch) inpBatch.value = '';
		if (inpLotId) inpLotId.value = '';
		if (inpLabel) inpLabel.value = '';
		if (inpDesc) inpDesc.value = '';
	}

	// ================================================================
	// CASCADE: Product → MO → Serial
	// ================================================================

	// ---- Step 1: Product changes → load MOs, reset serial ----
	function onProductChange() {
		var el = document.querySelector('[name=fk_product]');
		var pid = el ? parseInt(el.value, 10) : 0;

		// Reset downstream
		setSelectOption(selSerial, '', '{$strSelectMOFirst}', true);
		clearAutoFields();

		// Auto-fill product label/desc
		if (pid > 0) {
			fetch(ajaxBase + 'fetch_serials.php?fk_product=' + pid + '&fk_mo=0', {credentials: 'same-origin'})
			.then(function(r){ return r.json(); })
			.then(function(data){
				if (data.product) {
					if (inpLabel) inpLabel.value = data.product.label || '';
					if (inpDesc) inpDesc.value = data.product.description || '';
				}
			}).catch(function(){});
		}

		loadMos(pid);
	}

	// ---- Step 2: Load MOs for product ----
	function loadMos(pid) {
		pid = parseInt(pid, 10);
		if (!pid || pid <= 0) {
			setSelectOption(selMo, '', '{$strSelectFirst}', true);
			setSelectOption(selSerial, '', '{$strSelectMOFirst}', true);
			return;
		}
		setSelectOption(selMo, '', '{$strLoading}...', true);

		fetch(ajaxBase + 'fetch_mos.php?fk_product=' + pid, {credentials: 'same-origin'})
		.then(function(r){ return r.json(); })
		.then(function(mos){
			selMo.innerHTML = '';

			if (!mos.length) {
				setSelectOption(selMo, '', '{$strNoMOs}', true);
				setSelectOption(selSerial, '', '{$strSelectMOFirst}', true);
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
				onMoChange(); // Trigger serial load
			}
		})
		.catch(function(){
			setSelectOption(selMo, '', '{$strNoMOs}', true);
		});
	}

	// ---- Step 3: MO changes → load serials for that MO ----
	function onMoChange() {
		var moId = parseInt(selMo.value, 10);
		var prodEl = document.querySelector('[name=fk_product]');
		var pid = prodEl ? parseInt(prodEl.value, 10) : 0;

		if (!moId || moId <= 0 || !pid || pid <= 0) {
			setSelectOption(selSerial, '', '{$strSelectMOFirst}', true);
			if (inpBatch) inpBatch.value = '';
			if (inpLotId) inpLotId.value = '';
			return;
		}

		loadSerials(pid, moId);
	}

	// ---- Step 4: Load serials filtered by product + MO ----
	function loadSerials(pid, moId) {
		setSelectOption(selSerial, '', '{$strLoading}...', true);

		var url = ajaxBase + 'fetch_serials.php?fk_product=' + pid + '&fk_mo=' + moId;
		fetch(url, {credentials: 'same-origin'})
		.then(function(r){ return r.json(); })
		.then(function(data){
			selSerial.innerHTML = '';

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

	// ---- Step 5: Serial changes → auto-fill batch, date, lot ID ----
	function onSerialChange() {
		var opt = selSerial.options[selSerial.selectedIndex];
		if (!opt || !opt.value) {
			if (inpBatch) inpBatch.value = '';
			if (inpLotId) inpLotId.value = '';
			return;
		}
		// Batch = MO reference (production run identifier), not serial
		var moOpt = selMo.options[selMo.selectedIndex];
		if (inpBatch) inpBatch.value = (moOpt && moOpt.value) ? moOpt.textContent : '';
		if (inpLotId) inpLotId.value = opt.dataset.lotId || '';
		setDateField(dateDayEl, opt.dataset.mfgDay);
		setDateField(dateMonthEl, opt.dataset.mfgMonth);
		setDateField(dateYearEl, opt.dataset.mfgYear);
	}

	// ---- Event bindings ----
	selSerial.addEventListener('change', onSerialChange);
	selMo.addEventListener('change', onMoChange);

	if (typeof jQuery !== 'undefined') {
		jQuery(document).on('select2:select', '[name=fk_product]', onProductChange);
		jQuery(document).on('select2:clear', '[name=fk_product]', function(){
			setSelectOption(selMo, '', '{$strSelectFirst}', true);
			setSelectOption(selSerial, '', '{$strSelectMOFirst}', true);
			clearAutoFields();
		});
	}
	var prodEl = document.querySelector('[name=fk_product]');
	if (prodEl) prodEl.addEventListener('change', onProductChange);

	// ---- Init: load if pre-selected ----
	if (preProduct > 0) {
		// Fetch product info
		fetch(ajaxBase + 'fetch_serials.php?fk_product=' + preProduct + '&fk_mo=0', {credentials: 'same-origin'})
		.then(function(r){ return r.json(); })
		.then(function(data){
			if (data.product) {
				if (inpLabel) inpLabel.value = data.product.label || '';
				if (inpDesc) inpDesc.value = data.product.description || '';
			}
		}).catch(function(){});
		loadMos(preProduct);
	}
})();
JSEOF;

	return $js;
}
