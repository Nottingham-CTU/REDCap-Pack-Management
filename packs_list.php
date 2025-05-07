<?php

namespace Nottingham\PackManagement;


// Get the pack category.
$queryCategory = $module->query( 'SELECT ems.`value` FROM redcap_external_module_settings ems ' .
                                 'JOIN redcap_external_modules em ON ems.external_module_id = ' .
                                 'em.external_module_id JOIN JSON_TABLE( ems.`value`, \'$\' ' .
                                 'COLUMNS( id TEXT PATH \'$.id\' ) ) jtbl WHERE ' .
                                 'em.directory_prefix = ? AND ems.`key` LIKE ? AND jtbl.id = ?',
                                 [ $module->getModuleDirectoryBaseName(),
                                  'p' . $module->getProjectId() . '-packcat-%', $_GET['cat_id'] ] );
$infoCategory = $queryCategory->fetch_assoc();
if ( $infoCategory == null )
{
	exit;
}
$infoCategory = json_decode( $infoCategory['value'], true );


// Get the user role.
$canConfigure = $module->canConfigure();
$roleName = null;
$userRights = $module->getUser()->getRights();
if ( $userRights !== null && $userRights['role_id'] !== null )
{
	$roleName = $userRights['role_name'];
}


// Check that the user has the rights to view this page (they must be able to do at least 1 of:
// view packs, issue to DAGs, mark as invalid, manually (re)assign, edit/delete).
if ( ! $canConfigure && ! in_array( $roleName, $infoCategory['roles_view'] ) &&
     ! in_array( $roleName, $infoCategory['roles_dags'] ) &&
     ! in_array( $roleName, $infoCategory['roles_invalid'] ) &&
     ! in_array( $roleName, $infoCategory['roles_assign'] ) &&
     ! in_array( $roleName, $infoCategory['roles_edit'] ) )
{
	exit;
}


// Get the project DAGs.
$listDAGs = \REDCap::getGroupNames();

// Get all records in the project.
$listRecords = [];
$queryRecords = $module->query( 'SELECT DISTINCT record FROM ' .
                                \REDCap::getDataTable( $module->getProjectId() ) . ' WHERE ' .
                                'project_id = ? ORDER BY record', [ $module->getProjectId() ] );
while ( $infoRecord = $queryRecords->fetch_assoc() )
{
	$listRecords[] = $infoRecord['record'];
}

// Get form name of the pack field.
$formName = \REDCap::getDataDictionary( 'array', false, $infoCategory['packfield'] );
$formName = empty( $formName ) ? null : $formName[ $infoCategory['packfield'] ]['form_name'];

// Get all events for the pack field.
$listEvents = [];
if ( \REDCap::isLongitudinal() )
{
	$listEvents = \REDCap::getEventNames( false, true );
	if ( $formName !== null )
	{
		$listFormEvents = [];
		$queryFormEvents = $module->query( 'SELECT event_id FROM redcap_events_forms ' .
		                                   'WHERE form_name = ?', [ $formName ] );
		while ( $infoFormEvent = $queryFormEvents->fetch_assoc() )
		{
			$listFormEvents[] = $infoFormEvent['event_id'];
		}
		foreach ( $listEvents as $eventID => $eventName )
		{
			if ( ! in_array( $eventID, $listFormEvents ) )
			{
				unset( $listEvents[ $eventID ] );
			}
		}
	}
}

// Get maximum instance for the project.
$queryMaxInst = $module->query( 'SELECT max(ifnull(instance,1)) max FROM ' .
                                \REDCap::getDataTable( $module->getProjectId() ) . ' WHERE ' .
                                'project_id = ?', [ $module->getProjectId() ] );
$maxInst = $queryMaxInst->fetch_assoc();
$maxInst = $maxInst == null ? 1 : $maxInst['max'];


// Get the full list of packs in the category.
$queryPacks = $module->query( 'SELECT id, block_id, packlist.value, expiry, dag, dag_rcpt, ' .
                              'assigned, invalid ' .
                              'FROM redcap_external_module_settings ems, redcap_external_modules ' .
                              'em, ' . $module->makePacklistSQL('ems.value') .
                              'WHERE em.external_module_id = ems.external_module_id ' .
                              'AND em.directory_prefix = ? AND ems.key = ? ' .
                              'ORDER BY expiry, if( block_id RLIKE \'^[0-9]{1,20}$\', ' .
                              'right(concat(\'00000000000000000000\',block_id),20), block_id ), ' .
                              'if( id RLIKE \'^[0-9]{1,20}$\', right(concat(' .
                              '\'00000000000000000000\',id),20), id )',
                             [ $module->getModuleDirectoryBaseName(),
                              'p' . $module->getProjectId() . '-packlist-' . $infoCategory['id'] ]);
$listPacks = [];
$totalPacks = 0;
$availablePacks = 0;
$assignedPacks = 0;
$invalidPacks = 0;
$expiredPacks = 0;
while ( $infoPack = $queryPacks->fetch_assoc() )
{
	// If packs are assigned to DAGs and user is in a DAG, only show packs in that DAG.
	if ( $infoCategory['dags'] && $userRights['group_id'] != '' &&
	     $userRights['group_id'] != $infoPack['dag'] )
	{
		continue;
	}
	$listPacks[] = $infoPack;
	$totalPacks++;
	if ( $infoPack['assigned'] )
	{
		$assignedPacks++;
	}
	elseif ( $infoPack['invalid'] )
	{
		$invalidPacks++;
	}
	elseif ( $infoPack['expiry'] != '' && $infoPack['expiry'] < date('Y-m-d H:i:s') )
	{
		$expiredPacks++;
	}
	else
	{
		$availablePacks++;
	}
}


