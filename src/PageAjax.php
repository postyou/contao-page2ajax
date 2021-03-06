<?php

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2010 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Andreas Schempp 2009-2012
 * @author     Andreas Schempp <andreas@schempp.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 *
 *
 * Changed by Gerald Meier for Postyou 2015
 */



/**
 * Ajax front end controller.
 */
class PageAjax extends PageRegular
{

    private static $scriptTags=array('[[TL_JQUERY]]','[[TL_CSS]]','[[TL_MOOTOOLS]]','[[TL_BODY]]','[[TL_HEAD]]');

    /**
     * Initialize the object
     */
    public function __construct()
    {
        // Load user object before calling the parent constructor
        $this->import('FrontendUser', 'User');
        parent::__construct();

        // Check whether a user is logged in
        define('BE_USER_LOGGED_IN', $this->getLoginStatus('BE_USER_AUTH'));
        define('FE_USER_LOGGED_IN', $this->getLoginStatus('FE_USER_AUTH'));
    }

    public static function init(){

    }



    /**
     * Run the controller
     */
    public function run()
    {
        global $objPage;

        $intPage = (int) \Input::get('pageId');

        if (!$intPage && isset($_GET['page']))
        {
            $intPage = (int) \Input::get('page');
        }elseif(!$intPage && isset($_GET['language'])){
            if (\Config::get('addLanguageToUrl') && !empty($_GET['language']))
                $language= \Input::get('language');
            else
                $language = \Environment::get('httpAcceptLanguage');
            $intPage = \PageModel::findFirstPublishedRootByHostAndLanguage(\Environment::get('host'), $language)->id;
        }elseif(!$intPage ){
            $intPage = \Contao\PageModel::findBy(array("type=? AND language!=''"),"root",array("order"=>"id ASC","limit"=>"1"))->id;
        }

        // Define the stat
        if ($intPage > 0)
        {
            $objPage = \Contao\PageModel::findWithDetails($intPage);

            if (version_compare(VERSION, '2.9', '>'))
            {
                //static URL constants
                define('TL_FILES_URL', ($objPage->staticFiles != '' && !$GLOBALS['TL_CONFIG']['debugMode']) ? $objPage->staticFiles . TL_PATH . '/' : '');
                define('TL_SCRIPT_URL', ($objPage->staticSystem != '' && !$GLOBALS['TL_CONFIG']['debugMode']) ? $objPage->staticSystem . TL_PATH . '/' : '');
                define('TL_PLUGINS_URL', ($objPage->staticPlugins != '' && !$GLOBALS['TL_CONFIG']['debugMode']) ? $objPage->staticPlugins . TL_PATH . '/' : '');


                // Get the page layout
                $objLayout = $this->getPageLayout(version_compare(VERSION, '3.0', '>=') ? $objPage : $objPage->layout);
                $objPage->template = strlen($objLayout->template) ? $objLayout->template : 'fe_page';
                $objPage->templateGroup = $objLayout->templates;

                // Store the output format
                list($strFormat, $strVariant) = explode('_', $objLayout->doctype);
                $objPage->outputFormat = $strFormat;
                $objPage->outputVariant = $strVariant;
            }

            if (version_compare(VERSION, '2.11', '>='))
            {
                // Use the global date format if none is set
                if ($objPage->dateFormat == '')
                {
                    $objPage->dateFormat = $GLOBALS['TL_CONFIG']['dateFormat'];
                }
                if ($objPage->timeFormat == '')
                {
                    $objPage->timeFormat = $GLOBALS['TL_CONFIG']['timeFormat'];
                }
                if ($objPage->datimFormat == '')
                {
                    $objPage->datimFormat = $GLOBALS['TL_CONFIG']['datimFormat'];
                }

                // Set the admin e-mail address
                if ($objPage->adminEmail != '')
                {
                    list($GLOBALS['TL_ADMIN_NAME'], $GLOBALS['TL_ADMIN_EMAIL']) = $this->splitFriendlyName($objPage->adminEmail);
                }
                else
                {
                    list($GLOBALS['TL_ADMIN_NAME'], $GLOBALS['TL_ADMIN_EMAIL']) = $this->splitFriendlyName($GLOBALS['TL_CONFIG']['adminEmail']);
                }
            }

            $GLOBALS['TL_LANGUAGE'] = $objPage->language;
        }
        if(!isset($objPage) || $objPage->language==""){
            echo("Pagefault ! please set the pageId Parameter to a page or a page with a language!");
            header('HTTP/1.1 412 Precondition Failed');
            die('Invalid AJAX call.');
        }

        $this->User->authenticate();


        // Set language from _GET
        if (strlen(\Input::get('language')))
        {
            $GLOBALS['TL_LANGUAGE'] = \Input::get('language');
        }

        //unset($GLOBALS['TL_HOOKS']['outputFrontendTemplate']);
        //unset($GLOBALS['TL_HOOKS']['parseFrontendTemplate']);

        $this->loadLanguageFile('default');

        if(!isset($_GET['id'])){
            header('HTTP/1.1 412 Precondition Failed');
            return 'Missing ID';
        }

        if(!isset($_GET['action'])){
            header('HTTP/1.1 412 Precondition Failed');
            return 'Missing Action-Parameter';
        }else{
            $action=\Input::get('action');
            $id=\Input::get('id');
            switch($action){
                case 'cte':
                    $this->output($this->getElement($id));
                    break;
                case 'fmd':
                    $this->output($this->getAjaxFrontendModule($id));
                    break;
                case 'ffl':
                    $this->output($this->getFormField($id));
                    break;
                case 'page':
                    $this->output($this->getPage($id));
                    break;
                case 'art':
                    $this->output($this->getAjaxArticle($id));
                    break;
            }
        }

        if (is_array($GLOBALS['TL_HOOKS']['dispatchAjax']))
        {
            foreach ($GLOBALS['TL_HOOKS']['dispatchAjax'] as $callback)
            {
                $this->import($callback[0]);
                $varValue = $this->$callback[0]->$callback[1]();

                if ($varValue !== false)
                {
                    $this->output($varValue);
                }
            }
        }

        header('HTTP/1.1 412 Precondition Failed');
        die('Invalid AJAX call.');
    }

