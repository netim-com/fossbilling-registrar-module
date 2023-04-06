<?php 

namespace Netim
{
	require_once __DIR__ . '/Contact.php';
	require_once __DIR__ . '/NetimAPIException.php';
    require_once __DIR__ . '/AbstractAPISoap.php';

	ini_set("soap.wsdl_cache_enabled", "0");

	class APISoap extends AbstractAPISoap
	{
        const OTE_URL = 'http://oteapi.netim.com/2.0/api.wsdl';
		const PROD_URL = 'http://api.netim.com/2.0/api.wsdl';

        private $_infosVersion;

        public function __construct($userID, $password, $sandbox=false)
        {
            $this->_userID = $userID;
            $this->_password = $password;
            $this->_defaultLanguage = "EN";
            if($sandbox)
                $apiURL = $this::OTE_URL;
            else
                $apiURL = $this::PROD_URL;
            
            parent::__construct($userID, $password, $apiURL, $this->_defaultLanguage);
        }

        public function setVersion(string $version){$this->_infosVersion=$version; }
        public function getVersion(){ return $this->_infosVersion;}

        public function sessionOpen():void
        {
            // Redefined function to login with the version parameter
            $params[] = strtoupper($this->_userID);
            $params[] = $this->_password;
            $params[] = $this->_defaultLanguage;
            if (!empty($this->getVersion())) 
            {
                $params[] = $this->getVersion();
                $this->_launchCommand('login', $params);
            }
            else
                $this->_launchCommand('sessionOpen', $params);
        }

        public function contactCreateObj(NormalizedContact $contact) {
            // Redefined function to use the Contact object instead an array    
            return parent::contactCreate($contact->to_array());
        }

        public function contactInfoObj(string $idContact) {
            // Redefined function to get Contact object instead an array
            $c = Contact::object_to_contact(parent::contactInfo($idContact));
            $c->denormalization();
			return $c;
        }

        public function contactUpdateObj(string $idContact, NormalizedContact $datas) {
            // Redefined function to use the Contact object instead an array
            return parent::contactUpdate($idContact, $datas->to_array());
        }

        public function contactOwnerUpdateObj(string $idContact, NormalizedContact $datas) {
            // Redefined function to use the Contact object instead an array    
            return parent::contactOwnerUpdate($idContact, $datas->to_array());
        }
    }
}