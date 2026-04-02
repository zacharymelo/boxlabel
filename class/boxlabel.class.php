<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    class/boxlabel.class.php
 * \ingroup boxlabel
 * \brief   CRUD class for BoxLabel business object
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class BoxLabel — represents a printable box label with product/batch/serial info
 */
class BoxLabel extends CommonObject
{
	/** @var string Trigger prefix for call_trigger() */
	public $TRIGGER_PREFIX = 'BOXLABEL';

	/** @var string Module name — REQUIRED for getElementType() to return prefixed name */
	public $module = 'boxlabel';

	/** @var string Element type — simple lowercase, no prefix */
	public $element = 'boxlabel';

	/** @var string Table element without llx_ prefix */
	public $table_element = 'box_label';

	/** @var string Picto icon */
	public $picto = 'mrp';

	/** @var string Ref field name */
	protected $table_ref_field = 'ref';

	// Status constants
	const STATUS_DRAFT = 0;
	const STATUS_GENERATED = 1;
	const STATUS_ARCHIVED = 2;

	// Properties
	/** @var string */
	public $ref;
	/** @var int */
	public $entity;
	/** @var int */
	public $fk_product;
	/** @var int */
	public $fk_mo;
	/** @var int */
	public $fk_product_lot;
	/** @var string */
	public $batch;
	/** @var string */
	public $serial_number;
	/** @var string */
	public $product_label;
	/** @var string */
	public $product_description;
	/** @var string */
	public $free_text;
	/** @var int|string */
	public $date_manufactured;
	/** @var int|string */
	public $date_archived;
	/** @var int */
	public $qty_labels;
	/** @var int */
	public $status;
	/** @var string */
	public $note_private;
	/** @var string */
	public $note_public;
	/** @var int|string */
	public $date_creation;
	/** @var int */
	public $fk_user_creat;
	/** @var int */
	public $fk_user_modif;
	/** @var string */
	public $import_key;
	/** @var string */
	public $model_pdf;
	/** @var string */
	public $last_main_doc;


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
	 * Create object into database
	 *
	 * @param  User $user      User that creates
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function create($user, $notrigger = 0)
	{
		global $conf;
		$error = 0;

		$this->db->begin();

		$now = dol_now();
		$this->ref = $this->getNextNumRef();
		$this->status = self::STATUS_DRAFT;

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."box_label (";
		$sql .= "ref, entity, fk_product, fk_mo, fk_product_lot";
		$sql .= ", batch, serial_number, product_label, product_description, free_text";
		$sql .= ", date_manufactured, qty_labels, status";
		$sql .= ", note_private, note_public";
		$sql .= ", date_creation, fk_user_creat";
		$sql .= ") VALUES (";
		$sql .= "'".$this->db->escape($this->ref)."'";
		$sql .= ", ".((int) $conf->entity);
		$sql .= ", ".((int) $this->fk_product);
		$sql .= ", ".(empty($this->fk_mo) ? "NULL" : ((int) $this->fk_mo));
		$sql .= ", ".(empty($this->fk_product_lot) ? "NULL" : ((int) $this->fk_product_lot));
		$sql .= ", ".(empty($this->batch) ? "NULL" : "'".$this->db->escape($this->batch)."'");
		$sql .= ", ".(empty($this->serial_number) ? "NULL" : "'".$this->db->escape($this->serial_number)."'");
		$sql .= ", ".(empty($this->product_label) ? "NULL" : "'".$this->db->escape($this->product_label)."'");
		$sql .= ", ".(empty($this->product_description) ? "NULL" : "'".$this->db->escape($this->product_description)."'");
		$sql .= ", ".(empty($this->free_text) ? "NULL" : "'".$this->db->escape($this->free_text)."'");
		$sql .= ", ".(empty($this->date_manufactured) ? "NULL" : "'".$this->db->idate($this->date_manufactured)."'");
		$sql .= ", ".((int) ($this->qty_labels > 0 ? $this->qty_labels : 1));
		$sql .= ", ".((int) $this->status);
		$sql .= ", ".(empty($this->note_private) ? "NULL" : "'".$this->db->escape($this->note_private)."'");
		$sql .= ", ".(empty($this->note_public) ? "NULL" : "'".$this->db->escape($this->note_public)."'");
		$sql .= ", '".$this->db->idate($now)."'";
		$sql .= ", ".((int) $user->id);
		$sql .= ")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++;
			$this->errors[] = "Error ".$this->db->lasterror();
		}