    /**
     * Generate a Page and return it as HTML string
     * @param integer
     * @param string
     * @return string
     */
    protected function getPage($intId)
    {
        global $objPage;

        if (!strlen($intId) || $intId < 1)
        {
            header('HTTP/1.1 412 Precondition Failed');
            return 'Missing page ID';
        }

        $newObjPageModel = \PageModel::findPublishedByIdOrAlias($intId);
        if(isset($newObjPageModel) && ($newObjPageModel instanceof \Contao\Model\Collection || is_array($newObjPageModel)))
            $newObjPageModel[0]->loadDetails();
        elseif($newObjPageModel instanceof \Contao\PageModel){
            $newObjPageModel->loadDetails();
        }

        if (!isset($newObjPageModel))
        {
            header('HTTP/1.1 404 Not Found');
            return 'Page not found';
        }

        // Show to guests only
        if ($newObjPageModel->guests && FE_USER_LOGGED_IN && !BE_USER_LOGGED_IN && !$newObjPageModel->protected)
        {
            header('HTTP/1.1 403 Forbidden');
            return 'Forbidden';
        }

        // Protected element
        if (!BE_USER_LOGGED_IN && $newObjPageModel->protected)
        {
            if (!FE_USER_LOGGED_IN)
            {
                header('HTTP/1.1 403 Forbidden');
                return 'Forbidden';
            }

            $this->import('FrontendUser', 'User');
            $groups = deserialize($newObjPageModel->groups);

            if (!is_array($groups) || count($groups) < 1 || count(array_intersect($groups, $this->User->groups)) < 1)
            {
                header('HTTP/1.1 403 Forbidden');
                return 'Forbidden';
            }
        }


        $this->setStaticUrls($newObjPageModel);

        $layout=$this->getPageLayout($newObjPageModel);
        $layout->head="";
        $layout->template="fe_ajax_page";

        $objHandler = new $GLOBALS['TL_PTY']['ajax']();

        $objPage= $newObjPageModel;

        if(\Input::get('g') == '1'){
            $objHandler->generate($newObjPageModel, false);
        } else {
            if (is_array($GLOBALS['TL_HOOKS']['ajaxGenerate']))
            {
                foreach ($GLOBALS['TL_HOOKS']['ajaxGenerate'] as $callback)
                {
                    $this->import($callback[0]);
                    $this->$callback[0]->$callback[1]($newObjPageModel);
                }
            }
        }

    }

