<?php
/**
 * @copyright Netim (https://www.netim.com)
 * @license   GPL-3.0
 *
 * This source file is subject to the GPL-3.0 License that is bundled
 * with this source code in the file LICENSE
 * 
 * Module documentation is available at https://support.netim.com/en/docs/fossbilling
 * Support at modules-support@netim.com
 */

require_once __DIR__ . "/Netim/api/APISoap.php";
require_once(__DIR__ . "/Netim/api/NetimAPIException.php");
require_once(__DIR__ . "/Netim/lib/NormalizedContact.php");

use Netim\NormalizedContact;

class Registrar_Adapter_Netim extends Registrar_AdapterAbstract
{
    private $config = array(
        'Username'   => null,
        'Password' => null,
        'logAPI' => null,
        'logFunction' => null,
    );

    private const MODULE_VERSION = "0.1";
    private const PATH_LOG = "./library/Registrar/Adapter/Netim";
    private const DIR_LOG = "logs";
    private const FILE_LOG = "netim.log";
    private const TLDLIST_URL = "https://www.netim.com/tld-list.xml";

    public function __construct($options)
    {
        if (!class_exists('SoapClient')) 
            throw new Registrar_Exception('NETIM Registrar module error<br>SOAP functions are not available<br>Please check PHP pre-requisites at https://support.netim.com/en/docs/fossbilling/installation"');

        if(isset($options['Username']) && !empty($options['Username'])) {
            $this->config['Username'] = $options['Username'];
            unset($options['Username']);
        } 
        else 
            throw new Registrar_Exception('NETIM Registrar module error.<br>Please update configuration parameter "Reseller ID" at "Configuration -> Domain registration"');

        if(isset($options['Password']) && !empty($options['Password'])) {
            $this->config['Password'] = $options['Password'];
            unset($options['Password']);
        } 
        else 
            throw new Registrar_Exception('NETIM Registrar module error.<br>Please update configuration parameter "Reseller Password" at "Configuration -> Domain registration"');
    
        if(isset($options['logAPI']) && !empty($options['logAPI'])) {
            $this->config['logAPI'] = $options['logAPI'];
            unset($options['logAPI']);
        } 

        if(isset($options['logFunction']) && !empty($options['logFunction'])) {
            $this->config['logFunction'] = $options['logFunction'];
            unset($options['logFunction']);
        } 
    }
    
    /**
     * Return array with configuration
     */

    public static function getConfig()
    {
        return array(
            'label'     =>  'Netim provide more than 1000 global extensions (ccTlds, gTlds, ngTlds) with a single registrar module. You will need to open a reseller account in order to use our registrar services. To do so please start the signup process here (http://www.netim.com/reseller/) or contact our sales team (sales@netim.com).
            Please note that production and testing plateforms are differents. If you want to use the test mode, you will need an additional test account for the Operational Testing Environment. Your production account will not work on that environment.',
            'form'  => array(
                'Username' => array('text', array(
                            'label' => 'Reseller ID',
                            'description'=> '',
                            'required' => true,
                        ),
                     ),
                'Password' => array('password', array(
                            'label' => 'Reseller password',
                            'description'=> '',
                            'required' => true,
                        ),
                    ),
                'logAPI' => array('radio', array(
                            'label' => 'log API Function',
                            'Description' => '',
                            'required' => true,
                            'multiOptions' => array(
                                true => 'Yes',
                                false => 'No',
                            ),
                        ),
                    ),
                'logFunction' => array('radio', array(
                            'label' => 'log Function',
                            'Description' => '',
                            'required' => true,
                            'multiOptions' => array(
                                true => 'Yes',
                                false => 'No',
                            ),
                        ),
                    ),
            ),
        );
    }
    
