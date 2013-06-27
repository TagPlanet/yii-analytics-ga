yii-analytics-google
====================

A Google Analytics Component for Yii. See our [Wiki](https://github.com/TagPlanet/yii-analytics-ga/wiki) for more information. 
Below will give you an overview of how to install and use the basics of this extension. **Looking for Universal Analytics?** 
See our latest addition over at [yii-analytics-ua](https://github.com/TagPlanet/yii-analytics-ua).

## Installation

#### Step 1: Upload the files
The first step is straightforward; simply unzip the files from the [latest downloads](https://github.com/TagPlanet/yii-analytics/downloads) 
into your extensions folder. You should now be able to navigate to `protected/extensions/TPGoogleAnalytics/components/` and see a file called `TPGoogleAnalytics.php`

##### Optional: Git Submodule
You can also install this as a [Git submodule](http://git-scm.com/book/en/Git-Tools-Submodules). This can be done with running the following 
command in the root of your project, where the path to the extensions folder (`protected/extensions`) might need updating.
```
git submodule add git://github.com/TagPlanet/yii-analytics-ga.git protected/extensions/TPGoogleAnalytics
```
By using a submodule, this will guarantee you'll have access to the latest version at all times.

#### Step 2: Add in configuration
Within your configuration files (usually found under `/protected/config/`) there is the "components" section. Just like your db and cache 
components, we'll need to add in our own configuration for this. Add in the following code within the components section:
```
'googleAnalytics' => array(
    'class' =>'ext.TPGoogleAnalytics.components.TPGoogleAnalytics',
    'account' => 'UA-########-#',
),
```

#### Step 3: (Optional) Add in auto-render
In order for the Google Analytics component to automatically render the code in the header, you must have the following two items configured:
 1.  *Configuration file* - within the googleAnalytics configuration, you must include:
```
'googleAnalytics' => array(
    'class' =>'ext.TPGoogleAnalytics.components.TPGoogleAnalytics',
    'account' => 'UA-########-#',
    'autoRender' => true,
),
```
 1.  *Controllers* - your controllers must have the following code:
```
protected function beforeRender($view)
{
    $return = parent::beforeRender($view);
    Yii::app()->googleAnalytics->render();
    return $return;
}
```
You can place this either within `protected/components/Controller.php` (or whichever Controller you are overloading) _or_ within 
every single one of your controllers. In the event that you already have the method `beforeRender` within your controllers, simple 
add in the following line to it, before the return statement:
```
Yii::app()->googleAnalytics->render();
```

## Configuration Options
This component allows for some flexibility within the configuration section. Below are all of the allowed configuration variables:
  * **class** - The TPGoogleAnalytics class location 
    * Required: yes
    * Type: string
    * Default: `ext.TPGoogleAnalytics.components.TPGoogleAnalytics`
  * **account** - Your Google Analytics account ID
    * Required: yes
    * Type: string
    * Format:  `UA-########-#`
    * Default: (none)
  * **autoRender** - Automatically render the Google Analytics code in the head. If you do set this to true, you will need to update your controller's `beforeRender` method (see:[Optionally adding in auto-render code](https://github.com/TagPlanet/yii-analytics-ga/wiki/Install))
    * Required: no
    * Type: boolean
    * Recommend Setting: true
    * Default: false   
  * **autoPageview** - Automatically add `_trackPageview` to the code. By disabling this, you will need to call `Yii::app()->googleAnalytics->_trackPageview();` for each view you want to track. Even when this is enabled, you can still call `_trackPageview` with a specified page name and it will not call `_trackPageview` twice.
    * Required: no
    * Type: boolean
    * Recommend Setting: true
    * Default: true
  * **debug** - Changes Google's JS to their [ga_debug.js file](https://developers.google.com/analytics/resources/articles/gaTrackingTroubleshooting#gaDebug) and includes Yii debugging
    * Required: no
    * Type: boolean
    * Recommend Setting: false (in production, true in development)
    * Default: false
  * **includeFile** - Enable to include the call to Google Analytics file. When using multiple trackers on one page, you should disable this for all but one instance. 
    * Required: no
    * Type: boolean
    * Recommend Setting: true
    * Default: true
  * **prefix** - Allow for multiple instances of Google Analytics on the page via namespacing in the JavaScript. See [Google's Docs](https://developers.google.com/analytics/devguides/collection/gajs/#MultipleCommands) for more information.
    * Required: no
    * Type: string
    * Recommend Setting: ''
    * Default: ''
 
## Usage

#### Accessing Google Analytics in Yii
Since the Google Analytics extension is setup as a component, you can simply use the following call to access the extension:
```
Yii::app()->googleAnalytics
```

#### Calling a Google Analytics Method
In Google Analytics, you call various [methods](https://developers.google.com/analytics/devguides/collection/gajs/methods/) 
to change the settings and values that are passed to Google's severs. For the Yii extension, you use a similar setup. 
All you need to do is call the name of the method, and pass in the parameters (not as an array!)

##### A simple example
A normal call to set a custom variable in JavaScript:
```javascript
_gaq.push(['_setCustomVar', 1, 'Section', 'Life & Style', 3]);
```

Within a controller or view, you can do the same as above via the extension:
```
Yii::app()->googleAnalytics->_setCustomVar(1, 'Section', 'Life & Style', 3);
```

##### A more complex example
Sometimes you need to push quite a bit of data into Google Analytics. With this extension, that is fairly easy.

For an example, let's push in a transaction when the user completes a checkout via the `checkout` action within the 
`cart` controller. You can see within this example that Yii's relational records can be used (see: `$order->Store->Name`)

_`protected/controllers/cart.php`_:
```
<?php

// ...

protected function beforeRender($view)
{
    $return = parent::beforeRender($view);
    Yii::app()->googleAnalytics->render();
    return $return;
}

public function actionCheckout( )
{
    // Do some processing here (let's say $order has information about the order)
    if($order->isComplete)
    {
        // Start the transaction using $order's information
        Yii::app()->googleAnalytics->_addTrans(
                                        $order->OrderID, 
                                        $order->Store->Name, 
                                        $order->Total, 
                                        $order->Tax, 
                                        $order->ShippingAmount, 
                                        $order->City, 
                                        $order->State, 
                                        $order->Country
                                    );
        
        // Loop through each item that the order had
        foreach($order->Items as $item)
        {
            // And add in the item to pass to Google Analytics
            Yii::app()->googleAnalytics->_addItem(
                                            $order->OrderID,
                                            $item->SKU,
                                            $item->Name,
                                            $item->Category->Name,
                                            $item->Price,
                                            $item->Quantity
                                        );
        }
        
        // Finally, call _trackTrans to finalize the order.
        Yii::app()->googleAnalytics->_trackTrans();
    }
}
```

#### Disallowed methods
Nearly all of the [methods](https://developers.google.com/analytics/devguides/collection/gajs/methods/) are accessible 
via the extension. The exceptions are as follows:
  * Any deprecated method
  * Any method starting with `_get`
  * `_link` (see: issue #2)
  * `_linkByPost` (see: issue #3)

#### About the methods
It should be noted that methods are output in a FIFO (First In, First Out) method. This is important because some methods 
such as [_setCustomVar](https://developers.google.com/analytics/devguides/collection/gajs/gaTrackingCustomVariables) need 
a [_trackPageview](https://developers.google.com/analytics/devguides/collection/gajs/methods/gaJSApiBasicConfiguration#_gat.GA_Tracker_._trackPageview), 
[_trackEvent](https://developers.google.com/analytics/devguides/collection/gajs/methods/gaJSApiEventTracking#_gat.GA_EventTracker_._trackEvent), 
or [_trackTrans](https://developers.google.com/analytics/devguides/collection/gajs/methods/gaJSApiEcommerce#_gat.GA_Tracker_._trackTrans) 
after it in order for the data to be sent in.

#### Rendering the Google Analytics Output
Rendering within the extension depends on the way you configured it.

##### If Auto Rendering is enabled
If auto rendering is enabled and you followed the configuration steps (adding `beforeRender` call to your controllers) 
then there is nothing else for you to do to render the JavaScript code.

##### If Auto Rendering is disabled
If you have auto rendering disabled (which it is by default), then you can call the `render()` method within your views 
which will return the rendered Google Analytics JavaScript code. In almost all cases, you should use this in your main layout views (e.g. `protected/views/layouts/main.php`)
```html
<script type="text/JavaScript">
<?php echo Yii::app()->googleAnalytics()->render(); ?>
</script>
```

*Note*: The `render` method does not wrap `<script></script>` tags around the output. If auto-rendering is enabled, 
[`CClientScript::registerScript`](http://www.yiiframework.com/doc/api/1.1/CClientScript#registerScript-detail) is utilized, otherwise JavaScript code is returned.