    /**
     * Copy from parent with hack to not load head
     */
    public function generate($objPage, $blnCheckRequest=false)
    {
        $GLOBALS['TL_KEYWORDS'] = '';
        $GLOBALS['TL_LANGUAGE'] = $objPage->language;

        \System::loadLanguageFile('default');

        // Static URLs
        $this->setStaticUrls();

        // Get the page layout
        $objLayout = $this->getPageLayout($objPage);

        // HOOK: modify the page or layout object (see #4736)
        if (isset($GLOBALS['TL_HOOKS']['getPageLayout']) && is_array($GLOBALS['TL_HOOKS']['getPageLayout']))
        {
            foreach ($GLOBALS['TL_HOOKS']['getPageLayout'] as $callback)
            {
                $this->import($callback[0]);
                $this->$callback[0]->$callback[1]($objPage, $objLayout, $this);
            }
        }

        // Set the layout template and template group
        $objPage->template = $objLayout->template ?: 'fe_page';
        $objPage->templateGroup = $objLayout->getRelated('pid')->templates;

        // Store the output format
        list($strFormat, $strVariant) = explode('_', $objLayout->doctype);
        $objPage->outputFormat = $strFormat;
        $objPage->outputVariant = $strVariant;

        // Initialize the template
        $this->createTemplate($objPage, $objLayout);

        // Initialize modules and sections
        $arrCustomSections = array();
        $arrSections = array('header', 'left', 'right', 'main', 'footer');
        $arrModules = deserialize($objLayout->modules);

        $arrModuleIds = array();

        // Filter the disabled modules
        foreach ($arrModules as $module)
        {
            if ($module['enable'])
            {
                $arrModuleIds[] = $module['mod'];
            }
        }

        // Get all modules in a single DB query
        $objModules = \ModuleModel::findMultipleByIds($arrModuleIds);

        if ($objModules !== null || $arrModules[0]['mod'] == 0) // see #4137
        {
            $arrMapper = array();

            // Create a mapper array in case a module is included more than once (see #4849)
            if ($objModules !== null)
            {
                while ($objModules->next())
                {
                    $arrMapper[$objModules->id] = $objModules->current();
                }
            }

            foreach ($arrModules as $arrModule)
            {
                // Disabled module
                if (!$arrModule['enable'])
                {
                    continue;
                }

                // Replace the module ID with the module model
                if ($arrModule['mod'] > 0 && isset($arrMapper[$arrModule['mod']]))
                {
                    $arrModule['mod'] = $arrMapper[$arrModule['mod']];
                }

                // Generate the modules
                if (in_array($arrModule['col'], $arrSections))
                {
                    // Filter active sections (see #3273)
                    if ($arrModule['col'] == 'header' && $objLayout->rows != '2rwh' && $objLayout->rows != '3rw')
                    {
                        continue;
                    }
                    if ($arrModule['col'] == 'left' && $objLayout->cols != '2cll' && $objLayout->cols != '3cl')
                    {
                        continue;
                    }
                    if ($arrModule['col'] == 'right' && $objLayout->cols != '2clr' && $objLayout->cols != '3cl')
                    {
                        continue;
                    }
                    if ($arrModule['col'] == 'footer' && $objLayout->rows != '2rwf' && $objLayout->rows != '3rw')
                    {
                        continue;
                    }

                    $this->Template->$arrModule['col'] .= $this->getFrontendModule($arrModule['mod'], $arrModule['col']);
                }
                else
                {
                    $arrCustomSections[$arrModule['col']] .= $this->getFrontendModule($arrModule['mod'], $arrModule['col']);
                }
            }
        }

        $this->Template->sections = $arrCustomSections;

        // Mark RTL languages (see #7171)
        if ($GLOBALS['TL_LANG']['MSC']['textDirection'] == 'rtl')
        {
            $this->Template->isRTL = true;
        }

        // HOOK: modify the page or layout object
        if (isset($GLOBALS['TL_HOOKS']['generatePage']) && is_array($GLOBALS['TL_HOOKS']['generatePage']))
        {
            foreach ($GLOBALS['TL_HOOKS']['generatePage'] as $callback)
            {
                $this->import($callback[0]);
                $this->$callback[0]->$callback[1]($objPage, $objLayout, $this);
            }
        }

        // Set the page title and description AFTER the modules have been generated
        $this->Template->mainTitle = $objPage->rootPageTitle;
        $this->Template->pageTitle = $objPage->pageTitle ?: $objPage->title;

        // Meta robots tag
        $this->Template->robots = $objPage->robots ?: 'index,follow';

        // Remove shy-entities (see #2709)
        $this->Template->mainTitle = str_replace('[-]', '', $this->Template->mainTitle);
        $this->Template->pageTitle = str_replace('[-]', '', $this->Template->pageTitle);

        // Fall back to the default title tag
        if ($objLayout->titleTag == '')
        {
            $objLayout->titleTag = '{{page::pageTitle}} - {{page::rootPageTitle}}';
        }

        // Assign the title and description
        $this->Template->title = strip_insert_tags($this->replaceInsertTags($objLayout->titleTag)); // see #7097
        $this->Template->description = str_replace(array("\n", "\r", '"'), array(' ' , '', ''), $objPage->description);

        // Body onload and body classes
        $this->Template->onload = trim($objLayout->onload);
        $this->Template->class = trim($objLayout->cssClass . ' ' . $objPage->cssClass);

        if(isset($_GET['lws']) && !empty($_GET['lws'])){
            $this->Template->mootools=$this->generateScriptTags();
        }

        // Print the template to the screen
        $this->Template->output($blnCheckRequest);
    }