    /**
     * Returns an array of top-level domains (TLDs) that the registrar is capable of registering.
     * // CAUTION: Not sure that this function is used at this time
     */
    public function getTlds()
    {       
        $tlds = [];
        
        # Download datas
        $xmlstr=file_get_contents(self::TLDLIST_URL);

        if(!empty($xmlstr)){
            # Parse xml to build the array of data
            $xmlobj = simplexml_load_string($xmlstr);

            foreach($xmlobj->extension as $element)
                $tlds[] = "." . (string)$element["name"];
        }

        return $tlds;
    }

    /**
     * Checks if a domain is available for registration.
     */
    public function isDomainAvailable(Registrar_Domain $domain)
    {        
        $dom = $domain->getSld() . $domain->getTld();
        
        # Request domain availability check
        $api = $this->_getAPI();
        try {
            $response = $api->domainCheck($dom)[0];
        }
        catch(NetimAPIException $exception) {
            throw new Registrar_Exception($exception->getMessage());
        }
        finally {
            $this->_logAPIRequest(__FUNCTION__, $api, "domainCheck", ["domain"=>$dom]);
        }

        # Process result
        if($response->result == "AVAILABLE" && empty($response->reason))
            return true;
        
        if($response->result == "AVAILABLE" && $response->reason=="PREMIUM")
            throw new Registrar_Exception("Premium domains cannot be registered.");

        if(in_array($response->result,["NOT SOLD","CLOSED"]))
            throw new Registrar_Exception("This domain name cannot be registered.");    

        if($response->result == "UNKNOWN")
            throw new Registrar_Exception("An unexpected error occured, please retry.");

        return false;
    }

    /**
     * Checks if a domain can be transferred to the registrar.
     */
    public function isDomaincanBeTransferred(Registrar_Domain $domain)
    {
        // For testing purpose
        if(preg_match('#fossbilling-transfer#',$domain->getSld()))
            return true;
        
        $dom = $domain->getSld() . $domain->getTld();

        # Request domain availability check
        $api = $this->_getAPI();
        try {
            $response = $api->domainCheck($dom)[0];
        }
        catch(NetimAPIException $exception) {
            throw new Registrar_Exception($exception->getMessage());
        }
        finally {
            $this->_logAPIRequest(__FUNCTION__, $api, "domainCheck", ["domain"=>$dom]);
        }

        # Process result
        if($response->result == "NOT AVAILABLE" && empty($response->reason))
            return true;

        if($response->result == "NOT AVAILABLE" && $response->reason=="PREMIUM")
            throw new Registrar_Exception("Premium domains cannot be transfered.");

        if(in_array($response->result,["NOT SOLD","CLOSED"]))
            throw new Registrar_Exception("This domain name cannot be transfered.");  

        if($response->result == "UNKNOWN")
            throw new Registrar_Exception("An unexpected error occured, please retry.");

        return false;
    }

    /**
     * Modifies the name servers for a domain.
     */
    public function modifyNs(Registrar_Domain $domain)
    {
        $this->_logFunction(__FUNCTION__, $domain, null);
        $dom = $domain->getSld() . $domain->getTld();

        # Request domain update
        $ns1 = $domain->getNs1() ?? "";
        $ns2 = $domain->getNs2() ?? "";
        $ns3 = $domain->getNs3() ?? "";
        $ns4 = $domain->getNs4() ?? "";
        $ns5 = "";
        
        $api = $this->_getAPI();
        try {
            $response = $api->domainChangeDNS($dom,$ns1,$ns2,$ns3,$ns4,$ns5);
        }
        catch(NetimAPIException $exception) {
            throw new Registrar_Exception($exception->getMessage());
        }
        finally {
            $this->_logAPIRequest(__FUNCTION__, $api, "domainChangeDNS", ["domain"=>$dom,"ns1"=>$ns1,"ns2"=>$ns2,"ns3"=>$ns3,"ns4"=>$ns4,"ns5"=>$ns5]);
        }

        # Process result
        if (!in_array($response->STATUS, ["Done", "Pending"])) {
            throw new Registrar_Exception($response->MESSAGE);
        }

        return true;
    }

