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


if ( ! $canConfigure && ! in_array( $roleName, $infoCategory['roles_add'] ) )
{
	exit;
}


// Process any submission.
if ( ! empty( $_POST ) )
{
	// Set up default values for a pack.
	$defaultPack = [ 'id' => '', 'value' => '', 'block_id' => '', 'expiry' => null,
	                 'extrafields' => [], 'dag' => null, 'dag_rcpt' => true, 'assigned' => false,
	                 'invalid' => false, 'invalid_desc' => '' ];
	foreach ( $infoCategory['extrafields'] as $extraFieldName => $infoExtraField )
	{
		$defaultPack['extrafields'][ $extraFieldName ] =
			( $infoExtraField['type'] == 'integer' ) ? 0 : '';
	}
	// Build the list of packs (or single pack).
	$listPacks = [];
	$listPackIDs = [];
	$listErrors = [];
	if ( isset( $_FILES['packs_upload'] ) ) // CSV upload
	{
		if ( is_uploaded_file( $_FILES['packs_upload']['tmp_name'] ) )
		{
			$csvFile = fopen( $_FILES['packs_upload']['tmp_name'], 'r' );
			$headerRow = true;
			$listHeaders = [];
			while ( $csvLine = fgetcsv( $csvFile, 0, $_POST['packs_upload_delimiter'], '"', '' ) )
			{
				if ( $csvLine === false ) break;
				if ( $csvLine[0] === null ) continue;
				// If first row, get/check headers.
				if ( $headerRow )
				{
					$listHeaders = $csvLine;
					$listHeaders[0] = str_replace( chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ),
					                               '', $listHeaders[0] );
					if ( ! in_array( 'id', $listHeaders ) ||
					     ( $infoCategory['blocks'] && ! in_array( 'block_id', $listHeaders ) ) ||
					     ( $infoCategory['expire'] && ! in_array( 'expiry', $listHeaders ) ) )
					{
						$listErrors[] = ['error_required_header_missing'];
						break;
					}
					foreach ( $infoCategory['extrafields'] as $extraFieldName => $infoExtraField )
					{
						if ( $infoExtraField['required'] &&
						     in_array( $extraFieldName, $listHeaders ) )
						{
							$listErrors[] = ['error_required_header_missing'];
							break 2;
						}
					}
					$headerRow = false;
				}
				// Otherwise get/validate values.
				else
				{
					if ( count( $csvLine ) != count( $listHeaders ) )
					{
						$listErrors[] = ['error_incomplete_data_row'];
						break;
					}
					$infoPack = $defaultPack;
					foreach ( $csvLine as $csvIndex => $csvValue )
					{
						$csvField = $listHeaders[ $csvIndex ];
						if ( in_array( $csvField, [ 'id', 'value', 'block_id', 'expiry' ] ) )
						{
							$infoPack[ $csvField ] = $csvValue;
						}
						elseif ( substr( $csvField, 0, 2 ) == 'f_' &&
						         isset( $infoPack['extrafields'][ substr( $csvField, 2 ) ] ) )
						{
							if ( $infoCategory['extrafields'][ substr( $csvField, 2 ) ]['type'] ==
							        'integer' )
							{
								$csvValue = intval( $csvValue );
							}
							$infoPack['extrafields'][ substr( $csvField, 2 ) ] = $csvValue;
						}
					}
					if ( ! isset( $infoPack['id'] ) || $infoPack['id'] == '' )
					{
						$listErrors[] = ['error_incomplete_data_row'];
						break;
					}
					if ( in_array( $infoPack['id'], $listPackIDs ) )
					{
						$listErrors[] = [ 'error_duplicate_pack_id', $infoPack['id'] ];
						continue;
					}
					if ( $infoCategory['expire'] )
					{
						if ( $infoPack['expiry'] == '' )
						{
							$listErrors[] = [ 'error_missing_expiry', $infoPack['id'] ];
						}
						elseif ( ! preg_match( '/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|' .
						                       '3[01])( ([01][0-9]|2[0-3]):[0-5][0-9])?$/',
						                       $infoPack['expiry'] ) )
						{
							$listErrors[] = [ 'error_invalid_date_time',
							                  $infoPack['id'], $infoPack['expiry'] ];
						}
					}
					$listPacks[] = $infoPack;
					$listPackIDs[] = $infoPack['id'];
				}
			}
		}
	}
	else // add single pack
	{
		$infoPack = $defaultPack;
		foreach ( $_POST as $packField => $packValue )
		{
			if ( in_array( $packField, [ 'id', 'value', 'block_id', 'expiry' ] ) )
			{
				$infoPack[ $packField ] = $packValue;
			}
			elseif ( substr( $packField, 0, 2 ) == 'f_' &&
			         isset( $infoPack['extrafields'][ substr( $packField, 2 ) ] ) )
			{
				if ( $infoCategory['extrafields'][ substr( $packField, 2 ) ]['type'] ==
				        'integer' )
				{
					$packValue = intval( $packValue );
				}
				$infoPack['extrafields'][ substr( $packField, 2 ) ] = $packValue;
			}
		}
		if ( ! isset( $infoPack['id'] ) || $infoPack['id'] == '' )
		{
			$listErrors[] = ['error_incomplete_data_row'];
		}
		else
		{
			if ( $infoCategory['expire'] )
			{
				$infoPack['expiry'] = str_replace( 'T', ' ', $infoPack['expiry'] );
				if ( $infoPack['expiry'] == '' )
				{
					$listErrors[] = [ 'error_missing_expiry', $infoPack['id'] ];
				}
				elseif ( ! preg_match( '/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])' .
				                      '( ([01][0-9]|2[0-3]):[0-5][0-9])?$/', $infoPack['expiry'] ) )
				{
					$listErrors[] = [ 'error_invalid_date_time',
					                  $infoPack['id'], $infoPack['expiry'] ];
				}
			}
		}
		$listPacks[] = $infoPack;
		$listPackIDs[] = $infoPack['id'];
	}
	// Get existing pack IDs and check for collisions.
	$module->dbGetLock();
	$queryExistingPacks =
	       $module->query( 'SELECT packlist.id FROM redcap_external_module_settings ems, ' .
	                       'redcap_external_modules em, ' . $module->makePacklistSQL('ems.value') .
	                       'WHERE em.external_module_id = ems.external_module_id ' .
	                       'AND em.directory_prefix = ? AND ems.key = ?',
	                       [ $module->getModuleDirectoryBaseName(),
	                         'p' . $module->getProjectId() . '-packlist-' . $infoCategory['id'] ] );
	while ( $infoExistingPack = $queryExistingPacks->fetch_assoc() )
	{
		if ( in_array( $infoExistingPack['id'], $listPackIDs ) )
		{
			$listErrors[] = [ 'error_duplicate_pack_id', $infoExistingPack['id'] ];
		}
	}
	// Submit the packs into the list.
	if ( empty( $listErrors ) )
	{
		foreach ( $listPacks as $infoPack )
		{
			$module->query( 'UPDATE redcap_external_module_settings SET `value` = ' .
			                'JSON_MERGE_PRESERVE(`value`,?) WHERE external_module_id = ' .
			                '(SELECT external_module_id FROM redcap_external_modules WHERE ' .
			                'directory_prefix = ?) AND `key` = ?',
			                [ json_encode( [$infoPack] ), $module->getModuleDirectoryBaseName(),
			                 'p' . $module->getProjectId() . '-packlist-' . $infoCategory['id'] ] );
			$infoLog = [ 'event' => 'PACK_ADD', 'user' => USERID, 'time' => date( 'Y-m-d H:i:s' ),
			             'data' => $infoPack ];
			$module->query( 'UPDATE redcap_external_module_settings SET `value` = ' .
			                'JSON_MERGE_PRESERVE(`value`,?) WHERE external_module_id = ' .
			                '(SELECT external_module_id FROM redcap_external_modules WHERE ' .
			                'directory_prefix = ?) AND `key` = ?',
			                [ json_encode( [$infoLog] ), $module->getModuleDirectoryBaseName(),
			                 'p' . $module->getProjectId() . '-packlog-' . $infoCategory['id'] ] );
		}
	}
	$module->dbReleaseLock();
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