    /**
     * Generate a Page and return it as HTML string
     * @param integer
     * @param string
     * @return string
     */
    protected function getAjaxArticle($intId)
    {
        global $objPage;

        if (!strlen($intId) || $intId < 1)
        {
            header('HTTP/1.1 412 Precondition Failed');
            return 'Missing content Article';
        }

        $objArticle= \ArticleModel::findPublishedById($intId);

        if (count($objArticle)< 1)
        {
            header('HTTP/1.1 404 Not Found');
            return 'Article not found';
        }

        // Show to guests only
        if ($objArticle->guests && FE_USER_LOGGED_IN && !BE_USER_LOGGED_IN && !$objArticle->protected)
        {
            header('HTTP/1.1 403 Forbidden');
            return 'Forbidden';
        }

        // Protected element
        if ($objArticle->protected && !BE_USER_LOGGED_IN)
        {
            if (!FE_USER_LOGGED_IN)
            {
                header('HTTP/1.1 403 Forbidden');
                return 'Forbidden';
            }

            $this->import('FrontendUser', 'User');
            $groups = deserialize($objArticle->groups);

            if (!is_array($groups) || count($groups) < 1 || count(array_intersect($groups, $this->User->groups)) < 1)
            {
                header('HTTP/1.1 403 Forbidden');
                return 'Forbidden';
            }
        }
        if (\Input::get('g') == '1')
        {
            $objPage->id=null;
            $strBuffer=self::getArticle($intId);
            if(isset($_GET['lws']) && !empty($_GET['lws'])){
                $strBuffer.=$this->generateScriptTags();
            }
        }
        else
        {
            if (is_array($GLOBALS['TL_HOOKS']['ajaxGenerate']))
            {
                foreach ($GLOBALS['TL_HOOKS']['ajaxGenerate'] as $callback)
                {
                    $this->import($callback[0]);
                    $strBuffer=$this->$callback[0]->$callback[1](\Contao\ArticleModel::findByIdOrAlias($intId));
                }
            }
        }
        return $strBuffer;


    }

    public function generateScriptTags(){
        $scriptTokens=implode(" ",self::$scriptTags);
        $this->setStaticUrls();
        $scriptBuffer=\Contao\Controller::replaceDynamicScriptTags($scriptTokens);
        return $scriptBuffer;
    }


