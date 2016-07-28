<?php

class FinancialController extends Zend_Controller_Action
{
    protected $_fpDs;

    /**
     * Validates form values and returns Zend_Filter_Input object
     *
     * @return object $input
     */
    protected function _validateAjaxForm()
    {
        $pattern = "/[0-9]+\.[0-9]+/";
        if(!preg_match_all($pattern, $_POST['location']))
            $filters = array(
                'location'     => array(
                    'StringTrim',
                    array('Alnum', array('allowwhitespace' => true))
                ),
            );
        else
            $filters = array();

        $validators = array(
            'location' => array(
                'allowEmpty' => false,
                array('StringLength', array('max' => 50))
            ),
        );

        $data = $_POST;

        $input = new Zend_Filter_Input($filters, $validators, $data);

        return $input;
    }

    /**
     * Validates form values and returns Zend_Filter_Input object
     *
     * @return object $input
     */
    protected function _validateSingleTransAjax()
    {
        $filters = array(
            'key'     => array(
                'StringTrim',
                array('Alnum', array('allowwhitespace' => false))
            ),
            'name'     => array(
                'StringTrim',
                array('Alnum', array('allowwhitespace' => true))
            ),
            'dbId'     => array(
                'StringTrim',
                array('Alnum', array('allowwhitespace' => false))
            ),
            'offset'     => array(
                'StringTrim',
            ),
        );

        $validators = array(
            'key' => array(
                'allowEmpty' => false,
                array('StringLength', array('max' => 20))
            ),
            'name' => array(
                'allowEmpty' => false,
                array('StringLength', array('max' => 40))
            ),
            'dbId' => array(
                'allowEmpty' => false,
                array('StringLength', array('max' => 10))
            ),
            'offset' => array(
                'Digits',
                array('Between', 0, 16)
            ),
        );

        $data = $_POST;

        $input = new Zend_Filter_Input($filters, $validators, $data);

        return $input;
    }


    /**
     * Validates form values and returns Zend_Filter_Input object
     *
     * @return object $input
     */
    protected function _validateQualCompleteMobileForm()
    {
        $filters = array(
            '*'     => array(
                'StringTrim',
            ),
        );

        $validators = array(
            'time' => array(
                'allowEmpty' => false,
                array('StringLength', array('max' => 20))
            ),
            'date' => array(
                'allowEmpty' => false,
                array('StringLength', array('max' => 30))
            ),
            'dateFormat' => array(
                'allowEmpty' => false,
                array('StringLength', array('max' => 10))
            ),
            'dayPart' => array(
                'allowEmpty' => false,
                array('StringLength', array('max' => 10))
            ),
            'accountType' => array(
                'allowEmpty' => false,
                array('StringLength', array('max' => 6))
            ),
            'jobLocation' => array(
                'allowEmpty' => false,
                array('StringLength', array('max' => 50))
            ),
            'TransId' => array(
                'allowEmpty' => false,
                'Digits',
                array('StringLength', array('max' => 20))
            ),
        );

        $data = $_POST;

        $input = new Zend_Filter_Input($filters, $validators, $data);

        return $input;
    }

    /**
     * Validates form values and returns Zend_Filter_Input object
     *
     * @return object $input
     */
    protected function _validateQualCompleteTransForm()
    {
        $filters = array(
            '*'     => array(
                'StringTrim',
            ),
        );

        $validators = array(
            'time' => array(
                'allowEmpty' => false,
                array('StringLength', array('max' => 20))
            ),
            'date' => array(
                'allowEmpty' => false,
                array('StringLength', array('max' => 30))
            ),
            'dateFormat' => array(
                'allowEmpty' => false,
                array('StringLength', array('max' => 10))
            ),
            'accountType' => array(
                'allowEmpty' => false,
                array('StringLength', array('max' => 6))
            ),
            'jobLocation' => array(
                'allowEmpty' => false,
                array('StringLength', array('max' => 50))
            ),
            'TransId' => array(
                'allowEmpty' => false,
                'Digits',
                array('StringLength', array('max' => 20))
            ),
            'TransName' => array(
                'allowEmpty' => false,
                array('StringLength', array('max' => 30))
            ),
            'TransAddress' => array(
                'allowEmpty' => false,
                array('StringLength', array('max' => 150))
            ),
            'TransPhone' => array(
                'allowEmpty' => true,
                array('StringLength', array('min' => 0, 'max' => 20))
            ),
            'TransWifi' => array(
                'allowEmpty' => true,
                array('StringLength', array('min' => 0, 'max' => 1))
            ),
            'TransDistance' => array(
                'allowEmpty' => false,
                array('StringLength', array('max' => 10))
            ),
        );

        $data = $_POST;

        $input = new Zend_Filter_Input($filters, $validators, $data);

        return $input;
    }

