<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    core/modules/boxlabel/doc/pdf_boxlabel_standard.modules.php
 * \ingroup boxlabel
 * \brief   PDF model for 4x6 inch box labels with professional layout and barcodes
 *
 * Layout follows industrial label design conventions:
 *   ┌─────────────────────────────┐
 *   │         COMPANY LOGO        │  ← Header zone (brand)
 *   ├─────────────────────────────┤
 *   │                             │
 *   │      PRODUCT NAME           │  ← Primary zone (what is it)
 *   │      Description text       │
 *   │                             │
 *   ├──────────────┬──────────────┤
 *   │  BATCH       │  SERIAL      │  ← Data zone (key-value pairs)
 *   ├──────────────┼──────────────┤
 *   │  MFG DATE    │  REF         │
 *   ├──────────────┴──────────────┤
 *   │      ||||| BARCODE |||||     │  ← Scan zone (machine-readable)
 *   │          [QR CODE]          │
 *   └─────────────────────────────┘
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/boxlabel/core/modules/boxlabel/modules_boxlabel.php';


/**
 * Class to generate professionally designed 4x6 inch box label PDFs
 */
class pdf_boxlabel_standard extends ModelePDFBoxLabel
{
	/** @var DoliDB */
	public $db;

	/** @var string */
	public $name = 'standard';

	/** @var string */
	public $description = '4x6 inch box label with barcodes';

	/** @var int */
	public $version = 1;

	/** @var string */
	public $type = 'pdf';

	/** @var float Page width in mm (4 inches) */
	public $page_largeur = 101.6;

	/** @var float Page height in mm (6 inches) */
	public $page_hauteur = 152.4;

	/** @var float */
	public $marge_gauche = 4;

	/** @var float */
	public $marge_droite = 4;

	/** @var float */
	public $marge_haute = 4;

	/** @var float */
	public $marge_basse = 4;


	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Generate the label PDF and write to disk
	 *
	 * @param  BoxLabel  $object          Object to generate label for
	 * @param  Translate $outputlangs     Language for output
	 * @param  string    $srctemplatepath  Not used for labels
	 * @param  int       $hidedetails     Not used
	 * @param  int       $hidedesc        Not used
	 * @param  int       $hideref         Not used
	 * @return int                        1=OK, 0=KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $conf, $mysoc;

		if (!is_object($outputlangs)) {
			$outputlangs = new Translate('', $conf);
		}
		$outputlangs->setDefaultLang($outputlangs->defaultlang);
		$outputlangs->loadLangs(array('boxlabel@boxlabel', 'products'));

		// Output directory
		$dir = $conf->boxlabel->dir_output;
		if (!empty($conf->boxlabel->multidir_output[$object->entity])) {
			$dir = $conf->boxlabel->multidir_output[$object->entity];
		}

		$objectref = dol_sanitizeFileName($object->ref);
		$dir .= '/'.$objectref;

		if (!file_exists($dir)) {
			dol_mkdir($dir);
		}

		$filename = $objectref.'.pdf';
		$filepath = $dir.'/'.$filename;

