<?php

namespace Nottingham\PackManagement;


class PackManagement extends \ExternalModules\AbstractExternalModule
{


	// Determine whether link to module configuration is shown.
	public function redcap_module_link_check_display( $project_id, $link )
	{
		if ( $this->canConfigure() )
		{
			return $link;
		}
		if ( $link['tt_name'] == 'module_link_config' )
		{
			return null;
		}
		if ( $this->canSeePacks() )
		{
			return $link;
		}
		return null;
	}


	// Always hide the button for the default REDCap module configuration interface.
	public function redcap_module_configure_button_display()
	{
		return ( $this->getProjectId() === null ) ? true : null;
	}



	// Handle mobile app submissions for the selection trigger.
	public function redcap_module_api_before( $projectID, $post )
	{
		if ( $post['mobile_app'] == '1' && $post['uuid'] != '' && $post['content'] == 'record' &&
		     $post['action'] == 'import' && $post['format'] == 'json' )
		{
			$listData = json_decode( $post['data'], true );
			if ( is_array( $listData ) )
			{
				// Get a list of the project's event IDs/unique names. If the project only has one
				// event (i.e. is not longitudinal) then always use that event ID.
				// Note the global Project object is used to get these values as the REDCap class
				// functions do not work in this context.
				$singleEvent = null;
				$listEvents = $GLOBALS['Proj']->getUniqueEventNames( null );
				if ( count( $listEvents ) == 1 )
				{
					$singleEvent = array_keys( $listEvents )[0];
				}
				$recordIDField = $GLOBALS['Proj']->table_pk;
				// Get the category IDs / pack fields for the active selection trigger categories.
				$queryCat = $this->query( 'SELECT JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.id\')) ' .
				                          'AS id, JSON_UNQUOTE(JSON_EXTRACT(`value`,' .
				                          '\'$.packfield\')) AS packfield ' .
				                          'FROM redcap_external_module_settings ems ' .
				                          'JOIN redcap_external_modules em ' .
				                          'ON ems.external_module_id = em.external_module_id ' .
				                          'WHERE em.directory_prefix = ? AND ems.`key` LIKE ? ' .
				                          'AND JSON_CONTAINS(`value`,\'true\',\'$.enabled\') ' .
				                          'AND ( JSON_CONTAINS(`value`,\'"S"\',\'$.trigger\') )',
				                          [ $this->getModuleDirectoryBaseName(),
				                            'p' . $projectID . '-packcat-%' ] );
				while( $infoCat = $queryCat->fetch_assoc() )
				{
					$catID = $infoCat['id'];
					$packField = $infoCat['packfield'];
					// For each submitted record...
					foreach ( $listData as $infoData )
					{
						// If the record contains the pack field...
						if ( isset( $infoData[ $packField ] ) )
						{
							// Identify the pack ID, record, event and instance.
							$packID = $infoData[ $packField ];
							$recordID = $infoData[ $recordIDField ];
							$eventID = $singleEvent ??
							           array_search( $infoData['redcap_event_name'], $listEvents );
							$repeatInstance = $infoData['redcap_repeat_instance'] ?? '';
							$repeatInstance = ( $repeatInstance == '' ? 1 : $repeatInstance );
							// Don't allow submission if the pack field already has a value.
							if ( $this->getValues( $projectID, $recordID, $eventID, $repeatInstance,
							                       $packField )[ $packField ] != '' )
							{
								return $this->tt( 'error_selection_pack_submit', $packID, $catID );
							}
							// Assign the pack. Don't allow submission if pack cannot be assigned.
							$infoPack = $this->choosePack( $catID, $recordID, null, null, $packID );
							if ( $infoPack === false )
							{
								return $this->tt( 'error_selection_pack_submit', $packID, $catID );
							}
							$this->updateValues( $projectID, $recordID, $eventID,
							                     $repeatInstance, $infoPack );
						}
					}
				}
			}
		}
	}



	public function redcap_every_page_before_render()
	{
		// Provide pack management settings to the REDCap UI Tweaker simplified view.
		if ( $this->isModuleEnabled('redcap_ui_tweaker') )
		{
			$UITweaker = \ExternalModules\ExternalModules::getModuleInstance('redcap_ui_tweaker');
			if ( $UITweaker->areExtModFuncExpected() )
			{
				$UITweaker->addExtModFunc( $this->getModuleDirectoryBaseName(),
				                           function( $data )
				                           {
				                               if ( substr( $data['setting'], 0, 8 ) != 'packcat-' )
				                               {
				                                   return false;
				                               }
				                               $input = json_decode( $data['value'], true );
				                               $output = '';
				                               foreach ( $input as $key => $val )
				                               {
				                                   if ( $key == 'extrafields' )
				                                   {
				                                       foreach ( $val as $key2 => $val2 )
				                                       {
				                                           $output .= "\nextrafields[";
				                                           $output .= json_encode( $key2 );
				                                           $output .= ']: ' . json_encode( $val2 );
				                                       }
				                                       continue;
				                                   }
				                                   $output .= "\n" . $key . ': ';
				                                   $output .= is_string( $val )
				                                              ? $val : json_encode( $val );
				                               }
				                               $data['value'] = substr( $output, 1 );
				                               return $data;
				                           } );
			}
		}

		// If a data entry page, check for active selection triggers for the current form.
		$this->listSelectionPackFields = [];
		if ( ( substr( PAGE_FULL, strlen( APP_PATH_WEBROOT ), 10 ) == 'DataEntry/' &&
		       isset( $_GET['id'] ) && isset( $_GET['page'] ) && $_GET['page'] != '' ) ||
		     ( substr( PAGE_FULL, 0, strlen( APP_PATH_SURVEY ) ) == APP_PATH_SURVEY &&
		       isset( $_GET['s'] ) ) )
		{
			if ( substr( PAGE_FULL, 0, strlen( APP_PATH_SURVEY ) ) == APP_PATH_SURVEY )
			{
				$queryRecord = $this->query( 'SELECT record, event_id event, instance, form_name ' .
				                             'FROM redcap_surveys_response sr ' .
				                             'JOIN redcap_surveys_participants sp ' .
				                             'ON sr.participant_id = sp.participant_id ' .
				                             'JOIN redcap_surveys s ON sp.survey_id = s.survey_id' .
				                             ' WHERE sp.hash = ?', [ $_GET['s'] ] );
				$infoRecord = $queryRecord->fetch_assoc();
			}
			else
			{
				$infoRecord = [ 'record' => $_GET['id'],
				                'event' => intval( $_GET['event_id'] ?? '' ),
				                'instance' => intval( $_GET['instance'] ?? 1 ),
				                'form_name' => $_GET['page'] ];
				if ( $infoRecord['event'] == '' )
				{
					$queryRecord = $this->query( 'SELECT event_id FROM ' . \REDCap::getDataTable() .
					                             ' WHERE project_id = ? LIMIT 1',
					                             [ $this->getProjectId() ] );
					$infoRecord['event'] = $queryRecord->fetch_assoc()['event_id'];
				}
			}
			$listFields = array_keys( \REDCap::getDataDictionary( $this->getProjectId(), 'array',
			                                                      false, null,
			                                                      $infoRecord['form_name'] ) );
			$queryCat = $this->query( 'SELECT JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.id\')) AS id, ' .
			                          'JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.logic\')) AS logic, ' .
			                          'JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.packfield\')) ' .
			                          'AS packfield, JSON_UNQUOTE(JSON_EXTRACT(`value`,' .
			                          '\'$.valuefield\')) AS valuefield ' .
			                          'FROM redcap_external_module_settings ems ' .
			                          'JOIN redcap_external_modules em ' .
			                          'ON ems.external_module_id = em.external_module_id ' .
			                          'WHERE em.directory_prefix = ? AND ems.`key` LIKE ? ' .
			                          'AND JSON_CONTAINS(`value`,\'true\',\'$.enabled\') ' .
			                          'AND JSON_CONTAINS(`value`,\'"S"\',\'$.trigger\')',
			                          [ $this->getModuleDirectoryBaseName(),
			                            'p' . $this->getProjectId() . '-packcat-%' ] );
			while ( $infoCat = $queryCat->fetch_assoc() )
			{
				if ( ! in_array( $infoCat['packfield'], $listFields ) )
				{
					continue;
				}
				$infoData = $this->getValues( $this->getProjectId(), $infoRecord['record'],
				                              $infoRecord['event'], $infoRecord['instance'],
				                              $infoCat['valuefield'] == '' ? $infoCat['packfield']
				                              : [ $infoCat['packfield'], $infoCat['valuefield'] ] );
				if ( $infoData[ $infoCat['packfield'] ] != '' )
				{
					continue;
				}
				$queryRptForm = $this->query( 'SELECT m.form_name FROM redcap_metadata m ' .
				                              'JOIN redcap_events_repeat er ON m.form_name = ' .
				                              'er.form_name WHERE m.project_id = ? ' .
				                              'AND er.event_id = ? AND m.field_name = ?',
				                              [ $this->getProjectId(), $infoRecord['event'],
				                                $infoCat['packfield'] ] );
				if ( $infoRptForm = $queryRptForm->fetch_assoc() )
				{
					$rptForm = $infoRptForm['form_name'];
				}
				else
				{
					$rptForm = null;
				}
				$packValue = ( $infoCat['valuefield'] == '' ||
				               $infoData[ $infoCat['valuefield'] ] ?? '' == '' )
				             ? null : $infoData[ $infoCat['valuefield'] ];
				$listPacks = [];
				if ( ( $infoCat['valuefield'] == '' || $packValue !== null ) &&
				     ( $infoCat['logic'] == '' ||
				       \REDCap::evaluateLogic( $infoCat['logic'], $this->getProjectId(),
				                               $infoRecord['record'], $infoRecord['event'],
				                               $infoRecord['instance'], $rptForm,
				                               $infoRecord['form_name'] ) ) )
				{
					$listPacks = $this->getAssignablePacks( $infoCat['id'],
					                                        $infoRecord['record'], $packValue );
					$listPacks = $this->getSelectionList( $infoCat['id'], $listPacks );
				}
				$packsEnum = '';
				foreach ( $listPacks as $packID => $packLabel )
				{
					if ( $packsEnum != '' )
					{
						$packsEnum .= ' \n ';
					}
					$packsEnum .= $packID . ', ' . str_replace( '\n', '\ n', $packLabel );
				}
				$GLOBALS['Proj']->metadata[ $infoCat['packfield'] ]['element_type'] = 'select';
				$GLOBALS['Proj']->metadata[ $infoCat['packfield'] ]['element_enum'] = $packsEnum;
				$GLOBALS['Proj']->metadata[ $infoCat['packfield'] ]['misc'] .= ' @NOMISSING';
				$this->listSelectionPackFields[] = $infoCat['packfield'];
			}
		}
	}



