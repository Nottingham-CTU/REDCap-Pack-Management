<?php

namespace Nottingham\PackManagement;


class PackManagement extends \ExternalModules\AbstractExternalModule
{


	// Determine whether link to module configuration is shown.
	function redcap_module_link_check_display( $project_id, $link )
	{
		if ( $this->canConfigure() )
		{
			return $link;
		}
		return null;
	}


	// Always hide the button for the default REDCap module configuration interface.
	function redcap_module_configure_button_display()
	{
		return ( $this->getProjectId() === null ) ? true : null;
	}



	// Check if the current user can configure the module settings for the project.
	public function canConfigure()
	{
		$user = $this->getUser();
		if ( ! is_object( $user ) )
		{
			return false;
		}
		if ( $user->isSuperUser() )
		{
			return true;
		}
		$userRights = $user->getRights();
		$specificRights = ( $this->getSystemSetting( 'config-require-user-permission' ) == 'true' );
		$moduleName = preg_replace( '/_v[0-9.]+$/', '', $this->getModuleDirectoryName() );
		if ( $specificRights && is_array( $userRights['external_module_config'] ) &&
		     in_array( $moduleName, $userRights['external_module_config'] ) )
		{
			return true;
		}
		if ( ! $specificRights && $userRights['design'] == '1' )
		{
			return true;
		}
		return false;
	}

}