// Handle form submission.
if ( isset( $_POST['action'] ) )
{
	// Acknowledge packs as received.
	if ( $infoCategory['dags'] && $_POST['action'] == 'rcpt' )
	{
		if ( ! $canConfigure && ! in_array( $roleName, $infoCategory['roles_view'] ) )
		{
			exit;
		}
		$listChosen = json_decode( $_POST['packs'], true );
		if ( $infoCategory['blocks'] )
		{
			$listChosenBlocks = [];
			$listUnchosenBlocks = [];
			foreach ( $listPacks as $infoPack )
			{
				if ( in_array( $infoPack['id'], $listChosen ) )
				{
					$listChosenBlocks[ $infoPack['block_id'] ] = true;
				}
				else
				{
					$listUnchosenBlocks[ $infoPack['block_id'] ] = true;
				}
			}
			if ( ! empty( array_intersect_key( $listChosenBlocks, $listUnchosenBlocks ) ) )
			{
				$listErrors[] = 'mark_packs_rcpt_error' .
				                ( $infoCategory['blocks'] ? '_wb' : '_nb' );
			}
		}
		if ( empty( $listErrors ) )
		{
			// Acknowledge each pack as received.
			$module->dbGetLock();
			foreach ( $listChosen as $packID )
			{
				$module->updatePackProperty( $module->getProjectId(), $infoCategory['id'], $packID,
				                             'dag_rcpt', true );
				$module->updatePackLog( $module->getProjectId(), $infoCategory['id'],
				                        'PACK_RCPT', [ 'id' => $packID ] );
			}
			$module->dbReleaseLock();
		}
	}
	// Issue packs to DAG.
	if ( $infoCategory['dags'] && $_POST['action'] == 'issue' )
	{
		if ( ! $canConfigure && ! in_array( $roleName, $infoCategory['roles_dags'] ) )
		{
			exit;
		}
		$listChosen = json_decode( $_POST['packs'], true );
		if ( $infoCategory['blocks'] )
		{
			$listChosenBlocks = [];
			$listUnchosenBlocks = [];
			foreach ( $listPacks as $infoPack )
			{
				if ( in_array( $infoPack['id'], $listChosen ) )
				{
					$listChosenBlocks[ $infoPack['block_id'] ] = true;
				}
				else
				{
					$listUnchosenBlocks[ $infoPack['block_id'] ] = true;
				}
			}
			if ( ! empty( array_intersect_key( $listChosenBlocks, $listUnchosenBlocks ) ) )
			{
				$listErrors[] = 'issue_unissue_packs_error' .
				                ( $infoCategory['blocks'] ? '_wb' : '_nb' );
			}
		}
		if ( empty( $listErrors ) )
		{
			// Assign each pack to the selected DAG. If packs must be marked as received then
			// clear the received flag.
			$module->dbGetLock();
			if ( $_POST['dag_id'] == '' )
			{
				$_POST['dag_id'] = null;
			}
			foreach ( $listChosen as $packID )
			{
				$module->updatePackProperty( $module->getProjectId(), $infoCategory['id'], $packID,
				                             [ 'dag', 'dag_rcpt' ],
				                             [ $_POST['dag_id'], ! $infoCategory['dags_rcpt'] ] );
				$infoLog = [ 'id' => $packID ];
				if ( $_POST['dag_id'] !== null )
				{
					$infoLog['dag'] = $_POST['dag_id'];
				}
				$module->updatePackLog( $module->getProjectId(), $infoCategory['id'],
				                        $_POST['dag_id'] === null ? 'PACK_UNISSUE' : 'PACK_ISSUE',
				                        $infoLog );
			}
			$module->dbReleaseLock();
		}
	}
	// Mark packs as invalid.
	if ( $_POST['action'] == 'invalid' )
	{
		if ( ! $canConfigure && ! in_array( $roleName, $infoCategory['roles_invalid'] ) )
		{
			exit;
		}
		$listChosen = json_decode( $_POST['packs'], true );
		if ( $infoCategory['blocks'] )
		{
			$listValidPacks = [];
			$listInvalidPacks = [];
			foreach ( $listPacks as $infoPack )
			{
				if ( in_array( $infoPack['id'], $listChosen ) )
				{
					if ( $infoPack['assigned'] )
					{
						$listErrors[] = 'mark_unmark_packs_invalid_error';
						break;
					}
					if ( $infoPack['invalid'] )
					{
						$listInvalidPacks[ $infoPack['block_id'] ] = true;
					}
					else
					{
						$listValidPacks[ $infoPack['block_id'] ] = true;
					}
				}
			}
			if ( ! empty( $listValidPacks ) && ! empty( $listInvalidPacks ) )
			{
				$listErrors[] = 'mark_unmark_packs_invalid_error';
			}
		}
		if ( empty( $listErrors ) )
		{
			$packsInvalid = empty( $listInvalidPacks );
			$invalidDesc = trim( str_replace( [ "\r\n", "\r" ], "\n", $_POST['invalid_desc'] ) );
			// Mark each pack as (in)valid.
			$module->dbGetLock();
			foreach ( $listChosen as $packID )
			{
				$module->updatePackProperty( $module->getProjectId(), $infoCategory['id'], $packID,
				                             ['invalid', 'invalid_desc'],
				                             [ $packsInvalid, $invalidDesc ] );
				$infoLog = [ 'id' => $packID, 'invalid' => $packsInvalid,
				             'invalid_desc' => $invalidDesc ];
				$module->updatePackLog( $module->getProjectId(), $infoCategory['id'],
				                        $packsInvalid ? 'PACK_INVALID' : 'PACK_VALID',
				                        $infoLog);
			}
			$module->dbReleaseLock();
		}
	}
	// Assign or unassign pack to record, or exchange two packs.
	if ( $_POST['action'] == 'assign' )
	{
		if ( ! $canConfigure && ! in_array( $roleName, $infoCategory['roles_assign'] ) )
		{
			exit;
		}
		// Ensure the record ID, event ID and instance are either all set or all unset.
		if ( ( $_POST['record_id'] ?? '' ) . ( $_POST['event_id'] ?? '' ) .
		     ( $_POST['instance'] ?? '' ) != '' &&
		     ( ( $_POST['record_id'] ?? '' ) == '' || ( $_POST['instance'] ?? '' ) == '' ||
		       ( \REDCap::isLongitudinal() && ( $_POST['event_id'] ?? '' ) == '' ) ) )
		{
			$listErrors[] = 'assign_reassign_packs_error_rei';
		}
		// Validate the selected packs. There must be exactly 1 or 2 packs selected.
		// If 2 packs are selected, they will be exchanged. At least 1 of these packs must be
		// already assigned for an exchange to be able to take place.
		$listChosen = json_decode( $_POST['packs'], true );
		if ( ( count( $listChosen ) != 1 && count( $listChosen ) != 2 ) ||
		     ( count( $listChosen ) == 2 && $_POST['record_id'] ?? '' != '' ) )
		{
			$listErrors[] = 'assign_reassign_packs_error';
		}
		else
		{
			$listAssignedPacks = [];
			$listUnassignedPacks = [];
			$hasInvalidPacks = false;
			foreach ( $listPacks as $infoPack )
			{
				if ( in_array( $infoPack['id'], $listChosen ) )
				{
					if ( $infoPack['invalid'] )
					{
						$hasInvalidPacks = true;
					}
					if ( $infoPack['assigned'] )
					{
						$listAssignedPacks[] = $infoPack;
					}
					else
					{
						$listUnassignedPacks[] = $infoPack;
					}
				}
			}
			if ( $hasInvalidPacks || ( count( $listChosen ) == 2 && empty( $listAssignedPacks ) ) )
			{
				$listErrors[] = 'assign_reassign_packs_error';
			}
		}
		if ( empty( $listErrors ) )
		{
			if ( ! \REDCap::isLongitudinal() && ( $_POST['record_id'] ?? '' ) != '' )
			{
				$queryCEvent = $module->query( 'SELECT event_id FROM redcap_events_metadata em ' .
				                               'JOIN redcap_events_arms ea ' .
				                               'ON em.arm_id = ea.arm_id WHERE project_id = ?',
				                               [ $module->getProjectId() ] );
				$_POST['event_id'] = '' . $queryCEvent->fetch_assoc()['event_id'];
			}
			// Get fields from minimization module if required.
			$listMinimFields = [];
			if ( $infoCategory['trigger'] == 'M' &&
			     $module->isModuleEnabled( 'minimization', $module->getProjectId() ) )
			{
				$minimModule = \ExternalModules\ExternalModules::getModuleInstance('minimization');
				foreach ( [ 'rando-field', 'rando-date-field', 'bogus-field', 'diag-field' ]
				          as $minimFieldSetting )
				{
					$minimField = $minimModule->getProjectSetting( $minimFieldSetting );
					if ( $minimField != '' )
					{
						$listMinimFields[ $minimFieldSetting ] = $minimField;
					}
				}
			}
			$listPackFields = array_values( $listMinimFields );
			// Get the fields populated by the pack management module.
			foreach ( [ 'packfield', 'datefield', 'countfield', 'valuefield' ] as $packField )
			{
				if ( $infoCategory[ $packField ] != '' )
				{
					$listPackFields[] = $infoCategory[ $packField ];
				}
			}
			foreach( $infoCategory['extrafields'] as $packField )
			{
				if ( $packField['field'] != '' )
				{
					$listPackFields[] = $packField['field'];
				}
			}
			$module->dbGetLock();
			// Assigning or unassinging single pack, or exchanging 1 assigned and 1 unassigned pack.
			if ( count( $listChosen ) == 1 || count( $listUnassignedPacks ) == 1 )
			{
				// Determine the pack's assigned record if applicable.
				if ( count( $listAssignedPacks ) == 1 )
				{
					$infoPackAssignment =
						$module->getPackAssignedRecord( $infoCategory['id'],
						                                $listAssignedPacks[0]['id'] );
				}
				else
				{
					$infoPackAssignment = [ 'record' => $_POST['record_id'],
					                        'event' => $_POST['event_id'],
					                        'instance' => $_POST['instance'] ];
				}
				$infoData = true;
				// Assign the unassigned pack.
				if ( count( $listUnassignedPacks ) == 1 )
				{
					// Get a pack as if it was being assigned normally, but using the specific pack
					// ID and allowing expired packs to be chosen.
					$infoData = $infoPackAssignment === false ? false :
					            $module->choosePack( $infoCategory['id'],
					                                 $infoPackAssignment['record'], null,
					                                 null, $listUnassignedPacks[0]['id'], true );
					if ( $infoData !== false )
					{
						// If this is a pack exchange, do not update the date and count fields if
						// these are applicable. If the pack has a value field then this must be
						// updated on the record.
						if ( count( $listAssignedPacks ) == 1 )
						{
							if ( $infoCategory['datefield'] != '' )
							{
								unset( $infoData[ $infoCategory['datefield'] ] );
							}
							if ( $infoCategory['countfield'] != '' )
							{
								unset( $infoData[ $infoCategory['countfield'] ] );
							}
							if ( $infoCategory['valuefield'] != '' )
							{
								$infoData[ $infoCategory['valuefield'] ] =
									$listUnassignedPacks[0]['value'];
							}
							elseif ( isset( $listMinimFields['rando-field'] ) )
							{
								$infoData[ $listMinimFields['rando-field'] ] =
									$listUnassignedPacks[0]['value'];
							}
						}
						// Update the record.
						$module->updateValues( $module->getProjectId(),
						                       $infoPackAssignment['record'],
						                       $infoPackAssignment['event'],
						                       $infoPackAssignment['instance'], $infoData );
					}
				}
				// Unassign the assigned pack.
				if ( count( $listAssignedPacks ) == 1 && $infoData !== false )
				{
					// Blank out the pack fields on the record unless this is a pack exchange.
					if ( empty( $listUnassignedPacks ) && $infoPackAssignment !== false )
					{
						$infoData = [];
						foreach ( $listPackFields as $packField )
						{
							$infoData[ $packField ] = '';
						}
						$module->updateValues( $module->getProjectId(),
						                       $infoPackAssignment['record'],
						                       $infoPackAssignment['event'],
						                       $infoPackAssignment['instance'], $infoData );
					}
					// Set the assigned flag on the pack to false and update the log.
					$module->updatePackProperty( $module->getProjectId(), $infoCategory['id'],
					                             $listAssignedPacks[0]['id'], 'assigned', false );
					$module->updatePackLog( $module->getProjectId(), $infoCategory['id'],
					                        'PACK_UNASSIGN',
					                        [ 'id' => $listAssignedPacks[0]['id'] ] );
				}
			}
			// Exchanging 2 assigned packs.
			else
			{
				// Get the packs and their current records.
				$infoPack1 = $listAssignedPacks[0];
				$infoPack1Assignment =
					$module->getPackAssignedRecord( $infoCategory['id'], $infoPack1['id'] );
				$infoPack2 = $listAssignedPacks[1];
				$infoPack2Assignment =
					$module->getPackAssignedRecord( $infoCategory['id'], $infoPack2['id'] );
				// Remove the fields which are not to be swapped from the list of pack fields.
				foreach ( [ 'datefield', 'countfield' ] as $packField )
				{
					if ( $infoCategory[ $packField ] != '' )
					{
						unset( $listPackFields[ array_search( $infoCategory[ $packField ],
						                                      $listPackFields ) ] );
					}
				}
				foreach ( [ 'rando-date-field', 'bogus-field' ] as $minimFieldSetting )
				{
					if ( isset( $listMinimFields[ $minimFieldSetting ] ) )
					{
						unset( $listPackFields[ array_search( $listMinimFields[ $minimFieldSetting ],
						                                      $listPackFields ) ] );
					}
				}
				// Get the values from the records for each pack.
				$listPack1Values = $module->getValues( $module->getProjectId(),
				                                       $infoPack1Assignment['record'],
				                                       $infoPack1Assignment['event'],
				                                       $infoPack1Assignment['instance'],
				                                       $listPackFields );
				$listPack2Values = $module->getValues( $module->getProjectId(),
				                                       $infoPack2Assignment['record'],
				                                       $infoPack2Assignment['event'],
				                                       $infoPack2Assignment['instance'],
				                                       $listPackFields );
				// Apply each set of values to the other record.
				if ( $module->updateValues( $module->getProjectId(), $infoPack1Assignment['record'],
				                            $infoPack1Assignment['event'],
				                            $infoPack1Assignment['instance'], $listPack2Values ) )
				{
					if ( $module->updateValues( $module->getProjectId(),
					                            $infoPack2Assignment['record'],
					                            $infoPack2Assignment['event'],
					                            $infoPack2Assignment['instance'],
					                            $listPack1Values ) )
					{
						// Both records updated successfully, update the pack log.
						$module->updatePackLog( $module->getProjectId(), $infoCategory['id'],
						                        'PACK_ASSIGN',
						                        [ 'id' => $infoPack1['id'],
						                          'record' => $infoPack2Assignment['record'] ] );
						$module->updatePackLog( $module->getProjectId(), $infoCategory['id'],
						                        'PACK_ASSIGN',
						                        [ 'id' => $infoPack2['id'],
						                          'record' => $infoPack1Assignment['record'] ] );
					}
					else
					{
						// Error updating the second record, restore the first record.
						$module->updateValues( $module->getProjectId(),
						                       $infoPack1Assignment['record'],
						                       $infoPack1Assignment['event'],
						                       $infoPack1Assignment['instance'], $listPack1Values );
					}
				}
			}
			$module->dbReleaseLock();
		}
	}
	$_SESSION['pack_management_listerrors'] = json_encode( $listErrors );
	header( 'Location: ' . $_SERVER['REQUEST_URI'] );
	exit;
}


// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->writeStyle();


?>
<div class="projhdr">
 <i class="fas fa-boxes-stacked"></i> <?php echo $module->tt('module_name'), "\n"; ?>
</div>

<p>
 <a href="<?php echo $module->getUrl('packs.php'); ?>">
  <i class="far fa-circle-left"></i> <?php echo $module->tt('back'), "\n"; ?>
 </a>
</p>

<p>&nbsp;</p>

<?php
if ( isset( $_SESSION['pack_management_listerrors'] ) )
{
	$listErrors = json_decode( $_SESSION['pack_management_listerrors'], true );
	unset( $_SESSION['pack_management_listerrors'] );
	if ( empty( $listErrors ) )
	{
		echo '<div class="mod-packmgmt-okmsg"><p>', $module->tt( 'save_packs_ok' ), '</p></div>';
	}
	else
	{
		echo '<div class="mod-packmgmt-errmsg"><p>', $module->tt( 'save_packs_err' ), '</p><ul>';
		foreach ( $listErrors as $infoError )
		{
			echo '<li>', $module->tt( ...$infoError ), '</li>';
		}
		echo '</ul></div>';
	}
}
?>

<p style="font-size:1.3em">
 <?php echo $module->tt('view_packs'), ' &#8211; ', $module->escape( $infoCategory['id'] ), "\n"; ?>