    /**
     * @param object $input Zend_Filter_Input object
     */
    protected function _moduleComplete($input)
    {
        $ns =& $this->_fpDs->accounts;
        // set module complete in session
        $this->_fpDs->completed['accounts'] = true;

        // add form values to session
        $this->_fpDs->accounts['time'] = $input->time;
        $this->_fpDs->accounts['date'] = $input->date;
        $this->_fpDs->accounts['dateFormat'] = $input->dateFormat;
        $this->_fpDs->accounts['dayPart'] = $input->dayPart;
        $this->_fpDs->accounts['accountType'] = $input->accountType;
        $this->_fpDs->accounts['TransId'] = $input->TransId;
        $this->_fpDs->accounts['TransName'] = $input->TransName;
        $this->_fpDs->accounts['TransAddress'] = $input->TransAddress;
        $this->_fpDs->accounts['TransPhone'] = $input->TransPhone;
        $this->_fpDs->accounts['TransWifi'] = $input->TransWifi;
        $this->_fpDs->accounts['TransDistance'] = $input->TransDistance;

        $accountsTimeRange = explode(' - ', $ns['time']);
        // strtotime()-able datetime
        $accountsDateStart = sprintf('%s %s', $ns['dateFormat'], $accountsTimeRange[0]);
        $accountsDateEnd = isset($accountsTimeRange[1]) ?
            sprintf('%s %s', $ns['dateFormat'], $accountsTimeRange[1]) :
            false;
        /**
         * @todo Make sure the date format in the session is properly extractable.
         **/
        $accountsTimeStart = strtotime($accountsDateStart);
        $ns['startTime'] = $accountsTimeStart;
        $ns['startDate'] = date('c', $accountsTimeStart);
        $ns['endTime'] = false;
        $ns['endDate'] = false;
        if ($accountsDateEnd) {
            $accountsTimeEnd = strtotime($accountsDateEnd);
            $ns['endTime'] = $accountsTimeEnd;
            $ns['endDate'] = date('c', $accountsTimeEnd);
        }

        $jobLocation = $input->jobLocation;
        if ('mobile' == $input->accountType) {

            if (!isset($this->_fpDs->accounts['mobile']['postcode'])) {
                if($this->_fpDs->accounts)
                    $msg = "mobile address should be set if job type is mobile, " .
                        "but it isn't.";
                $errorLevel = 'development' == APPLICATION_ENV ? E_USER_ERROR : E_USER_WARNING;
                trigger_error($msg, $errorLevel);
            }
            else {
                $jobLocation = $this->_fpDs->accounts['mobile']['postcode'];
            }

        }
        $this->_fpDs->accounts['jobLocation'] = $jobLocation; // The location used to trigger the map on summary/confirm pages

        // dispatch to next module handler
        $this->_forward('goto-module', 'index');
    }

    /*
     *  Prepare a few other bits
     */
    public function init()
    {
        $this->_helper->shared('assignPostcodeFieldErrorTranslations');

        $amendDetailsAjaxUrl = $this->view->url(array (
            'action' => 'amend-details-ajax',
            'controller' => 'details'
        ));

        $saveMobileAddressAjaxUrl = $this->view->url(array (
            'action' => 'save-mobile-address-ajax'
        ));

        $this->view->amendDetailsAjaxUrl = $amendDetailsAjaxUrl;
        $this->view->saveMobileAddressAjaxUrl = $saveMobileAddressAjaxUrl;

        // Pass current controller to view for progress tracker
        $this->view->curModule = $this->getRequest()->getControllerName();

        // Pass all session data to the view
        $this->_fpDs = new Zend_Session_Namespace('fp');
        if (sizeof($this->_fpDs->getIterator()) > 0) {
            foreach ($this->_fpDs->getIterator() as $index => $val) {
                //echo $index . ' - ' . $val . '<br>';
                $this->view->$index = $this->_fpDs->$index;
            }
        }

        // form extension functionality
        if ($loc = Default_Model_Debug::getGetValue('location')) {
            $this->_fpDs->accounts['mapSearchLocation'] = $loc;
        }

        $this->_helper->ajaxContext
            ->addActionContext('Trans-lookup-ajax', 'json')
            ->addActionContext('save-mobile-address-ajax', 'json')
            ->setAutoJsonSerialization(false)
            ->initContext();
    }


    /**
     * Holding page for the email template
     */
    /*public function emailAction()
    {
    }*/