    /**
     * Modifies the contact information for a domain.
     */
    public function modifyContact(Registrar_Domain $domain)
    {
        $this->_logFunction(__FUNCTION__, $domain, null);
        $dom = $domain->getSld() . $domain->getTld();
        
        # Get domain info
        $api = $this->_getAPI();
        try {
            $result = $api->domainInfo($dom);
        }
        catch(NetimAPIException $exception) {
            throw new Registrar_Exception($exception->getMessage());
        }
        finally {
            $this->_logAPIRequest(__FUNCTION__, $api, "domainInfo", ["domain"=>$dom]);
        }

        # Prepare data
        $id_owner = $result->idOwner;
        $contact_owner = $this->_prepareContactInfo($domain,1);
        $id_contact= $result->idAdmin;
        $contact_admin = $this->_prepareContactInfo($domain,0);

        # Request contact updates
        try{
            $response = $api->contactOwnerUpdateObj($id_owner, $contact_owner);
        }
        catch(NetimAPIException $exception) {
            throw new Registrar_Exception($exception->getMessage());
        }
        finally {
            $this->_logAPIRequest(__FUNCTION__, $api, "contactOwnerUpdateObj", ["idContact"=>$id_owner,"contact"=>$contact_owner]); 
        }

        try{
            $response = $api->contactUpdateObj($id_contact, $contact_admin);
        }
        catch(NetimAPIException $exception) {
            throw new Registrar_Exception($exception->getMessage());
        }
        finally {
            $this->_logAPIRequest(__FUNCTION__, $api, "contactUpdateObj", ["idContact"=>$id_contact,"contact"=>$contact_admin]); 
        }  

        return true;
    }

    /**
     * Transfers a domain to the registrar.
     */
    public function transferDomain(Registrar_Domain $domain)
    {
        $this->_logFunction(__FUNCTION__, $domain, null);
        $dom = $domain->getSld() . $domain->getTld();

        # Check transfer status   
        if(!$this->isDomaincanBeTransferred($domain)){
            $this->_logFunction(__FUNCTION__, $domain, 'Domain is not transferrable');
            throw new Registrar_Exception('Domain is not transferrable');
        }

        # Is already pending transfer ?
        if($this->_isAlreadyPending($dom, "domainTransferIn")){
            $this->_logFunction(__FUNCTION__, $dom, 'Domain is already pending');
            throw new Registrar_Exception('Domain is already pending');
        }
   
        # Create domain contact objects
        $contact = $this->_prepareContactInfo($domain);
        $api = $this->_getAPI();
        try {
            $id_owner = $api->contactCreateObj($contact);
            $this->_logAPIRequest(__FUNCTION__, $api, "contactCreateObj", ["contact"=>$contact]);

            $contact->setIsOwner(0);
            $id_admin = $api->contactCreateObj($contact);
            $this->_logAPIRequest(__FUNCTION__, $api, "contactCreateObj", ["contact"=>$contact]);

            $id_tech = $id_billing = $id_admin;
        }
        catch(NetimAPIException $exception) {
            $this->_logAPIRequest(__FUNCTION__, $api, "contactCreateObj", ["contact"=>$contact]);
            throw new Registrar_Exception($exception->getMessage());
        }

        # Request domain registration
        $epp = $domain->getEpp() ?? "";
        $ns1 = $domain->getNs1() ?? "";
        $ns2 = $domain->getNs2() ?? "";
        $ns3 = $domain->getNs3() ?? "";
        $ns4 = $domain->getNs4() ?? "";
        $ns5 = "";

        try {
            $response = $api->domainTransferIn($dom, $epp, $id_owner, $id_admin, $id_tech, $id_billing, $ns1, $ns2, $ns3, $ns4, $ns5);
        } 
        catch (NetimAPIException $exception) {
            throw new Registrar_Exception($exception->getMessage());
        }
        finally {
            $this->_logAPIRequest(__FUNCTION__, $api, "domainTransferIn", ["domain"=>$dom, "epp"=>$epp, "id_owner"=>$id_owner, "id_admin"=>$id_admin, "id_tech"=>$id_tech, "id_billing"=>$id_billing, "ns1"=>$ns1, "ns2"=>$ns2, "ns3"=>$ns3, "ns4"=>$ns4, "ns5"=>$ns5]);
        }

        # Process result
        if ($response->STATUS=="Failed"){
            throw new Registrar_Exception($response->MESSAGE);
        }

        /* Transfers are always pending = Feature request to be implemented
        if ($response->STATUS=="Pending")
            throw new Registrar_Exception("The domain registration is pending");
        */

        return true;
    }