</p>

<?php
if ( $infoCategory['dags'] && $userRights['group_id'] != '' )
{
	echo '<p>', $module->tt( 'pack_list_dag', $listDAGs[ $userRights['group_id'] ] ), '</p>';
}
?>

<p>
 <?php echo $module->tt('total_packs'), ' ', $totalPacks, '<br>',
            $module->tt('available_packs'), ' ', $availablePacks, '<br>',
            $module->tt('assigned_packs'), ' ', $assignedPacks,
            $invalidPacks == 0 ? '' : ( '<br>' . $module->tt('invalid_packs') .
                                        ' ' . $invalidPacks ),
            $expiredPacks == 0 ? '' : ( '<br>' . $module->tt('expired_packs') .
                                        ' ' . $expiredPacks ); ?>

</p>

<div style="display:flex;column-gap:15px;max-width:97%">
 <p style="white-space:nowrap"><?php echo $module->tt('sh_label'); ?></p>
 <p style="display:flex;justify-content:flex-start;flex-wrap:wrap;column-gap:15px;max-width:unset">
  <a href="#" data-tblfilter=".pack-col-assign">
   <i class="far fa-eye"></i> <?php echo $module->tt('sh_assignment'), "\n"; ?>
  </a>
  <a href="#" data-tblfilter="tr:has(input[data-assigned=&quot;true&quot;])">
   <i class="far fa-eye"></i> <?php echo $module->tt('sh_assigned'), "\n"; ?>
  </a>
  <a href="#" data-tblfilter="tr:has(input[data-assigned=&quot;false&quot;])">
   <i class="far fa-eye"></i> <?php echo $module->tt('sh_unassigned'), "\n"; ?>
  </a>
