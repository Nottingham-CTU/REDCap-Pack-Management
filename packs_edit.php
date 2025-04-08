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


// Check that the user has the rights to view this page (they must be able to edit/delete packs).
if ( ! $canConfigure && ! in_array( $roleName, $infoCategory['roles_edit'] ) )
{
	exit;
}


// Get the project DAGs.
$listDAGs = \REDCap::getGroupNames();


// Get the pack.
$queryPack = $module->query( 'SELECT id, packlist.value, block_id, expiry, extrafields, ' .
                             'dag, dag_rcpt, assigned, invalid ' .
                             'FROM redcap_external_module_settings ems, redcap_external_modules ' .
                             'em, ' . $module->makePacklistSQL('ems.value') .
                             'WHERE em.external_module_id = ems.external_module_id ' .
                             'AND em.directory_prefix = ? AND ems.key = ? ' .
                             'AND packlist.id = ?',
                             [ $module->getModuleDirectoryBaseName(),
                               'p' . $module->getProjectId() . '-packlist-' . $infoCategory['id'],
                               $_GET['pack_id'] ]);
$infoPack = $queryPack->fetch_assoc();
if ( $infoPack == null ||
     ( $infoCategory['dags'] && $userRights['group_id'] != '' &&
       $userRights['group_id'] != $infoPack['dag'] ) )
{
	exit;
}
$infoPack['extrafields'] = json_decode( $infoPack['extrafields'], true );


// Handle form submission.
if ( ! empty( $_POST ) )
{
	// If pack deleted.
	if ( isset( $_POST['action'] ) && $_POST['action'] == 'delete' )
	{
		$module->dbGetLock();
		$module->deletePack( $module->getProjectId(), $infoCategory['id'], $infoPack['id'] );
		$module->updatePackLog( $module->getProjectId(), $infoCategory['id'],
		                        'PACK_DELETE', [ 'id' => $infoPack['id'] ] );
		$module->dbReleaseLock();
		header( 'Location: ' . $module->getUrl( 'packs_list.php?cat_id=' . $infoCategory['id'] ) );
		exit;
	}
	// Otherwise, edit pack.
	$updatedPack = $infoPack;
	foreach ( $_POST as $packField => $packValue )
	{
		if ( in_array( $packField, [ 'value', 'block_id', 'expiry' ] ) )
		{
			$updatedPack[ $packField ] = $packValue;
		}
		elseif ( substr( $packField, 0, 2 ) == 'f_' &&
		         isset( $infoCategory['extrafields'][ substr( $packField, 2 ) ] ) )
		{
			if ( $infoCategory['extrafields'][ substr( $packField, 2 ) ]['type'] ==
			        'integer' )
			{
				$packValue = intval( $packValue );
			}
			$updatedPack['extrafields'][ substr( $packField, 2 ) ] = $packValue;
		}
	}

	if ( $updatedPack === $infoPack )
	{
		header( 'Location: ' . $_SERVER['REQUEST_URI'] );
		exit;
	}

	foreach ( $updatedPack as $property => $value )
	{
		if ( ! in_array( $property, [ 'value', 'block_id', 'expiry', 'extrafields' ] ) )
		{
			unset( $updatedPack[ $property ] );
		}
	}
	$listErrors = [];

	if ( $infoCategory['expire'] )
	{
		$updatedPack['expiry'] = str_replace( 'T', ' ', $updatedPack['expiry'] );
		if ( $updatedPack['expiry'] == '' )
		{
			$listErrors[] = [ 'error_missing_expiry', $infoPack['id'] ];
		}
		elseif ( ! preg_match( '/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])' .
		                      '( ([01][0-9]|2[0-3]):[0-5][0-9])?$/', $updatedPack['expiry'] ) )
		{
			$listErrors[] = [ 'error_invalid_date_time',
			                  $infoPack['id'], $updatedPack['expiry'] ];
		}
	}

	if ( empty( $listErrors ) )
	{
		// Update the pack.
		$listProperties = [];
		$listValues = [];
		foreach ( $updatedPack as $property => $value )
		{
			$listProperties[] = $property;
			$listValues[] = $value;
		}
		$module->dbGetLock();
		$module->updatePackProperty( $module->getProjectId(), $infoCategory['id'], $infoPack['id'],
		                             $listProperties, $listValues );
		$module->updatePackLog( $module->getProjectId(), $infoCategory['id'],
		                        'PACK_UPDATE', [ 'id' => $infoPack['id'] ] + $updatedPack );
		$module->dbReleaseLock();
	}
	$_SESSION['pack_management_listerrors'] = json_encode( $listErrors );
	header( 'Location: ' . $_SERVER['REQUEST_URI'] );
	exit;
}


