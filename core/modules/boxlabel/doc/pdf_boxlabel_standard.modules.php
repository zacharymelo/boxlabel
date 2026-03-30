<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    core/modules/boxlabel/doc/pdf_boxlabel_standard.modules.php
 * \ingroup boxlabel
 * \brief   PDF model for 4x6 inch box labels with barcodes
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/boxlabel/core/modules/boxlabel/modules_boxlabel.php';


/**
 * Class to generate 4x6 inch box label PDFs with Code 128 and QR barcodes
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
	public $marge_gauche = 5;

	/** @var float */
	public $marge_droite = 5;

	/** @var float */
	public $marge_haute = 5;

	/** @var float */
	public $marge_basse = 5;


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

		// Set file permissions
		dolChmod($filepath);

		return 1;
	}

	/**
	 * Generate a single label page
	 *
	 * @param TCPDF     $pdf         PDF instance
	 * @param BoxLabel  $object      BoxLabel object
	 * @param Translate $outputlangs Language object
	 */
	private function _generateLabelPage(&$pdf, $object, $outputlangs)
	{
		global $conf, $mysoc;

		$pdf->AddPage('P', array($this->page_largeur, $this->page_hauteur));

		$usableWidth = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$curY = $this->marge_haute;

		// ---- 1. Company Logo ----
		$logo = $mysoc->logo;
		$logoHeight = 0;
		if (!empty($logo)) {
			$logoFile = $conf->mycompany->dir_output.'/logos/'.$logo;
			if (file_exists($logoFile)) {
				$logoHeight = 15;
				// Center the logo
				$logoWidth = 0; // Auto-calculate width from height
				$info = pdf_getHeightForLogo($logoFile);
				$logoWidth = min(50, $info * 15 / max(1, $info)); // Max 50mm wide
				$xLogo = $this->marge_gauche + ($usableWidth - min(50, 50)) / 2;
				$pdf->Image($logoFile, $xLogo, $curY, 0, $logoHeight);
				$curY += $logoHeight + 3;
			}
		}

		if ($logoHeight == 0) {
			// No logo — print company name instead
			$pdf->SetFont('', 'B', 10);
			$pdf->SetXY($this->marge_gauche, $curY);
			$pdf->Cell($usableWidth, 5, $outputlangs->convToOutputCharset($mysoc->name), 0, 1, 'C');
			$curY += 8;
		}

		// ---- 2. Product Name ----
		$pdf->SetFont('', 'B', 16);
		$pdf->SetXY($this->marge_gauche, $curY);
		$productName = $outputlangs->convToOutputCharset($object->product_label);
		$pdf->MultiCell($usableWidth, 6, $productName, 0, 'C');
		$curY = $pdf->GetY() + 2;

		// ---- 3. Product Description ----
		if (!empty($object->product_description)) {
			$pdf->SetFont('', '', 9);
			$pdf->SetXY($this->marge_gauche, $curY);
			$desc = dol_string_nohtmltag($object->product_description, 1);
			$desc = $outputlangs->convToOutputCharset($desc);
			// Limit description length to avoid overflow
			if (dol_strlen($desc) > 200) {
				$desc = dol_substr($desc, 0, 197).'...';
			}
			$pdf->MultiCell($usableWidth, 4, $desc, 0, 'L');
			$curY = $pdf->GetY() + 2;
		}

		// ---- 4. Horizontal Rule ----
		$pdf->SetDrawColor(0, 0, 0);
		$pdf->Line($this->marge_gauche, $curY, $this->page_largeur - $this->marge_droite, $curY);
		$curY += 3;

		// ---- 5. Batch & Serial ----
		$pdf->SetFont('', 'B', 11);
		$pdf->SetXY($this->marge_gauche, $curY);

		$batchSerial = '';
		if (!empty($object->batch)) {
			$batchSerial .= 'Batch: '.$outputlangs->convToOutputCharset($object->batch);
		}
		if (!empty($object->serial_number)) {
			if (!empty($batchSerial)) {
				$batchSerial .= '  |  ';
			}
			$batchSerial .= 'Serial: '.$outputlangs->convToOutputCharset($object->serial_number);
		}
		$pdf->Cell($usableWidth, 6, $batchSerial, 0, 1, 'C');
		$curY = $pdf->GetY() + 1;

		// ---- 6. Manufacturing Date ----
		if (!empty($object->date_manufactured)) {
			$pdf->SetFont('', '', 10);
			$pdf->SetXY($this->marge_gauche, $curY);
			$dateStr = 'Mfg Date: '.dol_print_date($object->date_manufactured, 'day');
			$pdf->Cell($usableWidth, 5, $dateStr, 0, 1, 'C');
			$curY = $pdf->GetY() + 3;
		}

		// ---- 7. Code 128 Barcode (Serial Number) ----
		$barcodeData = !empty($object->serial_number) ? $object->serial_number : $object->ref;
		if (!empty($barcodeData)) {
			$barcodeWidth = min(70, $usableWidth - 10);
			$barcodeHeight = 18;
			$xBarcode = $this->marge_gauche + ($usableWidth - $barcodeWidth) / 2;

			$barcodeStyle = array(
				'position' => '',
				'align' => 'C',
				'stretch' => false,
				'fitwidth' => true,
				'cellfitalign' => '',
				'border' => false,
				'hpadding' => 'auto',
				'vpadding' => 'auto',
				'fgcolor' => array(0, 0, 0),
				'bgcolor' => false,
				'text' => true,
				'font' => 'helvetica',
				'fontsize' => 7,
				'stretchtext' => 4,
			);

			$pdf->write1DBarcode($barcodeData, 'C128', $xBarcode, $curY, $barcodeWidth, $barcodeHeight, 0.4, $barcodeStyle, 'N');
			$curY += $barcodeHeight + 4;
		}

		// ---- 8. QR Code ----
		$qrData = json_encode(array(
			'ref' => $object->ref,
			'product' => $object->product_label,
			'batch' => $object->batch,
			'serial' => $object->serial_number,
			'date' => !empty($object->date_manufactured) ? dol_print_date($object->date_manufactured, 'daytext') : '',
		));

		$qrSize = 35;
		$xQR = $this->marge_gauche + ($usableWidth - $qrSize) / 2;

		// Ensure QR code fits on the page
		$remainingHeight = $this->page_hauteur - $this->marge_basse - $curY;
		if ($qrSize > $remainingHeight) {
			$qrSize = max(20, $remainingHeight - 2);
		}

		$qrStyle = array(
			'border' => false,
			'vpadding' => 'auto',
			'hpadding' => 'auto',
			'fgcolor' => array(0, 0, 0),
			'bgcolor' => false,
			'module_width' => 1,
			'module_height' => 1,
		);

		$pdf->write2DBarcode($qrData, 'QRCODE,M', $xQR, $curY, $qrSize, $qrSize, $qrStyle, 'N');
	}
}