<?php
if ( ! empty( $_POST ) )
{
	if ( empty( $listErrors ) )
	{
		echo '<div class="mod-packmgmt-okmsg"><p>', $module->tt('add_packs_ok'), '</p></div>';
	}
	else
	{
		echo '<div class="mod-packmgmt-errmsg"><p>', $module->tt('add_packs_err'), '</p><ul>';
		foreach ( $listErrors as $infoError )
		{
			echo '<li>', $module->tt( ...$infoError ), '</li>';
		}
		echo '</ul></div>';
	}
}
?>

<p style="font-size:1.3em">
 <?php echo $module->tt('add_packs'), ' &#8211; ', $module->escape( $infoCategory['id'] ), "\n"; ?>
</p>

<div id="addpackstabs" style="width:97%">
 <ul>
  <li><a href="#singlepack"><?php echo $module->tt('add_single_pack'); ?></a></li>
  <li><a href="#multiplepacks"><?php echo $module->tt('add_multiple_packs'); ?></a></li>
 </ul>
 <div id="singlepack">
  <form method="post">
   <table class="mod-packmgmt-formtable">
    <tr>
     <td><?php echo $module->tt('packfield_id'); ?> *</td>
     <td><input type="text" name="id" required></td>
    </tr>
<?php $module->writePackFields( $infoCategory ); ?>
    <tr>
     <td></td>
     <td><input type="submit" value="<?php echo $module->tt('save'); ?>"></td>
    </tr>
   </table>
  </form>
 </div>
 <div id="multiplepacks">
  <p><?php echo $module->tt('add_multiple_packs_info'); ?></p>
  <table class="mod-packmgmt-listtable">
   <tr>
    <th><?php echo $module->tt('field'); ?></th>
    <th><?php echo $module->tt('description'); ?></th>
    <th><?php echo $module->tt('type'); ?></th>
    <th><?php echo $module->tt('required'); ?></th>
   </tr>
   <tr>
    <td>id</td>
    <td><?php echo $module->tt('packfield_id'); ?></td>
    <td><?php echo $module->getPackFieldTypes('text'); ?></td>
    <td><?php echo $module->tt('opt_yes'); ?></td>
   </tr>
   <tr>
    <td>value</td>
    <td><?php echo $module->tt( 'packfield_value' .
                                ( $infoCategory['trigger'] == 'M' ? '_minim' : '' ) ); ?></td>
    <td><?php echo $module->getPackFieldTypes('text'); ?></td>
    <td><?php echo $module->tt( $infoCategory['trigger'] == 'M' || $infoCategory['valuefield'] != ''
                                ? 'opt_yes' : 'opt_no' ); ?></td>
   </tr>
