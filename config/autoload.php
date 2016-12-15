<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @license LGPL-3.0+
 */


/**
 * Register the classes
 */
ClassLoader::addClasses(array
(
	// Src
	'PageAjax' => 'system/modules/page2ajax/src/PageAjax.php',
));


/**
 * Register the templates
 */
TemplateLoader::addFiles(array
(
	'fe_ajax_page' => 'system/modules/page2ajax/templates',
));