	// Identify the pack ID fields on forms/surveys where the selection trigger is used.
	public function redcap_data_entry_form( $projectID, $recordID, $instrument, $eventID, $groupID,
	                                        $repeatInstance )
	{
		if ( ! empty( $this->listSelectionPackFields ) )
		{
			$fieldsString = $this->escapeJSString( json_encode( $this->listSelectionPackFields ) );
?>
<script type="text/javascript">
  $(function()
  {
    var vFields = JSON.parse(<?php echo $fieldsString; ?>)
    vFields.forEach( function ( vField )
    {
      $('[name="' + vField + '"]').after('<input type="hidden" name="' + vField +
                                         ':packmanagement-selection" value="1">')
    } )
  })
</script>
<?php
		}
	}

	public function redcap_survey_page( $projectID, $recordID, $instrument, $eventID, $groupID,
	                                    $surveyHash, $responseID, $repeatInstance )
	{
		$this->redcap_data_entry_form( $projectID, $recordID, $instrument, $eventID, $groupID,
		                               $repeatInstance );
	}



	// Run whenever a record is saved. This will trigger pack assignments where the assignment
	// mode is Automatic, or Form submission for the current form.
	public function redcap_save_record( $projectID, $recordID, $instrument, $eventID, $groupID,
	                                    $surveyHash, $responseID, $repeatInstance )
	{
		// Do not trigger if a form is being deleted.
		if ( ! isset( $_POST[ $instrument . '_complete' ] ) )
		{
			return;
		}

		$this->dbGetLock();
		// Get the pack categories which are relevant to this submission, excluding selection
		// triggers which are handled later.
		$queryCat = $this->query( 'SELECT JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.id\')) AS id, ' .
		                          'JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.logic\')) AS logic, ' .
		                          'JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.packfield\')) ' .
		                          'AS packfield, JSON_UNQUOTE(JSON_EXTRACT(`value`,' .
		                          '\'$.valuefield\')) AS valuefield ' .
		                          'FROM redcap_external_module_settings ems ' .
		                          'JOIN redcap_external_modules em ' .
		                          'ON ems.external_module_id = em.external_module_id ' .
		                          'WHERE em.directory_prefix = ? AND ems.`key` LIKE ? ' .
		                          'AND JSON_CONTAINS(`value`,\'true\',\'$.enabled\') ' .
		                          'AND ( JSON_CONTAINS(`value`,\'"A"\',\'$.trigger\') ' .
		                            'OR ( JSON_CONTAINS(`value`,\'"F"\',\'$.trigger\') ' .
		                              'AND JSON_CONTAINS(`value`,?,\'$.form\') ) )',
		                          [ $this->getModuleDirectoryBaseName(),
		                            'p' . $this->getProjectId() . '-packcat-%',
		                            json_encode( $instrument ) ] );
		$listCat = [];
		while ( $infoCat = $queryCat->fetch_assoc() )
		{
			// Check that the pack field on the record/event/instance is empty and that the
			// logic (if specified) evaluates as true.
			if ( $this->getValues( $projectID, $recordID, $eventID, $repeatInstance,
			                       $infoCat['packfield'] )[ $infoCat['packfield'] ] == '' &&
			     ( $infoCat['logic'] == '' ||
			       \REDCap::evaluateLogic( $infoCat['logic'], $projectID, $recordID, $eventID,
			                               $repeatInstance, $instrument, $instrument ) ) )
			{
				$listCat[ $infoCat['id'] ] = $infoCat['valuefield'];
			}
		}
		foreach ( $listCat as $catID => $valueField )
		{
			// Get and assign pack for the pack category.
			$packValue = null;
			if ( $valueField != '' )
			{
				$packValue = $this->getValues( $projectID, $recordID, $eventID,
				                               $repeatInstance, $valueField )[ $valueField ];
			}
			$infoData = $this->choosePack( $catID, $recordID, $packValue );
			// Save the data to the record.
			if ( $infoData !== false )
			{
				$this->updateValues( $projectID, $recordID, $eventID, $repeatInstance, $infoData );
			}
		}

		// Get the pack categories with selection trigger.
		$queryCat = $this->query( 'SELECT JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.id\')) AS id, ' .
		                          'JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.packfield\')) ' .
		                          'AS packfield ' .
		                          'FROM redcap_external_module_settings ems ' .
		                          'JOIN redcap_external_modules em ' .
		                          'ON ems.external_module_id = em.external_module_id ' .
		                          'WHERE em.directory_prefix = ? AND ems.`key` LIKE ? ' .
		                          'AND JSON_CONTAINS(`value`,\'true\',\'$.enabled\') ' .
		                          'AND ( JSON_CONTAINS(`value`,\'"S"\',\'$.trigger\') )',
		                          [ $this->getModuleDirectoryBaseName(),
		                            'p' . $this->getProjectId() . '-packcat-%' ] );
		$listCat = [];
		while( $infoCat = $queryCat->fetch_assoc() )
		{
			// Only consider categories with a submitted selection field.
			if ( isset( $_POST[ $infoCat['packfield'] . ':packmanagement-selection' ] ) )
			{
				// Get the pack field to be assigned for this category.
				$listCat[ $infoCat['id'] ] = $infoCat['packfield'];
			}
		}
		foreach ( $listCat as $catID => $packField )
		{
			// Get and assign pack for the pack category.
			$infoData = $this->choosePack( $catID, $recordID, null, null, $_POST[ $packField ] );
			// Save the data to the record.
			$this->updateValues( $projectID, $recordID, $eventID, $repeatInstance,
			                     ( $infoData !== false ? $infoData : [ $packField => '' ] ) );
		}
		$this->dbReleaseLock();
	}



