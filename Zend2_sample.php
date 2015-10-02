<?php


namespace Financial;

use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\ModuleManager\ModuleEvent;
use Zend\EventManager\Event;
use Zend\I18n\Translator\Translator;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;
use Zend\Log\Writer\FirePhp;
use Zend\Log\Logger;
use Financial\Model\BookingsTable;
use Zend\ModuleManager\ModuleManager;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\TableGateway\TableGateway;
use Financial\Model\Insurers;
use Financial\Model\DbTable\InsurersTable;
use Financial\Model\CarteVerteLabels;
use Financial\Model\DescriptionByCVInsurer;
use Financial\Model\DbTable\LabelTable;

class Module
{

    protected $_sm;
    protected $_router;
    protected $_layoutViewModel;
    protected $application;
    protected $_view;
    protected $_front;
    protected $_config;
    protected $_FinancialNs;
    protected $_debugNs;
    protected $_sharedVars = array();

    /**
     * Public resources base uri
     * 
     * @var string
     * @access public
     */
    public $resUri;
    public $uploadUri;
    public $isLocalDev = false; // Server is a local dev server
    public $isDevIp = false; // IP is Netefficiency's
    public $isDebugOutputSafe = false; // Is it safe to output debug information
    public $isDebugInvisibleSafe = false; // Is it safe to invisibly output debug information
    public $viewModelIsJson;
    private $googleMapsLangCode;

    /**
     * layoutViewModel 
     * 
     * @var \Zend\View\Model\ViewModel
     * @access public
     */
    public $layoutViewModel;

    public function init(ModuleManager $moduleManager)
    {
        // Remember to keep the init() method as lightweight as possible
        $events = $moduleManager->getEventManager();
        $sharedManager = $events->getSharedManager();
        $sharedManager->attach(
                'Zend\Mvc\Application', MvcEvent::EVENT_BOOTSTRAP, array($this, 'onSecondBootstrap'), -1000
        );
        $sharedManager->attach(
                'Zend\Mvc\Application', MvcEvent::EVENT_RENDER, array($this, 'setLayout')
        );
    }

    /**
     * onBootstrap
     *
     * @param MvcEvent $e
     * @access public
     * @return void
     */
    public function onBootstrap(MvcEvent $e)
    {
        ob_start(); // capture any premature output. Save the contents to layout before the script ends.

        $application = $e->getApplication();
        $this->application = $application;
        $this->mvcEvent = $e;

        $sm = $application->getServiceManager();
        $this->_sm = $sm;
        $em = $e->getApplication()->getEventManager();
        $this->eventManager = $em;
        $this->sharedManager = $em->getSharedManager();

        $this->_initAll();

        $application = $e->getApplication();
        $em->attach(\Zend\Mvc\MvcEvent::EVENT_DISPATCH_ERROR, array($this, 'onError'), 1000);
        $em->attach(\Zend\Mvc\MvcEvent::EVENT_RENDER_ERROR, array($this, 'onError'), 1000);
        $em->attach(\Zend\Mvc\MvcEvent::EVENT_DISPATCH, array($this, 'onBeforeDispatch'), 1000);
    }


    /**
     * onSecondBootstrap
     *
     * @param MvcEvent $e
     * @access public
     * @return void
     */
    public function onSecondBootstrap(MvcEvent $e)
    {
        $application = $this->application;
        $this->_sm->get('translator');
        $eventManager = $application->getEventManager();
        $app = $e->getTarget();
        $app->getEventManager()->attach('route', array($this, 'onPreRoute'), 100);
    }

    /**
     * Before routing is performed... 
     * 
     * @param MvcEvent $e 
     * @access public
     * @return void
     */
    public function onPreRoute(MvcEvent $e)
    {
        $app = $e->getTarget();
        $serviceManager = $app->getServiceManager();
        $router = $serviceManager->get('router');
        $translator = $serviceManager->get('translator');
        $router->setTranslator($translator);
//        $router->setTranslatorTextDomain('url');
    }

    /**
     * This bit of code should run after routing.
     * There should be code here taken from the dispatchLoopStartup plugin from the ZF1 version of UK.
     * 
     * @param \Zend\Mvc\MvcEvent $evt 
     * @access public
     * @return void
     */


