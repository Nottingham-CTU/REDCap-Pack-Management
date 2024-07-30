<?php

namespace Nottingham\PackManagement;


if ( ! $module->canSeePacks() )
{
	exit;
}


// Get the user role.
$canConfigure = $module->canConfigure();
$roleName = null;
$userRights = $module->getUser()->getRights();
if ( $userRights !== null && $userRights['role_id'] !== null )
{
	$roleName = $userRights['role_name'];
}


// Get the existing pack categories.
$listCategories = [];
$queryCategories = $module->query( 'SELECT ems.`value` FROM redcap_external_module_settings ems ' .
		                           'JOIN redcap_external_modules em ON ems.external_module_id = ' .
		                           'em.external_module_id JOIN JSON_TABLE( ems.`value`, \'$\' ' .
		                           'COLUMNS( id TEXT PATH \'$.id\', enabled INT PATH ' .
		                           '\'$.enabled\' ) ) jtbl WHERE em.directory_prefix = ? AND ' .
		                           'ems.`key` LIKE ? ORDER BY jtbl.enabled DESC, jtbl.id',
		                           [ $module->getModuleDirectoryBaseName(),
		                             'p' . $module->getProjectId() . '-packcat-%' ] );
while ( $infoCategory = $queryCategories->fetch_assoc() )
{
	$listCategories[] = json_decode( $infoCategory['value'], true );
}


// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->writeStyle();


?>
<div class="projhdr">
 <i class="fas fa-boxes-stacked"></i> <?php echo $module->tt('module_name'), "\n"; ?>
</div>

<p>&nbsp;</p>

<table class="mod-packmgmt-listtable">
 <tr>
  <th colspan="3" style="font-size:1.3em"><?php echo $module->tt('pack_category'); ?></th>
 </tr>
<?php
foreach ( $listCategories as $infoCategory )
{
	if ( ! ( $canConfigure || in_array( $roleName, $infoCategory['roles_view'] ) ||
	         ( $infoCategory['dags'] && in_array( $roleName, $infoCategory['roles_dags'] ) ) ||
	         in_array( $roleName, $infoCategory['roles_invalid'] ) ||
	         in_array( $roleName, $infoCategory['roles_assign'] ) ||
	         in_array( $roleName, $infoCategory['roles_add'] ) ||
	         in_array( $roleName, $infoCategory['roles_edit'] ) ) )
	{
		continue;
	}
?>
 <tr>
  <td>
   <span style="font-size:1.2em"><?php echo $module->escape( $infoCategory['id'] ); ?></span>
<?php
	if ( ! $infoCategory['enabled'] )
	{
?>
   &nbsp; <i class="far fa-rectangle-xmark"
             title="<?php echo $module->tt('pack_cat_disabled_desc'); ?>"></i>
<?php
	}
	elseif ( $infoCategory['trigger'] == 'M' )
	{
?>
   &nbsp; <i class="fas fa-shuffle"
             title="<?php echo $module->tt('pack_cat_minim_desc'); ?>"></i>
<?php
	}
?>
  </td>
  <td style="width:125px;text-align:center">
<?php
	if ( $canConfigure || in_array( $roleName, $infoCategory['roles_view'] ) ||
	     ( $infoCategory['dags'] && in_array( $roleName, $infoCategory['roles_dags'] ) ) ||
	     in_array( $roleName, $infoCategory['roles_invalid'] ) ||
	     in_array( $roleName, $infoCategory['roles_assign'] ) ||
	     in_array( $roleName, $infoCategory['roles_edit'] ) )
	{
?>
   <a href="<?php echo $module->getUrl( 'packs_list.php?cat_id=' . $infoCategory['id'] ); ?>">
    <i class="far fa-file-alt fs14"></i> <?php echo $module->tt('view_packs'), "\n"; ?>
   </a>
<?php
	}
?>
  </td>
  <td style="width:125px;text-align:center">
<?php
	if ( $canConfigure || in_array( $roleName, $infoCategory['roles_add'] ) )
	{
?>
   <a href="<?php echo $module->getUrl( 'packs_add.php?cat_id=' . $infoCategory['id'] ); ?>">
    <i class="far fa-square-plus fs14"></i> <?php echo $module->tt('add_packs'), "\n"; ?>
   </a>
<?php
	}
?>
  </td>
 </tr>
<?php
}
?>
</table>

<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';