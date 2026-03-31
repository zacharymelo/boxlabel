<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    core/triggers/interface_99_modBoxlabel_BoxlabelTrigger.class.php
 * \ingroup boxlabel
 * \brief   Trigger class for BoxLabel module
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Class InterfaceBoxlabelTrigger — handles trigger events for BoxLabel module.
 * Listens for MRP_MO_PRODUCE to auto-generate box labels when products are
 * manufactured, gated by global and per-product switches.
 */
class InterfaceBoxlabelTrigger extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = 'products';
		$this->description = 'Triggers for BoxLabel module';
		$this->version = '1.0.1';
		$this->picto = 'mrp';
	}

	/**
	 * Return name of trigger file
	 *
	 * @return string Name of trigger
	 */
	public function getName()
	{
		return 'BoxlabelTrigger';
	}

	/**
	 * Return description of trigger file
	 *
	 * @return string Description
	 */
	public function getDesc()
	{
		return 'Triggers for BoxLabel module — auto-generates box labels on MO production';
	}

	/**
	 * Function called when a Dolibarr business event is done
	 *
	 * @param  string    $action Event code
	 * @param  object    $object Object affected
	 * @param  User      $user   User performing action
	 * @param  Translate $langs  Language object
	 * @param  Conf      $conf   Configuration object
	 * @return int               0=OK, <0=KO
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('boxlabel')) {
			return 0;
		}

		// Handle own object triggers
		switch ($action) {
			case 'BOXLABEL_BOXLABEL_CREATE':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;

			case 'BOXLABEL_BOXLABEL_MODIFY':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;

			case 'BOXLABEL_BOXLABEL_DELETE':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;

			case 'BOXLABEL_BOXLABEL_VALIDATE':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;
		}

		// MO production — auto-generate box labels
		switch ($action) {
			case 'MRP_MO_PRODUCE':
				$this->_handleMoProduction($object, $user, $conf, $langs);
				break;
		}

		return 0;
	}

	/**
	 * Handle MO production event — auto-generate box labels if both
	 * global switch and per-product switch are enabled.
	 *
	 * @param  object    $mo    The Mo object that was produced
	 * @param  User      $user  User performing the action
	 * @param  Conf      $conf  Configuration
	 * @param  Translate $langs Language object
	 * @return void
	 */
	private function _handleMoProduction($mo, $user, $conf, $langs)
	{
		// 1. Check global switch
		if (!getDolGlobalInt('BOXLABEL_AUTO_GENERATE')) {
			dol_syslog("BoxlabelTrigger: MRP_MO_PRODUCE — global auto-generate disabled, skipping");
			return;
		}

		// 2. Get the product being produced
		$fk_product = 0;
		if (!empty($mo->fk_product)) {
			$fk_product = (int) $mo->fk_product;
		}
		if ($fk_product <= 0) {
			dol_syslog("BoxlabelTrigger: MRP_MO_PRODUCE — no product on MO id=".$mo->id.", skipping");
			return;
		}

		// 3. Check per-product switch
		$sql = "SELECT auto_label FROM ".MAIN_DB_PREFIX."boxlabel_product_auto";
		$sql .= " WHERE fk_product = ".((int) $fk_product);
		$sql .= " AND entity = ".((int) $conf->entity);
		$sql .= " LIMIT 1";

		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog("BoxlabelTrigger: MRP_MO_PRODUCE — SQL error checking product auto-label: ".$this->db->lasterror(), LOG_ERR);
			return;
		}

		$auto_enabled = false;
		if ($obj = $this->db->fetch_object($resql)) {
			$auto_enabled = ((int) $obj->auto_label > 0);
		}

		if (!$auto_enabled) {
			dol_syslog("BoxlabelTrigger: MRP_MO_PRODUCE — product id=$fk_product not flagged for auto-label, skipping");
			return;
		}

		// 4. Generate labels from this MO
		dol_syslog("BoxlabelTrigger: MRP_MO_PRODUCE — auto-generating labels for MO id=".$mo->id." product=$fk_product");

		dol_include_once('/boxlabel/class/boxlabel.class.php');
		$boxlabel = new BoxLabel($this->db);
		$count = $boxlabel->generateFromMo($mo->id, $user);

		if ($count > 0) {
			dol_syslog("BoxlabelTrigger: MRP_MO_PRODUCE — created $count label(s) for MO id=".$mo->id);

			// 5. Auto-generate PDFs for each new label
			$sql_labels = "SELECT rowid FROM ".MAIN_DB_PREFIX."box_label";
			$sql_labels .= " WHERE fk_mo = ".((int) $mo->id);
			$sql_labels .= " AND status = 0"; // Draft — just created
			$sql_labels .= " AND entity IN (".getEntity('boxlabel').")";

			$res_labels = $this->db->query($sql_labels);
			if ($res_labels) {
				while ($label_obj = $this->db->fetch_object($res_labels)) {
					$label = new BoxLabel($this->db);
					if ($label->fetch($label_obj->rowid) > 0) {
						$result = $label->buildLabelPdf($langs);
						if ($result > 0) {
							$label->validate($user, 1); // notrigger=1 to avoid loops
						}
					}
				}
			}
		} elseif ($count == 0) {
			dol_syslog("BoxlabelTrigger: MRP_MO_PRODUCE — no new labels needed (already exist or no serials)");
		} else {
			dol_syslog("BoxlabelTrigger: MRP_MO_PRODUCE — error generating labels: ".$boxlabel->error, LOG_ERR);
		}
	}
}