    public function onDispatch(\Zend\Mvc\MvcEvent $e)
    {
        $routeMatch = $e->getRouteMatch();
        $controller = $this->_sm->get('ControllerLoader')->get($routeMatch->getParam('controller'));
        $controllerParamName = \Zend\Mvc\ModuleRouteListener::ORIGINAL_CONTROLLER;

        $controllerName = $routeMatch->getParam($controllerParamName); // my-controller
        $actionName = $controller->params('action', 'index');

        $layoutViewModel = $e->getViewModel();
        /**
         * Must check for ViewModel. If this is a JsonModel in ajax, we would be unwantingly adding variables
         * to the json response.
         */
        $this->viewModelIsJson = $layoutViewModel instanceof JsonModel;
        $this->_layoutViewModel = $layoutViewModel;
        $layoutViewModel->setVariable('dataFacade', $this->_sm->get('session.facade'));

        if ($layoutViewModel instanceof ViewModel && !($layoutViewModel instanceof JsonModel)) {
            /**
             * Set view vars
             * */
            $layoutViewModel->setVariables(array(
                'controllerName' => $controllerName,
                'curModule' => $controllerName,
                'actionName' => $actionName
            ));
            $sharedVars = $this->_sm->get('sharedVars');
            $layoutViewModel->showAnalytics = $sharedVars['showAnalytics'];
            $layoutViewModel->isDebugInvisibleSafe = $this->isDebugInvisibleSafe;
            $controller->assignJsVar('resUri', $this->resUri);

            $FinancialNs = $this->_sm->get('Session\Financial');

            // Pass all session data to the layout view
            if (sizeof($FinancialNs->getIterator()) > 0) {
                foreach ($FinancialNs->getIterator() as $index => $val) {
                    //echo $index . ' - ' . $val . '<br>';
                    $layoutViewModel->setVariable($index, $FinancialNs->$index);
                }
            }

            $controller->shared('assignDynamicTranslationKeys');
        }
    }

    public function onError(\Zend\Mvc\MvcEvent $e)
    {
        $sm = $this->_sm;
        if ($e->getParam('exception')) {
            trigger_error($e->getParam('exception'), E_USER_WARNING);
        }
    }

    protected function _initAll()
    {
        $this->setupSharedEvents();
        $this->_initEnv();
        $this->_initPhpSettings();
        $this->_initLogging();
        $this->initDebug();
    }

    /**
     * Set up listeners to events cross-controller here
     * 
     * @access private
     * @return void
     */
    private function setupSharedEvents()
    {
        $sm = $this->_sm; // service manager
        $controllerLoader = $sm->get('ControllerLoader');
        $this->sharedManager->attach(
                'Financial', 'appointmentJobTypeChanged', function (Event $evt) use ($controllerLoader) {
            $detailsController = $controllerLoader->get('Financial\Controller\Details');
            $detailsController->onStatementJobTypeChanged($evt);
        }
        );
    }

    protected function _initAppEnv()
    {
        if (!defined('APPLICATION_ENV')) {
            $appEnv = getenv('APPLICATION_ENV');
            if (!$appEnv) {
                $appEnv = 'production';
            }
            define('APPLICATION_ENV', $appEnv);
        }
    }

    protected function _initEnv()
    {
        $conf = $this->_sm->get('config');
        $staticSharedVars = $conf['staticSharedVars'];

        $this->isLocalDev = $staticSharedVars['isLocalDev'];
        if ('attila.szeremi@netefficiency.co.uk' == getenv('MAIL_ADDRESS')) {
            $this->isAttila = true;
        }
        $GLOBALS['isAttila'] = $this->isAttila;

        if (in_array(getenv('REMOTE_ADDR'), array('127.0.0.1'))) {
            $this->isDevIp = true;
        }
        $this->isDebugOutputSafe = $this->isLocalDev || $this->isDevIp;
        $this->isDebugInvisibleSafe = $this->isDebugOutputSafe || $this->isLocalDev;

        /**
         * Ability to debug invisibly by this GET param
         * */
        if (false !== strpos(getenv('REQUEST_URI'), 'debug_debug_invisible=1')) {
            $this->_sm->get('Session\debug')->isDebugInvisibleSafe = true;
        }
        if ($this->_sm->get('Session\debug')->isDebugInvisibleSafe) {
            $this->isDebugInvisibleSafe = true;
        }

        $er = error_reporting();
        error_reporting(E_ALL);
        $this->originalErrorReporting = $er;

        set_error_handler(function ($errno, $errstr, $errfile, $errline, $errcontext) {
            // suppress is_readable() open_basedir restriction related errors.
            if (false !== strpos($errstr, 'open_basedir') && false !== strpos($errstr, 'is_readable')) {
                return true;
            }
            return false;
        });
        $GLOBALS['isLocalDev'] = $this->isLocalDev;
        $GLOBALS['isDebugOutputSafe'] = $this->isDebugOutputSafe;
        $GLOBALS['isDebugInvisibleSafe'] = $this->isDebugInvisibleSafe;
        $GLOBALS['isLevelDev'] = $this->isDebugInvisibleSafe;

        iconv_set_encoding("input_encoding", "UTF-8");
        iconv_set_encoding("output_encoding", "UTF-8");
        iconv_set_encoding("internal_encoding", "UTF-8");
    }