<?php
if ( $infoCategory['dags'] && $infoCategory['dags_rcpt'] )
{
?>
  <a href="#" data-tblfilter="tr:has(input[data-dag-rcpt=&quot;false&quot;])">
   <i class="far fa-eye"></i> <?php echo $module->tt('sh_intransit'), "\n"; ?>
  </a>
  <a href="#" data-tblfilter="tr:has(input[data-dag-rcpt=&quot;true&quot;])">
   <i class="far fa-eye"></i> <?php echo $module->tt('sh_notintransit'), "\n"; ?>
  </a>
<?php
}
if ( $expiredPacks > 0 )
{
?>
  <a href="#" data-tblfilter="tr:has(input[data-expired=&quot;true&quot;])">
   <i class="far fa-eye"></i> <?php echo $module->tt('sh_expired'), "\n"; ?>
  </a>
<?php
}
if ( $invalidPacks > 0 )
{
?>
  <a href="#" data-tblfilter="tr:has(input[data-invalid=&quot;true&quot;])">
   <i class="far fa-eye"></i> <?php echo $module->tt('sh_invalid'), "\n"; ?>
  </a>
<?php
}
?>
 </p>
</div>

<table class="mod-packmgmt-listtable" style="opacity:0;border-width:2px">
 <tr>
  <th style="width:45px"></th>
  <th><?php echo $module->tt('packfield_id'); ?></th>
<?php
if ( $infoCategory['blocks'] )
{
?>
  <th><?php echo $module->tt('packfield_block_id'); ?></th>
<?php
}
if ( $infoCategory['dags'] && $userRights['group_id'] == '' )
{
?>
  <th><?php echo $module->tt('dag'); ?></th>
<?php
}
if ( $infoCategory['expire'] )
{
?>
  <th><?php echo $module->tt('packfield_expiry'); ?></th>
<?php
}
?>
  <th class="pack-col-assign"><?php echo $module->tt('assignment'); ?></th>
 </tr>