		// Create PDF instance with custom 4x6 page size
		$format = array($this->page_largeur, $this->page_hauteur);
		$pdf = pdf_getInstance($format);

		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}

		$pdf->SetAutoPageBreak(false, 0);
		$pdf->SetCreator('Dolibarr '.DOL_VERSION);
		$pdf->SetAuthor($outputlangs->convToOutputCharset($mysoc->name));
		$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
		$pdf->SetSubject('Box Label');

		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);

		// Generate label pages (qty_labels copies)
		$qty = max(1, (int) $object->qty_labels);
		for ($copy = 0; $copy < $qty; $copy++) {
			$this->_generateLabelPage($pdf, $object, $outputlangs);
		}

		// Write to disk
		$pdf->Output($filepath, 'F');

		// Update object
		$object->last_main_doc = $objectref.'/'.$filename;
		$object->model_pdf = $this->name;

		$sql = "UPDATE ".MAIN_DB_PREFIX."box_label SET";
		$sql .= " last_main_doc = '".$this->db->escape($object->last_main_doc)."'";
		$sql .= ", model_pdf = '".$this->db->escape($object->model_pdf)."'";
		$sql .= " WHERE rowid = ".((int) $object->id);
		$this->db->query($sql);

		dolChmod($filepath);

		return 1;
	}

	/**
	 * Generate a combined multi-page PDF with one label per page.
	 *
	 * @param  BoxLabel[] $labels      Array of BoxLabel objects to include
	 * @param  Translate  $outputlangs Language object
	 * @param  string     $mo_ref      MO reference for filename
	 * @return string                  Filepath of generated PDF, or empty on error
	 */
	public function write_file_multi($labels, $outputlangs, $mo_ref)
	{
		global $conf, $mysoc;

		if (empty($labels)) {
			return '';
		}

		if (!is_object($outputlangs)) {
			$outputlangs = new Translate('', $conf);
		}
		$outputlangs->setDefaultLang($outputlangs->defaultlang);
		$outputlangs->loadLangs(array('boxlabel@boxlabel', 'products'));

		// Output directory — store under MO ref subdirectory
		$dir = $conf->boxlabel->dir_output;
		if (!empty($conf->boxlabel->multidir_output[$conf->entity])) {
			$dir = $conf->boxlabel->multidir_output[$conf->entity];
		}

		$safeRef = dol_sanitizeFileName($mo_ref);
		$dir .= '/'.$safeRef;

		if (!file_exists($dir)) {
			dol_mkdir($dir);
		}

		$filename = $safeRef.'_all_labels.pdf';
		$filepath = $dir.'/'.$filename;

		// Create single PDF instance
		$format = array($this->page_largeur, $this->page_hauteur);
		$pdf = pdf_getInstance($format);

		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}

		$pdf->SetAutoPageBreak(false, 0);
		$pdf->SetCreator('Dolibarr '.DOL_VERSION);
		$pdf->SetAuthor($outputlangs->convToOutputCharset($mysoc->name));
		$pdf->SetTitle($outputlangs->convToOutputCharset($mo_ref.' - All Labels'));
		$pdf->SetSubject('Box Labels');
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);

		// Add one page per label
		foreach ($labels as $label) {
			$this->_generateLabelPage($pdf, $label, $outputlangs);
		}

		$pdf->Output($filepath, 'F');
		dolChmod($filepath);

		return $filepath;
	}

	/**
	 * Generate a single label page with adaptive layout.
	 * Calculates available space and scales fonts/sizes to prevent overlap.
	 *
	 * @param TCPDF     $pdf         PDF instance
	 * @param BoxLabel  $object      BoxLabel object
	 * @param Translate $outputlangs Language object
	 * @return void
	 */
	private function _generateLabelPage(&$pdf, $object, $outputlangs)
	{
		global $conf, $mysoc;

		$pdf->AddPage('P', array($this->page_largeur, $this->page_hauteur));

		$W = $this->page_largeur;   // 101.6
		$H = $this->page_hauteur;   // 152.4
		$L = $this->marge_gauche;   // 4
		$R = $this->marge_droite;   // 4
		$T = $this->marge_haute;    // 4
		$B = $this->marge_basse;    // 4
		$usable = $W - $L - $R;     // 93.6

		// Color palette — high contrast for B&W laser printers
		$dataBg    = array(255, 255, 255);
		$borderClr = array(0, 0, 0);
		$labelClr  = array(80, 80, 80);
		$valueClr  = array(0, 0, 0);

		// ================================================================
		// PRE-CALCULATE: Count data grid rows to determine sizing
		// ================================================================
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
		$product = new Product($this->db);
		$countryName = '';
		$weightStr = '';
		$dimsStr = '';
		$volumeStr = '';
		$customCode = '';
		$surfaceStr = '';
		$netMeasureStr = '';
		$extraFieldData = array(); // key => array('label' => ..., 'value' => ...)

		if (!empty($object->fk_product) && $object->fk_product > 0 && $product->fetch($object->fk_product) > 0) {
			if (!empty($product->country_id)) {
				$countryName = getCountry($product->country_id, 'all', $this->db);
				if (is_array($countryName) || is_object($countryName)) {
					$countryName = is_object($countryName) ? $countryName->label : (isset($countryName['label']) ? $countryName['label'] : '');
				}
			}
			if (!empty($product->weight) && $product->weight > 0) {
				$weightUnit = measuring_units_string($product->weight_units, 'weight', 0, 1);
				$weightStr = $product->weight.' '.$weightUnit;
			}
			$dimParts = array();
			$dimUnit = '';
			if (!empty($product->length) && $product->length > 0) {
				$dimParts[] = $product->length;
				$dimUnit = measuring_units_string($product->length_units, 'size', 0, 1);
			}
			if (!empty($product->width) && $product->width > 0) {
				$dimParts[] = $product->width;
			}
			if (!empty($product->height) && $product->height > 0) {
				$dimParts[] = $product->height;
			}
			if (count($dimParts) > 0) {
				$dimsStr = implode(' x ', $dimParts).(!empty($dimUnit) ? ' '.$dimUnit : '');
			}
			if (!empty($product->volume) && $product->volume > 0) {
				$volUnit = measuring_units_string($product->volume_units, 'volume', 0, 1);
				$volumeStr = $product->volume.' '.$volUnit;
			}
			if (!empty($product->surface) && $product->surface > 0) {
				$surfUnit = measuring_units_string($product->surface_units, 'surface', 0, 1);
				$surfaceStr = $product->surface.' '.$surfUnit;
			}
			if (!empty($product->net_measure) && $product->net_measure > 0) {
				$nmUnit = measuring_units_string($product->net_measure_units, 'weight', 0, 1);
				$netMeasureStr = $product->net_measure.' '.$nmUnit;
			}
			if (!empty($product->customcode)) {
				$customCode = $product->customcode;
			}

			// Collect populated extrafields with their labels
			if (!empty($product->array_options)) {
				$ef = new ExtraFields($this->db);
				$ef->fetch_name_optionals_label('product');
				foreach ($product->array_options as $key => $val) {
					if (!empty($val)) {
						$attrname = preg_replace('/^options_/', '', $key);
						$extralabel = isset($ef->attributes['product']['label'][$attrname]) ? $ef->attributes['product']['label'][$attrname] : $attrname;
						$extraFieldData['extra_'.$attrname] = array('label' => strtoupper($extralabel), 'value' => $val);
					}
				}
			}
		}

		// Load per-product label template (which fields to show)
		// Cascading: check product first, then fall back to parent product's template
		$tplFields = null; // null = show all populated (default behavior)
		if (!empty($object->fk_product) && $object->fk_product > 0) {
			// Check this product's template
			$sqlTpl = "SELECT enabled_fields FROM ".MAIN_DB_PREFIX."boxlabel_product_template";
			$sqlTpl .= " WHERE fk_product = ".((int) $object->fk_product);
			$sqlTpl .= " AND entity = ".((int) $conf->entity);
			$sqlTpl .= " LIMIT 1";
			$resTpl = $this->db->query($sqlTpl);
			if ($resTpl && ($objTpl = $this->db->fetch_object($resTpl))) {
				$tplFields = array_filter(array_map('trim', explode(',', $objTpl->enabled_fields)));
			}

			// If no template on this product, check parent (variant inheritance)
			if ($tplFields === null) {
				$sqlParent = "SELECT pac.fk_product_parent FROM ".MAIN_DB_PREFIX."product_attribute_combination as pac";
				$sqlParent .= " WHERE pac.fk_product_child = ".((int) $object->fk_product);
				$sqlParent .= " AND pac.entity IN (".getEntity('product').")";
				$sqlParent .= " LIMIT 1";
				$resParent = $this->db->query($sqlParent);
				if ($resParent && ($objParent = $this->db->fetch_object($resParent))) {
					$sqlTpl2 = "SELECT enabled_fields FROM ".MAIN_DB_PREFIX."boxlabel_product_template";
					$sqlTpl2 .= " WHERE fk_product = ".((int) $objParent->fk_product_parent);
					$sqlTpl2 .= " AND entity = ".((int) $conf->entity);
					$sqlTpl2 .= " LIMIT 1";
					$resTpl2 = $this->db->query($sqlTpl2);
					if ($resTpl2 && ($objTpl2 = $this->db->fetch_object($resTpl2))) {
						$tplFields = array_filter(array_map('trim', explode(',', $objTpl2->enabled_fields)));
					}
				}
			}
		}

		// Helper: field is enabled if no template (show all) or field is in template list
		$fieldOn = function ($key) use ($tplFields) {
			return ($tplFields === null || in_array($key, $tplFields));
		};

		// Build list of optional data cells to render
		// If template exists: show fields the user selected (even if empty)
		// If no template: show fields that have data
		$optionalCells = array();

		// Core fields: each is a label => value pair
		$allCoreFields = array(
			'weight'      => array('label' => 'WEIGHT',            'value' => $weightStr),
			'dimensions'  => array('label' => 'DIMENSIONS',        'value' => $dimsStr),
			'volume'      => array('label' => 'VOLUME',            'value' => $volumeStr),
			'surface'     => array('label' => 'SURFACE',           'value' => $surfaceStr),
			'net_measure' => array('label' => 'NET MEASURE',       'value' => $netMeasureStr),
			'country'     => array('label' => 'COUNTRY OF ORIGIN', 'value' => !empty($countryName) ? $outputlangs->convToOutputCharset($countryName) : ''),
			'hs_code'     => array('label' => 'HS CODE',           'value' => !empty($customCode) ? $outputlangs->convToOutputCharset($customCode) : ''),
		);

		// Add extrafields
		foreach ($extraFieldData as $efKey => $efData) {
			$allCoreFields[$efKey] = array('label' => $efData['label'], 'value' => $outputlangs->convToOutputCharset($efData['value']));
		}

		// Filter: if template exists, show selected fields; otherwise show populated fields
		foreach ($allCoreFields as $fKey => $fData) {
			if ($tplFields !== null) {
				// Template exists — show if user selected it
				if ($fieldOn($fKey)) {
					$optionalCells[] = array('label' => $fData['label'], 'value' => !empty($fData['value']) ? $fData['value'] : '—');
				}
			} else {
				// No template — show if data is populated
				if (!empty($fData['value'])) {
					$optionalCells[] = array('label' => $fData['label'], 'value' => $fData['value']);
				}
			}
		}

		// Count grid rows: 2 always + ceil(optionalCells / 2) for paired rows
		$optionalRows = (int) ceil(count($optionalCells) / 2);
		$gridRows = 2 + $optionalRows;

		// ================================================================
		// ADAPTIVE SIZING — scale everything based on content density
		// ================================================================
		// Fixed zones: header(16) + barcode(20) + padding(~12) + free text if present
		$freeTextReserve = (!empty($object->free_text)) ? 10 : 0;
		$fixedH = 48 + $freeTextReserve;
		$availableH = $H - $T - $B - $fixedH;

		// Budget: product name/desc ~15mm, product barcode ~20mm, serial barcode ~22mm (bottom-pinned)
		$prodBarcodeReserve = 20;
		$gridBudget = $availableH - $prodBarcodeReserve - 15; // 15mm for product name/desc
		$cellH = min(12, max(8, floor($gridBudget / $gridRows)));

		// Scale fonts based on density
		$titleFont = ($gridRows <= 3) ? 16 : 14;
		$descFont = ($gridRows <= 3) ? 8 : 7;
		$valueFontBase = ($cellH >= 11) ? 10 : (($cellH >= 9) ? 8 : 7);
		$labelFont = ($cellH >= 10) ? 6 : 5;

		$curY = $T;

		// ================================================================
		// ZONE 1: HEADER — Logo + Title + Subtitle (configurable via admin)
		// ================================================================
		$headerTitle = getDolGlobalString('BOXLABEL_HEADER_TITLE', $mysoc->name);
		$headerSubtitle = getDolGlobalString('BOXLABEL_HEADER_SUBTITLE', '');
		$headerLogoSetting = getDolGlobalString('BOXLABEL_HEADER_LOGO', '');

		$headerH = !empty($headerSubtitle) ? 20 : 16;

		// Determine logo file
		$logoEndX = $L;
		if ($headerLogoSetting !== 'none') {
			$logoFile = '';
			if (!empty($headerLogoSetting)) {
				// Specific logo file selected in admin
				$logoFile = $conf->mycompany->dir_output.'/logos/'.$headerLogoSetting;
			} elseif (!empty($mysoc->logo)) {
				// Default: company logo
				$logoFile = $conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
			}
			if (!empty($logoFile) && file_exists($logoFile)) {
				$pdf->Image($logoFile, $L + 2, $curY + 2, 0, $headerH - 4);
				$logoEndX = $L + 20;
			}
		}

		$textAreaW = $usable - ($logoEndX - $L) - 4;

		// Title — configurable
		$pdf->SetFont('helvetica', 'B', 14);
		$pdf->SetTextColor($valueClr[0], $valueClr[1], $valueClr[2]);
		$titleY = !empty($headerSubtitle) ? $curY + 3 : $curY + 4;
		$pdf->SetXY($logoEndX + 2, $titleY);
		$pdf->Cell($textAreaW, 6, $outputlangs->convToOutputCharset($headerTitle), 0, 0, 'L');

		// Subtitle — configurable (only if set)
		if (!empty($headerSubtitle)) {
			$pdf->SetFont('helvetica', '', 10);
			$pdf->SetTextColor($labelClr[0], $labelClr[1], $labelClr[2]);
			$pdf->SetXY($logoEndX + 2, $curY + 10);
			$pdf->Cell($textAreaW, 4, $outputlangs->convToOutputCharset($headerSubtitle), 0, 0, 'L');
		}

		$curY += $headerH;
		$pdf->SetDrawColor($borderClr[0], $borderClr[1], $borderClr[2]);
		$pdf->SetLineWidth(0.4);
		$pdf->Line($L, $curY, $L + $usable, $curY);
		$pdf->SetLineWidth(0.3);

		// ================================================================
		// ZONE 2: PRODUCT NAME + DESCRIPTION
		// ================================================================
		$productName = $outputlangs->convToOutputCharset($object->product_label);
		$pdf->SetTextColor($valueClr[0], $valueClr[1], $valueClr[2]);

		$curY += 2;
		$pdf->SetFont('helvetica', 'B', $titleFont);
		$pdf->SetXY($L, $curY);
		$pdf->MultiCell($usable, $titleFont * 0.4, $productName, 0, 'C');
		$curY = $pdf->GetY() + 1;

		if (!empty($object->product_description)) {
			$desc = dol_string_nohtmltag($object->product_description, 1);
			$desc = $outputlangs->convToOutputCharset($desc);
			$maxDescLen = ($gridRows <= 3) ? 150 : 100;
			if (dol_strlen($desc) > $maxDescLen) {
				$desc = dol_substr($desc, 0, $maxDescLen - 3).'...';
			}
			$pdf->SetFont('helvetica', '', $descFont);
			$pdf->SetTextColor($labelClr[0], $labelClr[1], $labelClr[2]);
			$pdf->SetXY($L + 2, $curY);
			$pdf->MultiCell($usable - 4, 3, $desc, 0, 'C');
			$curY = $pdf->GetY();
		}

		$curY += 1;

		// ================================================================
		// ZONE 2.5: FREE TEXT — Optional configurable text block
		// ================================================================
		if (!empty($object->free_text)) {
			$freeText = dol_string_nohtmltag($object->free_text, 1);
			$freeText = $outputlangs->convToOutputCharset($freeText);

			// Separator line above free text
			$pdf->SetDrawColor($borderClr[0], $borderClr[1], $borderClr[2]);
			$pdf->SetLineWidth(0.2);
			$pdf->Line($L + 10, $curY, $L + $usable - 10, $curY);
			$curY += 2;

			$pdf->SetFont('helvetica', 'I', $descFont);
			$pdf->SetTextColor($valueClr[0], $valueClr[1], $valueClr[2]);
			$pdf->SetXY($L + 2, $curY);
			$pdf->MultiCell($usable - 4, 3, $freeText, 0, 'C');
			$curY = $pdf->GetY() + 1;
		}

		// ================================================================
		// ZONE 3: PRODUCT BARCODE — Code 128 encoding product barcode value
		// ================================================================
		$productBarcode = '';
		if (!empty($object->fk_product) && is_object($product)) {
			// Use the product's barcode value if set, fall back to product ref
			if (!empty($product->barcode)) {
				$productBarcode = $product->barcode;
			} elseif (!empty($product->ref)) {
				$productBarcode = $product->ref;
			}
		}
		if (!empty($productBarcode)) {
			$prodBarcodeH = 14;
			$prodBarcodeW = min(75, $usable - 6);
			$xProdBarcode = $L + ($usable - $prodBarcodeW) / 2;

			$prodBarcodeStyle = array(
				'position' => '', 'align' => 'C', 'stretch' => false,
				'fitwidth' => false, 'cellfitalign' => 'C', 'border' => false,
				'hpadding' => 2, 'vpadding' => 'auto',
				'fgcolor' => array(0, 0, 0), 'bgcolor' => false,
				'text' => true, 'font' => 'helvetica', 'fontsize' => 7, 'stretchtext' => 4,
			);

			// Label above barcode
			$pdf->SetFont('helvetica', 'B', 6);
			$pdf->SetTextColor($labelClr[0], $labelClr[1], $labelClr[2]);
			$pdf->SetXY($L, $curY);
			$pdf->Cell($usable, 3, 'PRODUCT', 0, 0, 'C');
			$curY += 3;

			$pdf->write1DBarcode($productBarcode, 'C128', $xProdBarcode, $curY, $prodBarcodeW, $prodBarcodeH, 0.4, $prodBarcodeStyle, 'N');
			$curY += $prodBarcodeH + 2;
		}

		// ================================================================
		// ZONE 4: DATA GRID — Adaptive cell heights
		// ================================================================
		$gridTop = $curY;
		$halfW = $usable / 2;

		$pdf->SetDrawColor($borderClr[0], $borderClr[1], $borderClr[2]);
		$pdf->SetLineWidth(0.3);
		$pdf->SetFillColor($dataBg[0], $dataBg[1], $dataBg[2]);

		$rowCount = 0;

		// Row 1: MO Ref (batch) | Serial (always)
		$this->_drawDataCell($pdf, $L, $curY, $halfW, $cellH, 'MFG ORDER',
			!empty($object->batch) ? $outputlangs->convToOutputCharset($object->batch) : '—',
			$labelClr, $valueClr, $dataBg, $labelFont, $valueFontBase);
		$this->_drawDataCell($pdf, $L + $halfW, $curY, $halfW, $cellH, 'SERIAL',
			!empty($object->serial_number) ? $outputlangs->convToOutputCharset($object->serial_number) : '—',
			$labelClr, $valueClr, $dataBg, $labelFont, $valueFontBase);
		$curY += $cellH;
		$rowCount++;

		// Row 2: Mfg Date (full width)
		$mfgDate = !empty($object->date_manufactured) ? dol_print_date($object->date_manufactured, 'day') : '—';
		$this->_drawDataCell($pdf, $L, $curY, $usable, $cellH, 'MFG DATE',
			$mfgDate, $labelClr, $valueClr, $dataBg, $labelFont, $valueFontBase);
		$curY += $cellH;
		$rowCount++;

		// Dynamic rows: render optional cells in pairs
		for ($ci = 0; $ci < count($optionalCells); $ci += 2) {
			$left = $optionalCells[$ci];
			$right = isset($optionalCells[$ci + 1]) ? $optionalCells[$ci + 1] : null;

			if ($right !== null) {
				// Two cells side by side
				$this->_drawDataCell($pdf, $L, $curY, $halfW, $cellH, $left['label'],
					$left['value'], $labelClr, $valueClr, $dataBg, $labelFont, $valueFontBase);
				$this->_drawDataCell($pdf, $L + $halfW, $curY, $halfW, $cellH, $right['label'],
					$right['value'], $labelClr, $valueClr, $dataBg, $labelFont, $valueFontBase);
			} else {
				// Single cell full width (odd number of fields)
				$this->_drawDataCell($pdf, $L, $curY, $usable, $cellH, $left['label'],
					$left['value'], $labelClr, $valueClr, $dataBg, $labelFont, $valueFontBase);
			}
			$curY += $cellH;
			$rowCount++;
		}

		// Outer border
		$pdf->SetDrawColor($borderClr[0], $borderClr[1], $borderClr[2]);
		$pdf->SetLineWidth(0.5);
		$pdf->Rect($L, $gridTop, $usable, $cellH * $rowCount);
		$pdf->SetLineWidth(0.3);

		// ================================================================
		// ZONE 5: SERIAL BARCODE — Code 128 centered, pinned to bottom
		// ================================================================
		$serialData = !empty($object->serial_number) ? $object->serial_number : $object->ref;
		if (!empty($serialData)) {
			$barcodeHeight = 16;
			$labelH = 3;
			$serialBarcodeW = min(75, $usable - 6);
			$xSerialBarcode = $L + ($usable - $serialBarcodeW) / 2;
			$barcodeY = $H - $B - $barcodeHeight;
			$labelY = $barcodeY - $labelH - 1;

			// Safety: ensure barcode doesn't overlap grid
			if ($labelY < $curY + 2) {
				$barcodeHeight = max(10, $H - $B - $curY - $labelH - 4);
				$barcodeY = $H - $B - $barcodeHeight;
				$labelY = $barcodeY - $labelH - 1;
			}

			// Label above barcode
			$pdf->SetFont('helvetica', 'B', 6);
			$pdf->SetTextColor($labelClr[0], $labelClr[1], $labelClr[2]);
			$pdf->SetXY($L, $labelY);
			$pdf->Cell($usable, $labelH, 'SERIAL NUMBER', 0, 0, 'C');

			$barcodeStyle = array(
				'position' => '', 'align' => 'C', 'stretch' => false,
				'fitwidth' => false, 'cellfitalign' => 'C', 'border' => false,
				'hpadding' => 2, 'vpadding' => 'auto',
				'fgcolor' => array(0, 0, 0), 'bgcolor' => false,
				'text' => true, 'font' => 'helvetica', 'fontsize' => 8, 'stretchtext' => 4,
			);

			$pdf->write1DBarcode($serialData, 'C128', $xSerialBarcode, $barcodeY, $serialBarcodeW, $barcodeHeight, 0.4, $barcodeStyle, 'N');
		}
	}

	/**
	 * Draw a bordered data cell with a small label above a large value.
	 *
	 * @param TCPDF  $pdf       PDF instance
	 * @param float  $x         X position
	 * @param float  $y         Y position
	 * @param float  $w         Cell width
	 * @param float  $h         Cell height
	 * @param string $label     Small label text (e.g. "BATCH")
	 * @param string $value     Large value text (e.g. "SN-12345")
	 * @param array  $labelClr  RGB for label text
	 * @param array  $valueClr  RGB for value text
	 * @param array  $bgClr     RGB for background fill
	 */
	/**
	 * Draw a bordered data cell with a small label above a large value.
	 * Font sizes adapt based on cell height and value length.
	 *
	 * @param TCPDF  $pdf          PDF instance
	 * @param float  $x            X position
	 * @param float  $y            Y position
	 * @param float  $w            Cell width
	 * @param float  $h            Cell height
	 * @param string $label        Small label text (e.g. "BATCH")
	 * @param string $value        Large value text (e.g. "SN-12345")
	 * @param array  $labelClr     RGB for label text
	 * @param array  $valueClr     RGB for value text
	 * @param array  $bgClr        RGB for background fill
	 * @param int    $labelFontSz  Label font size (default 6)
	 * @param int    $valueFontSz  Base value font size (default 10, auto-shrinks for long text)
	 * @return void
	 */
	private function _drawDataCell(&$pdf, $x, $y, $w, $h, $label, $value, $labelClr, $valueClr, $bgClr, $labelFontSz = 6, $valueFontSz = 10)
	{
		// Background + border — solid black for B&W laser printers
		$pdf->SetFillColor($bgClr[0], $bgClr[1], $bgClr[2]);
		$pdf->SetDrawColor(0, 0, 0);
		$pdf->SetLineWidth(0.3);
		$pdf->Rect($x, $y, $w, $h, 'DF');

		// Adaptive vertical positions based on cell height
		$labelTopPad = max(0.8, $h * 0.1);
		$labelH = $labelFontSz * 0.5;
		$valueTopY = $y + $labelTopPad + $labelH + max(0.5, $h * 0.05);
		$valueH = $h - ($valueTopY - $y) - 1;

		// Label — small uppercase
		$pdf->SetFont('helvetica', 'B', $labelFontSz);
		$pdf->SetTextColor($labelClr[0], $labelClr[1], $labelClr[2]);
		$pdf->SetXY($x + 1.5, $y + $labelTopPad);
		$pdf->Cell($w - 3, $labelH, $label, 0, 0, 'L');

		// Value — auto-shrink based on text length and available width
		$fontSize = $valueFontSz;
		$valLen = dol_strlen($value);
		if ($valLen > 20) {
			$fontSize = max(6, $valueFontSz - 2);
		}
		if ($valLen > 30) {
			$fontSize = max(5, $valueFontSz - 3);
		}

		$pdf->SetFont('helvetica', 'B', $fontSize);
		$pdf->SetTextColor($valueClr[0], $valueClr[1], $valueClr[2]);
		$pdf->SetXY($x + 1.5, $valueTopY);
		$pdf->Cell($w - 3, $valueH, $value, 0, 0, 'L');
	}
}
