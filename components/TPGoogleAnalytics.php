<?php
/**
 * Google Analytics Component
 *
 * @author Philip Lawrence <philip@misterphilip.com>
 * @link http://misterphilip.com
 * @link http://tagpla.net
 * @link https://github.com/TagPlanet/yii-analytics-ga
 * @copyright Copyright &copy; 2012 - 2013 Philip Lawrence
 * @license http://tagpla.net/licenses/MIT.txt
 * @version 1.0.4-dev
 */
class TPGoogleAnalytics extends CApplicationComponent
{
    /**
     * Account ID, from the configuration - not to be trusted!
     * @var string
     */
    public $account;
    
    /**
     * Prefix
     * @var string
     */
    public $prefix = '';

    /**
     * Account ID, to be trusted (regexed)
     * @var string
     */
    private $_accountID;

    /**
     * Auto render or return the JS
     * @var bool
     */
    public $autoRender = false;

    /**
     * Automatically add trackPageview when render is called
     * @var bool
     */
    public $autoPageview = true;

    /**
     * Should we render a URL string for mobile, or a JS block?
     * @var bool
     */
    public $renderMobile = false;
    
    /**
     * Enable / disable debug mode
     * 
     * Includes extra Yii logging and uses the ga_debug.js instead of ga.js to receive errors and warnings in the window.console
     * Per: https://developers.google.com/analytics/resources/articles/gaTrackingTroubleshooting#gaDebug
     * @var bool
     */
    public $debug = false;    
    
    /**
     * Enable or disable the inclusion of the Google Analytics output
     * @var bool
     */
    public $includeFile = true;

    /**
     * Type of quotes to use for values
     */
    const Q = "'";

    /**
     * Available options, pulled (May 4, 2012) from
     * https://developers.google.com/analytics/devguides/collection/gajs/methods/
     * @var array
     */
    protected $_availableOptions = array
    (
        '_addIgnoredOrganic',
        '_addIgnoredRef',
        '_addItem',
        '_addOrganic',
        '_addTrans',
        '_anonymizeIp',
        '_clearIgnoredOrganic',
        '_clearIgnoredRef',
        '_clearOrganic',
        '_cookiePathCopy',
        '_deleteCustomVar',
        '_set',
        '_setAccount',
        '_setAllowAnchor',
        '_setAllowLinker',
        '_setCampContentKey',
        '_setCampMediumKey',
        '_setCampNOKey',
        '_setCampNameKey',
        '_setCampSourceKey',
        '_setCampTermKey',
        '_setCampaignCookieTimeout',
        '_setCampaignTrack',
        '_setClientInfo',
        '_setCookiePath',
        '_setCustomVar',
        '_setDetectFlash',
        '_setDetectTitle',
        '_setDomainName',
        '_setLocalGifPath',
        '_setLocalRemoteServerMode',
        '_setLocalServerMode',
        '_setReferrerOverride',
        '_setRemoteServerMode',
        '_setSampleRate',
        '_setSessionCookieTimeout',
        '_setSiteSpeedSampleRate',
        '_setVisitorCookieTimeout',
        '_trackEvent',
        '_trackPageview',
        '_trackSocial',
        '_trackTiming',
        '_trackTrans',
    );

    /**
     * An array of all the methods called for _gaq
     * @var array
     */
    protected $_calledOptions = array();

    /**
     * Method data to be pushed into the _gaq object
     * @var array
     */

    private $_data = array();

    /**
     * init function - Yii automaticall calls this
     */
    public function init()
    {
        $this->_accountID = $this->parseAccountID($this->account);
        if($this->_accountID !== null)
        {
            $this->_setAccount($this->_accountID);
        }
        else
        {
            throw new CException('Invalid Account ID for TPGoogleAnalytics');
        }
    }