	// Run on schedule to trigger pack assignments automatically where required.
	public function autoAssignmentCron( $infoCron )
	{
		$startTimestamp = time();
		$activeProjects = implode( ',', $this->getProjectsWithModuleEnabled() );
		if ( $activeProjects == '' )
		{
			return;
		}
		$queryCat = $this->query( 'SELECT JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.id\')) AS id, ' .
		                          'JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.logic\')) AS logic, ' .
		                          'JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.packfield\')) ' .
		                          'AS packfield, JSON_UNQUOTE(JSON_EXTRACT(`value`,' .
		                          '\'$.valuefield\')) AS valuefield, ' .
		                          'REGEXP_SUBSTR(`key`,\'[0-9]+\') project_id, ' .
		                          'IFNULL((SELECT `value` FROM redcap_external_module_settings ' .
		                          'ems1 WHERE ems1.`key` = REGEXP_REPLACE(ems.`key`,' .
		                          '\'^p([0-9]+)-packcat-\',\'p$1-packcatats-\')), \'0\') ts ' .
		                          'FROM redcap_external_module_settings ems ' .
		                          'JOIN redcap_external_modules em ' .
		                          'ON ems.external_module_id = em.external_module_id ' .
		                          'WHERE em.directory_prefix = ? AND ems.`key` LIKE ? ' .
		                          'AND JSON_CONTAINS(`value`,\'true\',\'$.enabled\') ' .
		                          'AND JSON_CONTAINS(`value`,\'"A"\',\'$.trigger\') ' .
		                          'HAVING project_id IN (' . $activeProjects . ') ' .
		                          'ORDER BY CAST(ts AS decimal)',
		                          [ $this->getModuleDirectoryBaseName(), 'p%-packcat-%' ] );
		while ( $infoCat = $queryCat->fetch_assoc() )
		{
			if ( time() - $startTimestamp > 300 )
			{
				break;
			}
			// Get the project ID and set the project context.
			$projectID = $infoCat['project_id'];
			$_GET['pid'] = $projectID;
			$GLOBALS['Proj'] = new \Project( $projectID );
			// Get list of records/events/instances which contain data.
			$queryRecords =
				$this->query( 'SELECT record, event_id, max(ifnull(instance,1)) max_instance ' .
				              'FROM ' . $this->getDataTable( $projectID ) . ' ' .
				              'WHERE project_id = ? GROUP BY record, event_id',
				              [ $projectID ] );
			$listRptForm = [];
			// For each record/event.
			while ( $infoRecord = $queryRecords->fetch_assoc() )
			{
				$recordID = $infoRecord['record'];
				$eventID = $infoRecord['event_id'];
				$maxInstance = $infoRecord['max_instance'];
				// Get repeating instance form name if applicable.
				if ( ! array_key_exists( $eventID, $listRptForm ) )
				{
					$queryRptForm = $this->query( 'SELECT m.form_name FROM redcap_metadata m ' .
					                              'JOIN redcap_events_repeat er ON m.form_name = ' .
					                              'er.form_name WHERE m.project_id = ? ' .
					                              'AND er.event_id = ? AND m.field_name = ?',
					                              [ $projectID, $eventID, $infoCat['packfield'] ] );
					if ( $infoRptForm = $queryRptForm->fetch_assoc() )
					{
						$listRptForm[ $eventID ] = $infoRptForm['form_name'];
					}
					else
					{
						$listRptForm[ $eventID ] = null;
					}
				}
				$rptForm = $listRptForm[ $eventID ];
				// For each instance of this record/event.
				for ( $instanceNum = 1; $instanceNum <= $maxInstance; $instanceNum++ )
				{
					$this->dbGetLock();
					// Check that the pack field on the record/event/instance is empty and that the
					// logic (if specified) evaluates as true.
					if ( ! ( $this->getValues( $projectID, $recordID, $eventID, $instanceNum,
					                       $infoCat['packfield'] )[ $infoCat['packfield'] ] == '' &&
					         ( $infoCat['logic'] == '' ||
					           \REDCap::evaluateLogic( $infoCat['logic'], $projectID, $recordID,
					                                   $eventID, $instanceNum, $rptForm ) ) ) )
					{
						continue;
					}
					// Get and assign pack for the pack category.
					$packValue = null;
					if ( $infoCat['valuefield'] != '' )
					{
						$packValue = $this->getValues( $projectID, $recordID, $eventID,
						                               $instanceNum, $infoCat['valuefield'] )
						                             [ $infoCat['valuefield'] ];
					}
					$infoData = $this->choosePack( $infoCat['id'], $recordID, $packValue );
					// Save the data to the record.
					if ( $infoData !== false )
					{
						$this->updateValues( $projectID, $recordID, $eventID,
						                     $instanceNum, $infoData );
					}
					$this->dbReleaseLock();
				}
			}
			// Update last-run timestamp for this pack category.
			$this->setSystemSetting( 'p' . $projectID . '-packcatats-' . $infoCat['id'], time() );
		}
	}



	// Assign a minimization pack as requested by the Minimization module.
	public function assignMinimPack( $recordID, $listMinimCodes, $randoField = '', $packID = null )
	{
		$this->dbGetLock();
		// Get pack category for minimization field.
		$queryCat = $this->query( 'SELECT JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.id\')) AS ' .
		                          'id, JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.packfield\')) AS ' .
		                          'packfield, JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.nominim\')) ' .
		                          'AS nominim FROM redcap_external_module_settings ems JOIN ' .
		                          'redcap_external_modules em ON ems.external_module_id = ' .
		                          'em.external_module_id WHERE em.directory_prefix = ? AND ' .
		                          'ems.`key` LIKE ? AND ' .
		                          'JSON_CONTAINS(`value`,\'"M"\',\'$.trigger\') ' .
		                          'AND JSON_CONTAINS(`value`,\'true\',\'$.enabled\')' .
		                          'AND JSON_CONTAINS(`value`,?,\'$.valuefield\')',
		                          [ $this->getModuleDirectoryBaseName(),
		                            'p' . $this->getProjectId() . '-packcat-%',
		                            json_encode( $randoField ) ] );
		$infoCat = $queryCat->fetch_assoc();
		if ( empty( $infoCat ) )
		{
			$this->dbReleaseLock();
			return false;
		}
		// Assign pack
		$infoPack = false;
		$chosenCode = null;
		if ( $packID === null )
		{
			// Standard minimization, choose a pack for the minimized allocation.
			foreach ( $listMinimCodes as $minimCode )
			{
				$infoPack = $this->choosePack( $infoCat['id'], $recordID, $minimCode,
				                               ( $infoCat['nominim'] == 'S'
				                                 ? null : $listMinimCodes ) );
				$chosenCode = $minimCode;
				if ( $infoPack !== false || $infoCat['nominim'] != 'S' )
				{
					break;
				}
			}
		}
		else
		{
			// Manual randomization, choose the specified pack ID.
			$infoPack = $this->choosePack( $infoCat['id'], $recordID, null, null, $packID );
			if ( $infoPack !== false )
			{
				$chosenCode = $this->getPackProperty( $this->getProjectId(), $infoCat['id'],
				                                      $packID, 'value' );
			}
		}
		$this->dbReleaseLock();
		if ( $infoPack === false )
		{
			return false;
		}
		// Return the data to the Minimization module.
		$infoRando = [ 'packID' => $infoPack[ $infoCat['packfield'] ], 'randoCode' => $chosenCode ];
		unset( $infoPack[ $infoCat['packfield'] ] );
		$infoRando['extraData'] = $infoPack;
		return $infoRando;
	}