		if (!$error) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."box_label");
			$this->date_creation = $now;
			$this->fk_user_creat = $user->id;

			// Link BoxLabel to MO in llx_element_element
			if (!empty($this->fk_mo) && $this->fk_mo > 0) {
				$this->add_object_linked('mo', $this->fk_mo);
			}

			// Link BoxLabel to Product Lot/Serial in llx_element_element
			if (!empty($this->fk_product_lot) && $this->fk_product_lot > 0) {
				$this->add_object_linked('productlot', $this->fk_product_lot);
			}
		}

		if (!$error && !$notrigger) {
			$result = $this->call_trigger('BOXLABEL_BOXLABEL_CREATE', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		return $this->id;
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param  int    $id  Id object
	 * @param  string $ref Ref
	 * @return int         >0 if OK, 0 if not found, <0 if KO
	 */
	public function fetch($id, $ref = '')
	{
		$sql = "SELECT t.rowid, t.ref, t.entity, t.fk_product, t.fk_mo, t.fk_product_lot";
		$sql .= ", t.batch, t.serial_number, t.product_label, t.product_description, t.free_text";
		$sql .= ", t.date_manufactured, t.date_archived, t.qty_labels, t.status";
		$sql .= ", t.note_private, t.note_public";
		$sql .= ", t.date_creation, t.tms, t.fk_user_creat, t.fk_user_modif";
		$sql .= ", t.import_key, t.model_pdf, t.last_main_doc";
		$sql .= " FROM ".MAIN_DB_PREFIX."box_label as t";
		$sql .= " WHERE t.entity IN (".getEntity('boxlabel').")";
		if ($id > 0) {
			$sql .= " AND t.rowid = ".((int) $id);
		} elseif (!empty($ref)) {
			$sql .= " AND t.ref = '".$this->db->escape($ref)."'";
		} else {
			return -1;
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$obj = $this->db->fetch_object($resql);

				$this->id                  = $obj->rowid;
				$this->ref                 = $obj->ref;
				$this->entity              = $obj->entity;
				$this->fk_product          = $obj->fk_product;
				$this->fk_mo               = $obj->fk_mo;
				$this->fk_product_lot      = $obj->fk_product_lot;
				$this->batch               = $obj->batch;
				$this->serial_number       = $obj->serial_number;
				$this->product_label       = $obj->product_label;
				$this->product_description = $obj->product_description;
				$this->free_text           = $obj->free_text;
				$this->date_manufactured   = $this->db->jdate($obj->date_manufactured);
				$this->date_archived       = $this->db->jdate($obj->date_archived);
				$this->qty_labels          = $obj->qty_labels;
				$this->status              = $obj->status;
				$this->note_private        = $obj->note_private;
				$this->note_public         = $obj->note_public;
				$this->date_creation       = $this->db->jdate($obj->date_creation);
				$this->tms                 = $obj->tms;
				$this->fk_user_creat       = $obj->fk_user_creat;
				$this->fk_user_modif       = $obj->fk_user_modif;
				$this->import_key          = $obj->import_key;
				$this->model_pdf           = $obj->model_pdf;
				$this->last_main_doc       = $obj->last_main_doc;

				$this->db->free($resql);
				return 1;
			}
			$this->db->free($resql);
			return 0;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Update object into database
	 *
	 * @param  User $user      User that modifies
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function update($user, $notrigger = 0)
	{
		$error = 0;

		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX."box_label SET";
		$sql .= " fk_product = ".((int) $this->fk_product);
		$sql .= ", fk_mo = ".(empty($this->fk_mo) ? "NULL" : ((int) $this->fk_mo));
		$sql .= ", fk_product_lot = ".(empty($this->fk_product_lot) ? "NULL" : ((int) $this->fk_product_lot));
		$sql .= ", batch = ".(empty($this->batch) ? "NULL" : "'".$this->db->escape($this->batch)."'");
		$sql .= ", serial_number = ".(empty($this->serial_number) ? "NULL" : "'".$this->db->escape($this->serial_number)."'");
		$sql .= ", product_label = ".(empty($this->product_label) ? "NULL" : "'".$this->db->escape($this->product_label)."'");
		$sql .= ", product_description = ".(empty($this->product_description) ? "NULL" : "'".$this->db->escape($this->product_description)."'");
		$sql .= ", free_text = ".(empty($this->free_text) ? "NULL" : "'".$this->db->escape($this->free_text)."'");
		$sql .= ", date_manufactured = ".(empty($this->date_manufactured) ? "NULL" : "'".$this->db->idate($this->date_manufactured)."'");
		$sql .= ", qty_labels = ".((int) ($this->qty_labels > 0 ? $this->qty_labels : 1));
		$sql .= ", status = ".((int) $this->status);
		$sql .= ", note_private = ".(empty($this->note_private) ? "NULL" : "'".$this->db->escape($this->note_private)."'");
		$sql .= ", note_public = ".(empty($this->note_public) ? "NULL" : "'".$this->db->escape($this->note_public)."'");
		$sql .= ", fk_user_modif = ".((int) $user->id);
		$sql .= " WHERE rowid = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++;
			$this->errors[] = "Error ".$this->db->lasterror();
		}

		// Re-sync MO and Lot/Serial links in llx_element_element
		if (!$error) {
			$this->syncLinkedObjects();
		}

		if (!$error && !$notrigger) {
			$result = $this->call_trigger('BOXLABEL_BOXLABEL_MODIFY', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		return 1;
	}

	/**
	 * Sync the MO and Lot/Serial links in llx_element_element.
	 * Removes stale links and ensures current FK values are linked.
	 *
	 * @return void
	 */
	public function syncLinkedObjects()
	{
		if (empty($this->id)) {
			return;
		}

		$target_type = $this->getElementType(); // 'boxlabel_boxlabel'
		$bare_target = $this->element;           // 'boxlabel'

		$link_types = array('mo', 'productlot');

		foreach ($link_types as $source_type) {
			// Remove existing links of this type
			$sql_del = "DELETE FROM ".MAIN_DB_PREFIX."element_element";
			$sql_del .= " WHERE fk_target = ".((int) $this->id);
			$sql_del .= " AND targettype = '".$this->db->escape($target_type)."'";
			$sql_del .= " AND sourcetype = '".$this->db->escape($source_type)."'";
			$this->db->query($sql_del);

			// Clean bare element name links
			if ($bare_target !== $target_type) {
				$sql_del2 = "DELETE FROM ".MAIN_DB_PREFIX."element_element";
				$sql_del2 .= " WHERE fk_target = ".((int) $this->id);
				$sql_del2 .= " AND targettype = '".$this->db->escape($bare_target)."'";
				$sql_del2 .= " AND sourcetype = '".$this->db->escape($source_type)."'";
				$this->db->query($sql_del2);
			}
		}

		// Re-create MO link
		if (!empty($this->fk_mo) && $this->fk_mo > 0) {
			$this->add_object_linked('mo', $this->fk_mo);
		}

		// Re-create Lot/Serial link
		if (!empty($this->fk_product_lot) && $this->fk_product_lot > 0) {
			$this->add_object_linked('productlot', $this->fk_product_lot);
		}
	}

	/**
	 * Delete object in database
	 *
	 * @param  User $user      User that deletes
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function delete($user, $notrigger = 0)
	{
		$error = 0;

		$this->db->begin();

		if (!$error && !$notrigger) {
			$result = $this->call_trigger('BOXLABEL_BOXLABEL_DELETE', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if (!$error) {
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."box_label";
			$sql .= " WHERE rowid = ".((int) $this->id);

			$resql = $this->db->query($sql);
			if (!$resql) {
				$error++;
				$this->errors[] = "Error ".$this->db->lasterror();
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		return 1;
	}

	/**
	 * Set status to Generated
	 *
	 * @param  User $user      User that validates
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function validate($user, $notrigger = 0)
	{
		$error = 0;

		if ($this->status == self::STATUS_GENERATED) {
			return 0;
		}

		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX."box_label SET";
		$sql .= " status = ".self::STATUS_GENERATED;
		$sql .= " WHERE rowid = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++;
			$this->errors[] = "Error ".$this->db->lasterror();
		}

		if (!$error) {
			$this->status = self::STATUS_GENERATED;
		}

		if (!$error && !$notrigger) {
			$result = $this->call_trigger('BOXLABEL_BOXLABEL_VALIDATE', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		return 1;
	}

	/**
	 * Return next free value for ref
	 *
	 * @return string Next ref value
	 */
	public function getNextNumRef()
	{
		global $conf, $db;

		$addon = getDolGlobalString('BOXLABEL_ADDON', 'mod_boxlabel_standard');

		$classfile = DOL_DOCUMENT_ROOT.'/custom/boxlabel/core/modules/boxlabel/'.$addon.'.php';
		if (file_exists($classfile)) {
			require_once $classfile;
			$obj = new $addon();
			return $obj->getNextValue('', $this);
		}

		// Fallback
		$sql = "SELECT MAX(CAST(SUBSTRING(ref FROM 14) AS SIGNED)) as max_num";
		$sql .= " FROM ".MAIN_DB_PREFIX."box_label";
		$sql .= " WHERE ref LIKE 'BXL-%'";
		$sql .= " AND entity = ".((int) $conf->entity);

		$max = 0;
		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			if ($obj) {
				$max = intval($obj->max_num);
			}
		}

		$ymd = dol_print_date(dol_now(), '%Y%m%d');
		return 'BXL-'.$ymd.'-'.sprintf('%04d', $max + 1);
	}

	/**
	 * Return clickable link to object card
	 *
	 * @param  int    $withpicto  Add picto into link
	 * @param  string $option     Where point the link
	 * @param  int    $notooltip  No tooltip
	 * @return string             HTML link string
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0)
	{
		$url = dol_buildpath('/boxlabel/boxlabel_card.php', 1).'?id='.$this->id;
		$label = dol_escape_htmltag($this->ref);

		$link = '<a href="'.$url.'" title="'.$label.'">';
		$linkend = '</a>';

		$result = $link;
		if ($withpicto) {
			$result .= img_picto('', 'mrp', 'class="pictofixedwidth"');
		}
		$result .= $label.$linkend;
		return $result;
	}

	/**
	 * Return status label (badge)
	 *
	 * @param  int    $mode  0=long, 1=short, 2=picto, 3=picto+short, 4=picto+long, 5=short+picto, 6=long+picto
	 * @return string        HTML status string
	 */
	public function getLibStatut($mode = 0)
	{
		return self::LibStatut($this->status, $mode);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * Return label for given status
	 *
	 * @param  int    $status Status value
	 * @param  int    $mode   Display mode
	 * @return string         HTML status string
	 */
	public static function LibStatut($status, $mode = 0)
	{
		// phpcs:enable
		global $langs;
		$langs->load('boxlabel@boxlabel');

		$statusType = '';
		$statusLabel = '';

		if ($status == self::STATUS_DRAFT) {
			$statusType = 'status0';
			$statusLabel = $langs->transnoentitiesnoconv('BoxLabelDraft');
		} elseif ($status == self::STATUS_GENERATED) {
			$statusType = 'status4';
			$statusLabel = $langs->transnoentitiesnoconv('BoxLabelGenerated');
		} elseif ($status == self::STATUS_ARCHIVED) {
			$statusType = 'status5';
			$statusLabel = $langs->transnoentitiesnoconv('StatusArchived');
		}

		return dolGetStatus($statusLabel, $statusLabel, '', $statusType, $mode);
	}

	/**
	 * Generate PDF label by calling the PDF model's write_file() directly.
	 * Dolibarr v23 removed generateDocument() from CommonObject,
	 * so we load and call the model ourselves.
	 *
	 * @param  Translate $outputlangs Language object
	 * @param  string    $modelname  PDF model class name (default from config)
	 * @return int                    1=OK, <=0=KO
	 */
	public function buildLabelPdf($outputlangs, $modelname = '')
	{
		if (empty($modelname)) {
			$modelname = getDolGlobalString('BOXLABEL_ADDON_PDF', 'pdf_boxlabel_standard');
		}

		$modelfile = DOL_DOCUMENT_ROOT.'/custom/boxlabel/core/modules/boxlabel/doc/'.$modelname.'.modules.php';
		if (!file_exists($modelfile)) {
			$this->error = 'PDF model file not found: '.$modelname;
			return -1;
		}

		require_once $modelfile;
		$pdfmodel = new $modelname($this->db);
		$result = $pdfmodel->write_file($this, $outputlangs);

		if ($result <= 0) {
			$this->error = 'PDF generation failed';
			return -1;
		}

		return 1;
	}

	/**
	 * Generate box labels from a Manufacturing Order
	 *
	 * Queries produced lines from llx_mrp_production, creates BoxLabel records
	 * for each unique batch/serial, and generates PDF for each.
	 *
	 * @param  int    $mo_id              Manufacturing Order ID
	 * @param  User   $user               User performing the action
	 * @param  string $free_text_override  Optional free text override for all labels in this batch
	 * @return int                         Number of labels created, or <0 on error
	 */
	public function generateFromMo($mo_id, $user, $free_text_override = '')
	{
		global $conf, $langs;

		$mo_id = (int) $mo_id;
		if ($mo_id <= 0) {
			$this->error = 'Invalid MO ID';
			return -1;
		}

		// Fetch MO to get product info
		require_once DOL_DOCUMENT_ROOT.'/mrp/class/mo.class.php';
		$mo = new Mo($this->db);
		if ($mo->fetch($mo_id) <= 0) {
			$this->error = 'Manufacturing Order not found';
			return -2;
		}

		// Fetch product info
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		$product = new Product($this->db);
		if ($mo->fk_product > 0) {
			$product->fetch($mo->fk_product);
		}

		// Get all produced lines with batch/serial numbers
		$sql = "SELECT mp.rowid, mp.fk_product, mp.batch, mp.qty, mp.fk_stock_movement";
		$sql .= " FROM ".MAIN_DB_PREFIX."mrp_production as mp";
		$sql .= " WHERE mp.fk_mo = ".((int) $mo_id);
		$sql .= " AND mp.role = 'produced'";
		$sql .= " AND mp.batch IS NOT NULL AND mp.batch != ''";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -3;
		}

		$count = 0;
		while ($obj = $this->db->fetch_object($resql)) {
			// Check for existing label with same MO + serial (dedup)
			$sql_check = "SELECT rowid FROM ".MAIN_DB_PREFIX."box_label";
			$sql_check .= " WHERE fk_mo = ".((int) $mo_id);
			$sql_check .= " AND serial_number = '".$this->db->escape($obj->batch)."'";
			$sql_check .= " AND entity IN (".getEntity('boxlabel').")";

			$res_check = $this->db->query($sql_check);
			if ($res_check && $this->db->num_rows($res_check) > 0) {
				continue; // Already exists
			}

			// Look up product lot for manufacturing date
			$mfg_date = null;
			$lot_id = null;
			$sql_lot = "SELECT pl.rowid, pl.manufacturing_date";
			$sql_lot .= " FROM ".MAIN_DB_PREFIX."product_lot as pl";
			$sql_lot .= " WHERE pl.fk_product = ".((int) $obj->fk_product);
			$sql_lot .= " AND pl.batch = '".$this->db->escape($obj->batch)."'";
			$sql_lot .= " AND pl.entity IN (".getEntity('productlot').")";
			$sql_lot .= " LIMIT 1";

			$res_lot = $this->db->query($sql_lot);
			if ($res_lot && ($lot_obj = $this->db->fetch_object($res_lot))) {
				$lot_id = $lot_obj->rowid;
				$mfg_date = $this->db->jdate($lot_obj->manufacturing_date);
			}

			// If the produced line's product differs from MO product, fetch it
			$prod_label = $product->label;
			$prod_desc = $product->description;
			$prod_id = $mo->fk_product;
			if ($obj->fk_product != $mo->fk_product) {
				$alt_product = new Product($this->db);
				if ($alt_product->fetch($obj->fk_product) > 0) {
					$prod_label = $alt_product->label;
					$prod_desc = $alt_product->description;
					$prod_id = $alt_product->id;
				}
			}

			// Resolve free text: override → product template default → parent template default
			$label_free_text = '';
			if (!empty($free_text_override)) {
				$label_free_text = $free_text_override;
			} else {
				// Check product template default (with parent inheritance)
				$sql_ft = "SELECT free_text_default FROM ".MAIN_DB_PREFIX."boxlabel_product_template";
				$sql_ft .= " WHERE fk_product = ".((int) $prod_id);
				$sql_ft .= " AND entity = ".((int) $conf->entity);
				$sql_ft .= " LIMIT 1";
				$res_ft = $this->db->query($sql_ft);
				if ($res_ft && ($obj_ft = $this->db->fetch_object($res_ft)) && !empty($obj_ft->free_text_default)) {
					$label_free_text = $obj_ft->free_text_default;
				} else {
					// Check parent product template
					$sql_parent = "SELECT pt.free_text_default FROM ".MAIN_DB_PREFIX."boxlabel_product_template as pt";
					$sql_parent .= " JOIN ".MAIN_DB_PREFIX."product_attribute_combination as pac ON pac.fk_product_parent = pt.fk_product";
					$sql_parent .= " WHERE pac.fk_product_child = ".((int) $prod_id);
					$sql_parent .= " AND pac.entity IN (".getEntity('product').")";
					$sql_parent .= " AND pt.entity = ".((int) $conf->entity);
					$sql_parent .= " LIMIT 1";
					$res_parent = $this->db->query($sql_parent);
					if ($res_parent && ($obj_parent = $this->db->fetch_object($res_parent)) && !empty($obj_parent->free_text_default)) {
						$label_free_text = $obj_parent->free_text_default;
					}
				}
			}

			// Create the BoxLabel record
			$label = new BoxLabel($this->db);
			$label->fk_product = $prod_id;
			$label->fk_mo = $mo_id;
			$label->fk_product_lot = $lot_id;
			$label->batch = $mo->ref;  // Batch = MO reference (production run identifier)
			$label->serial_number = $obj->batch;
			$label->product_label = $prod_label;
			$label->product_description = $prod_desc;
			$label->free_text = $label_free_text;
			$label->date_manufactured = $mfg_date ? $mfg_date : dol_now();
			$label->qty_labels = 1;

			$result = $label->create($user, 1); // notrigger=1 to avoid loops
			if ($result > 0) {
				$count++;
			} else {
				$this->errors = array_merge($this->errors, $label->errors);
			}
		}

		$this->db->free($resql);
		return $count;
	}

	/**
	 * Count box labels for a given MO (used for tab badge)
	 *
	 * @param  int         $mo_id Manufacturing Order ID
	 * @param  object|null $obj   Optional object context
	 * @return int                Count of labels
	 */
	public function countForMo($mo_id, $obj = null)
	{
		$sql = "SELECT COUNT(rowid) as nb";
		$sql .= " FROM ".MAIN_DB_PREFIX."box_label";
		$sql .= " WHERE fk_mo = ".((int) $mo_id);
		$sql .= " AND entity IN (".getEntity('boxlabel').")";

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			return (int) $obj->nb;
		}
		return 0;
	}

	/**
	 * Archive this label (mark as shipped).
	 * Sets status to ARCHIVED and records the archive date.
	 *
	 * @param  User $user User performing the action
	 * @return int         >0 if OK, <0 if KO
	 */
	public function archive($user)
	{
		if ($this->status == self::STATUS_ARCHIVED) {
			return 0; // Already archived
		}

		$now = dol_now();

		$sql = "UPDATE ".MAIN_DB_PREFIX."box_label SET";
		$sql .= " status = ".self::STATUS_ARCHIVED;
		$sql .= ", date_archived = '".$this->db->idate($now)."'";
		$sql .= " WHERE rowid = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$this->status = self::STATUS_ARCHIVED;
		$this->date_archived = $now;

		return 1;
	}

	/**
	 * Cleanup archived labels past the retention period.
	 * Called by Dolibarr cron job. Deletes labels and their PDF files.
	 *
	 * @return int  Number of labels deleted, or -1 on error
	 */
	public function cleanupArchivedLabels()
	{
		global $conf, $user;

		if (!getDolGlobalInt('BOXLABEL_AUTO_DELETE')) {
			dol_syslog("BoxLabel::cleanupArchivedLabels — auto-delete disabled, skipping");
			return 0;
		}

		$retentionDays = getDolGlobalInt('BOXLABEL_RETENTION_DAYS', 90);
		if ($retentionDays <= 0) {
			return 0;
		}

		$cutoff = dol_now() - ($retentionDays * 86400);

		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."box_label";
		$sql .= " WHERE status = ".self::STATUS_ARCHIVED;
		$sql .= " AND date_archived IS NOT NULL";
		$sql .= " AND date_archived < '".$this->db->idate($cutoff)."'";
		$sql .= " AND entity IN (".getEntity('boxlabel').")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$deleted = 0;
		while ($obj = $this->db->fetch_object($resql)) {
			$label = new BoxLabel($this->db);
			if ($label->fetch($obj->rowid) > 0) {
				// Delete PDF file from disk
				if (!empty($label->last_main_doc)) {
					$filepath = $conf->boxlabel->dir_output.'/'.$label->last_main_doc;
					if (file_exists($filepath)) {
						dol_delete_file($filepath);
					}
					// Remove the directory if empty
					$dirpath = dirname($filepath);
					if (is_dir($dirpath) && count(scandir($dirpath)) <= 2) {
						dol_delete_dir($dirpath);
					}
				}

				$result = $label->delete($user, 1); // notrigger to avoid loops
				if ($result > 0) {
					$deleted++;
				}
			}
		}

		dol_syslog("BoxLabel::cleanupArchivedLabels — deleted $deleted expired labels (retention=$retentionDays days)");
		return $deleted;
	}
}