    protected function _initPhpSettings()
    {
        $config = $this->_sm->get('config');
        $phpSettings = $config['phpSettings'];

        if ($phpSettings) {
            foreach ($phpSettings as $key => $value) {
                ini_set($key, $value);
            }
        }
        if ($this->isDebugOutputSafe) {
            ini_set('display_errors', true);
        }
    }

    protected function _initLogging()
    {

    }

    /**
     * Initiate debug triggers here. Some debug triggers might be in other
     * parts of the code though.
     * 
     * @access private
     * @return void
     */
    private function initDebug()
    {
        if (null !== \Financial\Model\Debug::getGetValue('debugForceUpdateReports')) {
            $ReportModel = $this->_sm->get('Financial\Model\Report');
            $ReportModel->importReports();
        }
        if (null !== \Financial\Model\Debug::getGetValue('debugForceUpdateStyles')) {
            $ReportModel = $this->_sm->get('Financial\Model\Report');
            $ReportModel->importStyles();
        }
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\ClassMapAutoloader' => array(
                __DIR__ . '/autoload_classmap.php',
            ),
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

    public function getServiceConfig()
    {
        $me = $this;
        return array(
            'abstract_factories' => array(
                // this solves the depencency for the Zend Cache factory service, allowing for configurations
                // under application's config's 'caches' property.
                'Zend\Cache\Service\StorageCacheAbstractServiceFactory',
                'Zend\Session\Service\ContainerAbstractServiceFactory',
                'Zend\Db\Adapter\AdapterAbstractServiceFactory',
                // this solves the depencency for the Zend Cache factory service, allowing for configurations
                // under application's config's 'caches' property.
                'Zend\Cache\Service\StorageCacheAbstractServiceFactory',
                'Financial\Data\Facade\FacadeAbstractServiceFactory',
            ),
            'aliases' => array(),
            'factories' => array(
                /**
                 * Has all the models for use for summary/confirm/saveBooking
                 */
                'Financial\Model\Facade' => function ($sm) {
                    $ret = new \Financial\Model\Facade(
                        $sm->get('Financial\Model\Report'),
                        $sm->get('Financial\Model\Invoice'),
                        $sm->get('Financial\Model\CreditNote'),
                        $sm->get('Financial\Model\ProductId'),
                        $sm->get('Financial\Model\Statement'),
                        $sm->get('Financial\Model\Details')
                    );
                    return $ret;
                },

                'Financial\Model\DbTable\LabelTable' => function($sm) {
                    $tableGateway = $sm->get('LabelTableGateway');
                    $table = new LabelTable($tableGateway);
                    return $table;
                },
                'Financial\Model\DbTable\DescriptionByCVInsurerTable' => function($sm) {
                    $tableGateway = $sm->get('DescriptionByCVInsurerTableGateway');
                    $table = new DescriptionByCVInsurerTable($tableGateway);
                    return $table;
                },
                'Financial\Form\ReportForm' => function ($sm) use ($me) {
                    $translate = $sm->get('ViewHelperManager')->get('translate');
                    $form = new \Financial\Form\ReportForm($translate);
                    $form->prepareElements();
                    return $form;
                },
                'Financial\Form\Payment\Address' => function ($sm) use ($me) {
                    $detailsForm = $sm->get('Financial\Form\Details\1');
                    $ret = new \Zend\Form\Form;

                    $els = array();
                    $els['postcode'] = $detailsForm->get('postcode');
                    $els['address'] = $detailsForm->get('address');

                    $addressLabel = rtrim($els['address']->getLabel(), ':') . ':';
                    $els['address']->setLabel($addressLabel);

                    foreach ($els as $el) {
                        $ret->add($el);
                    }
                    return $ret;
                },
                'session.Report' => function ($sm) {
                    return $sm->get('session.facade')->Report;
                },
                'session.Invoice' => function ($sm) {
                    return $sm->get('session.facade')->Invoice;
                },
                'session.CreditNote' => function ($sm) {
                    return $sm->get('session.facade')->CreditNote;
                },
                'session.appointment' => function ($sm) {
                    return $sm->get('session.facade')->appointment;
                },
                'session.details' => function ($sm) {
                    return $sm->get('session.facade')->details;
                },
                'Financial\Form\Statement\Postcode' => function ($sm) {
                    $_ = $sm->get('ViewHelperManager')->get('translate');
                    $form = new \Financial\Form\PostcodeForm;
                    $form->_ = $_;
                    $form->prepareElements();
                    return $form;
                },
                'Financial\Form\CreditNote' => function ($sm) {
                    $form = new CreditNoteForm;
                    $form->_ = $sm->get('ViewHelperManager')->get('translate');
                    $form->insurers = $sm->get('Financial\Model\DbTable\InsurersTable')->fetchAllByInsuranceCategory('Insurance');
                    $form->fleet = $sm->get('Financial\Model\DbTable\InsurersTable')->fetchAllByInsuranceCategory('Fleet');
                    $form->prepareElements();
                    return $form;
                },
                'Financial\SaveBookingCsv' => function ($sm) {
                    $session = $sm->get('Session\Financial');
                    $modelFacade = $sm->get('Financial\Model\Facade');
                    $controllerPluginManager = $sm->get('ControllerPluginManager');
                    $sharedPlugin = $controllerPluginManager->get('shared');
                    $transKeys = $sharedPlugin->assignDynamicTranslationKeys();
                    $dataFacade = $sm->get('session.facade');
                    $ret = new SaveBookingCsv(
                        $session,
                        $modelFacade,
                        $transKeys,
                        $dataFacade
                    );

                    return $ret;
                },
                'Financial\SaveBookingCsv\Save' => function ($sm) {
                    $saveBookingCsv = $sm->get('Financial\SaveBookingCsv');
                    $sharedVars = $sm->get('sharedVars');
                    $db = $sm->get('Zf1\Db\Financial');
                    #$sessionFinancial = $sm->get('\Session\Financial');
                    $ret = new SaveBookingCsv\Save($saveBookingCsv, $sharedVars, $db);
                    return $ret;
                },
                'Financial\Service\SimpleEmail' => function ($sm) {
                    $transport = $sm->get('Zend\Mail\Transport');
                    return new Service\SimpleEmail($transport);
                },
                'Financial\Email\Confirm\Writer' => function ($sm) {
                    $dataFacade = $sm->get('session.facade');
                    $phpRenderer = $sm->get('ViewRenderer');
                    $translator = $sm->get('translator');
                    $FinancialNs = $sm->get('Session\Financial');

                    $plugins = $sm->get('ControllerPluginManager');
                    $sharedPlugin = $plugins->get('Shared');

                    $addressDetails = $sharedPlugin->getAddressDetails();

                    $ret = new Email\Confirm\Writer(
                            $dataFacade, $phpRenderer, $translator, $FinancialNs, $addressDetails
                    );

                    return $ret;
                },
                'Financial\Email\Confirm' => function ($sm) {
                    $writer = $sm->get('Financial\Email\Confirm\Writer');
                    $sharedVars = $sm->get('sharedVars');
                    $config = $sharedVars['confirmEmail'];
                    $simpleEmail = $sm->get('Financial\Service\SimpleEmail');

                    $ret = new Email\Confirm($writer, $config, $simpleEmail);

                    $ret->setEmailSubjectEnv($sharedVars['emailSubjectEnv']);

                    return $ret;
                },
                'Financial\Email\BackupEmail' => function ($sm) {
                    $writer = $sm->get('Financial\Service\BackupEmail\Writer');
                    $sharedVars = $sm->get('sharedVars');
                    $config = $sharedVars['cccEmail'];

                    $simpleEmail = $sm->get('Financial\Service\SimpleEmail');

                    $ret = new Email\BackupEmail($writer, $config, $simpleEmail);

                    $ret->setEmailSubjectEnv($sharedVars['emailSubjectEnv']);

                    return $ret;
                },
                'Financial\Service\BackupEmail\Writer' => function ($sm) {
                    $dataFacade = $sm->get('session.facade');
                    $phpRenderer = $sm->get('ViewRenderer');
                    $translate = $sm->get('ViewHelperManager')->get('translate');
                    $ret = new Service\BackupEmail\Writer(
                        $dataFacade,
                        $phpRenderer,
                        $translate
                    );

                    return $ret;
                },
                'Financial\Email\CccEmail' => function ($sm) {
                    $sharedVars = $sm->get('sharedVars');

                    return new Email\CccEmail(
                            $sharedVars, $sm->get('Financial\Service\SimpleEmail'), $sm->get('Session\Financial'), $sm->get('ViewRenderer'), $sm->get('translator')
                    );
                },
                /**
                 * This may not be ready when requested 
                 */
                'Financial\layoutViewModel' => function ($sm) use ($me) {
                    return $me->_layoutViewModel;
                },
                'Financial\Webservice' => function ($sm) {
                    $ws = new Webservice($sm->get('Financial\Webservice\WebServiceRequest'), $sm->get('Financial\Webservice\GetConstantService'), $sm->get('translator'));
                    $ws->sharedVars = $sm->get('sharedVars');
                    $ws->cache = $sm->get('Financial\Cache');
                    $ws->db = $sm->get('Zf1\Db\Financial');
                    $ws->sharedVars = $sm->get('sharedVars');
                    $ws->sl = new RestrictedServiceLocator($sm, array(
                        'Financial\Webservice',
                        'Financial\Webservice\Statement',
                    ));

                    return $ws;
                },
                'Financial\Webservice\Statement' => function ($sm) {
                    $logger = $sm->get('log.ws');
                    $stmt = new \Financial\Webservice\Statement($logger);
                    $ws = $sm->get('Financial\Webservice');
                    $stmt->ws = $ws;
                    $stmt->sharedVars = $sm->get('sharedVars');
                    $stmt->cache = $sm->get('Financial\Cache');
                    $stmt->db = $sm->get('Zf1\Db\Financial');
                    return $stmt;
                },
                'Financial\Webservice\WebServiceRequest' => function ($sm) {
                    $logger = $sm->get('log.ws');
                    $ws_request = new \Financial\Webservice\WebServiceRequest($logger);
                    return $ws_request;
                },
                'Financial\Webservice\GetConstantService' => function ($sm) {
                    $constant_service = new \Financial\Webservice\GetConstantService();
                    return $constant_service;
                },
                'Session\Financial' => function ($sm) {
                    return new \Zend_Session_Namespace('Financial');
                },
                'Session\debug' => function ($sm) {
                    return new \Zend_Session_Namespace('debug');
                },
                'Session\FinancialTemp' => function ($sm) {
                    return new \Zend_Session_Namespace('FinancialTemp');
                },
                'Zf1\Db\Financial' => function ($sm) {
                    $config = $sm->get('config');
                    $key = 'Db\Financial';
                    $conf = $config['db']['adapters'][$key];

                    $zf1conf = array(
                        'host' => $conf['host'],
                        'username' => $conf['username'],
                        'password' => $conf['password'],
                        'dbname' => $conf['database'],
                        'driver_options' => $conf['driver_options'],
                    );

                    try {
                        $db = \Zend_Db::factory($conf['driver'], $zf1conf);
                    } catch (\Exception $e) {
                        echo $e->getMessage();
                        die('Could not connect to database');
                    }

                    \Zend_Db_Table::setDefaultAdapter($db);
                    return $db;
                },
                'sharedVars' => function ($sm) use ($me) {
                    return $me->setSharedVars();
                },
                /**
                 * ZF1 cache is better as in ZF2, lifetimes cannot be set per-item.
                 */
                'Financial\Cache' => function ($sm) {
                    $aDay = 60 * 60 * 24;
                    $fo = array(
                        'lifetime' => $aDay,
                        'automatic_serialization' => true,
                        'automatic_cleaning_factor' => 0,
                    );
                    $cacheDir = './data/cache';
                    $bo = array(
                        'cache_dir' => $cacheDir
                    );
                    $cache = \Zend_Cache::factory('core', 'File', $fo, $bo);
                    return $cache;
                },
                /**
                 * Black hole Zend_Cache_Core 
                 */
                'Financial\Cache\Null' => function ($sm) {
                    $cache = \Zend_Cache::factory('core', new \Zend_Cache_Backend_BlackHole);
                    return $cache;
                },
                'Zend\Mail\Transport' => function ($sm) {
                    $config = $sm->get('config');

                    if (!class_exists('\Zend\Mail\Transport\Factory', false)) {
                        require_once __DIR__ . '/src/Zend/Mail/Transport/Factory.php';
                    }

                    return \Zend\Mail\Transport\Factory::create(
                                    $config['mailTransport']
                    );
                },
                /**
                 * Returns a logger for logging webservice calls.
                 * Logs to logws/{Y-m-d}.log and to firephp
                 *
                 * @return \Zend\Log\Logger 
                 */
                'log.ws' => function ($sm) {
                    /**
                     * Logging to <Y-m-d>.log
                     * */
                    $logFile = sprintf('./logws/%s.log', date('Y-m-d'));

                    $logger = new Logger;

                    /*
                      $firephpWriter = $sm->get('Zend\Log\Writer\FirePhp');
                      $logger->addWriter($firephpWriter);
                     */

                    $config = $sm->get('config');
                    $wsLogLevel = $config['wsLogLevel'];

                    if ($wsLogLevel) {
                        // wsLogLevel is at least 1, so let's add the file log writer
                        try {
                            $fileWriter = new \Zend\Log\Writer\Stream($logFile, null, "\n\n");
                            // for prettier logging of non-scalar data
                            // @see \Financial\Log\Formatter\Simple
                            $fileWriter->setFormatter(new \Financial\Log\Formatter\Simple);

                            // log all errors to file
                            $priority = Logger::NOTICE;
                            if (2 <= $wsLogLevel) {
                                // if the wsLogLevel is at least 2, then log successes to file too
                                $priority = Logger::INFO;
                            }
                            $filter = new \Zend\Log\Filter\Priority($priority);

                            if ($wsLogLevel < 3) {
                                // unless the wsLogLevel is at least 3, deny logging any cached ws
                                // calls/responses to file
                                $filterCb = function (array $event) {
                                    $ret = true;
                                    if (strpos($event['message'], '"Duration":"0s"')) {
                                        // ws call was cached, so reject it by returning FALSE
                                        $ret = false;
                                    }

                                    return $ret;
                                };

                                $fileWriter->addFilter(new Log\Filter\Callback($filterCb));
                            }

                            $fileWriter->addFilter($filter);
                            $logger->addWriter($fileWriter);
                        } catch (\Exception $e) {
                            trigger_error($e);
                        }
                    }

                    // log to FirePHP as well
                    $firephpWriter = $sm->get('Zend\Log\Writer\FirePhp');
                    $logger->addWriter($firephpWriter);

                    return $logger;
                },
                'Zend\Log\Writer\FirePhp' => function ($sm) {
                    $writer = new FirePhp();
                    return $writer;
                },
                'Log\Firebug' => function ($sm) {
                    $fpb = '\Zend\Log\Writer\FirePhp\FirePhpBridge';
                    /**
                     * Preferred class with overrides. 
                     */
                    if (!class_exists('\Zend\Log\Writer\FirePhp\FirePhpBridge', false)) {
                        require_once __DIR__ . '/src/Zend/Log/Writer/FirePhp/FirePhpBridge.php';
                    }
                    $writer = $sm->get('Zend\Log\Writer\FirePhp');
                    $logger = new Logger();
                    $logger->addWriter($writer);
                    return $logger;
                },
                'Financial\Model\BranchTable' => function($sm) {
                    $tableGateway = $sm->get('BranchTableGateway');
                    $table = new \Financial\Model\BranchTable($tableGateway);
                    $table->sharedVars = $sm->get('sharedVars');
                    return $table;
                },
                'Financial\Model\BookingsTable' =>  function($sm) {
                    $tableGateway = $sm->get('BookingsTableGateway');
                    $table = new \Financial\Model\BookingsTable($tableGateway);
                    $table->sharedVars = $sm->get('sharedVars');
                    return $table;
                },
                'BookingsTableGateway' => function ($sm) {
                    $dbAdapter = $sm->get('Db\Financial');
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new \Financial\Model\Bookings());
                    return new TableGateway('tx_neFinancialolbtracking_bookings', $dbAdapter, null, $resultSetPrototype);
                },
                'BranchTableGateway' => function ($sm) {
                    $dbAdapter = $sm->get('Db\Financial');
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new \Financial\Model\Branch());
                    return new TableGateway('tx_cfFinancialbol_branches_rendered', $dbAdapter, null, $resultSetPrototype);
                },
                'googleMapsLangCode' => function ($sm) use ($me) {
                    return $me->getGoogleMapsLangCode();
                },
                
                /**
                 * Language of translatable URLs depends on this
                 * */
                'locale' => function ($sm) {
                    $config = $sm->get('config');
                    return $config['translator']['locale'];
                },
                'device' => function ($sm) {
                    return getenv('DEVICE') == 'mobile' ? 'mobile' : 'desktop';
                }
            ),
            'invokables' => array(),
            'services' => array(),
            'shared' => array(
                'Financial\Webservice\Statement' => false,
                'Financial\Model\ShadowTable' => false,
                'Financial\Form\Statement\Postcode' => false,
                'Financial\Form\StatementMobileAddressForm' => false,
                'Financial\Form\Details\1' => false,
                'Financial\Form\Payment\Address' => false,
                'session.facade.now' => false,
            ),
        );
    }

    /**
     * Returns the lang code needed for google maps services.
     * If the postRoute event has been triggered, the lang should have been
     * determined by the url (e.g. /Financial/Invoice). Otherwise a fallback
     * language set in the config will be used.
     * 
     * @access public
     * @return void
     */
    public function getGoogleMapsLangCode()
    {
        if ($this->googleMapsLangCode) {
            $ret = $this->googleMapsLangCode;
        } else {
            $sharedVars = $this->_sm->get('sharedVars');
            $ret = $sharedVars['googleMaps']['defaultLangCode'];
        }

        return $ret;
    }

    public function setSharedVars()
    {
        // Grab the static constants held in global & local.php
        $conf = $this->_sm->get('config');
        $staticSharedVars = $conf['staticSharedVars'];

        // Set the dynamic constants
        $this->_sharedVars['time'] = time();
        $this->_sharedVars['getdate'] = getdate();
        $this->_sharedVars['resUri'] = $this->resUri;
        $this->_sharedVars['uploadUri'] = $this->uploadUri;
        $this->_sharedVars['isDebugOutputSafe'] = $this->isDebugOutputSafe;
        $this->_sharedVars['isDebugInvisibleSafe'] = $this->isDebugInvisibleSafe;

        // Return the static and dynamic sharedVars as a merged array, set to 'sharedVars' SM
        return array_merge($staticSharedVars, $this->_sharedVars);
    }

    public function getControllerPluginConfig()
    {
        return array(
            'invokables' => array(
                'assignJsVar' => 'Financial\Controller\Plugin\AssignJsVar',
                'importTranslations' => 'Financial\Controller\Plugin\ImportTranslations',
                'shared' => 'Financial\Controller\Plugin\Shared',
            ),
        );
    }

    public function getViewHelperConfig()
    {
        return array(
            'invokables' => array(
                'baseUrl' => 'Financial\View\Helper\BaseUrl',
                'resUri' => 'Financial\View\Helper\ResUri',
                'uploadUri' => 'Financial\View\Helper\UploadUri',
                'templateMap' => 'Financial\View\Helper\TemplateMap',
                'translateKey' => 'Financial\View\Helper\TranslateKey',
                'version' => 'Financial\View\Helper\Version',
            ),
            'factories' => array(
            )
        );
    }

    public function onBeforeRender(\Zend\Mvc\MvcEvent $e)
    {

        $view = $e->getViewModel();

        /**
         * Close the ob_start() added from onBootstrap()
         * */
        $contents = ob_get_contents();
        ob_end_clean();
        if ($this->viewModelIsJson) {
            if ($contents && $GLOBALS['isDebugInvisibleSafe']) {
                $view->setVariable('output', explode("\n", $contents));
            }
        } else {
            $view->setVariable('output', $contents);
        }
    }

}
        