    /**
     * Returns the details of a registered domain.
     */
    public function getDomainDetails(Registrar_Domain $d)
    {   
        $dom = $d->getSld() . $d->getTld();

        # Get domain info
        $api = $this->_getAPI();
        try {
            $domainInfo = $api->domainInfo($dom);
        }
        catch(NetimAPIException $exception) {
            throw new Registrar_Exception($exception->getMessage());
        }
        finally {
            $this->_logAPIRequest(__FUNCTION__, $api, "domainInfo", ["domain"=>$dom]);
        }

        # Get contact info
        try {
            $reg = $api->contactInfo($domainInfo->idOwner);
        }
        catch(NetimAPIException $exception) {
            throw new Registrar_Exception($exception->getMessage());
        }
        finally {
            $this->_logAPIRequest(__FUNCTION__, $api, "contactInfo", ["id"=>$domainInfo->idOwner]);
        }

        # Process result
        $d->setEpp($domainInfo->authID);
        $d->setPrivacyEnabled($domainInfo->whoisPrivacy);
        $d->setLocked($domainInfo->domainIsLock);

        $ns = $domainInfo->ns;
        foreach($ns as $key => $data) {
            $fct = "setNs".($key+1);
            $d->$fct($data);
        }

        $phone = explode(" ",$reg->phone);
        $tel = $phone[1];
        $telCC = str_replace("+","",$phone[0]);

        $c = $d->getContactRegistrar();
        $c->setFirstName($reg->firstName)
          ->setLastName($reg->lastName)
          ->setEmail($reg->email)
          ->setCity($reg->city)
          ->setZip($reg->zipCode)
          ->setCountry($reg->country)
          ->setState($reg->area)
          ->setTel($tel)
          ->setTelCc($telCC)
          ->setAddress1($reg->address1)
          ->setAddress2($reg->address2);

        $d->setContactRegistrar($c);
        
        $this->_logFunction(__FUNCTION__, $dom, $d);
        return $d;
    }

    /**
     * Returns the domain transfer code (also known as the EPP code or auth code) for a domain.
     */
    public function getEpp(Registrar_Domain $domain)
    {
        $this->_logFunction(__FUNCTION__, $domain, null);
        $dom = $domain->getSld() . $domain->getTld();

        # Request new EPP code
        $api = $this->_getAPI();
        try {
            $result = $api->domainAuthID($dom, 1);
        }
        catch(NetimAPIException $exception) {
            throw new Registrar_Exception($exception->getMessage());
        }
        finally {
            $this->_logAPIRequest(__FUNCTION__, $api, "domainAuthID", ["domain"=>$dom]);
        }

        throw new Registrar_Exception("EPP code has been sent to registrant's email address");
    }