	// Choose a pack from the specified pack category and mark it as assigned.
	public function choosePack( $catID, $recordID, $value = null, $reqPackValues = null,
	                            $packID = null, $ignoreExpiry = false )
	{
		$this->dbGetLock();
		// Get the pack category details.
		$queryCat = $this->query( 'SELECT ems.`value` AS category ' .
		                          'FROM redcap_external_module_settings ems JOIN ' .
		                          'redcap_external_modules em ON ems.external_module_id = ' .
		                          'em.external_module_id WHERE em.directory_prefix = ? AND ' .
		                          'ems.`key` = ? AND JSON_CONTAINS(`value`,\'true\',\'$.enabled\')',
		                          [ $this->getModuleDirectoryBaseName(),
		                            'p' . $this->getProjectId() . '-packcat-' . $catID ] );
		$infoCat = $queryCat->fetch_assoc();
		if ( ! is_array( $infoCat ) )
		{
			$this->dbReleaseLock();
			return false;
		}
		$infoCat = json_decode( $infoCat['category'], true );
		$paramsPacks = [ $this->getModuleDirectoryBaseName(),
		                 'p' . $this->getProjectId() . '-packlist-' . $catID ];
		// If packs are assigned to DAGs, prepare to filter the list of packs by DAG.
		$sqlPacks = '';
		if ( $infoCat['dags'] )
		{
			// Get the record DAG.
			$queryDAG = $this->query( 'SELECT `value` AS dag FROM ' .
			                          $this->getDataTable( $this->getProjectId() ) .
			                          ' WHERE project_id = ? AND record = ? AND field_name = ?',
			                          [ $this->getProjectId(), $recordID, '__GROUPID__' ] );
			$infoDAG = $queryDAG->fetch_assoc();
			if ( empty( $infoDAG ) )
			{
				// Record not in a DAG, cannot assign pack.
				$this->dbReleaseLock();
				return false;
			}
			$sqlPacks .= ' AND packlist.dag = ? AND packlist.dag_rcpt = 1';
			$paramsPacks[] = $infoDAG['dag'];
		}
		// If packs expire, prepare to exclude expired packs.
		if ( ! $ignoreExpiry && $infoCat['expire'] )
		{
			// Use expire buffer (number of hours), or 10 minutes if buffer = 0;
			$expireBuffer = $infoCat['expire_buf'] ?? 0;
			$expireBuffer = ( $expireBuffer == 0 ) ? 600 : ( $expireBuffer * 3600 );
			$now = date( 'Y-m-d H:i:s', time() + $expireBuffer );
			$sqlPacks .= ' AND packlist.expiry > ?';
			$paramsPacks[] = $now;
		}
		// If the pack must match a value, prepare to filter by it.
		if ( $value !== null )
		{
			$sqlPacks2 .= ' AND value = ?';
			$paramsPacks[] = $value;
		}
		// If a specific pack ID is required, prepare to filter by it.
		if ( $packID !== null )
		{
			$sqlPacks2 .= ' AND id = ?';
			$paramsPacks[] = $packID;
		}
		// If a required pack value does not exist amongst the available packs, return 0 rows.
		if ( $reqPackValues !== null && is_array( $reqPackValues ) )
		{
			foreach ( $reqPackValues as $reqPackValue )
			{
				$sqlPacks2 .= ' AND EXISTS( SELECT 1 FROM packs p' .
				              ' WHERE p.assigned = 0 AND p.value = ? )';
				$paramsPacks[] = $reqPackValue;
			}
		}
		// Get the available packs, filtered as required.  Up to 3 packs are returned,
		// those closest to expiry and from partially used blocks are preferred.
		$queryPacks = $this->query( 'WITH packs AS (' .
		                            'SELECT id, block_id, packlist.value, expiry, ' .
		                            'extrafields, assigned ' .
		                            'FROM redcap_external_module_settings ems, ' .
		                            'redcap_external_modules em, ' .
		                            $this->makePacklistSQL('ems.value') .
		                            'WHERE em.external_module_id = ems.external_module_id ' .
		                            'AND em.directory_prefix = ? AND ems.key = ? ' .
		                            'AND packlist.invalid = 0' . $sqlPacks . ') ' .
		                            'SELECT id, extrafields, (SELECT count(*) FROM packs p ' .
		                            'WHERE p.assigned = 0) count FROM packs WHERE assigned = 0 ' .
		                            $sqlPacks2 . ' ORDER BY expiry, if((SELECT count(*) ' .
		                            'FROM packs p WHERE packs.block_id = p.block_id ' .
		                            'AND p.assigned = 1)>0,0,1), (SELECT count(*) ' .
		                            'FROM packs p WHERE packs.block_id = p.block_id ' .
		                            'AND p.assigned = 0), id LIMIT 3',
		                            $paramsPacks );
		// Pick a pack.
		$infoPack = null;
		while ( $nextPack = $queryPacks->fetch_assoc() )
		{
			// Use the 1st pack returned 67% of the time, 2nd pack 22%, 3rd pack 11%.
			$infoPack = $nextPack;
			if ( $packID !== null || random_int( 0, 2 ) > 0 )
			{
				break;
			}
		}
		if ( $infoPack === null )
		{
			// No pack available.
			$this->dbReleaseLock();
			return false;
		}
		// Set the pack as assigned and write the pack assignment to the log.
		$this->updatePackProperty( $this->getProjectId(), $catID,
		                           $infoPack['id'], 'assigned', true );
		$this->updatePackLog( $this->getProjectId(), $infoCat['id'], 'PACK_ASSIGN',
		                      [ 'id' => $infoPack['id'], 'record' => $recordID ] );
		$this->dbReleaseLock();
		// Return the fields/values to be updated on the record.
		$infoValues = [ $infoCat['packfield'] => $infoPack['id'] ];
		if ( $infoCat['datefield'] != '' )
		{
			$infoValues[ $infoCat['datefield'] ] = date( 'Y-m-d H:i:s' );
		}
		if ( $infoCat['countfield'] != '' )
		{
			$infoValues[ $infoCat['countfield'] ] = $infoPack['count'] - 1;
		}
		$listPackExtraFields = json_decode( $infoPack['extrafields'], true );
		foreach ( $infoCat['extrafields'] as $extraFieldName => $infoCatExtraField )
		{
			if ( $infoCatExtraField['field'] != '' )
			{
				$infoValues[ $infoCatExtraField['field'] ] =
						$listPackExtraFields[ $extraFieldName ];
			}
		}
		return $infoValues;
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



	// Check if the current user can see (some of) the packs for the project.
	public function canSeePacks()
	{
		$user = $this->getUser();
		if ( ! is_object( $user ) )
		{
			return false;
		}
		if ( $this->canConfigure() )
		{
			return true;
		}
		$userRights = $user->getRights();
		if ( $userRights === null || $userRights['role_id'] === null )
		{
			return false;
		}
		$roleName = $userRights['role_name'];
		$moduleName = $this->getModuleDirectoryBaseName();
		$queryRoles = $this->query( 'SELECT jtbl.role FROM redcap_external_module_settings ems ' .
		                            'JOIN redcap_external_modules em ON ems.external_module_id = ' .
		                            'em.external_module_id JOIN JSON_TABLE( JSON_EXTRACT( ' .
		                            'ems.value, \'$.roles_view[*]\', \'$.roles_dags[*]\', ' .
		                            '\'$.roles_invalid[*]\', \'$.roles_assign[*]\', ' .
		                            '\'$.roles_add[*]\', \'$.roles_edit[*]\' ), \'$[*]\' ' .
		                            'COLUMNS ( `role` TEXT PATH \'$\' ) ) jtbl ' .
		                            'WHERE em.directory_prefix = ? AND ems.key LIKE ?',
		                            [ $moduleName, 'p' . $this->getProjectId() . '-packcat-%' ] );
		while ( $infoRole = $queryRoles->fetch_assoc() )
		{
			if ( $infoRole['role'] == $roleName )
			{
				return true;
			}
		}
		return false;
	}



	// Functions to get/release database lock.
	public function dbGetLock()
	{
		$this->query( 'DO GET_LOCK(?,60)', [ $GLOBALS['db'] . '.pack_management' ] );
	}

	public function dbReleaseLock()
	{
		$this->query( 'DO RELEASE_LOCK(?)', [ $GLOBALS['db'] . '.pack_management' ] );
	}



	// Delete a pack.
	public function deletePack( $projectID, $catID, $packID )
	{
		$sql = 'UPDATE redcap_external_module_settings ems SET ems.value = JSON_REMOVE(ems.value' .
		       ',REPLACE(JSON_UNQUOTE(JSON_SEARCH(ems.value,\'one\',?,NULL,\'$[*].id\')),' .
		       '\'.id\',\'\')) WHERE ems.external_module_id = (SELECT em.external_module_id ' .
		        'FROM redcap_external_modules em WHERE em.directory_prefix = ? LIMIT 1) ' .
		        'AND ems.key = ? AND JSON_CONTAINS( JSON_EXTRACT( ems.value, \'$[*].id\' ), ? )';
		$sqlParams = [ $packID, $this->getModuleDirectoryBaseName(),
		               'p' . $projectID . '-packlist-' . $catID, json_encode( $packID ) ];
		$this->query( $sql, $sqlParams );
	}



	// Echo plain text to output (without Psalm taints).
	// Use only for e.g. JSON or CSV output.
	function echoText( $text )
	{
		$text = htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XHTML );
		$chars = [ '&amp;' => 38, '&quot;' => 34, '&apos;' => 39, '&lt;' => 60, '&gt;' => 62 ];
		$text = preg_split( '/(&(?>amp|quot|apos|lt|gt);)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( $text as $part )
		{
			echo isset( $chars[ $part ] ) ? chr( $chars[ $part ] ) : $part;
		}
	}



	// Escapes text string for inclusion in JavaScript.
	function escapeJSString( $text )
	{
		return '"' . $this->escape( substr( json_encode( (string)$text,
		                                                 JSON_HEX_QUOT | JSON_HEX_APOS |
		                                                 JSON_HEX_TAG | JSON_HEX_AMP |
		                                                 JSON_UNESCAPED_SLASHES ),
		                                    1, -1 ) ) . '"';
	}



	// Export project settings (e.g. for Project Deployment module).
	public function exportProjectSettings( $projectID )
	{
		// Get the pack categories.
		$queryCat = $this->query( 'SELECT REGEXP_REPLACE(ems.`key`,\'^p[0-9]+-\',\'\') AS `key`, ' .
		                          '\'json\' AS `type`, ems.`value` ' .
		                          'FROM redcap_external_module_settings ems JOIN ' .
		                          'redcap_external_modules em ON ems.external_module_id = ' .
		                          'em.external_module_id WHERE em.directory_prefix = ? ' .
		                          'AND ems.`key` LIKE ? ORDER BY ems.`key`',
		                          [ $this->getModuleDirectoryBaseName(),
		                            'p' . $projectID . '-packcat-%' ] );
		$listCat = [];
		while ( $infoCat = $queryCat->fetch_assoc() )
		{
			$listCat[] = $infoCat;
		}
		return $listCat;
	}



	// Get a list of valid assignable packs from the specified pack category.
	public function getAssignablePacks( $catID, $recordID, $value = null, $ignoreExpiry = false )
	{
		$this->dbGetLock();
		// Get the pack category details.
		$queryCat = $this->query( 'SELECT ems.`value` AS category ' .
		                          'FROM redcap_external_module_settings ems JOIN ' .
		                          'redcap_external_modules em ON ems.external_module_id = ' .
		                          'em.external_module_id WHERE em.directory_prefix = ? AND ' .
		                          'ems.`key` = ? AND JSON_CONTAINS(`value`,\'true\',\'$.enabled\')',
		                          [ $this->getModuleDirectoryBaseName(),
		                            'p' . $this->getProjectId() . '-packcat-' . $catID ] );
		$infoCat = $queryCat->fetch_assoc();
		if ( ! is_array( $infoCat ) )
		{
			$this->dbReleaseLock();
			return [];
		}
		$infoCat = json_decode( $infoCat['category'], true );
		$paramsPacks = [ $this->getModuleDirectoryBaseName(),
		                 'p' . $this->getProjectId() . '-packlist-' . $catID ];
		// If packs are assigned to DAGs, prepare to filter the list of packs by DAG.
		$sqlPacks = '';
		if ( $infoCat['dags'] )
		{
			// Get the record DAG.
			$queryDAG = $this->query( 'SELECT `value` AS dag FROM ' .
			                          $this->getDataTable( $this->getProjectId() ) .
			                          ' WHERE project_id = ? AND record = ? AND field_name = ?',
			                          [ $this->getProjectId(), $recordID, '__GROUPID__' ] );
			$infoDAG = $queryDAG->fetch_assoc();
			if ( empty( $infoDAG ) )
			{
				// Record not in a DAG, cannot assign pack.
				$this->dbReleaseLock();
				return [];
			}
			$sqlPacks .= ' AND packlist.dag = ? AND packlist.dag_rcpt = 1';
			$paramsPacks[] = $infoDAG['dag'];
		}
		// If packs expire, prepare to exclude expired packs.
		if ( ! $ignoreExpiry && $infoCat['expire'] )
		{
			// Use expire buffer (number of hours), or 10 minutes if buffer = 0;
			// Add an extra 5 minutes to the expiry buffer here to allow time between selection
			// and submission.
			$expireBuffer = $infoCat['expire_buf'] ?? 0;
			$expireBuffer = ( $expireBuffer == 0 ) ? 600 : ( $expireBuffer * 3600 );
			$now = date( 'Y-m-d H:i:s', time() + $expireBuffer + 300 );
			$sqlPacks .= ' AND packlist.expiry > ?';
			$paramsPacks[] = $now;
		}
		// If the pack must match a value, prepare to filter by it.
		if ( $value !== null )
		{
			$sqlPacks .= ' AND packlist.value = ?';
			$paramsPacks[] = $value;
		}
		// Get the available packs, filtered as required.
		$queryPacks = $this->query( 'SELECT id, block_id, packlist.value, expiry, extrafields ' .
		                            'FROM redcap_external_module_settings ems, ' .
		                            'redcap_external_modules em, ' .
		                            $this->makePacklistSQL('ems.value') .
		                            'WHERE em.external_module_id = ems.external_module_id ' .
		                            'AND em.directory_prefix = ? AND ems.key = ? ' .
		                            'AND packlist.invalid = 0 AND packlist.assigned = 0 ' .
		                            $sqlPacks . ' ORDER BY id', $paramsPacks );
		$listPacks = [];
		while ( $infoPack = $queryPacks->fetch_assoc() )
		{
			$infoPack['extrafields'] = json_decode( $infoPack['extrafields'] );
			foreach ( $infoPack['extrafields'] as $efName => $efValue )
			{
				$infoPack[ 'f_' . $efName ] = $efValue;
			}
			unset( $infoPack['extrafields'] );
			$listPacks[ $infoPack['id'] ] = $infoPack;
		}
		$this->dbReleaseLock();
		// Return the list of assignable packs.
		return $listPacks;
	}



	// Get a project's redcap_data table.
	public function getDataTable( $projectID )
	{
		return method_exists( '\REDCap', 'getDataTable' )
		       ? \REDCap::getDataTable( $projectID ) : 'redcap_data';
	}



	// Get the list of packs for manual randomization using the minimization module.
	public function getMinimManualList( $recordID, $randoField = '' )
	{
		$queryCat = $this->query( 'SELECT JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.id\')) AS ' .
		                          'id FROM redcap_external_module_settings ems JOIN ' .
		                          'redcap_external_modules em ON ems.external_module_id = ' .
		                          'em.external_module_id WHERE em.directory_prefix = ? AND ' .
		                          'ems.`key` LIKE ? AND ' .
		                          'JSON_CONTAINS(`value`,\'"M"\',\'$.trigger\') ' .
		                          'AND JSON_CONTAINS(`value`,\'true\',\'$.enabled\')' .
		                          'AND JSON_CONTAINS(`value`,?,\'$.valuefield\')',
		                          [ $this->getModuleDirectoryBaseName(),
		                            'p' . $this->getProjectId() . '-packcat-%',
		                            json_encode( $randoField ) ] );
		$infoCat = $queryCat->fetch_assoc();
		if ( $infoCat )
		{
			$listPacks = $this->getAssignablePacks( $infoCat['id'], $recordID, null, true );
			$listPacks = $this->getSelectionList( $infoCat['id'], $listPacks );
			return $listPacks;
		}
		return [];
	}



	// Get the minimization pack project field.
	public function getMinimPackField( $randoField = '' )
	{
		$queryCat = $this->query( 'SELECT JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.packfield\')) AS ' .
		                          'packfield FROM redcap_external_module_settings ems JOIN ' .
		                          'redcap_external_modules em ON ems.external_module_id = ' .
		                          'em.external_module_id WHERE em.directory_prefix = ? AND ' .
		                          'ems.`key` LIKE ? AND ' .
		                          'JSON_CONTAINS(`value`,\'"M"\',\'$.trigger\') ' .
		                          'AND JSON_CONTAINS(`value`,\'true\',\'$.enabled\')' .
		                          'AND JSON_CONTAINS(`value`,?,\'$.valuefield\')',
		                          [ $this->getModuleDirectoryBaseName(),
		                            'p' . $this->getProjectId() . '-packcat-%',
		                            json_encode( $randoField ) ] );
		$infoCat = $queryCat->fetch_assoc();
		return $infoCat ? $infoCat['packfield'] : null;
	}



	// Get module directory name, without version number.
	public function getModuleDirectoryBaseName()
	{
		return preg_replace( '/_v[0-9.]+$/', '', $this->getModuleDirectoryName() );
	}



	// Get the record, event ID and instance number for an assigned pack.
	public function getPackAssignedRecord( $catID, $packID )
	{
		// Get the pack category details.
		$queryCat = $this->query( 'SELECT ems.`value` AS category ' .
		                          'FROM redcap_external_module_settings ems JOIN ' .
		                          'redcap_external_modules em ON ems.external_module_id = ' .
		                          'em.external_module_id WHERE em.directory_prefix = ? ' .
		                          'AND ems.`key` = ?',
		                          [ $this->getModuleDirectoryBaseName(),
		                            'p' . $this->getProjectId() . '-packcat-' . $catID ] );
		$infoCat = $queryCat->fetch_assoc();
		if ( ! is_array( $infoCat ) )
		{
			return false;
		}
		$infoCat = json_decode( $infoCat['category'], true );
		$packField = $infoCat['packfield'];
		$queryRecord = $this->query( 'SELECT record, event_id event, ifnull(instance,1) instance ' .
		                             'FROM ' . $this->getDataTable( $this->getProjectId() ) . ' ' .
		                             'WHERE project_id = ? AND field_name = ? AND value = ?',
		                             [ $this->getProjectId(), $packField, $packID ] );
		$infoRecord = $queryRecord->fetch_assoc();
		return $infoRecord ? $infoRecord : false;
	}



	// Get the list of pack field types, or the description of a specified type.
	public function getPackFieldTypes( $type = null )
	{
		$listFieldTypes =
			[ 'text' => preg_replace( '/\\(.*?\\)/', '', $GLOBALS['lang']['design_634'] ),
			  'date' => $GLOBALS['lang']['global_18'], 'datetime' => $GLOBALS['lang']['global_55'],
			  'time' => $GLOBALS['lang']['global_13'], 'integer' => $GLOBALS['lang']['design_86'] ];
		if ( $type !== null )
		{
			if ( isset( $listFieldTypes[ $type ] ) )
			{
				return $listFieldTypes[ $type ];
			}
			return null;
		}
		return $listFieldTypes;
	}



	// Get one or more properties for a pack.
	public function getPackProperty( $projectID, $catID, $packID, $property )
	{
		if ( ! is_array( $property ) )
		{
			$property = [ $property ];
		}
		$sql = '';
		$sqlParams = [];
		for ( $i = 0; $i < count( $property ) && $i < count( $value ); $i++ )
		{
			$sql .= ( $sql == '' ) ? 'SELECT ' : ', ';
			$sql .= 'JSON_UNQUOTE( JSON_EXTRACT( ems.value, REPLACE( JSON_UNQUOTE( ' .
			        'JSON_SEARCH( ems.value, \'one\', ?, NULL, \'$[*].id\' ) ), \'.id\', ? ) ) ) ' .
			        'AS `' . str_replace( '`', '', $property[$i] ) . '`';
			$sqlParams[] = $packID;
			$sqlParams[] = '.' . $property[$i];
		}
		$sql .= ' FROM redcap_external_module_settings ems WHERE ems.external_module_id = ' .
		        '(SELECT em.external_module_id FROM redcap_external_modules em ' .
		        'WHERE em.directory_prefix = ? LIMIT 1) AND ems.key = ? ' .
		        'AND JSON_CONTAINS( JSON_EXTRACT( ems.value, \'$[*].id\' ), ? )';
		$sqlParams[] = $this->getModuleDirectoryBaseName();
		$sqlParams[] = 'p' . $projectID . '-packlist-' . $catID;
		$sqlParams[] = json_encode( $packID );
		$queryProperty = $this->query( $sql, $sqlParams );
		$infoProperty = $queryProperty->fetch_assoc();
		if ( is_array( $infoProperty ) && count( $property ) == 1 )
		{
			$infoProperty = $infoProperty[ str_replace( '`', '', $property[0] ) ];
		}
		return $infoProperty;
	}



	// Get the project fields which can be used for storing pack fields into the records.
	public function getProjectFields( $type = '' )
	{
		$listAllFields = \REDCap::getDataDictionary( $this->getProjectId(), 'array' );
		$listFields = [];
		foreach ( $listAllFields as $field )
		{
			if ( ( $field['field_type'] == 'text' || $field['field_type'] == 'notes' ) &&
			     strpos( $field['field_annotation'], '@CALC' ) === false )
			{
				$fieldValidation = $field['text_validation_type_or_show_slider_number'];
				if ( $type == '' || $fieldValidation == '' ||
				     ( $type == 'integer' && ( $fieldValidation == 'integer' ||
				                               strpos( $fieldValidation, 'number' ) !== false ) ) ||
				     ( $type == 'datetime' && strpos( $fieldValidation, 'datetime' ) !== false ) )
				{
					$listFields[ $field['field_name'] ] = strip_tags( $field['field_label'] );
				}
			}
		}
		return $listFields;
	}



	// Get the project fields as a drop-down.
	public function getProjectFieldsSelect( $name, $value = '', $attrs = '', $type = '' )
	{
		$output = '<select name="' . $this->escape( $name ) . '" ' . $attrs . '>';
		$output .= '<option value="">' . $this->tt('select') . '</option>';
		foreach ( $this->getProjectFields( $type ) as $fieldName => $label )
		{
			if ( strlen( $label ) > 50 )
			{
				$label = substr( $label, 0, 35 ) . ' ... ' . substr( $label, -10 );
			}
			$label = $fieldName . ' - ' . $label;
			$output .= '<option value="' . $this->escape( $fieldName ) . '"';
			if ( $fieldName == $value )
			{
				$output .= ' selected';
			}
			$output .= '>';
			$output .= $this->escape( $label ) . '</option>';
		}
		$output .= '</select>';
		return $output;
	}



	// Get selection list items from list of assignable packs.
	public function getSelectionList( $catID, $listPacks )
	{
		// Get the pack category details.
		$queryCat = $this->query( 'SELECT ems.`value` AS category ' .
		                          'FROM redcap_external_module_settings ems JOIN ' .
		                          'redcap_external_modules em ON ems.external_module_id = ' .
		                          'em.external_module_id WHERE em.directory_prefix = ? AND ' .
		                          'ems.`key` = ? AND JSON_CONTAINS(`value`,\'true\',\'$.enabled\')',
		                          [ $this->getModuleDirectoryBaseName(),
		                            'p' . $this->getProjectId() . '-packcat-' . $catID ] );
		$infoCat = $queryCat->fetch_assoc();
		if ( ! is_array( $infoCat ) )
		{
			return [];
		}
		$infoCat = json_decode( $infoCat['category'], true );
		$listItems = [];
		foreach ( $listPacks as $infoPack )
		{
			$expiryDMY = substr( $infoPack['expiry'], 8, 2 ) . '-' .
			             substr( $infoPack['expiry'], 5, 2 ) . '-' .
			             substr( $infoPack['expiry'], 0, 4 ) . substr( $infoPack['expiry'], 10 );
			$expiryMDY = substr( $infoPack['expiry'], 5, 2 ) . '-' .
			             substr( $infoPack['expiry'], 8, 2 ) . '-' .
			             substr( $infoPack['expiry'], 0, 4 ) . substr( $infoPack['expiry'], 10 );
			$packLabel = ( ( $infoCat['sel_label'] ?? '' ) == '' ) ? '[id]' : $infoCat['sel_label'];
			$packLabel = str_replace( '[expiry]',
			                          \DateTimeRC::format_ts_from_ymd( $infoPack['expiry'] ),
			                          $packLabel );
			$packLabel = str_replace( '[expiry:ymd]', $infoPack['expiry'], $packLabel );
			$packLabel = str_replace( '[expiry:dmy]', $expiryDMY, $packLabel );
			$packLabel = str_replace( '[expiry:mdy]', $expiryMDY, $packLabel );
			foreach ( $infoPack as $fieldName => $fieldValue )
			{
				$packLabel = str_replace( '[' . $fieldName . ']', $fieldValue, $packLabel );
			}
			$listItems[ $infoPack['id'] ] = $packLabel;
		}
		return $listItems;
	}



	// Get values for a specific record/event/instance.
	public function getValues( $projectID, $recordID, $eventID, $instanceNum, $fieldNames )
	{
		if ( ! is_array( $fieldNames ) )
		{
			$fieldNames = [ $fieldNames ];
		}
		$listRepeatingFields = true;
		$listRepeatingForms = $this->getRepeatingForms( $eventID, $projectID );
		if ( empty( $listRepeatingForms ) )
		{
			$listRepeatingFields = [];
		}
		elseif ( $listRepeatingForms[0] != '' )
		{
			$listRepeatingFields = [];
			foreach ( $listRepeatingForms as $repeatingForm )
			{
				foreach ( $this->getProject( $projectID )->getForm( $repeatingForm )->getFieldNames()
				          as $fieldName )
				{
					$listRepeatingFields[ $fieldName ] = $repeatingForm;
				}
			}
		}
		$recordData = \REDCap::getData( $projectID, 'array', $recordID, $fieldNames,
		                                $eventID, null, true );
		$listValues = [];
		foreach ( $fieldNames as $fieldName )
		{
			if ( $listRepeatingFields === true || isset( $listRepeatingFields[ $fieldName ] ) )
			{
				$repeatingFormName = $listRepeatingFields === true
				                     ? '' : $listRepeatingFields[ $fieldName ];
				$listValues[ $fieldName ] = $recordData[ $recordID ]['repeat_instances'][ $eventID ]
				                            [ $repeatingFormName ][ $instanceNum ][ $fieldName ];
			}
			else
			{
				$listValues[ $fieldName ] = $recordData[ $recordID ][ $eventID ][ $fieldName ];
			}
		}
		return $listValues;
	}



	// Check if a minimization pack category exists.
	public function hasMinimPackCategory( $randoField = '' )
	{
		$queryCat = $this->query( 'SELECT 1 FROM redcap_external_module_settings ems JOIN ' .
		                          'redcap_external_modules em ON ems.external_module_id = ' .
		                          'em.external_module_id WHERE em.directory_prefix = ? AND ' .
		                          'ems.`key` LIKE ? AND ' .
		                          'JSON_CONTAINS(`value`,\'"M"\',\'$.trigger\') ' .
		                          'AND JSON_CONTAINS(`value`,\'true\',\'$.enabled\')' .
		                          'AND JSON_CONTAINS(`value`,?,\'$.valuefield\')',
		                          [ $this->getModuleDirectoryBaseName(),
		                            'p' . $this->getProjectId() . '-packcat-%',
		                            json_encode( $randoField ) ] );
		return ( $queryCat->fetch_assoc() ) ? true : false;
	}



	// Make the SQL query clause to get a packlist table.
	public function makePacklistSQL( $source )
	{
		return ' JSON_TABLE(' . $source . ',\'$[*]\' ' .
		       'COLUMNS( id TEXT PATH \'$.id\', ' .
		                'value TEXT PATH \'$.value\' DEFAULT \'""\' ON EMPTY, ' .
		                'block_id TEXT PATH \'$.block_id\' DEFAULT \'""\' ON EMPTY, ' .
		                'expiry DATETIME PATH \'$.expiry\', ' .
		                'extrafields JSON PATH \'$.extrafields\' DEFAULT \'[]\' ON EMPTY, ' .
		                'dag INT PATH \'$.dag\', ' .
		                'dag_rcpt TINYINT PATH \'$.dag_rcpt\' DEFAULT \'true\' ON EMPTY, ' .
		                'assigned TINYINT PATH \'$.assigned\' DEFAULT \'false\' ON EMPTY, ' .
		                'invalid TINYINT PATH \'$.invalid\' DEFAULT \'false\' ON EMPTY, ' .
		                'invalid_desc TEXT PATH \'$.invalid_desc\' DEFAULT \'""\' ON EMPTY ' .
		       ') ) AS packlist ';
	}



	// Make the SQL query clause to get a packlog table.
	public function makePacklogSQL( $source )
	{
		return ' JSON_TABLE(' . $source . ',\'$[*]\' ' .
		       'COLUMNS( event TEXT PATH \'$.event\', ' .
		                'user TEXT PATH \'$.user\' DEFAULT \'""\' ON EMPTY, ' .
		                'time DATETIME PATH \'$.time\', ' .
		                'data JSON PATH \'$.data\' DEFAULT \'[]\' ON EMPTY, ' .
		                'id TEXT PATH \'$.data.id\' DEFAULT \'""\' ON EMPTY ' .
		       ') ) AS packlog ';
	}



	// Update the log for a pack category with a new entry.
	public function updatePackLog( $projectID, $catID, $logEvent, $logData )
	{
		$logItem = json_encode( [ 'event' => $logEvent,
		                          'user' => ( defined('USERID') ? USERID : '' ),
		                          'time' => date('Y-m-d H:i:s'), 'data' => $logData ] );
		$sql = 'UPDATE redcap_external_module_settings SET `value` = JSON_MERGE_PRESERVE(' .
		       '`value`,?) WHERE external_module_id = (SELECT external_module_id FROM ' .
		       'redcap_external_modules WHERE directory_prefix = ?) AND `key` = ?';
		$this->query( $sql, [ $logItem, $this->getModuleDirectoryBaseName(),
		                      'p' . $projectID . '-packlog-' . $catID ] );
	}



	// Update one or more properties on a pack.
	public function updatePackProperty( $projectID, $catID, $packID, $property, $value )
	{
		if ( ! is_array( $property ) )
		{
			$property = [ $property ];
		}
		if ( ! is_array( $value ) )
		{
			$value = [ $value ];
		}
		$sql = 'UPDATE redcap_external_module_settings ems SET ems.value = JSON_SET(ems.value';
		$sqlParams = [];
		for ( $i = 0; $i < count( $property ) && $i < count( $value ); $i++ )
		{
			$sql .= ',REPLACE(JSON_UNQUOTE(JSON_SEARCH(ems.value,\'one\',?,NULL,' .
			        '\'$[*].id\')),\'.id\',?),CAST(? AS JSON)';
			$sqlParams[] = $packID;
			$sqlParams[] = '.' . $property[$i];
			$sqlParams[] = json_encode( $value[$i] );
		}
		$sql .= ') WHERE ems.external_module_id = (SELECT em.external_module_id FROM ' .
		        'redcap_external_modules em WHERE em.directory_prefix = ? LIMIT 1) ' .
		        'AND ems.key = ? AND JSON_CONTAINS( JSON_EXTRACT( ems.value, \'$[*].id\' ), ? )';
		$sqlParams[] = $this->getModuleDirectoryBaseName();
		$sqlParams[] = 'p' . $projectID . '-packlist-' . $catID;
		$sqlParams[] = json_encode( $packID );
		$this->query( $sql, $sqlParams );
	}



	// Update a record with new data on a specific record/event/instance.
	public function updateValues( $projectID, $recordID, $eventID, $instanceNum, $infoData )
	{
		$listRepeatingFields = true;
		$listRepeatingForms = $this->getRepeatingForms( $eventID, $projectID );
		if ( empty( $listRepeatingForms ) )
		{
			$listRepeatingFields = [];
		}
		elseif ( $listRepeatingForms[0] != '' )
		{
			$listRepeatingFields = [];
			foreach ( $listRepeatingForms as $repeatingForm )
			{
				foreach ( $this->getProject( $projectID )->getForm( $repeatingForm )->getFieldNames()
				          as $fieldName )
				{
					$listRepeatingFields[ $fieldName ] = $repeatingForm;
				}
			}
		}
		$newData = [];
		foreach ( $infoData as $fieldName => $value )
		{
			if ( $listRepeatingFields === true || isset( $listRepeatingFields[ $fieldName ] ) )
			{
				$repeatingFormName = $listRepeatingFields === true
				                     ? '' : $listRepeatingFields[ $fieldName ];
				$newData[ $recordID ]['repeat_instances'][ $eventID ][ $repeatingFormName ]
				        [ $instanceNum ][ $fieldName ] = $value;
			}
			else
			{
				$newData[ $recordID ][ $eventID ][ $fieldName ] = $value;
			}
		}
		$result = \REDCap::saveData( $projectID, 'array', $newData, 'overwrite', 'YMD' );
		return empty( $result['errors'] );
	}



	// Output the pack add/edit form fields.
	public function writePackFields( $infoCategory, $infoPack = null )
	{
?>
    <tr>
<?php
		$v = '';
		if ( $infoPack !== null )
		{
			$v = ' value="' . $this->escape( $infoPack['value'] ) . '"';
		}
		if ( $infoCategory['trigger'] == 'M' || $infoCategory['valuefield'] != '' )
		{
?>
     <td><?php echo $this->tt( 'packfield_value' .
                                 ( $infoCategory['trigger'] == 'M' ? '_minim' : '' ) ); ?> *</td>
     <td><input type="text" name="value"<?php echo $v; ?> required></td>
<?php
		}
		else
		{
?>
     <td><?php echo $this->tt( 'packfield_value' ); ?></td>
     <td><input type="text" name="value"<?php echo $v; ?>></td>
<?php
		}
?>
    </tr>
<?php
		if ( $infoCategory['blocks'] )
		{
			$v = '';
			if ( $infoPack !== null )
			{
				$v = ' value="' . $this->escape( $infoPack['block_id'] ) . '"';
			}
?>
    <tr>
     <td><?php echo $this->tt('packfield_block_id'); ?> *</td>
     <td><input type="text" name="block_id"<?php echo $v; ?> required></td>
    </tr>
<?php
		}
?>
<?php
		if ( $infoCategory['expire'] )
		{
			$v = '';
			if ( $infoPack !== null )
			{
				$v = ' value="' . $this->escape( $infoPack['expiry'] ) . '"';
			}
?>
    <tr>
     <td><?php echo $this->tt('packfield_expiry'); ?> *</td>
     <td><input type="datetime-local" name="expiry"<?php echo $v; ?> required></td>
    </tr>
<?php
		}
		foreach ( $infoCategory['extrafields'] as $extraFieldName => $infoExtraField )
		{
			$v = '';
			if ( $infoPack !== null )
			{
				$v = ' value="' .
				     $this->escape( $infoPack['extrafields'][ $extraFieldName ] ) . '"';
			}
			$extraFieldLabel = $this->escape( $infoExtraField['label'] );
			$extraFieldType = '"text"';
			if ( $infoExtraField['type'] == 'date' )
			{
				$extraFieldType = '"date"';
			}
			elseif ( $infoExtraField['type'] == 'datetime' )
			{
				$extraFieldType = '"datetime-local"';
			}
			elseif ( $infoExtraField['type'] == 'integer' )
			{
				$extraFieldType = '"number"';
			}
			elseif ( $infoExtraField['type'] == 'time' )
			{
				$extraFieldType = '"time"';
			}
			if ( $infoExtraField['required'] )
			{
				$extraFieldLabel .= ' *';
				$extraFieldType .= ' required';
			}
?>
    <tr>
     <td><?php echo $extraFieldLabel; ?></td>
     <td><input type=<?php echo $extraFieldType, $v, "\n"; ?>
                name="f_<?php echo $this->escape( $extraFieldName ); ?>"></td>
    </tr>
<?php
		}
	}



	// CSS style for Pack Management pages.
	public function writeStyle()
	{
		$style = '
			.mod-packmgmt-formtable
			{
				width: 97%;
				border: solid 1px #000;
			}
			.mod-packmgmt-formtable th
			{
				padding: 5px;
				font-size: 130%;
				font-weight: bold;
			}
			.mod-packmgmt-formtable td
			{
				padding: 5px;
			}
			.mod-packmgmt-formtable td:first-child
			{
				width: 200px;
				padding-top: 7px;
				padding-right: 8px;
				text-align:right;
				vertical-align: top;
			}
			.mod-packmgmt-formtable
			 input:not([type=submit]):not([type=button]):not([type=radio]):not([type=checkbox])
			{
				width: 95%;
				max-width: 600px;
			}
			.mod-packmgmt-formtable textarea
			{
				width: 95%;
				max-width: 600px;
				height: 100px;
			}
			.mod-packmgmt-formtable label
			{
				margin-bottom: 0px;
			}
			.mod-packmgmt-formtable span.field-desc
			{
				font-size: 90%;
			}
			.mod-packmgmt-listtable
			{
				width: 97%;
				border: solid 1px #000;
				border-collapse: collapse;
			}
			.mod-packmgmt-listtable th
			{
				padding: 8px 5px;
				font-weight: bold;
				border: solid 1px #000;
			}
			.mod-packmgmt-listtable td
			{
				padding: 3px;
				border: solid 1px #000;
			}
			.mod-packmgmt-okmsg
			{
				max-width: 800px;
				color: #0a3622;
				background-color: #d1e7dd;
				border: solid 1px #a3cfbb;
				border-radius: 0.375rem;
				padding: 1rem;
			}
			.mod-packmgmt-errmsg
			{
				max-width: 800px;
				color: #58151c;
				background-color: #f8d7da;
				border: solid 1px #f1aeb5;
				border-radius: 0.375rem;
				padding: 1rem;
			}
			';
		echo '<script type="text/javascript">',
			 '(function (){var el = document.createElement(\'style\');',
			 'el.setAttribute(\'type\',\'text/css\');',
			 'el.innerText = \'', addslashes( preg_replace( "/[\t\r\n ]+/", ' ', $style ) ), '\';',
			 'document.getElementsByTagName(\'head\')[0].appendChild(el)})()</script>';
	}

	private $listSelectionPackFields;

}
