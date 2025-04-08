<?php

namespace Nottingham\PackManagement;


if ( ! $module->canConfigure() )
{
	exit;
}


// The super user can run functions as part of a testing framework.
if ( defined('SUPER_USER') && SUPER_USER == 1 && isset( $_GET['runtest'] ) )
{
	if ( $_GET['runtest'] == 'assignMinimPack' )
	{
		$module->echoText( json_encode( $module->assignMinimPack( $_GET['record_id'],
		                                             json_decode( $_GET['list_minim_codes'], true ),
		                                             $_GET['minim_field'] ) ) );
		exit;
	}
	if ( $_GET['runtest'] == 'autoAssignmentCron' )
	{
		$module->echoText( json_encode( $module->autoAssignmentCron( [] ) ) );
		exit;
	}
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
<form method="post" action="<?php echo str_replace( 'ExternalModules/?',
                                                    'ExternalModules/index.php?',
                                                    $module->getUrl( 'configure_edit.php' ) ); ?>">
 <table class="mod-packmgmt-formtable">
  <tr><th colspan="2"><?php echo $module->tt('add_category'); ?></th></tr>
  <tr>
   <td><?php echo $module->tt('uniq_cat_name'); ?></td>
   <td>
    <input type="text" name="cat_id" required
           placeholder="<?php echo $module->tt('uniq_cat_name_ph'); ?>"
           pattern="[a-z0-9_\-]+" title="<?php echo $module->tt('uniq_cat_name_tt'); ?>">
   </td>
  </tr>
  <tr>
   <td></td>
   <td>
    <input type="hidden" name="action" value="new">
    <input type="submit" value="<?php echo $module->tt('add_category'); ?>">
   </td>
  </tr>
 </table>
</form>

<p>&nbsp;</p>

<table class="mod-packmgmt-listtable">
 <tr>
  <th colspan="2" style="font-size:1.3em"><?php echo $module->tt('pack_category'); ?></th>
 </tr>
<?php
foreach ( $listCategories as $infoCategory )
{
?>
 <tr>
  <td>
   <span style="font-size:1.2em"><?php echo $module->escape( $infoCategory['id'] ); ?></span>
   <br>
   <span style="font-size:0.9em">
    <b><?php echo $module->tt('enabled'); ?>:</b>
    <?php echo $module->tt( $infoCategory['enabled'] ? 'opt_yes' : 'opt_no' ); ?> &nbsp;|&nbsp;
    <b><?php echo $module->tt('trigger_label'); ?>:</b> <?php
	if ( $infoCategory['trigger'] == 'F' )
	{
		echo $module->tt('trigger_form'), ' (', $module->escape( $infoCategory['form'] ), ')';
	}
	elseif ( $infoCategory['trigger'] == 'S' )
	{
		echo $module->tt('trigger_select'),
		     ' (', $module->escape( $infoCategory['packfield'] ), ')';
	}
	else
	{
		echo $module->tt( $infoCategory['trigger'] == 'M' ? 'trigger_minim' : 'trigger_auto' );
	}
	if ( $infoCategory['dags'] || $infoCategory['blocks'] || $infoCategory['expire'] )
	{
		echo ' &nbsp;|&nbsp; ';
		if ( $infoCategory['dags'] )
		{
			echo ' <i class="fas fa-users-rectangle" title="',
			     $module->tt('packs_issue_dags'), '"></i>';
		}
		if ( $infoCategory['blocks'] )
		{
			echo ' <i class="fas fa-box" title="', $module->tt('packs_group_blocks'), '"></i>';
		}
		if ( $infoCategory['expire'] )
		{
			echo ' <i class="far fa-calendar-check" title="',
			     $module->tt('packs_have_expiry'), '"></i>';
		}
	}
	echo "\n";
?>
   </span>
  </td>
  <td style="width:75px;text-align:center">
   <a href="<?php echo $module->getUrl( 'configure_edit.php?cat_id=' . $infoCategory['id'] ); ?>">
    <i class="fas fa-pencil-alt fs14"></i> <?php echo $module->tt('edit'), "\n"; ?>
   </a>
  </td>
 </tr>
<?php
}
?>
</table>

<p>&nbsp;</p>

<ul>
 <li>
  <a href="<?php echo $module->getUrl( 'export.php' ); ?>">
   <?php echo $module->tt('pack_categories_export'), "\n"; ?>
  </a>
 </li>
 <li>
  <a href="<?php echo $module->getUrl( 'import.php' ); ?>">
   <?php echo $module->tt('pack_categories_import'), "\n"; ?>
  </a>
 </li>
</ul>

<p>&nbsp;</p>

<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
