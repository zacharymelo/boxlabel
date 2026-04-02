<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    ajax/debug.php
 * \ingroup boxlabel
 * \brief   Comprehensive debug diagnostics for the boxlabel module.
 *          Gated by admin permission + BOXLABEL_DEBUG_MODE setting.
 *
 * Modes (via ?mode=):
 *   overview    - Module config, hook contexts, trigger registration, DB table health (default)
 *   object      - Deep inspect a single object (?mode=object&type=boxlabel&id=11)
 *   links       - All element_element rows involving this module's types
 *   settings    - All BOXLABEL_* constants from llx_const
 *   classes     - Class loading + method availability for all module objects
 *   sql         - Run a read-only diagnostic query (?mode=sql&q=SELECT...)
 *   triggers    - List all registered triggers and check ours is loaded
 *   hooks       - Show registered hook contexts and verify our hooks fire
 *   all         - Run every diagnostic at once
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists("../../../../main.inc.php")) { $res = @include "../../../../main.inc.php"; }
if (!$res) { http_response_code(500); exit; }

if (!$user->admin) { http_response_code(403); print 'Admin only'; exit; }
if (!getDolGlobalInt('BOXLABEL_DEBUG_MODE')) {
	http_response_code(403);
	print 'Debug mode not enabled. Go to Boxlabel > Setup and enable Debug Mode.';
	exit;
}

header('Content-Type: text/plain; charset=utf-8');

$mode = GETPOST('mode', 'alpha') ?: 'overview';
$run_all = ($mode === 'all');

$MODULE_NAME   = 'boxlabel';
$MODULE_UPPER  = 'BOXLABEL';
$OBJECTS = array(
	'boxlabel' => array(
		'class'      => 'BoxLabel',
		'classfile'  => 'boxlabel',
		'table'      => 'box_label',
		'prefixed'   => 'boxlabel_boxlabel',
		'fk_fields'  => array('fk_product', 'fk_mo', 'fk_product_lot'),
	),
);

print "=== BOXLABEL DEBUG DIAGNOSTICS ===\n";
print "Timestamp: ".date('Y-m-d H:i:s T')."\n";
print "Dolibarr: ".(defined('DOL_VERSION') ? DOL_VERSION : 'unknown')."\n";
print "Module version: ".getDolGlobalString('MAIN_MODULE_BOXLABEL_VERSION', 'unknown')."\n";
print "Mode: $mode\n";
print "Usage: ?mode=overview|object|links|settings|classes|sql|triggers|hooks|all\n";
print "       ?mode=object&type=boxlabel&id=11\n";
print "       ?mode=sql&q=SELECT+rowid,ref+FROM+llx_box_label+LIMIT+5\n";
print str_repeat('=', 60)."\n\n";


// OVERVIEW
if ($mode === 'overview' || $run_all) {
	print "--- MODULE STATUS ---\n";
	print "isModEnabled('$MODULE_NAME'): ".(isModEnabled($MODULE_NAME) ? 'YES' : 'NO')."\n";

	print "\n--- DATABASE TABLES ---\n";
	$tables = array();
	foreach ($OBJECTS as $bare => $odef) {
		$tables[] = $odef['table'];
		$tables[] = $odef['table'].'_extrafields';
	}
	foreach ($tables as $tbl) {
		$sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX.$tbl;
		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			print "  llx_$tbl: ".$obj->cnt." rows\n";
		} else {
			print "  llx_$tbl: TABLE MISSING OR ERROR\n";
		}
	}

	print "\n--- ELEMENT PROPERTIES ---\n";
	foreach ($OBJECTS as $bare => $odef) {
		foreach (array($bare, $odef['prefixed']) as $etype) {
			$props = getElementProperties($etype);
			$ok = (!empty($props['classname']) && $props['classname'] === $odef['class']);
			$cn = isset($props['classname']) ? $props['classname'] : '(empty)';
			print "  $etype -> classname=$cn ".($ok ? 'OK' : 'MISMATCH (expected '.$odef['class'].')')."\n";
		}
	}

	print "\n--- LINKED OBJECT TEMPLATES ---\n";
	foreach ($OBJECTS as $bare => $odef) {
		$tplpath = $MODULE_NAME.'/'.$bare.'/tpl/linkedobjectblock.tpl.php';
		$fullpath = dol_buildpath('/'.$tplpath);
		print "  $tplpath: ".(file_exists($fullpath) ? 'EXISTS' : 'MISSING ('.$fullpath.')')."\n";
	}
	print "\n";
}