    public static function setStaticUrls($objPage=null)
    {
        if (defined('TL_ASSETS_URL'))
        {
            return;
        }

        // Use the global object (see #5906)
        if ($objPage === null)
        {
            global $objPage;
        }

        $arrConstants = array
        (
            'staticFiles'   => 'TL_FILES_URL',
            'staticPlugins' => 'TL_ASSETS_URL'
        );

        foreach ($arrConstants as $strKey=>$strConstant)
        {
            $url = ($objPage !== null) ? $objPage->$strKey : \Config::get($strKey);

            if ($url == '' || \Config::get('debugMode'))
            {
                define($strConstant, '');
            }
            else
            {
                define($strConstant, '//' . preg_replace('@https?://@', '', $url) . TL_PATH . '/');
            }
        }

        // Backwards compatibility
        define('TL_SCRIPT_URL', TL_ASSETS_URL);
        define('TL_PLUGINS_URL', TL_ASSETS_URL);
    }



    /**
     * Generate a front end module and return it as HTML string
     * @param integer
     * @param string
     * @return string
     */
    protected function getAjaxFrontendModule($intId, $strColumn='main')
    {
        if (!strlen($intId) || $intId < 1)
        {
            header('HTTP/1.1 412 Precondition Failed');
            return 'Missing frontend module ID';
        }

        $objModule = $this->Database->prepare("SELECT * FROM tl_module WHERE id=?")
            ->limit(1)
            ->execute($intId);

        if ($objModule->numRows < 1)
        {
            header('HTTP/1.1 404 Not Found');
            return 'Frontend module not found';
        }

        // Show to guests only
        if ($objModule->guests && FE_USER_LOGGED_IN && !BE_USER_LOGGED_IN && !$objModule->protected)
        {
            header('HTTP/1.1 403 Forbidden');
            return 'Forbidden';
        }

        // Protected element
        if (!BE_USER_LOGGED_IN && $objModule->protected)
        {
            if (!FE_USER_LOGGED_IN)
            {
                header('HTTP/1.1 403 Forbidden');
                return 'Forbidden';
            }

            $this->import('FrontendUser', 'User');
            $groups = deserialize($objModule->groups);

            if (!is_array($groups) || count($groups) < 1 || count(array_intersect($groups, $this->User->groups)) < 1)
            {
                header('HTTP/1.1 403 Forbidden');
                return 'Forbidden';
            }
        }

        $strClass = \Module::findClass($objModule->type);

        // Return if the class does not exist
        if (!class_exists($strClass))
        {
            $this->log('Module class "'.$GLOBALS['FE_MOD'][$objModule->type].'" (module "'.$objModule->type.'") does not exist', 'Ajax getFrontendModule()', TL_ERROR);

            header('HTTP/1.1 404 Not Found');
            return 'Frontend module class does not exist';
        }

        $objModule->typePrefix = 'mod_';
        $objModule = new $strClass($objModule, $strColumn);

        return \Input::get('g') == '1' ? $objModule->generate() : $objModule->generateAjax();
    }