    /**
     * Ajax request for Transactions example
     */
    public function qualifiedAjaxAction()
    {
        $t = Zend_Registry::get('Zend_Translate');

        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $result = null;

        if (empty($this->_fpDs->completed['quote'])) {
            $result = array(
                'redirect' => $this->view->url( array(
                    'action' => 'index',
                    'controller' => 'quote',
                    'module' => 'default'
                ))
            );
        }
        else if ($this->getRequest()->isPost()) {
            $input = $this->_validateAjaxForm();

            if ($input->isValid()) {
                try {
                    $this->_fpDs->accounts['mapSearchLocation'] = $input->location;

                    $accounts = Default_Model_Factory::accounts();
                    $accounts->location = $input->location;
                    $accounts->dateFrom = time();
                    $accounts->numDays = 14;
                    $accounts->getTranses();
                    $accounts->getEntries();

                    $result = $accounts->Transes;
                    #print_r($accounts->Transes);
                }
                catch (Exception $e) {
                    trigger_error($e, E_USER_WARNING);
                }


            }
        }

        if (false === $result) {
            // bad postcode or city given
        }
        else {
            if (!$result) {
                $result = array ();
            }
            if (!empty($result['Entries'])) {
                $result['success'] = true;
            }
            else {
                $result['success'] = false;
                $result['msg'] = $t->_('accountsServiceDownErrorMessage');
            }

            $obContents = ob_get_contents();
            if ($obContents) {
                $result['output'] = $obContents;
                ob_clean();
            }
        }

        header('Content-Type: application/json');
        echo Zend_Json::encode($result);
        exit;

    }

    /**
     * Ajax request more Trans Entries
     */
    public function singleTransSlotsAjaxAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $result = false;

        if (empty($this->_fpDs->completed['quote'])) {
            $result = array(
                'redirect' => $this->view->url( array(
                    'action' => 'index',
                    'controller' => 'quote',
                    'module' => 'default'
                ))
            );
        }
        else if ($this->getRequest()->isPost()) {
            $input = $this->_validateSingleTransAjax();

            if ($input->isValid()) {

                $dateFrom = strtotime('+' . $input->offset . ' day');

                try {
                    $accounts = Default_Model_Factory::accounts();
                    $accounts->dateFrom = $dateFrom;
                    $accounts->numDays = 14;
                    $accounts->buildManualTrans($input);
                    $accounts->getTransEntries();

                    $result = $accounts->Transes;
                }
                catch (Exception $e) {
                    trigger_error($e, E_USER_WARNING);
                }


            }
        }