    /**
     * Registers a domain with the registrar.
     */
    public function registerDomain(Registrar_Domain $domain)
    {   
        $dom = $domain->getSld() . $domain->getTld();

        # Check domain availability   
        if(!$this->isDomainAvailable($domain)){
            $this->_logFunction(__FUNCTION__, $domain, 'Domain is not available for registration');
            throw new Registrar_Exception('Domain is not available for registration');
        }

        # Is already pending registration ?
        if($this->_isAlreadyPending($dom,"domainCreate")){
            $this->_logFunction(__FUNCTION__, $dom , 'Domain is already pending');
            throw new Registrar_Exception('Domain is already pending');
        }

        # Create domain contact objects
        $contact = $this->_prepareContactInfo($domain);
        $api = $this->_getAPI();
        try {
            $id_owner = $api->contactCreateObj($contact);
            $this->_logAPIRequest(__FUNCTION__, $api, "contactCreateObj", ["contact"=>$contact]);

            $contact->setIsOwner(0);
            $id_admin = $api->contactCreateObj($contact);
            $this->_logAPIRequest(__FUNCTION__, $api, "contactCreateObj", ["contact"=>$contact]);

            $id_tech = $id_billing = $id_admin;
        }
        catch(NetimAPIException $exception) {
            $this->_logAPIRequest(__FUNCTION__, $api, "contactCreateObj", ["contact"=>$contact]);
            throw new Registrar_Exception($exception->getMessage());
        }

        # Request domain registration
        $ns1 = $domain->getNs1() ?? "";
        $ns2 = $domain->getNs2() ?? "";
        $ns3 = $domain->getNs3() ?? "";
        $ns4 = $domain->getNs4() ?? "";
        $ns5 = "";

        try {
            $response = $api->domainCreate($dom, $id_owner, $id_admin, $id_tech, $id_billing, $ns1, $ns2, $ns3, $ns4, $ns5, $domain->getRegistrationPeriod());
        } 
        catch (NetimAPIException $exception) {
            throw new Registrar_Exception($exception->getMessage());
        }
        finally {
            $this->_logAPIRequest(__FUNCTION__, $api, "domainCreate", ["domain"=>$dom, "id_owner"=>$id_owner, "id_admin"=>$id_admin, "id_tech"=>$id_tech, "id_billing"=>$id_billing, "ns1"=>$ns1, "ns2"=>$ns2, "ns3"=>$ns3, "ns4"=>$ns4, "ns5"=>$ns5, "duration"=>$domain->getRegistrationPeriod()]);
        }

        # Process result
        if ($response->STATUS=="Failed"){
            throw new Registrar_Exception($response->MESSAGE);
        }

        /* Pending status is not managed = Feature request to be implemented
        if ($response->STATUS=="Pending")
            throw new Registrar_Exception("The domain registration is pending");
        */

        return true;
    }

    /**
     * Renews a domain registration with the registrar.
     */
    public function renewDomain(Registrar_Domain $domain)
    {
        $this->_logFunction(__FUNCTION__, $domain, null);
        $dom = $domain->getSld() . $domain->getTld();

        # Request domain renewal
        $years = $domain->getRegistrationPeriod();
        $api = $this->_getAPI();
        try {
            $result = $api->domainRenew($dom,$years);
        }
        catch(NetimAPIException $exception) {
            throw new Registrar_Exception($exception->getMessage());
        }
        finally {
            $this->_logAPIRequest(__FUNCTION__, $api, "domainRenew", ["domain"=>$dom,"duration"=>$years]);
        }

        # Process result
        if (!in_array($result->STATUS, ["Done", "Pending"])){ 
            throw new Registrar_Exception($result->MESSAGE);
        }

        /* Pending status is not managed = Feature request to be implemented
        if ($response->STATUS=="Pending")
            throw new Registrar_Exception("The domain renewal is pending");
        */

        return true;
    }

    /**
     * Deletes a domain from the registrar.
     */
    public function deleteDomain(Registrar_Domain $domain)
    {
        $this->_logFunction(__FUNCTION__, $domain, null);
        $dom = $domain->getSld() . $domain->getTld();

        # Request domain delete
        $api = $this->_getAPI();
        try {
            $result = $api->domainDelete($dom);
        }
        catch(NetimAPIException $exception) {
            throw new Registrar_Exception($exception->getMessage());
        }
        finally {
            $this->_logAPIRequest(__FUNCTION__, $api, "domainDelete", ["domain"=>$dom]);
        }

        # Process result
        if(!in_array($result->STATUS, ["Done", "Pending"])){ 
            throw new Registrar_Exception($result->MESSAGE);
        }

        return true;
    }