// Get the pack log.
$queryLog = $module->query( 'SELECT packlog.event, packlog.user, packlog.time, packlog.data ' .
                            'FROM redcap_external_module_settings ems, redcap_external_modules ' .
                            'em, ' . $module->makePacklogSQL('ems.value') .
                            'WHERE em.external_module_id = ems.external_module_id ' .
                            'AND em.directory_prefix = ? AND ems.key = ? ' .
                            'AND packlog.event LIKE ? AND packlog.id = ? ' .
                            'ORDER BY packlog.time DESC',
                            [ $module->getModuleDirectoryBaseName(),
                              'p' . $module->getProjectId() . '-packlog-' . $infoCategory['id'],
                              'PACK\_%', $_GET['pack_id'] ]);
$listLog = [];
while( $infoLog = $queryLog->fetch_assoc() )
{
	$infoLog['data'] = json_decode( $infoLog['data'], true );
	$listLog[] = $infoLog;
}


// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->writeStyle();


?>
<div class="projhdr">
 <i class="fas fa-boxes-stacked"></i> <?php echo $module->tt('module_name'), "\n"; ?>
</div>

<p>
 <a href="<?php echo $module->getUrl( 'packs_list.php?cat_id=' . $infoCategory['id'] ); ?>">
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
		echo '<div class="mod-packmgmt-okmsg"><p>', $module->tt( 'save_pack_ok' ), '</p></div>';
	}
	else
	{
		echo '<div class="mod-packmgmt-errmsg"><p>', $module->tt( 'save_pack_err' ), '</p><ul>';
		foreach ( $listErrors as $infoError )
		{
			echo '<li>', $module->tt( ...$infoError ), '</li>';
		}
		echo '</ul></div>';
	}
}
?>

<p style="font-size:1.3em">
 <?php echo $module->tt('edit_pack'), ' &#8211; ', $module->escape( $infoCategory['id'] ),
            ' &#8211; ', $module->escape( $infoPack['id'] ), "\n"; ?>
</p>

<form method="post">
 <table class="mod-packmgmt-formtable">
  <tr>
   <td><?php echo $module->tt('packfield_id'); ?></td>
   <td><?php echo $module->escape( $infoPack['id'] ); ?></td>
  </tr>
<?php $module->writePackFields( $infoCategory, $infoPack ); ?>
  <tr>
   <td></td>
   <td>
    <input type="submit" value="<?php echo $module->tt('save'); ?>">
<?php
if ( ! $infoPack['assigned'] )
{
?>
    &nbsp;&nbsp;&nbsp;&nbsp;
    <input type="button" value="<?php echo $module->tt('delete_pack'); ?>" id="deletepack">
<?php
}
?>
   </td>
  </tr>
 </table>
</form>
<?php
if ( ! $infoPack['assigned'] )
{
?>
<form method="post" id="deletepackform">
 <input type="hidden" name="action" value="delete">
</form>
<?php
}
?>

<p>&nbsp;</p>
<p>&nbsp;</p>

<p style="font-size:1.3em"><?php echo $module->tt('pack_log'), "\n"; ?></p>

<table class="mod-packmgmt-listtable">
 <tr>
  <th><?php echo $module->tt('event'); ?></th>
  <th><?php echo $module->tt('log_user'); ?></th>
  <th><?php echo $module->tt('log_time'); ?></th>
  <th><?php echo $module->tt('log_data'); ?></th>
 </tr>
<?php
foreach ( $listLog as $infoLog )
{
	unset( $infoLog['data']['id'] );
	$logData = json_encode( $infoLog['data'], JSON_PRETTY_PRINT );
	$logData = ( $logData == '[]' ) ? '' : preg_replace( '/^[{}]$/m', '', $logData );
	$logData = trim( preg_replace( '/^    /m', '', $logData ) );
?>
 <tr>
  <td><?php echo $module->tt( 'log_event_' . $infoLog['event'] ); ?></td>
  <td><?php echo $module->escape( $infoLog['user'] ); ?></td>
  <td><?php echo $module->escape( \DateTimeRC::format_ts_from_ymd( $infoLog['time'],
                                                                   false, true ) ); ?></td>
  <td style="white-space:pre"><?php echo $module->escape( $logData ); ?></td>
 </tr>
<?php
}
?>
</table>

<?php
if ( ! $infoPack['assigned'] )
{
?>
<script type="text/javascript">
  $(function()
  {
    $('#deletepack').click(function()
    {
      simpleDialog( '<?php echo $module->tt('delete_pack_confirm'); ?>',
                    '<?php echo $module->tt('delete_pack'); ?>', null, null, null,
                    '<?php echo $module->tt('opt_cancel'); ?>',
                    function(){ $('#deletepackform').trigger('submit') },
                    '<?php echo $module->tt('delete_pack'); ?>')
    })
  })
</script>
<?php
}
?>

<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';