    /**
     * Cleans up and verifies the Google Analytics account ID
     * @param string $accountID
     * @return string
     */
    public function parseAccountID($accountID)
    {
        if($this->_accountID == null)
        {
            $account = null;
            if(preg_match('~^(UA|MO)-\d{4,10}-\d{1,3}$~i', $accountID))
            {
                $account = strtoupper($accountID);
            }
            else
            {
                Yii::log('Invalid Google Analytics account ID', 'warning', 'application.components.googleAnalytics');
            }
        }
        return $account;
    }


    /**
     * Render and return the Google Analytics code
     * @return mixed
     */
    public function render()
    {
        // Check to see if we need to throw in the trackPageview call
        if(!in_array('_trackPageview', $this->_calledOptions) && $this->autoPageview)
        {
            $this->_trackPageview();
        }
        
        // Get the prefix information
        if($this->prefix != '')
        {
            if(strpos($this->prefix, '.') === false)
            {
                $this->prefix .= '.';
            }
        }
        else
        {
            $this->prefix = '';
        }

        // Start the JS string
        $js = 'var _gaq = _gaq || [];' . PHP_EOL;
        foreach($this->_data as $data)
        {
            // No prefixes for the first argument.
            $prefixed = false;
            
            // Clean up each item
            foreach($data as $key => $item)
            {
                
                if(is_string($item))
                {
                    $data[$key] = self::Q . ((!$prefixed) ? $this->prefix : '') . preg_replace('~(?<!\\\)'. self::Q . '~', '\\' . (($prefixed) ? $this->prefix : '') . self::Q, $item) . self::Q;
                }
                else if(is_bool($item))
                {
                    $data[$key] = ($item) ? 'true' : 'false';
                }
                
                $prefixed = true;
            }

            $js.= '_gaq.push([' . implode(',', $data) . ']);' . PHP_EOL;
        }
        
        // Check to see if we should include the file or not
        if($this->includeFile)
        {
            //Set the debug url?
            $url = $this->debug ? 'u/ga_debug.js' : 'ga.js';
                
            $js.= <<<EOJS
(function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/{$url}';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
})();
EOJS;
        }
        
        // Include the copyright
        $js.= <<<EOCOPY
// Google Analytics Extension provided by TagPla.net
// https://github.com/TagPlanet/yii-analytics-ga
// Copyright 2012 - 2013, TagPla.net & Philip Lawrence
EOCOPY;
        
        // Should we auto add in the analytics tag?
        if($this->autoRender)
        {
            Yii::app()->clientScript
                    ->registerScript('TPGoogleAnalytics', $js, CClientScript::POS_HEAD);
            return;
        }
        else
        {
            return $js;
        }
    }

    /**
     * Magic Method for options
     * @param string $name
     * @param array  $arguments
     */
    public function __call($name, $arguments)
    {
        if($name[0] != '_')
            $name = '_' . $name;
        if(in_array($name, $this->_availableOptions))
        {
            // Clean up the debugging a bit
            if(is_array($arguments))
                $this->_debug('Setting method "' . $name . '" with arguments: "' . implode('", "', $arguments) . '"');
            else
                $this->_debug('Setting method "' . $name . '" with argument: "' . $arguments .'"');
            
            $this->_push($name, $arguments);
            return true;
        }
        
        $this->_debug('Method "' . $name . '" does not exist and cannot be called', 'warning');
        return false;
    }

    /**
     * Push data into the array
     * @param string $variable
     * @param array  $arguments
     * @protected
     */
    protected function _push($variable, $arguments)
    {
        $data = array_merge(array($variable), $arguments);
        array_push($this->_data, $data);
        $this->_calledOptions[] = $variable;
    }
    
    /**
     * Output debugging, if enabled
     * @param string $message
     * @param string $level
     * @protected
     */
    protected function _debug($message = '', $level = 'info')
    {
        if($this->debug)
        {
            Yii::log($message, $level, 'ext.TPGoogleAnalytics.components.TPGoogleAnalytics');
        }
    }
}