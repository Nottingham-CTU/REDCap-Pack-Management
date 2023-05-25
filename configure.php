<?php

namespace Nottingham\PackManagement;


if ( ! $module->canConfigure() )
{
	exit;
}



// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';


?>
<div class="projhdr">
 <i class="fas fa-boxes-stacked"></i> <?php echo $module->tt('module_name'), "\n"; ?>
</div>

<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