    /**
     * Generate a content element return it as HTML string
     * @param integer
     * @return string
     */
    protected function getElement($intId)
    {
        if (!strlen($intId) || $intId < 1)
        {
            header('HTTP/1.1 412 Precondition Failed');
            return 'Missing content element ID';
        }

        $objElement = $this->Database->prepare("SELECT * FROM tl_content WHERE id=?")
            ->limit(1)
            ->execute($intId);

        if ($objElement->numRows < 1)
        {
            header('HTTP/1.1 404 Not Found');
            return 'Content element not found';
        }

        // Show to guests only
        if ($objElement->guests && FE_USER_LOGGED_IN && !BE_USER_LOGGED_IN && !$objElement->protected)
        {
            header('HTTP/1.1 403 Forbidden');
            return 'Forbidden';
        }

        // Protected element
        if ($objElement->protected && !BE_USER_LOGGED_IN)
        {
            if (!FE_USER_LOGGED_IN)
            {
                header('HTTP/1.1 403 Forbidden');
                return 'Forbidden';
            }

            $this->import('FrontendUser', 'User');
            $groups = deserialize($objElement->groups);

            if (!is_array($groups) || count($groups) < 1 || count(array_intersect($groups, $this->User->groups)) < 1)
            {
                header('HTTP/1.1 403 Forbidden');
                return 'Forbidden';
            }
        }

        $strClass = \Module::findClass($objElement->type);

        // Return if the class does not exist
        if (!class_exists($strClass))
        {
            $this->log('Content element class "'.$strClass.'" (content element "'.$objElement->type.'") does not exist', 'Ajax getContentElement()', TL_ERROR);

            header('HTTP/1.1 404 Not Found');
            return 'Content element class does not exist';
        }

        $objElement->typePrefix = 'ce_';
        $objElement = new $strClass($objElement);

        if (\Input::get('g') == '1')
        {
            $strBuffer = $objElement->generate();
        }
        else
        {
            if (is_array($GLOBALS['TL_HOOKS']['ajaxGenerate']))
            {
                foreach ($GLOBALS['TL_HOOKS']['ajaxGenerate'] as $callback)
                {
                    $this->import($callback[0]);
                    $strBuffer=$this->$callback[0]->$callback[1]($objElement);
                }
            }
        }

        // HOOK: add custom logic
        if (isset($GLOBALS['TL_HOOKS']['getContentElement']) && is_array($GLOBALS['TL_HOOKS']['getContentElement']))
        {
            foreach ($GLOBALS['TL_HOOKS']['getContentElement'] as $callback)
            {
                $this->import($callback[0]);
                $strBuffer = $this->$callback[0]->$callback[1]($objElement, $strBuffer);
            }
        }

        return $strBuffer;
    }


    /**
     * Generate a form field
     * @param  int
     * @return string
     */
    protected function getFormField($strId)
    {
        if (!strlen($strId) || !isset($_SESSION['AJAX-FFL'][$strId]))
        {
            header('HTTP/1.1 412 Precondition Failed');
            return 'Missing form field ID';
        }

        $arrConfig = $_SESSION['AJAX-FFL'][$strId];

        $strClass = strlen($GLOBALS['TL_FFL'][$arrConfig['type']]) ? $GLOBALS['TL_FFL'][$arrConfig['type']] : $GLOBALS['BE_FFL'][$arrConfig['type']];

        if (!class_exists($strClass))
        {
            $this->log('Form field class "'.$strClass.'" (form field "'.$arrConfig['type'].'") does not exist', 'Ajax getFormField()', TL_ERROR);

            header('HTTP/1.1 404 Not Found');
            return 'Form field class does not exist';
        }

        $objField = new $strClass($arrConfig);

        return $objField->generateAjax();
    }


    /**
     * Output data, encode to json and replace insert tags
     * @param  mixed
     * @return string
     */
    protected function output($varValue)
    {
        $varValue = $this->replaceTags($varValue);

//		if (version_compare(VERSION, '2.9', '>'))
//		{
//			$varValue = json_encode(array
//			(
//				'token'		=> REQUEST_TOKEN,
//				'content'	=> $varValue,
//			));
//		}
//		elseif (is_array($varValue) || is_object($varValue))
//		{
//			$varValue = json_encode($varValue);
//		}

        echo $varValue;
        exit;
    }


    /**
     * Recursively replace inserttags in the return value
     * @param	array|string
     * @return	array|string
     */
    private function replaceTags($varValue)
    {
        if (is_array($varValue))
        {
            foreach( $varValue as $k => $v )
            {
                $varValue[$k] = $this->replaceTags($v);
            }

            return $varValue;
        }
        elseif (is_object($varValue))
        {
            return $varValue;
        }

        return $this->replaceInsertTags($varValue);
    }

    public static function getAjaxURL(){
        if(defined('TL_ROOT')){
            return substr(__DIR__,strlen(TL_ROOT),(strlen(__DIR__)-strlen(TL_ROOT))-4)."/assets/ajax.php";
        }
        elseif(strpos(__DIR__,"composer")!=false)
            return "/composer/vendor/postyou/page2ajax/assets/ajax.php";
        else
            return "/system/modules/page2ajax/assets/ajax.php";
    }
}