        header('Content-Type: application/json');
        echo Zend_Json::encode($result);
        exit;

    }

    /**
     * Ajax request more mobile Entries
     */
    public function mobileMoreEntriesAjaxAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $result = false;

        if (empty($this->_fpDs->completed['quote'])) {
            $result = array(
                'redirect' => $this->view->url( array(
                    'action' => 'index',
                    'controller' => 'quote',
                    'module' => 'default'
                ))
            );
        }
        else if ($this->getRequest()->isPost()) {
            $input = $this->_validateMobileAjaxForm();

            if ($input->isValid()) {

                try {
                    $dateFrom = strtotime('+' . $input->offset . ' day');

                    $accounts = Default_Model_Factory::accounts();
                    $accounts->location = $input->location;
                    $accounts->mobileKey = $input->key;
                    $accounts->dateFrom = $dateFrom;
                    $accounts->numDays = 14;
                    $accounts->getMobileEntries();

                    $result = $accounts->Transes;
                    #print_r($accounts->Transes);
                }
                catch (Exception $e) {
                    trigger_error($e, E_USER_WARNING);
                }

            }
        }

        header('Content-Type: application/json');
        echo Zend_Json::encode($result);
        exit;

    }

    /**
     * Ajax request for Transactions
     */
    public function unqualifiedAjaxAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $result = false;

        if ($this->getRequest()->isPost()) {

            $input = $this->_validateAjaxForm();

            if ($input->isValid()) {
                $this->_fpDs->accounts['mapSearchLocation'] = $input->location;

                $accounts = Default_Model_Factory::accounts();
                $accounts->location = $input->location;
                $accounts->getTranses();
                $accounts->setquote();
                $accounts->getEntriesUnqual();

                $result = $accounts->Transes;

            }
        }

        header('Content-Type: application/json');
        echo Zend_Json::encode($result);
        exit;

    }

    /*
     *
     */
    public function saveMobileAddressAjaxAction() {
        $resp = array ();
        $resp['success'] = false;
        $varNames = array (
            'postcode', 'housenumber', 'housenumber_extension',
            'address1', 'city', 'comment'
        );
        $vals = array ();
        foreach ($varNames as $varName) {
            $val = $this->_getParam($varName);
            if (!isset($val)) {
                trigger_error("'$varName' missing from POST");
            }
            $vals[$varName] = $val;
        }
        $vals['housenumber'] = substr($vals['housenumber'], 0, 4);
        $vals['housenumber_extension'] = substr($vals['housenumber_extension'], 0, 4);
        if (!isset($this->_fpDs->accounts['mobile'])) {
            $this->_fpDs->accounts['mobile'] = array ();
        }
        $this->_fpDs->accounts['mobile'] = array_merge(
            $this->_fpDs->accounts['mobile'], $vals
        );
        $resp['success'] = true;

        header('Content-Type: application/json');
        echo Zend_Json::encode($resp);
        exit;
    }

    /**
     * Qualified accounts page - ie quote complete
     */
    public function qualifiedAction()
    {
        // Redirect to unqualified page if quote incompleted
        if (empty($this->_fpDs->completed['quote'])) {
            $this->_helper->redirector('index', 'accounts');
        }

        $this->view->importTranslations(array (
            'accountsServiceDownErrorMessage',
            'your accounts not found'
        ));

        $url = new Zend_View_Helper_Url();
        $t = Zend_Registry::get('Zend_Translate');

        $this->view->assignJsVar('is_qualified', true);
        $this->view->assignJsVar('accountType', $this->_fpDs->quote['accountType']);
        $this->view->assignJsVar('restrictMobile', Zend_Registry::get('restrictMobile'));

        if (Zend_Registry::get('isMobile'))
            $this->_helper->viewRenderer('mobile/qualified');

        $locationForm = new Form_PostcodeForm();
        if (!empty($this->_fpDs->accounts['jobLocation'])) {
            if (isset($this->_fpDs->accounts['mapSearchLocation'])) {
                $mapSearchLocation = $this->_fpDs->accounts['mapSearchLocation'];
            }
            else {
                $mapSearchLocation = $this->_fpDs->accounts['jobLocation'];
            }
            $locationForm->getElement('location')->setValue($mapSearchLocation);
            $this->view->location = $mapSearchLocation;
        }
        $this->view->locationForm = $locationForm;

        if ($this->getRequest()->isPost()) {
            if ($this->_request->getParam('accountType') == 'mobile') {
                $input = $this->_validateQualCompleteMobileForm();
            } else  {
                $input = $this->_validateQualCompleteTransForm();  // accountType == 'Trans'
            }

            if ($input->isValid()) {
                $this->_moduleComplete($input);
            } else {
                $this->view->errors = $input->getMessages();
            }
        }

        $this->_helper->assignJsVar('currentPage', 'accounts');

        // Create view variable for accounts length copy
        $Stage = $this->_fpDs->quote['whichStage'];
        $accountType = $this->_fpDs->quote['accountType'];
        $this->view->accountType = $accountType;
        $this->view->copyBlock = $Stage . ' ' . $accountType;

        $gotoString = null;
        $gotoButtonTextTranslated = null;
        if (!empty($this->_fpDs->completed['Insurance'])) {
            $gotoString = 'gotoDetails';
            $gotoButtonTextTranslated = $t->_('fp253');
        }
        else {
            $gotoString = 'gotoInsurance';
            $gotoButtonTextTranslated = $t->_('fp251');
        }
        $this->view->gotoString = $gotoString;
        $this->view->gotoButtonTextTranslated = $gotoButtonTextTranslated;
    }

    /**
     * Unqualified accounts page - ie quote not completed
     */
    public function indexAction()
    {

        $this->_helper->shared('saveBooking');
        // Redirect to qualified page if quote completed
        if (!empty($this->_fpDs->completed['quote'])) {
            $this->_helper->redirector('qualified', 'accounts');
        }

        $this->_helper->assignJsVar('currentPage', 'accounts');

        if (Zend_Registry::get('isMobile'))
            $this->_helper->viewRenderer('mobile/index');

        $url = new Zend_View_Helper_Url();
        $t = Zend_Registry::get('Zend_Translate');

        $locationForm = new Form_PostcodeForm();
        $locationForm->setAttrib('id', 'frmPostcodeLookupUnqual');

        $mapSearchLocation = '';
        if (isset($this->_fpDs->accounts['mapSearchLocation'])) {
            $mapSearchLocation = $this->_fpDs->accounts['mapSearchLocation'];
        }
        else if (isset($this->_fpDs->accounts['jobLocation'])) {
            $mapSearchLocation = $this->_fpDs->accounts['jobLocation'];
        }

        $locationForm->getElement('location')->setValue($mapSearchLocation);
        $this->view->location = $mapSearchLocation;
        $this->view->locationForm = $locationForm;

        if ($this->getRequest()->isPost()) {

            $input = $this->_validateUnqualCompleteForm();

            if ($input->isValid()) {
                // Set jobLocation to session for use on qualified page
                $this->_fpDs->accounts['jobLocation'] = $input->location;
                // Goto quote assessment
                $this->_helper->redirector('index', 'quote');

            } else {
                $this->view->errors = $input->getMessages();
            }


            // Direct access to page or when coming from another module, so populate location from session if exists
        } else {
            // pass location in session to view to pre-fill input
            #$this->view->location = array('location' => $this->_fpDs->accounts['jobLocation']);
        }

    }


}