<?php
$row = 0;
foreach ( $listPacks as $infoPack )
{
	$packAssigned = $infoPack['assigned']
	                ? $module->getPackAssignedRecord( $infoCategory['id'], $infoPack['id'] )
	                : false;
	$packExpired = ( $infoPack['expiry'] != '' && $infoPack['expiry'] < date('Y-m-d H:i:s') );

?>
 <tr>
  <td style="text-align:center">
   <input type="checkbox" name="pack_id" data-pack-chkbx="<?php echo ++$row; ?>"
             value="<?php echo $module->escape( $infoPack['id'] ); ?>"
             data-block-id="<?php echo $module->escape( $infoPack['block_id'] ); ?>"
             data-assigned="<?php echo $infoPack['assigned'] ? 'true' : 'false'; ?>"
             data-invalid="<?php echo $infoPack['invalid'] ? 'true' : 'false'; ?>"
             data-dag="<?php echo $module->escape( $infoPack['dag'] ?? '' ); ?>"
             data-dag-rcpt="<?php echo $infoPack['dag_rcpt'] ? 'true' : 'false'; ?>"
             data-expired="<?php echo $packExpired ? 'true' : 'false'; ?>"
             title="<?php echo $module->tt('tooltip_chkbx_shift'); ?>">
  </td>
  <td>
<?php
	echo '   ';
	if ( $canConfigure || in_array( $roleName, $infoCategory['roles_edit'] ) )
	{
		echo '<a style="text-decoration:underline dotted" ' .
		     'title="', $module->tt('edit_pack'), '" href="',
		     $module->getUrl( 'packs_edit.php?cat_id=' . $infoCategory['id'] .
		                      '&pack_id=' . $infoPack['id'] ), '">';
	}
	echo $module->escape( $infoPack['id'] );
	if ( $canConfigure || in_array( $roleName, $infoCategory['roles_edit'] ) )
	{
		echo '</a>';
	}
	echo "\n";
?>
   <span style="float:right">
    <?php echo $infoPack['assigned'] ? ( '<i class="far fa-square-check" title="' .
                                         $module->tt('tooltip_pack_assigned') . '"></i>' )
                                     : ''; ?>
    <?php echo $infoPack['invalid'] ? ( '<i class="fas fa-ban" title="' .
                                        $module->escape( $infoPack['invalid_desc'] ) .
                                        '"></i>&nbsp;' ) : '', "\n"; ?>
   </span>
  </td>
<?php
	if ( $infoCategory['blocks'] )
	{
?>
  <td><?php echo $module->escape( $infoPack['block_id'] ); ?></td>
<?php
	}
	if ( $infoCategory['dags'] && $userRights['group_id'] == '' )
	{
?>
  <td><?php echo $infoPack['dag'] == '' ? '&#8212;'
                                        : $module->escape( $listDAGs[ $infoPack['dag'] ] ),
                 $infoPack['dag_rcpt'] ? '' : ' <i class="fas fa-truck"></i>'; ?></td>
<?php
	}
	if ( $infoCategory['expire'] )
	{
?>
  <td><?php echo $module->escape( \DateTimeRC::format_ts_from_ymd( $infoPack['expiry'] ) ); ?></td>
<?php
	}
?>
  <td class="pack-col-assign"><?php
	if ( $packAssigned !== false )
	{
		echo $module->escape( $packAssigned['record'] ), ' (';
		if ( ! empty( $listEvents ) )
		{
			echo $module->escape( $listEvents[ $packAssigned['event'] ] ), ', ';
		}
		echo $module->tt('instance'), ' ', intval( $packAssigned['instance'] ), ')';
	}
	elseif ( $infoPack['assigned'] )
	{
		echo '<i>', $module->tt('no_record'), '</i>';
	}
?></td>
 </tr>
<?php
}
?>
</table>

<p>&nbsp;</p>

<?php
if ( $canConfigure || ( in_array( $roleName, $infoCategory['roles_view'] ) &&
                        $infoCategory['dags'] && $infoCategory['dags_rcpt'] ) ||
                      ( in_array( $roleName, $infoCategory['roles_dags'] ) &&
                        $infoCategory['dags'] && $userRights['group_id'] == '' ) ||
                      in_array( $roleName, $infoCategory['roles_invalid'] ) ||
                      in_array( $roleName, $infoCategory['roles_assign'] ) )
{
?>
<p style="font-size:1.3em">
 <?php echo $module->tt('with_selected_packs'), "\n"; ?>
</p>
<?php
	if ( ( $canConfigure || in_array( $roleName, $infoCategory['roles_view'] ) ) &&
	     $infoCategory['dags'] && $infoCategory['dags_rcpt'] )
	{
?>
<form method="post" class="packmgmt-packrcpt">
 <table class="mod-packmgmt-formtable" style="margin-bottom:10px">
  <tr>
   <th colspan="2"><?php echo $module->tt('mark_packs_rcpt'); ?></th>
  </tr>
  <tr>
   <td colspan="2" class="errmsg" style="color:#58151c;display:none;text-align:left">
    <?php echo $module->tt( 'mark_packs_rcpt_error' .
                            ( $infoCategory['blocks'] ? '_wb' : '_nb' ) ), "\n"; ?>
   </td>
  </tr>
  <tr>
   <td></td>
   <td>
    <input type="hidden" name="action" value="rcpt">
    <input type="hidden" name="packs" value="">
    <input type="submit" value="<?php echo $module->tt('save'); ?>">
   </td>
  </tr>
 </table>
</form>
<?php
	}
	if ( ( $canConfigure || in_array( $roleName, $infoCategory['roles_dags'] ) ) &&
	     $infoCategory['dags'] && $userRights['group_id'] == '' )
	{
?>
<form method="post" class="packmgmt-packissue">
 <table class="mod-packmgmt-formtable" style="margin-bottom:10px">
  <tr>
   <th colspan="2"><?php echo $module->tt('issue_unissue_packs'); ?></th>
  </tr>
  <tr>
   <td colspan="2" class="errmsg" style="color:#58151c;display:none;text-align:left">
    <?php echo $module->tt( 'issue_unissue_packs_error' .
                            ( $infoCategory['blocks'] ? '_wb' : '_nb' ) ), "\n"; ?>
   </td>
  </tr>
  <tr>
   <td><?php echo $module->tt('dag'); ?></td>
   <td>
    <select name="dag_id">
     <option value=""><?php echo $module->tt('opt_none'); ?></option>
<?php
		foreach ( $listDAGs as $dagID => $dagName )
		{
?>
     <option value="<?php echo $dagID; ?>"><?php echo $module->escape( $dagName ); ?></option>
<?php
		}
?>
    </select>
   </td>
  </tr>
  <tr>
   <td></td>
   <td>
    <input type="hidden" name="action" value="issue">
    <input type="hidden" name="packs" value="">
    <input type="submit" value="<?php echo $module->tt('save'); ?>">
   </td>
  </tr>
 </table>
</form>
<?php
	}
	if ( $canConfigure || in_array( $roleName, $infoCategory['roles_invalid'] ) )
	{
?>
<form method="post" class="packmgmt-packinvalid">
 <table class="mod-packmgmt-formtable" style="margin-bottom:10px">
  <tr>
   <th colspan="2"><?php echo $module->tt('mark_unmark_packs_invalid'); ?></th>
  </tr>
  <tr>
   <td colspan="2" class="errmsg" style="color:#58151c;display:none;text-align:left">
    <?php echo $module->tt('mark_unmark_packs_invalid_error'), "\n"; ?>
   </td>
  </tr>
  <tr>
   <td class="desclbl"><?php echo $module->tt('mark_invalid_reason'); ?></td>
   <td>
    <textarea name="invalid_desc"></textarea>
   </td>
  </tr>
  <tr>
   <td></td>
   <td>
    <input type="hidden" name="action" value="invalid">
    <input type="hidden" name="packs" value="">
    <input type="submit" value="<?php echo $module->tt('save'); ?>">
   </td>
  </tr>
  </tr>
 </table>
</form>
<?php
	}
	if ( $canConfigure || in_array( $roleName, $infoCategory['roles_assign'] ) )
	{
?>
<form method="post" class="packmgmt-packassign">
 <table class="mod-packmgmt-formtable" style="margin-bottom:10px">
  <tr>
   <th colspan="2"><?php echo $module->tt('assign_reassign_packs'); ?></th>
  </tr>
  <tr>
   <td colspan="2" class="errmsg" style="color:#58151c;display:none;text-align:left">
    <?php echo $module->tt('assign_reassign_packs_error'), "\n"; ?>
   </td>
  </tr>
  <tr>
   <td><?php echo $module->tt('record'); ?></td>
   <td>
    <select name="record_id">
     <option value=""><?php echo $module->tt('opt_none'); ?></option>
<?php
		foreach ( $listRecords as $recordID )
		{
?>
     <option><?php echo $module->escape( $recordID ); ?></option>
<?php
		}
?>
    </select>
   </td>
  </tr>
<?php
		if ( \REDCap::isLongitudinal() )
		{
?>
  <tr>
   <td><?php echo $module->tt('event'); ?></td>
   <td>
    <select name="event_id">
     <option value=""><?php echo $module->tt('opt_none'); ?></option>
<?php
			foreach ( $listEvents as $eventID => $eventName )
			{
?>
     <option value="<?php echo $eventID; ?>"><?php echo $module->escape( $eventName ); ?></option>
<?php
			}
?>
    </select>
   </td>
  </tr>
<?php
		}
?>
  <tr>
   <td><?php echo $module->tt('instance'); ?></td>
   <td>
    <select name="instance">
     <option value=""><?php echo $module->tt('opt_none'); ?></option>
<?php
		for ( $i = 1; $i <= $maxInst; $i++ )
		{
?>
     <option><?php echo $i; ?></option>
<?php
		}
?>
    </select>
   </td>
  </tr>
  <tr>
   <td></td>
   <td>
    <input type="hidden" name="action" value="assign">
    <input type="hidden" name="packs" value="">
    <input type="submit" value="<?php echo $module->tt('save'); ?>">
   </td>
  </tr>
 </table>
</form>
<?php
	}
}
?>