// OBJECT
if ($mode === 'object' || $run_all) {
	$otype = GETPOST('type', 'alpha') ?: 'boxlabel';
	$oid   = GETPOSTINT('id');

	if ($oid <= 0 && !$run_all) {
		print "--- OBJECT DIAGNOSIS ---\nUsage: ?mode=object&type=boxlabel&id=11\n\n";
	} elseif ($oid > 0) {
		$odef = isset($OBJECTS[$otype]) ? $OBJECTS[$otype] : null;
		if (!$odef) {
			print "--- OBJECT DIAGNOSIS ---\nUnknown type '$otype'.\n\n";
		} else {
			print "--- OBJECT DIAGNOSIS: $otype id=$oid ---\n";
			dol_include_once('/'.$MODULE_NAME.'/class/'.$odef['classfile'].'.class.php');
			$classname = $odef['class'];

			if (!class_exists($classname)) {
				print "  Class $classname NOT FOUND!\n\n";
			} else {
				$obj = new $classname($db);
				$fetch_result = $obj->fetch($oid);
				print "  fetch() returned: $fetch_result\n";
				if ($fetch_result > 0) {
					print "  ref: $obj->ref\n";
					print "  element: $obj->element\n";
					print "  module: ".(property_exists($obj, 'module') ? ($obj->module ?: '(empty)') : '(NOT DEFINED)')."\n";
					print "  getElementType(): ".$obj->getElementType()."\n";
					print "  getNomUrl(): ".(method_exists($obj, 'getNomUrl') ? 'defined' : 'MISSING')."\n";

					print "\n  FK fields:\n";
					foreach ($odef['fk_fields'] as $fk) {
						$val = isset($obj->$fk) ? $obj->$fk : null;
						if (!empty($val)) {
							print "    $fk = $val\n";
						}
					}
					print "  Status: ".$obj->status."\n";
				}
			}
			print "\n";
		}
	}
}

// SETTINGS
if ($mode === 'settings' || $run_all) {
	print "--- BOXLABEL SETTINGS ---\n";
	$sql = "SELECT name, value FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'BOXLABEL%' AND entity IN (0, ".((int) $conf->entity).") ORDER BY name";
	$resql = $db->query($sql);
	if ($resql) {
		while ($row = $db->fetch_object($resql)) {
			$display_val = strlen($row->value) > 80 ? substr($row->value, 0, 80).'...' : $row->value;
			print "  $row->name = $display_val\n";
		}
	}
	print "\n";
}

// CLASSES
if ($mode === 'classes' || $run_all) {
	print "--- CLASS LOADING & METHODS ---\n";
	foreach ($OBJECTS as $bare => $odef) {
		print "  $bare ({$odef['class']}):\n";
		$inc = @dol_include_once('/'.$MODULE_NAME.'/class/'.$odef['classfile'].'.class.php');
		print "    dol_include_once: ".($inc ? 'OK' : 'FAILED')."\n";
		print "    class_exists: ".(class_exists($odef['class']) ? 'YES' : 'NO')."\n";

		if (class_exists($odef['class'])) {
			$required_methods = array('create', 'fetch', 'update', 'delete', 'validate', 'getNomUrl', 'getLibStatut', 'getNextNumRef');
			$obj = new $odef['class']($db);
			print "    \$module: ".(property_exists($obj, 'module') ? ($obj->module ?: '(empty)') : 'NOT DEFINED')."\n";
			print "    \$element: ".$obj->element."\n";

			$missing = array();
			foreach ($required_methods as $m) {
				if (!method_exists($obj, $m)) {
					$missing[] = $m;
				}
			}
			print "    Required methods: ".(empty($missing) ? 'ALL PRESENT' : 'MISSING: '.implode(', ', $missing))."\n";
		}
		print "\n";
	}
}