    /**
     * Enables privacy protection for a domain.
     */
    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
        $this->_logFunction(__FUNCTION__, $domain, null);
        $dom = $domain->getSld() . $domain->getTld();

        # Request domain update
        $api = $this->_getAPI();
        try {
            $result = $api->domainSetWhoisPrivacy($dom,1);
        }
        catch(NetimAPIException $exception) {
            throw new Registrar_Exception($exception->getMessage());
        }
        finally {
            $this->_logAPIRequest(__FUNCTION__, $api, "domainSetWhoisPrivacy", ["domain"=>$dom,"value"=>1]);
        }

        # Process result
        if (!in_array($result->STATUS, ["Done", "Pending"])){ 
            throw new Registrar_Exception($result->MESSAGE);
        }

        return true;
    }

    /**
     * Disables privacy protection for a domain.
     */
    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
        $this->_logFunction(__FUNCTION__, $domain, null);
        $dom = $domain->getSld() . $domain->getTld();

        # Request domain update
        $api = $this->_getAPI();
        try {
            $result = $api->domainSetWhoisPrivacy($dom,0);
        }
        catch(NetimAPIException $exception) {
            throw new Registrar_Exception($exception->getMessage());
        }
        finally {
            $this->_logAPIRequest(__FUNCTION__, $api, "domainSetWhoisPrivacy", ["domain"=>$dom,"value"=>0]);
        }

        # Process result
        if (!in_array($result->STATUS, ["Done", "Pending"])){
            throw new Registrar_Exception($result->MESSAGE);
        }

        return true;
    }

    /**
     * Locks a domain to prevent transfer to another registrar.
     */
    public function lock(Registrar_Domain $domain)
    {
        $this->_logFunction(__FUNCTION__, $domain, null);
        $dom = $domain->getSld() . $domain->getTld();

        # Request domain update
        $api = $this->_getAPI();
        try {
            $result = $api->domainSetRegistrarLock($dom,1);
        }
        catch(NetimAPIException $exception) {
            throw new Registrar_Exception($exception->getMessage());
        }
        finally {
            $this->_logAPIRequest(__FUNCTION__, $api, "domainSetRegistrarLock", ["domain"=>$dom,"value"=>1]);
        }

        # Process result
        if (!in_array($result->STATUS, ["Done", "Pending"])){ 
            throw new Registrar_Exception($result->MESSAGE);
        }

        return true;
    }

    /**
     * Unlocks a domain to allow transfer to another registrar.
     */
    public function unlock(Registrar_Domain $domain)
    {
        $this->_logFunction(__FUNCTION__, $domain, null);
        $dom = $domain->getSld() . $domain->getTld();

        # Request domain update
        $api = $this->_getAPI();
        try {
            $result = $api->domainSetRegistrarLock($dom,0);
        }
        catch(NetimAPIException $exception) {
            throw new Registrar_Exception($exception->getMessage());
        }
        finally {
            $this->_logAPIRequest(__FUNCTION__, $api, "domainSetRegistrarLock", ["domain"=>$dom,"value"=>0]);
        }

        # Process result
        if (!in_array($result->STATUS, ["Done", "Pending"])){ 
            throw new Registrar_Exception($result->MESSAGE);
        }

        return true;
    }

    /**
     * Get the registrar API
     */
    private function _getAPI()
    {
        $username = $this->config["Username"];
        $password = $this->config["Password"];

        try {
            $api = new Netim\APISoap($username, $password, $this->_testMode);

            //Don't update setVersion() call, it used by NETIM to track and follow module usage
            $version = \FOSSBilling_Version::VERSION;
        	$api->setVersion("FOSSB=".$version.",PLUGIN=".self::MODULE_VERSION);

			//Increase synchronization value
			$api->sessionSetPreference("syncDelay",25);
        }
        catch(NetimAPIException $exception) {
            throw new Registrar_Exception($exception->getMessage());
        }

        return $api;
    }

    /**
     * Create a contact object for the Registrar API
     */
    private function _prepareContactInfo(Registrar_Domain $domain,$owner = 1)
    {
        $c = $domain->getContactRegistrar();
        $firstname = $c->getFirstName();
        $lastname = $c->getLastName();
        if ($c->getCompany()) {
            $bodyForm = "ORG";
            $bodyName = $c->getCompany();
        } 
        else {
            $bodyForm = "IND";
            $bodyName = "";
        }
        $companyNumber = "";
        if ($c->getCompany() && !empty($c->getCompanyNumber())) 
            $companyNumber = $c->getCompanyNumber();
        $phone = '+' . $c->getTelCc() . '.' . $c->getTel();
        $area = $c->getState()?$c->getState():"";
        $address1 = $c->getAddress1();
        $address2 = $c->getAddress2();
        $zipCode = $c->getZip();
        $city = $c->getCity();
        $country = $c->getCountry();
        $fax = $c->getFax();
        $email = $c->getEmail();
        $language = "EN";
        $isOwner = $owner;
        $tmName = "";
        $tmNumber = "";
        $tmType = "";
        $tmDate = "";
        $vatNumber = "";
        $birthDate = $c->getBirthday();
        $birthZipCode = "";
        $birthCity = "";
        $birthCountry = "";
        $idNumber = "";
        $additional = [];
        
        # Build Normalized object
        $contact = new NormalizedContact($firstname,$lastname,$bodyName,$address1,$address2,$zipCode,$area,$country,$city,$phone,$email,$language,$isOwner,$tmName,$tmNumber,$tmType,$tmDate,$companyNumber,$vatNumber,$birthDate,$birthZipCode,$birthCity,$birthCountry,$idNumber,$additional);

        return $contact;
    }

    /**
     * Log function calls
     */
    private function _logFunction($fn, $param, $return)
    {
        if(!$this->config['logFunction'])
            return;

        $date = date('Y-m-d:H:i:s');
        $param_str = print_r($param,true);            
        $return_str = print_r($return,true);            

        $str = "function $fn\n";
        $str.= "----> param: $param_str \n";
        $str.= "<---- response: $return_str";

        $this->_log($str);
    }

    /**
     * Log API calls
     */
    private function _logAPIRequest($fn, \Netim\APISoap $api, string $fnapi, $fnapireq)
    {
        if(!$this->config['logAPI'])
            return;

        $date = date('Y-m-d:H:i:s');
        $request_str = print_r($fnapireq,true);            
        if(empty($api->getLastError()))
            $response = $api->getLastResponse();
        else
            $response = $api->getLastError();   
        $response_str = print_r($response,true); 
 
        $str = "$fn (API $fnapi) \n";
        $str.= "----> param: $request_str".(is_string($fnapireq)?"\n":"");
        $str.= "<---- response: $response_str".(is_string($response)?"\n":"");

        $this->_log($str);
    }

    /**
     * Write logs into file
     */
    private function _log($str)
    {
        $date = date('Y-m-d:H:i:s');
        $str = $date . " ".$str;

        if (!file_exists(self::PATH_LOG.'/'.self::DIR_LOG))
            mkdir(self::PATH_LOG.'/'.self::DIR_LOG, 0755);    

        $file = fopen(self::PATH_LOG.'/'.self::DIR_LOG.'/'.self::FILE_LOG,'a');
        if($file)
        {
            fwrite($file,$str);
            fclose($file);
        }
    }

    /**
     * Check if an operation for a given domain name is already pending at Netim
     */
    private function _isAlreadyPending(string $domain, string $codeope)
    {
        $opepending = [];

        # Get all pending operations
        $api = $this->_getAPI();
        try{            
            $opepending = $api->queryOpePending();
        }catch(NetimAPIException $exception){
            throw new Registrar_Exception($exception->getMessage());
        }

        # Check the domain
        foreach($opepending as $key => $ope){
            if($ope->data_ope == $domain && $ope->code_ope==$codeope)
                return true;
        }

        return false;
    }
}