<script type="text/javascript">
$(function()
{
  $('[data-tblfilter]').each(function()
  {
    var vSelector = $(this).attr('data-tblfilter')
    $(this).on('click', function( ev )
    {
      ev.preventDefault()
      $(vSelector).css('display', $(vSelector).css('display') == 'none' ? '' : 'none')
      $(this).find('i').attr('class', 'far fa-eye' + ( $(vSelector).css('display') == 'none'
                                                       ? '-slash' : '' ) )
    })
  })
  $('[data-tblfilter=".pack-col-assign"]').trigger('click')
  $('.mod-packmgmt-listtable').css('opacity','')
  var vLastChecked = 0
  var vMultiCheck = false
  $(document).on('keyup', function( event )
  {
    if ( event.key == 'Shift' )
    {
      vMultiCheck = false
      vLastChecked = 0
    }
  })
  $('[data-pack-chkbx]').on('click', function( event )
  {
    var vCB = $(this)
    vCB.closest('tr').css( 'background-color', ( vCB.prop('checked') ? '#ccffcc' : '' ) )
    if ( ! vMultiCheck && event.shiftKey && vLastChecked > 0 )
    {
      vMultiCheck = true
      vCBNum = vCB.attr('data-pack-chkbx') - 0
      $('[data-pack-chkbx]').each( function()
      {
        var vIndex = $(this).attr('data-pack-chkbx') - 0
        if ( ( ( vIndex < vLastChecked && vIndex > vCBNum ) ||
               ( vIndex > vLastChecked && vIndex < vCBNum ) ) &&
             $(this).prop('checked') != vCB.prop('checked') )
        {
          $(this).click()
        }
      })
      vMultiCheck = false
      vLastChecked = vCB.attr('data-pack-chkbx') - 0
    }
    else if ( ! vMultiCheck )
    {
      vLastChecked = event.shiftKey ? ( vCB.attr('data-pack-chkbx') - 0 ) : 0
    }
    if ( $('.packmgmt-packrcpt').length > 0 )
    {
      if ( ( [...new Set($('[data-pack-chkbx]').map(function(){return $(this)
              .attr('data-block-id')}).get())].length == 1 ||
             $('[data-pack-chkbx]:checked').map(function(){return $(this).attr('data-block-id')})
             .filter($('[data-pack-chkbx]:not(:checked)').map(function(){return $(this)
             .attr('data-block-id')})).get().length == 0 ) &&
           $('[data-dag-rcpt="true"]:checked').length == 0 &&
           $('[data-pack-chkbx]:checked').length > 0 )
      {
        $('.packmgmt-packrcpt .errmsg').css('display','none')
        $('.packmgmt-packrcpt input, .packmgmt-packrcpt select').prop('disabled',false)
      }
      else
      {
        $('.packmgmt-packrcpt .errmsg').css('display','')
        $('.packmgmt-packrcpt input, .packmgmt-packrcpt select').prop('disabled',true)
      }
      $('.packmgmt-packrcpt [name="packs"]').val(
          JSON.stringify($('[name="pack_id"]:checked')
          .map(function(i,item){return $(item).val()}).get()) )
    }
    if ( $('.packmgmt-packissue').length > 0 )
    {
      if ( ( [...new Set($('[data-pack-chkbx]').map(function(){return $(this)
              .attr('data-block-id')}).get())].length == 1 ||
             $('[data-pack-chkbx]:checked').map(function(){return $(this).attr('data-block-id')})
             .filter($('[data-pack-chkbx]:not(:checked)').map(function(){return $(this)
             .attr('data-block-id')})).get().length == 0 ) &&
           [...new Set($('[data-pack-chkbx]:checked').map(function(){return $(this)
            .attr('data-dag')}).get())].length == 1 &&
           $('[data-assigned="true"]:checked').length == 0 )
      {
        $('.packmgmt-packissue .errmsg').css('display','none')
        $('.packmgmt-packissue input, .packmgmt-packissue select').prop('disabled',false)
      }
      else
      {
        $('.packmgmt-packissue .errmsg').css('display','')
        $('.packmgmt-packissue input, .packmgmt-packissue select').prop('disabled',true)
      }
      $('.packmgmt-packissue [name="packs"]').val(
          JSON.stringify($('[name="pack_id"]:checked')
          .map(function(i,item){return $(item).val()}).get()) )
    }
    if ( $('.packmgmt-packinvalid').length > 0 )
    {
      $('.packmgmt-packinvalid .desclbl').text("<?php echo $module->tt('mark_invalid_reason'); ?>")
      if ( $('[data-assigned="true"]:checked').length == 0 &&
           ( $('[data-invalid="true"]:checked').length == 0 ||
             $('[data-invalid="false"]:checked').length == 0 ) &&
           $('[data-pack-chkbx]:checked').length > 0 )
      {
        $('.packmgmt-packinvalid .errmsg').css('display','none')
        $('.packmgmt-packinvalid input, .packmgmt-packinvalid textarea').prop('disabled',false)
        if ( $('[data-invalid="true"]:checked').length > 0 )
        {
          $('.packmgmt-packinvalid .desclbl')
          .text("<?php echo $module->tt('unmark_invalid_reason'); ?>")
        }
      }
      else
      {
        $('.packmgmt-packinvalid .errmsg').css('display','')
        $('.packmgmt-packinvalid input, .packmgmt-packinvalid textarea').prop('disabled',true)
      }
      $('.packmgmt-packinvalid [name="packs"]').val(
          JSON.stringify($('[name="pack_id"]:checked')
          .map(function(i,item){return $(item).val()}).get()) )
    }
    if ( $('.packmgmt-packassign').length > 0 )
    {
      $('.packmgmt-packassign input[type="submit"]').val("<?php echo $module->tt('save'); ?>")
      $('.packmgmt-packassign select').val('')
      if ( ( $('[data-pack-chkbx]:checked').length == 1 ||
             ( $('[data-pack-chkbx]:checked').length == 2 &&
               $('[data-assigned="true"]:checked').length > 0 ) ) &&
           $('[data-invalid="true"]:checked').length == 0 )
      {
        $('.packmgmt-packassign .errmsg').css('display','none')
        $('.packmgmt-packassign input, .packmgmt-packassign select').prop('disabled',false)
        if ( $('[data-pack-chkbx]:checked').length == 2 )
        {
          $('.packmgmt-packassign input[type="submit"]').val("<?php echo $module->tt('exchange'); ?>")
          $('.packmgmt-packassign select').prop('disabled',true)
        }
      }
      else
      {
        $('.packmgmt-packassign .errmsg').css('display','')
        $('.packmgmt-packassign input, .packmgmt-packassign select').prop('disabled',true)
      }
      $('.packmgmt-packassign [name="packs"]').val(
          JSON.stringify($('[name="pack_id"]:checked')
          .map(function(i,item){return $(item).val()}).get()) )
    }
  })
  $('[data-pack-chkbx]').first().trigger('click').trigger('click')
  if ( $('.packmgmt-packassign').length > 0 )
  {
    var vPackAssignSubmit = false
    $('.packmgmt-packassign').on('submit', function( event )
    {
      if ( vPackAssignSubmit )
      {
        return
      }
      event.preventDefault()
      var vDialogBtn = $('.packmgmt-packassign input[type="submit"]').val()
      var vDialogMsg = ( vDialogBtn == "<?php echo $module->tt('save'); ?>" )
                       ? "<?php echo $module->tt('assign_reassign_packs_confirm_save'); ?>"
                       : "<?php echo $module->tt('assign_reassign_packs_confirm_exchange'); ?>"
      var vDialogMsgPacks = JSON.parse( $('.packmgmt-packassign [name="packs"]').val() )
      vDialogMsg = vDialogMsg.replace('{0}', vDialogMsgPacks[0]).replace('{1}', vDialogMsgPacks[1])
      simpleDialog( vDialogMsg, "<?php echo $module->tt('assign_reassign_packs'); ?>",
                    null, null, null, "<?php echo $module->tt('opt_cancel'); ?>",
                    function(){ vPackAssignSubmit = true; $('.packmgmt-packassign').trigger('submit') },
                    vDialogBtn )
    })
  }
})
</script>
<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';