<?php
if ( $infoCategory['blocks'] )
{
?>
   <tr>
    <td>block_id</td>
    <td><?php echo $module->tt('packfield_block_id'); ?></td>
    <td><?php echo $module->getPackFieldTypes('text'); ?></td>
    <td><?php echo $module->tt('opt_yes'); ?></td>
   </tr>
<?php
}
if ( $infoCategory['expire'] )
{
?>
   <tr>
    <td>expiry</td>
    <td><?php echo $module->tt('packfield_expiry'); ?></td>
    <td><?php echo $module->getPackFieldTypes('datetime'); ?></td>
    <td><?php echo $module->tt('opt_yes'); ?></td>
   </tr>
<?php
}
foreach ( $infoCategory['extrafields'] as $extraFieldName => $infoExtraField )
{
?>
   <tr>
    <td>f_<?php echo $module->escape( $extraFieldName ); ?></td>
    <td><?php echo $module->escape( $infoExtraField['label'] ); ?></td>
    <td><?php echo $module->getPackFieldTypes( $infoExtraField['type'] ); ?></td>
    <td><?php echo $module->tt( $infoExtraField['required'] ? 'opt_yes' : 'opt_no' ); ?></td>
   </tr>
<?php
}
?>
  </table>
  <p>&nbsp;</p>
  <form method="post" enctype="multipart/form-data">
   <p><b><?php echo $module->tt('add_multiple_packs_upload'); ?></b></p>
   <p>
    <input type="file" name="packs_upload">
    &nbsp;&nbsp; <?php echo $module->tt('csv_delimiter'); ?>:
    <select name="packs_upload_delimiter">
     <option value=","><?php echo $module->tt('csv_comma'); ?></option>
     <option value="&#09;"><?php echo $module->tt('csv_tab'); ?></option>
     <option value=";"><?php echo $module->tt('csv_semicolon'); ?></option>
    </select>
    <br><input type="submit" value="<?php echo $module->tt('upload'); ?>" style="margin-top:2px">
   </p>
  </form>
 </div>
</div>
<script type="text/javascript">
 $('#addpackstabs').tabs()
 $('head').append('<style type="text/css">.ui-tabs .ui-tabs-nav li.ui-tabs-active .ui-tabs-anchor' +
                  '{cursor:default;font-weight:bold;color:#000}</style>')
</script>

<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';