// SQL
if ($mode === 'sql') {
	$q = GETPOST('q', 'restricthtml');
	print "--- SQL QUERY ---\n";
	if (empty($q)) {
		print "Usage: ?mode=sql&q=SELECT+rowid,ref,status+FROM+llx_box_label+ORDER+BY+rowid+DESC+LIMIT+10\n";
	} else {
		$q_trimmed = trim($q);
		if (stripos($q_trimmed, 'SELECT') !== 0) {
			print "ERROR: Only SELECT queries allowed.\n";
		} else {
			$blocked = array('INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'TRUNCATE', 'CREATE', 'GRANT', 'REVOKE');
			$safe = true;
			foreach ($blocked as $kw) {
				if (stripos($q_trimmed, $kw) !== false && stripos($q_trimmed, $kw) !== stripos($q_trimmed, 'SELECT')) {
					$safe = false;
					break;
				}
			}
			if (!$safe) {
				print "ERROR: Query contains blocked keywords.\n";
			} else {
				if (stripos($q_trimmed, 'LIMIT') === false) {
					$q_trimmed .= ' LIMIT 50';
				}
				print "Query: $q_trimmed\n\n";
				$resql = $db->query($q_trimmed);
				if ($resql) {
					$first = true;
					$row_num = 0;
					while ($obj = $db->fetch_array($resql)) {
						if ($first) {
							print implode("\t", array_keys($obj))."\n";
							print str_repeat('-', 80)."\n";
							$first = false;
						}
						$row_num++;
						$vals = array();
						foreach ($obj as $v) {
							$vals[] = ($v === null) ? 'NULL' : (strlen($v) > 40 ? substr($v, 0, 40).'...' : $v);
						}
						print implode("\t", $vals)."\n";
					}
					print "\n$row_num rows returned.\n";
				} else {
					print "SQL ERROR: ".$db->lasterror()."\n";
				}
			}
		}
	}
	print "\n";
}

// TRIGGERS
if ($mode === 'triggers' || $run_all) {
	print "--- TRIGGER REGISTRATION ---\n";
	$trigger_dir = DOL_DOCUMENT_ROOT.'/custom/'.$MODULE_NAME.'/core/triggers';
	if (is_dir($trigger_dir)) {
		$files = scandir($trigger_dir);
		foreach ($files as $f) {
			if (preg_match('/^interface_.*\.class\.php$/', $f)) {
				print "  Found trigger file: $f\n";
				include_once $trigger_dir.'/'.$f;
			}
		}
	} else {
		print "  Trigger directory not found: $trigger_dir\n";
	}
	print "\n";
}

// HOOKS
if ($mode === 'hooks' || $run_all) {
	print "--- HOOK REGISTRATION ---\n";
	if (isset($conf->modules_parts['hooks'])) {
		foreach ($conf->modules_parts['hooks'] as $context => $modules) {
			if (is_array($modules)) {
				foreach ($modules as $mod) {
					if (stripos($mod, $MODULE_NAME) !== false) {
						print "    context='$context' module='$mod'\n";
					}
				}
			} elseif (stripos($modules, $MODULE_NAME) !== false) {
				print "    context='$context' module='$modules'\n";
			}
		}
	}

	$actions_file = DOL_DOCUMENT_ROOT.'/custom/'.$MODULE_NAME.'/class/actions_'.$MODULE_NAME.'.class.php';
	print "\n  Actions class:\n";
	print "    File exists: ".(file_exists($actions_file) ? 'YES' : 'NO')."\n";
	if (file_exists($actions_file)) {
		include_once $actions_file;
		$actions_class = 'ActionsBoxlabel';
		print "    Class exists: ".(class_exists($actions_class) ? 'YES' : 'NO')."\n";
		if (class_exists($actions_class)) {
			$methods = array('getElementProperties', 'formObjectOptions', 'showLinkToObjectBlock');
			foreach ($methods as $m) {
				print "    method $m(): ".(method_exists($actions_class, $m) ? 'defined' : 'MISSING')."\n";
			}
		}
	}
	print "\n";
}

print "=== END DEBUG ===\n";
