<?php
/*
 *	Imports the advanced reports configuration from a JSON document.
 */

namespace Nottingham\AdvancedReports;


if ( ! $module->canConfigure() )
{
	exit;
}


$projectID = $module->getProjectID();


$mode = 'upload';
if ( ! empty( $_FILES ) ) // file is uploaded
{
	$mode = 'verify';
	// Check that a file has been uploaded and it is valid.
	if ( ! is_uploaded_file( $_FILES['import_file']['tmp_name'] ) )
	{
		$mode = 'error';
		$error = 'No file uploaded.';
	}
	if ( $mode == 'verify' ) // no error
	{
		$fileData = file_get_contents( $_FILES['import_file']['tmp_name'] );
		$data = json_decode( $fileData, true );
		if ( $data == null || ! is_array( $data ) || ! isset( $data[0] ) || ! isset( $data[1] ) ||
			 ! isset( $data[2] ) || ! is_string( $data[0] ) || ! is_string( $data[1] ) ||
			 ! is_string( $data[2] ) || $data[0] != 'pack_management' )
		{
			$mode = 'error';
			$error = ['pack_categories_import_error_file'];
		}
	}
	if ( $mode == 'verify' ) // no error
	{
		for ( $i = 3; $i < count( $data ); $i++ )
		{
			if ( ! is_array( $data[ $i ] ) || ! isset( $data[ $i ]['id'] ) )
			{
				$mode = 'error';
				$error = ['pack_categories_import_error_cat'];
				break;
			}
			if ( preg_match( '/[^a-z0-9_-]/', $data[ $i ]['id'] ) ||
			     ! isset( $data[ $i ]['enabled'] ) || ! isset( $data[ $i ]['trigger'] ) ||
			     ! isset( $data[ $i ]['form'] ) || ! isset( $data[ $i ]['logic'] ) ||
			     ! isset( $data[ $i ]['nominim'] ) || ! isset( $data[ $i ]['dags'] ) ||
			     ! isset( $data[ $i ]['dags_rcpt'] ) || ! isset( $data[ $i ]['blocks'] ) ||
			     ! isset( $data[ $i ]['expire'] ) || ! isset( $data[ $i ]['packfield'] ) ||
			     ! isset( $data[ $i ]['datefield'] ) || ! isset( $data[ $i ]['valuefield'] ) ||
			     ! isset( $data[ $i ]['extrafields'] ) || !is_array( $data[ $i ]['extrafields'] ) ||
			     ! isset( $data[ $i ]['roles_view'] ) || ! isset( $data[ $i ]['roles_dags'] ) ||
			     ! isset( $data[ $i ]['roles_invalid'] ) || !isset( $data[ $i ]['roles_assign'] ) ||
			     ! isset( $data[ $i ]['roles_add'] ) || ! isset( $data[ $i ]['roles_edit'] ) ||
			     ! is_array( $data[ $i ]['roles_view'] ) ||
			     ! is_array( $data[ $i ]['roles_dags'] ) ||
			     ! is_array( $data[ $i ]['roles_invalid'] ) ||
			     ! is_array( $data[ $i ]['roles_assign'] ) ||
			     ! is_array( $data[ $i ]['roles_add'] ) || ! is_array( $data[ $i ]['roles_edit'] ) )
			{
				$mode = 'error';
				$error = ['pack_categories_import_error_cat2', $data[ $i ]['id'] ];
				break;
			}
		}
	}
	// Parse the uploaded file for differences between the existing reports and those contained
	// within the file. The user will be asked to confirm the changes.
	if ( $mode == 'verify' ) // no error
	{
		$listCurrent = [];
		$queryCategories = $module->query( 'SELECT ems.`value`, jtbl.id ' .
		                                   'FROM redcap_external_module_settings ems ' .
		                                   'JOIN redcap_external_modules em ' .
		                                   'ON ems.external_module_id = em.external_module_id ' .
				                           'JOIN JSON_TABLE( ems.`value`, \'$\' COLUMNS( id TEXT ' .
				                           'PATH \'$.id\', enabled INT PATH \'$.enabled\' ) ) ' .
				                           'jtbl WHERE em.directory_prefix = ? AND ems.`key` ' .
				                           'LIKE ? ORDER BY jtbl.id',
				                           [ $module->getModuleDirectoryBaseName(),
				                             'p' . $module->getProjectId() . '-packcat-%' ] );
		while ( $infoCategory = $queryCategories->fetch_assoc() )
		{
			$listCurrent[ $infoCategory['id'] ] = json_decode( $infoCategory['value'], true );
		}
		$listImported = [];
		for ( $i = 3; $i < count( $data ); $i++ )
		{
			$listImported[ $data[ $i ]['id'] ] = $data[ $i ];
		}
		$listNew = array_diff( array_keys( $listImported ), array_keys( $listCurrent ) );
		$listDeleted = array_diff( array_keys( $listCurrent ), array_keys( $listImported ) );
		$listIdentical = [];
		$listChanged = [];
		foreach ( array_intersect( array_keys( $listCurrent ), array_keys( $listImported ) )
		          as $categoryID )
		{
			if ( $listCurrent[ $categoryID ] === $listImported[ $categoryID ] )
			{
				$listIdentical[] = $categoryID;
			}
			else
			{
				$listChanged[] = $categoryID;
			}
		}
	}
}
elseif ( ! empty( $_POST ) ) // normal POST request (confirming import)
{
	$mode = 'complete';
	// The contents of the file are passed across from the verify stage. If this is valid, the
	// selected changes are applied.
	$fileData = $_POST['import_data'];
	$data = json_decode( $fileData, true );
	if ( $data == null || ! is_array( $data ) || ! isset( $data[0] ) || ! isset( $data[1] ) ||
		 ! isset( $data[2] ) || ! is_string( $data[0] ) || ! is_string( $data[1] ) ||
		 ! is_string( $data[2] ) || $data[0] != 'pack_management' )
	{
		$mode = 'error';
		$error = ['pack_categories_import_error_file'];
	}
	if ( $mode == 'complete' ) // no error
	{
		$listImported = [];
		for ( $i = 3; $i < count( $data ); $i++ )
		{
			$listImported[ $data[ $i ]['id'] ] = $data[ $i ];
		}
		$module->dbGetLock();
		foreach ( $_POST as $key => $val )
		{
			if ( substr( $key, 0, 8 ) == 'cat-add-' )
			{
				// Add new category into project from file.
				$categoryID = substr( $key, 8 );
				$infoCategory = $listImported[ $categoryID ];
				if ( empty( $infoCategory['extrafields'] ) )
				{
					$infoCategory['extrafields'] = new \stdClass;
				}
				$module->setSystemSetting( 'p' . $module->getProjectId() . '-packcat-' .
				                           $infoCategory['id'], json_encode( $infoCategory ) );
				if ( $module->getSystemSetting( 'p' . $module->getProjectId() . '-packlist-' .
				                                $infoCategory['id'] ) === null )
				{
					$module->setSystemSetting( 'p' . $module->getProjectId() . '-packlist-' .
					                           $infoCategory['id'], '[]' );
				}
				if ( $module->getSystemSetting( 'p' . $module->getProjectId() . '-packlog-' .
				                                $infoCategory['id'] ) === null )
				{
					$module->setSystemSetting( 'p' . $module->getProjectId() . '-packlog-' .
					                           $infoCategory['id'], '[]' );
				}
				$module->updatePackLog( $module->getProjectId(), $categoryID, 'CAT_CREATE',
				                        $infoCategory );
			}
			elseif ( substr( $key, 0, 11 ) == 'cat-update-' )
			{
				// Update category with definition from file.
				$categoryID = substr( $key, 11 );
				$infoCategory = $listImported[ $categoryID ];
				if ( empty( $infoCategory['extrafields'] ) )
				{
					$infoCategory['extrafields'] = new \stdClass;
				}
				$module->setSystemSetting( 'p' . $module->getProjectId() . '-packcat-' .
				                           $infoCategory['id'], json_encode( $infoCategory ) );
				$module->updatePackLog( $module->getProjectId(), $categoryID, 'CAT_UPDATE',
				                        $infoCategory );
			}
			elseif ( substr( $key, 0, 11 ) == 'cat-delete-' )
			{
				// Remove category from project.
				$categoryID = substr( $key, 11 );
				$module->removeSystemSetting( 'p' . $module->getProjectId() . '-packcat-' .
				                              $categoryID );
				$module->removeSystemSetting( 'p' . $module->getProjectId() . '-packlist-' .
				                              $categoryID );
				$module->removeSystemSetting( 'p' . $module->getProjectId() . '-packlog-' .
				                              $categoryID );
			}
		}
		$module->dbReleaseLock();
	}
}


// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->writeStyle();


?>
<div class="projhdr">
 <?php echo $module->tt('pack_categories_import'), "\n"; ?>
</div>
<p style="font-size:11px">
 <a href="<?php echo $module->getUrl( 'configure.php' )
?>"><i class="far fa-circle-left"></i> <?php echo $module->tt('back'); ?></a>
</p>
<p>&nbsp;</p>
<?php


// Display the file upload form.
if ( $mode == 'upload' )
{


?>
<form method="post" enctype="multipart/form-data">
 <table class="mod-advrep-formtable">
  <tr>
   <td><?php echo $module->tt('pack_categories_import_file'); ?></td>
   <td>
    <input type="file" name="import_file">
   </td>
  </tr>
  <tr>
   <td></td>
   <td>
    <input type="submit" value="<?php echo $module->tt('pack_categories_import_file'); ?>">
   </td>
  </tr>
 </table>
</form>
<?php


}
// Display the options to confirm the changes to the report definitions introduced by the file.
elseif ( $mode == 'verify' )
{


?>
<p style="font-size:1.1em"><?php echo $module->tt( 'pack_categories_import_info',
                                                   $module->escape( $data[1] ),
                                                   $module->escape( $data[2] ) ); ?></p>
<p>&nbsp;</p>
<form method="post">
 <table class="mod-advrep-formtable">
<?php
	if ( count( $listIdentical ) > 0 )
	{
?>
  <tr>
   <th colspan="2"><?php echo $module->tt('pack_categories_import_identical'); ?></th>
  </tr>
  <tr>
   <td colspan="2" style="text-align:left">
    <ul>
<?php
		foreach ( $listIdentical as $categoryID )
		{
?>
     <li><?php echo $module->escape( $categoryID ); ?></li>
<?php
		}
?>
    </ul>
   </td>
  </tr>
<?php
	}

	if ( count( $listNew ) > 0 )
	{
?>
  <tr>
   <th colspan="2"><?php echo $module->tt('pack_categories_import_new'); ?></th>
  </tr>
<?php
		foreach ( $listNew as $categoryID )
		{
?>
  <tr>
   <td><?php echo $module->escape( $categoryID ); ?></td>
   <td>
    <input type="checkbox" name="cat-add-<?php
			echo $module->escape( $categoryID ); ?>" value="1" checked>
    <?php echo $module->tt('pack_categories_import_add'), "\n"; ?>
    <ul>
<?php
			foreach ( [ 'enabled' => 'enabled', 'trigger' => 'trigger_label',
			            'form' => 'form', 'logic' => 'trig_logic', 'dags' => 'packs_issue_dags',
			            'blocks' => 'packs_group_blocks', 'expire' => 'packs_have_expiry',
			            'packfield' => 'pack_id_proj_field' ]
			          as $configName => $configLabel )
			{
				$configValue = $listImported[ $categoryID ][ $configName ];
				if ( $configValue === null )
				{
					continue;
				}
				if ( is_bool( $configValue ) )
				{
					$configValue = $module->tt( $configValue ? 'opt_yes' : 'opt_no' );
				}
				elseif ( is_string( $configValue ) )
				{
					$configValue = $module->escape( $configValue );
					if ( strpos( $configValue, "\n" ) !== false )
					{
						$configValue = str_replace( [ "\r\n", "\n" ], '</li><li>', $configValue );
						$configValue = "<ul><li>$configValue</li></ul>";
					}
				}
?>
     <li><b><?php echo $module->tt( $configLabel ); ?>:</b> <?php echo $configValue; ?></li>
<?php
			}
?>
    </ul>
   </td>
  </tr>
<?php
		}
	}

	if ( count( $listChanged ) > 0 )
	{
?>
  <tr>
   <th colspan="2"><?php echo $module->tt('pack_categories_import_changed'); ?></th>
  </tr>
<?php
		foreach ( $listChanged as $categoryID )
		{
?>
  <tr>
   <td><?php echo $module->escape( $categoryID ); ?></td>
   <td>
    <input type="checkbox" name="cat-update-<?php
			echo $module->escape( $categoryID ); ?>" value="1" checked>
    <?php echo $module->tt('pack_categories_import_update'), "\n"; ?>
    <br>
    <ul>
<?php
			foreach ( [ 'enabled' => 'enabled', 'trigger' => 'trigger_label',
			            'form' => 'form', 'logic' => 'trig_logic', 'dags' => 'packs_issue_dags',
			            'blocks' => 'packs_group_blocks', 'expire' => 'packs_have_expiry',
			            'packfield' => 'pack_id_proj_field' ]
			          as $configName => $configLabel )
			{
				$configValue = [];
				$configValue['old'] = $listCurrent[ $categoryID ][ $configName ];
				$configValue['new'] = $listImported[ $categoryID ][ $configName ];
				if ( $configValue['old'] === null && $configValue['new'] === null )
				{
					continue;
				}
				foreach ( [ 'old', 'new' ] as $configVer )
				{
					if ( is_bool( $configValue[$configVer] ) )
					{
						$configValue[$configVer] =
								$module->tt( $configValue[$configVer] ? 'opt_yes' : 'opt_no' );
					}
					elseif ( is_string( $configValue[$configVer] ) )
					{
						$configValue[$configVer] = $module->escape( $configValue[$configVer] );
						if ( strpos( $configValue[$configVer], "\n" ) !== false )
						{
							$configValue[$configVer] = str_replace( [ "\r\n", "\n" ], '</li><li>',
							                                        $configValue[$configVer] );
							$configValue[$configVer] = '<ul><li>' . $configValue[$configVer] .
							                           '</li></ul>';
						}
					}
				}

				if ( $configValue['old'] == $configValue['new'] )
				{
?>
     <li><b><?php echo $module->tt( $configLabel ); ?>:</b> <?php echo $configValue['new']; ?></li>
<?php
				}
				else
				{
?>
     <li style="color:#c00;text-decoration:line-through">
      <b><?php echo $module->tt( $configLabel ); ?>:</b> <?php echo $configValue['old']; ?>
     </li>
     <li style="color:#060">
      <b><?php echo $module->tt( $configLabel ); ?>:</b> <?php echo $configValue['new']; ?>
     </li>
<?php
				}
			}
?>
    </ul>
   </td>
  </tr>
<?php
		}
	}

	if ( count( $listDeleted ) > 0 )
	{
?>
  <tr>
   <th colspan="2"><?php echo $module->tt('pack_categories_import_deleted'); ?></th>
  </tr>
<?php
		foreach ( $listDeleted as $categoryID )
		{
?>
  <tr>
   <td><?php echo $module->escape( $categoryID ); ?></td>
   <td>
    <input type="checkbox" name="cat-delete-<?php
			echo $module->escape( $categoryID ); ?>" value="1">
    <?php echo $module->tt('pack_categories_import_delete'), "\n"; ?>
    <ul>
<?php
			foreach ( [ 'enabled' => 'enabled', 'trigger' => 'trigger_label',
			            'form' => 'form', 'logic' => 'trig_logic', 'dags' => 'packs_issue_dags',
			            'blocks' => 'packs_group_blocks', 'expire' => 'packs_have_expiry',
			            'packfield' => 'pack_id_proj_field' ]
			          as $configName => $configLabel )
			{
				$configValue = $listCurrent[ $categoryID ][ $configName ];
				if ( $configValue === null )
				{
					continue;
				}
				if ( is_bool( $configValue ) )
				{
					$configValue = $module->tt( $configValue ? 'opt_yes' : 'opt_no' );
				}
				elseif ( is_string( $configValue ) )
				{
					$configValue = $module->escape( $configValue );
					if ( strpos( $configValue, "\n" ) !== false )
					{
						$configValue = str_replace( [ "\r\n", "\n" ], '</li><li>', $configValue );
						$configValue = "<ul><li>$configValue</li></ul>";
					}
				}
?>
     <li><b><?php echo $module->tt( $configLabel ); ?>:</b> <?php echo $configValue; ?></li>
<?php
			}
?>
    </ul>
   </td>
  </tr>
<?php
		}
	}
	if ( ! empty( $listNew ) || ! empty( $listChanged ) || ! empty( $listDeleted ) )
	{
?>
  <tr>
   <td></td>
   <td>
    <input type="submit" value="<?php echo $module->tt('pack_categories_import_submit'); ?>">
    <input type="hidden" name="import_data" value="<?php echo $module->escape( $fileData ); ?>">
   </td>
  </tr>
<?php
	}
?>
 </table>
</form>
<?php


}
// Display error message.
elseif ( $mode == 'error' )
{


?>
<p style="font-size:14px;color:#f00"><?php echo $module->escape( $module->tt( ...$error ) ); ?></p>
<?php


}
// Display success message.
elseif ( $mode == 'complete' )
{


?>
<p style="font-size:14px"><?php echo $module->tt('pack_categories_import_complete'); ?></p>
<?php


}


// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';

