<?php
/*
 *	Exports the pack management categories as a JSON document.
 */

namespace Nottingham\PackManagement;


if ( ! $module->canConfigure() )
{
	exit;
}

header( 'Content-Type: application/json' );
header( 'Content-Disposition: attachment; filename=' .
        trim( preg_replace( '/[^A-Za-z0-9-]+/', '_', \REDCap::getProjectTitle() ), '_-' ) .
        '_packmgmt_' . gmdate( 'Ymd-His' ) . '.json' );

$projectID = $module->getProjectId();

// Get the pack categories.
$listCategories = [];
$queryCategories = $module->query( 'SELECT ems.`value` FROM redcap_external_module_settings ems ' .
                                   'JOIN redcap_external_modules em ON ems.external_module_id = ' .
                                   'em.external_module_id JOIN JSON_TABLE( ems.`value`, \'$\' ' .
                                   'COLUMNS( id TEXT PATH \'$.id\' ) ) jtbl ' .
                                   'WHERE em.directory_prefix = ? AND ems.`key` LIKE ? ' .
                                   'ORDER BY jtbl.id',
                                   [ $module->getModuleDirectoryBaseName(),
                                     'p' . $module->getProjectId() . '-packcat-%' ] );
echo '["pack_management",', $module->escapeJSString( \REDCap::getProjectTitle() ),
     ',', $module->escapeJSString( $_SERVER['HTTP_HOST'] );
while ( $infoCategory = $queryCategories->fetch_assoc() )
{
	$module->echoText( ",\n" . $infoCategory['value'] );
}
echo "\n]";